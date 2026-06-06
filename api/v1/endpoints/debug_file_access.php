<?php
/**
 * Debug File Access for Production
 * Upload this to /api/v1/endpoints/ and access via browser
 */

header('Content-Type: application/json');

// Test file ID from the error
$fileId = 1840;

// Include DB connection
require_once '../../../config.php';
require_once '../../../models/DB.php';

$db = new DB();

// Get file info - use fetchQuery for production compatibility
try {
    $result = $db->fetchQuery("SELECT * FROM archives WHERE id = $fileId");
    $file = $result ? $result[0] : null;
} catch (Exception $e) {
    $debug['db_error'] = $e->getMessage();
    $file = null;
}

$debug = [
    'file_id' => $fileId,
    'file_found_in_db' => $file ? true : false,
];

if ($file) {
    $debug['file_data'] = [
        'id' => $file['id'],
        'name' => $file['name'],
        'path' => $file['path'],
    ];

    $dbPath = $file['path'];
    $debug['db_path'] = $dbPath;

    // Test different path resolutions
    $paths_to_test = [
        'current_dir' => __DIR__,
        'document_root' => $_SERVER['DOCUMENT_ROOT'],
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
    ];

    // Try different combinations
    $attempts = [
        'attempt_1' => '../../../' . $dbPath,
        'attempt_2' => '../../' . $dbPath,
        'attempt_3' => '../../../../' . str_replace('../', '', $dbPath),
        'attempt_4' => '../../../allfiles/pf-archives/' . basename($dbPath),
    ];

    foreach ($attempts as $key => $path) {
        $paths_to_test[$key] = [
            'path' => $path,
            'exists' => file_exists($path),
            'is_readable' => file_exists($path) ? is_readable($path) : false,
            'real_path' => realpath($path) ?: 'cannot resolve',
        ];

        if (file_exists($path)) {
            $paths_to_test[$key]['size'] = filesize($path);
        }
    }

    $debug['path_tests'] = $paths_to_test;

    // Check if allfiles directory exists relative to endpoints
    $allfiles_checks = [
        '../../../allfiles' => file_exists('../../../allfiles'),
        '../../../../allfiles' => file_exists('../../../../allfiles'),
        '../../../../../allfiles' => file_exists('../../../../../allfiles'),
        '../../../allfiles/pf-archives' => file_exists('../../../allfiles/pf-archives'),
        '../../../../allfiles/pf-archives' => file_exists('../../../../allfiles/pf-archives'),
        '../../../../../allfiles/pf-archives' => file_exists('../../../../../allfiles/pf-archives'),
    ];

    $debug['allfiles_checks'] = $allfiles_checks;

    // Try to list some files if we find the directory
    foreach (['../../../', '../../../../', '../../../../../'] as $prefix) {
        $testDir = $prefix . 'allfiles/pf-archives';
        if (is_dir($testDir)) {
            $debug['found_dir_at'] = $prefix;
            $debug['found_dir_real_path'] = realpath($testDir);
            $files = scandir($testDir);
            $debug['sample_files'] = array_slice($files, 2, 5); // Skip . and ..
            break;
        }
    }
}

echo json_encode($debug, JSON_PRETTY_PRINT);
