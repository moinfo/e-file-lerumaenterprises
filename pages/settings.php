<?php
include_once ("./config.php");
$db = new DB();
$document_types = $db->fetch('document_types');
$q = "SELECT *  FROM archive_document_folders  ";
$folders = $db->query($q, 'SELECT');

$settings = [
    ['name' => "document_folders", 'title' => 'Document Folders', 'link' => '.?p=settings.document_folders', 'descriptions' => 'Add, Edit and Delete Document Folder/s', 'icon' => 'bi-folder'],
    ['name' => "document_sub_folders", 'title' => 'Document Sub Folders', 'link' => '.?p=settings.document_sub_folders', 'descriptions' => 'Add, Edit and Delete Document Sub Folder/s', 'icon' => 'bi-folder2'],
    ['name' => "document_types", 'title' => 'Document Types', 'link' => '.?p=settings_document_types', 'descriptions' => 'Add, Edit and Delete Document Type/s', 'icon' => 'bi-file-earmark-text'],
    ['name' => "users", 'title' => 'Users', 'link' => '.?p=settings_users', 'descriptions' => 'Add, Edit and Delete User/s', 'icon' => 'bi-people'],
    ['name' => "uploads", 'title' => 'Uploads', 'link' => '.?p=settings_uploads', 'descriptions' => 'Uploads all Documents, modifications, and Organizations', 'icon' => 'bi-upload'],
    ['name' => "edited_files", 'title' => 'Edited Files', 'link' => '.?p=settings_edited_files', 'descriptions' => 'Cruds For All Files Edited by a User', 'icon' => 'bi-pencil-square'],
    ['name' => "user_groups_and_access", 'title' => 'User Group & Access', 'link' => '.?p=settings.users', 'descriptions' => 'Assign User to a group and set access', 'icon' => 'bi-shield-lock'],
];
?>

<!-- Add Bootstrap Icons CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
    /* ===== Settings page polish (scoped to .settings-page) ===== */
    .settings-page { animation: setIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both; }
    @keyframes setIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }

    .set-head { margin: 0.5rem 0 1.8rem; }
    .set-title {
        font-family: var(--font-display, 'Bricolage Grotesque', sans-serif);
        font-weight: 800; font-size: 2.1rem; line-height: 1; letter-spacing: -0.02em; margin: 0;
        background: linear-gradient(100deg, #fff 35%, var(--light-orange, #ffb24d));
        -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    }
    .set-sub { color: var(--text-muted, #9c9389); margin: 0.45rem 0 0; font-size: 0.96rem; }

    .settings-page .row > [class*="col-"] { margin-bottom: 1.3rem; display: flex; }
    .settings-page .menu-link { text-decoration: none; display: block; width: 100%; }
    .settings-page .menu-card {
        position: relative; overflow: hidden; display: flex; align-items: center; gap: 1rem; height: 100%;
        background: linear-gradient(180deg, rgba(31, 26, 21, 0.92), rgba(20, 17, 13, 0.95));
        border: 1px solid var(--border-color, rgba(240,140,0,0.16)); border-radius: 16px; padding: 1.2rem 1.3rem;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        animation: setIn 0.5s ease-out both;
    }
    .settings-page .menu-card::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 3px; background: var(--accent, #f08c00); opacity: 0; transition: opacity 0.2s ease; }
    .settings-page .menu-link:hover .menu-card {
        transform: translateY(-4px);
        border-color: color-mix(in srgb, var(--accent, #f08c00) 55%, transparent);
        box-shadow: 0 16px 34px -16px rgba(0, 0, 0, 0.7);
    }
    .settings-page .menu-link:hover .menu-card::before { opacity: 1; }
    .settings-page .mc-icon {
        flex: 0 0 auto; width: 52px; height: 52px; display: grid; place-items: center; border-radius: 14px; font-size: 1.4rem;
        background: color-mix(in srgb, var(--accent, #f08c00) 14%, transparent);
        color: var(--accent, #f08c00);
        border: 1px solid color-mix(in srgb, var(--accent, #f08c00) 28%, transparent);
        transition: transform 0.25s ease;
    }
    .settings-page .menu-link:hover .mc-icon { transform: translateY(-2px) scale(1.06); }
    .settings-page .mc-text { flex: 1; min-width: 0; }
    .settings-page .mc-title { font-family: var(--font-display, 'Bricolage Grotesque', sans-serif); font-weight: 700; font-size: 1.05rem; color: #f4eee4; line-height: 1.2; }
    .settings-page .mc-desc { color: var(--text-muted, #9c9389); font-size: 0.84rem; margin-top: 0.25rem; line-height: 1.35; }
    .settings-page .mc-arrow { flex: 0 0 auto; color: var(--text-muted, #9c9389); font-size: 1rem; transition: transform 0.2s ease, color 0.2s ease; }
    .settings-page .menu-link:hover .mc-arrow { color: var(--accent, #f08c00); transform: translateX(3px); }

    @media (max-width: 575px) { .set-title { font-size: 1.7rem; } }
</style>

<div class="container settings-page">
    <div class="set-head">
        <h1 class="set-title">Settings</h1>
        <p class="set-sub">Manage folders, document types, users, access and synchronization.</p>
    </div>
    <div class="row">
        <?php
        $accents = ['#ffb24d', '#56b6ff', '#34d399', '#c084fc', '#ff7a66', '#fbbf24', '#2dd4bf'];
        foreach ($settings as $i => $setting) {
            $accent = $accents[$i % count($accents)];
            $delay = 0.05 + ($i * 0.05);
            echo "
            <div class='col-12 col-md-6 col-lg-4'>
                <a href='{$setting['link']}' class='menu-link'>
                    <div class='menu-card' style='--accent:{$accent}; animation-delay:{$delay}s;'>
                        <div class='mc-icon'><i class='bi {$setting['icon']}'></i></div>
                        <div class='mc-text'>
                            <div class='mc-title'>{$setting['title']}</div>
                            <div class='mc-desc'>{$setting['descriptions']}</div>
                        </div>
                        <i class='bi bi-chevron-right mc-arrow'></i>
                    </div>
                </a>
            </div>";
        }
        ?>
    </div>
</div>

<?php
// Keep your existing JavaScript
?>
<script>
    function showFile(path) {
        var file_path = "<?php echo BASE_URL; ?>serve_file.php?file="+encodeURIComponent(path);
        PDFObject.embed(file_path, "#search-result");
    }

    $(document).ready(function(){
        $("#input-description").on('keyup', function() {
            var text = $(this).val();
            if(text.length > 3) {
                search();
            }
        });
        $("#input-number").on('keyup', function() {
            var text = $(this).val();
            if(text.length > 2 || text == '') {
                search();
            }
        });
    });

    function search() {
        // Your existing search function
        var document_type = $("#input-document_type").val();
        var year = $("#input-year").val();
        var description = $("#input-description").val();
        var number = $("#input-number").val();
        // Rest of your search function...
    }
</script>