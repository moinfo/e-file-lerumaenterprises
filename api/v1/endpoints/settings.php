<?php
/**
 * Settings Endpoints
 *
 * Handles system settings and configuration
 */

function handleSettings($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    switch ($method) {
        case 'GET':
            if ($action === 'menu') {
                getSettingsMenu($db);
            } elseif ($action === 'system') {
                getSystemSettings($db, $user);
            } elseif ($action === 'user-preferences') {
                getUserPreferences($db, $user);
            } else {
                ApiResponse::error('Invalid action', 400);
            }
            break;

        case 'PUT':
            if ($action === 'system') {
                updateSystemSettings($db, $user, $input);
            } elseif ($action === 'user-preferences') {
                updateUserPreferences($db, $user, $input);
            } else {
                ApiResponse::error('Invalid action', 400);
            }
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getSettingsMenu($db) {
    // Return settings menu structure
    $menu = [
        [
            'name' => 'document_folders',
            'title' => 'Document Folders',
            'link' => 'folders',
            'description' => 'Add, Edit and Delete Document Folder/s',
            'icon' => 'folder'
        ],
        [
            'name' => 'document_sub_folders',
            'title' => 'Document Sub Folders',
            'link' => 'sub-folders',
            'description' => 'Add, Edit and Delete Document Sub Folder/s',
            'icon' => 'folder-open'
        ],
        [
            'name' => 'document_types',
            'title' => 'Document Types',
            'link' => 'document-types',
            'description' => 'Add, Edit and Delete Document Type/s',
            'icon' => 'file-text'
        ],
        [
            'name' => 'users',
            'title' => 'Users',
            'link' => 'users',
            'description' => 'Add, Edit and Delete User/s',
            'icon' => 'users'
        ],
        [
            'name' => 'uploads',
            'title' => 'Uploads',
            'link' => 'uploads',
            'description' => 'Uploads all Documents, modifications, and Organizations',
            'icon' => 'upload'
        ],
        [
            'name' => 'edited_files',
            'title' => 'Edited Files',
            'link' => 'stats?action=edited-files',
            'description' => 'Cruds For All Files Edited by a User',
            'icon' => 'edit'
        ],
        [
            'name' => 'user_groups_and_access',
            'title' => 'User Group & Access',
            'link' => 'user-groups',
            'description' => 'Assign User to a group and set access',
            'icon' => 'shield'
        ],
        [
            'name' => 'backup',
            'title' => 'Backup',
            'link' => 'backup',
            'description' => 'Backup database and files',
            'icon' => 'database'
        ]
    ];

    ApiResponse::success([
        'menu' => $menu
    ], 'Settings menu retrieved successfully');
}

function getSystemSettings($db, $user) {
    // Check admin permissions
    if (!checkAdminPermission($db, $user)) {
        ApiResponse::error('Insufficient permissions. Admin access required.', 403);
    }

    // Get or create system settings
    $query = "SELECT * FROM system_settings WHERE id = 1";
    $settings = $db->query($query, 'SELECT', 'ROW');

    if (!$settings) {
        // Create default settings
        createDefaultSettings($db);
        $settings = $db->query($query, 'SELECT', 'ROW');
    }

    ApiResponse::success([
        'settings' => $settings
    ], 'System settings retrieved successfully');
}

function updateSystemSettings($db, $user, $input) {
    // Check admin permissions
    if (!checkAdminPermission($db, $user)) {
        ApiResponse::error('Insufficient permissions. Admin access required.', 403);
    }

    // Build update query
    $fields = [];
    $params = [];

    if (isset($input['app_name'])) {
        $fields[] = "app_name = ?";
        $params[] = $input['app_name'];
    }

    if (isset($input['max_file_size'])) {
        $fields[] = "max_file_size = ?";
        $params[] = (int)$input['max_file_size'];
    }

    if (isset($input['allowed_file_types'])) {
        $fields[] = "allowed_file_types = ?";
        $params[] = $input['allowed_file_types'];
    }

    if (isset($input['items_per_page'])) {
        $fields[] = "items_per_page = ?";
        $params[] = (int)$input['items_per_page'];
    }

    if (isset($input['enable_notifications'])) {
        $fields[] = "enable_notifications = ?";
        $params[] = $input['enable_notifications'] ? 1 : 0;
    }

    if (isset($input['enable_auto_backup'])) {
        $fields[] = "enable_auto_backup = ?";
        $params[] = $input['enable_auto_backup'] ? 1 : 0;
    }

    if (isset($input['backup_frequency'])) {
        $fields[] = "backup_frequency = ?";
        $params[] = $input['backup_frequency'];
    }

    if (empty($fields)) {
        ApiResponse::error('No settings to update', 400);
    }

    $fields[] = "updated_at = NOW()";

    $updateQuery = "UPDATE system_settings SET " . implode(", ", $fields) . " WHERE id = 1";

    $result = $db->query($updateQuery, 'UPDATE', 'ROW', $params);

    if ($result) {
        ApiResponse::success([
            'updated' => true
        ], 'System settings updated successfully');
    } else {
        ApiResponse::error('Failed to update system settings', 500);
    }
}

function getUserPreferences($db, $user) {
    $userId = $user['user_id'];

    // Get user preferences
    $query = "SELECT * FROM user_preferences WHERE user_id = ?";
    $preferences = $db->query($query, 'SELECT', 'ROW', [$userId]);

    if (!$preferences) {
        // Create default preferences
        $insertQuery = "INSERT INTO user_preferences
                       (user_id, theme, language, items_per_page, created_at)
                       VALUES (?, 'dark', 'en', 25, NOW())";
        $db->query($insertQuery, 'INSERT', 'ROW', [$userId]);

        $preferences = $db->query($query, 'SELECT', 'ROW', [$userId]);
    }

    ApiResponse::success([
        'preferences' => $preferences
    ], 'User preferences retrieved successfully');
}

function updateUserPreferences($db, $user, $input) {
    $userId = $user['user_id'];

    // Check if preferences exist
    $checkQuery = "SELECT id FROM user_preferences WHERE user_id = ?";
    $exists = $db->query($checkQuery, 'SELECT', 'ROW', [$userId]);

    if (!$exists) {
        // Create new preferences
        $insertQuery = "INSERT INTO user_preferences
                       (user_id, theme, language, items_per_page, created_at)
                       VALUES (?, 'dark', 'en', 25, NOW())";
        $db->query($insertQuery, 'INSERT', 'ROW', [$userId]);
    }

    // Build update query
    $fields = [];
    $params = [];

    if (isset($input['theme'])) {
        $fields[] = "theme = ?";
        $params[] = $input['theme'];
    }

    if (isset($input['language'])) {
        $fields[] = "language = ?";
        $params[] = $input['language'];
    }

    if (isset($input['items_per_page'])) {
        $fields[] = "items_per_page = ?";
        $params[] = (int)$input['items_per_page'];
    }

    if (isset($input['notifications_enabled'])) {
        $fields[] = "notifications_enabled = ?";
        $params[] = $input['notifications_enabled'] ? 1 : 0;
    }

    if (empty($fields)) {
        ApiResponse::error('No preferences to update', 400);
    }

    $fields[] = "updated_at = NOW()";
    $params[] = $userId;

    $updateQuery = "UPDATE user_preferences SET " . implode(", ", $fields) . " WHERE user_id = ?";

    $result = $db->query($updateQuery, 'UPDATE', 'ROW', $params);

    if ($result) {
        ApiResponse::success([
            'updated' => true
        ], 'User preferences updated successfully');
    } else {
        ApiResponse::error('Failed to update user preferences', 500);
    }
}

function createDefaultSettings($db) {
    $insertQuery = "INSERT INTO system_settings
                    (id, app_name, max_file_size, allowed_file_types, items_per_page,
                     enable_notifications, enable_auto_backup, backup_frequency, created_at)
                    VALUES (1, 'E-File System', 52428800, 'pdf,jpg,jpeg,png,gif', 25,
                            1, 0, 'daily', NOW())";

    // Create table if doesn't exist
    $createTableQuery = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT PRIMARY KEY,
        app_name VARCHAR(255) DEFAULT 'E-File System',
        max_file_size BIGINT DEFAULT 52428800,
        allowed_file_types VARCHAR(255) DEFAULT 'pdf,jpg,jpeg,png,gif',
        items_per_page INT DEFAULT 25,
        enable_notifications TINYINT(1) DEFAULT 1,
        enable_auto_backup TINYINT(1) DEFAULT 0,
        backup_frequency VARCHAR(50) DEFAULT 'daily',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL
    )";

    $db->query($createTableQuery, 'CREATE');
    $db->query($insertQuery, 'INSERT');
}

function checkAdminPermission($db, $user) {
    $userQuery = "SELECT user_group FROM users WHERE id = ?";
    $result = $db->query($userQuery, 'SELECT', 'ROW', [$user['user_id']]);

    // Assuming user_group 1 is admin
    return $result && $result['user_group'] == 1;
}
