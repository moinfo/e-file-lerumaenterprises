<?php
/**
 * Authenticated inline file viewer.
 * Serves a document from FILES_PATH for logged-in users (or same-site embeds).
 * Path resolution mirrors serve_file.php: string-normalized (no realpath under
 * open_basedir), multi-candidate, with a pf-archives/ fallback for bare names.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Buffer include output so a stray BOM/whitespace byte can't corrupt the
// streamed file or pre-send headers.
ob_start();

require_once('./config.php');
require_once('./models/Autoload.php');

// Auth: logged-in user, OR a same-site request (for embedded PDFs/images).
if (!isset($_SESSION[SESSION_NAME]['user_id'])) {
    $refererHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    if ($refererHost !== parse_url(BASE_URL, PHP_URL_HOST)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(401);
        exit('Unauthorized');
    }
}

$req = isset($_GET['file']) ? (string) $_GET['file'] : '';
if ($req === '') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(400);
    exit('File not specified');
}

$baseDir  = rtrim(FILES_PATH, '/');
$siteRoot = rtrim(__DIR__, '/');

// Resolve ../ and ./ as pure string ops (safe under open_basedir).
function _fv_normalize(string $path): string {
    $parts = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $p) {
        if ($p === '..' && !empty($parts)) { array_pop($parts); }
        elseif ($p !== '.' && $p !== '')   { $parts[] = $p; }
    }
    return '/' . implode('/', $parts);
}

$allowedBases = [$baseDir, $siteRoot . '/allfiles'];
$withinAllowed = function (string $p) use ($allowedBases): bool {
    foreach ($allowedBases as $base) {
        if (strncmp($p, $base . '/', strlen($base) + 1) === 0) return true;
    }
    return false;
};

// Build candidate absolute paths, then pick the first that is contained & exists.
$candidates = [];
if ($req[0] === '/') {
    $candidates[] = _fv_normalize($req);                       // absolute (ingest API)
} else {
    $r = ltrim($req, '/\\');
    $candidates[] = _fv_normalize($siteRoot . '/' . $r);       // ../allfiles/... relative
    $stripped = preg_replace('#(\.\.[\\/])+#', '', $r);
    $stripped = ltrim(preg_replace('#^allfiles[\\/]#i', '', $stripped), '/');
    $candidates[] = _fv_normalize($baseDir . '/' . $stripped);              // FILES_PATH/<path>
    $candidates[] = _fv_normalize($baseDir . '/pf-archives/' . $stripped);  // bare name → pf-archives
}

$filePath = null;
foreach ($candidates as $c) {
    $norm = _fv_normalize($c);
    if (!$withinAllowed($norm)) continue;
    if (!is_file($norm) || !is_readable($norm)) continue;
    $real = realpath($norm);                       // may be false under open_basedir
    if ($real !== false && !$withinAllowed($real)) continue;   // symlink escape
    $filePath = $norm;
    break;
}

if ($filePath === null) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(404);
    exit('File not found');
}

// MIME by extension (reliable on cPanel), octet-stream fallback.
$contentTypes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',  'jpeg' => 'image/jpeg',
    'png'  => 'image/png',   'gif'  => 'image/gif',
    'webp' => 'image/webp',
];
$ext         = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$contentType = $contentTypes[$ext] ?? 'application/octet-stream';

// Discard buffered include output so the body is exactly the file bytes.
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Cache-Control: public, max-age=86400');

readfile($filePath);
exit;
