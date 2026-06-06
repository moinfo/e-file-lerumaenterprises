<?php
/**
 * Ingest endpoint — machine-to-machine document ingestion from external systems.
 *
 * Auth:  X-API-Key: <key>  (from connected_systems.api_key, NOT a user Bearer token)
 *
 * Routes (all under /api/v1/ingest/):
 *   POST   upload          — receive a file, return efile_id
 *   GET    file/{ref_id}   — get status of an ingested file by external ref
 *   DELETE file/{ref_id}   — soft-delete if not yet completed
 *   GET    stats           — counts per status for this system
 */

require_once '../../models/ConnectedSystem.php';

function handleIngest(string $method, ?string $id, ?string $action, ?array $input): void {
    $system = _ingestAuth();
    $csm    = new ConnectedSystem();

    switch ($id) {
        case 'upload':
            if ($method !== 'POST') ApiResponse::error('Method not allowed', 405);
            _ingestUpload($system, $csm);
            break;

        case 'file':
            if (empty($action)) ApiResponse::error('File reference ID required in path: /ingest/file/{ref_id}', 400);
            match ($method) {
                'GET'    => _ingestGetFile($system, $csm, $action),
                'DELETE' => _ingestDeleteFile($system, $csm, $action),
                default  => ApiResponse::error('Method not allowed', 405),
            };
            break;

        case 'stats':
            if ($method !== 'GET') ApiResponse::error('Method not allowed', 405);
            _ingestStats($system, $csm);
            break;

        default:
            ApiResponse::error('Unknown ingest path. Available: upload | file/{ref_id} | stats', 404);
    }
}

// ── Auth ────────────────────────────────────────────────────────────────────

function _ingestAuth(): array {
    $headers = getallheaders();
    $key = $headers['X-API-Key']
        ?? $headers['x-api-key']
        ?? ($_SERVER['HTTP_X_API_KEY'] ?? null);

    if (!$key) {
        ApiResponse::error('X-API-Key header required', 401);
    }

    $csm    = new ConnectedSystem();
    $system = $csm->findByApiKey((string) $key);

    if (!$system) {
        ApiResponse::error('Invalid or inactive API key', 401);
    }

    return $system;
}

// ── Upload ──────────────────────────────────────────────────────────────────

function _ingestUpload(array $system, ConnectedSystem $csm): void {
    // Validate file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        ApiResponse::error("File upload failed (PHP error code {$code})", 400);
    }
    $file = $_FILES['file'];

    // Extension check
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = array_map('trim', explode(',', $system['allowed_extensions']));
    if (!in_array($ext, $allowed, true)) {
        ApiResponse::error(
            "File type .{$ext} not permitted for this system. Allowed: " . implode(', ', $allowed),
            400
        );
    }

    // Size check
    $maxBytes = (int) $system['max_file_size_mb'] * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        ApiResponse::error("File exceeds maximum size of {$system['max_file_size_mb']}MB", 400);
    }

    // Required: external ref ID (mainstore's own ID for the file)
    $extRefId = trim($_POST['external_ref_id'] ?? '');
    if ($extRefId === '') {
        ApiResponse::error('external_ref_id is required (your system\'s unique ID for this file)', 400);
    }
    if ($csm->refExists((int) $system['id'], $extRefId)) {
        ApiResponse::error("A file with external_ref_id '{$extRefId}' already exists for this system. Use GET /file/{ref} to check its status.", 409);
    }

    // Folder / document type — POST param overrides system default
    $subFolderId = (int) ($_POST['sub_folder_id'] ?? $system['default_sub_folder_id'] ?? 0);
    $docTypeId   = (int) ($_POST['document_type_id'] ?? $system['default_document_type_id'] ?? 0);

    if (!$subFolderId) {
        ApiResponse::error('sub_folder_id is required (or set a default on the connected system in Settings)', 400);
    }
    if (!$docTypeId) {
        ApiResponse::error('document_type_id is required (or set a default on the connected system in Settings)', 400);
    }

    // Optional metadata
    $description = trim($_POST['description'] ?? '');
    $rawDate     = trim($_POST['document_date'] ?? '');
    $docDate     = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) ? $rawDate : date('Y-m-d');

    // Store file with a collision-proof name
    $safeName   = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
    $storedName = time() . '_' . $system['slug'] . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath   = rtrim(FILES_PATH, '/') . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        ApiResponse::error('Failed to save uploaded file to storage. Check FILES_PATH permissions.', 500);
    }

    // Create archive record
    $db          = new DB();
    $displayName = $description ?: $safeName;
    $archiveId   = (int) $db->query(
        "INSERT INTO archives (name, path, description, document_type, sub_folder_id, completed, document_date, created_at)
         VALUES (?, ?, ?, ?, ?, 0, ?, NOW())",
        'INSERT', null,
        [$displayName, $destPath, $description, $docTypeId, $subFolderId, $docDate]
    );

    if (!$archiveId) {
        unlink($destPath);
        ApiResponse::error('Failed to create archive record', 500);
    }

    // Link the archive to the external ref
    $csm->createRef((int) $system['id'], $extRefId, $archiveId);

    ApiResponse::success([
        'efile_id'        => $archiveId,
        'external_ref_id' => $extRefId,
        'system'          => $system['name'],
        'status'          => 'active',
        'editable'        => true,
        'sub_folder_id'   => $subFolderId,
        'document_type_id'=> $docTypeId,
    ], 'File received and archived successfully', 201);
}

// ── Get file ────────────────────────────────────────────────────────────────

function _ingestGetFile(array $system, ConnectedSystem $csm, string $extRefId): void {
    $ref = $csm->findRef((int) $system['id'], $extRefId);
    if (!$ref) {
        ApiResponse::error("No file with external_ref_id '{$extRefId}' found for this system", 404);
    }

    $completed = (bool) (int) $ref['completed'];
    ApiResponse::success([
        'efile_id'        => (int) $ref['archive_id'],
        'external_ref_id' => $ref['external_ref_id'],
        'status'          => $ref['status'],
        'completed'       => $completed,
        'editable'        => $ref['status'] === 'active' && !$completed,
        'name'            => $ref['archive_name'],
        'description'     => $ref['description'],
        'document_date'   => $ref['document_date'],
        'received_at'     => $ref['created_at'],
        'updated_at'      => $ref['updated_at'],
    ]);
}

// ── Delete file ─────────────────────────────────────────────────────────────

function _ingestDeleteFile(array $system, ConnectedSystem $csm, string $extRefId): void {
    $ref = $csm->findRef((int) $system['id'], $extRefId);
    if (!$ref) {
        ApiResponse::error("No file with external_ref_id '{$extRefId}' found for this system", 404);
    }

    // Completed files are permanent — they cannot be deleted externally
    if ((int) $ref['completed'] === 1) {
        ApiResponse::error(
            'This file has been completed/archived and cannot be deleted via the external API. Contact an administrator.',
            403
        );
    }

    if ($ref['status'] === 'deleted_by_source') {
        ApiResponse::error('File has already been deleted', 410);
    }

    if (!$csm->softDeleteRef((int) $system['id'], $extRefId)) {
        ApiResponse::error('Delete operation failed', 500);
    }

    ApiResponse::success([
        'external_ref_id' => $extRefId,
        'efile_id'        => (int) $ref['archive_id'],
        'deleted'         => true,
        'note'            => 'The file record is preserved for audit. Only the external reference is marked deleted.',
    ], 'File deleted successfully');
}

// ── Stats ────────────────────────────────────────────────────────────────────

function _ingestStats(array $system, ConnectedSystem $csm): void {
    $db   = new DB();
    $rows = $db->query(
        "SELECT status, COUNT(*) AS cnt FROM external_file_refs WHERE connected_system_id = ? GROUP BY status",
        'SELECT', false, [(int) $system['id']]
    );

    $stats = ['active' => 0, 'completed' => 0, 'deleted_by_source' => 0];
    if (is_array($rows)) {
        foreach ($rows as $r) {
            $stats[$r['status']] = (int) $r['cnt'];
        }
    }

    ApiResponse::success([
        'system'  => $system['name'],
        'slug'    => $system['slug'],
        'active'  => (bool) (int) $system['is_active'],
        'files'   => $stats,
        'total'   => array_sum($stats),
    ]);
}
