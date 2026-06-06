<?php
/**
 * Simple API Test Script
 * Upload this to production at /api/v1/test.php
 * Then access: https://e-file.lerumaenterprises.co.tz/api/v1/test.php
 */

// Force JSON output
header('Content-Type: application/json');

$response = [
    'status' => 'OK',
    'message' => 'API is reachable',
    'php_version' => phpversion(),
    'current_time' => date('Y-m-d H:i:s'),
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
];

// Test database connection
try {
    require_once '../../config.php';
    require_once '../../models/DB.php';

    $db = new DB();
    $result = $db->query("SELECT COUNT(*) as count FROM users", 'ROW', false);

    $response['database'] = 'Connected';
    $response['user_count'] = $result['count'] ?? 0;
} catch (Exception $e) {
    $response['database'] = 'ERROR: ' . $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
