<?php
/**
 * Search Endpoints
 *
 * Handles global search across files, folders, and document types
 */

function handleSearch($method, $input) {
    if ($method !== 'GET') {
        ApiResponse::error('Method not allowed', 405);
    }

    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    // Get search query
    $query = $_GET['q'] ?? $_GET['query'] ?? '';

    if (empty(trim($query))) {
        ApiResponse::error('Search query is required', 400);
    }

    $searchTerm = '%' . trim($query) . '%';

    // Search in files/archives
    $filesQuery = "SELECT
                    a.id,
                    a.name,
                    a.description,
                    a.path,
                    a.year,
                    a.completed,
                    a.document_date,
                    dt.name as document_type_name,
                    'file' as type,
                    adsf.name as sub_folder_name,
                    adf.name as folder_name
                   FROM archives a
                   LEFT JOIN archive_document_sub_folders adsf ON adsf.id = a.sub_folder_id
                   LEFT JOIN archive_document_folders adf ON adf.id = adsf.archive_document_folder_id
                   LEFT JOIN document_types dt ON dt.id = a.document_type
                   WHERE a.name LIKE ? OR a.description LIKE ? OR a.year LIKE ?
                   LIMIT 50";

    $files = $db->query($filesQuery, 'SELECT', 'ALL', [$searchTerm, $searchTerm, $searchTerm]);

    // Search in folders
    $foldersQuery = "SELECT
                        id,
                        name,
                        description,
                        'folder' as type
                     FROM archive_document_folders
                     WHERE name LIKE ? OR description LIKE ?
                     LIMIT 20";

    $folders = $db->query($foldersQuery, 'SELECT', 'ALL', [$searchTerm, $searchTerm]);

    // Search in sub-folders
    $subFoldersQuery = "SELECT
                          adsf.id,
                          adsf.name,
                          adsf.description,
                          'sub_folder' as type,
                          adf.name as folder_name
                        FROM archive_document_sub_folders adsf
                        LEFT JOIN archive_document_folders adf ON adf.id = adsf.archive_document_folder_id
                        WHERE adsf.name LIKE ? OR adsf.description LIKE ?
                        LIMIT 20";

    $subFolders = $db->query($subFoldersQuery, 'SELECT', 'ALL', [$searchTerm, $searchTerm]);

    // Search in document types
    $docTypesQuery = "SELECT
                        id,
                        name,
                        keyword,
                        'document_type' as type
                      FROM document_types
                      WHERE name LIKE ? OR keyword LIKE ?
                      LIMIT 20";

    $documentTypes = $db->query($docTypesQuery, 'SELECT', 'ALL', [$searchTerm, $searchTerm]);

    // Combine all results into a single list for mobile app
    $allResults = array_merge(
        $files ?: [],
        $folders ?: [],
        $subFolders ?: [],
        $documentTypes ?: []
    );

    ApiResponse::success([
        'query' => $query,
        'results' => $allResults,
        'total' => count($allResults),
        'categorized' => [
            'files' => $files ?: [],
            'folders' => $folders ?: [],
            'sub_folders' => $subFolders ?: [],
            'document_types' => $documentTypes ?: []
        ]
    ], 'Search completed successfully');
}
