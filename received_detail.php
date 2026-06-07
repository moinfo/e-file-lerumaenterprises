<?php
/**
 * Session-protected AJAX handler: proxy expense details from a connected system.
 *
 * GET received_detail.php?ref={external_ref_id}
 * Returns JSON {success, data} — mirrors what the connected system's callback returns.
 */

require_once 'config.php';
require_once 'models/DB.php';
require_once 'models/ConnectedSystem.php';

// ── Session guard ────────────────────────────────────────────────────────────
session_name(SESSION_NAME);
session_start();

header('Content-Type: application/json');

if (empty($_SESSION[SESSION_NAME]['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$extRefId = trim($_GET['ref'] ?? '');
$systemId = (int) ($_GET['system_id'] ?? 0);
if ($extRefId === '' || $systemId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ref and system_id parameters are required']);
    exit;
}

// ── Look up the ref scoped to the specific connected system ──────────────────
// Both system_id AND external_ref_id must match — prevents one system's ref
// from being used to proxy callbacks through another system's callback_url.
$db  = new DB();
$row = $db->query(
    "SELECT efr.local_id, efr.external_ref_id, efr.status,
            cs.name AS system_name, cs.callback_url, cs.api_key
     FROM external_file_refs efr
     JOIN connected_systems cs ON cs.id = efr.connected_system_id
     WHERE efr.connected_system_id = ? AND efr.external_ref_id = ?",
    'SELECT', true, [$systemId, $extRefId]
);

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Reference not found']);
    exit;
}

if (empty($row['local_id'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No source record linked yet — details not available']);
    exit;
}

if (empty($row['callback_url'])) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Source system has no callback URL configured']);
    exit;
}

// ── Proxy the request to the connected system ─────────────────────────────────
// The connected system authenticates us using the same API key it registered with
$callbackUrl = rtrim($row['callback_url'], '/') . '/efile_callback/expense/' . rawurlencode($row['local_id']);

$callbackHost  = parse_url($callbackUrl, PHP_URL_HOST);
$isLoopback    = in_array($callbackHost, ['localhost', '127.0.0.1', '::1'], true);
$callbackScheme = parse_url($callbackUrl, PHP_URL_SCHEME);

$curl = curl_init($callbackUrl);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'X-API-Key: ' . $row['api_key'],
        'Accept: application/json',
    ],
    // Skip TLS verification only for plain-HTTP loopback (local dev).
    // All production HTTPS callbacks use full peer verification.
    CURLOPT_SSL_VERIFYPEER => !($isLoopback && $callbackScheme === 'http'),
    CURLOPT_SSL_VERIFYHOST => ($isLoopback && $callbackScheme === 'http') ? 0 : 2,
]);

$raw      = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlErr  = curl_errno($curl) ? curl_error($curl) : null;
curl_close($curl);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Could not reach ' . $row['system_name'] . ': ' . $curlErr]);
    exit;
}

$json = json_decode($raw, true);
if ($httpCode !== 200 || empty($json['success'])) {
    $msg = $json['message'] ?? ('HTTP ' . $httpCode);
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => $row['system_name'] . ' returned: ' . $msg]);
    exit;
}

$json['system'] = $row['system_name'];
echo json_encode($json);
exit;
