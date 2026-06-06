<?php
/**
 * Files/Archives Endpoints
 *
 * Handles file operations (CRUD, upload, download)
 */

function handleFiles($method, $id, $action, $input) {
    // Validate authentication
    $user = ApiAuth::validateToken();
    $db = new DB();

    switch ($method) {
        case 'GET':
            if ($action === 'download' && $id) {
                downloadFile($db, $id, $user);
            } elseif ($action === 'view' && $id) {
                viewFile($db, $id, $user);
            } elseif ($id) {
                getSingleFile($db, $id, $user);
            } else {
                getAllFiles($db, $user);
            }
            break;

        case 'POST':
            if ($action === 'upload') {
                uploadFile($db, $user);
            } else {
                createFileRecord($db, $input, $user);
            }
            break;

        case 'PUT':
            if (!$id) {
                ApiResponse::error('File ID required', 400);
            }
            updateFile($db, $id, $input, $user);
            break;

        case 'DELETE':
            if (!$id) {
                ApiResponse::error('File ID required', 400);
            }
            deleteFile($db, $id, $user);
            break;

        default:
            ApiResponse::error('Method not allowed', 405);
    }
}

function getAllFiles($db, $user) {
    // Get query parameters for filtering
    $subFolderId = $_GET['sub_folder_id'] ?? null;
    $documentType = $_GET['document_type'] ?? null;
    $completed = $_GET['completed'] ?? null;
    $limit = $_GET['limit'] ?? 100;
    $offset = $_GET['offset'] ?? 0;

    // Build query
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
              WHERE 1=1";

    $params = [];

    if ($subFolderId) {
        $query .= " AND a.sub_folder_id = ?";
        $params[] = $subFolderId;
    }

    if ($documentType) {
        $query .= " AND a.document_type = ?";
        $params[] = $documentType;
    }

    if ($completed !== null) {
        $query .= " AND a.completed = ?";
        $params[] = $completed;
    }

    // Get total count
    $countQuery = str_replace('SELECT a.*,', 'SELECT COUNT(*) as total,', $query);
    $countResult = $db->query($countQuery, 'SELECT', 'ROW', $params);
    $total = $countResult['total'] ?? 0;

    // Add sorting and pagination
    $query .= " ORDER BY a.id DESC LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;

    $files = $db->query($query, 'SELECT', 'ALL', $params);

    ApiResponse::success([
        'files' => $files ?: [],
        'total' => $total,
        'limit' => (int)$limit,
        'offset' => (int)$offset
    ], 'Files retrieved successfully');
}

function getSingleFile($db, $id, $user) {
    $query = "SELECT
                a.*,
                adsf.name as sub_folder_name,
                adsf.archive_document_folder_id as folder_id,
                adf.name as folder_name,
                dt.name as document_type_name,
                u.username as editor_name
              FROM archives a
              LEFT JOIN archive_document_sub_folders adsf ON adsf.id = a.sub_folder_id
              LEFT JOIN archive_document_folders adf ON adf.id = adsf.archive_document_folder_id
              LEFT JOIN document_types dt ON dt.id = a.document_type
              LEFT JOIN users u ON u.id = a.edited_by
              WHERE a.id = ?";

    $file = $db->query($query, 'SELECT', 'ROW', [$id]);

    if (!$file) {
        ApiResponse::error('File not found', 404);
    }

    // Add full file URL
    $file['file_url'] = BASE_URL . $file['path'];

    ApiResponse::success(['file' => $file], 'File retrieved successfully');
}

function uploadFile($db, $user) {
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        ApiResponse::error('No file uploaded or upload error occurred', 400);
    }

    // Validate required parameters
    $subFolderId = $_POST['sub_folder_id'] ?? null;
    $documentType = $_POST['document_type'] ?? null;

    if (!$subFolderId || !$documentType) {
        ApiResponse::error('sub_folder_id and document_type are required', 400);
    }

    // Validate sub-folder exists
    $subFolder = $db->query("SELECT * FROM archive_document_sub_folders WHERE id = ?",
                           'SELECT', 'ROW', [$subFolderId]);
    if (!$subFolder) {
        ApiResponse::error('Sub-folder not found', 404);
    }

    // Get file info
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Validate file type (only PDF allowed based on your system)
    $allowedExts = ['pdf'];
    if (!in_array($fileExt, $allowedExts)) {
        ApiResponse::error('Only PDF files are allowed', 400);
    }

    // Create upload directory if doesn't exist
    $uploadDir = '../../allfiles/' . date('Y/m/');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExt;
    $uploadPath = $uploadDir . $uniqueFileName;
    $relativePath = 'allfiles/' . date('Y/m/') . $uniqueFileName;

    // Move uploaded file
    if (!move_uploaded_file($fileTmpName, $uploadPath)) {
        ApiResponse::error('Failed to save file', 500);
    }

    // Create file record in database
    $description = $_POST['description'] ?? $fileName;
    $documentDate = $_POST['document_date'] ?? date('Y-m-d');
    $name = $_POST['name'] ?? pathinfo($fileName, PATHINFO_FILENAME);

    $insertQuery = "INSERT INTO archives
                    (name, description, path, sub_folder_id, document_type,
                     document_date, file_size, edited_by, completed, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";

    $db->query($insertQuery, 'INSERT', 'ROW',
              [$name, $description, $relativePath, $subFolderId, $documentType,
               $documentDate, $fileSize, $user['user_id']]);

    $fileId = $db->getLastInsertId();

    // Get created file record
    $createdFile = $db->query("SELECT * FROM archives WHERE id = ?",
                             'SELECT', 'ROW', [$fileId]);

    $createdFile['file_url'] = BASE_URL . $createdFile['path'];

    ApiResponse::success(['file' => $createdFile], 'File uploaded successfully', 201);
}

function downloadFile($db, $id, $user) {
    // Get file info
    $file = $db->query("SELECT * FROM archives WHERE id = ?", 'SELECT', 'ROW', [$id]);

    if (!$file) {
        ApiResponse::error('File not found', 404);
    }

    // Handle different DB return formats
    if (is_array($file) && isset($file[0])) {
        $file = $file[0];
    }

    // Path in DB is like: ../allfiles/pf-archives/file.pdf
    // From /api/v1/endpoints/, we need to resolve the path
    // The ../ in DB path goes up from pages directory
    // We need to go from endpoints -> v1 -> api -> root, then apply DB path
    $dbPath = $file['path'];

    // Remove leading ../ from DB path and prepend correct number of ../
    // On production: ../../../../allfiles/... (allfiles is in public_html)
    // On local: ../../../../allfiles/... (allfiles is in htdocs)
    if (strpos($dbPath, '../') === 0) {
        $relativePath = substr($dbPath, 3); // Remove '../'
        $filePath = '../../../../' . $relativePath;
    } else {
        $filePath = '../../../../' . $dbPath;
    }

    if (!file_exists($filePath)) {
        ApiResponse::error('Physical file not found', 404);
    }

    // Clear any output buffers and remove JSON headers
    while (ob_get_level()) {
        ob_end_clean();
    }

    if (function_exists('header_remove')) {
        header_remove();
    }

    // Set headers for file download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($file['name']) . '.pdf"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache');

    // Output file
    readfile($filePath);
    exit();
}

function viewFile($db, $id, $user) {
    try {
        // Get file info
        $file = $db->query("SELECT * FROM archives WHERE id = ?", 'SELECT', 'ROW', [$id]);

        if (!$file) {
            ApiResponse::error('File not found in database', 404);
        }

        // Handle different DB return formats
        if (is_array($file) && isset($file[0])) {
            $file = $file[0];
        }

        // Path in DB is like: ../allfiles/pf-archives/file.pdf
        // From /api/v1/endpoints/, we need to resolve the path
        // The ../ in DB path goes up from pages directory
        // We need to go from endpoints -> v1 -> api -> root, then apply DB path
        $dbPath = $file['path'] ?? null;

        if (!$dbPath) {
            ApiResponse::error('File path not found in database record', 404);
        }
    } catch (Exception $e) {
        ApiResponse::error('Error in viewFile: ' . $e->getMessage(), 500);
    }

    // Remove leading ../ from DB path and prepend correct number of ../
    // On production: ../../../../allfiles/... (allfiles is in public_html)
    // On local: ../../../../allfiles/... (allfiles is in htdocs)
    if (strpos($dbPath, '../') === 0) {
        $relativePath = substr($dbPath, 3); // Remove '../'
        $filePath = '../../../../' . $relativePath;
    } else {
        $filePath = '../../../../' . $dbPath;
    }

    if (!file_exists($filePath)) {
        // Return detailed error for debugging
        ApiResponse::error('Physical file not found. Path: ' . $filePath . ', DB Path: ' . $dbPath . ', Resolved: ' . realpath($filePath), 404);
    }

    // CRITICAL: Clear any output buffers and remove JSON headers set by index.php
    // This prevents "Content-Type: application/json" from interfering with PDF serving
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Clear all previously set headers
    if (function_exists('header_remove')) {
        header_remove();
    }

    // Determine MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);

    if (!$mimeType) {
        $mimeType = 'application/pdf';
    }

    // Set headers for inline viewing (not download)
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . basename($file['name']) . '.pdf"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: public, max-age=3600');
    header('Accept-Ranges: bytes');

    // Handle range requests for PDF streaming
    if (isset($_SERVER['HTTP_RANGE'])) {
        $fileSize = filesize($filePath);
        $range = $_SERVER['HTTP_RANGE'];
        $range = str_replace('bytes=', '', $range);
        $range = explode('-', $range);
        $start = intval($range[0]);
        $end = isset($range[1]) && $range[1] ? intval($range[1]) : $fileSize - 1;

        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        header('Content-Length: ' . ($end - $start + 1));

        $fp = fopen($filePath, 'rb');
        fseek($fp, $start);
        $buffer = 8192;
        $bytesLeft = $end - $start + 1;

        while ($bytesLeft > 0 && !feof($fp)) {
            $read = ($bytesLeft > $buffer) ? $buffer : $bytesLeft;
            echo fread($fp, $read);
            $bytesLeft -= $read;
            flush();
        }

        fclose($fp);
    } else {
        // Normal file serving
        readfile($filePath);
    }

    exit();
}

function createFileRecord($db, $input, $user) {
    // Validate input
    if (!isset($input['name']) || empty(trim($input['name']))) {
        ApiResponse::error('File name is required', 400);
    }

    if (!isset($input['sub_folder_id']) || !isset($input['document_type'])) {
        ApiResponse::error('sub_folder_id and document_type are required', 400);
    }

    $name = trim($input['name']);
    $description = $input['description'] ?? '';
    $path = $input['path'] ?? '';
    $subFolderId = $input['sub_folder_id'];
    $documentType = $input['document_type'];
    $documentDate = $input['document_date'] ?? date('Y-m-d');
    $completed = $input['completed'] ?? 0;

    // Create file record
    $insertQuery = "INSERT INTO archives
                    (name, description, path, sub_folder_id, document_type,
                     document_date, edited_by, completed, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $db->query($insertQuery, 'INSERT', 'ROW',
              [$name, $description, $path, $subFolderId, $documentType,
               $documentDate, $user['user_id'], $completed]);

    $fileId = $db->getLastInsertId();

    // Get created file
    $file = $db->query("SELECT * FROM archives WHERE id = ?", 'SELECT', 'ROW', [$fileId]);

    ApiResponse::success(['file' => $file], 'File record created successfully', 201);
}

function updateFile($db, $id, $input, $user) {
    // Check if file exists
    $file = $db->query("SELECT * FROM archives WHERE id = ?", 'SELECT', 'ROW', [$id]);

    if (!$file) {
        ApiResponse::error('File not found', 404);
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
        $params[] = $input['description'];
    }

    if (isset($input['document_date'])) {
        $updates[] = "document_date = ?";
        $params[] = $input['document_date'];
    }

    if (isset($input['completed'])) {
        $updates[] = "completed = ?";
        $params[] = $input['completed'];
    }

    if (isset($input['document_type'])) {
        $updates[] = "document_type = ?";
        $params[] = $input['document_type'];
    }

    if (empty($updates)) {
        ApiResponse::error('No fields to update', 400);
    }

    $updates[] = "edited_by = ?";
    $params[] = $user['user_id'];

    $params[] = $id;

    $updateQuery = "UPDATE archives SET " . implode(', ', $updates) . " WHERE id = ?";
    $db->query($updateQuery, 'UPDATE', 'ROW', $params);

    // Get updated file
    $updatedFile = $db->query("SELECT * FROM archives WHERE id = ?", 'SELECT', 'ROW', [$id]);

    ApiResponse::success(['file' => $updatedFile], 'File updated successfully');
}

function deleteFile($db, $id, $user) {
    // Check if file exists
    $file = $db->query("SELECT * FROM archives WHERE id = ?", 'SELECT', 'ROW', [$id]);

    if (!$file) {
        ApiResponse::error('File not found', 404);
    }

    // Delete physical file
    $filePath = '../../' . $file['path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Delete database record
    $deleteQuery = "DELETE FROM archives WHERE id = ?";
    $db->query($deleteQuery, 'DELETE', 'ROW', [$id]);

    ApiResponse::success([], 'File deleted successfully');
}
