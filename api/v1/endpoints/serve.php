<?php
/**
 * Top-level serve endpoint: GET /api/v1/serve/{efile_ref}
 *
 * Streams a pre-uploaded file by external_ref_id so connected systems
 * (e.g. mainstore) can embed a preview before the record is saved.
 * Auth: X-API-Key from the connected system that owns the ref.
 *
 * The mainstore preview_efile proxy builds URLs as:
 *   {efile_api_url_base}/serve/{efile_ref}
 * which routes here (resource=serve, id={efile_ref}).
 */

require_once '../../models/ConnectedSystem.php';

function handleServe(string $method, ?string $id, ?string $action, ?array $input): void {
    if ($method !== 'GET') {
        http_response_code(405); exit('Method not allowed');
    }

    // Auth: X-API-Key from the calling connected system
    $headers = getallheaders();
    $key = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? null);
    if (!$key) {
        http_response_code(401); exit('X-API-Key required');
    }

    $csm    = new ConnectedSystem();
    $system = $csm->findByApiKey((string) $key);
    if (!$system) {
        http_response_code(401); exit('Invalid or inactive API key');
    }

    $efileRef = trim((string) $id);
    if ($efileRef === '') {
        http_response_code(400); exit('efile_ref required in URL: /serve/{efile_ref}');
    }

    $ref = $csm->findRef((int) $system['id'], $efileRef);
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
    $mime = $types[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($real));
    header('Content-Disposition: inline; filename="' . basename($real) . '"');
    header('Cache-Control: private, max-age=300');
    readfile($real);
    exit;
}
