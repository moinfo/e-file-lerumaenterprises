<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('./config.php');
require_once('./models/Autoload.php');

// Check if user is logged in
if (!isset($_SESSION[SESSION_NAME]['user_id'])) {
    // For embedded PDFs, check if request comes from same site
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $baseUrlHost = parse_url(BASE_URL, PHP_URL_HOST);
    $refererHost = parse_url($referer, PHP_URL_HOST);

    if ($refererHost !== $baseUrlHost) {
        http_response_code(401);
        exit('Unauthorized');
    }
}

// Get file path from query parameter
$file = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($file)) {
    http_response_code(400);
    exit('File not specified');
}

// Sanitize file path to prevent directory traversal attacks
$file = basename($file);

// Build the actual file path (allfiles is outside project folder)
$filePath = '../allfiles/pf-archives/' . $file;

// Check if file exists
if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found');
}

// Get file extension
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

// Set appropriate content type
$contentTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif'
];

$contentType = isset($contentTypes[$ext]) ? $contentTypes[$ext] : 'application/octet-stream';

// Set headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $file . '"');
header('Cache-Control: public, max-age=86400');

// Output file
readfile($filePath);
exit;
