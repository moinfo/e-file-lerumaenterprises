<?php
/**
 * Test if Authorization header is being received
 */

header('Content-Type: text/plain');

echo "=== AUTHORIZATION HEADER TEST ===\n\n";

echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET') . "\n";
echo "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'NOT SET') . "\n\n";

echo "=== ALL HEADERS ===\n";
$headers = getallheaders();
foreach ($headers as $key => $value) {
    echo "$key: $value\n";
}

echo "\n=== AUTHORIZATION HEADER CHECKS ===\n";
echo "Authorization (getallheaders): " . ($headers['Authorization'] ?? 'NOT SET') . "\n";
echo "authorization (lowercase): " . ($headers['authorization'] ?? 'NOT SET') . "\n";
echo "HTTP_AUTHORIZATION: " . ($_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT SET') . "\n";
echo "REDIRECT_HTTP_AUTHORIZATION: " . ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'NOT SET') . "\n\n";

echo "=== PHP AUTH VARIABLES ===\n";
echo "PHP_AUTH_USER: " . ($_SERVER['PHP_AUTH_USER'] ?? 'NOT SET') . "\n";
echo "PHP_AUTH_PW: " . ($_SERVER['PHP_AUTH_PW'] ?? 'NOT SET') . "\n\n";

echo "=== CGI/FASTCGI ===\n";
if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    echo "Using REDIRECT_HTTP_AUTHORIZATION\n";
    echo "Value: " . $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] . "\n";
} else {
    echo "REDIRECT_HTTP_AUTHORIZATION not set\n";
}

echo "\n=== RECOMMENDATION ===\n";
if (isset($headers['Authorization']) || isset($headers['authorization'])) {
    echo "✓ Authorization header is being passed correctly!\n";
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    echo "✓ Authorization header in HTTP_AUTHORIZATION\n";
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    echo "⚠ Authorization header in REDIRECT_HTTP_AUTHORIZATION\n";
    echo "  Need to update ApiAuth to check this variable\n";
} else {
    echo "✗ Authorization header NOT found!\n";
    echo "  .htaccess may not be passing the header correctly\n";
}
