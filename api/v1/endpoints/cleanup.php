<?php
/**
 * Cleanup Endpoints
 *
 * Handles unregistered files and orphaned database records cleanup
 */

function handleCleanup($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    switch ($method) {
        case 'GET':
            if ($action === 'unregistered-files') {
                analyzeUnregisteredFiles($db, $user);
            } elseif ($action === 'orphaned-records') {
                analyzeOrphanedRecords($db, $user);
            } else {
                ApiResponse::error('Invalid action', 400);
            }
            break;

        case 'DELETE':
            if ($action === 'file') {
                deleteUnregisteredFile($db, $user, $input);
            } elseif ($action === 'record' && $id) {
                deleteOrphanedRecord($db, $user, $id);
            } else {
                ApiResponse::error('Invalid action or missing ID', 400);
            }
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function analyzeUnregisteredFiles($db, $user) {
    // Check permission
    if (!checkCleanupPermission($db, $user, 'FILE_DELETION')) {
        ApiResponse::error('Insufficient permissions for file cleanup', 403);
    }

    $archivesPath = dirname(dirname(dirname(dirname(__DIR__)))) . '/allfiles/pf-archives/';

    try {
        // Get files from folder
        $filesInFolder = [];
        if (is_dir($archivesPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($archivesPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $filesInFolder[] = str_replace('\\', '/', $file->getPathname());
                }
            }
        } else {
            ApiResponse::error('Archives directory not found', 404);
        }

        // Get files from database
        $query = "SELECT path FROM archives WHERE path IS NOT NULL AND path != ''";
        $result = $db->query($query, 'SELECT');

        $filesInDatabase = [];
        if ($result) {
            foreach ($result as $row) {
                if (!empty($row['path'])) {
                    $filename = basename($row['path']);
                    $fullPath = $archivesPath . $filename;
                    $filesInDatabase[] = $fullPath;
                }
            }
        }

        // Find unregistered files
        $filesToDelete = array_diff($filesInFolder, $filesInDatabase);

        // Get file details
        $fileDetails = [];
        foreach ($filesToDelete as $file) {
            if (file_exists($file)) {
                $fileDetails[] = [
                    'name' => basename($file),
                    'size' => filesize($file),
                    'modified' => date("Y-m-d H:i:s", filemtime($file)),
                    'mime' => mime_content_type($file),
                    'path' => $file
                ];
            }
        }

        ApiResponse::success([
            'total_files_in_folder' => count($filesInFolder),
            'total_files_in_database' => count($filesInDatabase),
            'unregistered_files' => count($filesToDelete),
            'file_details' => $fileDetails
        ], 'Unregistered files analysis completed');

    } catch (Exception $e) {
        ApiResponse::error('Analysis failed: ' . $e->getMessage(), 500);
    }
}

function deleteUnregisteredFile($db, $user, $input) {
    // Check permission
    if (!checkCleanupPermission($db, $user, 'FILE_DELETION')) {
        ApiResponse::error('Insufficient permissions for file deletion', 403);
    }

    if (!isset($input['file_path'])) {
        ApiResponse::error('File path is required', 400);
    }

    $filePath = $input['file_path'];
    $archivesPath = dirname(dirname(dirname(dirname(__DIR__)))) . '/allfiles/pf-archives/';

    try {
        if (!file_exists($filePath)) {
            ApiResponse::error('File not found', 404);
        }

        if (!is_writable($filePath)) {
            ApiResponse::error('File is not writable', 403);
        }

        // Security check: ensure file is within archives directory
        $normalizedPath = str_replace('\\', '/', $filePath);
        $normalizedArchivesPath = str_replace('\\', '/', $archivesPath);

        if (strpos($normalizedPath, $normalizedArchivesPath) !== 0) {
            ApiResponse::error('File is outside of archives directory', 403);
        }

        if (unlink($filePath)) {
            ApiResponse::success([
                'deleted' => true,
                'file_path' => $filePath
            ], 'File deleted successfully');
        } else {
            ApiResponse::error('Failed to delete file', 500);
        }

    } catch (Exception $e) {
        ApiResponse::error('Deletion failed: ' . $e->getMessage(), 500);
    }
}

function analyzeOrphanedRecords($db, $user) {
    // Check permission
    if (!checkCleanupPermission($db, $user, 'RECORD_DELETION')) {
        ApiResponse::error('Insufficient permissions for record cleanup', 403);
    }

    $archivesPath = dirname(dirname(dirname(dirname(__DIR__)))) . '/allfiles/pf-archives/';

    try {
        // Get files from folder
        $filesInFolder = [];
        if (is_dir($archivesPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($archivesPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $filesInFolder[] = basename($file->getPathname());
                }
            }
        } else {
            ApiResponse::error('Archives directory not found', 404);
        }

        // Get records from database
        $query = "SELECT id, path, name FROM archives WHERE path IS NOT NULL AND path != ''";
        $records = $db->query($query, 'SELECT');

        $orphanedRecords = [];
        $totalRecords = 0;

        if ($records) {
            foreach ($records as $record) {
                $totalRecords++;
                if (!empty($record['path'])) {
                    $filename = basename($record['path']);
                    if (!in_array($filename, $filesInFolder)) {
                        $orphanedRecords[] = [
                            'id' => $record['id'],
                            'name' => $record['name'],
                            'filename' => $filename,
                            'full_path' => $record['path']
                        ];
                    }
                }
            }
        }

        ApiResponse::success([
            'total_files_in_folder' => count($filesInFolder),
            'total_records_in_database' => $totalRecords,
            'orphaned_records' => count($orphanedRecords),
            'record_details' => $orphanedRecords
        ], 'Orphaned records analysis completed');

    } catch (Exception $e) {
        ApiResponse::error('Analysis failed: ' . $e->getMessage(), 500);
    }
}

function deleteOrphanedRecord($db, $user, $id) {
    // Check permission
    if (!checkCleanupPermission($db, $user, 'RECORD_DELETION')) {
        ApiResponse::error('Insufficient permissions for record deletion', 403);
    }

    $recordId = (int)$id;

    // Check if record exists
    $checkQuery = "SELECT id FROM archives WHERE id = ?";
    $exists = $db->query($checkQuery, 'SELECT', 'ROW', [$recordId]);

    if (!$exists) {
        ApiResponse::error('Record not found', 404);
    }

    // Delete record
    $deleteQuery = "DELETE FROM archives WHERE id = ?";
    $result = $db->query($deleteQuery, 'DELETE', 'ROW', [$recordId]);

    if ($result) {
        ApiResponse::success([
            'deleted' => true,
            'record_id' => $recordId
        ], 'Record deleted successfully');
    } else {
        ApiResponse::error('Failed to delete record', 500);
    }
}

function checkCleanupPermission($db, $user, $permissionName) {
    $query = "SELECT p.permission_name
              FROM user_permissions up
              JOIN permissions p ON p.id = up.permission_id
              WHERE up.user_id = ? AND p.permission_name = ?";

    $result = $db->query($query, 'SELECT', 'ROW', [$user['user_id'], $permissionName]);

    return !empty($result);
}
