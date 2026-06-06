<?php
include_once ("./config.php");
if (!isset($_REQUEST["sub_folder_id"])) {
    die("Sub folder ID is required");
}

$request_id = isset($_REQUEST['sub_folder_id']) ? (int)$_REQUEST['sub_folder_id'] : null;
$user_id = (int)$_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);
$user_group_relation = Utility::query("SELECT user_group FROM user_group_relation WHERE user = $user_id",'SELECT','ROW');
$user_group_id = (int)$user_group_relation['user_group'];
$db = new DB();

// Get document types
try {
    $q = "SELECT dt.*, dt.name AS document_type_name
          FROM config_folder_access_rights cfar
          JOIN document_types dt ON (dt.id = cfar.folder_sub_id)
          WHERE cfar.user_group = $user_group_id
          AND cfar.type = 'DOCUMENT TYPE'";
    $folders = $db->query($q, 'SELECT');
} catch (Exception $e) {
    $folders = [];
    echo "Error loading document types: " . $e->getMessage();
}

// Get folder details
try {
    $q1 = "SELECT adsf.*, adf.name AS folder_name, adf.id AS folder_id
           FROM archive_document_sub_folders adsf
           JOIN archive_document_folders adf ON (adf.id = adsf.archive_document_folder_id)
           WHERE adsf.id = $request_id";
    $folder_detail = $db->query($q1, 'SELECT', 'ROW');
} catch (Exception $e) {
    $folder_detail = null;
    echo "Error loading folder details: " . $e->getMessage();
}

// Verify we have the necessary data
if (!$folder_detail || !$folders) {
    echo "<div class='alert alert-warning'>Unable to load complete folder information</div>";
}
?>
<br/>

<div class="container">
    <!-- Search Bar -->
    <div class="row mt-4">
        <div class="col-12 col-md-8 mx-auto">
            <div class="search-bar">
                <input type="text" id="documentSearch" class="form-control" placeholder="Search document types...">
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./?p=folders"><i class="bi bi-house-door"></i> Home</a></li>
            <?php if($folder_detail): ?>
                <li class="breadcrumb-item">
                    <a href="./?p=sub_folders&id=<?php echo htmlspecialchars($folder_detail['folder_id']); ?>">
                        <?php echo htmlspecialchars($folder_detail['folder_name']); ?>
                    </a>
                </li>
                <li class="breadcrumb-item active">
                    <?php echo htmlspecialchars($folder_detail['name']); ?>
                </li>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="search-result" id="search-result">
        <div class="row">
            <?php
            if (is_array($folders) && !empty($folders)) {
                foreach ($folders as $index => $folder) {
                    $document_type_id = $folder['id'];
                    $sub_folder_id = $request_id;

                    try {
                        $q = "SELECT * FROM archives 
                              WHERE document_type = '$document_type_id' 
                              AND sub_folder_id = $sub_folder_id";
                        $files = count($db->query($q, 'SELECT'));

                        if($files > 0) {
                            echo "<div class='col-12 col-sm-6 col-lg-4 mb-4 document-item'>
                                    <a href='./?p=files&document_type_id=$document_type_id&sub_folder_id=$sub_folder_id' 
                                       style='text-decoration: none;'>
                                        <div class='document-card'>
                                            <div class='card-header'>
                                                <h3 class='folder-title'>
                                                    <i class='bi bi-file-text me-2'></i>
                                                    " . htmlspecialchars($folder['document_type_name']) . "
                                                </h3>
                                            </div>
                                            <div class='card-body d-flex align-items-center justify-content-center'>
                                                <div class='file-count'>
                                                    <i class='bi bi-files'></i>
                                                    {$files} Files
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>";
                        }
                    } catch (Exception $e) {
                        echo "<div class='alert alert-danger'>Error loading files for document type: " .
                            htmlspecialchars($folder['document_type_name']) . "</div>";
                    }
                }
            } else {
                echo "<div class='col-12'>
                        <div class='alert alert-info'>No document types found</div>
                      </div>";
            }
            ?>
        </div>
    </div>
</div>

<!-- Add Bootstrap Icons CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">

<script>
    $(document).ready(function(){
        // Search functionality
        $("#documentSearch").on("keyup", function() {
            let searchText = $(this).val().toLowerCase();

            $(".document-item").each(function() {
                let docName = $(this).find(".document-title").text().toLowerCase();

                if (docName.includes(searchText)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });

            // Show no results message
            if ($(".document-item:visible").length === 0) {
                if ($("#noResults").length === 0) {
                    $("#search-result .row").append('<div id="noResults" class="col-12 text-center mt-4">No document types found</div>');
                }
            } else {
                $("#noResults").remove();
            }
        });

        // Clear search on escape key
        $(document).on('keydown', function(e) {
            if (e.key === "Escape") {
                $("#documentSearch").val('').trigger('keyup');
            }
        });
    });
</script>