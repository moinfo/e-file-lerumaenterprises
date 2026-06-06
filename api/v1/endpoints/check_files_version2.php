<?php
/**
 * Check if files.php has the header clearing fix
 */

header('Content-Type: text/plain');

$filesPhpPath = __DIR__ . '/files.php';

if (!file_exists($filesPhpPath)) {
    echo "ERROR: files.php not found at $filesPhpPath\n";
    exit;
}

$content = file_get_contents($filesPhpPath);

echo "=== FILES.PHP VERSION CHECK ===\n\n";

echo "File: $filesPhpPath\n";
echo "Last Modified: " . date('Y-m-d H:i:s', filemtime($filesPhpPath)) . "\n";
echo "File Size: " . filesize($filesPhpPath) . " bytes\n\n";

// Check for the critical fix
$hasObEndClean = strpos($content, 'ob_end_clean()') !== false;
$hasHeaderRemove = strpos($content, 'header_remove()') !== false;
$hasBufferClearComment = strpos($content, 'Clear any output buffers') !== false;

echo "=== CRITICAL FIX CHECKS ===\n";
echo "Has ob_end_clean(): " . ($hasObEndClean ? '✓ YES' : '✗ NO') . "\n";
echo "Has header_remove(): " . ($hasHeaderRemove ? '✓ YES' : '✗ NO') . "\n";
echo "Has buffer clear comment: " . ($hasBufferClearComment ? '✓ YES' : '✗ NO') . "\n\n";

if ($hasObEndClean && $hasHeaderRemove) {
    echo "✓✓✓ SUCCESS! File has the header clearing fix!\n\n";

    // Show the exact code
    echo "=== viewFile() HEADER CLEARING CODE ===\n";
    preg_match('/while \(ob_get_level\(\)\).*?header_remove\(\);\s*}/s', $content, $matches);
    if ($matches) {
        echo $matches[0] . "\n";
    }
} else {
    echo "✗✗✗ ERROR! File does NOT have the fix!\n";
    echo "You may have uploaded the wrong version or the upload failed.\n\n";
    echo "The file should contain these lines in viewFile():\n";
    echo "while (ob_get_level()) {\n";
    echo "    ob_end_clean();\n";
    echo "}\n";
    echo "if (function_exists('header_remove')) {\n";
    echo "    header_remove();\n";
    echo "}\n";
}

echo "\n=== OTHER CHECKS ===\n";
echo "Has viewFile function: " . (strpos($content, 'function viewFile(') !== false ? 'YES' : 'NO') . "\n";
echo "Has downloadFile function: " . (strpos($content, 'function downloadFile(') !== false ? 'YES' : 'NO') . "\n";
echo "Has nested array handling: " . (strpos($content, 'if (is_array($file) && isset($file[0]))') !== false ? 'YES' : 'NO') . "\n";
