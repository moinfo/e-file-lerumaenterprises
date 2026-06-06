<?php
/**
 * Check files.php version to verify it's been updated
 */

header('Content-Type: application/json');

$filesPhpPath = __DIR__ . '/files.php';

if (!file_exists($filesPhpPath)) {
    echo json_encode([
        'error' => 'files.php not found',
        'path' => $filesPhpPath
    ]);
    exit;
}

$content = file_get_contents($filesPhpPath);

// Check for specific code patterns to verify version
$checks = [
    'has_viewFile_function' => strpos($content, 'function viewFile(') !== false,
    'uses_fetchQuery' => strpos($content, '$db->fetchQuery(') !== false,
    'has_old_query_method' => strpos($content, "\$file = \$db->query(\"SELECT * FROM archives WHERE id = ?\", 'SELECT', 'ROW', [\$id])") !== false,
    'has_path_resolution' => strpos($content, "../../../../") !== false,
    'has_new_path_logic' => strpos($content, "substr(\$dbPath, 3)") !== false,
];

// Count occurrences
$fetchQueryCount = substr_count($content, '$db->fetchQuery(');
$queryMethodCount = substr_count($content, '$db->query(');

echo json_encode([
    'file' => 'files.php',
    'exists' => true,
    'path' => $filesPhpPath,
    'last_modified' => date('Y-m-d H:i:s', filemtime($filesPhpPath)),
    'file_size' => filesize($filesPhpPath),
    'checks' => $checks,
    'stats' => [
        'fetchQuery_count' => $fetchQueryCount,
        'query_method_count' => $queryMethodCount,
    ],
    'verdict' => $checks['uses_fetchQuery'] && $checks['has_new_path_logic']
        ? '✓ UPDATED - Has new code'
        : '✗ OLD VERSION - Needs update'
], JSON_PRETTY_PRINT);
