<?php
/**
 * E-File System REST API v1.0
 * Main API Router
 *
 * This file handles all API requests and routes them to appropriate endpoints
 */

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Buffer all output so stray whitespace/BOM from an included file (config,
// secrets, endpoint) cannot corrupt the JSON body, and so a fatal mid-request
// produces a clean JSON 500 (via the shutdown handler) instead of a bare space.
ob_start();

// Set JSON header
header('Content-Type: application/json');

// Enable CORS for mobile apps
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include config and models
require_once '../../config.php';
require_once '../../models/DB.php';

// Include helpers
require_once 'helpers/role_checker.php';

// API Response Helper Class
class ApiResponse {
    // Set once a real response has been emitted, so the shutdown handler
    // doesn't double-send if a fatal occurs after we've already replied.
    public static $responded = false;

    public static function success($data = [], $message = 'Success', $code = 200) {
        self::send($code, [
            'success'   => true,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function error($message = 'Error', $code = 400, $errors = []) {
        self::send($code, [
            'success'   => false,
            'message'   => $message,
            'errors'    => $errors,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function send($code, array $payload) {
        // Discard any buffered output (stray whitespace/BOM from includes) so
        // the body is exactly the JSON we intend to send.
        while (ob_get_level() > 0) { ob_end_clean(); }
        self::$responded = true;
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_PRETTY_PRINT);
        exit();
    }
}

// Convert any fatal/parse error that escapes the try/catch into a clean JSON 500
// instead of a half-sent response (the classic "bare space" body). The real error
// is always logged; the message is only echoed outside production.
register_shutdown_function(function () {
    $err = error_get_last();
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!$err || !in_array($err['type'], $fatal, true)) {
        return;
    }
    error_log('api/v1 fatal: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
    if (ApiResponse::$responded) {
        return;
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(500);
    header('Content-Type: application/json');
    $isProd = defined('APP_ENV') && APP_ENV === 'production';
    echo json_encode([
        'success'   => false,
        'message'   => 'Internal server error',
        'errors'    => $isProd ? [] : [$err['message'] . ' in ' . $err['file'] . ':' . $err['line']],
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT);
});

// API Authentication Class
class ApiAuth {
    private static $db;

    public static function init() {
        self::$db = new DB();
    }

    // Validate API token
    public static function validateToken() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$token) {
            ApiResponse::error('Authorization token required', 401);
        }

        // Remove 'Bearer ' prefix if present
        $token = str_replace('Bearer ', '', $token);

        // Validate token in database
        $query = "SELECT u.*, uat.* FROM user_api_tokens uat
                  JOIN users u ON u.id = uat.user_id
                  WHERE uat.token = ? AND uat.expires_at > NOW() AND uat.is_active = 1";

        $result = self::$db->query($query, 'SELECT', 'ROW', [$token]);

        if (!$result) {
            ApiResponse::error('Invalid or expired token', 401);
        }

        // Update last used timestamp
        $updateQuery = "UPDATE user_api_tokens SET last_used_at = NOW() WHERE token = ?";
        self::$db->query($updateQuery, 'UPDATE', 'ROW', [$token]);

        return $result;
    }

    // Generate API token
    public static function generateToken($userId, $expiresIn = 30) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresIn} days"));

        $query = "INSERT INTO user_api_tokens (user_id, token, expires_at, created_at)
                  VALUES (?, ?, ?, NOW())";

        self::$db->query($query, 'INSERT', 'ROW', [$userId, $token, $expiresAt]);

        return $token;
    }
}

// Initialize API
ApiAuth::init();

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$baseApiPath = '/api/v1/';

// Parse endpoint
$endpoint = str_replace($baseApiPath, '', parse_url($requestUri, PHP_URL_PATH));
$endpoint = trim($endpoint, '/');
$parts = explode('/', $endpoint);

$resource = $parts[0] ?? null;
$id = $parts[1] ?? null;
$action = $parts[2] ?? null;

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

// Route to appropriate endpoint
try {
    switch ($resource) {
        case 'auth':
            require_once 'endpoints/auth.php';
            handleAuth($method, $id, $input);  // For auth, the action is in $id position
            break;

        case 'folders':
            require_once 'endpoints/folders.php';
            handleFolders($method, $id, $action, $input);
            break;

        case 'sub-folders':
            require_once 'endpoints/sub_folders.php';
            handleSubFolders($method, $id, $action, $input);
            break;

        case 'document-types':
            require_once 'endpoints/document_types.php';
            handleDocumentTypes($method, $id, $action, $input);
            break;

        case 'dashboard':
            require_once 'endpoints/dashboard.php';
            handleDashboard($method, $id, $action, $input);
            break;

        case 'files':
            require_once 'endpoints/files.php';
            handleFiles($method, $id, $action, $input);
            break;

        case 'users':
            require_once 'endpoints/users.php';
            handleUsers($method, $id, $action, $input);
            break;

        case 'archives':
            require_once 'endpoints/archives.php';
            handleArchives($method, $id, $action, $input);
            break;

        case 'search':
            require_once 'endpoints/search.php';
            handleSearch($method, $input);
            break;

        case 'stats':
            require_once 'endpoints/stats.php';
            handleStats($method, $input);
            break;

        case 'backup':
            require_once 'endpoints/backup.php';
            handleBackup($method, $id, $action, $input);
            break;

        case 'uploads':
            require_once 'endpoints/uploads.php';
            handleUploads($method, $id, $action, $input);
            break;

        case 'editor':
            require_once 'endpoints/editor.php';
            handleEditor($method, $id, $action, $input);
            break;

        case 'settings':
            require_once 'endpoints/settings.php';
            handleSettings($method, $id, $action, $input);
            break;

        case 'cleanup':
            require_once 'endpoints/cleanup.php';
            handleCleanup($method, $id, $action, $input);
            break;

        case 'ingest':
            require_once 'endpoints/ingest.php';
            handleIngest($method, $id, $action, $input);
            break;

        case 'serve':
            require_once 'endpoints/serve.php';
            handleServe($method, $id, $action, $input);
            break;

        case 'user-groups':
            require_once 'endpoints/user_groups.php';
            handleUserGroups($method, $id, $action, $input);
            break;

        case 'user-permissions':
            require_once 'endpoints/user_permissions.php';
            handleUserPermissions($method, $id, $action, $input);
            break;

        case '':
            // API info endpoint
            ApiResponse::success([
                'name' => 'E-File System API',
                'version' => '1.0',
                'endpoints' => [
                    'auth' => '/auth',
                    'dashboard' => '/dashboard',
                    'folders' => '/folders',
                    'sub-folders' => '/sub-folders',
                    'document-types' => '/document-types',
                    'files' => '/files',
                    'users' => '/users',
                    'archives' => '/archives',
                    'search' => '/search',
                    'stats' => '/stats',
                    'backup' => '/backup',
                    'uploads' => '/uploads',
                    'editor' => '/editor',
                    'settings' => '/settings',
                    'cleanup' => '/cleanup',
                    'user-groups' => '/user-groups',
                    'user-permissions' => '/user-permissions',
                    'ingest'           => '/ingest/upload | /ingest/file/{ref_id} | /ingest/stats'
                ]
            ], 'Welcome to E-File System API v1.0');
            break;

        default:
            ApiResponse::error('Endpoint not found', 404);
    }
} catch (\Throwable $e) {
    // \Throwable (not just Exception) so PHP Error/TypeError/ParseError from an
    // endpoint are caught and returned as JSON rather than fataling silently.
    error_log('api/v1 exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $isProd = defined('APP_ENV') && APP_ENV === 'production';
    ApiResponse::error(
        $isProd ? 'Internal server error' : ('Internal server error: ' . $e->getMessage()),
        500
    );
}
