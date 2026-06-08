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

$ref     = isset($_GET['ref']) ? trim($_GET['ref']) : '';
$user_id = (int) $_SESSION[SESSION_NAME]['user_id'];

if ($ref === '') {
    http_response_code(400);
    exit('No ref specified');
}

$db  = new DB();

// Scope to sub-folders the user's group(s) can access. Uncategorised archives
// (sub_folder_id NULL/0 — e.g. freshly ingested files not yet sorted) are
// visible to any logged-in user. Returns 404 either way — no 403 — to avoid
// ref enumeration.
//
// NOTE: the user_group_relation table columns are `user` and `user_group`
// (NOT user_id/group_id), and config_folder_access_rights uses `user_group`
// and `folder_sub_id` — matching pages/folders.php and pages/document_types.php.
$row = $db->query(
    "SELECT a.path
       FROM external_file_refs efr
       JOIN archives a ON a.id = efr.archive_id
      WHERE efr.external_ref_id = ?
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
    'ROW', true, [$ref, $user_id]
);

if (!$row) {
    http_response_code(404);
    exit('Not found');
}

// serve_file.php handles FILES_PATH resolution for all file locations.
header('Location: serve_file.php?file=' . urlencode($row['path']));
exit;
