<?php
/**
 * Session-protected AJAX handler: proxy a source record's details from a
 * connected system, resolved by external_ref_id.
 *
 * GET received_detail.php?ref={external_ref_id}&system_id={id}[&debug=1]
 * Returns JSON {success, data}. ?debug=1 adds a {debug} trace of each step
 * (session, ref lookup, the outbound callback URL, HTTP code, raw response) —
 * TEMPORARY diagnostic; remove or fix the secrets-file stray byte once resolved.
 */

// Buffer output from the includes (config/secrets) so a stray BOM/whitespace
// byte can't pre-send headers — which would otherwise break session_start()
// (→ "Not authenticated") and corrupt the JSON body.
ob_start();

require_once 'config.php';
require_once 'models/DB.php';
require_once 'models/ConnectedSystem.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$debug = isset($_GET['debug']);
$trace = [];

/** Emit JSON, discarding any buffered include output first. */
function rd_out(array $payload, int $code = 200) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// ── Session diagnostics ──────────────────────────────────────────────────────
$loggedIn = !empty($_SESSION[SESSION_NAME]['user_id']);
$trace['session_status']  = session_status();                 // 2 = PHP_SESSION_ACTIVE
$trace['session_id']      = session_id() !== '' ? 'present' : 'none';
$trace['session_key_set'] = isset($_SESSION[SESSION_NAME]);
$trace['user_id_present'] = $loggedIn;
$trace['headers_sent']    = headers_sent();   // boolean only — no file path leak
$trace['ob_level']        = ob_get_level();

if (!$loggedIn) {
    rd_out(['success' => false, 'message' => 'Not authenticated'] + ($debug ? ['debug' => $trace] : []), 401);
}

$extRefId = trim($_GET['ref'] ?? '');
$systemId = (int) ($_GET['system_id'] ?? 0);
if ($extRefId === '' || $systemId <= 0) {
    rd_out(['success' => false, 'message' => 'ref and system_id parameters are required'] + ($debug ? ['debug' => $trace] : []), 400);
}

// ── Look up the ref scoped to the specific connected system ──────────────────
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
    rd_out(['success' => false, 'message' => 'Reference not found'] + ($debug ? ['debug' => $trace] : []), 404);
}
$trace['callback_url_set'] = !empty($row['callback_url']);

if (empty($row['callback_url'])) {
    rd_out(['success' => false, 'message' => 'Source system has no callback URL configured'] + ($debug ? ['debug' => $trace] : []), 503);
}

// ── Proxy to the connected system (server-side, with its API key) ─────────────
$callbackUrl = rtrim($row['callback_url'], '/') . '/efile_callback/' . rawurlencode($row['external_ref_id']);
$trace['callback_request'] = $callbackUrl;

$callbackHost   = parse_url($callbackUrl, PHP_URL_HOST);
$callbackScheme = parse_url($callbackUrl, PHP_URL_SCHEME);
$isLoopback     = in_array($callbackHost, ['localhost', '127.0.0.1', '::1'], true);

$curl = curl_init($callbackUrl);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'X-API-Key: ' . $row['api_key'],
        'Accept: application/json',
    ],
    CURLOPT_SSL_VERIFYPEER => !($isLoopback && $callbackScheme === 'http'),
    CURLOPT_SSL_VERIFYHOST => ($isLoopback && $callbackScheme === 'http') ? 0 : 2,
]);
$raw      = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlErr  = curl_errno($curl) ? curl_error($curl) : null;
curl_close($curl);

$trace['curl_error']   = $curlErr;
$trace['http_code']    = $httpCode;
$trace['raw_response'] = $debug ? substr((string) $raw, 0, 600) : null;

if ($curlErr) {
    rd_out(['success' => false, 'message' => 'Could not reach ' . $row['system_name'] . ': ' . $curlErr] + ($debug ? ['debug' => $trace] : []), 502);
}

$json = json_decode($raw, true);
if ($httpCode !== 200 || empty($json['success'])) {
    $msg = $json['message'] ?? ('HTTP ' . $httpCode);
    rd_out(['success' => false, 'message' => $row['system_name'] . ' returned: ' . $msg] + ($debug ? ['debug' => $trace] : []), 502);
}

$json['system'] = $row['system_name'];
if ($debug) { $json['debug'] = $trace; }
rd_out($json);
