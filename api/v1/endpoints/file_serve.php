<?php
/**
 * File Serve Endpoint
 *
 * Securely serves files from outside the web root
 * Handles both local development and production paths
 */

function handleFileServe($method, $id, $action, $input) {
    // Only allow GET requests
    if ($method !== 'GET') {
        ApiResponse::error('Method not allowed', 405);
    }

    // Validate authentication
    $user = ApiAuth::validateToken();

    // Get file path from query parameter
    if (!isset($_GET['path']) || empty($_GET['path'])) {
        ApiResponse::error('File path is required', 400);
    }

    $requestedPath = $_GET['path'];

    // Clean the path to prevent directory traversal
    $requestedPath = str_replace(['../', '..\\'], '', $requestedPath);

    // Determine if we're in production or development
    $isProduction = false;
    $documentRoot = $_SERVER['DOCUMENT_ROOT'];

    // Debug logging
    error_log("=== File Serve Debug ===");
    error_log("Document Root: " . $documentRoot);
    error_log("Requested Path: " . $requestedPath);

    // Check if we're in production (adjust this check based on your server setup)
    if (strpos($documentRoot, 'public_html') !== false ||
        strpos($documentRoot, 'e-file.lerumaenterprises.co.tz') !== false) {
        $isProduction = true;
    }

    error_log("Environment: " . ($isProduction ? 'PRODUCTION' : 'DEVELOPMENT'));

    // Build the full file path
    if ($isProduction) {
        // Production: files are in /home/username/allfiles/
        // Web root is /home/username/public_html/e-file.lerumaenterprises.co.tz/
        $baseDir = dirname(dirname($documentRoot)) . '/allfiles/';
        $filePath = $baseDir . str_replace('../allfiles/', '', $requestedPath);
    } else {
        // Development: files are in /htdocs/allfiles/
        // Web root is /htdocs/e-file/
        $baseDir = dirname($documentRoot) . '/allfiles/';
        $filePath = $baseDir . str_replace('../allfiles/', '', $requestedPath);
    }

    error_log("Base Directory: " . $baseDir);
    error_log("Full File Path: " . $filePath);
    error_log("File Exists: " . (file_exists($filePath) ? 'YES' : 'NO'));
    error_log("File Readable: " . (is_readable($filePath) ? 'YES' : 'NO'));

    // Verify the file exists and is readable
    if (!file_exists($filePath) || !is_readable($filePath)) {
        error_log("ERROR: File not found or not readable: $filePath");

        // Return detailed error for debugging
        ApiResponse::error('File not found', 404, [
            'requested_path' => $requestedPath,
            'document_root' => $documentRoot,
            'base_dir' => $baseDir,
            'full_path' => $filePath,
            'environment' => $isProduction ? 'production' : 'development',
            'file_exists' => file_exists($filePath),
            'is_readable' => is_readable($filePath)
        ]);
    }

    // Verify file is within allowed directory (security check)
    $realPath = realpath($filePath);
    $realBaseDir = realpath($baseDir);

    if ($realPath === false || strpos($realPath, $realBaseDir) !== 0) {
        error_log("Security violation: attempted to access file outside allowed directory");
        ApiResponse::error('Access denied', 403);
    }

    // Get file info
    $fileSize = filesize($filePath);
    $fileName = basename($filePath);

    // Determine MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);

    // If MIME type detection fails, use default
    if (!$mimeType) {
        $mimeType = 'application/octet-stream';
    }

    // Set headers for file serving
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    header('Cache-Control: public, max-age=3600');
    header('Accept-Ranges: bytes');

    // Handle range requests for video/audio streaming
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        $range = str_replace('bytes=', '', $range);
        $range = explode('-', $range);
        $start = intval($range[0]);
        $end = isset($range[1]) && $range[1] ? intval($range[1]) : $fileSize - 1;

        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        header('Content-Length: ' . ($end - $start + 1));

        $fp = fopen($filePath, 'rb');
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
        readfile($filePath);
    }

    exit;
}
