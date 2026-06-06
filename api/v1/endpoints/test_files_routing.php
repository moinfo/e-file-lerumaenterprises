<?php
/**
 * Test files endpoint routing
 * Upload to production and access: /api/v1/endpoints/test_files_routing.php?file_id=116
 */

header('Content-Type: application/json');

// Simulate the routing that happens in index.php
$fileId = $_GET['file_id'] ?? 116;
$action = 'view';

// Check if files.php exists
$filesPhpPath = __DIR__ . '/files.php';
$filesExists = file_exists($filesPhpPath);

$response = [
    'test' => 'Files endpoint routing diagnostic',
    'file_id' => $fileId,
    'action' => $action,
    'files_php_exists' => $filesExists,
    'files_php_path' => $filesPhpPath,
];

if ($filesExists) {
    // Read files.php and check for viewFile function
    $content = file_get_contents($filesPhpPath);

    $response['checks'] = [
        'has_viewFile_function' => strpos($content, 'function viewFile(') !== false,
        'has_view_action_route' => strpos($content, "action === 'view'") !== false,
        'has_fetchQuery' => strpos($content, 'fetchQuery') !== false,
        'file_size' => filesize($filesPhpPath),
        'last_modified' => date('Y-m-d H:i:s', filemtime($filesPhpPath)),
    ];

    // Try to include and test
    require_once '../../../config.php';
    require_once '../../../models/DB.php';

    // Check if ApiResponse class exists
    if (!class_exists('ApiResponse')) {
        class ApiResponse {
            public static function error($message, $code) {
                return ['error' => $message, 'code' => $code];
            }
            public static function success($data, $message) {
                return ['success' => true, 'data' => $data, 'message' => $message];
            }
        }
    }

    // Check if ApiAuth exists
    if (!class_exists('ApiAuth')) {
        class ApiAuth {
            public static function validateToken() {
                return ['user_id' => 1]; // Mock user
            }
        }
    }

    // Include files.php
    require_once $filesPhpPath;

    // Check if function exists
    $response['function_exists'] = [
        'viewFile' => function_exists('viewFile'),
        'downloadFile' => function_exists('downloadFile'),
        'handleFiles' => function_exists('handleFiles'),
    ];

    // Test the routing logic
    $method = 'GET';
    $id = $fileId;
    $action = 'view';

    $response['routing_test'] = [
        'method' => $method,
        'id' => $id,
        'action' => $action,
        'should_call_viewFile' => ($method === 'GET' && $action === 'view' && $id),
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
