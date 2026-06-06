<?php
/**
 * Test routing to diagnose 404 issue
 */

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$baseApiPath = '/api/v1/';

// Parse endpoint
$endpoint = str_replace($baseApiPath, '', parse_url($requestUri, PHP_URL_PATH));
$endpoint = trim($endpoint, '/');
$parts = explode('/', $endpoint);

$resource = $parts[0] ?? null;
$id = $parts[1] ?? null;
$action = $parts[2] ?? null;

echo json_encode([
    'test' => 'Routing diagnostic',
    'raw_request_uri' => $requestUri,
    'parsed_url_path' => parse_url($requestUri, PHP_URL_PATH),
    'base_api_path' => $baseApiPath,
    'endpoint_after_replace' => $endpoint,
    'parts' => $parts,
    'parsed' => [
        'resource' => $resource,
        'id' => $id,
        'action' => $action
    ],
    'would_route_to' => $resource === 'files' ? 'endpoints/files.php' : 'other endpoint',
    'function_call' => $resource === 'files' ? "handleFiles('$method', '$id', '$action', \$input)" : 'other'
], JSON_PRETTY_PRINT);
