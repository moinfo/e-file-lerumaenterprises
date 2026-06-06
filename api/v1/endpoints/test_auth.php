<?php
/**
 * Test authentication
 */

header('Content-Type: application/json');

// Get headers
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

// Include necessary files
require_once '../../../config.php';
require_once '../../../models/DB.php';

$response = [
    'test' => 'Authentication diagnostic',
    'headers_received' => $headers,
    'auth_header' => $authHeader,
    'bearer_token' => null,
];

if ($authHeader) {
    $token = str_replace('Bearer ', '', $authHeader);
    $response['bearer_token'] = substr($token, 0, 20) . '...' . substr($token, -10); // Show partial token for security

    // Try to validate
    $db = new DB();
    $query = "SELECT u.*, uat.* FROM user_api_tokens uat
              JOIN users u ON u.id = uat.user_id
              WHERE uat.token = ? AND uat.expires_at > NOW() AND uat.is_active = 1";

    try {
        $result = $db->fetchQuery("SELECT u.*, uat.* FROM user_api_tokens uat
              JOIN users u ON u.id = uat.user_id
              WHERE uat.token = '$token' AND uat.expires_at > NOW() AND uat.is_active = 1");

        $response['token_valid'] = $result ? true : false;
        $response['user_found'] = $result ? true : false;

        if ($result) {
            $response['user_id'] = $result[0]['user_id'] ?? null;
            $response['username'] = $result[0]['username'] ?? null;
        }
    } catch (Exception $e) {
        $response['db_error'] = $e->getMessage();
    }
}

echo json_encode($response, JSON_PRETTY_PRINT);
