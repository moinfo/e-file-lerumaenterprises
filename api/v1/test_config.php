<?php
/**
 * Test config.php loading
 */

header('Content-Type: application/json');

$response = [
    'status' => 'Testing',
    'document_root' => $_SERVER['DOCUMENT_ROOT'],
];

// Test if config.php exists
$configPath = '../../config.php';
$fullConfigPath = dirname(__FILE__) . '/' . $configPath;

$response['config_relative_path'] = $configPath;
$response['config_full_path'] = $fullConfigPath;
$response['config_exists'] = file_exists($fullConfigPath) ? 'YES' : 'NO';
$response['config_readable'] = is_readable($fullConfigPath) ? 'YES' : 'NO';

// Try to include it
try {
    require_once $fullConfigPath;
    $response['config_loaded'] = 'SUCCESS';

    // Check if DB class exists
    $dbPath = dirname(__FILE__) . '/../../models/DB.php';
    $response['db_path'] = $dbPath;
    $response['db_exists'] = file_exists($dbPath) ? 'YES' : 'NO';

    if (file_exists($dbPath)) {
        require_once $dbPath;
        $response['db_loaded'] = 'SUCCESS';

        // Try to instantiate DB
        $db = new DB();
        $response['db_instance'] = 'SUCCESS';
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $response['trace'] = $e->getTraceAsString();
} catch (Error $e) {
    $response['error'] = $e->getMessage();
    $response['trace'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_PRETTY_PRINT);
