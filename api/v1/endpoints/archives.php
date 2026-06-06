<?php
/**
 * Archives Endpoints (Edited Files)
 *
 * Handles archive/file management operations
 */

function handleArchives($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    switch ($method) {
        case 'GET':
            if ($id) {
                getSingleArchive($db, $id);
            } else {
                getAllArchives($db, $_GET);
            }
            break;

        case 'PUT':
            if (!$id) {
                ApiResponse::error('Archive ID required', 400);
            }
            updateArchive($db, $id, $input);
            break;

        case 'DELETE':
            if (!$id) {
                ApiResponse::error('Archive ID required', 400);
            }
            deleteArchive($db, $id);
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getAllArchives($db, $params) {
    // Build query with filters
    $query = "SELECT
                a.id,
                a.name,
                a.year,
                a.document_type,
                a.document_date,
                a.sub_folder_id,
                a.description,
                a.path,
                a.updated_at,
                a.size,
                a.mime,
                dt.name as document_type_name,
                adsf.name as sub_folder_name,
                adf.id as folder_id,
                adf.name as folder_name
              FROM archives a
              LEFT JOIN document_types dt ON a.document_type = dt.id
              LEFT JOIN archive_document_sub_folders adsf ON a.sub_folder_id = adsf.id
              LEFT JOIN archive_document_folders adf ON adsf.archive_document_folder_id = adf.id
              WHERE 1=1";

    $queryParams = [];

    // Filter by year
    if (isset($params['year']) && !empty($params['year'])) {
        $query .= " AND a.year = ?";
        $queryParams[] = $params['year'];
    }

    // Filter by folder
    if (isset($params['folder_id']) && !empty($params['folder_id'])) {
        $query .= " AND adf.id = ?";
        $queryParams[] = $params['folder_id'];
    }

    // Filter by sub folder
    if (isset($params['sub_folder_id']) && !empty($params['sub_folder_id'])) {
        $query .= " AND a.sub_folder_id = ?";
        $queryParams[] = $params['sub_folder_id'];
    }

    // Filter by document type
    if (isset($params['document_type']) && !empty($params['document_type'])) {
        $query .= " AND a.document_type = ?";
        $queryParams[] = $params['document_type'];
    }

    // Filter by description (partial match)
    if (isset($params['description']) && !empty($params['description'])) {
        $query .= " AND a.description LIKE ?";
        $queryParams[] = '%' . $params['description'] . '%';
    }

    // Filter by date range
    if (isset($params['date_from']) && !empty($params['date_from'])) {
        $query .= " AND a.document_date >= ?";
        $queryParams[] = $params['date_from'];
    }

    if (isset($params['date_to']) && !empty($params['date_to'])) {
        $query .= " AND a.document_date <= ?";
        $queryParams[] = $params['date_to'];
    }

    // Filter by search query (searches name and description)
    if (isset($params['q']) && !empty($params['q'])) {
        $query .= " AND (a.name LIKE ? OR a.description LIKE ?)";
        $searchTerm = '%' . $params['q'] . '%';
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
    }

    // Add pagination
    $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
    $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

    // When searching, increase limit to search all files
    if (isset($params['q']) && !empty($params['q'])) {
        $limit = 1000; // Search through more files
    }

    $query .= " ORDER BY a.updated_at DESC, a.id DESC LIMIT ? OFFSET ?";
    $queryParams[] = $limit;
    $queryParams[] = $offset;

    $archives = $db->query($query, 'ALL', $queryParams);

    ApiResponse::success([
        'archives' => $archives ?: [],
        'total' => count($archives ?: [])
    ], 'Archives retrieved successfully');
}

function getSingleArchive($db, $id) {
    $query = "SELECT
                a.id,
                a.name,
                a.year,
                a.document_type,
                a.document_date,
                a.sub_folder_id,
                a.description,
                a.path,
                a.updated_at,
                a.size,
                a.mime,
                dt.name as document_type_name,
                adsf.name as sub_folder_name,
                adf.id as folder_id,
                adf.name as folder_name
              FROM archives a
              LEFT JOIN document_types dt ON a.document_type = dt.id
              LEFT JOIN archive_document_sub_folders adsf ON a.sub_folder_id = adsf.id
              LEFT JOIN archive_document_folders adf ON adsf.archive_document_folder_id = adf.id
              WHERE a.id = ?";

    $archive = $db->query($query, 'ROW', [$id]);

    if (!$archive) {
        ApiResponse::error('Archive not found', 404);
    }

    ApiResponse::success(['archive' => $archive], 'Archive retrieved successfully');
}

function updateArchive($db, $id, $input) {
    // Check if archive exists
    $archive = $db->query("SELECT * FROM archives WHERE id = ?", 'ROW', [$id]);

    if (!$archive) {
        ApiResponse::error('Archive not found', 404);
    }

    // Build update query
    $updates = [];
    $params = [];

    if (isset($input['name'])) {
        $updates[] = "name = ?";
        $params[] = trim($input['name']);
    }

    if (isset($input['description'])) {
        $updates[] = "description = ?";
        $params[] = trim($input['description']);
    }

    if (isset($input['year'])) {
        $updates[] = "year = ?";
        $params[] = $input['year'];
    }

    if (isset($input['document_type'])) {
        $updates[] = "document_type = ?";
        $params[] = $input['document_type'];
    }

    if (isset($input['sub_folder_id'])) {
        $updates[] = "sub_folder_id = ?";
        $params[] = $input['sub_folder_id'];
    }

    if (isset($input['document_date'])) {
        $updates[] = "document_date = ?";
        $params[] = $input['document_date'];
    }

    if (empty($updates)) {
        ApiResponse::error('No fields to update', 400);
    }

    $params[] = $id;

    $updateQuery = "UPDATE archives SET " . implode(', ', $updates) . " WHERE id = ?";
    $result = $db->query($updateQuery, 'UPDATE', $params);

    if ($result === false) {
        ApiResponse::error('Failed to update archive in database', 500);
    }

    // Get updated archive
    $updatedArchive = $db->query(
        "SELECT a.*, dt.name as document_type_name, adsf.name as sub_folder_name, adf.name as folder_name
         FROM archives a
         LEFT JOIN document_types dt ON a.document_type = dt.id
         LEFT JOIN archive_document_sub_folders adsf ON a.sub_folder_id = adsf.id
         LEFT JOIN archive_document_folders adf ON adsf.archive_document_folder_id = adf.id
         WHERE a.id = ?",
        'ROW', [$id]
    );

    ApiResponse::success(['archive' => $updatedArchive], 'Archive updated successfully');
}

function deleteArchive($db, $id) {
    // Check if archive exists
    $archive = $db->query("SELECT * FROM archives WHERE id = ?", 'ROW', [$id]);

    if (!$archive) {
        ApiResponse::error('Archive not found', 404);
    }

    // Delete the archive
    $deleteQuery = "DELETE FROM archives WHERE id = ?";
    $db->query($deleteQuery, 'DELETE', [$id]);

    ApiResponse::success([], 'Archive deleted successfully');
}
