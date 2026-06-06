<?php
/**
 * Test Dashboard Endpoint
 * Direct test without authentication
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$response = ['status' => 'testing'];

try {
    // Load config and DB
    require_once '../../config.php';
    require_once '../../models/DB.php';

    $response['config_loaded'] = 'yes';

    $db = new DB();
    $response['db_created'] = 'yes';

    // Test with fetchQuery method instead
    $result = $db->fetchQuery("SELECT COUNT(*) as count FROM archives");
    $response['query_result'] = $result;
    $response['archives_count'] = $result[0]['count'] ?? 'unknown';

    // Test dashboard stats query
    $unedited = $db->fetchQuery("SELECT COUNT(*) as count FROM archives WHERE completed = 0");
    $response['unedited_count'] = $unedited[0]['count'] ?? 0;

    $response['status'] = 'SUCCESS';

} catch (Exception $e) {
    $response['status'] = 'ERROR';
    $response['error'] = $e->getMessage();
    $response['trace'] = $e->getTraceAsString();
} catch (Error $e) {
    $response['status'] = 'ERROR';
    $response['error'] = $e->getMessage();
    $response['trace'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_PRETTY_PRINT);
