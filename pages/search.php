<?php
include_once ("./config.php");
$db = new DB();
$user_id = $user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);
$user_group_relation = Utility::query("SELECT user_group FROM user_group_relation WHERE user = $user_id",'SELECT','ROW');
$user_group_id = $user_group_relation['user_group'];
$sub_folders = Utility::query("SELECT adf.*,adfs.name AS folder_name  FROM config_folder_access_rights cfar
    JOIN archive_document_sub_folders adf ON (adf.id = cfar.folder_sub_id)
    JOIN archive_document_folders adfs ON(adfs.id = adf.archive_document_folder_id)
WHERE cfar.type = 'SUB FOLDER' AND cfar.user_group = $user_group_id ");
$sub_folders = $db->fetch('archive_document_sub_folders');
$q = "SELECT adf.*  FROM config_folder_access_rights cfar
    JOIN archive_document_folders adf ON (adf.id = cfar.folder_sub_id) WHERE cfar.type = 'FOLDER' AND cfar.user_group = $user_group_id";
$folders = $db->query($q, 'SELECT');
$document_types = Utility::query("SELECT dt.* FROM config_folder_access_rights cfar JOIN
                                        document_types dt ON (dt.id = cfar.folder_sub_id) WHERE cfar.type = 'DOCUMENT TYPE' AND cfar.user_group = $user_group_id
");
?>

<br />
<div class="container">
<form class="search-form">
    <div class="row">
        <div class="col-md-2">
            <div class="form-group">
                <label class="control-label">Year</label>
                <select name="year" id="input-year" class="form-control">
                    <option value="2024">2024</option>
                    <?php
                        foreach(range(date('Y'), 2008) as $year) {
                            echo "<option value='{$year}'>{$year}</option>";
                        }
                    ?>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="" >Document Date</label>
                <input type="text" name="document_date" id="input-document_date" value="<?=date('Y-m-d')?>" autocomplete="off" class="form-control datepicker" required />
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Folder</label>
                <select name="folder_id" id="input-folder_id" class="form-control">
                    <option></option>
                    <?php
                        foreach ($folders as $index => $type) {
                            echo "<option value='{$type['id']}'>{$type['name']}</option>";
                        }
                    ?>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Sub Folder</label>
                <select name="sub_folder_id" id="input-sub_folder_id" class="form-control">
                    <option></option>
                    <?php
                        foreach ($sub_folders as $index => $type) {
                            echo "<option value='{$type['id']}'>{$type['name']}</option>";
                        }
                    ?>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Document Type</label>
                <select name="document_type" id="input-document_type" class="form-control">
                    <option></option>
                    <?php
                        foreach ($document_types as $index => $type) {
                            echo "<option value='{$type['id']}'>{$type['name']}</option>";
                        }
                    ?>
                </select>
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                <label>Details</label>
                <textarea name="description" id="input-description" rows="1" class="form-control"></textarea>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Document Number</label>
                <textarea name="number" id="input-number" rows="1" class="form-control"></textarea>
            </div>
        </div>
        <div class="col-md-1">
            <div class="form-group">
                <label>Search</label>
                <input type="button" onclick="search()" class="btn btn-custom" value="Go" />
            </div>
        </div>
    </div>
</form>

    <div class="search-result" id="search-result" style="min-height:400px; overflow: hidden; overflow-y: scroll;">
<!--        <div class="text-center">Still Under Construction</div>-->
    </div>
    <br>
</div>



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
        var document_type = $("#input-document_type").val();
        var year = $("#input-year").val();
        var description = $("#input-description").val();
        var number = $("#input-number").val();
        var document_date = $("#input-document_date").val();
        var folder_id = $("#input-folder_id").val();
        var sub_folder_id = $("#input-sub_folder_id").val();
        $("#search-result").html("<div class='loader-icon'><i class='fa fa-spinner fa-spin fa-3x'></i></div>")
        if(year == '' || (description == '' && number == '' && document_type == '' && document_date == '' && folder_id == '' && sub_folder_id == '')) {
            return;
        }

        $.ajax({
            type: "POST",
            url: "./ajax.php?fx=search_file",
            data: "document_type=" + document_type + "&document_date=" + document_date + "&year=" + year+ "&folder_id=" + folder_id+ "&sub_folder_id=" + sub_folder_id + "&description=" + description + "&number="+ number + "",
            cache: false,
            success: function (data) {
                if(data && (data != '')) {
                    json_data = JSON.parse(data);
                    if(typeof(json_data) === 'object') {
                        console.log(json_data);
                        if(json_data.id !== undefined) {
                             $("#search-result").html(data);

                            showFile(data.path);
                        } else {
                            if(json_data.length > 0) {
                                // alert('hello');
                                $("#search-result").html('');
                                var html = "<ul class='list-group'>";
                                for(var i = 0; i < json_data.length; i++) {
                                    var obj = json_data[i];
                                    html += "<li class='list-group-item' onclick='showFile(\"" + obj['path'] + "\")'>PF/ARCH/" + obj['id'] + " [" + obj['name'] + "] " + " [" + obj['description'] + "] " + "</li>";

                                }
                                html += "</ul>";
                                $("#search-result").append(html);
                            } else {
                                $("#search-result").html("<div class='loader-icon text-center'>No result!</div>");
                            }
                        }
                    } else {
                        $("#search-result").html("Invalid result!");
                    }
                } else {
                    Swal.fire('Search Failed');
                }
            }
        });

        // function trimText(text) {
        //     return text.substring(0,60) + "...";
        // }


    }
</script>
