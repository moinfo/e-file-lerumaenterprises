<?php
ob_start();
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once ("../config.php");
require_once ("../models/DB.php");

$db = new DB();
$user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = $db->query("SELECT * FROM users WHERE id='{$user_id}'", 'SELECT', 1);

// Base query
$q = "SELECT a.*, 
    u.username as editor,
    dt.name as document_type_name,
    f.name as folder_name,
    sf.name as sub_folder_name
    FROM archives a 
    LEFT JOIN users u ON (u.id = a.edited_by)
    LEFT JOIN document_types dt ON (dt.id = a.document_type)
    LEFT JOIN archive_document_folders f ON (f.id = a.document_type)
    LEFT JOIN archive_document_sub_folders sf ON (sf.id = a.sub_folder_id)";

// Add WHERE clause based on filter
$filter = $_GET['filter'] ?? 'all';
switch ($filter) {
    case 'pending':
        $q .= " WHERE a.completed = 0";
        break;
    case 'completed':
        $q .= " WHERE a.completed = 1";
        break;
    case 'rpf':
        $q .= " WHERE a.name LIKE 'RPF%'";
        break;
    default:
        // 'all' filter - no additional where clause needed
        break;
}

// Add final ordering
$q .= " ORDER BY a.id DESC";

$pf_files = $db->query($q, 'SELECT');

// Get the offset and limit from GET parameters
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 24;

// Get the next batch of files
$next_files = array_slice($pf_files, $offset, $limit);

// Generate and return the HTML for the new files
foreach ($next_files as $file): ?>
    <div class="file-card">
        <?php if ($file['completed'] === '1'): ?>
            <div class="completion-badge" title="Edited by: <?php echo htmlspecialchars($file['editor']); ?>">
                <i class="fas fa-check"></i>
            </div>
        <?php endif; ?>

        <div class="file-header">
            <i class="fas fa-file-pdf file-icon"></i>
            <div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
        </div>

        <div class="file-info">
            <?php if ($file['document_type_name']): ?>
                <div class="info-item">
                    <span class="info-label">Type:</span>
                    <span class="info-value"><?php echo htmlspecialchars($file['document_type_name']); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($file['folder_name']): ?>
                <div class="info-item">
                    <span class="info-label">Folder:</span>
                    <span class="info-value"><?php echo htmlspecialchars($file['folder_name']); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($file['sub_folder_name']): ?>
                <div class="info-item">
                    <span class="info-label">Sub-folder:</span>
                    <span class="info-value"><?php echo htmlspecialchars($file['sub_folder_name']); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($file['description']): ?>
                <div class="info-item">
                    <span class="info-label">Description:</span>
                    <span class="info-value"><?php echo htmlspecialchars($file['description']); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($file['year']): ?>
                <div class="info-item">
                    <span class="info-label">Year:</span>
                    <span class="info-value"><?php echo htmlspecialchars($file['year']); ?></span>
                </div>
            <?php endif; ?>

            <div class="info-item">
                <span class="info-label">Size:</span>
                <span class="info-value"><?php echo number_format($file['size'] / 1024, 2) . ' KB'; ?></span>
            </div>
        </div>

        <div class="file-actions">
            <a href="#" class="btn-custom view-pdf" data-path="<?php echo htmlspecialchars($file['path']); ?>">
                <i class="fas fa-eye"></i> View Document
            </a>
        </div>
    </div>
<?php endforeach; ?>