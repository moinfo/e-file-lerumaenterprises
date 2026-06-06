<?php
/**
 * Ultra Simple Test - No dependencies
 * Upload to production at /api/v1/test_simple.php
 */

header('Content-Type: application/json');

echo json_encode([
    'status' => 'OK',
    'message' => 'PHP is working',
    'php_version' => phpversion(),
    'time' => date('Y-m-d H:i:s'),
    'document_root' => isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'unknown',
    'script_path' => isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : 'unknown',
    'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown',
], JSON_PRETTY_PRINT);
