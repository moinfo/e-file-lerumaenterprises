<?php
/**
 * Test the actual view endpoint flow
 * This simulates what happens when the mobile app calls /files/115/view
 */

// Include necessary files
require_once '../../../config.php';
require_once '../../../models/DB.php';

// Include API Response class
class ApiResponse {
    public static function success($data = [], $message = 'Success', $code = 200) {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        exit();
    }

    public static function error($message = 'Error', $code = 400, $errors = []) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        exit();
    }
}

// Test file ID
$id = 115;

$db = new DB();

// Get file info (same as viewFile function)
$file = $db->query("SELECT * FROM archives WHERE id = ?", 'SELECT', 'ROW', [$id]);

if (!$file) {
    ApiResponse::error('File not found', 404);
}

// Path resolution (from updated viewFile function)
$dbPath = $file['path'];

if (strpos($dbPath, '../') === 0) {
    $relativePath = substr($dbPath, 3);
    $filePath = '../../../../' . $relativePath;
} else {
    $filePath = '../../../../' . $dbPath;
}

// Check if file exists
if (!file_exists($filePath)) {
    ApiResponse::error('Physical file not found: ' . $filePath, 404);
}

// Get file info
$fileSize = filesize($filePath);
$realPath = realpath($filePath);

// If we got here, everything should work
ApiResponse::success([
    'message' => 'File can be served successfully',
    'file' => [
        'id' => $file['id'],
        'name' => $file['name'],
        'db_path' => $dbPath,
        'resolved_path' => $filePath,
        'real_path' => $realPath,
        'size' => $fileSize,
        'exists' => true,
        'readable' => is_readable($filePath)
    ]
], 'Test successful - file should be viewable');
