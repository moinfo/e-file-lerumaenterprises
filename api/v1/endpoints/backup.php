<?php
/**
 * Backup Management Endpoints
 *
 * Handles database and file backup operations
 */

function handleBackup($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    // Check admin permissions
    if (!checkAdminPermission($db, $user)) {
        ApiResponse::error('Insufficient permissions. Admin access required.', 403);
    }

    switch ($method) {
        case 'GET':
            if ($action === 'history') {
                getBackupHistory($db);
            } elseif ($action === 'status' && $id) {
                getBackupStatus($db, $id);
            } elseif ($action === 'download' && $id) {
                downloadBackup($db, $id);
            } else {
                getBackupHistory($db);
            }
            break;

        case 'POST':
            if ($action === 'initiate') {
                initiateBackup($db, $input);
            } else {
                ApiResponse::error('Invalid action', 400);
            }
            break;

        case 'DELETE':
            if (!$id) {
                ApiResponse::error('Backup ID required', 400);
            }
            deleteBackup($db, $id);
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getBackupHistory($db) {
    $limit = $_GET['limit'] ?? 50;
    $offset = $_GET['offset'] ?? 0;

    $query = "SELECT * FROM backup_history
              ORDER BY created_at DESC
              LIMIT ? OFFSET ?";

    $backups = $db->query($query, 'SELECT', 'ALL', [(int)$limit, (int)$offset]);

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM backup_history";
    $totalResult = $db->query($countQuery, 'SELECT', 'ROW');

    ApiResponse::success([
        'backups' => $backups ?: [],
        'total' => (int)($totalResult['total'] ?? 0),
        'limit' => (int)$limit,
        'offset' => (int)$offset
    ], 'Backup history retrieved successfully');
}

function getBackupStatus($db, $id) {
    $query = "SELECT * FROM backup_history WHERE id = ?";
    $backup = $db->query($query, 'SELECT', 'ROW', [$id]);

    if (!$backup) {
        ApiResponse::error('Backup not found', 404);
    }

    ApiResponse::success([
        'backup' => $backup
    ], 'Backup status retrieved successfully');
}

function initiateBackup($db, $input) {
    // Validate input
    if (!isset($input['backup_type']) || !in_array($input['backup_type'], ['database', 'files'])) {
        ApiResponse::error('Valid backup_type required (database or files)', 400);
    }

    $backupType = $input['backup_type'];

    // Create backup_history table if not exists
    createBackupHistoryTable($db);

    try {
        // Create unique backup name
        $backupName = date('Y-m-d_H-i-s') . '_' . $backupType . '_' . uniqid();
        $fileName = $backupName . ($backupType == 'database' ? '.sql' : '.zip');

        // Insert backup record
        $data = [
            'backup_type' => $backupType,
            'file_name' => $fileName,
            'status' => 'processing',
            'notes' => 'Backup initiated',
            'progress' => 0
        ];

        $insertQuery = "INSERT INTO backup_history
                       (backup_type, file_name, status, notes, progress, created_at)
                       VALUES (?, ?, ?, ?, ?, NOW())";

        $db->query($insertQuery, 'INSERT', 'ROW',
                  [$data['backup_type'], $data['file_name'], $data['status'],
                   $data['notes'], $data['progress']]);

        $backupId = $db->getLastInsertId();

        // In a real implementation, you would trigger the actual backup process here
        // For API purposes, we'll return the initiated status

        ApiResponse::success([
            'backup_id' => $backupId,
            'status' => 'initiated',
            'message' => 'Backup process started successfully'
        ], 'Backup initiated', 201);

    } catch (Exception $e) {
        ApiResponse::error('Failed to initiate backup: ' . $e->getMessage(), 500);
    }
}

function downloadBackup($db, $id) {
    $query = "SELECT * FROM backup_history WHERE id = ?";
    $backup = $db->query($query, 'SELECT', 'ROW', [$id]);

    if (!$backup) {
        ApiResponse::error('Backup not found', 404);
    }

    if ($backup['status'] !== 'completed') {
        ApiResponse::error('Backup is not completed yet', 400);
    }

    $backupDir = dirname(dirname(dirname(__DIR__))) . '/backups/';
    $filePath = $backupDir . $backup['file_name'];

    if (!file_exists($filePath)) {
        ApiResponse::error('Backup file not found on server', 404);
    }

    // Set headers for file download
    $ext = pathinfo($backup['file_name'], PATHINFO_EXTENSION);
    $contentType = $ext === 'sql' ? 'application/sql' : 'application/zip';

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $backup['file_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache');

    readfile($filePath);
    exit();
}

function deleteBackup($db, $id) {
    $query = "SELECT * FROM backup_history WHERE id = ?";
    $backup = $db->query($query, 'SELECT', 'ROW', [$id]);

    if (!$backup) {
        ApiResponse::error('Backup not found', 404);
    }

    // Delete physical file if exists
    $backupDir = dirname(dirname(dirname(__DIR__))) . '/backups/';
    $filePath = $backupDir . $backup['file_name'];

    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Delete database record
    $deleteQuery = "DELETE FROM backup_history WHERE id = ?";
    $db->query($deleteQuery, 'DELETE', 'ROW', [$id]);

    ApiResponse::success([], 'Backup deleted successfully');
}

function createBackupHistoryTable($db) {
    $checkTable = "SHOW TABLES LIKE 'backup_history'";
    $exists = $db->query($checkTable, 'SELECT');

    if (empty($exists)) {
        $createTable = "CREATE TABLE backup_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            backup_type VARCHAR(50) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_size BIGINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(50) DEFAULT 'processing',
            notes TEXT,
            progress INT DEFAULT 0,
            INDEX idx_backup_type (backup_type),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $db->query($createTable, 'CREATE');
    }
}

function checkAdminPermission($db, $user) {
    $userQuery = "SELECT user_group FROM users WHERE id = ?";
    $result = $db->query($userQuery, 'SELECT', 'ROW', [$user['user_id']]);

    // Assuming user_group 1 is admin
    return $result && $result['user_group'] == 1;
}
