<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', dirname(__FILE__) . '/backup_error.log');
include_once("./config.php");
$db = new DB();

// First check if table exists
$table_exists_query = "SHOW TABLES LIKE 'backup_history'";
$table_exists = $db->query($table_exists_query, 'SELECT');

if (empty($table_exists)) {
    // Create backup_history table if not exists
    $create_table_query = "CREATE TABLE backup_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        backup_type VARCHAR(50) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_size BIGINT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(50) DEFAULT 'processing',
        notes TEXT,
        progress INT DEFAULT 0
    )";

    // Use mysqli directly for DDL queries
    $db->query($create_table_query);
//    $mysqli->close();
}

class AsyncBackupHandler {
    private $db;
    private $backup_dir;
    private $upload_dir;

    public function __construct($db) {
        $this->db = $db;
        $this->backup_dir = dirname(__DIR__) . '/backups/';
        $this->upload_dir = dirname(__DIR__) . '/allfiles/pf-archives/';

        // Verify and create backup directory with proper permissions
        if (!file_exists($this->backup_dir)) {
            if (!mkdir($this->backup_dir, 0755, true)) {
                throw new Exception("Failed to create backup directory: " . $this->backup_dir);
            }
        }

        // Check directory permissions
        if (!is_writable($this->backup_dir)) {
            $currentPerms = substr(sprintf('%o', fileperms($this->backup_dir)), -4);
            throw new Exception("Backup directory is not writable. Current permissions: " . $currentPerms);
        }

        // Verify upload directory exists and is readable
        if (!file_exists($this->upload_dir) || !is_readable($this->upload_dir)) {
            throw new Exception("Upload directory is not accessible: " . $this->upload_dir);
        }
    }

    private function logError($message, $context = []) {
        $logMessage = date('Y-m-d H:i:s') . " - " . $message;
        if (!empty($context)) {
            $logMessage .= " - Context: " . json_encode($context);
        }
        error_log($logMessage . PHP_EOL, 3, dirname(__DIR__) . '/logs/backup_error.log');
    }


    public function initiateBackup($backup_type) {
        try {
            // Validate backup directory
            if (!is_dir($this->backup_dir) || !is_writable($this->backup_dir)) {
                throw new Exception("Backup directory is not writable: " . $this->backup_dir);
            }

            // Validate backup type
            if (!in_array($backup_type, ['database', 'files'])) {
                throw new Exception("Invalid backup type: " . $backup_type);
            }

            // Create unique backup name
            $backup_name = date('Y-m-d_H-i-s') . '_' . $backup_type . '_' . uniqid();

            $data = [
                'backup_type' => $backup_type,
                'file_name' => $backup_name . ($backup_type == 'database' ? '.sql' : '.zip'),
                'status' => 'processing',
                'notes' => 'Backup initiated',
                'progress' => 0
            ];

            $backup_id = Utility::insert('backup_history', $data);
            if (!$backup_id) {
                throw new Exception("Failed to create backup record");
            }

            // Create and start the background process
            $this->startBackgroundProcess($backup_type, $backup_id, $backup_name);

            return [
                'status' => 'initiated',
                'backup_id' => $backup_id,
                'message' => 'Backup process started successfully'
            ];
        } catch (Exception $e) {
            error_log("Backup initiation error: " . $e->getMessage());
            throw $e;
        }
    }

    private function startBackgroundProcess($backup_type, $backup_id, $backup_name) {
        $process_file = $this->createProcessFile($backup_type, $backup_id, $backup_name);

        if (substr(php_uname(), 0, 7) == "Windows") {
            pclose(popen("start /B " . PHP_BINARY . " " . $process_file, "r"));
        } else {
            exec(PHP_BINARY . " " . $process_file . " > /dev/null 2>&1 &");
        }
    }

    // Then modify your process code to include detailed logging
    private function createProcessFile($backup_type, $backup_id, $backup_name) {
        $process_file = $this->backup_dir . 'process_' . $backup_id . '.php';

        $code = '<?php
    set_time_limit(0);
    ini_set("memory_limit", "512M");
    error_log("Starting backup process ID: ' . $backup_id . '");
    
    include_once("./config.php");
    $db = new DB();
    
    try {
        error_log("Initialized backup process. Type: ' . $backup_type . ', ID: ' . $backup_id . '");
        
        ' . ($backup_type == 'database' ?
                $this->getDatabaseProcessCode($backup_id, $backup_name) :
                $this->getFilesProcessCode($backup_id, $backup_name)) . '
        
        error_log("Backup process completed successfully");
        unlink(__FILE__);
        
    } catch (Exception $e) {
        error_log("Critical backup error for ID ' . $backup_id . ': " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        $db->query("UPDATE backup_history SET 
            status = \'failed\', 
            notes = \'" . addslashes($e->getMessage()) . "\' 
            WHERE id = ' . $backup_id . '");
        
        if (file_exists(__FILE__)) {
            unlink(__FILE__);
        }
    }
    ?>';

        if (!file_put_contents($process_file, $code)) {
            throw new Exception("Failed to create process file: " . $process_file);
        }

        chmod($process_file, 0755);
        return $process_file;
    }

    private function getDatabaseProcessCode($backup_id, $backup_name) {
        return '
    $backup_path = "' . $this->backup_dir . $backup_name . '.sql";
    $tables = array();
    
    try {
        $db->query("UPDATE backup_history SET notes = \'Getting table list\' WHERE id = ' . $backup_id . '");
        
        // Add error handling for table list query
        $tables_result = $db->query("SHOW TABLES");
        if (!$tables_result) {
            throw new Exception("Failed to get table list");
        }
        
        while ($row = $tables_result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        $total_tables = count($tables);
        if ($total_tables === 0) {
            throw new Exception("No tables found to backup");
        }
        
        $current_table = 0;
        $output = "";
        
        foreach ($tables as $table) {
            $current_table++;
            $progress = round(($current_table / $total_tables) * 100);
            
            // Properly escape table name
            $escaped_table = "`" . str_replace("`", "``", $table) . "`";
            
            $db->query("UPDATE backup_history SET 
                notes = \'Processing table: " . $escaped_table . "\',
                progress = " . $progress . "
                WHERE id = ' . $backup_id . '");
            
            // Add error handling for table data query
            $result = $db->query("SELECT * FROM " . $escaped_table);
            if (!$result) {
                throw new Exception("Failed to read data from table: " . $table);
            }
            
            $num_fields = $result->field_count;
            
            // Create table structure with proper escaping
            $output .= "DROP TABLE IF EXISTS " . $escaped_table . ";\n";
            $create_table_result = $db->query("SHOW CREATE TABLE " . $escaped_table);
            if (!$create_table_result) {
                throw new Exception("Failed to get table structure for: " . $table);
            }
            
            $row2 = $create_table_result->fetch_row();
            $output .= "\n\n" . $row2[1] . ";\n\n";
            
            // Handle data rows with proper escaping
            while($row = $result->fetch_row()) {
                $output .= "INSERT INTO " . $escaped_table . " VALUES(";
                for($j = 0; $j < $num_fields; $j++) {
                    if ($j > 0) {
                        $output .= ", ";
                    }
                    if (isset($row[$j])) {
                        $value = str_replace(
                            array("\\\\", "\\0", "\\n", "\\r", "\\Z", "\\"", "\\\'"),
                            array("\\\\\\\\", "\\\\0", "\\\\n", "\\\\r", "\\\\Z", "\\\\"", "\\\\\'"),
                            $row[$j]
                        );
                        $output .= "\'" . $value . "\'";
                    } else {
                        $output .= "NULL";
                    }
                }
                $output .= ");\n";
            }
            $output .= "\n\n";
            
            // Write in chunks to avoid memory issues
            file_put_contents($backup_path, $output, FILE_APPEND);
            $output = ""; // Clear output after writing
        }
        
        $file_size = filesize($backup_path);
        if ($file_size === false) {
            throw new Exception("Failed to get backup file size");
        }
        
        $db->query("UPDATE backup_history SET 
            status = \'completed\', 
            notes = \'Backup completed successfully\',
            file_size = " . $file_size . ",
            progress = 100
            WHERE id = ' . $backup_id . '");
            
    } catch (Exception $e) {
        $db->query("UPDATE backup_history SET 
            status = \'failed\', 
            notes = \'" . addslashes($e->getMessage()) . "\' 
            WHERE id = ' . $backup_id . '");
        throw $e;
    }';
    }

// 2. Fix Files Backup Process
    private function getFilesProcessCode($backup_id, $backup_name) {
        return '
    $backup_path = "' . $this->backup_dir . $backup_name . '.zip";
    
    try {
        $zip = new ZipArchive();
        if ($zip->open($backup_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception("Cannot create zip file: " . $backup_path);
        }
        
        if (!is_dir("' . $this->upload_dir . '")) {
            throw new Exception("Upload directory does not exist: ' . $this->upload_dir . '");
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator("' . $this->upload_dir . '"),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        $total_files = iterator_count($iterator);
        if ($total_files === 0) {
            throw new Exception("No files found to backup");
        }
        
        // Reset iterator
        $iterator->rewind();
        $processed_files = 0;
        
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen("' . $this->upload_dir . '"));
                
                if (!is_readable($filePath)) {
                    throw new Exception("Cannot read file: " . $filePath);
                }
                
                if ($zip->addFile($filePath, $relativePath)) {
                    $processed_files++;
                    if ($processed_files % 10 == 0) {
                        $progress = round(($processed_files / $total_files) * 100);
                        $db->query("UPDATE backup_history SET 
                            notes = \'Processed " . $processed_files . " of " . $total_files . " files\',
                            progress = " . $progress . "
                            WHERE id = ' . $backup_id . '");
                    }
                } else {
                    throw new Exception("Failed to add file to zip: " . $filePath);
                }
            }
        }
        
        if (!$zip->close()) {
            throw new Exception("Failed to close zip file");
        }
        
        $file_size = filesize($backup_path);
        if ($file_size === false) {
            throw new Exception("Failed to get backup file size");
        }
        
        $db->query("UPDATE backup_history SET 
            status = \'completed\', 
            notes = \'Backup completed successfully\',
            file_size = " . $file_size . ",
            progress = 100
            WHERE id = ' . $backup_id . '");
            
    } catch (Exception $e) {
        if (isset($zip)) {
            $zip->close();
        }
        if (file_exists($backup_path)) {
            unlink($backup_path);
        }
        $db->query("UPDATE backup_history SET 
            status = \'failed\', 
            notes = \'" . addslashes($e->getMessage()) . "\' 
            WHERE id = ' . $backup_id . '");
        throw $e;
    }';
    }
}

// Handle backup requests
// Modify your backup request handler to include more detailed error information
if ($_POST['action'] ?? '' == 'backup') {
    header('Content-Type: application/json');

    try {
        if (empty($_POST['backup_type'])) {
            throw new Exception('Backup type not specified');
        }

        if (!in_array($_POST['backup_type'], ['database', 'files'])) {
            throw new Exception('Invalid backup type: ' . $_POST['backup_type']);
        }

        // Log the attempt
        error_log("Initiating backup of type: " . $_POST['backup_type']);

        $backup_handler = new AsyncBackupHandler($db);
        $result = $backup_handler->initiateBackup($_POST['backup_type']);

        // Log the result
        error_log("Backup initiated with result: " . json_encode($result));

        echo json_encode($result);
    } catch (Exception $e) {
        error_log("Backup error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Get backup history
$backup_history = $db->query("SELECT * FROM backup_history ORDER BY created_at DESC", 'SELECT');
if (!is_array($backup_history)) {
    $backup_history = [];
}

// Initialize user for role checking
$user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);
?>
<style>
    /* ===== Backup page polish (scoped to .backup-page) ===== */
    .backup-page { animation: bkIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both; }
    @keyframes bkIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }

    .bk-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin: 0.5rem 0 1.6rem; }
    .bk-title {
        font-family: var(--font-display, 'Bricolage Grotesque', sans-serif);
        font-weight: 800; font-size: 2rem; line-height: 1; letter-spacing: -0.02em; margin: 0;
        background: linear-gradient(100deg, #fff 35%, var(--light-orange, #ffb24d));
        -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    }
    .bk-sub { color: var(--text-muted, #9c9389); margin: 0.45rem 0 0; font-size: 0.95rem; }
    .bk-chip {
        display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.9rem; border-radius: 999px;
        background: rgba(240, 140, 0, 0.10); border: 1px solid var(--border-color, rgba(240,140,0,0.16));
        color: #f6efe5; font-size: 0.84rem; font-weight: 600;
    }
    .bk-chip i { color: var(--primary-orange, #f08c00); }

    /* Top backup cards — fix the clipped-button bug (stats-card forces height:160px) */
    .backup-page .row.mb-4 > .col-md-6 { display: flex; }
    .backup-page .stats-card { height: auto !important; display: block !important; width: 100%; }
    .backup-page .stats-card .card-body { padding: 2.2rem 1.5rem; }
    .backup-page .backup-icon {
        width: 64px; height: 64px; display: grid; place-items: center; margin: 0 auto 1.1rem;
        border-radius: 18px; font-size: 1.7rem;
        background: rgba(240, 140, 0, 0.12); color: var(--primary-orange, #f08c00);
        border: 1px solid var(--border-color, rgba(240,140,0,0.2)); -webkit-text-fill-color: var(--primary-orange);
    }
    .backup-page .stats-card .card-title { font-family: var(--font-display, 'Bricolage Grotesque', sans-serif); font-weight: 700; font-size: 1.25rem; margin-bottom: 0.4rem; }
    .backup-page .stats-card .card-text { color: var(--text-muted, #9c9389); font-size: 0.92rem; margin-bottom: 1.3rem; }
    .backup-page .stats-card .btn-primary {
        display: inline-flex; align-items: center; gap: 0.5rem; width: auto;
        padding: 0.7rem 1.6rem; border-radius: 12px; font-weight: 700;
        box-shadow: 0 10px 24px -10px rgba(240, 140, 0, 0.6);
    }

    /* History table */
    .backup-page .table { color: #e7e0d6; margin: 0; }
    .backup-page .table thead th {
        color: var(--light-orange, #ffb24d); font-weight: 700; font-size: 0.76rem;
        text-transform: uppercase; letter-spacing: 0.07em;
        border-bottom: 1px solid var(--border-color, rgba(240,140,0,0.2)); padding: 0.85rem 0.8rem;
    }
    .backup-page .table tbody td { border-color: rgba(255, 255, 255, 0.05); padding: 0.85rem 0.8rem; vertical-align: middle; }
    .backup-page .table tbody tr { transition: background 0.2s ease; }
    .backup-page .table tbody tr:hover { background: rgba(240, 140, 0, 0.05); }
    .backup-page .table td:nth-child(3) { font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace; font-size: 0.82rem; color: #d7cfc4; word-break: break-all; }

    /* Badges */
    .backup-page .badge { font-weight: 700; font-size: 0.72rem; padding: 0.4rem 0.7rem; border-radius: 999px; letter-spacing: 0.02em; }
    .backup-page .badge.bg-primary { background: rgba(240, 140, 0, 0.16) !important; color: var(--light-orange, #ffb24d) !important; border: 1px solid var(--border-color, rgba(240,140,0,0.2)); }
    .backup-page .badge.status-completed { background: rgba(52, 211, 153, 0.15); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
    .backup-page .badge.status-processing { background: rgba(240, 140, 0, 0.16); color: var(--light-orange, #ffb24d); border: 1px solid var(--border-color, rgba(240,140,0,0.2)); }
    .backup-page .badge.status-failed { background: rgba(255, 107, 93, 0.16); color: #ff7a66; border: 1px solid rgba(255, 107, 93, 0.3); }

    /* Progress bar → amber */
    .backup-page .progress { background: rgba(255, 255, 255, 0.06); border-radius: 999px; height: 18px; overflow: hidden; min-width: 90px; }
    .backup-page .progress-bar {
        background: linear-gradient(90deg, var(--light-orange, #ffb24d), var(--dark-orange, #d97706));
        color: #241400; font-weight: 700; font-size: 0.7rem; display: flex; align-items: center; justify-content: center;
    }

    /* Action buttons */
    .backup-page td .btn-sm { width: 34px; height: 34px; padding: 0; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; }
    .backup-page .btn-info { background: rgba(255, 255, 255, 0.06) !important; color: var(--text-muted, #9c9389) !important; border: 1px solid var(--border-color, rgba(240,140,0,0.2)) !important; }
    .backup-page .btn-info:hover { background: rgba(240, 140, 0, 0.16) !important; color: #fff !important; border-color: var(--primary-orange, #f08c00) !important; }
</style>

<div class="container backup-page">
    <div class="bk-head">
        <div>
            <h1 class="bk-title">Backup Management</h1>
            <p class="bk-sub">Create and download database and file backups.</p>
        </div>
        <span class="bk-chip"><i class="fas fa-history"></i> <?= number_format(count($backup_history)) ?> backups</span>
    </div>
    <div class="toast-container"></div>

    <!-- Backup Options -->
    <div class="row mb-4">
        <!-- Original database backup card -->
        <div class="col-md-6">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <i class="fas fa-database backup-icon mb-3"></i>
                    <h5 class="card-title">Database Backup</h5>
                    <p class="card-text">Create a complete backup of your database</p>
                    <?php if ($user->can('BACKUP_DATABASE')){ ?>
                    <form method="post" class="mt-3 backupForm">
                        <input type="hidden" name="action" value="backup">
                        <input type="hidden" name="backup_type" value="database">
                        <button type="submit" class="btn btn-primary" name="backup">
                            <i class="fas fa-download me-2"></i>Backup Database
                        </button>
                    </form>
                    <?php } else { ?>
                    <p class="text-muted">You don't have permission to create backups</p>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Original files backup card -->
        <div class="col-md-6">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <i class="fas fa-file-archive backup-icon mb-3"></i>
                    <h5 class="card-title">Files Backup</h5>
                    <p class="card-text">Backup all uploaded files and documents</p>
                    <?php if ($user->can('BACKUP_DATABASE')){ ?>
                    <form method="post" class="mt-3 backupForm">
                        <input type="hidden" name="action" value="backup">
                        <input type="hidden" name="backup_type" value="files">
                        <button type="submit" class="btn btn-primary" name="backup">
                            <i class="fas fa-download me-2"></i>Backup Files
                        </button>
                    </form>
                    <?php } else { ?>
                    <p class="text-muted">You don't have permission to create backups</p>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
<!--    -->







<!--    -->

    <!-- Backup History -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-history me-2"></i>Backup History
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>File Name</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($backup_history as $backup): ?>
                        <tr id="backup-row-<?=$backup['id']?>">
                            <td><?=date('Y-m-d H:i:s', strtotime($backup['created_at']))?></td>
                            <td>
                                        <span class="badge bg-primary">
                                            <?=ucfirst($backup['backup_type'])?>
                                        </span>
                            </td>
                            <td><?=$backup['file_name']?></td>
                            <td><?=formatBytes($backup['file_size'])?></td>
                            <td>
                                        <span class="badge status-<?=$backup['status']?>">
                                            <?=ucfirst($backup['status'])?>
                                        </span>
                            </td>
                            <td>
                                <?php if ($backup['status'] == 'processing'): ?>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar"
                                             style="width: <?=$backup['progress']?>%"
                                             aria-valuenow="<?=$backup['progress']?>"
                                             aria-valuemin="0"
                                             aria-valuemax="100">
                                            <?=$backup['progress']?>%
                                        </div>
                                    </div>
                                    <small class="text-muted" id="backup-status-<?=$backup['id']?>">
                                        <?=$backup['notes']?>
                                    </small>
                                <?php else: ?>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar"
                                             style="width: 100%"
                                             aria-valuenow="100"
                                             aria-valuemin="0"
                                             aria-valuemax="100">
                                            100%
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($backup['status'] == 'completed'): ?>
                                    <a href="../backups/<?=$backup['file_name']?>"
                                       class="btn btn-sm btn-primary me-1"
                                       download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                <?php endif; ?>

                                <button type="button"
                                        class="btn btn-sm btn-info check-status-btn"
                                        onclick="checkBackupStatus(<?=$backup['id']?>, this)"
                                        title="Check Status">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Check Backup Status API -->
<?php
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

<script>
    // Toast notification system
    const Toast = {
        init() {
            this.container = document.querySelector('.toast-container');
        },

        show(message, type = 'info') {
            const toastEl = document.createElement('div');
            toastEl.className = `toast show`;
            toastEl.innerHTML = `
            <div class="toast-header">
                <strong class="me-auto">Backup Status</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        `;

            this.container.appendChild(toastEl);

            // Initialize Bootstrap toast
            const bsToast = new bootstrap.Toast(toastEl, {
                autohide: true,
                delay: 5000
            });

            bsToast.show();

            // Remove toast after it's hidden
            toastEl.addEventListener('hidden.bs.toast', () => {
                toastEl.remove();
            });
        }
    };

    // Initialize toast
    Toast.init();

    // Handle form submission

    // Check backup status
    document.querySelectorAll('.backupForm').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            try {
                const formData = new FormData(this);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'initiated') {
                    // Display only the message from the JSON response
                    Toast.show(data.message);
                    // Start checking status
                    checkBackupStatus(data.backup_id);
                } else {
                    Toast.show(data.message || 'Backup failed to start', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                Toast.show('An error occurred while processing the backup', 'error');
            } finally {
                submitButton.disabled = false;
            }
        });
    });

    // Handle form submission
    // Replace your existing form submission JavaScript with this:
    document.querySelectorAll('.backupForm').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            try {
                const formData = new FormData(this);

                // Log what we're sending
                console.log('Sending backup request for type:', formData.get('backup_type'));

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                // Log the raw response
                const responseText = await response.text();
                console.log('Raw server response:', responseText);

                // Try to parse as JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Failed to parse server response as JSON:', parseError);
                    throw new Error('Server response was not valid JSON: ' + responseText);
                }

                if (data.status === 'initiated') {
                    Toast.show('Backup Started', 'info', 'Backup process has been initiated');
                    // Start checking status
                    checkBackupStatus(data.backup_id);
                } else {
                    Toast.show('Error', 'error', data.message || 'Backup failed to start');
                }
            } catch (error) {
                console.error('Full error details:', error);
                Toast.show('Error', 'error', error.message);
            } finally {
                submitButton.disabled = false;
            }
        });
    });

    // Check for any processing backups on page load
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[id^="backup-row-"]').forEach(row => {
            const backupId = row.id.replace('backup-row-', '');
            const status = row.querySelector('.status-badge').textContent.trim().toLowerCase();

            if (status === 'processing') {
                checkBackupStatus(backupId);
            }
        });
    });
    function checkBackupStatus(backupId, buttonElement = null) {
        const statusChecks = {};

        // If this is a manual check (button click), clear any existing interval
        if (buttonElement && statusChecks[backupId]) {
            clearInterval(statusChecks[backupId].interval);
            delete statusChecks[backupId];
        }

        if (!statusChecks[backupId]) {
            statusChecks[backupId] = {
                interval: null,
                failedAttempts: 0,
                maxAttempts: 60  // 5 minutes (with 5-second intervals)
            };
        }

        async function updateStatus() {
            // If a button was clicked, show loading state
            if (buttonElement) {
                buttonElement.disabled = true;
                buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            try {
                const response = await fetch(`check-backup-status.php?id=${backupId}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to get backup status');
                }

                const backup = data.data;
                const row = document.getElementById(`backup-row-${backupId}`);

                if (row) {
                    // Update status badge
                    const statusBadge = row.querySelector('.badge');
                    if (statusBadge) {
                        statusBadge.className = `badge status-${backup.status}`;
                        statusBadge.textContent = backup.status.charAt(0).toUpperCase() + backup.status.slice(1);
                    }

                    // Update progress bar
                    const progressBar = row.querySelector('.progress-bar');
                    if (progressBar) {
                        progressBar.style.width = `${backup.progress}%`;
                        progressBar.setAttribute('aria-valuenow', backup.progress);
                        progressBar.textContent = `${backup.progress}%`;
                    }

                    // Update notes
                    const notesElement = document.getElementById(`backup-status-${backupId}`);
                    if (notesElement) {
                        notesElement.textContent = backup.notes;
                    }

                    // Handle completion or failure
                    if (backup.status === 'completed' || backup.status === 'failed') {
                        if (statusChecks[backupId]) {
                            clearInterval(statusChecks[backupId].interval);
                            delete statusChecks[backupId];
                        }

                        if (backup.status === 'completed') {
                            Toast.show('Backup completed successfully');
                            location.reload(); // Refresh to show download button
                        } else {
                            Toast.show(`Backup failed: ${backup.notes}`, 'error');
                        }
                    }
                }
            } catch (error) {
                console.error('Status check error:', error);
                Toast.show('Failed to check backup status: ' + error.message, 'error');

                if (statusChecks[backupId]) {
                    statusChecks[backupId].failedAttempts++;

                    if (statusChecks[backupId].failedAttempts >= statusChecks[backupId].maxAttempts) {
                        clearInterval(statusChecks[backupId].interval);
                        delete statusChecks[backupId];
                    }
                }
            } finally {
                // Reset button state if it exists
                if (buttonElement) {
                    buttonElement.disabled = false;
                    buttonElement.innerHTML = '<i class="fas fa-sync-alt"></i>';
                }
            }
        }

        // Initial check
        updateStatus();

        // Only set up interval if this wasn't triggered by a button click
        if (!buttonElement && !statusChecks[backupId].interval) {
            statusChecks[backupId].interval = setInterval(updateStatus, 5000); // Check every 5 seconds
        }
    }
</script>