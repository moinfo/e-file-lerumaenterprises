<?php
/**
 * Role Permission Checker
 *
 * Helper functions to check user role permissions
 */

/**
 * Check if user has a specific role permission
 *
 * @param DB $db Database connection
 * @param array $user User data from token
 * @param string $roleKeyword Role keyword (e.g., 'ADD', 'EDIT', 'DELETE', 'VIEW')
 * @return bool True if user has the role, false otherwise
 */
function hasRole($db, $user, $roleKeyword) {
    // Get user ID
    $userId = $user['user_id'] ?? $user['id'];

    // Get user's groups
    $groupsQuery = "SELECT user_group FROM user_group_relation WHERE user = ?";
    $userGroups = $db->query($groupsQuery, 'SELECT', 'ALL', [$userId]);

    if (empty($userGroups)) {
        return false;
    }

    // Extract group IDs
    $groupIds = array_column($userGroups, 'user_group');
    $groupIdsStr = implode(',', array_map('intval', $groupIds));

    // Check if user has this role through any of their groups
    $roleQuery = "SELECT r.id
                  FROM config_role_access cra
                  JOIN config_roles r ON r.id = cra.role_id
                  WHERE cra.access_type = 'GROUP'
                  AND cra.user_id IN ($groupIdsStr)
                  AND cra.access = 1
                  AND r.keyword = ?";

    $role = $db->query($roleQuery, 'SELECT', 'ROW', [$roleKeyword]);

    return $role !== null && $role !== false;
}

/**
 * Check if user has any of the specified roles
 *
 * @param DB $db Database connection
 * @param array $user User data from token
 * @param array $roleKeywords Array of role keywords
 * @return bool True if user has at least one of the roles
 */
function hasAnyRole($db, $user, $roleKeywords) {
    foreach ($roleKeywords as $keyword) {
        if (hasRole($db, $user, $keyword)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user has all of the specified roles
 *
 * @param DB $db Database connection
 * @param array $user User data from token
 * @param array $roleKeywords Array of role keywords
 * @return bool True if user has all of the roles
 */
function hasAllRoles($db, $user, $roleKeywords) {
    foreach ($roleKeywords as $keyword) {
        if (!hasRole($db, $user, $keyword)) {
            return false;
        }
    }
    return true;
}

/**
 * Require a specific role or return 403 error
 *
 * @param DB $db Database connection
 * @param array $user User data from token
 * @param string $roleKeyword Role keyword required
 * @param string $message Optional error message
 */
function requireRole($db, $user, $roleKeyword, $message = null) {
    if (!hasRole($db, $user, $roleKeyword)) {
        $errorMessage = $message ?? "You don't have permission to perform this action. Required role: $roleKeyword";
        ApiResponse::error($errorMessage, 403);
    }
}

/**
 * Get all roles for a user
 *
 * @param DB $db Database connection
 * @param array $user User data from token
 * @return array Array of role keywords
 */
function getUserRoles($db, $user) {
    // Get user ID
    $userId = $user['user_id'] ?? $user['id'];

    // Get user's groups
    $groupsQuery = "SELECT user_group FROM user_group_relation WHERE user = ?";
    $userGroups = $db->query($groupsQuery, 'SELECT', 'ALL', [$userId]);

    if (empty($userGroups)) {
        return [];
    }

    // Extract group IDs
    $groupIds = array_column($userGroups, 'user_group');
    $groupIdsStr = implode(',', array_map('intval', $groupIds));

    // Get all roles
    $rolesQuery = "SELECT DISTINCT r.keyword
                   FROM config_role_access cra
                   JOIN config_roles r ON r.id = cra.role_id
                   WHERE cra.access_type = 'GROUP'
                   AND cra.user_id IN ($groupIdsStr)
                   AND cra.access = 1
                   ORDER BY r.keyword";

    $roles = $db->query($rolesQuery, 'SELECT', 'ALL');

    if (empty($roles)) {
        return [];
    }

    return array_column($roles, 'keyword');
}
