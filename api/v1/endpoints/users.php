<?php
/**
 * Users Endpoints
 *
 * Handles user management operations (CRUD)
 */

function handleUsers($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    // Check if user has admin permissions (you can adjust this based on your permissions system)
    if (!checkUserPermission($db, $user, 'USER_MANAGEMENT')) {
        ApiResponse::error('Insufficient permissions', 403);
    }

    switch ($method) {
        case 'GET':
            if ($id) {
                getSingleUser($db, $id);
            } elseif ($action === 'me') {
                getCurrentUser($db, $user);
            } else {
                getAllUsers($db);
            }
            break;

        case 'POST':
            createUser($db, $input);
            break;

        case 'PUT':
            if (!$id) {
                ApiResponse::error('User ID required', 400);
            }
            updateUser($db, $id, $input);
            break;

        case 'DELETE':
            if (!$id) {
                ApiResponse::error('User ID required', 400);
            }
            deleteUser($db, $id);
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getAllUsers($db) {
    $query = "SELECT
                u.id,
                u.username,
                u.last_login,
                u.current_file,
                (SELECT COUNT(*) FROM archives WHERE edited_by = u.id) as file_count
              FROM users u
              ORDER BY u.username ASC";

    $users = $db->query($query, 'SELECT');

    ApiResponse::success([
        'users' => $users ?: [],
        'total' => count($users ?: [])
    ], 'Users retrieved successfully');
}

function getSingleUser($db, $id) {
    $query = "SELECT
                u.id,
                u.username,
                u.last_login,
                u.current_file
              FROM users u
              WHERE u.id = ?";

    $user = $db->query($query, 'SELECT', 'ROW', [$id]);

    if (!$user) {
        ApiResponse::error('User not found', 404);
    }

    ApiResponse::success(['user' => $user], 'User retrieved successfully');
}

function getCurrentUser($db, $authUser) {
    $query = "SELECT
                u.id,
                u.username,
                u.last_login,
                u.current_file
              FROM users u
              WHERE u.id = ?";

    $user = $db->query($query, 'SELECT', 'ROW', [$authUser['user_id']]);

    if (!$user) {
        ApiResponse::error('User not found', 404);
    }

    ApiResponse::success(['user' => $user], 'Current user retrieved successfully');
}

function createUser($db, $input) {
    // Validate input
    if (!isset($input['username']) || empty(trim($input['username']))) {
        ApiResponse::error('Username is required', 400);
    }

    if (!isset($input['password']) || empty(trim($input['password']))) {
        ApiResponse::error('Password is required', 400);
    }

    $username = trim($input['username']);
    $password = md5(trim($input['password']));

    // Check for duplicate username
    $checkQuery = "SELECT id FROM users WHERE username = ?";
    $existing = $db->query($checkQuery, 'SELECT', 'ROW', [$username]);

    if ($existing) {
        ApiResponse::error('Username already exists', 409);
    }

    // Create user
    $insertQuery = "INSERT INTO users (username, password)
                    VALUES (?, ?)";
    $db->query($insertQuery, 'INSERT', 'ROW', [$username, $password]);

    $userId = $db->getLastInsertId();

    // Get created user
    $user = $db->query("SELECT id, username, last_login, current_file FROM users WHERE id = ?",
                      'SELECT', 'ROW', [$userId]);

    ApiResponse::success(['user' => $user], 'User created successfully', 201);
}

function updateUser($db, $id, $input) {
    // Check if user exists
    $user = $db->query("SELECT * FROM users WHERE id = ?", 'SELECT', 'ROW', [$id]);

    if (!$user) {
        ApiResponse::error('User not found', 404);
    }

    // Build update query
    $updates = [];
    $params = [];

    if (isset($input['username'])) {
        $username = trim($input['username']);

        // Check for duplicate username
        $checkQuery = "SELECT id FROM users WHERE username = ? AND id != ?";
        $existing = $db->query($checkQuery, 'SELECT', 'ROW', [$username, $id]);

        if ($existing) {
            ApiResponse::error('Username already exists', 409);
        }

        $updates[] = "username = ?";
        $params[] = $username;
    }

    if (isset($input['password']) && !empty(trim($input['password']))) {
        $updates[] = "password = ?";
        $params[] = md5(trim($input['password']));
    }

    if (empty($updates)) {
        ApiResponse::error('No fields to update', 400);
    }

    $params[] = $id;

    $updateQuery = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $result = $db->query($updateQuery, 'UPDATE', 'ROW', $params);

    if ($result === false) {
        ApiResponse::error('Failed to update user in database', 500);
    }

    // Get updated user
    $updatedUser = $db->query("SELECT id, username, last_login, current_file FROM users WHERE id = ?",
                             'SELECT', 'ROW', [$id]);

    ApiResponse::success(['user' => $updatedUser], 'User updated successfully');
}

function deleteUser($db, $id) {
    // Check if user exists
    $user = $db->query("SELECT * FROM users WHERE id = ?", 'SELECT', 'ROW', [$id]);

    if (!$user) {
        ApiResponse::error('User not found', 404);
    }

    // Don't allow deleting if it's the last user (safety check)
    $userCount = $db->query("SELECT COUNT(*) as count FROM users", 'SELECT', 'ROW');
    if ($userCount['count'] <= 1) {
        ApiResponse::error('Cannot delete the last user', 400);
    }

    // Delete user
    $deleteQuery = "DELETE FROM users WHERE id = ?";
    $db->query($deleteQuery, 'DELETE', 'ROW', [$id]);

    ApiResponse::success([], 'User deleted successfully');
}

function checkUserPermission($db, $user, $permission) {
    // Simplified permission check - all authenticated users have permission
    // You can expand this based on your permission system
    $userQuery = "SELECT id FROM users WHERE id = ?";
    $result = $db->query($userQuery, 'SELECT', 'ROW', [$user['user_id']]);

    return $result !== null;
}
