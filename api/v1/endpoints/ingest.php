<?php
/**
 * Ingest endpoint — machine-to-machine document ingestion from external systems.
 *
 * Auth:  X-API-Key: <key>  (from connected_systems.api_key, NOT a user Bearer token)
 *
 * Routes (all under /api/v1/ingest/):
 *   POST   upload              — receive a file, return efile_id
 *   GET    file/{ref_id}       — get status of an ingested file by external ref
 *   PATCH  file/{ref_id}       — update local_id or description on an existing ref
 *   DELETE file/{ref_id}       — soft-delete if not yet completed
 *   GET    detail/{ref_id}     — fetch live record details from the source system
 *   GET    serve/{ref_id}      — stream the actual file (for embedding in external systems)
 *   GET    stats               — counts per status for this system
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
            // switch (not match) for PHP 7.x compatibility on shared hosting
            switch ($method) {
                case 'GET':    _ingestGetFile($system, $csm, $action); break;
                case 'DELETE': _ingestDeleteFile($system, $csm, $action); break;
                case 'PATCH':  _ingestPatchFile($system, $csm, $action, $input ?? []); break;
                default:       ApiResponse::error('Method not allowed', 405);
            }
            break;

        case 'detail':
            if ($method !== 'GET') ApiResponse::error('Method not allowed', 405);
            if (empty($action)) ApiResponse::error('File reference ID required: /ingest/detail/{ref_id}', 400);
            _ingestDetail($system, $csm, $action);
            break;

        case 'stats':
            if ($method !== 'GET') ApiResponse::error('Method not allowed', 405);
            _ingestStats($system, $csm);
            break;

        case 'serve':
            if ($method !== 'GET') ApiResponse::error('Method not allowed', 405);
            if (empty($action)) ApiResponse::error('File reference ID required: /ingest/serve/{ref_id}', 400);
            _ingestServeFile($system, $csm, $action);
            break;

        default:
            ApiResponse::error('Unknown ingest path. Available: upload | file/{ref_id} | detail/{ref_id} | serve/{ref_id} | stats', 404);
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
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        ApiResponse::error("File upload failed (PHP error code {$code})", 400);
    }
    $file = $_FILES['file'];

    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = array_map('trim', explode(',', $system['allowed_extensions']));
    if (!in_array($ext, $allowed, true)) {
        ApiResponse::error("File type .{$ext} not permitted. Allowed: " . implode(', ', $allowed), 400);
    }

    $maxBytes = (int) $system['max_file_size_mb'] * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        ApiResponse::error("File exceeds maximum size of {$system['max_file_size_mb']}MB", 400);
    }

    $extRefId = trim($_POST['external_ref_id'] ?? '');
    if ($extRefId === '') {
        ApiResponse::error('external_ref_id is required', 400);
    }
    if ($csm->refExists((int) $system['id'], $extRefId)) {
        ApiResponse::error("A file with external_ref_id '{$extRefId}' already exists. Use GET /file/{ref} to check its status.", 409);
    }

    $description = trim($_POST['description'] ?? '');
    $rawDate     = trim($_POST['document_date'] ?? '');
    $docDate     = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) ? $rawDate : date('Y-m-d');
    $localId     = trim($_POST['local_id'] ?? '') ?: null;

    $safeName   = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
    $storedName = time() . '_' . $system['slug'] . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $archivesDir = rtrim(FILES_PATH, '/') . DIRECTORY_SEPARATOR . 'pf-archives';
    if (!is_dir($archivesDir)) {
        mkdir($archivesDir, 0755, true);
    }
    $destPath   = $archivesDir . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        ApiResponse::error('Failed to save uploaded file. Check FILES_PATH permissions.', 500);
    }

    $db          = new DB();
    $displayName = $description ?: $safeName;
    // document_type and sub_folder_id are intentionally left NULL —
    // the e-file editor user categorises received files themselves.
    $archiveId   = (int) $db->query(
        "INSERT INTO archives (name, path, description, completed, document_date)
         VALUES (?, ?, ?, 0, ?)",
        'INSERT', null,
        [$displayName, $destPath, $description, $docDate]
    );

    if (!$archiveId) {
        unlink($destPath);
        ApiResponse::error('Failed to create archive record', 500);
    }

    $csm->createRef((int) $system['id'], $extRefId, $archiveId, $localId);

    ApiResponse::success([
        'efile_id'        => $archiveId,
        'external_ref_id' => $extRefId,
        'system'          => $system['name'],
        'status'          => 'active',
        'editable'        => true,
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
        'local_id'        => $ref['local_id'],
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

    if ((int) $ref['completed'] === 1) {
        ApiResponse::error(
            'This file has been completed and cannot be deleted via the external API.',
            403
        );
    }

    if ($ref['status'] === 'deleted_by_source') {
        ApiResponse::error('File has already been deleted', 410);
    }

    if (!$csm->softDeleteRef((int) $system['id'], $extRefId)) {
        ApiResponse::error('Delete operation failed', 500);
    }

    // Remove the archive record and its file from disk — the source system owns
    // this document, so when they retract it we clean up fully.
    $db = new DB();
    $db->query("DELETE FROM archives WHERE id = ?", 'DELETE', null, [(int) $ref['archive_id']]);
    if (!empty($ref['path']) && file_exists($ref['path'])) {
        @unlink($ref['path']);
    }

    ApiResponse::success([
        'external_ref_id' => $extRefId,
        'efile_id'        => (int) $ref['archive_id'],
        'deleted'         => true,
    ], 'File deleted successfully');
}

// ── Patch file metadata ──────────────────────────────────────────────────────

function _ingestPatchFile(array $system, ConnectedSystem $csm, string $extRefId, array $input): void {
    $ref = $csm->findRef((int) $system['id'], $extRefId);
    if (!$ref) {
        ApiResponse::error("No file with external_ref_id '{$extRefId}' found for this system", 404);
    }

    if ($ref['status'] === 'deleted_by_source') {
        ApiResponse::error('Cannot update a deleted file reference', 410);
    }

    $updates = [];
    if (array_key_exists('local_id', $input)) {
        $updates['local_id'] = trim($input['local_id']) ?: null;
    }
    if (isset($input['description'])) {
        $updates['description'] = trim($input['description']);
    }

    if (empty($updates)) {
        ApiResponse::error('No updatable fields provided. Supported: local_id, description', 400);
    }

    $db         = new DB();
    $setClauses = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($updates)));
    $params     = array_values($updates);
    $params[]   = (int) $system['id'];
    $params[]   = $extRefId;

    $db->query(
        "UPDATE external_file_refs SET {$setClauses}, updated_at = NOW() WHERE connected_system_id = ? AND external_ref_id = ?",
        'UPDATE', null, $params
    );

    ApiResponse::success([
        'external_ref_id' => $extRefId,
        'updated'         => array_keys($updates),
    ], 'File reference updated');
}

// ── Detail (proxy to source system) ─────────────────────────────────────────

function _ingestDetail(array $system, ConnectedSystem $csm, string $extRefId): void {
    $ref = $csm->findRef((int) $system['id'], $extRefId);
    if (!$ref) {
        ApiResponse::error("No file with external_ref_id '{$extRefId}' found", 404);
    }

    if (empty($ref['local_id'])) {
        ApiResponse::error('No local_id linked to this file — source system details unavailable', 404);
    }

    if (empty($system['callback_url'])) {
        ApiResponse::error('This connected system has no callback_url configured', 503);
    }

    $callbackUrl = rtrim($system['callback_url'], '/') . '/efile_callback/expense/' . rawurlencode($ref['local_id']);

    $cbHost   = parse_url($callbackUrl, PHP_URL_HOST);
    $cbScheme = parse_url($callbackUrl, PHP_URL_SCHEME);
    $isLoop   = in_array($cbHost, ['localhost', '127.0.0.1', '::1'], true);

    $curl = curl_init($callbackUrl);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'X-API-Key: ' . $system['api_key'],
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => !($isLoop && $cbScheme === 'http'),
        CURLOPT_SSL_VERIFYHOST => ($isLoop && $cbScheme === 'http') ? 0 : 2,
    ]);

    $raw      = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlErr  = curl_errno($curl) ? curl_error($curl) : null;
    curl_close($curl);

    if ($curlErr) {
        ApiResponse::error('Could not reach source system: ' . $curlErr, 502);
    }

    $json = json_decode($raw, true);
    if ($httpCode !== 200 || empty($json['success'])) {
        $msg = $json['message'] ?? ('HTTP ' . $httpCode);
        ApiResponse::error('Source system returned an error: ' . $msg, 502);
    }

    ApiResponse::success($json['data'], 'Details fetched from ' . $system['name']);
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

// ── Serve file ───────────────────────────────────────────────────────────────

function _ingestServeFile(array $system, ConnectedSystem $csm, string $extRefId): void {
    $ref = $csm->findRef((int) $system['id'], $extRefId);
    if (!$ref) {
        http_response_code(404); exit('File not found');
    }

    if ($ref['status'] === 'deleted_by_source') {
        http_response_code(410); exit('File has been deleted');
    }

    $filePath = $ref['path'];
    if (!file_exists($filePath)) {
        $filePath = rtrim(FILES_PATH, '/') . '/pf-archives/' . basename($ref['path']);
    }
    if (!file_exists($filePath)) {
        http_response_code(404); exit('File not found on disk');
    }

    $real = realpath($filePath);
    $base = realpath(rtrim(FILES_PATH, DIRECTORY_SEPARATOR));
    if ($real === false || $base === false || strncmp($real, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) {
        http_response_code(403); exit('Access denied');
    }

    $ext   = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    $types = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png',  'gif'  => 'image/gif',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    $mime  = $types[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($real));
    header('Content-Disposition: inline; filename="' . basename($real) . '"');
    header('Cache-Control: private, max-age=300');
    readfile($real);
    exit;
}
