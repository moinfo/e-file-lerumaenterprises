<?php
/**
 * Statistics Endpoints
 *
 * Handles dashboard statistics and analytics
 */

function handleStats($method, $input) {
    if ($method !== 'GET') {
        ApiResponse::error('Method not allowed', 405);
    }

    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    $action = $_GET['action'] ?? 'dashboard';

    switch ($action) {
        case 'dashboard':
            getDashboardStats($db, $user);
            break;

        case 'recent-files':
            getRecentFiles($db, $user);
            break;

        case 'file-stats':
            getFileStats($db, $user);
            break;

        default:
            ApiResponse::error('Invalid stats action', 400);
    }
}

function getDashboardStats($db, $user) {
    // Get total folders
    $foldersCount = $db->query("SELECT COUNT(*) as count FROM archive_document_folders",
                               'SELECT', 'ROW');

    // Get total sub-folders
    $subFoldersCount = $db->query("SELECT COUNT(*) as count FROM archive_document_sub_folders",
                                  'SELECT', 'ROW');

    // Get total files
    $filesCount = $db->query("SELECT COUNT(*) as count FROM archives",
                            'SELECT', 'ROW');

    // Get completed files count
    $completedFilesCount = $db->query("SELECT COUNT(*) as count FROM archives WHERE completed = 1",
                                      'SELECT', 'ROW');

    // Get pending files count
    $pendingFilesCount = $db->query("SELECT COUNT(*) as count FROM archives WHERE completed = 0",
                                    'SELECT', 'ROW');

    // Get total document types
    $docTypesCount = $db->query("SELECT COUNT(*) as count FROM document_types",
                               'SELECT', 'ROW');

    // Get total users
    $usersCount = $db->query("SELECT COUNT(*) as count FROM users",
                            'SELECT', 'ROW');

    // Get files uploaded today
    $todayFilesCount = $db->query("SELECT COUNT(*) as count FROM archives
                                   WHERE DATE(created_at) = CURDATE()",
                                 'SELECT', 'ROW');

    // Get files uploaded this month
    $monthFilesCount = $db->query("SELECT COUNT(*) as count FROM archives
                                   WHERE MONTH(created_at) = MONTH(CURDATE())
                                   AND YEAR(created_at) = YEAR(CURDATE())",
                                 'SELECT', 'ROW');

    // Get recent activity (last 7 days)
    $recentActivityQuery = "SELECT
                             DATE(created_at) as date,
                             COUNT(*) as count
                           FROM archives
                           WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                           GROUP BY DATE(created_at)
                           ORDER BY date ASC";
    $recentActivity = $db->query($recentActivityQuery, 'SELECT');

    // Get files by document type
    $filesByType = $db->query("SELECT
                                 dt.name,
                                 COUNT(a.id) as count
                               FROM document_types dt
                               LEFT JOIN archives a ON a.document_type = dt.id
                               GROUP BY dt.id
                               ORDER BY count DESC
                               LIMIT 10",
                             'SELECT');

    ApiResponse::success([
        'totals' => [
            'folders' => (int)$foldersCount['count'],
            'sub_folders' => (int)$subFoldersCount['count'],
            'files' => (int)$filesCount['count'],
            'completed_files' => (int)$completedFilesCount['count'],
            'pending_files' => (int)$pendingFilesCount['count'],
            'document_types' => (int)$docTypesCount['count'],
            'users' => (int)$usersCount['count']
        ],
        'activity' => [
            'today' => (int)$todayFilesCount['count'],
            'this_month' => (int)$monthFilesCount['count'],
            'last_7_days' => $recentActivity ?: []
        ],
        'files_by_type' => $filesByType ?: []
    ], 'Dashboard statistics retrieved successfully');
}

function getRecentFiles($db, $user) {
    $limit = $_GET['limit'] ?? 20;

    $query = "SELECT
                a.*,
                adsf.name as sub_folder_name,
                adf.name as folder_name,
                dt.name as document_type_name,
                u.username as editor_name
              FROM archives a
              LEFT JOIN archive_document_sub_folders adsf ON adsf.id = a.sub_folder_id
              LEFT JOIN archive_document_folders adf ON adf.id = adsf.archive_document_folder_id
              LEFT JOIN document_types dt ON dt.id = a.document_type
              LEFT JOIN users u ON u.id = a.edited_by
              ORDER BY a.created_at DESC
              LIMIT ?";

    $files = $db->query($query, 'SELECT', 'ALL', [(int)$limit]);

    ApiResponse::success([
        'files' => $files ?: [],
        'total' => count($files ?: [])
    ], 'Recent files retrieved successfully');
}

function getFileStats($db, $user) {
    // Get file size statistics
    $sizeStats = $db->query("SELECT
                               COUNT(*) as total_files,
                               SUM(file_size) as total_size,
                               AVG(file_size) as avg_size,
                               MAX(file_size) as max_size,
                               MIN(file_size) as min_size
                             FROM archives
                             WHERE file_size IS NOT NULL",
                           'SELECT', 'ROW');

    // Get files count by month (last 12 months)
    $filesByMonth = $db->query("SELECT
                                  DATE_FORMAT(created_at, '%Y-%m') as month,
                                  COUNT(*) as count
                                FROM archives
                                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                                ORDER BY month ASC",
                              'SELECT');

    // Get top contributors
    $topContributors = $db->query("SELECT
                                     u.username,
                                     COUNT(a.id) as file_count
                                   FROM users u
                                   LEFT JOIN archives a ON a.edited_by = u.id
                                   GROUP BY u.id
                                   ORDER BY file_count DESC
                                   LIMIT 10",
                                 'SELECT');

    ApiResponse::success([
        'size_stats' => [
            'total_files' => (int)($sizeStats['total_files'] ?? 0),
            'total_size' => (int)($sizeStats['total_size'] ?? 0),
            'avg_size' => (float)($sizeStats['avg_size'] ?? 0),
            'max_size' => (int)($sizeStats['max_size'] ?? 0),
            'min_size' => (int)($sizeStats['min_size'] ?? 0),
            'total_size_mb' => round(($sizeStats['total_size'] ?? 0) / 1024 / 1024, 2)
        ],
        'files_by_month' => $filesByMonth ?: [],
        'top_contributors' => $topContributors ?: []
    ], 'File statistics retrieved successfully');
}
