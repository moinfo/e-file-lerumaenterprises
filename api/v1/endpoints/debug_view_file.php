<?php
/**
 * Debug View File Endpoint
 * Test file viewing to see exact error details
 *
 * Usage: /api/v1/endpoints/debug_view_file.php?file_id=41
 */

header('Content-Type: application/json');

// Include config and models
require_once '../../../config.php';
require_once '../../../models/DB.php';

$fileId = $_GET['file_id'] ?? 41;

$db = new DB();

// Step 1: Check if file exists in database
echo "=== STEP 1: Database Query ===\n";
$query = "SELECT * FROM archives WHERE id = ?";
echo "Query: $query\n";
echo "File ID: $fileId\n\n";

$file = $db->query($query, 'SELECT', 'ROW', [$fileId]);

echo "Query Result Type: " . gettype($file) . "\n";
echo "Is Array: " . (is_array($file) ? 'Yes' : 'No') . "\n";
if (is_array($file)) {
    echo "Array Keys: " . implode(', ', array_keys($file)) . "\n";
    echo "Has [0] index: " . (isset($file[0]) ? 'Yes' : 'No') . "\n";
}
echo "\nRaw Result:\n";
print_r($file);
echo "\n\n";

if (!$file) {
    echo "ERROR: File not found in database\n";
    exit;
}

// Step 2: Handle different DB return formats
echo "=== STEP 2: Handle DB Format ===\n";
if (is_array($file) && isset($file[0])) {
    echo "Detected nested array format, extracting file[0]\n";
    $file = $file[0];
}
echo "File after format handling:\n";
print_r($file);
echo "\n\n";

// Step 3: Get file path from database
echo "=== STEP 3: Extract Path ===\n";
$dbPath = $file['path'] ?? null;
echo "DB Path: " . ($dbPath ?? 'NULL') . "\n";

if (!$dbPath) {
    echo "ERROR: File path not found in database record\n";
    exit;
}
echo "\n";

// Step 4: Resolve file path
echo "=== STEP 4: Path Resolution ===\n";
echo "Current directory: " . __DIR__ . "\n";
echo "DB Path: $dbPath\n";

if (strpos($dbPath, '../') === 0) {
    $relativePath = substr($dbPath, 3); // Remove '../'
    $filePath = '../../../../' . $relativePath;
    echo "Path starts with ../, removing it\n";
    echo "Relative path: $relativePath\n";
} else {
    $filePath = '../../../../' . $dbPath;
    echo "Path does not start with ../\n";
}

echo "Resolved file path: $filePath\n";

// Step 5: Check if file exists
echo "\n=== STEP 5: File Existence Check ===\n";
echo "file_exists(): " . (file_exists($filePath) ? 'YES' : 'NO') . "\n";

if (!file_exists($filePath)) {
    echo "realpath(): " . (realpath($filePath) ?: 'FALSE') . "\n";

    // Try different path variations
    echo "\n=== Trying Alternative Paths ===\n";

    $alternatives = [
        '../../../' . $relativePath,
        '../../' . $relativePath,
        '../' . $relativePath,
        $dbPath,
        '../../' . $dbPath,
        '../../../' . $dbPath,
    ];

    foreach ($alternatives as $altPath) {
        echo "$altPath => " . (file_exists($altPath) ? 'EXISTS' : 'NOT FOUND') . "\n";
    }

    echo "\nERROR: Physical file not found at any tested path\n";
    exit;
}

echo "realpath(): " . realpath($filePath) . "\n";
echo "filesize(): " . filesize($filePath) . " bytes\n";
echo "is_readable(): " . (is_readable($filePath) ? 'YES' : 'NO') . "\n";

// Step 6: Get MIME type
echo "\n=== STEP 6: MIME Type ===\n";
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);
echo "MIME Type: $mimeType\n";

echo "\n=== SUCCESS ===\n";
echo "File can be served successfully!\n";
echo "All checks passed.\n";
