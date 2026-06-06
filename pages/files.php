<?php
include_once ("./config.php");
if (!isset($_REQUEST["sub_folder_id"])) {
    die();
}
$sub_folder_id = (int)$_REQUEST['sub_folder_id'];
$document_type_id = isset($_REQUEST['document_type_id']) ? (int)$_REQUEST['document_type_id'] : null;
$db = new DB();
$document_types = $db->fetch('document_types');
$q = "SELECT a.*, u.username as editor FROM archives a LEFT JOIN users u ON  (u.id = a.edited_by) WHERE a.document_type ='$document_type_id' AND a.sub_folder_id = $sub_folder_id ";
$folders = $db->query($q, 'SELECT');
$q1 = "SELECT * FROM archive_document_sub_folders WHERE id = $sub_folder_id";
$sub_folder = $db->query($q1, 'SELECT','ROW');
$q1 = "SELECT * FROM document_types WHERE id = $document_type_id";
$document_type = $db->query($q1, 'SELECT','ROW');

$q1 = "SELECT adsf.*, adf.name AS folder_name, adf.id AS folder_id FROM archive_document_sub_folders adsf
JOIN archive_document_folders adf ON (adf.id = adsf.archive_document_folder_id)
WHERE adsf.id = $sub_folder_id";
$folder_detail = $db->query($q1, 'SELECT','ROW');
?>

<style>
    /* ===== Files browser theming (scoped to .files-browser) ===== */
    .files-browser { animation: fbIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both; }
    @keyframes fbIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }

    .files-browser .fb-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin: 0.5rem 0 0.4rem; }
    .files-browser .fb-title {
        font-family: var(--font-display, 'Bricolage Grotesque', sans-serif); font-weight: 800; font-size: 1.8rem; line-height: 1; letter-spacing: -0.02em; margin: 0;
        background: linear-gradient(100deg, #fff 35%, var(--light-orange, #ffb24d)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    }
    .files-browser .fb-chip { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.45rem 0.85rem; border-radius: 999px; background: rgba(240, 140, 0, 0.10); border: 1px solid var(--border-color, rgba(240,140,0,0.16)); color: #f6efe5; font-size: 0.82rem; font-weight: 600; white-space: nowrap; }
    .files-browser .fb-chip i { color: var(--primary-orange, #f08c00); }

    .files-browser .breadcrumb { background: transparent; padding: 0; margin: 0.4rem 0 0; font-size: 0.85rem; }
    .files-browser .breadcrumb-item, .files-browser .breadcrumb-item a { color: var(--text-muted, #9c9389); text-decoration: none; }
    .files-browser .breadcrumb-item a:hover { color: var(--primary-orange, #f08c00); }
    .files-browser .breadcrumb-item.active { color: #f6efe5; }
    .files-browser .breadcrumb-item + .breadcrumb-item::before { color: #6f675e; }

    .files-browser .fb-search { position: relative; max-width: 520px; margin: 1.3rem 0; }
    .files-browser .fb-search input { width: 100%; background: var(--bg-dark, #17130f); border: 1.5px solid rgba(255, 255, 255, 0.08); color: #f6efe5; border-radius: 12px; padding: 0.7rem 1rem 0.7rem 2.6rem; font-size: 0.95rem; }
    .files-browser .fb-search input:focus { border-color: var(--primary-orange, #f08c00); box-shadow: 0 0 0 3px rgba(240, 140, 0, 0.12); outline: none; }
    .files-browser .fb-search::before { content: "\f002"; font-family: "Font Awesome 5 Free"; font-weight: 900; position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #7d756b; }

    /* File cards */
    .files-browser .preview-file { text-decoration: none; display: block; height: 100%; }
    .files-browser .file-card {
        position: relative; overflow: hidden; height: 100%; text-align: center;
        background: linear-gradient(180deg, rgba(31, 26, 21, 0.92), rgba(20, 17, 13, 0.95));
        border: 1px solid var(--border-color, rgba(240,140,0,0.16)); border-radius: 14px; padding: 1.1rem 0.8rem;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .files-browser .file-card:hover { transform: translateY(-4px); border-color: var(--primary-orange, #f08c00); box-shadow: 0 14px 30px -16px rgba(0, 0, 0, 0.7); }
    .files-browser .file-icon {
        width: 54px; height: 54px; margin: 0 auto 0.7rem; border-radius: 14px; display: grid; place-items: center; font-size: 1.5rem;
        background: rgba(255, 107, 93, 0.14); color: #ff7a66; border: 1px solid rgba(255, 107, 93, 0.25);
    }
    .files-browser .file-icon:has(.fa-file-pdf) { background: rgba(52, 211, 153, 0.14); color: #34d399; border-color: rgba(52, 211, 153, 0.25); }
    .files-browser .file-name { color: #ece5db; font-size: 0.82rem; font-weight: 500; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; }
    .files-browser .file-info { color: var(--text-muted, #9c9389); font-size: 0.72rem; margin-top: 0.35rem; }
    .files-browser .alert-info { background: rgba(86, 182, 255, 0.1); border: 1px solid rgba(86, 182, 255, 0.25); color: #9fd0ff; border-radius: 12px; }

    /* Preview modal */
    #pdfModal .modal-content { background: #110e0b !important; border: 1px solid var(--border-color, rgba(240,140,0,0.2)) !important; border-radius: 16px !important; color: #f6efe5; }
    #pdfModal .modal-header, #pdfModal .modal-footer { border-color: var(--border-color, rgba(240,140,0,0.16)) !important; }
    #pdfModal .modal-title { font-family: var(--font-display, 'Bricolage Grotesque', sans-serif); color: #fff; }
    #pdfModal .close { color: var(--text-muted, #9c9389); opacity: 1; text-shadow: none; }
    #pdfModal .close:hover { color: var(--primary-orange, #f08c00); }
    #pdfModal .btn-custom { background: rgba(240, 140, 0, 0.10); border: 1px solid var(--border-color, rgba(240,140,0,0.2)); color: var(--light-orange, #ffb24d); border-radius: 10px; padding: 0.5rem 1.1rem; }
    #pdfModal .btn-custom:hover { background: rgba(240, 140, 0, 0.18); color: #fff; }
    #pdfModal #downloadPdf { background: linear-gradient(135deg, var(--light-orange, #ffb24d), var(--dark-orange, #d97706)); border: none; color: #241400; font-weight: 700; }
</style>

<div class="container files-browser">
    <div class="fb-head">
        <div>
            <h1 class="fb-title"><?= htmlspecialchars($document_type['name'] ?? 'Documents') ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="./?p=folders"><i class="fa fa-home"></i> Home</a></li>
                    <li class="breadcrumb-item"><a href="./?p=sub_folders&id=<?=$folder_detail['folder_id']?>"><?=htmlspecialchars($folder_detail['folder_name'])?></a></li>
                    <li class="breadcrumb-item"><a href="./?p=document_types&sub_folder_id=<?=$sub_folder_id?>"><?=htmlspecialchars($sub_folder['name'])?></a></li>
                    <li class="breadcrumb-item active"><?=htmlspecialchars($document_type['name'])?></li>
                </ol>
            </nav>
        </div>
        <span class="fb-chip"><i class="fa fa-file-pdf"></i> <?= number_format(is_array($folders) ? count($folders) : 0) ?> files</span>
    </div>

    <div class="fb-search">
        <input type="text" id="search-box" placeholder="Search files...">
    </div>
    <!-- Files Display -->
    <div class="search-result" id="search-result">
        <div class="row">
            <?php
            if (is_array($folders) && !empty($folders)) {
                foreach ($folders as $index => $folder) {
                    $description = $folder['description'] ?? $folder['name'];
                    echo "<div class='col-6 col-sm-4 col-md-3 col-lg-2 mb-4 file-item'>
                        <a href='javascript:void(0);' class='preview-file'
                           data-path='{$folder['path']}'
                           data-filename='{$description}'>
                            <div class='file-card'>
                                <div class='file-icon'>
                                    " . ($folder['completed'] === '1' ? "<i class='fa fa-file-pdf'></i>" : "<i class='fa fa-file-text'></i>") . "
                                </div>
                                <div class='file-name'>{$description}</div>
                                " . ($folder['completed'] === '1' ? "<div class='file-info'>{$folder['document_date']}</div>" : '') . "
                            </div>
                        </a>
                    </div>";
                }
            } else {
                echo "<div class='col-12 text-center'>
                        <div class='alert alert-info'>No files found</div>
                      </div>";
            }
            ?>
        </div>
    </div>
</div>

<!-- PDF Preview Modal (Bootstrap 4 syntax) -->
<div class="modal fade" id="pdfModal" tabindex="-1" role="dialog" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfModalLabel">PDF Preview</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div id="pdfViewer" style="height: 80vh;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-custom" data-dismiss="modal">Close</button>
                <a href="#" id="downloadPdf" class="btn btn-custom" target="_blank">
                    <i class="fa fa-download"></i> Download
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Scripts - jQuery, Bootstrap, and PDFObject are already loaded in main.php -->
<script>
    $(document).ready(function(){
        // Search functionality
        $("#search-box").on("keyup", function() {
            let searchText = $(this).val().toLowerCase();

            $(".file-item").each(function() {
                let fileName = $(this).find(".file-name").text().toLowerCase();
                let fileDescription = $(this).find(".file-card").attr("title") || "";
                fileDescription = fileDescription.toLowerCase();

                if (fileName.includes(searchText) || fileDescription.includes(searchText)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });

            if ($(".file-item:visible").length === 0) {
                if ($("#noResults").length === 0) {
                    $("#search-result .row").append('<div id="noResults" class="col-12 text-center mt-4">No files found</div>');
                }
            } else {
                $("#noResults").remove();
            }
        });

        // PDF preview functionality
        $(document).on('click', '.preview-file', function(e) {
            e.preventDefault();
            const filePath = $(this).data('path');
            const fileName = $(this).data('filename');
            // Use serve_file.php to serve files from outside web root
            const fullPath = "<?php echo BASE_URL; ?>serve_file.php?file=" + encodeURIComponent(filePath);

            $('#pdfModalLabel').text(fileName);
            $('#downloadPdf').attr('href', fullPath);

            PDFObject.embed(fullPath, "#pdfViewer", {
                height: "80vh",
                fallbackLink: "<p>This browser does not support inline PDFs. <a href='[url]'>Click here to download the PDF</a></p>"
            });

            $('#pdfModal').modal('show');
        });

        // Clear PDF viewer when modal is closed
        $('#pdfModal').on('hidden.bs.modal', function () {
            $('#pdfViewer').empty();
        });

        // Clear search on escape key
        $(document).on('keydown', function(e) {
            if (e.key === "Escape") {
                $("#search-box").val('').trigger('keyup');
            }
        });
    });
</script>