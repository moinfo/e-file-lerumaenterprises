<?php
ob_start();
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once ("../config.php");
require_once ("../models/DB.php");

$db = new DB();
$user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = $db->query("SELECT * FROM users WHERE id='{$user_id}'", 'SELECT', 1);
header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No backup ID provided']);
    exit;
}

$db = new DB();
$backup_id = (int)$_GET['id'];

// Get backup status
$query = "SELECT status, progress, notes, file_name FROM backup_history WHERE id = " . $backup_id;
$result = $db->query($query, 'SELECT');

if (!$result) {
    echo json_encode(['error' => 'Backup not found']);
    exit;
}

$backup = $result[0];

// Check if the backup file exists when status is completed
if ($backup['status'] === 'completed') {
    $backup_file = '../backups/' . $backup['file_name'];
    if (!file_exists($backup_file)) {
        // Update status to failed if file doesn't exist
        $db->query("UPDATE backup_history SET 
            status = 'failed', 
            notes = 'Backup file not found' 
            WHERE id = " . $backup_id);

        $backup['status'] = 'failed';
        $backup['notes'] = 'Backup file not found';
    }
}

// Check for timeout
$timeout_minutes = 30; // Adjust this value based on your needs
$query = "SELECT created_at FROM backup_history WHERE id = " . $backup_id;
$result = $db->query($query, 'SELECT');
$created_at = strtotime($result[0]['created_at']);

if ($backup['status'] === 'processing' && (time() - $created_at) > ($timeout_minutes * 60)) {
    $db->query("UPDATE backup_history SET 
        status = 'failed', 
        notes = 'Backup process timed out' 
        WHERE id = " . $backup_id);

    $backup['status'] = 'failed';
    $backup['notes'] = 'Backup process timed out';
}

echo json_encode($backup);