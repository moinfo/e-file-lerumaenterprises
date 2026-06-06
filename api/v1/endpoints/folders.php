<?php
/**
 * Folders Endpoints
 *
 * Handles folder operations (CRUD)
 */

function handleFolders($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    switch ($method) {
        case 'GET':
            if ($id) {
                getSingleFolder($db, $id, $user);
            } else {
                getAllFolders($db, $user);
            }
            break;

        case 'POST':
            createFolder($db, $input, $user);
            break;

        case 'PUT':
            if (!$id) {
                ApiResponse::error('Folder ID required', 400);
            }
            updateFolder($db, $id, $input, $user);
            break;

        case 'DELETE':
            if (!$id) {
                ApiResponse::error('Folder ID required', 400);
            }
            deleteFolder($db, $id, $user);
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getAllFolders($db, $user) {
    // Get all folders with sub-folder count
    $query = "SELECT
                adf.*,
                COUNT(DISTINCT adsf.id) as sub_folder_count,
                (SELECT COUNT(*) FROM archives a
                 INNER JOIN archive_document_sub_folders sf ON a.sub_folder_id = sf.id
                 WHERE sf.archive_document_folder_id = adf.id) as file_count
              FROM archive_document_folders adf
              LEFT JOIN archive_document_sub_folders adsf ON adsf.archive_document_folder_id = adf.id
              GROUP BY adf.id
              ORDER BY adf.name ASC";

    $folders = $db->query($query, 'SELECT');

    // Check user access rights if applicable
    $accessibleFolders = [];
    foreach ($folders as $folder) {
        if (checkFolderAccess($db, $folder['id'], $user)) {
            $accessibleFolders[] = $folder;
        }
    }

    ApiResponse::success([
        'folders' => $accessibleFolders,
        'total' => count($accessibleFolders)
    ], 'Folders retrieved successfully');
}

function getSingleFolder($db, $id, $user) {
    // Check access
    if (!checkFolderAccess($db, $id, $user)) {
        ApiResponse::error('Access denied', 403);
    }

    // Get folder details
    $query = "SELECT
                adf.*,
                COUNT(DISTINCT adsf.id) as sub_folder_count,
                (SELECT COUNT(*) FROM archives a
                 INNER JOIN archive_document_sub_folders sf ON a.sub_folder_id = sf.id
                 WHERE sf.archive_document_folder_id = adf.id) as file_count
              FROM archive_document_folders adf
              LEFT JOIN archive_document_sub_folders adsf ON adsf.archive_document_folder_id = adf.id
              WHERE adf.id = ?
              GROUP BY adf.id";

    $folder = $db->query($query, 'SELECT', 'ROW', [$id]);

    if (!$folder) {
        ApiResponse::error('Folder not found', 404);
    }

    // Get sub-folders
    $subFoldersQuery = "SELECT * FROM archive_document_sub_folders
                        WHERE archive_document_folder_id = ?
                        ORDER BY name ASC";
    $subFolders = $db->query($subFoldersQuery, 'SELECT', 'ALL', [$id]);

    $folder['sub_folders'] = $subFolders ?: [];

    ApiResponse::success(['folder' => $folder], 'Folder retrieved successfully');
}

function createFolder($db, $input, $user) {
    // Check if user has FOLDER_ADDITION role
    requireRole($db, $user, 'FOLDER_ADDITION', 'You do not have permission to add folders');

    // Validate input
    if (!isset($input['name']) || empty(trim($input['name']))) {
        ApiResponse::error('Folder name is required', 400);
    }

    $name = trim($input['name']);
    $description = $input['description'] ?? '';

    // Check if folder already exists
    $checkQuery = "SELECT id FROM archive_document_folders WHERE name = ?";
    $existing = $db->query($checkQuery, 'SELECT', 'ROW', [$name]);

    if ($existing) {
        ApiResponse::error('Folder with this name already exists', 409);
    }

    // Create folder
    $insertQuery = "INSERT INTO archive_document_folders (name, description, entry_timestamp)
                    VALUES (?, ?, NOW())";
    $db->query($insertQuery, 'INSERT', 'ROW', [$name, $description]);

    $folderId = $db->getLastInsertId();

    // Get created folder
    $folder = $db->query("SELECT * FROM archive_document_folders WHERE id = ?",
                         'SELECT', 'ROW', [$folderId]);

    ApiResponse::success(['folder' => $folder], 'Folder created successfully', 201);
}

function updateFolder($db, $id, $input, $user) {
    // Check if user has FOLDER_EDITION role
    requireRole($db, $user, 'FOLDER_EDITION', 'You do not have permission to edit folders');

    // Check if folder exists
    $folder = $db->query("SELECT * FROM archive_document_folders WHERE id = ?",
                         'SELECT', 'ROW', [$id]);

    if (!$folder) {
        ApiResponse::error('Folder not found', 404);
    }

    // Validate input
    if (!isset($input['name']) || empty(trim($input['name']))) {
        ApiResponse::error('Folder name is required', 400);
    }

    $name = trim($input['name']);
    $description = $input['description'] ?? $folder['description'];

    // Check for duplicate name (excluding current folder)
    $checkQuery = "SELECT id FROM archive_document_folders WHERE name = ? AND id != ?";
    $existing = $db->query($checkQuery, 'SELECT', 'ROW', [$name, $id]);

    if ($existing) {
        ApiResponse::error('Folder with this name already exists', 409);
    }

    // Update folder
    $updateQuery = "UPDATE archive_document_folders
                    SET name = ?, description = ?
                    WHERE id = ?";
    $result = $db->query($updateQuery, 'UPDATE', 'ROW', [$name, $description, $id]);

    if ($result === false) {
        ApiResponse::error('Failed to update folder in database', 500);
    }

    // Get updated folder
    $updatedFolder = $db->query("SELECT * FROM archive_document_folders WHERE id = ?",
                                'SELECT', 'ROW', [$id]);

    if (!$updatedFolder) {
        ApiResponse::error('Failed to retrieve updated folder', 500);
    }

    ApiResponse::success(['folder' => $updatedFolder], 'Folder updated successfully');
}

function deleteFolder($db, $id, $user) {
    // Check if user has FOLDER_DELETION role
    requireRole($db, $user, 'FOLDER_DELETION', 'You do not have permission to delete folders');

    // Check if folder exists
    $folder = $db->query("SELECT * FROM archive_document_folders WHERE id = ?",
                         'SELECT', 'ROW', [$id]);

    if (!$folder) {
        ApiResponse::error('Folder not found', 404);
    }

    // Check if folder has sub-folders or files
    $subFolderCount = $db->query("SELECT COUNT(*) as count FROM archive_document_sub_folders
                                   WHERE archive_document_folder_id = ?",
                                  'SELECT', 'ROW', [$id]);

    if ($subFolderCount['count'] > 0) {
        ApiResponse::error('Cannot delete folder with sub-folders. Please delete sub-folders first.', 400);
    }

    // Delete folder
    $deleteQuery = "DELETE FROM archive_document_folders WHERE id = ?";
    $db->query($deleteQuery, 'DELETE', 'ROW', [$id]);

    ApiResponse::success([], 'Folder deleted successfully');
}

function checkFolderAccess($db, $folderId, $user) {
    // Get all user groups from user_group_relation table
    // Handle both array and object user formats
    if (is_array($user) && isset($user[0])) {
        // User is returned as array with index 0
        $userId = $user[0]['user_id'] ?? $user[0]['id'];
    } else {
        // User is returned as direct array
        $userId = $user['user_id'] ?? $user['id'];
    }

    $userGroupsQuery = "SELECT user_group FROM user_group_relation WHERE user = ?";
    $userGroups = $db->query($userGroupsQuery, 'SELECT', 'ALL', [$userId]);

    if (empty($userGroups)) {
        // If no user groups found, deny access
        return false;
    }

    // Extract group IDs
    $groupIds = array_column($userGroups, 'user_group');
    $groupIdsStr = implode(',', array_map('intval', $groupIds));

    // Check if user has access to this folder through any of their groups
    $accessQuery = "SELECT * FROM config_folder_access_rights
                    WHERE user_group IN ($groupIdsStr) AND folder_sub_id = ? AND type = 'FOLDER'";
    $access = $db->query($accessQuery, 'SELECT', 'ROW', [$folderId]);

    return $access !== null && $access !== false;
}
