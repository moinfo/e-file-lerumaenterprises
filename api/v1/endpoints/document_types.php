<?php
/**
 * Document Types Endpoints
 *
 * Handles document type operations (CRUD)
 */

function handleDocumentTypes($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    switch ($method) {
        case 'GET':
            if ($id) {
                getSingleDocumentType($db, $id, $user);
            } else {
                getAllDocumentTypes($db, $user);
            }
            break;

        case 'POST':
            createDocumentType($db, $input, $user);
            break;

        case 'PUT':
            if (!$id) {
                ApiResponse::error('Document type ID required', 400);
            }
            updateDocumentType($db, $id, $input, $user);
            break;

        case 'DELETE':
            if (!$id) {
                ApiResponse::error('Document type ID required', 400);
            }
            deleteDocumentType($db, $id, $user);
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getAllDocumentTypes($db, $user) {
    // Check if filtering by sub_folder_id
    $subFolderId = $_GET['sub_folder_id'] ?? null;

    if ($subFolderId) {
        // Get document types that have files in this specific sub-folder
        $query = "SELECT dt.*, dt.name AS document_type_name,
                         COUNT(DISTINCT a.id) as file_count
                  FROM document_types dt
                  LEFT JOIN archives a ON (a.document_type = dt.id AND a.sub_folder_id = ?)
                  GROUP BY dt.id
                  HAVING file_count > 0
                  ORDER BY dt.name ASC";

        $documentTypes = $db->query($query, 'SELECT', 'ALL', [$subFolderId]);
    } else {
        // Get all document types with total file counts
        $query = "SELECT
                    dt.*,
                    COUNT(DISTINCT a.id) as file_count
                  FROM document_types dt
                  LEFT JOIN archives a ON a.document_type = dt.id
                  GROUP BY dt.id
                  ORDER BY dt.name ASC";

        $documentTypes = $db->query($query, 'SELECT');
    }

    // Filter by user access
    $accessibleDocumentTypes = [];
    foreach ($documentTypes as $documentType) {
        if (checkDocumentTypeAccess($db, $documentType['id'], $user)) {
            $accessibleDocumentTypes[] = $documentType;
        }
    }

    ApiResponse::success([
        'document_types' => $accessibleDocumentTypes,
        'total' => count($accessibleDocumentTypes),
        'sub_folder_id' => $subFolderId
    ], 'Document types retrieved successfully');
}

function getSingleDocumentType($db, $id, $user) {
    $query = "SELECT
                dt.*,
                COUNT(DISTINCT a.id) as file_count
              FROM document_types dt
              LEFT JOIN archives a ON a.document_type = dt.id
              WHERE dt.id = ?
              GROUP BY dt.id";

    $documentType = $db->query($query, 'SELECT', 'ROW', [$id]);

    if (!$documentType) {
        ApiResponse::error('Document type not found', 404);
    }

    ApiResponse::success(['document_type' => $documentType], 'Document type retrieved successfully');
}

function createDocumentType($db, $input, $user) {
    // Check if user has DOCUMENT_TYPE_ADDITION role
    requireRole($db, $user, 'DOCUMENT_TYPE_ADDITION', 'You do not have permission to add document types');

    // Validate input
    if (!isset($input['name']) || empty(trim($input['name']))) {
        ApiResponse::error('Document type name is required', 400);
    }

    if (!isset($input['keyword']) || empty(trim($input['keyword']))) {
        ApiResponse::error('Keyword is required', 400);
    }

    $name = trim($input['name']);
    $keyword = trim($input['keyword']);

    // Check for duplicate name or keyword
    $checkQuery = "SELECT id FROM document_types WHERE name = ? OR keyword = ?";
    $existing = $db->query($checkQuery, 'SELECT', 'ROW', [$name, $keyword]);

    if ($existing) {
        ApiResponse::error('Document type with this name or keyword already exists', 409);
    }

    // Create document type
    $insertQuery = "INSERT INTO document_types (name, keyword)
                    VALUES (?, ?)";
    $db->query($insertQuery, 'INSERT', 'ROW', [$name, $keyword]);

    $documentTypeId = $db->getLastInsertId();

    // Get created document type
    $documentType = $db->query("SELECT * FROM document_types WHERE id = ?",
                              'SELECT', 'ROW', [$documentTypeId]);

    ApiResponse::success(['document_type' => $documentType], 'Document type created successfully', 201);
}

function updateDocumentType($db, $id, $input, $user) {
    // Check if user has DOCUMENT_TYPE_EDITION role
    requireRole($db, $user, 'DOCUMENT_TYPE_EDITION', 'You do not have permission to edit document types');

    // Check if document type exists
    $documentType = $db->query("SELECT * FROM document_types WHERE id = ?",
                              'SELECT', 'ROW', [$id]);

    if (!$documentType) {
        ApiResponse::error('Document type not found', 404);
    }

    // Validate input
    if (!isset($input['name']) || empty(trim($input['name']))) {
        ApiResponse::error('Document type name is required', 400);
    }

    if (!isset($input['keyword']) || empty(trim($input['keyword']))) {
        ApiResponse::error('Keyword is required', 400);
    }

    $name = trim($input['name']);
    $keyword = trim($input['keyword']);

    // Check for duplicate name or keyword (excluding current document type)
    $checkQuery = "SELECT id FROM document_types WHERE (name = ? OR keyword = ?) AND id != ?";
    $existing = $db->query($checkQuery, 'SELECT', 'ROW', [$name, $keyword, $id]);

    if ($existing) {
        ApiResponse::error('Document type with this name or keyword already exists', 409);
    }

    // Update document type
    $updateQuery = "UPDATE document_types
                    SET name = ?, keyword = ?
                    WHERE id = ?";
    $result = $db->query($updateQuery, 'UPDATE', 'ROW', [$name, $keyword, $id]);

    if ($result === false) {
        ApiResponse::error('Failed to update document type in database', 500);
    }

    // Get updated document type
    $updatedDocumentType = $db->query("SELECT * FROM document_types WHERE id = ?",
                                     'SELECT', 'ROW', [$id]);

    ApiResponse::success(['document_type' => $updatedDocumentType], 'Document type updated successfully');
}

function deleteDocumentType($db, $id, $user) {
    // Check if user has DOCUMENT_TYPE_DELETION role
    requireRole($db, $user, 'DOCUMENT_TYPE_DELETION', 'You do not have permission to delete document types');

    // Check if document type exists
    $documentType = $db->query("SELECT * FROM document_types WHERE id = ?",
                              'SELECT', 'ROW', [$id]);

    if (!$documentType) {
        ApiResponse::error('Document type not found', 404);
    }

    // Check if document type has files
    $fileCount = $db->query("SELECT COUNT(*) as count FROM archives WHERE document_type = ?",
                           'SELECT', 'ROW', [$id]);

    if ($fileCount['count'] > 0) {
        ApiResponse::error('Cannot delete document type with files. Please reassign or delete files first.', 400);
    }

    // Delete document type
    $deleteQuery = "DELETE FROM document_types WHERE id = ?";
    $db->query($deleteQuery, 'DELETE', 'ROW', [$id]);

    ApiResponse::success([], 'Document type deleted successfully');
}

function checkDocumentTypeAccess($db, $documentTypeId, $user) {
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

    // Check if user has access to this document type through any of their groups
    $accessQuery = "SELECT * FROM config_folder_access_rights
                    WHERE user_group IN ($groupIdsStr) AND folder_sub_id = ? AND type = 'DOCUMENT TYPE'";
    $access = $db->query($accessQuery, 'SELECT', 'ROW', [$documentTypeId]);

    return $access !== null && $access !== false;
}
