<?php
$filter = filter_input(INPUT_GET, 'filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'all';
$db = new DB();

// Validate session
if (!isset($_SESSION[SESSION_NAME]['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = (int)$_SESSION[SESSION_NAME]['user_id'];
$user = $db->query("SELECT * FROM users WHERE id={$user_id}", 'SELECT', 1);

// Get search parameters with proper sanitization
$search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$date_from = filter_input(INPUT_GET, 'date_from', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$date_to = filter_input(INPUT_GET, 'date_to', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$doc_type = filter_input(INPUT_GET, 'doc_type', FILTER_VALIDATE_INT) ?? '';
$doc_folder = filter_input(INPUT_GET, 'doc_folder', FILTER_VALIDATE_INT) ?? '';
$doc_subfolder = filter_input(INPUT_GET, 'doc_subfolder', FILTER_VALIDATE_INT) ?? '';

// Fetch document types, folders and subfolders for dropdowns
$document_types = $db->query("SELECT DISTINCT id, name FROM document_types ORDER BY name", 'SELECT');
$folders = $db->query("SELECT DISTINCT id, name FROM archive_document_folders ORDER BY name", 'SELECT');
$subfolders = $db->query("SELECT DISTINCT id, name FROM archive_document_sub_folders ORDER BY name", 'SELECT');


// Base query
$q = "SELECT DISTINCT a.*, 
    u.username as editor,
    dt.name as document_type_name,
    f.name as folder_name,
    sf.name as sub_folder_name
    FROM archives a 
    LEFT JOIN users u ON (u.id = a.edited_by)
    LEFT JOIN document_types dt ON (dt.id = a.document_type)
    LEFT JOIN archive_document_folders f ON (f.id = a.document_type)
    LEFT JOIN archive_document_sub_folders sf ON (sf.id = a.sub_folder_id)";

// Build WHERE clause using proper sanitization
$where_conditions = [];

if ($search_query) {
    $search_term = addslashes($search_query);
    $where_conditions[] = "(
        a.name LIKE '%{$search_term}%' OR 
        MATCH(a.description) AGAINST ('{$search_term}' IN BOOLEAN MODE) OR
        EXISTS (
            SELECT 1 
            FROM document_contents dc 
            WHERE dc.archive_id = a.id 
            AND MATCH(dc.content) AGAINST ('{$search_term}' IN BOOLEAN MODE)
        )
    )";
}

if ($date_from) {
    $date_from = addslashes($date_from);
    $where_conditions[] = "a.created_at >= '{$date_from}'";
}

if ($date_to) {
    $date_to = addslashes($date_to);
    $where_conditions[] = "a.created_at <= '{$date_to}'";
}

if ($doc_type) {
    $doc_type = (int)$doc_type;
    $where_conditions[] = "a.document_type = {$doc_type}";
}

if ($doc_folder) {
    $doc_folder = (int)$doc_folder;
    $where_conditions[] = "a.document_type = {$doc_folder}";
}

if ($doc_subfolder) {
    $doc_subfolder = (int)$doc_subfolder;
    $where_conditions[] = "a.sub_folder_id = {$doc_subfolder}";
}

// Add filter conditions
switch ($filter) {
    case 'pending':
        $where_conditions[] = "a.completed = 0";
        break;
    case 'completed':
        $where_conditions[] = "a.completed = 1";
        break;
    case 'rpf':
        $where_conditions[] = "a.name LIKE 'RPF%'";
        break;
}

// Combine WHERE conditions
if (!empty($where_conditions)) {
    $q .= " WHERE " . implode(" AND ", $where_conditions);
}
$q .= " ORDER BY a.id DESC";

// Execute query using existing query method
$pf_files = $db->query($q, 'SELECT');

// Handle file upload with improved security
if(isset($_POST['upload'], $_POST['password'])) {
    if(defined('SYNC_PASSWORD') && SYNC_PASSWORD !== '' && hash_equals(SYNC_PASSWORD, (string) $_POST['password'])) {
//        $delete_existing = $_POST['delete_existing'] ?? false;
        $db = new DB();
//        if($delete_existing) {
//            if($x = $db->query("DELETE FROM archives WHERE 1")) {
//                echo 'Files deleted...<br />';
//            } else {
//                echo "Delete failed!<br />";
//            }
//
//        }


        $hashes = $db->query("SELECT hash FROM archives", "SELECT");
        $hashes = array_column($hashes, 'hash');


        /**
         * Process new files
         */
        $file_path = rtrim(FILES_PATH, '/') . '/pf-archives/';
        // Legacy fallback: if the canonical FILES_PATH location doesn't exist yet,
        // check inside the site root (older deployments stored files there via CWD-relative path).
        if (!is_dir($file_path)) {
            $file_path = rtrim(dirname(__DIR__), '/') . '/allfiles/pf-archives/';
        }
        $dir = $file_path;
        $ignored = array('.', '..', '.DS_Store');
        $local_files = scandir($file_path);
        $files = array();
        foreach (scandir($dir) as $file) {
            if (in_array($file, $ignored)) continue;
            $files[$file] = filemtime($dir . '/' . $file);
        }

        arsort($files);
        $files = array_keys($files);

        // return $files;
        // dump($files);
        //  return;
        foreach ($files as $index => $file_name) {
            if ($index >= 0 && $index <= 50) {

                // if (in_array($file_name, ['.', '..', '.DS_Store'])) {
                //     continue;
                // }

                $PREFIX = 'RPF';
                $year = substr($file_name, 0, strlen($PREFIX)) == $PREFIX ? substr($file_name, strlen($PREFIX), 4) : null;
                $hash = MD5_file($file_path . $file_name);
                $mime = mime_content_type($file_path . $file_name);
                $size = filesize($file_path . $file_name);
                $name = $file_name;
                $path = $file_path . $file_name;
                $data = [
                    'name' => $name,
                    'year' => $year,
                    'mime' => $mime,
                    'hash' => $hash,
                    'size' => $size,
                    'path' => $path
                ];
                $file = new File();
                $file->patch($data);

                if (in_array($hash, $hashes)) {
//                dump($data);
                    echo "This hash already exists in the database {$hash} <br />";
                } else {
                    if ($id = $file->add()) {
                        echo "File added {$id} <br />";
                    } else {
//                    dump($data);
                        echo "File add failed {$id} " . $file->db->errorMessage() . "<br />";
                    }
                }


            }
        }
        return;

    }

}
$user_one = new User($user_id);
?>
<style>
    /* ===== Synchronization page polish (scoped to .sync-page) ===== */
    .sync-page { animation: syncIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both; }
    @keyframes syncIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }

    .sync-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin: 0.5rem 0 1.6rem; }
    .sync-title {
        font-family: var(--font-display, 'Bricolage Grotesque', sans-serif);
        font-weight: 800; font-size: 2rem; line-height: 1; letter-spacing: -0.02em; margin: 0;
        background: linear-gradient(100deg, #fff 35%, var(--light-orange, #ffb24d));
        -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    }
    .sync-sub { color: var(--text-muted, #9c9389); margin: 0.45rem 0 0; font-size: 0.95rem; }
    .sync-chips { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .sync-chip {
        display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.9rem; border-radius: 999px;
        background: rgba(240, 140, 0, 0.10); border: 1px solid var(--border-color, rgba(240,140,0,0.16));
        color: #f6efe5; font-size: 0.84rem; font-weight: 600;
    }
    .sync-chip i { color: var(--primary-orange, #f08c00); }

    /* Button system: ghost base, filled active/primary */
    .sync-page .btn-custom {
        display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
        width: 100%; margin-bottom: 0.55rem; cursor: pointer; text-decoration: none;
        font-family: var(--font-body, 'Hanken Grotesk', sans-serif); font-weight: 600; font-size: 0.9rem;
        text-transform: none; letter-spacing: 0;
        padding: 0.62rem 1rem; border-radius: 11px;
        color: var(--light-orange, #ffb24d);
        background: rgba(240, 140, 0, 0.08);
        border: 1px solid var(--border-color, rgba(240, 140, 0, 0.2));
        transition: all 0.2s ease;
    }
    .sync-page .btn-custom:hover { background: rgba(240, 140, 0, 0.16); border-color: var(--primary-orange, #f08c00); color: #fff; transform: translateY(-1px); }
    .sync-page .filter-buttons .btn-custom.active {
        background: linear-gradient(135deg, var(--light-orange, #ffb24d), var(--dark-orange, #d97706));
        color: #241400; border-color: transparent; box-shadow: 0 8px 18px -8px rgba(240, 140, 0, 0.6);
    }
    .sync-page .upload-form button[name="upload"] {
        background: linear-gradient(135deg, var(--light-orange, #ffb24d), var(--dark-orange, #d97706));
        color: #241400; border-color: transparent; font-weight: 700;
    }
    .sync-page .upload-form button[name="upload"]:hover { filter: brightness(1.05); color: #241400; }

    /* Search + upload panels */
    .sync-page .search-form, .sync-page .upload-form {
        background: linear-gradient(180deg, rgba(31, 26, 21, 0.7), rgba(20, 17, 13, 0.8));
        border: 1px solid var(--border-color, rgba(240,140,0,0.16)); border-radius: 16px; padding: 1.1rem 1.2rem;
    }
    .sync-page .search-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.8rem; }
    .sync-page .form-control {
        background: var(--bg-dark, #17130f) !important; color: #f6efe5 !important;
        border: 1.5px solid rgba(255, 255, 255, 0.07) !important; border-radius: 11px !important;
        padding: 0.6rem 0.85rem !important; font-size: 0.92rem !important;
    }
    .sync-page .form-control:focus { border-color: var(--primary-orange, #f08c00) !important; box-shadow: 0 0 0 4px rgba(240, 140, 0, 0.12) !important; outline: none !important; }
    .sync-page select.form-control option { background: #17130f; color: #f6efe5; }
    .sync-page .upload-form label { color: var(--text-muted, #9c9389); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; margin-bottom: 0.4rem; }
    .sync-page .form-check-label { color: #d7cfc4; text-transform: none; letter-spacing: 0; }

    /* Files grid */
    .sync-page .files-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(232px, 1fr)); gap: 1.1rem; margin-top: 1.3rem; }
    .sync-page .file-card {
        position: relative; overflow: hidden; display: flex; flex-direction: column;
        background: linear-gradient(180deg, rgba(31, 26, 21, 0.92), rgba(20, 17, 13, 0.95));
        border: 1px solid var(--border-color, rgba(240,140,0,0.16)); border-radius: 16px; padding: 1.1rem;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        animation: syncIn 0.5s ease-out both;
    }
    .sync-page .file-card::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 3px; background: linear-gradient(90deg, var(--primary-orange), var(--light-orange)); opacity: 0; transition: opacity 0.2s ease; }
    .sync-page .file-card:hover { transform: translateY(-4px); border-color: var(--primary-orange, #f08c00); box-shadow: 0 16px 34px -16px rgba(0, 0, 0, 0.7); }
    .sync-page .file-card:hover::before { opacity: 1; }
    .sync-page .file-header { display: flex; align-items: center; gap: 0.7rem; margin-bottom: 0.9rem; }
    .sync-page .file-icon {
        flex: 0 0 auto; width: 42px; height: 42px; display: grid; place-items: center; border-radius: 11px; font-size: 1.2rem;
        background: rgba(255, 107, 93, 0.14); color: #ff7a66; border: 1px solid rgba(255, 107, 93, 0.25);
    }
    .sync-page .file-name { font-weight: 600; font-size: 0.9rem; color: #f1ebe1; line-height: 1.3; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .sync-page .file-info { display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 0.9rem; flex: 1; }
    .sync-page .info-item { display: flex; gap: 0.5rem; font-size: 0.8rem; min-width: 0; }
    .sync-page .info-label { color: var(--text-muted, #9c9389); flex: 0 0 auto; min-width: 62px; }
    .sync-page .info-value { color: #d7cfc4; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sync-page .completion-badge {
        position: absolute; top: 0.7rem; right: 0.7rem; width: 26px; height: 26px; border-radius: 50%;
        display: grid; place-items: center; font-size: 0.7rem; color: #08230f; z-index: 2;
        background: linear-gradient(135deg, #34d399, #10b981); box-shadow: 0 4px 10px -2px rgba(16, 185, 129, 0.5);
    }

    /* Load more */
    .sync-page .load-more-container { text-align: center; margin: 1.7rem 0 0.5rem; }
    .sync-page .load-more-btn {
        background: rgba(240, 140, 0, 0.08); color: var(--light-orange, #ffb24d);
        border: 1px solid var(--border-color, rgba(240,140,0,0.2)); border-radius: 999px;
        padding: 0.65rem 1.7rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease;
    }
    .sync-page .load-more-btn:hover { background: rgba(240, 140, 0, 0.16); border-color: var(--primary-orange, #f08c00); color: #fff; }
    .sync-page .load-more-btn .counter { opacity: 0.65; font-weight: 500; margin-left: 0.3rem; }

    /* PDF modal (lives outside .sync-page) — proper centered dialog above the navbar */
    #pdfModal {
        z-index: 20000 !important;
        background: rgba(8, 6, 4, 0.8) !important;
        -webkit-backdrop-filter: blur(4px); backdrop-filter: blur(4px);
        padding: 96px 16px 24px !important; /* clear the fixed navbar above */
    }
    #pdfModal .modal-content {
        background: #110e0b !important; border: 1px solid var(--border-color, rgba(240,140,0,0.2)) !important;
        border-radius: 16px !important; width: 100% !important; max-width: 980px !important;
        height: calc(100vh - 128px) !important; max-height: 880px !important;
        margin: 0 auto !important; display: flex; flex-direction: column; overflow: hidden;
        box-shadow: 0 40px 90px -30px rgba(0, 0, 0, 0.9);
    }
    #pdfModal .modal-header { padding: 1rem 1.4rem; border-bottom: 1px solid var(--border-color, rgba(240,140,0,0.16)) !important; }
    #pdfModal .modal-title { font-family: var(--font-display, 'Bricolage Grotesque', sans-serif); color: #fff; font-size: 1.15rem; margin: 0; }
    #pdfModal .modal-close {
        background: none; border: none; color: var(--text-muted, #9c9389); font-size: 1.6rem; line-height: 1; cursor: pointer;
        width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; transition: all 0.2s ease;
    }
    #pdfModal .modal-close:hover { background: rgba(240, 140, 0, 0.12); color: var(--primary-orange, #f08c00); }
    #pdfModal .modal-body { flex: 1; padding: 0; overflow: hidden; background: #0b0907; }
    #pdfModal .modal-body iframe { width: 100%; height: 100%; border: none; }

    /* Keep select text from clipping */
    .sync-page select.form-control { line-height: 1.6 !important; height: auto !important; padding-top: 0.62rem !important; padding-bottom: 0.62rem !important; }

    @media (max-width: 900px) { .sync-page .search-row { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 768px) {
        /* the Incoming/Cleanup action buttons use col-4 — stack them on phones */
        .sync-page .upload-form .col-4 { flex: 0 0 100%; max-width: 100%; }
    }
    @media (max-width: 560px) { .sync-page .search-row { grid-template-columns: 1fr; } .sync-title { font-size: 1.7rem; } }
</style>
<div class="container sync-page">
    <!-- Page Header -->
    <div class="sync-head">
        <div>
            <h1 class="sync-title">Document Archives</h1>
            <p class="sync-sub">Browse, search and synchronize scanned documents.</p>
        </div>
        <div class="sync-chips">
            <span class="sync-chip"><i class="fa fa-file-pdf"></i> <?= number_format(count($pf_files)) ?> documents</span>
            <span class="sync-chip"><i class="fa fa-filter"></i> <?= htmlspecialchars(ucfirst($filter)) ?></span>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div class="row">
        <div class="col-sm-3">
            <div class="filter-buttons">
                <a href="./?p=settings_synchronization" class="btn-custom <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="fa fa-list"></i> All
                </a>
                <a href="./?p=settings_synchronization&filter=pending" class="btn-custom <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                    <i class="fa fa-edit"></i> Uncompleted
                </a>
                <a href="./?p=settings_synchronization&filter=completed" class="btn-custom <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                    <i class="fa fa-check"></i> Completed
                </a>
                <a href="./?p=settings_synchronization&filter=rpf" class="btn-custom <?php echo $filter === 'rpf' ? 'active' : ''; ?>">
                    <i class="fa fa-recycle"></i> Rescanned
                </a>
            </div>
        </div>
        <div class="col-sm-9">
            <div class="upload-form" style="margin-bottom: 1rem;">
                <div class="row g-3">
                    <div class="col-4 col-md-4 col-lg-4">
                        <div class="d-grid">
                            <a href="./?p=incoming_system_uploads" class="btn-custom">
                                <i class="fas fa-download"></i> Incoming System Uploads
                            </a>
                        </div>
                    </div>
                    <div class="col-4 col-md-4 col-lg-4">
                        <div class="d-grid">
                            <a href="./?p=unregistered_files_cleanup" class="btn-custom">
                                <i class="fas fa-broom"></i> Unregistered Files Cleanup
                            </a>
                        </div>
                    </div>
                    <div class="col-4 col-md-4 col-lg-4">
                        <div class="d-grid">
                            <a href="./?p=database_record_cleanup" class="btn-custom">
                                <i class="fas fa-database"></i> Database Record Cleanup
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Search Form -->
    <div class="search-form mb-3">
        <form id="searchForm" method="get" action="">
            <input type="hidden" name="p" value="settings_synchronization">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">

            <div class="search-row">
                <div class="search-field">
                    <input type="text" id="search" name="search" class="form-control"
                           value="<?php echo htmlspecialchars($search_query); ?>"
                           placeholder="Search in documents...">
                </div>

                <div class="search-field">
                    <input type="text" id="date_from" name="date_from" class="form-control" autocomplete="off"
                           value="<?php echo htmlspecialchars($date_from); ?>"
                           placeholder="From date">
                </div>

                <div class="search-field">
                    <input type="text" id="date_to" name="date_to" class="form-control" autocomplete="off"
                           value="<?php echo htmlspecialchars($date_to); ?>"
                           placeholder="To date">
                </div>

                <div class="search-field">
                    <select id="doc_type" name="doc_type" class="form-control">
                        <option value="">Type: All</option>
                        <?php foreach ($document_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['id']); ?>"
                                <?php echo $doc_type == $type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-field">
                    <select id="doc_folder" name="doc_folder" class="form-control">
                        <option value="">Folder: All</option>
                        <?php foreach ($folders as $folder): ?>
                            <option value="<?php echo htmlspecialchars($folder['id']); ?>"
                                <?php echo $doc_folder == $folder['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($folder['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-field">
                    <select id="doc_subfolder" name="doc_subfolder" class="form-control">
                        <option value="">Subfolder: All</option>
                        <?php foreach ($subfolders as $subfolder): ?>
                            <option value="<?php echo htmlspecialchars($subfolder['id']); ?>"
                                <?php echo $doc_subfolder == $subfolder['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subfolder['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
    <!-- System Menu Buttons -->
    <?php if($user_one->can('PROCESS_UPLOADED_SYNCHRONIZATION')): ?>


        <!-- Upload Form -->
        <div class="upload-form">
            <form name="uploadForm" method="post">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="form-group">
                            <label for="password">Passcode</label>
                            <input type="password" id="password" name="password" class="form-control">
                        </div>
                    </div>

                    <?php if ($user_one->can('UPLOAD_DELETE_EXISTING')): ?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="form-group">
                                <label class="d-block">Delete existing?</label>
                                <div class="form-check">
                                    <input type="checkbox" name="delete_existing" class="form-check-input" id="deleteExisting">
                                    <label class="form-check-label" for="deleteExisting">Yes, delete existing</label>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="d-grid">
                            <button type="submit" name="upload" value="process" class="btn-custom">
                                <i class="fas fa-sync"></i> Process New Files
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Files Grid -->
    <div class="files-grid">
        <?php
        $items_per_page = 24;
        $total_files = count($pf_files);
        $files_to_show = array_slice($pf_files, 0, $items_per_page);

        foreach ($files_to_show as $file): ?>
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
    </div>

    <!-- Load More Button -->
    <?php if ($total_files > $items_per_page): ?>
        <div class="load-more-container">
            <button id="load-more" class="load-more-btn"
                    data-total="<?php echo $total_files; ?>"
                    data-loaded="<?php echo $items_per_page; ?>">
                Load More
                <span class="counter">
                        (<?php echo $items_per_page; ?> of <?php echo $total_files; ?>)
                    </span>
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- PDF Viewer Modal -->
<div class="modal" id="pdfModal" tabindex="-1">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">View Document</h5>
            <button type="button" class="modal-close" id="modalClose">&times;</button>
        </div>
        <div class="modal-body">
            <iframe id="pdfFrame" style="width: 100%; height: 100%;" frameborder="0"></iframe>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
    // Modal functionality
    const modal = document.getElementById('pdfModal');
    const modalClose = document.getElementById('modalClose');
    const pdfFrame = document.getElementById('pdfFrame');
    const baseUrl = "<?php echo BASE_URL; ?>";

    // View PDF handlers
    document.querySelectorAll('.view-pdf').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const pdfPath = this.getAttribute('data-path');
            // Use serve_file.php to serve files
            pdfFrame.src = baseUrl + 'serve_file.php?file=' + encodeURIComponent(pdfPath);
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    });

    // Close modal handlers
    modalClose.addEventListener('click', () => {
        modal.style.display = 'none';
        pdfFrame.src = '';
        document.body.style.overflow = 'auto';
    });

    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
            pdfFrame.src = '';
            document.body.style.overflow = 'auto';
        }
    });

    // Load more functionality
    const loadMoreBtn = document.getElementById('load-more');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            const button = this;
            const totalFiles = parseInt(button.dataset.total);
            let loadedFiles = parseInt(button.dataset.loaded);
            const nextBatch = 24;

            // Add filter parameter to the load-more request
            const currentFilter = '<?php echo $filter; ?>';
            fetch(`pages/load-more-files.php?offset=${loadedFiles}&limit=${nextBatch}&filter=${currentFilter}`)
                .then(response => response.text())
                .then(html => {
                    document.querySelector('.files-grid').insertAdjacentHTML('beforeend', html);

                    loadedFiles += nextBatch;
                    button.dataset.loaded = loadedFiles;

                    const counter = button.querySelector('.counter');
                    counter.textContent = `(${Math.min(loadedFiles, totalFiles)} of ${totalFiles})`;

                    if (loadedFiles >= totalFiles) {
                        button.style.display = 'none';
                    }

                    // Reinitialize view PDF handlers for new elements
                    document.querySelectorAll('.view-pdf').forEach(button => {
                        button.addEventListener('click', function(e) {
                            e.preventDefault();
                            const pdfPath = this.getAttribute('data-path');
                            // Use serve_file.php to serve files
                            pdfFrame.src = baseUrl + 'serve_file.php?file=' + encodeURIComponent(pdfPath);
                            modal.style.display = 'block';
                            document.body.style.overflow = 'hidden';
                        });
                    });
                });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('searchForm');
        const inputs = form.querySelectorAll('input, select');
        let debounceTimeout;

        // Function to debounce search
        function debounce(func, wait) {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(func, wait);
        }

        // Function to perform search
        function performSearch() {
            const formData = new URLSearchParams(new FormData(form));
            const queryString = formData.toString();
            const newUrl = `${window.location.pathname}?${queryString}`;

            // Update URL without reloading page
            window.history.pushState({}, '', newUrl);

            // Fetch and update results
            fetch(newUrl)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newGrid = doc.querySelector('.files-grid');
                    const oldGrid = document.querySelector('.files-grid');

                    if (newGrid && oldGrid) {
                        oldGrid.innerHTML = newGrid.innerHTML;
                        // Reinitialize PDF viewer handlers
                        initializePdfViewers();
                    }

                    // Update load more button
                    const newLoadMore = doc.querySelector('.load-more-container');
                    const oldLoadMore = document.querySelector('.load-more-container');
                    if (newLoadMore && oldLoadMore) {
                        oldLoadMore.innerHTML = newLoadMore.innerHTML;
                        initializeLoadMore();
                    }
                });
        }

        // Add event listeners to all form inputs
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                debounce(performSearch, 500);
            });

            input.addEventListener('change', () => {
                debounce(performSearch, 500);
            });
        });

        // Initialize PDF viewers
        function initializePdfViewers() {
            document.querySelectorAll('.view-pdf').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const pdfPath = this.getAttribute('data-path');
                    // Use serve_file.php to serve files
                    pdfFrame.src = baseUrl + 'serve_file.php?file=' + encodeURIComponent(pdfPath);
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                });
            });
        }

        // Initialize load more functionality
        function initializeLoadMore() {
            const loadMoreBtn = document.getElementById('load-more');
            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', function() {
                    // ... existing load more code ...
                });
            }
        }
    });
</script>

<!-- flatpickr date picker -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    /* flatpickr calendar — dark/amber theme (must load AFTER flatpickr.min.css to win) */
    .flatpickr-calendar { background: #17130f; border: 1px solid rgba(240,140,0,0.2); border-radius: 14px; box-shadow: 0 24px 60px -24px rgba(0,0,0,0.85); }
    .flatpickr-calendar.arrowTop:before { border-bottom-color: rgba(240,140,0,0.2); }
    .flatpickr-calendar.arrowTop:after { border-bottom-color: #17130f; }
    .flatpickr-calendar.arrowBottom:before { border-top-color: rgba(240,140,0,0.2); }
    .flatpickr-calendar.arrowBottom:after { border-top-color: #17130f; }
    .flatpickr-months, .flatpickr-weekdays, .flatpickr-month { background: transparent; }
    .flatpickr-current-month, .flatpickr-current-month input.cur-year, .flatpickr-weekday, span.flatpickr-weekday { color: #f6efe5 !important; fill: #f6efe5 !important; }
    .flatpickr-monthDropdown-months { background: #17130f; color: #f6efe5; }
    .flatpickr-day { color: #d7cfc4; border-radius: 9px; }
    .flatpickr-day:hover { background: rgba(240, 140, 0, 0.16); border-color: transparent; color: #fff; }
    .flatpickr-day.today { border-color: #f08c00; }
    .flatpickr-day.selected, .flatpickr-day.selected:hover { background: linear-gradient(135deg, #ffb24d, #d97706); border-color: transparent; color: #241400; }
    .flatpickr-day.flatpickr-disabled, .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay { color: #6f675e; }
    .flatpickr-months .flatpickr-prev-month svg, .flatpickr-months .flatpickr-next-month svg { fill: #cfc6ba; }
    .flatpickr-months .flatpickr-prev-month:hover svg, .flatpickr-months .flatpickr-next-month:hover svg { fill: #f08c00; }
    .numInputWrapper span.arrowUp:after { border-bottom-color: #cfc6ba; }
    .numInputWrapper span.arrowDown:after { border-top-color: #cfc6ba; }
</style>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.flatpickr) {
            var opts = { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', allowInput: true, disableMobile: true };
            flatpickr('#date_from', opts);
            flatpickr('#date_to', opts);
        }
    });
</script>