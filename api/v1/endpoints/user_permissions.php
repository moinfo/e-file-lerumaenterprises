<?php
/**
 * User Permissions Endpoints
 *
 * Returns user's permissions based on their group membership
 */

function handleUserPermissions($method, $action, $id, $input) {
    $db = new DB();
    $user = ApiAuth::validateToken();

    switch ($method) {
        case 'GET':
            if ($action === 'my-permissions' || $id === 'my-permissions') {
                getUserPermissions($db, $user);
            } else {
                ApiResponse::error('Invalid action', 400);
            }
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getUserPermissions($db, $user) {
    // Handle both array and object user formats
    if (is_array($user) && isset($user[0])) {
        // User is returned as array with index 0
        $userId = $user[0]['user_id'] ?? $user[0]['id'];
    } else {
        // User is returned as direct array
        $userId = $user['user_id'] ?? $user['id'];
    }

    // Get user's groups
    $groupsQuery = "SELECT user_group FROM user_group_relation WHERE user = ?";
    $userGroups = $db->query($groupsQuery, 'SELECT', 'ALL', [$userId]);

    // Get menu access - always get individual menu access
    $menusQuery = "SELECT DISTINCT m.id, m.name, m.link, m.parent_menu, m.icon, m.list_order, m.title
                   FROM config_access_rights car
                   JOIN menu m ON m.id = car.menu
                   WHERE m.status = 'ACTIVE'";

    // Get role access - include both INDIVIDUAL and GROUP permissions
    $rolesQuery = "SELECT DISTINCT r.id, r.keyword, r.description, r.default_access
                   FROM config_role_access cra
                   JOIN config_roles r ON r.id = cra.role_id
                   WHERE (
                       (cra.access_type = 'INDIVIDUAL' AND cra.user_id = ?)";

    $roleParams = [$userId];

    if (!empty($userGroups)) {
        // Extract group IDs
        $groupIds = array_column($userGroups, 'user_group');
        $groupIdsStr = implode(',', array_map('intval', $groupIds));

        // Add group-based menu access
        $menusQuery .= " AND car.user_group IN ($groupIdsStr)";

        // Add group-based role access
        $rolesQuery .= " OR (cra.access_type = 'GROUP' AND cra.user_id IN ($groupIdsStr))";
    }

    $menusQuery .= " ORDER BY m.list_order, m.name";
    $rolesQuery .= ") AND cra.access = 1";

    // For non-prepared statements (no params), use fetchQuery approach
    $menus = !empty($userGroups) ? $db->fetchQuery($menusQuery, 'SELECT', false) : [];
    $roles = $db->query($rolesQuery, 'SELECT', 'ALL', $roleParams);

    // Get folder, sub-folder, and document type access (only for group-based access)
    $folders = [];
    $subFolders = [];
    $documentTypes = [];

    if (!empty($userGroups)) {
        // Get folder access (type = 'FOLDER')
        $foldersQuery = "SELECT DISTINCT f.id, f.name, f.description
                         FROM config_folder_access_rights cfar
                         JOIN archive_document_folders f ON f.id = cfar.folder_sub_id
                         WHERE cfar.user_group IN ($groupIdsStr)
                         AND cfar.type = 'FOLDER'";
        $folders = $db->fetchQuery($foldersQuery, 'SELECT', false);

        // Get sub-folder access (type = 'SUB FOLDER')
        $subFoldersQuery = "SELECT DISTINCT sf.id, sf.name, sf.description, sf.archive_document_folder_id as folder_id
                            FROM config_folder_access_rights cfar
                            JOIN archive_document_sub_folders sf ON sf.id = cfar.folder_sub_id
                            WHERE cfar.user_group IN ($groupIdsStr)
                            AND cfar.type = 'SUB FOLDER'";
        $subFolders = $db->fetchQuery($subFoldersQuery, 'SELECT', false);

        // Get document type access (type = 'DOCUMENT TYPE')
        $documentTypesQuery = "SELECT DISTINCT dt.id, dt.name, dt.keyword
                               FROM config_folder_access_rights cfar
                               JOIN document_types dt ON dt.id = cfar.folder_sub_id
                               WHERE cfar.user_group IN ($groupIdsStr)
                               AND cfar.type = 'DOCUMENT TYPE'";
        $documentTypes = $db->fetchQuery($documentTypesQuery, 'SELECT', false);
    }

    ApiResponse::success([
        'menus' => $menus ?? [],
        'roles' => $roles ?? [],
        'folders' => $folders ?? [],
        'sub_folders' => $subFolders ?? [],
        'document_types' => $documentTypes ?? []
    ], 'User permissions retrieved successfully');
}
