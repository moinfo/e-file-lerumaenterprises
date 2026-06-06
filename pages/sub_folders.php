<?php
include_once ("./config.php");
$db = new DB();
$document_types = $db->fetch('document_types');
$user_id = (int)$_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);
$user_group_relation = Utility::query("SELECT user_group FROM user_group_relation WHERE user = $user_id",'SELECT','ROW');
$user_group_id = (int)$user_group_relation['user_group'];

// Get folder ID from request if it exists
$request_id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : null;

// Query for main folders
$main_folder_query = "SELECT adf.*  FROM config_folder_access_rights cfar
    JOIN archive_document_folders adf ON (adf.id = cfar.folder_sub_id)
    WHERE cfar.type = 'FOLDER' AND cfar.user_group = $user_group_id";

// Query for sub folders if a folder is selected
$sub_folder_query = $request_id ? "SELECT adf.*  FROM config_folder_access_rights cfar
    JOIN archive_document_sub_folders adf ON (adf.id = cfar.folder_sub_id)
    WHERE cfar.user_group = $user_group_id AND cfar.type = 'SUB FOLDER'
    AND adf.archive_document_folder_id = $request_id" : null;

$folders = $db->query($main_folder_query, 'SELECT');
$sub_folders = $request_id ? $db->query($sub_folder_query, 'SELECT') : [];
$folder_detail = $request_id ? $db->query("SELECT * FROM archive_document_folders WHERE id = $request_id", 'SELECT', 'ROW') : null;
?>


<div class="container">
    <!-- Search Bar -->
    <div class="row mt-4">
        <div class="col-12 col-md-8 mx-auto">
            <div class="search-bar">
                <input type="text" id="folderSearch" class="form-control" placeholder="Search folders...">
            </div>
        </div>
    </div>

    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./?p=folders"><i class="bi bi-house-door"></i> Home</a></li>
            <?php if($request_id): ?>
                <li class="breadcrumb-item active"><?= $folder_detail['name'] ?></li>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="search-result" id="search-result">
        <div class="row">
            <?php
            // Display folders or sub-folders based on request
            $display_items = $request_id ? $sub_folders : $folders;

            foreach ($display_items as $item) {
                $id = (int)$item['id'];
                $files_query = $request_id ?
                    "SELECT * FROM archives WHERE sub_folder_id = $id" :
                    "SELECT a.* FROM archives a 
                     JOIN archive_document_sub_folders adsf ON (adsf.id = a.sub_folder_id)
                     JOIN archive_document_folders adf ON (adf.id = adsf.archive_document_folder_id)
                     WHERE adf.id = '$id'";

                $files = count($db->query($files_query, 'SELECT'));
                $link = $request_id ?
                    "./?p=document_types&sub_folder_id=$id" :
                    "./?p=sub_folders&id=$id";

                echo "<div class='col-6 col-sm-4 col-lg-3 mb-3'>
                    <a href='{$link}' style='text-decoration: none;'>
                        <div class='document-card'>
                            <div class='card-header'>
                                <h3 class='folder-title'>
                                    <i class='bi bi-folder2-fill folder-icon'></i>
                                    {$item['name']}
                                </h3>
                            </div>
                            <div class='card-body'>
                                <div class='folder-description'>" .
                    (isset($item['description']) ? $item['description'] : '') .
                    "</div>
                                <div class='file-count'>
                                    <i class='bi bi-file-text'></i>
                                    {$files} Files
                                </div>
                            </div>
                        </div>
                    </a>
                </div>";
            }
            ?>
        </div>
    </div>
</div>

<script>
    $(document).ready(function(){
        // Search functionality
        $("#folderSearch").on("keyup", function() {
            let searchText = $(this).val().toLowerCase();

            $(".document-card").each(function() {
                let folderName = $(this).find(".folder-title").text().toLowerCase();
                let folderDescription = $(this).find(".folder-description").text().toLowerCase();
                let folderCard = $(this).closest('.col-12');

                if (folderName.includes(searchText) || folderDescription.includes(searchText)) {
                    folderCard.removeClass('folder-hidden');
                } else {
                    folderCard.addClass('folder-hidden');
                }
            });

            // Show no results message
            if ($(".document-card").closest('.col-12:not(.folder-hidden)').length === 0) {
                if ($("#noResults").length === 0) {
                    $(".row").append('<div id="noResults" class="col-12 text-center mt-4">No folders found</div>');
                }
            } else {
                $("#noResults").remove();
            }
        });

        // Clear search on escape key
        $(document).on('keydown', function(e) {
            if (e.key === "Escape") {
                $("#folderSearch").val('').trigger('keyup');
            }
        });

        // Your existing showFile function
        function showFile(path) {
            var file_path = "<?php echo BASE_URL; ?>serve_file.php?file="+encodeURIComponent(path);
            PDFObject.embed(file_path, "#search-result");
        }
    });
</script>