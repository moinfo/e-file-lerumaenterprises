<?php
/**
 * Secure File Server
 * Serves files from outside the web root (or via symlink on restricted hosts).
 *
 * NOTE: realpath() is intentionally NOT used for security checks here.
 * On cPanel with open_basedir, allfiles/ is a symlink whose canonical path
 * falls outside the basedir — realpath() returns false even for accessible files.
 * We use string-normalized paths + file_exists() instead.
 */

// Buffer any output produced while loading config/secrets (e.g. a stray BOM or
// blank line in efile_secrets.php). We discard it before sending headers so the
// binary file stream and its Content-Type are never corrupted by leading bytes.
ob_start();
require_once('./config.php');

$requestedPath = isset($_GET['file']) ? urldecode($_GET['file']) : '';

if (empty($requestedPath)) {
    http_response_code(400);
    die('No file specified');
}

$baseDir  = FILES_PATH;
$siteRoot = rtrim(__DIR__, '/');

// Resolve .. / . without touching the filesystem (safe under open_basedir).
function _normalizePath(string $path): string {
    $parts = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $p) {
        if ($p === '..' && !empty($parts)) { array_pop($parts); }
        elseif ($p !== '.' && $p !== '')   { $parts[] = $p; }
    }
    return '/' . implode('/', $parts);
}

// Allowed base directories — literal strings, NOT filtered by is_dir().
// open_basedir blocks is_dir() on the directory itself (the path is shorter than
// the basedir prefix entry), but file_exists() on files WITHIN the dir works fine.
// Security is enforced by $withinAllowed (string prefix + '/' guard) + file_exists().
$allowedBases = [
    rtrim($baseDir, '/'),        // /home/lerumaen/allfiles
    $siteRoot . '/allfiles',     // /home/lerumaen/e-file.../allfiles (legacy / symlink)
];

// Check whether a normalized absolute path is inside any allowed base.
$withinAllowed = function (string $p) use ($allowedBases): bool {
    foreach ($allowedBases as $base) {
        if (strncmp($p, $base . '/', strlen($base) + 1) === 0) return true;
    }
    return false;
};

// ── Build candidate paths (all strategies) then pick the first that exists ───
//
// Stored paths can be:
//   /home/lerumaen/allfiles/pf-archives/file.pdf  (absolute — ingest API)
//   ../allfiles/pf-archives/file.pdf              (relative — old sync)
//   pf-archives/file.pdf                          (relative to FILES_PATH)

$candidates = [];

if ($requestedPath[0] === '/') {
    $candidates[] = _normalizePath($requestedPath);
} else {
    $req = ltrim($requestedPath, '/\\');

    // Candidate 1: relative to site root
    //   ../allfiles/pf-archives/f → /home/lerumaen/allfiles/pf-archives/f
    $candidates[] = _normalizePath($siteRoot . '/' . $req);

    // Candidate 2: ../allfiles/ → allfiles/ within the site root
    //   handles old deployments where CWD was pages/ subdirectory
    if (strpos($req, '../allfiles/') !== false) {
        $req2        = str_replace('../allfiles/', 'allfiles/', $req);
        $candidates[] = _normalizePath($siteRoot . '/' . $req2);
    }

    // Candidate 3: strip traversal + allfiles/ prefix, resolve against FILES_PATH.
    // MUST be normalized like the others — a single regex pass can leave a "../"
    // behind (e.g. "....//" → "../"), so we collapse it with _normalizePath
    // before the containment check rather than trusting the stripped string.
    $stripped     = preg_replace('#(\.\.[\\/])+#', '', $req);
    $stripped     = ltrim(preg_replace('#^allfiles[\\/]#i', '', $stripped), '/');
    $candidates[] = _normalizePath(rtrim($baseDir, '/') . '/' . $stripped);
}

// Pick the first candidate that is (a) fully normalized & within an allowed base,
// (b) exists on disk, AND (c) whose realpath (if resolvable) is STILL within the
// base — the realpath pass catches symlink escapes that string normalization can't.
$filePath = null;
foreach ($candidates as $c) {
    $norm = _normalizePath($c);                 // defensive: normalize every candidate
    if (!$withinAllowed($norm)) continue;
    if (!file_exists($norm) || !is_readable($norm)) continue;
    $real = realpath($norm);                    // may be false under open_basedir
    if ($real !== false && !$withinAllowed($real)) continue;  // symlink escape — reject
    $filePath = $norm;
    break;
}

// ── Security gate ────────────────────────────────────────────────────────────
if ($filePath === null) {
    $anyAllowed = array_filter($candidates, $withinAllowed);
    if (empty($anyAllowed)) {
        http_response_code(403);
        error_log("serve_file: no candidate within allowed bases — tried: " . implode(', ', $candidates));
        die('Access denied');
    }
    http_response_code(404);
    error_log("serve_file: file not found in any location — tried: " . implode(', ', $candidates));
    die('File not found');
}

// ── MIME type ────────────────────────────────────────────────────────────────
$fileName = basename($filePath);
$extMap = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'txt'  => 'text/plain',
    'csv'  => 'text/csv',
];
$ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$mimeType = $extMap[$ext] ?? null;
if (!$mimeType) {
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath) ?: 'application/octet-stream';
    finfo_close($finfo);
}

// ── Serve ────────────────────────────────────────────────────────────────────
$fileSize = filesize($filePath);

// Discard any buffered output (stray BOM/whitespace from includes) so the
// response body is exactly the file bytes and the Content-Type sticks.
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Cache-Control: public, max-age=3600');
header('Accept-Ranges: bytes');

if (isset($_SERVER['HTTP_RANGE'])) {
    $range    = str_replace('bytes=', '', $_SERVER['HTTP_RANGE']);
    $range    = explode('-', $range);
    $start    = (int) $range[0];
    $end      = isset($range[1]) && $range[1] !== '' ? (int) $range[1] : $fileSize - 1;

    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    header('Content-Length: ' . ($end - $start + 1));

    $fp        = fopen($filePath, 'rb');
    $bytesLeft = $end - $start + 1;
    fseek($fp, $start);
    while ($bytesLeft > 0 && !feof($fp)) {
        $read       = min(8192, $bytesLeft);
        echo fread($fp, $read);
        $bytesLeft -= $read;
        flush();
    }
    fclose($fp);
} else {
    readfile($filePath);
}

exit;
