<?php
/**
 * Test what happens when we call viewFile directly
 * This simulates exactly what the mobile app does
 */

// Don't set any headers yet
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log to a variable instead of output
ob_start();

echo "=== SIMULATING MOBILE APP REQUEST ===\n";
echo "URL: /api/v1/files/41/view\n";
echo "Method: GET\n\n";

// Include config
require_once '../../../config.php';
require_once '../../../models/DB.php';

// Mock ApiAuth and ApiResponse
class ApiResponse {
    public static function error($message, $code) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'code' => $code
        ]);
        exit();
    }
}

class ApiAuth {
    public static function validateToken() {
        return ['user_id' => 1, 'username' => 'test'];
    }
}

// Include files.php
require_once 'files.php';

$db = new DB();
$user = ['user_id' => 1];
$fileId = $_GET['file_id'] ?? 41;

echo "Calling viewFile($fileId)...\n\n";

// Clear the buffer before calling viewFile
$diagnosticOutput = ob_get_clean();

// NOW call viewFile - it should serve the PDF
viewFile($db, $fileId, $user);

// This line should never execute because viewFile calls exit()
echo "ERROR: viewFile did not exit properly\n";
