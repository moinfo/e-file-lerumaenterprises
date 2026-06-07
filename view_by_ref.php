<?php
/**
 * View a document by its external_ref_id (e.g. receiving-xxx or expense-xxx).
 * Called from Mainstore "View" links: view_by_ref.php?ref={efile_ref}
 * Requires e-file login. Redirects to file_viewer.php for inline preview.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once './config.php';
require_once './models/DB.php';
require_once './models/Autoload.php';

if (!isset($_SESSION[SESSION_NAME]['user_id'])) {
    header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$ref = isset($_GET['ref']) ? trim($_GET['ref']) : '';
if ($ref === '') {
    http_response_code(400);
    exit('No ref specified');
}

$db  = new DB();
$row = $db->query(
    "SELECT a.path, efr.archive_id
       FROM external_file_refs efr
       JOIN archives a ON a.id = efr.archive_id
      WHERE efr.external_ref_id = ?
      LIMIT 1",
    'ROW', true, [$ref]
);

if (!$row) {
    http_response_code(404);
    exit('Document not found for ref: ' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'));
}

header('Location: file_viewer.php?file=' . urlencode(basename($row['path'])));
exit;
