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

// Sanitize — strip traversal sequences; treat as relative to FILES_PATH
$file = ltrim(str_replace(['../', '..' . DIRECTORY_SEPARATOR], '', $file), '/\\');
$file = str_replace('allfiles/', '', $file);

$filePath = rtrim(FILES_PATH, '/') . '/' . $file;
$realPath = realpath($filePath);
$realBase = realpath(rtrim(FILES_PATH, '/')) . DIRECTORY_SEPARATOR;

if ($realPath === false || strncmp($realPath, $realBase, strlen($realBase)) !== 0
    || !is_readable($realPath)) {
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
header('Content-Length: ' . filesize($realPath));
header('Content-Disposition: inline; filename="' . basename($realPath) . '"');
header('Cache-Control: public, max-age=86400');

// Output file
readfile($realPath);
exit;
