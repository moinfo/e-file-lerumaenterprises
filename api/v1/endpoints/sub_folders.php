<?php
/**
 * Sub-Folders Endpoints
 *
 * Handles sub-folder operations (CRUD)
 */

function handleSubFolders($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    switch ($method) {
        case 'GET':
            if ($id === 'by-folder') {
                // Get sub-folders by folder ID
                getSubFoldersByFolder($db, $user);
            } elseif ($id) {
                getSingleSubFolder($db, $id, $user);
            } else {
                getAllSubFolders($db, $user);
            }
            break;

        case 'POST':
            createSubFolder($db, $input, $user);
            break;

        case 'PUT':
            if (!$id) {
                ApiResponse::error('Sub-folder ID required', 400);
            }
            updateSubFolder($db, $id, $input, $user);
            break;

        case 'DELETE':
            if (!$id) {
                ApiResponse::error('Sub-folder ID required', 400);
            }
            deleteSubFolder($db, $id, $user);
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getAllSubFolders($db, $user) {
    // Get all sub-folders with folder name
    $query = "SELECT
                adsf.*,
                adf.name as folder_name,
                0 as document_type_count,
                (SELECT COUNT(*) FROM archives a
                 WHERE a.sub_folder_id = adsf.id) as file_count
              FROM archive_document_sub_folders adsf
              LEFT JOIN archive_document_folders adf ON adsf.archive_document_folder_id = adf.id
              GROUP BY adsf.id
              ORDER BY adsf.name ASC";

    $subFolders = $db->query($query, 'ALL');

    // Filter by user access
    $accessibleSubFolders = [];
    foreach ($subFolders as $subFolder) {
        if (checkSubFolderAccess($db, $subFolder['id'], $user)) {
            $accessibleSubFolders[] = $subFolder;
        }
    }

    ApiResponse::success([
        'sub_folders' => $accessibleSubFolders,
        'total' => count($accessibleSubFolders)
    ], 'Sub-folders retrieved successfully');
}

function getSubFoldersByFolder($db, $user) {
    // Get folder_id from query params
    $folderId = $_GET['folder_id'] ?? null;

    if (!$folderId) {
        ApiResponse::error('Folder ID is required', 400);
    }

    $query = "SELECT
                adsf.*,
                0 as document_type_count,
                (SELECT COUNT(*) FROM archives a
                 WHERE a.sub_folder_id = adsf.id) as file_count
              FROM archive_document_sub_folders adsf
              WHERE adsf.archive_document_folder_id = ?
              GROUP BY adsf.id
              ORDER BY adsf.name ASC";

    $subFolders = $db->query($query, 'ALL', [$folderId]);

    ApiResponse::success([
        'sub_folders' => $subFolders ?: [],
        'total' => count($subFolders ?: [])
    ], 'Sub-folders retrieved successfully');
}

function getSingleSubFolder($db, $id, $user) {
    // Get sub-folder details
    $query = "SELECT
                adsf.*,
                adf.name as folder_name,
                0 as document_type_count,
                (SELECT COUNT(*) FROM archives a
                 WHERE a.sub_folder_id = adsf.id) as file_count
              FROM archive_document_sub_folders adsf
              LEFT JOIN archive_document_folders adf ON adsf.archive_document_folder_id = adf.id
              WHERE adsf.id = ?
              GROUP BY adsf.id";

    $subFolder = $db->query($query, 'ROW', [$id]);

    if (!$subFolder) {
        ApiResponse::error('Sub-folder not found', 404);
    }

    $subFolder['document_types'] = [];

    ApiResponse::success(['sub_folder' => $subFolder], 'Sub-folder retrieved successfully');
}

function createSubFolder($db, $input, $user) {
    // Check if user has SUB_FOLDER_ADDITION role
    requireRole($db, $user, 'SUB_FOLDER_ADDITION', 'You do not have permission to add sub-folders');

    // Validate input
    if (!isset($input['name']) || empty(trim($input['name']))) {
        ApiResponse::error('Sub-folder name is required', 400);
    }

    if (!isset($input['archive_document_folder_id']) || empty($input['archive_document_folder_id'])) {
        ApiResponse::error('Parent folder ID is required', 400);
    }

    $name = trim($input['name']);
    $description = $input['description'] ?? '';
    $folderId = (int)$input['archive_document_folder_id'];

    // Check if parent folder exists
    $folderCheck = $db->query("SELECT id FROM archive_document_folders WHERE id = ?",
                               'ROW', [$folderId]);

    if (!$folderCheck) {
        ApiResponse::error('Parent folder not found', 404);
    }

    // Check if sub-folder already exists
    $checkQuery = "SELECT id FROM archive_document_sub_folders WHERE name = ?";
    $existing = $db->query($checkQuery, 'ROW', [$name]);

    if ($existing) {
        ApiResponse::error('Sub-folder with this name already exists', 409);
    }

    // Create sub-folder
    $insertQuery = "INSERT INTO archive_document_sub_folders
                    (name, description, archive_document_folder_id, entry_timestamp)
                    VALUES (?, ?, ?, NOW())";
    $db->query($insertQuery, 'INSERT', [$name, $description, $folderId]);

    $subFolderId = $db->getLastInsertId();

    // Get created sub-folder
    $subFolder = $db->query("SELECT * FROM archive_document_sub_folders WHERE id = ?",
                            'ROW', [$subFolderId]);

    ApiResponse::success(['sub_folder' => $subFolder], 'Sub-folder created successfully', 201);
}

function updateSubFolder($db, $id, $input, $user) {
    // Check if user has SUB_FOLDER_EDITION role
    requireRole($db, $user, 'SUB_FOLDER_EDITION', 'You do not have permission to edit sub-folders');

    // Check if sub-folder exists
    $subFolder = $db->query("SELECT * FROM archive_document_sub_folders WHERE id = ?",
                            'ROW', [$id]);

    if (!$subFolder) {
        ApiResponse::error('Sub-folder not found', 404);
    }

    // Validate input
    if (!isset($input['name']) || empty(trim($input['name']))) {
        ApiResponse::error('Sub-folder name is required', 400);
    }

    $name = trim($input['name']);
    $description = $input['description'] ?? $subFolder['description'];
    $folderId = isset($input['archive_document_folder_id'])
                ? (int)$input['archive_document_folder_id']
                : $subFolder['archive_document_folder_id'];

    // Check if parent folder exists
    if ($folderId) {
        $folderCheck = $db->query("SELECT id FROM archive_document_folders WHERE id = ?",
                                   'ROW', [$folderId]);

        if (!$folderCheck) {
            ApiResponse::error('Parent folder not found', 404);
        }
    }

    // Check for duplicate name (excluding current sub-folder)
    $checkQuery = "SELECT id FROM archive_document_sub_folders WHERE name = ? AND id != ?";
    $existing = $db->query($checkQuery, 'ROW', [$name, $id]);

    if ($existing) {
        ApiResponse::error('Sub-folder with this name already exists', 409);
    }

    // Update sub-folder
    $updateQuery = "UPDATE archive_document_sub_folders
                    SET name = ?, description = ?, archive_document_folder_id = ?
                    WHERE id = ?";
    $result = $db->query($updateQuery, 'UPDATE', [$name, $description, $folderId, $id]);

    if ($result === false) {
        ApiResponse::error('Failed to update sub-folder in database', 500);
    }

    // Get updated sub-folder
    $updatedSubFolder = $db->query("SELECT * FROM archive_document_sub_folders WHERE id = ?",
                                    'ROW', [$id]);

    if (!$updatedSubFolder) {
        ApiResponse::error('Failed to retrieve updated sub-folder', 500);
    }

    ApiResponse::success(['sub_folder' => $updatedSubFolder], 'Sub-folder updated successfully');
}

function deleteSubFolder($db, $id, $user) {
    // Check if user has SUB_FOLDER_DELETION role
    requireRole($db, $user, 'SUB_FOLDER_DELETION', 'You do not have permission to delete sub-folders');

    // Check if sub-folder exists
    $subFolder = $db->query("SELECT * FROM archive_document_sub_folders WHERE id = ?",
                            'ROW', [$id]);

    if (!$subFolder) {
        ApiResponse::error('Sub-folder not found', 404);
    }

    // Check if sub-folder has files
    $fileCount = $db->query("SELECT COUNT(*) as count FROM archives
                             WHERE sub_folder_id = ?",
                            'ROW', [$id]);

    if ($fileCount && $fileCount['count'] > 0) {
        ApiResponse::error('Cannot delete sub-folder with files. Please delete or move files first.', 400);
    }

    // Delete sub-folder
    $deleteQuery = "DELETE FROM archive_document_sub_folders WHERE id = ?";
    $db->query($deleteQuery, 'DELETE', [$id]);

    ApiResponse::success([], 'Sub-folder deleted successfully');
}

function checkSubFolderAccess($db, $subFolderId, $user) {
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

    // Check if user has access to this sub-folder through any of their groups
    $accessQuery = "SELECT * FROM config_folder_access_rights
                    WHERE user_group IN ($groupIdsStr) AND folder_sub_id = ? AND type = 'SUB FOLDER'";
    $access = $db->query($accessQuery, 'SELECT', 'ROW', [$subFolderId]);

    return $access !== null && $access !== false;
}
