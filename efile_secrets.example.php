<?php
/**
 * SECRETS TEMPLATE — copy this file, fill in real values, and place the copy
 * OUTSIDE the web root so it can never be served over HTTP.
 *
 * Where to put the real file (config.php looks here, in order):
 *   1. Path in the EFILE_SECRETS_FILE environment variable, if set.
 *   2. Production:  /home/lerumaen/efile_secrets.php      (one level above public_html)
 *   3. Local dev:   <one level above the project folder>/efile_secrets.php
 *
 * Do NOT commit the filled-in file. Individual values can also be supplied via
 * environment variables, which take precedence over this file:
 *   EFILE_DB_USER, EFILE_DB_PASS, EFILE_DB_NAME, EFILE_SYNC_PASSWORD
 *
 * This .example file contains placeholders only and is safe to keep in the repo.
 */

return [
    'production' => [
        'db_user'       => 'lerumaen_muddy',
        'db_pass'       => 'CHANGE_ME_production_db_password',
        'db_name'       => 'lerumaen_filebridge',
        'sync_password' => 'CHANGE_ME_strong_sync_password',
    ],
    'local' => [
        'db_user'       => 'root',
        'db_pass'       => 'CHANGE_ME_local_db_password',
        'db_name'       => 'lerumaen_filebridges',
        'sync_password' => 'CHANGE_ME_local_sync_password',
    ],
];
