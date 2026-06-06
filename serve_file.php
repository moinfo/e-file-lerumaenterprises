<?php
/**
 * Secure File Server
 * Serves files from outside the web root
 */
require_once('./config.php');

// Get the requested file path
$requestedPath = isset($_GET['file']) ? urldecode($_GET['file']) : '';

if (empty($requestedPath)) {
    http_response_code(400);
    die('No file specified');
}

// Clean the path to prevent directory traversal
$requestedPath = str_replace(['../', '..\\'], '', $requestedPath);

// Use FILES_PATH from config
$baseDir = FILES_PATH;

// Remove allfiles from path if present (it's already in baseDir)
$cleanPath = str_replace('allfiles/', '', $requestedPath);
$filePath = $baseDir . $cleanPath;

// Debug mode - remove in production after testing
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain');
    echo "Document Root: $documentRoot\n";
    echo "Is Production: " . ($isProduction ? 'YES' : 'NO') . "\n";
    echo "Base Dir: $baseDir\n";
    echo "Requested Path: $requestedPath\n";
    echo "Clean Path: $cleanPath\n";
    echo "File Path: $filePath\n";
    echo "File Exists: " . (file_exists($filePath) ? 'YES' : 'NO') . "\n";
    echo "Real Path: " . (realpath($filePath) ?: 'FALSE') . "\n";
    echo "Real Base Dir: " . (realpath($baseDir) ?: 'FALSE') . "\n";
    exit;
}

// Security check: verify file is within allowed directory
$realPath = realpath($filePath);
$realBaseDir = realpath($baseDir);

if ($realPath === false || strpos($realPath, $realBaseDir) !== 0) {
    http_response_code(403);
    error_log("Security violation: attempted to access file outside allowed directory - $filePath");
    die('Access denied');
}

// Check if file exists
if (!file_exists($realPath) || !is_readable($realPath)) {
    http_response_code(404);
    error_log("File not found: $realPath");
    die('File not found');
}

// Get file info
$fileSize = filesize($realPath);
$fileName = basename($realPath);

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $realPath);
finfo_close($finfo);

if (!$mimeType) {
    $mimeType = 'application/octet-stream';
}

// Set headers for file serving
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Cache-Control: public, max-age=3600');
header('Accept-Ranges: bytes');

// Handle range requests for PDF streaming
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $range = str_replace('bytes=', '', $range);
    $range = explode('-', $range);
    $start = intval($range[0]);
    $end = isset($range[1]) && $range[1] ? intval($range[1]) : $fileSize - 1;

    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    header('Content-Length: ' . ($end - $start + 1));

    $fp = fopen($realPath, 'rb');
    fseek($fp, $start);
    $buffer = 8192;
    $bytesLeft = $end - $start + 1;

    while ($bytesLeft > 0 && !feof($fp)) {
        $read = ($bytesLeft > $buffer) ? $buffer : $bytesLeft;
        echo fread($fp, $read);
        $bytesLeft -= $read;
        flush();
    }

    fclose($fp);
} else {
    // Normal file serving
    readfile($realPath);
}

exit;
