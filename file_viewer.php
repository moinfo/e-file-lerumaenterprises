<?php
/**
 * Authenticated, authorized inline file viewer.
 *
 * Security model:
 *  - Requires a logged-in e-File session (NO Referer-based bypass — Referer is
 *    spoofable; same-origin iframes/embeds carry the session cookie anyway).
 *  - The client supplies only a file *name*; the actual file is identified by a
 *    DB lookup and gated by the user's folder/group access (same scope as
 *    view_by_ref.php). The client-supplied string is never trusted as a path.
 *  - The on-disk path comes from the DB row and is resolved with string
 *    normalization (no realpath reliance under open_basedir) + containment.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Buffer include output so a stray BOM/whitespace byte can't pre-send headers
// or corrupt the streamed file.
ob_start();

require_once('./config.php');
require_once('./models/DB.php');
require_once('./models/Autoload.php');

function _fv_fail(int $code, string $msg) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($code);
    exit($msg);
}

// Require login — no Referer bypass.
if (!isset($_SESSION[SESSION_NAME]['user_id'])) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit;
}
$user_id = (int) $_SESSION[SESSION_NAME]['user_id'];

$req = isset($_GET['file']) ? (string) $_GET['file'] : '';
if ($req === '') {
    _fv_fail(400, 'File not specified');
}

// Identity is the file NAME only — never a client-supplied path.
$name = basename(str_replace('\\', '/', $req));
if ($name === '' || $name === '.' || $name === '..') {
    _fv_fail(400, 'Invalid file');
}

// Authorize: find an archive whose stored path ends with this name AND whose
// sub-folder the user may access (NULL/0 = uncategorised, visible to any
// logged-in user — matches view_by_ref.php). 404 on no match to avoid probing.
$db       = new DB();
$likeName = '%/' . addcslashes($name, '%_\\');
$row = $db->query(
    "SELECT a.path
       FROM archives a
      WHERE a.path LIKE ?
        AND (
              a.sub_folder_id IS NULL
              OR a.sub_folder_id = 0
              OR a.sub_folder_id IN (
                  SELECT cfar.folder_sub_id
                    FROM config_folder_access_rights cfar
                   WHERE cfar.user_group IN (
                       SELECT ugr.user_group
                         FROM user_group_relation ugr
                        WHERE ugr.user = ?
                   )
              )
            )
      LIMIT 1",
    'ROW', true, [$likeName, $user_id]
);
if (!$row || empty($row['path'])) {
    _fv_fail(404, 'File not found');
}

// ── Resolve the DB-confirmed path to an absolute on-disk path ────────────────
$baseDir  = rtrim(FILES_PATH, '/');
$siteRoot = rtrim(__DIR__, '/');

function _fv_normalize(string $path): string {
    $parts = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $p) {
        if ($p === '..' && !empty($parts)) { array_pop($parts); }
        elseif ($p !== '.' && $p !== '')   { $parts[] = $p; }
    }
    return '/' . implode('/', $parts);
}

$allowedBases  = [$baseDir, $siteRoot . '/allfiles'];
$withinAllowed = function (string $p) use ($allowedBases): bool {
    foreach ($allowedBases as $base) {
        if (strncmp($p, $base . '/', strlen($base) + 1) === 0) return true;
    }
    return false;
};

$stored = $row['path'];
$candidates = [];
if ($stored[0] === '/') {
    $candidates[] = _fv_normalize($stored);
} else {
    $r = ltrim($stored, '/\\');
    $candidates[] = _fv_normalize($siteRoot . '/' . $r);
    $strip = preg_replace('#(\.\.[\\/])+#', '', $r);
    $strip = ltrim(preg_replace('#^allfiles[\\/]#i', '', $strip), '/');
    $candidates[] = _fv_normalize($baseDir . '/' . $strip);
    $candidates[] = _fv_normalize($baseDir . '/pf-archives/' . $strip);
}

$filePath = null;
foreach ($candidates as $c) {
    $norm = _fv_normalize($c);
    if (!$withinAllowed($norm) || !is_file($norm) || !is_readable($norm)) continue;
    $real = realpath($norm);
    if ($real !== false) {
        // realpath resolved — trust it (catches symlink escapes)
        if (!$withinAllowed($real)) continue;
        $filePath = $real;
    } elseif (!is_link($norm)) {
        // realpath unavailable (open_basedir); $norm is fully normalized (no ..)
        // and the target is not a symlink → safe to serve.
        $filePath = $norm;
    }
    if ($filePath !== null) break;
}

if ($filePath === null) {
    _fv_fail(404, 'File not found');
}

// ── Serve ────────────────────────────────────────────────────────────────────
$contentTypes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',  'jpeg' => 'image/jpeg',
    'png'  => 'image/png',   'gif'  => 'image/gif',
    'webp' => 'image/webp',
];
$ext         = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$contentType = $contentTypes[$ext] ?? 'application/octet-stream';

while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Cache-Control: private, max-age=86400');

readfile($filePath);
exit;
