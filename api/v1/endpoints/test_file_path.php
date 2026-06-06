<?php
/**
 * Test file path resolution
 */

// Simulate being in /api/v1/endpoints/
$dbPath = '../allfiles/pf-archives/1731587795_BAKHRESA FOOD SODA 13.11.2024 AMOUNT 1,766,800.pdf';

// New logic: Remove ../ prefix and use ../../../../
if (strpos($dbPath, '../') === 0) {
    $relativePath = substr($dbPath, 3);
    $filePath = '../../../../' . $relativePath;
} else {
    $filePath = '../../../../' . $dbPath;
}

echo "DB Path: " . $dbPath . "\n";
echo "Constructed Path: " . $filePath . "\n";
echo "Resolved Path: " . realpath($filePath) . "\n";
echo "File Exists: " . (file_exists($filePath) ? 'YES' : 'NO') . "\n";

if (file_exists($filePath)) {
    echo "File Size: " . filesize($filePath) . " bytes\n";
    echo "File is readable: " . (is_readable($filePath) ? 'YES' : 'NO') . "\n";
} else {
    echo "\nTrying to find the file...\n";

    // Try different paths
    $alternatives = [
        '../../' . $dbPath,
        '../../../../' . str_replace('../', '', $dbPath),
        '../../../allfiles/pf-archives/20231101120409.pdf',
        '../../../../allfiles/pf-archives/20231101120409.pdf',
    ];

    foreach ($alternatives as $alt) {
        echo "\nTrying: $alt\n";
        echo "  Resolves to: " . realpath($alt) . "\n";
        echo "  Exists: " . (file_exists($alt) ? 'YES' : 'NO') . "\n";
    }
}
