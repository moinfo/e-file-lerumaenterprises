<?php
/**
 * Dashboard Endpoints
 *
 * Handles dashboard statistics and data
 */

function handleDashboard($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    switch ($method) {
        case 'GET':
            if ($action === 'stats' || $id === 'stats') {
                getDashboardStats($db, $user);
            } else {
                getDashboardData($db, $user);
            }
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getDashboardData($db, $user) {
    // Get statistics
    $stats = getStatistics($db);

    // Get top folders
    $topFoldersQuery = "SELECT adf.*, COUNT(a.sub_folder_id) AS total_files
                        FROM archive_document_folders adf
                        JOIN archive_document_sub_folders adsf ON (adsf.archive_document_folder_id = adf.id)
                        JOIN archives a ON (a.sub_folder_id = adsf.id)
                        GROUP BY adf.id
                        ORDER BY COUNT(a.sub_folder_id) DESC
                        LIMIT 10";
    $topFolders = $db->query($topFoldersQuery, 'SELECT');

    // Get top document types
    $topDocTypesQuery = "SELECT dt.*, COUNT(a.document_type) AS total_files
                         FROM document_types dt
                         JOIN archives a ON (a.document_type = dt.id)
                         GROUP BY dt.id
                         ORDER BY COUNT(a.document_type) DESC
                         LIMIT 10";
    $topDocumentTypes = $db->query($topDocTypesQuery, 'SELECT');

    ApiResponse::success([
        'stats' => $stats,
        'top_folders' => $topFolders ?: [],
        'top_document_types' => $topDocumentTypes ?: []
    ], 'Dashboard data retrieved successfully');
}

function getDashboardStats($db, $user) {
    $stats = getStatistics($db);
    ApiResponse::success($stats, 'Dashboard statistics retrieved successfully');
}

function getStatistics($db) {
    // Count unedited files
    $unedited = $db->query("SELECT COUNT(*) as count FROM archives WHERE completed = 0", 'SELECT', 'ROW');
    $uneditedCount = $unedited['count'] ?? 0;

    // Count completed files
    $completed = $db->query("SELECT COUNT(*) as count FROM archives WHERE completed = 1", 'SELECT', 'ROW');
    $completedCount = $completed['count'] ?? 0;

    // Count total files (uploaded)
    $total = $db->query("SELECT COUNT(*) as count FROM archives", 'SELECT', 'ROW');
    $totalCount = $total['count'] ?? 0;

    // Count folders
    $folders = $db->query("SELECT COUNT(*) as count FROM archive_document_folders", 'SELECT', 'ROW');
    $foldersCount = $folders['count'] ?? 0;

    // Count document types
    $docTypes = $db->query("SELECT COUNT(*) as count FROM document_types", 'SELECT', 'ROW');
    $docTypesCount = $docTypes['count'] ?? 0;

    return [
        'uploaded_files' => $totalCount,
        'unedited_files' => $uneditedCount,
        'completed_files' => $completedCount,
        'total_folders' => $foldersCount,
        'total_document_types' => $docTypesCount,
        'completion_percentage' => $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0
    ];
}
