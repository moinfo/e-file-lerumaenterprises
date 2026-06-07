<?php
/**
 * Application configuration.
 *
 * The environment (local vs. production) is detected AUTOMATICALLY, so this file
 * needs no edits when moving between your machine and the live server.
 *
 * Detection order:
 *   1. APP_ENV environment variable, if set to "local" or "production" (explicit override).
 *   2. Presence of the live server's home directory (/home/lerumaen/public_html) => production.
 *   3. A localhost/127.0.0.1 HTTP host => local.
 *   4. Otherwise default to local (safe for CLI scripts on dev machines).
 */

define('DB_HOSTNAME', 'localhost');

// ---------------------------------------------------------------------------
// Environment detection
// ---------------------------------------------------------------------------
$__prod_home   = '/home/lerumaen/public_html';      // unique to the live cPanel account
$__http_host   = $_SERVER['HTTP_HOST'] ?? '';
$__env_override = strtolower((string) getenv('APP_ENV'));

if ($__env_override === 'local' || $__env_override === 'production') {
    $__is_production = ($__env_override === 'production');
} elseif (is_dir($__prod_home)) {
    $__is_production = true;                          // running on the live server
} elseif (stripos($__http_host, 'localhost') !== false || strpos($__http_host, '127.0.0.1') !== false) {
    $__is_production = false;                         // local web request
} else {
    $__is_production = false;                         // default (e.g. local CLI scripts)
}

define('APP_ENV', $__is_production ? 'production' : 'local');

// ---------------------------------------------------------------------------
// Error display: verbose locally, silent in production (errors still logged).
// Centralized here so every entry point (index.php, login.php, ajax.php, API)
// inherits the right behavior from the detected environment.
// ---------------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('log_errors', '1');
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

// ---------------------------------------------------------------------------
// Secrets (passwords) are loaded from a file OUTSIDE the web root — never
// committed to the codebase. Resolution order for each secret:
//   1. Environment variable (e.g. EFILE_DB_PASS).
//   2. The secrets file, keyed by environment.
// The secrets file returns ['production' => [...], 'local' => [...]].
// See efile_secrets.example.php for the structure and where to put it.
// ---------------------------------------------------------------------------
$__secret_candidates = array_filter([
    getenv('EFILE_SECRETS_FILE') ?: null,        // explicit override
    '/home/lerumaen/efile_secrets.php',          // production: one level above public_html
    dirname(__DIR__) . '/efile_secrets.php',     // local: one level above the project dir
]);
$__secrets = [];
foreach ($__secret_candidates as $__cand) {
    if (is_file($__cand)) {
        $__loaded = require $__cand;
        if (is_array($__loaded)) { $__secrets = $__loaded; }
        break;
    }
}
$__env_secrets = $__secrets[APP_ENV] ?? [];

// env var wins, then the secrets file, then the supplied default.
$__secret = function ($key, $env_var, $default = null) use ($__env_secrets) {
    $v = getenv($env_var);
    if ($v !== false && $v !== '') { return $v; }
    return $__env_secrets[$key] ?? $default;
};

// ---------------------------------------------------------------------------
// Per-environment settings. Non-secret structure (usernames, db names, paths)
// has defaults here; the PASSWORD is required and must come from secrets/env.
// ---------------------------------------------------------------------------
if (APP_ENV === 'production') {
    define('DB_USERNAME', $__secret('db_user', 'EFILE_DB_USER', 'lerumaen_muddy'));
    define('DB_NAME',     $__secret('db_name', 'EFILE_DB_NAME', 'lerumaen_filebridge'));
    define('BASE_URL',    'https://e-file.lerumaenterprises.co.tz/');
    define('FILES_PATH',  '/home/lerumaen/allfiles/');
} else {
    define('DB_USERNAME', $__secret('db_user', 'EFILE_DB_USER', 'root'));
    define('DB_NAME',     $__secret('db_name', 'EFILE_DB_NAME', 'lerumaen_filebridges'));

    // Derive BASE_URL from the current request so it works on any port
    // (e.g. http://localhost:8000/) or under a subfolder. Falls back for CLI.
    $__scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $__host   = $__http_host !== '' ? $__http_host : 'localhost:8000';
    define('BASE_URL', $__scheme . '://' . $__host . '/');

    // Local file storage lives one level above the project (../allfiles/)
    define('FILES_PATH', dirname(__DIR__) . '/allfiles/');
}

// The DB password is a true secret — fail loudly if it isn't configured,
// rather than silently attempting a blank-password connection.
$__db_pass = $__secret('db_pass', 'EFILE_DB_PASS');
if ($__db_pass === null) {
    error_log('config.php: database password not configured (missing secrets file / env var).');
    http_response_code(500);
    die('Configuration error: application secrets are not set up. See efile_secrets.example.php.');
}
define('DB_PASSWORD', $__db_pass);

// Shared secret for the API upload-synchronization endpoint (api/v1/endpoints/uploads.php).
define('SYNC_PASSWORD', (string) $__secret('sync_password', 'EFILE_SYNC_PASSWORD', ''));

define('SESSION_NAME', MD5('BRIDGE'));
