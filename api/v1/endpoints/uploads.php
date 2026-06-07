<?php
/**
 * Uploads and Synchronization Endpoints
 *
 * Handles file uploads, synchronization, and incoming system uploads
 */

function handleUploads($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    // Support both path-based and query parameter actions
    // If action is in URL path (e.g., /uploads/settings), it comes in $id
    // If action is in query param (e.g., /uploads?action=settings), use that
    if (!$action && $id) {
        $action = $id;
    }
    if (!$action && isset($_GET['action'])) {
        $action = $_GET['action'];
    }

    switch ($method) {
        case 'GET':
            if ($action === 'incoming') {
                getIncomingUploads($db, $user);
            } elseif ($action === 'settings') {
                getUploadSettings($db);
            } else {
                ApiResponse::error('Invalid action', 400);
            }
            break;

        case 'POST':
            if ($action === 'sync') {
                processSynchronization($db, $user, $input);
            } elseif ($action === 'upload') {
                handleFileUpload($db, $user);
            } elseif ($action === 'download-batch') {
                downloadFilteredFiles($db, $user, $input);
            } else {
                ApiResponse::error('Invalid action', 400);
            }
            break;

        case 'PUT':
            if ($action === 'settings') {
                updateUploadSettings($db, $input);
            } else {
                ApiResponse::error('Invalid action', 400);
            }
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getIncomingUploads($db, $user) {
    $system = $_GET['system'] ?? null;
    $category = $_GET['category'] ?? null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    $limit = (int)($_GET['limit'] ?? 100);
    $offset = (int)($_GET['offset'] ?? 0);

    // Build query with filters
    $where = [];
    $params = [];

    if ($system) {
        $where[] = "system = ?";
        $params[] = $system;
    }

    if ($category) {
        $where[] = "category = ?";
        $params[] = $category;
    }

    if ($startDate && $endDate) {
        $where[] = "uploaded_time >= ?";
        $where[] = "uploaded_time <= ?";
        $params[] = $startDate;
        $params[] = $endDate . ' 23:59:59';
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Get uploads
    $query = "SELECT * FROM uploads
              {$whereClause}
              ORDER BY uploaded_time DESC
              LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;

    $uploads = $db->query($query, 'SELECT', 'ALL', $params);

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM uploads {$whereClause}";
    $countParams = array_slice($params, 0, count($params) - 2); // Remove limit and offset
    $totalResult = $db->query($countQuery, 'SELECT', 'ROW', $countParams);

    // Get available systems and categories for filtering
    $systems = $db->query("SELECT DISTINCT system FROM uploads ORDER BY system", 'SELECT');
    $categories = $db->query("SELECT DISTINCT category FROM uploads ORDER BY category", 'SELECT');

    ApiResponse::success([
        'uploads' => $uploads ?: [],
        'total' => (int)($totalResult['total'] ?? 0),
        'limit' => $limit,
        'offset' => $offset,
        'filters' => [
            'systems' => $systems ?: [],
            'categories' => $categories ?: []
        ]
    ], 'Incoming uploads retrieved successfully');
}

function handleFileUpload($db, $user) {
    if (!isset($_FILES['myfile']) || $_FILES['myfile']['error'] !== UPLOAD_ERR_OK) {
        ApiResponse::error('No file uploaded or upload error occurred', 400);
    }

    $file = $_FILES['myfile'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Get upload settings
    $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];
    $maxFileSize = 50 * 1024 * 1024; // 50MB

    if (!in_array($fileExt, $allowedTypes)) {
        ApiResponse::error('Invalid file type. Allowed types: ' . implode(', ', $allowedTypes), 400);
    }

    if ($fileSize > $maxFileSize) {
        ApiResponse::error('File too large. Maximum size: 50MB', 400);
    }

    // Upload directory — use FILES_PATH constant for reliable absolute path
    $uploadDir = rtrim(FILES_PATH, '/') . '/pf-archives/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename with timestamp
    $timestamp = date('YmdHis');
    $uniqueFileName = $timestamp . '_' . uniqid() . '.' . $fileExt;
    $uploadPath = $uploadDir . $uniqueFileName;

    // Move uploaded file
    if (!move_uploaded_file($fileTmpName, $uploadPath)) {
        ApiResponse::error('Failed to save file', 500);
    }

    // Get user info
    $userQuery = "SELECT username FROM users WHERE id = ?";
    $userInfo = $db->query($userQuery, 'SELECT', 'ROW', [$user['user_id']]);

    // Calculate file hash
    $hash = md5_file($uploadPath);
    $mime = mime_content_type($uploadPath);

    // Insert upload record
    $insertUploadQuery = "INSERT INTO uploads
                          (path, uploaded_time, uploaded_user, system, category)
                          VALUES (?, NOW(), ?, ?, ?)";

    $system = $_POST['system'] ?? 'E-File System';
    $category = $_POST['category'] ?? 'General';

    $db->query($insertUploadQuery, 'INSERT', 'ROW', [
        $uploadPath,
        $userInfo['username'] ?? 'Unknown',
        $system,
        $category
    ]);

    $uploadId = $db->getLastInsertId();

    // Also insert into archives table so file is immediately viewable
    // Store absolute path — resolved at upload time, not CWD-relative
    $relativePath = $uploadPath;

    $insertArchiveQuery = "INSERT INTO archives
                          (name, path, hash, mime, size, year, created_at, completed, description)
                          VALUES (?, ?, ?, ?, ?, ?, NOW(), 0, ?)";

    $year = date('Y'); // Current year
    $description = "Uploaded via mobile app by " . ($userInfo['username'] ?? 'Unknown');

    $db->query($insertArchiveQuery, 'INSERT', 'ROW', [
        $fileName, // Original filename
        $relativePath,
        $hash,
        $mime,
        $fileSize,
        $year,
        $description
    ]);

    $archiveId = $db->getLastInsertId();

    ApiResponse::success([
        'upload_id' => $uploadId,
        'archive_id' => $archiveId,
        'file_name' => $uniqueFileName,
        'original_name' => $fileName,
        'file_size' => $fileSize,
        'file_path' => $relativePath
    ], 'File uploaded and archived successfully', 201);
}

function processSynchronization($db, $user, $input) {
    // Check admin permissions
    if (!checkSyncPermission($db, $user)) {
        ApiResponse::error('Insufficient permissions. Synchronization access required.', 403);
    }

    // Validate password against the configured shared secret (constant-time).
    // If the secret is not configured, deny rather than allow an empty match.
    if (!defined('SYNC_PASSWORD') || SYNC_PASSWORD === '') {
        ApiResponse::error('Synchronization is not configured', 503);
    }
    if (!isset($input['password']) || !hash_equals(SYNC_PASSWORD, (string) $input['password'])) {
        ApiResponse::error('Invalid password', 401);
    }

    // Use FILES_PATH constant for reliable absolute path resolution
    $filePath = rtrim(FILES_PATH, '/') . '/pf-archives/';

    // Legacy fallback: older deployments may have stored files relative to the CWD
    // which resolved to inside the site root rather than FILES_PATH.
    if (!is_dir($filePath)) {
        $legacy = dirname(dirname(dirname(dirname(__DIR__)))) . '/allfiles/pf-archives/';
        if (is_dir($legacy)) {
            $filePath = $legacy;
        }
    }

    if (!is_dir($filePath)) {
        ApiResponse::error('Archive directory not found: ' . $filePath, 404);
    }

    // Get existing file hashes
    $hashQuery = "SELECT hash FROM archives";
    $existingHashes = $db->query($hashQuery, 'SELECT');
    $hashes = array_column($existingHashes ?: [], 'hash');

    // Scan directory for new files
    $ignored = ['.', '..', '.DS_Store'];
    $files = [];

    foreach (scandir($filePath) as $file) {
        if (in_array($file, $ignored)) continue;
        $files[$file] = filemtime($filePath . $file);
    }

    // Sort by modification time (newest first)
    arsort($files);
    $files = array_keys($files);

    // Process files (limit to 50 per batch)
    $processLimit = $input['limit'] ?? 50;
    $processed = 0;
    $added = 0;
    $skipped = 0;
    $errors = [];

    foreach (array_slice($files, 0, $processLimit) as $fileName) {
        $fullPath = $filePath . $fileName;

        try {
            $hash = md5_file($fullPath);
            $mime = mime_content_type($fullPath);
            $size = filesize($fullPath);

            // Extract year from filename (RPF format)
            $prefix = 'RPF';
            $year = substr($fileName, 0, strlen($prefix)) === $prefix
                    ? substr($fileName, strlen($prefix), 4)
                    : null;

            // Check if file already exists
            if (in_array($hash, $hashes)) {
                $skipped++;
                continue;
            }

            // Insert into database
            $insertQuery = "INSERT INTO archives
                           (name, path, hash, mime, size, year, created_at, completed)
                           VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)";

            // Compute absolute path for reliable future resolution
            $absPath = realpath($fullPath) ?: $fullPath;

            $db->query($insertQuery, 'INSERT', 'ROW', [
                $fileName,
                $absPath,
                $hash,
                $mime,
                $size,
                $year
            ]);

            $added++;

        } catch (Exception $e) {
            $errors[] = [
                'file' => $fileName,
                'error' => $e->getMessage()
            ];
        }

        $processed++;
    }

    ApiResponse::success([
        'processed' => $processed,
        'added' => $added,
        'skipped' => $skipped,
        'errors' => $errors,
        'total_files' => count($files)
    ], 'Synchronization completed successfully');
}

function downloadFilteredFiles($db, $user, $input) {
    $system = $input['system'] ?? null;
    $category = $input['category'] ?? null;
    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;

    // Build query
    $where = [];
    $params = [];

    if ($system) {
        $where[] = "system = ?";
        $params[] = $system;
    }

    if ($category) {
        $where[] = "category = ?";
        $params[] = $category;
    }

    if ($startDate && $endDate) {
        $where[] = "uploaded_time >= ?";
        $where[] = "uploaded_time <= ?";
        $params[] = $startDate;
        $params[] = $endDate . ' 23:59:59';
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $query = "SELECT * FROM uploads {$whereClause} ORDER BY uploaded_time DESC";
    $uploads = $db->query($query, 'SELECT', 'ALL', $params);

    if (empty($uploads)) {
        ApiResponse::error('No files found matching the criteria', 404);
    }

    // Create temporary zip file
    $tempDir = sys_get_temp_dir() . '/';
    $zipName = 'documents_' . date('Y-m-d_His') . '.zip';
    $zipPath = $tempDir . $zipName;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        ApiResponse::error('Cannot create zip file', 500);
    }

    $fileCount = 0;
    foreach ($uploads as $upload) {
        if (file_exists($upload['path'])) {
            $filename = basename($upload['path']);
            $zip->addFile($upload['path'], $filename);
            $fileCount++;
        }
    }

    $zip->close();

    if ($fileCount === 0) {
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
        ApiResponse::error('No valid files to download', 404);
    }

    // Return zip file info (actual download handled by separate endpoint)
    ApiResponse::success([
        'zip_path' => $zipPath,
        'zip_name' => $zipName,
        'file_count' => $fileCount,
        'download_url' => '/api/v1/uploads/download?file=' . urlencode($zipName)
    ], 'Batch download prepared successfully');
}

function getUploadSettings($db) {
    // Return current upload settings
    $settings = [
        'allowed_types' => ['pdf', 'jpg', 'jpeg', 'png', 'gif'],
        'max_file_size' => 50 * 1024 * 1024, // 50MB in bytes
        'max_file_count' => 50,
        'upload_directory' => 'allfiles/uploads/'
    ];

    ApiResponse::success([
        'settings' => $settings
    ], 'Upload settings retrieved successfully');
}

function updateUploadSettings($db, $input) {
    // This would update upload settings in a settings table
    // For now, return not implemented
    ApiResponse::error('Upload settings update not yet implemented', 501);
}

function checkSyncPermission($db, $user) {
    $query = "SELECT p.permission_name
              FROM user_permissions up
              JOIN permissions p ON p.id = up.permission_id
              WHERE up.user_id = ? AND p.permission_name = 'PROCESS_UPLOADED_SYNCHRONIZATION'";

    $result = $db->query($query, 'SELECT', 'ROW', [$user['user_id']]);

    return !empty($result);
}
