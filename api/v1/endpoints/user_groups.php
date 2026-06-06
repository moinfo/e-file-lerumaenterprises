<?php
/**
 * User Groups and Permissions Endpoints
 *
 * Handles user groups, permissions, and folder access rights
 */

function handleUserGroups($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    // Check admin permissions for all operations
    if (!checkAdminPermission($db, $user)) {
        ApiResponse::error('Insufficient permissions. Admin access required.', 403);
    }

    switch ($method) {
        case 'GET':
            if ($id && $action === 'permissions') {
                getGroupPermissions($db, $id);
            } elseif ($id && $action === 'folder-access') {
                getGroupFolderAccess($db, $id);
            } elseif ($id) {
                getSingleUserGroup($db, $id);
            } else {
                getAllUserGroups($db);
            }
            break;

        case 'POST':
            if ($action === 'assign-user' || $id === 'assign-user') {
                assignUserToGroup($db, $input);
            } else {
                createUserGroup($db, $input);
            }
            break;

        case 'PUT':
            if ($id && $action === 'folder-access') {
                updateGroupFolderAccess($db, $id, $input);
            } elseif ($id && $action === 'permissions') {
                updateGroupPermissions($db, $id, $input);
            } elseif ($id) {
                updateUserGroup($db, $id, $input);
            } else {
                ApiResponse::error('Group ID required', 400);
            }
            break;

        case 'DELETE':
            if ($action === 'remove-user' || $id === 'remove-user') {
                removeUserFromGroup($db, $input);
            } elseif (!$id) {
                ApiResponse::error('Group ID required', 400);
            } else {
                deleteUserGroup($db, $id);
            }
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getAllUserGroups($db) {
    // Get all groups with member count using a single query
    $query = "SELECT ug.*, COUNT(ugr.user) as member_count
              FROM user_groups ug
              LEFT JOIN user_group_relation ugr ON ugr.user_group = ug.id
              GROUP BY ug.id, ug.keyword, ug.name, ug.system_group
              ORDER BY ug.name";
    $groups = $db->query($query, 'SELECT');

    // Ensure member_count is an integer
    if ($groups) {
        foreach ($groups as &$group) {
            $group['member_count'] = (int)($group['member_count'] ?? 0);
        }
    }

    ApiResponse::success([
        'user_groups' => $groups ?: []
    ], 'User groups retrieved successfully');
}

function getSingleUserGroup($db, $id) {
    $query = "SELECT * FROM user_groups WHERE id = ?";
    $group = $db->query($query, 'SELECT', 'ROW', [(int)$id]);

    if (!$group) {
        ApiResponse::error('User group not found', 404);
    }

    // Get members
    $membersQuery = "SELECT u.id, u.username
                     FROM user_group_relation ugr
                     JOIN users u ON u.id = ugr.user
                     WHERE ugr.user_group = ?";
    $members = $db->query($membersQuery, 'SELECT', 'ALL', [(int)$id]);

    $group['members'] = $members ?: [];

    ApiResponse::success([
        'user_group' => $group
    ], 'User group retrieved successfully');
}

function createUserGroup($db, $input) {
    // Validate input
    if (!isset($input['name'])) {
        ApiResponse::error('Group name is required', 400);
    }

    // Check if group name already exists
    $checkQuery = "SELECT id FROM user_groups WHERE name = ?";
    $exists = $db->query($checkQuery, 'SELECT', 'ROW', [$input['name']]);

    if ($exists) {
        ApiResponse::error('Group name already exists', 409);
    }

    // Insert group
    // Note: keyword is required but can be auto-generated from name
    $keyword = strtoupper(str_replace(' ', '_', $input['name']));
    $insertQuery = "INSERT INTO user_groups (keyword, name, system_group)
                    VALUES (?, ?, 0)";

    $result = $db->query($insertQuery, 'INSERT', 'ROW', [
        $keyword,
        $input['name']
    ]);

    if ($result) {
        $groupId = $db->getLastInsertId();

        ApiResponse::success([
            'user_group_id' => $groupId,
            'name' => $input['name']
        ], 'User group created successfully', 201);
    } else {
        ApiResponse::error('Failed to create user group', 500);
    }
}

function updateUserGroup($db, $id, $input) {
    $groupId = (int)$id;

    // Check if group exists
    $checkQuery = "SELECT id FROM user_groups WHERE id = ?";
    $exists = $db->query($checkQuery, 'SELECT', 'ROW', [$groupId]);

    if (!$exists) {
        ApiResponse::error('User group not found', 404);
    }

    // Build update query
    $fields = [];
    $params = [];

    if (isset($input['name'])) {
        $fields[] = "name = ?";
        $params[] = $input['name'];

        // Also update keyword when name changes
        $keyword = strtoupper(str_replace(' ', '_', $input['name']));
        $fields[] = "keyword = ?";
        $params[] = $keyword;
    }

    if (empty($fields)) {
        ApiResponse::error('No fields to update', 400);
    }

    $params[] = $groupId;

    $updateQuery = "UPDATE user_groups SET " . implode(", ", $fields) . " WHERE id = ?";

    $result = $db->query($updateQuery, 'UPDATE', 'ROW', $params);

    if ($result) {
        ApiResponse::success([
            'updated' => true,
            'user_group_id' => $groupId
        ], 'User group updated successfully');
    } else {
        ApiResponse::error('Failed to update user group', 500);
    }
}

function deleteUserGroup($db, $id) {
    $groupId = (int)$id;

    // Check if group exists
    $checkQuery = "SELECT id FROM user_groups WHERE id = ?";
    $exists = $db->query($checkQuery, 'SELECT', 'ROW', [$groupId]);

    if (!$exists) {
        ApiResponse::error('User group not found', 404);
    }

    // Check if group has members
    $membersQuery = "SELECT COUNT(*) as count FROM user_group_relation WHERE user_group = ?";
    $memberCount = $db->query($membersQuery, 'SELECT', 'ROW', [$groupId]);

    if ((int)$memberCount['count'] > 0) {
        ApiResponse::error('Cannot delete group with members. Remove members first.', 400);
    }

    // Delete group
    $deleteQuery = "DELETE FROM user_groups WHERE id = ?";
    $result = $db->query($deleteQuery, 'DELETE', 'ROW', [$groupId]);

    if ($result) {
        ApiResponse::success([], 'User group deleted successfully');
    } else {
        ApiResponse::error('Failed to delete user group', 500);
    }
}

function assignUserToGroup($db, $input) {
    // Validate input
    if (!isset($input['user_id']) || !isset($input['group_id'])) {
        ApiResponse::error('User ID and Group ID are required', 400);
    }

    $userId = (int)$input['user_id'];
    $groupId = (int)$input['group_id'];

    // Check if user exists
    $userQuery = "SELECT id FROM users WHERE id = ?";
    $userExists = $db->query($userQuery, 'SELECT', 'ROW', [$userId]);

    if (!$userExists) {
        ApiResponse::error('User not found', 404);
    }

    // Check if group exists
    $groupQuery = "SELECT id FROM user_groups WHERE id = ?";
    $groupExists = $db->query($groupQuery, 'SELECT', 'ROW', [$groupId]);

    if (!$groupExists) {
        ApiResponse::error('Group not found', 404);
    }

    // Check if already assigned
    $checkQuery = "SELECT id FROM user_group_relation WHERE user = ? AND user_group = ?";
    $alreadyAssigned = $db->query($checkQuery, 'SELECT', 'ROW', [$userId, $groupId]);

    if ($alreadyAssigned) {
        ApiResponse::error('User already assigned to this group', 409);
    }

    // Assign user to group
    $insertQuery = "INSERT INTO user_group_relation (user, user_group)
                    VALUES (?, ?)";

    $result = $db->query($insertQuery, 'INSERT', 'ROW', [$userId, $groupId]);

    if ($result) {
        ApiResponse::success([
            'user_id' => $userId,
            'group_id' => $groupId
        ], 'User assigned to group successfully', 201);
    } else {
        ApiResponse::error('Failed to assign user to group', 500);
    }
}

function removeUserFromGroup($db, $input) {
    // Validate input
    if (!isset($input['user_id']) || !isset($input['group_id'])) {
        ApiResponse::error('User ID and Group ID are required', 400);
    }

    $userId = (int)$input['user_id'];
    $groupId = (int)$input['group_id'];

    // Check if assignment exists
    $checkQuery = "SELECT id FROM user_group_relation WHERE user = ? AND user_group = ?";
    $exists = $db->query($checkQuery, 'SELECT', 'ROW', [$userId, $groupId]);

    if (!$exists) {
        ApiResponse::error('User is not assigned to this group', 404);
    }

    // Remove user from group
    $deleteQuery = "DELETE FROM user_group_relation WHERE user = ? AND user_group = ?";
    $result = $db->query($deleteQuery, 'DELETE', 'ROW', [$userId, $groupId]);

    if ($result) {
        ApiResponse::success([
            'user_id' => $userId,
            'group_id' => $groupId
        ], 'User removed from group successfully');
    } else {
        ApiResponse::error('Failed to remove user from group', 500);
    }
}

function getGroupFolderAccess($db, $id) {
    $groupId = (int)$id;

    // Check if group exists
    $checkQuery = "SELECT id, name FROM user_groups WHERE id = ?";
    $group = $db->query($checkQuery, 'SELECT', 'ROW', [$groupId]);

    if (!$group) {
        ApiResponse::error('User group not found', 404);
    }

    // Get document types access
    $docTypesQuery = "SELECT cfar.folder_sub_id, dt.name
                      FROM config_folder_access_rights cfar
                      JOIN document_types dt ON dt.id = cfar.folder_sub_id
                      WHERE cfar.type = 'DOCUMENT TYPE' AND cfar.user_group = ?";
    $documentTypes = $db->query($docTypesQuery, 'SELECT', 'ALL', [$groupId]);

    // Get folders access
    $foldersQuery = "SELECT cfar.folder_sub_id, adf.name
                     FROM config_folder_access_rights cfar
                     JOIN archive_document_folders adf ON adf.id = cfar.folder_sub_id
                     WHERE cfar.type = 'FOLDER' AND cfar.user_group = ?";
    $folders = $db->query($foldersQuery, 'SELECT', 'ALL', [$groupId]);

    // Get sub-folders access
    $subFoldersQuery = "SELECT cfar.folder_sub_id, asf.name, adf.name as folder_name
                        FROM config_folder_access_rights cfar
                        JOIN archive_document_sub_folders asf ON asf.id = cfar.folder_sub_id
                        JOIN archive_document_folders adf ON adf.id = asf.archive_document_folder_id
                        WHERE cfar.type = 'SUB FOLDER' AND cfar.user_group = ?";
    $subFolders = $db->query($subFoldersQuery, 'SELECT', 'ALL', [$groupId]);

    ApiResponse::success([
        'group_name' => $group['name'],
        'document_types' => $documentTypes ?: [],
        'folders' => $folders ?: [],
        'sub_folders' => $subFolders ?: []
    ], 'Group folder access retrieved successfully');
}

function updateGroupFolderAccess($db, $id, $input) {
    $groupId = (int)$id;

    // Check if group exists
    $checkQuery = "SELECT id FROM user_groups WHERE id = ?";
    $exists = $db->query($checkQuery, 'SELECT', 'ROW', [$groupId]);

    if (!$exists) {
        ApiResponse::error('User group not found', 404);
    }

    // Delete existing access rights
    $deleteQuery = "DELETE FROM config_folder_access_rights WHERE user_group = ?";
    $db->query($deleteQuery, 'DELETE', 'ROW', [$groupId]);

    // Insert new access rights
    $inserted = 0;

    if (isset($input['document_types']) && is_array($input['document_types'])) {
        foreach ($input['document_types'] as $docTypeId) {
            $insertQuery = "INSERT INTO config_folder_access_rights
                           (user_group, folder_sub_id, type)
                           VALUES (?, ?, 'DOCUMENT TYPE')";
            $db->query($insertQuery, 'INSERT', 'ROW', [$groupId, (int)$docTypeId]);
            $inserted++;
        }
    }

    if (isset($input['folders']) && is_array($input['folders'])) {
        foreach ($input['folders'] as $folderId) {
            $insertQuery = "INSERT INTO config_folder_access_rights
                           (user_group, folder_sub_id, type)
                           VALUES (?, ?, 'FOLDER')";
            $db->query($insertQuery, 'INSERT', 'ROW', [$groupId, (int)$folderId]);
            $inserted++;
        }
    }

    if (isset($input['sub_folders']) && is_array($input['sub_folders'])) {
        foreach ($input['sub_folders'] as $subFolderId) {
            $insertQuery = "INSERT INTO config_folder_access_rights
                           (user_group, folder_sub_id, type)
                           VALUES (?, ?, 'SUB FOLDER')";
            $db->query($insertQuery, 'INSERT', 'ROW', [$groupId, (int)$subFolderId]);
            $inserted++;
        }
    }

    ApiResponse::success([
        'group_id' => $groupId,
        'access_rights_updated' => $inserted
    ], 'Group folder access updated successfully');
}

function getGroupPermissions($db, $id) {
    $groupId = (int)$id;

    // Check if group exists
    $checkQuery = "SELECT id, name FROM user_groups WHERE id = ?";
    $group = $db->query($checkQuery, 'SELECT', 'ROW', [$groupId]);

    if (!$group) {
        ApiResponse::error('User group not found', 404);
    }

    // Get all parent menus
    $menusQuery = "SELECT m.id, m.name, m.parent_menu,
                   (SELECT COUNT(*) FROM config_access_rights WHERE user_group = ? AND menu = m.id) as has_access
                   FROM menu m
                   WHERE m.status = 'ACTIVE'
                   ORDER BY m.list_order, m.name";
    $menus = $db->query($menusQuery, 'SELECT', 'ALL', [$groupId]);

    // Get all roles with current access status
    $rolesQuery = "SELECT r.id, r.keyword, r.description,
                   (SELECT COUNT(*) FROM config_role_access
                    WHERE access_type = 'GROUP' AND user_id = ? AND role_id = r.id) as has_access
                   FROM config_roles r
                   WHERE r.id > 1
                   ORDER BY r.id";
    $roles = $db->query($rolesQuery, 'SELECT', 'ALL', [$groupId]);

    ApiResponse::success([
        'group_name' => $group['name'],
        'menus' => $menus ?: [],
        'roles' => $roles ?: []
    ], 'Group permissions retrieved successfully');
}

function updateGroupPermissions($db, $id, $input) {
    $groupId = (int)$id;

    // Check if group exists
    $checkQuery = "SELECT id FROM user_groups WHERE id = ?";
    $exists = $db->query($checkQuery, 'SELECT', 'ROW', [$groupId]);

    if (!$exists) {
        ApiResponse::error('User group not found', 404);
    }

    // Delete existing access rights
    $deleteMenuQuery = "DELETE FROM config_access_rights WHERE user_group = ?";
    $db->query($deleteMenuQuery, 'DELETE', 'ROW', [$groupId]);

    $deleteRoleQuery = "DELETE FROM config_role_access WHERE access_type = 'GROUP' AND user_id = ?";
    $db->query($deleteRoleQuery, 'DELETE', 'ROW', [$groupId]);

    $insertedMenus = 0;
    $insertedRoles = 0;

    // Insert new menu access rights
    if (isset($input['menus']) && is_array($input['menus'])) {
        foreach ($input['menus'] as $menuId) {
            $insertQuery = "INSERT INTO config_access_rights (user_group, menu) VALUES (?, ?)";
            $db->query($insertQuery, 'INSERT', 'ROW', [$groupId, (int)$menuId]);
            $insertedMenus++;
        }
    }

    // Insert new role access rights
    if (isset($input['roles']) && is_array($input['roles'])) {
        foreach ($input['roles'] as $roleId) {
            $insertQuery = "INSERT INTO config_role_access (access_type, user_id, role_id, access)
                           VALUES ('GROUP', ?, ?, 1)";
            $db->query($insertQuery, 'INSERT', 'ROW', [$groupId, (int)$roleId]);
            $insertedRoles++;
        }
    }

    ApiResponse::success([
        'group_id' => $groupId,
        'menus_updated' => $insertedMenus,
        'roles_updated' => $insertedRoles
    ], 'Group permissions updated successfully');
}

function checkAdminPermission($db, $user) {
    // Check if user belongs to admin group (group id = 1)
    $userQuery = "SELECT ugr.user_group
                  FROM user_group_relation ugr
                  WHERE ugr.user = ? AND ugr.user_group = 1";
    $result = $db->query($userQuery, 'SELECT', 'ROW', [$user['user_id']]);

    // User is admin if they belong to group 1
    return $result !== null && $result !== false;
}
