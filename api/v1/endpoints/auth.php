<?php
/**
 * Authentication Endpoints
 *
 * Handles user authentication, login, logout, and token management
 */

function handleAuth($method, $action, $input) {
    $db = new DB();

    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                ApiResponse::error('Method not allowed', 405);
            }
            handleLogin($db, $input);
            break;

        case 'logout':
            if ($method !== 'POST') {
                ApiResponse::error('Method not allowed', 405);
            }
            handleLogout($db);
            break;

        case 'refresh':
            if ($method !== 'POST') {
                ApiResponse::error('Method not allowed', 405);
            }
            handleRefreshToken($db);
            break;

        case 'validate':
            if ($method !== 'GET') {
                ApiResponse::error('Method not allowed', 405);
            }
            handleValidateToken();
            break;

        case 'change-password':
            if ($method !== 'POST') {
                ApiResponse::error('Method not allowed', 405);
            }
            handleChangePassword($db, $input);
            break;

        default:
            ApiResponse::error('Invalid auth action', 400);
    }
}

function handleLogin($db, $input) {
    // Validate input
    if (!isset($input['username']) || !isset($input['password'])) {
        ApiResponse::error('Username and password required', 400);
    }

    $username = trim($input['username']);
    $password = trim($input['password']);

    // Check user credentials
    $query = "SELECT * FROM users WHERE username = ?";
    $user = $db->query($query, 'SELECT', true, [$username]);

    if (!$user) {
        ApiResponse::error('Invalid credentials', 401);
    }

    // Verify password (assuming md5 based on your code)
    $hashedPassword = md5($password);
    if ($user['password'] !== $hashedPassword) {
        ApiResponse::error('Invalid credentials', 401);
    }

    // Check if API tokens table exists, if not create it
    createApiTokensTable($db);

    // Generate API token
    $token = ApiAuth::generateToken($user['id']);

    // Update last login
    $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $db->query($updateQuery, 'UPDATE', 'ROW', [$user['id']]);

    // Remove sensitive data
    unset($user['password']);

    // Return user data and token
    ApiResponse::success([
        'user' => $user,
        'token' => $token,
        'expires_in' => 30 * 24 * 60 * 60 // 30 days in seconds
    ], 'Login successful', 200);
}

function handleLogout($db) {
    $user = ApiAuth::validateToken();

    // Get token from header
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    $token = str_replace('Bearer ', '', $token);

    // Deactivate token
    $query = "UPDATE user_api_tokens SET is_active = 0 WHERE token = ?";
    $db->query($query, 'UPDATE', 'ROW', [$token]);

    ApiResponse::success([], 'Logout successful');
}

function handleRefreshToken($db) {
    $user = ApiAuth::validateToken();

    // Get old token from header
    $headers = getallheaders();
    $oldToken = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    $oldToken = str_replace('Bearer ', '', $oldToken);

    // Deactivate old token
    $query = "UPDATE user_api_tokens SET is_active = 0 WHERE token = ?";
    $db->query($query, 'UPDATE', 'ROW', [$oldToken]);

    // Generate new token
    $newToken = ApiAuth::generateToken($user['user_id']);

    ApiResponse::success([
        'token' => $newToken,
        'expires_in' => 30 * 24 * 60 * 60
    ], 'Token refreshed successfully');
}

function handleValidateToken() {
    $user = ApiAuth::validateToken();

    unset($user['password']);
    unset($user['token']);

    ApiResponse::success([
        'user' => $user,
        'valid' => true
    ], 'Token is valid');
}

function handleChangePassword($db, $input) {
    $user = ApiAuth::validateToken();

    // Validate input
    if (!isset($input['current_password']) || !isset($input['new_password'])) {
        ApiResponse::error('Current password and new password required', 400);
    }

    $currentPassword = trim($input['current_password']);
    $newPassword = trim($input['new_password']);

    // Verify current password
    $query = "SELECT password FROM users WHERE id = ?";
    $result = $db->query($query, 'SELECT', 'ROW', [$user['user_id']]);

    $hashedCurrentPassword = md5($currentPassword);
    if ($result['password'] !== $hashedCurrentPassword) {
        ApiResponse::error('Current password is incorrect', 401);
    }

    // Update password
    $hashedNewPassword = md5($newPassword);
    $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
    $db->query($updateQuery, 'UPDATE', 'ROW', [$hashedNewPassword, $user['user_id']]);

    ApiResponse::success([], 'Password changed successfully');
}

function createApiTokensTable($db) {
    $checkTable = "SHOW TABLES LIKE 'user_api_tokens'";
    $exists = $db->query($checkTable, 'SELECT');

    if (empty($exists)) {
        $createTable = "CREATE TABLE user_api_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $db->query($createTable, 'CREATE');
    }
}
