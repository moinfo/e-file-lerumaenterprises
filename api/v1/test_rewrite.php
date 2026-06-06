<?php
/**
 * Test rewrite rules
 */

header('Content-Type: application/json');

echo json_encode([
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
    'php_self' => $_SERVER['PHP_SELF'] ?? 'unknown',
    'query_string' => $_SERVER['QUERY_STRING'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
    'path_info' => $_SERVER['PATH_INFO'] ?? 'none',
    'http_host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
], JSON_PRETTY_PRINT);
