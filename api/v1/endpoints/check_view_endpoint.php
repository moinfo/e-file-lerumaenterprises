<?php
/**
 * Check if viewFile function is updated
 * Upload to production and access directly
 */

header('Content-Type: application/json');

// Include necessary files
require_once '../../../config.php';
require_once '../../../models/DB.php';

$fileId = 115; // The failing file from the error

$db = new DB();

// Get file info
try {
    $result = $db->fetchQuery("SELECT * FROM archives WHERE id = $fileId");
    $file = $result ? $result[0] : null;
} catch (Exception $e) {
    echo json_encode([
        'error' => 'DB query failed',
        'message' => $e->getMessage()
    ]);
    exit;
}

if (!$file) {
    echo json_encode([
        'error' => 'File not found in database',
        'file_id' => $fileId
    ]);
    exit;
}

$dbPath = $file['path'];

// Test the path resolution that should be in the updated viewFile function
$pathTests = [];

// Old way (wrong)
$oldPath = '../../../' . $dbPath;
$pathTests['old_way'] = [
    'path' => $oldPath,
    'exists' => file_exists($oldPath),
    'real_path' => realpath($oldPath) ?: 'cannot resolve'
];

// New way (correct)
if (strpos($dbPath, '../') === 0) {
    $relativePath = substr($dbPath, 3);
    $newPath = '../../../../' . $relativePath;
} else {
    $newPath = '../../../../' . $dbPath;
}

$pathTests['new_way'] = [
    'path' => $newPath,
    'exists' => file_exists($newPath),
    'real_path' => realpath($newPath) ?: 'cannot resolve'
];

if (file_exists($newPath)) {
    $pathTests['new_way']['size'] = filesize($newPath);
    $pathTests['new_way']['readable'] = is_readable($newPath);
}

// Check the actual viewFile function code
$filesPhpPath = __DIR__ . '/files.php';
$filesPhpContent = file_get_contents($filesPhpPath);

// Look for the path resolution code in viewFile
$hasOldCode = strpos($filesPhpContent, "filePath = '../../../' . \$dbPath") !== false;
$hasNewCode = strpos($filesPhpContent, "filePath = '../../../../' . \$relativePath") !== false;

echo json_encode([
    'file_id' => $fileId,
    'file' => [
        'name' => $file['name'],
        'path' => $file['path']
    ],
    'path_tests' => $pathTests,
    'files_php_status' => [
        'has_old_code' => $hasOldCode,
        'has_new_code' => $hasNewCode,
        'file_exists' => file_exists($filesPhpPath),
        'last_modified' => date('Y-m-d H:i:s', filemtime($filesPhpPath))
    ],
    'current_dir' => __DIR__,
    'server_info' => [
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown'
    ]
], JSON_PRETTY_PRINT);
