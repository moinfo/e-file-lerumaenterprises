<?php
header('Content-Type: application/json');
require_once('./config.php');

try {
    // Test DB connection
    $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($db->connect_error) {
        throw new Exception("Database connection failed: " . $db->connect_error);
    }
    
    // Test upload directory
    $upload_dir = 'public/uploads/';
    if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
        throw new Exception("Upload directory not writable");
    }
    
    echo json_encode(['success' => true]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>