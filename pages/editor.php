<?php
    include_once ("./config.php");
    $document_types = [
            'PV' => 'Payment Voucher',
            'RCPT' => 'Receipt'
    ];
    $db = new DB();

    $file_path = "../allfiles/pf-archives/";
    $pf_files = array_slice(scandir($file_path), 2);
    $folders = $db->fetch('archive_document_folders');
//    $sub_folders = Utility::query('SELECT adsf.*,adf.name AS folder_name FROM archive_document_sub_folders adsf
//    JOIN archive_document_folders adf ON(adf.id = adsf.archive_document_folder_id)');

    $user_id = $user_id = $_SESSION[SESSION_NAME]['user_id'];
    $user = new User($user_id);

$user_group_relation = Utility::query("SELECT user_group FROM user_group_relation WHERE user = $user_id",'SELECT','ROW');
$user_group_id = $user_group_relation['user_group'];
$sub_folders = Utility::query("SELECT adf.*,adfs.name AS folder_name  FROM config_folder_access_rights cfar
    JOIN archive_document_sub_folders adf ON (adf.id = cfar.folder_sub_id) 
    JOIN archive_document_folders adfs ON(adfs.id = adf.archive_document_folder_id)
WHERE cfar.type = 'SUB FOLDER' AND cfar.user_group = $user_group_id ");

$document_types = Utility::query("SELECT dt.* FROM config_folder_access_rights cfar JOIN
                                        document_types dt ON (dt.id = cfar.folder_sub_id) WHERE cfar.type = 'DOCUMENT TYPE' AND cfar.user_group = $user_group_id
");

?>
<style>
    /* ===== Editor theming (matches app visual language) ===== */
    body #left-dock {
        background: linear-gradient(180deg, rgba(31, 26, 21, 0.96), rgba(17, 14, 11, 0.98)) !important;
        border-right: 1px solid var(--border-color, rgba(240,140,0,0.16)) !important;
    }
    body #container, #pdf-loader { background: #0b0907 !important; }
    #pdf-loader { height: 100%; }
    #pdf-loader:empty { display: flex; align-items: center; justify-content: center; }
    #pdf-loader:empty::before {
        content: "\f1c1\00a0\00a0 Use the arrows to load a document";
        font-family: var(--font-body, 'Hanken Grotesk', sans-serif); color: #6f675e; font-size: 0.95rem;
    }
    #pdf-loader:empty::before { content: "Use the « » arrows to load a document"; }

    /* Form */
    .editor-form label, .file-name-display label {
        color: #cfc6ba !important; font-weight: 700 !important; font-size: 0.74rem !important;
        text-transform: uppercase; letter-spacing: 0.06em; display: flex; align-items: center; gap: 0.4rem;
    }
    .editor-form label i { color: var(--primary-orange, #f08c00); }
    .editor-form .form-control, .file-name-display .form-control, .file-id-display {
        background: var(--bg-dark, #17130f) !important; border: 1.5px solid rgba(255, 255, 255, 0.08) !important;
        color: #f6efe5 !important; border-radius: 10px !important;
    }
    .editor-form .form-control:focus, .file-name-display .form-control:focus, .file-id-display:focus {
        border-color: var(--primary-orange, #f08c00) !important; box-shadow: 0 0 0 3px rgba(240, 140, 0, 0.12) !important;
    }
    .file-id-display { text-align: center; font-weight: 700; }
    .label-actions { margin-left: auto; display: inline-flex; gap: 0.3rem; }

    /* Nav + icon buttons */
    .nav-btn {
        background: rgba(240, 140, 0, 0.1) !important; border: 1px solid var(--border-color, rgba(240,140,0,0.2)) !important;
        color: var(--light-orange, #ffb24d) !important; border-radius: 10px !important; width: 40px; height: 40px;
        display: grid; place-items: center; transition: all 0.2s ease;
    }
    .nav-btn:hover { background: rgba(240, 140, 0, 0.2) !important; color: #fff !important; }
    .btn-icon {
        background: rgba(240, 140, 0, 0.1); border: 1px solid var(--border-color, rgba(240,140,0,0.2));
        color: var(--light-orange, #ffb24d); border-radius: 8px; width: 28px; height: 28px; cursor: pointer; transition: all 0.2s ease;
    }
    .btn-icon:hover { background: rgba(240, 140, 0, 0.2); color: #fff; }

    /* Save + checkbox */
    .btn-save {
        background: linear-gradient(135deg, var(--light-orange, #ffb24d), var(--dark-orange, #d97706)) !important;
        border: none !important; color: #241400 !important; font-weight: 700 !important; border-radius: 12px !important;
        padding: 0.8rem !important; width: 100%; box-shadow: 0 10px 24px -10px rgba(240, 140, 0, 0.6);
    }
    .btn-save:hover { filter: brightness(1.05); color: #241400 !important; }
    .checkbox-container, .checkbox-label, .details-toggle { color: #d7cfc4 !important; }
    .details-toggle { cursor: pointer; }

    /* select2 (used here and elsewhere) — dark theme */
    .select2-container--default .select2-selection--single {
        background: var(--bg-dark, #17130f) !important; border: 1.5px solid rgba(255, 255, 255, 0.08) !important;
        border-radius: 10px !important; height: 40px !important; display: flex; align-items: center;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered { color: #f6efe5 !important; line-height: normal !important; }
    .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #6f675e !important; }
    .select2-dropdown { background: #17130f !important; border: 1px solid var(--border-color, rgba(240,140,0,0.2)) !important; }
    .select2-results__option { color: #d7cfc4 !important; }
    .select2-container--default .select2-results__option--highlighted[aria-selected] { background: var(--primary-orange, #f08c00) !important; color: #241400 !important; }
    .select2-search__field { background: var(--bg-dark, #17130f) !important; color: #f6efe5 !important; border: 1px solid var(--border-color, rgba(240,140,0,0.2)) !important; }

    /* New sub-folder modal */
    #new-sub-folder-modal .modal-content { background: #110e0b !important; border: 1px solid var(--border-color, rgba(240,140,0,0.2)) !important; border-radius: 16px !important; color: #f6efe5; }
    #new-sub-folder-modal .modal-header, #new-sub-folder-modal .modal-footer { border-color: var(--border-color, rgba(240,140,0,0.16)) !important; }
    #new-sub-folder-modal .modal-title { font-family: var(--font-display, 'Bricolage Grotesque', sans-serif); color: #fff; }
    #new-sub-folder-modal label { color: var(--text-muted, #9c9389); }
    #new-sub-folder-modal .form-control { background: var(--bg-dark, #17130f) !important; border: 1.5px solid rgba(255, 255, 255, 0.08) !important; color: #f6efe5 !important; border-radius: 10px !important; }
    #new-sub-folder-modal .form-control:focus { border-color: var(--primary-orange, #f08c00) !important; box-shadow: 0 0 0 3px rgba(240, 140, 0, 0.12) !important; }
    #new-sub-folder-modal .close { color: var(--text-muted, #9c9389); opacity: 1; text-shadow: none; background: none; border: none; font-size: 1.5rem; cursor: pointer; }
    #new-sub-folder-modal .btn-danger { background: rgba(255, 107, 93, 0.14) !important; border: 1px solid rgba(255, 107, 93, 0.3) !important; color: #ff7a66 !important; }
    #new-sub-folder-modal .btn-custom:not(.btn-danger) { background: linear-gradient(135deg, var(--light-orange, #ffb24d), var(--dark-orange, #d97706)); border: none; color: #241400; font-weight: 700; }
</style>
<div id="left-dock">
    <div class="editor-sidebar">
        <!-- Navigation Header -->
        <div class="editor-nav">
            <button class="nav-btn nav-prev" onclick="previousFile()" title="Previous File">
                <i class="fa fa-chevron-left"></i>
            </button>
            <div class="file-indicator">
                <input type="number" class="file-id-display" readonly id="file-id" placeholder="#" />
            </div>
            <button class="nav-btn nav-next" onclick="nextFile()" title="Next File">
                <i class="fa fa-chevron-right"></i>
            </button>
        </div>

        <!-- File Name Display -->
        <div class="file-name-display">
            <input type="text" id="input-name" autocomplete="off" class="form-control" readonly required placeholder="File name" />
        </div>

        <!-- Scrollable Form Area -->
        <form class="editor-form" id="editor-form">
            <input type="hidden" name="id" id="input-id" value="" required />

            <!-- Document Type & Year Row -->
            <div class="editor-row">
                <div class="form-group form-group-half">
                    <label><i class="fa fa-file-alt"></i> Type</label>
                    <select id="input-document_type" name="document_type" class="form-control select2">
                        <option></option>
                        <?php
                        foreach ($document_types as $index => $type) {
                            echo "<option value='{$type['id']}'>{$type['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group form-group-half">
                    <label><i class="fa fa-calendar"></i> Year</label>
                    <select id="input-year" class="form-control select2">
                        <?php
                        foreach (range(date('Y'), date('Y')-20) as $index => $year) {
                            echo "<option value='{$year}'>{$year}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Document Number -->
            <div class="form-group">
                <label><i class="fa fa-hashtag"></i> Document Number</label>
                <input type="text" id="input-number" autocomplete="off" class="form-control" placeholder="Enter document number" />
            </div>

            <!-- Sub Folder -->
            <div class="form-group">
                <label>
                    <i class="fa fa-folder"></i> Sub Folder
                    <span class="label-actions">
                        <button type="button" class="btn-icon" onclick="loadSubFolders()" title="Refresh folders">
                            <i class="fa fa-sync-alt"></i>
                        </button>
                        <?php if ($user->can('SUB_FOLDER_ADDITION')): ?>
                        <button type="button" class="btn-icon" onclick="newSubFolder()" title="Add new folder">
                            <i class="fa fa-plus"></i>
                        </button>
                        <?php endif; ?>
                    </span>
                </label>
                <select id="input-sub_folder_id" class="form-control select2">
                    <option></option>
                    <?php
                    foreach ($sub_folders as $index => $sub_folder) {
                        echo "<option value='{$sub_folder['id']}' title='{$sub_folder['description']}'>{$sub_folder['name']} - {$sub_folder['folder_name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Payee Name -->
            <div class="form-group">
                <label><i class="fa fa-user"></i> Payee Name</label>
                <input type="text" id="input-payee_name" autocomplete="off" class="form-control" placeholder="Enter payee name" required />
            </div>

            <!-- Document Date & Cheque Row -->
            <div class="editor-row">
                <div class="form-group form-group-half">
                    <label><i class="fa fa-calendar-day"></i> Doc Date</label>
                    <input type="text" id="input-document_date" value="<?=date('Y-m-d')?>" autocomplete="off" class="form-control datepicker" required />
                </div>
                <div class="form-group form-group-half">
                    <label><i class="fa fa-money-check"></i> Cheque #</label>
                    <input type="text" id="input-cheque_number" autocomplete="off" class="form-control" placeholder="Optional" />
                </div>
            </div>

            <!-- Details (Collapsible) -->
            <div class="form-group details-group">
                <label class="details-toggle" onclick="$('#input-description').slideToggle(); $(this).toggleClass('open');">
                    <i class="fa fa-align-left"></i> Details
                    <i class="fa fa-chevron-down toggle-icon"></i>
                </label>
                <textarea style="display:none;" rows="4" class="form-control" id="input-description" placeholder="Additional details..."></textarea>
            </div>
        </form>

        <!-- Fixed Bottom Actions -->
        <div class="editor-actions">
            <?php if ($user->can('COMPLETE_EDITION')): ?>
            <label class="checkbox-container">
                <input type="checkbox" name="completed" id="input-completed" />
                <span class="checkmark"></span>
                <span class="checkbox-label">Mark as completed</span>
            </label>
            <?php endif; ?>
            <button class="btn-save" onclick="saveChanges()">
                <i class="fa fa-save"></i> Save Document
            </button>
        </div>
    </div>
</div>
<div id="container">
    <div id="pdf-loader"></div>
</div>


<div class="modal" id="new-sub-folder-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add a New Sub Folder</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form name="new_sub_folder_form" id="new-sub-folder-form" method="post" autocomplete="off">
                    <div class="form-group">
                        <label >Sub Folder Name *</label>
                        <input class="form-control" type="text" name="name" id="input-sub-folder-name" required />
                    </div>
                    <div class="form-group">
                        <label>Folder *</label>
                        <select name="archive_document_folder_id" id="archive_document_folder_id" class="form-control">
                            <option></option>
                            <?php
                            foreach ($folders as $index => $folder) {
                                echo "<option value='{$folder['id']}'>{$folder['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label >Description</label>
                        <textarea class="form-control" name="description" id="input-folder-description"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-custom" onclick="submitNewSubFolder()">Save changes</button>
                <button type="button" class="btn btn-danger btn-custom" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>

    function nextFile() {
            loadFile('next');
    }
    function previousFile() {
            loadFile('previous');
    }

    function loadFile(direction) {
        console.log('loading file...');
        $("#pdf-loader").html("<div class='loader-icon'><i class='fa fa-spinner fa-spin fa-3x'></i></div>");
        $("#input-description, #file-id").html("");
        $.ajax({
            type: "POST",
            url: "./ajax.php?fx=next_file",
            data: "direction="+direction,
            cache: false,
            success: function(data){
                if(data && data != '""'){
                    data = JSON.parse(data);
                    console.log('Returned', data);
                    var id = data['id'];
                    var name = data['name'];
                    var document_type = data['document_type'];
                    var description = data['description'];
                    var year = data['year'];
                    var url = data['url']
                    var number = data['number'];
                    var payee_name = data['payee_name'];
                    var sub_folder_id = data['sub_folder_id'];
                    var document_date = data['document_date'];
                    var cheque_number = data['cheque_number'];
                    var duplicate = data['duplicate'];
                    // Use serve_file.php to serve files from outside web root
                    var file_path = "<?php echo BASE_URL; ?>serve_file.php?file="+encodeURIComponent(url);
                    console.log(file_path);
                    if(url.toLowerCase().endsWith('.pdf')) {
                        PDFObject.embed(file_path, "#pdf-loader");
                        $("#input-id").val(id);
                        $("#file-id").val(id);
                        $("#input-name").val(name);
                        $("#input-document_type").val(document_type);
                        $("#input-year").val(year);
                        $("#input-description").val(description);
                        $("#input-number").val(number);
                        $("#input-payee_name").val(payee_name);
                        $("#input-sub_folder_id").val(sub_folder_id).trigger('change');
                        $("#input-document_date").val(document_date);
                        $("#input-cheque_number").val(cheque_number);
                        $("#input-completed").prop("checked", '');
                        $("#input-duplicate").prop("checked", (duplicate == '1' ? true: false));
                        $('#input-year, #input-document_type').select2().trigger('change');
                        $('#input-document_date').datepicker('destroy').datepicker({
                            format: 'yyyy/mm/dd',
                            changeYear: true,
                            autoclose: true,
                            defaultViewDate: {year: year}
                        })
                    } else {
                        $("#pdf-loader").html("<div class='loader-icon'>Invalid Response!</div>");
                        $("#input-description").html("");
                    }

                } else {
                    $("#pdf-loader").html("<div class='loader-icon'>No file</div>");
                    $("#input-description").html("");
                }
            }
        });

    }


    function saveChanges() {
        var id = $("#input-id").val();
        var name = $("#input-name").val();
        var document_type = $("#input-document_type").val();
        var year = $("#input-year").val();
        var description = $("#input-description").val();
        console.log('Desc', description);
        var number = $("#input-number").val();
        var sub_folder_id = $("#input-sub_folder_id").val();
        var document_date = $("#input-document_date").val();
        var cheque_number = $("#input-cheque_number").val();
        var payee_name = $("#input-payee_name").val();

        var completed = $("#input-completed").prop('checked');
        var duplicate = $("#input-duplicate").prop('checked');

        if(id == undefined || id == '') {
            return;
        }

        $.ajax({
            type: "POST",
            url: "./ajax.php?fx=save_data",
            data: "id="+id+"&name=" + name + "&document_type=" + document_type + "&year=" + year + "&description=" + description + "&number="+number+ "&sub_folder_id="+sub_folder_id + "&payee_name="+payee_name + "&document_date="+document_date+"&cheque_number=" + cheque_number + "&completed="+(completed ? "1" : "0")+"&duplicate="+(duplicate ? "1" : "0"),
            cache: false,
            success: function (data) {
                if(data && (data == '1' || data=='true')) {
                    Swal.fire({html: "Data saved!", title: 'Saved', type: 'success'});
                    if(completed) {
                        nextFile();
                    }
                } else {
                    Swal.fire('Failed');
                }
            }
        });
    }

    function loadSubFolders() {
        $.ajax({
            type: "POST",
            url: "./ajax.php?fx=load_sub_folders",
            data: "",
            cache: false,
                success: function (data) {
                if(data && (data !== '')) {
                    $("#input-sub_folder_id").html(data);
                    $('#input-sub_folder-id').select2().trigger('change');
                } else {
                    console.log('SUB_FOLDER_LOAD', data);
                }
            }
        });
    }

    function newSubFolder() {
        $("#new-sub-folder-modal").modal('show');
    }

    function submitNewSubFolder() {
        var folder_name = $("#input-sub-folder-name").val();
        var folder_description = $("#input-folder-description").val();
        var archive_document_folder_id = $("#archive_document_folder_id").val();
        if(folder_name !== '') {
            $.ajax({
                type: "POST",
                url: "./ajax.php?fx=new_sub_folder",
                data: "name="+folder_name+"&description="+folder_description+"&archive_document_folder_id="+archive_document_folder_id,
                cache: false,
                success: function(data){
                    console.log(data);
                    if(data && data == '1'){
                        Swal.fire({html: "Sub Folder Created Successfully!", title: 'Success', type: 'success'});
                        loadFolders();
                        $("#input-sub-folder-name, #input-folder-description, #archive_document_folder_id").val('');
                        $("#new-sub-folder-modal").modal('hide');
                    } else {
                        Swal.fire({html: "Sub Folder not created!", title: 'Failed', type: 'error'});
                        $("#new-sub-folder-modal").modal('hide');
                    }
                }
            });
        } else {

        }
    }
</script>