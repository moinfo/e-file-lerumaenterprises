<?php
/**
 * Editor Endpoints
 *
 * Handles document editing functionality
 */

function handleEditor($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    switch ($method) {
        case 'GET':
            if ($action === 'next' || $action === 'previous') {
                getNextFile($db, $user, $action);
            } elseif ($action === 'current' && $id) {
                getCurrentFile($db, $id);
            } elseif ($action === 'sub-folders') {
                getEditorSubFolders($db, $user);
            } elseif ($action === 'document-types') {
                getEditorDocumentTypes($db, $user);
            } else {
                ApiResponse::error('Invalid action', 400);
            }
            break;

        case 'POST':
            if ($action === 'save') {
                saveFileData($db, $user, $input);
            } elseif ($action === 'sub-folder') {
                createSubFolder($db, $user, $input);
            } else {
                ApiResponse::error('Invalid action', 400);
            }
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getNextFile($db, $user, $direction) {
    // Get user's current position in editing queue
    $userId = $user['user_id'];

    // Get last edited file ID from session or use 0
    $lastId = $_GET['last_id'] ?? 0;

    // Build query based on direction
    if ($direction === 'next') {
        $query = "SELECT * FROM archives
                  WHERE id > ? AND completed = 0
                  ORDER BY id ASC
                  LIMIT 1";
    } else {
        $query = "SELECT * FROM archives
                  WHERE id < ? AND completed = 0
                  ORDER BY id DESC
                  LIMIT 1";
    }

    $file = $db->query($query, 'SELECT', 'ROW', [(int)$lastId]);

    if (!$file) {
        // If no file found in direction, get first/last file
        if ($direction === 'next') {
            $query = "SELECT * FROM archives WHERE completed = 0 ORDER BY id ASC LIMIT 1";
        } else {
            $query = "SELECT * FROM archives WHERE completed = 0 ORDER BY id DESC LIMIT 1";
        }
        $file = $db->query($query, 'SELECT', 'ROW');
    }

    if (!$file) {
        ApiResponse::error('No files available for editing', 404);
    }

    // Format response to match expected structure
    $response = [
        'id' => $file['id'],
        'name' => $file['name'],
        'document_type' => $file['document_type'],
        'description' => $file['description'] ?? '',
        'year' => $file['year'] ?? date('Y'),
        'url' => $file['path'],
        'number' => $file['number'] ?? '',
        'payee_name' => $file['payee_name'] ?? '',
        'sub_folder_id' => $file['sub_folder_id'] ?? '',
        'document_date' => $file['document_date'] ?? date('Y-m-d'),
        'duplicate' => $file['duplicate'] ?? '0',
        'completed' => $file['completed'] ?? '0'
    ];

    ApiResponse::success($response, 'File retrieved successfully');
}

function getCurrentFile($db, $id) {
    $query = "SELECT * FROM archives WHERE id = ?";
    $file = $db->query($query, 'SELECT', 'ROW', [(int)$id]);

    if (!$file) {
        ApiResponse::error('File not found', 404);
    }

    $response = [
        'id' => $file['id'],
        'name' => $file['name'],
        'document_type' => $file['document_type'],
        'description' => $file['description'] ?? '',
        'year' => $file['year'] ?? date('Y'),
        'url' => $file['path'],
        'number' => $file['number'] ?? '',
        'payee_name' => $file['payee_name'] ?? '',
        'sub_folder_id' => $file['sub_folder_id'] ?? '',
        'document_date' => $file['document_date'] ?? date('Y-m-d'),
        'duplicate' => $file['duplicate'] ?? '0',
        'completed' => $file['completed'] ?? '0'
    ];

    ApiResponse::success($response, 'File retrieved successfully');
}

function saveFileData($db, $user, $input) {
    // Validate required fields
    if (!isset($input['id'])) {
        ApiResponse::error('File ID is required', 400);
    }

    $fileId = (int)$input['id'];

    // Check if file exists
    $checkQuery = "SELECT id FROM archives WHERE id = ?";
    $exists = $db->query($checkQuery, 'SELECT', 'ROW', [$fileId]);

    if (!$exists) {
        ApiResponse::error('File not found', 404);
    }

    // Check permission for completing edition
    $completed = $input['completed'] ?? '0';
    if ($completed == '1' && !checkEditorPermission($db, $user, 'COMPLETE_EDITION')) {
        ApiResponse::error('Insufficient permissions to mark as completed', 403);
    }

    // Build update query
    $fields = [];
    $params = [];

    if (isset($input['name'])) {
        $fields[] = "name = ?";
        $params[] = $input['name'];
    }

    if (isset($input['document_type'])) {
        $fields[] = "document_type = ?";
        $params[] = $input['document_type'];
    }

    if (isset($input['year'])) {
        $fields[] = "year = ?";
        $params[] = $input['year'];
    }

    if (isset($input['description'])) {
        $fields[] = "description = ?";
        $params[] = $input['description'];
    }

    if (isset($input['number'])) {
        $fields[] = "number = ?";
        $params[] = $input['number'];
    }

    if (isset($input['sub_folder_id'])) {
        $fields[] = "sub_folder_id = ?";
        $params[] = $input['sub_folder_id'];
    }

    if (isset($input['payee_name'])) {
        $fields[] = "payee_name = ?";
        $params[] = $input['payee_name'];
    }

    if (isset($input['document_date'])) {
        $fields[] = "document_date = ?";
        $params[] = $input['document_date'];
    }

    // Note: cheque_number column does not exist in archives table
    // Removed to prevent SQL errors

    if (isset($input['duplicate'])) {
        $fields[] = "duplicate = ?";
        $params[] = $input['duplicate'];
    }

    if (isset($input['completed'])) {
        $fields[] = "completed = ?";
        $params[] = $input['completed'];
    }

    // Add editor and updated time
    $fields[] = "edited_by = ?";
    $fields[] = "updated_at = NOW()";
    $params[] = $user['user_id'];

    // Add file ID for WHERE clause
    $params[] = $fileId;

    $updateQuery = "UPDATE archives SET " . implode(", ", $fields) . " WHERE id = ?";

    $result = $db->query($updateQuery, 'UPDATE', 'ROW', $params);

    if ($result) {
        ApiResponse::success([
            'file_id' => $fileId,
            'updated' => true
        ], 'File data saved successfully');
    } else {
        ApiResponse::error('Failed to save file data', 500);
    }
}

function getEditorSubFolders($db, $user) {
    $userId = $user['user_id'];

    // Get user's group
    $userGroupQuery = "SELECT user_group FROM user_group_relation WHERE user = ?";
    $userGroupResult = $db->query($userGroupQuery, 'SELECT', 'ROW', [$userId]);

    if (!$userGroupResult) {
        ApiResponse::error('User group not found', 404);
    }

    $userGroupId = $userGroupResult['user_group'];

    // Get accessible sub folders based on group permissions
    $query = "SELECT adf.*, adfs.name AS folder_name
              FROM config_folder_access_rights cfar
              JOIN archive_document_sub_folders adf ON (adf.id = cfar.folder_sub_id)
              JOIN archive_document_folders adfs ON (adfs.id = adf.archive_document_folder_id)
              WHERE cfar.type = 'SUB FOLDER' AND cfar.user_group = ?
              ORDER BY adfs.name, adf.name";

    $subFolders = $db->query($query, 'SELECT', 'ALL', [$userGroupId]);

    ApiResponse::success([
        'sub_folders' => $subFolders ?: []
    ], 'Sub-folders retrieved successfully');
}

function getEditorDocumentTypes($db, $user) {
    $userId = $user['user_id'];

    // Get user's group
    $userGroupQuery = "SELECT user_group FROM user_group_relation WHERE user = ?";
    $userGroupResult = $db->query($userGroupQuery, 'SELECT', 'ROW', [$userId]);

    if (!$userGroupResult) {
        ApiResponse::error('User group not found', 404);
    }

    $userGroupId = $userGroupResult['user_group'];

    // Get accessible document types based on group permissions
    $query = "SELECT dt.*
              FROM config_folder_access_rights cfar
              JOIN document_types dt ON (dt.id = cfar.folder_sub_id)
              WHERE cfar.type = 'DOCUMENT TYPE' AND cfar.user_group = ?
              ORDER BY dt.name";

    $documentTypes = $db->query($query, 'SELECT', 'ALL', [$userGroupId]);

    ApiResponse::success([
        'document_types' => $documentTypes ?: []
    ], 'Document types retrieved successfully');
}

function createSubFolder($db, $user, $input) {
    // Check permission
    if (!checkEditorPermission($db, $user, 'SUB_FOLDER_ADDITION')) {
        ApiResponse::error('Insufficient permissions to create sub-folders', 403);
    }

    // Validate input
    if (!isset($input['name']) || !isset($input['archive_document_folder_id'])) {
        ApiResponse::error('Name and folder ID are required', 400);
    }

    // Check if folder exists
    $folderQuery = "SELECT id FROM archive_document_folders WHERE id = ?";
    $folder = $db->query($folderQuery, 'SELECT', 'ROW', [(int)$input['archive_document_folder_id']]);

    if (!$folder) {
        ApiResponse::error('Parent folder not found', 404);
    }

    // Insert sub-folder
    $insertQuery = "INSERT INTO archive_document_sub_folders
                    (name, description, archive_document_folder_id, created_at)
                    VALUES (?, ?, ?, NOW())";

    $result = $db->query($insertQuery, 'INSERT', 'ROW', [
        $input['name'],
        $input['description'] ?? '',
        (int)$input['archive_document_folder_id']
    ]);

    if ($result) {
        $subFolderId = $db->getLastInsertId();

        ApiResponse::success([
            'sub_folder_id' => $subFolderId,
            'name' => $input['name']
        ], 'Sub-folder created successfully', 201);
    } else {
        ApiResponse::error('Failed to create sub-folder', 500);
    }
}

function checkEditorPermission($db, $user, $permissionName) {
    $query = "SELECT p.permission_name
              FROM user_permissions up
              JOIN permissions p ON p.id = up.permission_id
              WHERE up.user_id = ? AND p.permission_name = ?";

    $result = $db->query($query, 'SELECT', 'ROW', [$user['user_id'], $permissionName]);

    return !empty($result);
}
