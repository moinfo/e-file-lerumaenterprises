<?php
/**
 * Test View File API
 * Simulates what the mobile app sees when calling /files/{id}/view
 *
 * Usage: /api/v1/endpoints/test_view_api.php?file_id=41&token=YOUR_TOKEN
 */

// Capture all output
ob_start();

// Include config and models
require_once '../../../config.php';
require_once '../../../models/DB.php';

// Mock ApiResponse if not available
if (!class_exists('ApiResponse')) {
    class ApiResponse {
        public static function success($data = [], $message = 'Success', $code = 200) {
            http_response_code($code);
            echo json_encode([
                'success' => true,
                'message' => $message,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            exit();
        }

        public static function error($message = 'Error', $code = 400, $errors = []) {
            http_response_code($code);
            echo json_encode([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            exit();
        }
    }
}

// Mock user
$user = ['user_id' => 1, 'username' => 'test'];

$fileId = $_GET['file_id'] ?? 41;

echo "Testing file view for ID: $fileId\n";
echo "========================================\n\n";

// Include the files.php endpoint
require_once 'files.php';

// Clear output buffer to prevent headers already sent error
ob_clean();

// Call viewFile function
$db = new DB();
viewFile($db, $fileId, $user);
