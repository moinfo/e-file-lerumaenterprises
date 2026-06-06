<?php
ob_start();
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once ("../config.php");
require_once ("../models/DB.php");
require_once ("./models/Utility.php");

$db = new DB();

// Check if user is logged in
session_start();
if (!isset($_SESSION[SESSION_NAME]['user_id'])) {
    die("Unauthorized access");
}

// Get all uploads
$uploads = Utility::selectAll('uploads');
$filtered_uploads = $uploads;

// Apply filters
if (!empty($_GET['system'])) {
    $filtered_uploads = array_filter($filtered_uploads, function($upload) {
        return $upload['system'] == $_GET['system'];
    });
}

if (!empty($_GET['category'])) {
    $filtered_uploads = array_filter($filtered_uploads, function($upload) {
        return $upload['category'] == $_GET['category'];
    });
}

if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $filtered_uploads = array_filter($filtered_uploads, function($upload) {
        $upload_date = strtotime($upload['uploaded_time']);
        $start_date = strtotime($_GET['start_date']);
        $end_date = strtotime($_GET['end_date'] . ' 23:59:59');
        return $upload_date >= $start_date && $upload_date <= $end_date;
    });
}

// Create temporary zip file
$temp_file = tempnam(sys_get_temp_dir(), 'zip');
$zip = new ZipArchive();

if ($zip->open($temp_file, ZipArchive::CREATE) !== TRUE) {
    die("Cannot create zip file");
}

// Add files to zip
$fileCount = 0;
foreach ($filtered_uploads as $upload) {
    $filepath = $upload['path'];
    if (file_exists($filepath)) {
        // Get just the filename from the path
        $filename = basename($filepath);
        // Add file to zip
        $zip->addFile($filepath, $filename);
        $fileCount++;
    }
}

$zip->close();

// Check if any files were added
if ($fileCount == 0) {
    unlink($temp_file);
    die("No files found matching the criteria");
}

// Send the file to the browser
$zipName = 'documents_' . date('Y-m-d_His') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($temp_file));
header('Pragma: no-cache');
header('Expires: 0');

readfile($temp_file);

// Clean up
unlink($temp_file);
exit;
?>