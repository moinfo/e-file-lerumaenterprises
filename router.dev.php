<?php
/**
 * Dev router for PHP's built-in server (`php -S`), emulating the Apache .htaccess rewrites.
 * NOT for production — Apache + the real .htaccess files handle routing there.
 */
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = __DIR__;
$file = realpath($root . $uri);

// --- Containment: never serve anything resolving outside the project root ---
if ($file !== false && $file !== $root
    && strncmp($file, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) !== 0) {
    http_response_code(403);
    echo '403 Forbidden';
    return true;
}

// --- Deny sensitive files (mirrors root .htaccess FilesMatch) and diagnostics ---
$basename = basename($uri);
if (preg_match('/\.(env|sql|log|htaccess|disabled)$/i', $basename)
    || strcasecmp($basename, 'config.php') === 0
    || preg_match('/secrets.*\.php$/i', $basename)
    || preg_match('#^/backups/#i', $uri)
    || preg_match('/^(test|debug|check)[_.].*\.php$/i', $basename)) {
    http_response_code(403);
    echo '403 Forbidden';
    return true;
}

// Serve existing real files directly (assets, node_modules, uploads, etc.)
if ($uri !== '/' && $file && is_file($file)) {
    return false;
}

// API v1: path-based routing -> api/v1/index.php
// chdir so the endpoint's relative requires ('../../config.php') resolve,
// matching Apache's `RewriteBase /api/v1/`.
if (preg_match('#^/api/v1/#', $uri)) {
    chdir($root . '/api/v1');
    require $root . '/api/v1/index.php';
    return true;
}

// Everything else falls through to the web entry point
require $root . '/index.php';
return true;
