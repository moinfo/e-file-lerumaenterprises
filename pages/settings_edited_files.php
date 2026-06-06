<?php
include_once ("./config.php");
$db = new DB();
if (isset($_POST['addArchive']) && Utility::isNewSubmit()) {
    $archive = new Archive();
    $data = [
        'name' => $_POST['name'],
        'description' => $_POST['description']
    ];
    if (Utility::insert('archives', $data)) {
        Utility::notify('Archive Added', 'success', 'Successful!');
    } else {
        Utility::notify('Failed to Add Archive', 'error', 'Failed!');
    }
}

if (isset($_POST['editArchive']) && Utility::isNewSubmit()) {

    $data = [
        'name' => $_POST['name'],
        'document_type' => $_POST['document_type'],
        'sub_folder_id' => $_POST['sub_folder_id'],
        'year' => $_POST['year'],
        'document_date' => $_POST['document_date'],
        'description' => $_POST['description'],
        'id' => $_POST['id']
    ];
    $id = $data['id'];
    if (Utility::update('archives', $id, $data)) {
        Utility::notify('Updated', 'success', 'Successful!');
    } else {
        Utility::notify('Failed to update', 'error', 'Failed!');
    }

}

$year = isset($_POST['year']) ? (int)$_POST['year'] : 2024;
$document_type_id = isset($_POST['document_type_id']) ? (int)$_POST['document_type_id'] : null;
$folder_id = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
$sub_folder_id = isset($_POST['sub_folder_id']) ? (int)$_POST['sub_folder_id'] : null;
$description = $_POST['description'] ?? '';

$q = "SELECT a.*,dt.name AS document_type_name,adf.name AS folder_name,adsf.name AS sub_folder_name FROM archives a 
    JOIN document_types dt ON (a.document_type = dt.id)
    JOIN archive_document_sub_folders adsf ON (a.sub_folder_id = adsf.id) 
    JOIN archive_document_folders adf ON (adsf.archive_document_folder_id = adf.id) 

WHERE a.year = '{$year}' ";
if($document_type_id != null) {
    $q .= "AND a.document_type = '{$document_type_id}' ";
}
if($sub_folder_id != null) {
    $q .= "AND a.sub_folder_id = '{$sub_folder_id}' ";
}
if($folder_id != null) {
    $q .= "AND adf.id = '{$folder_id}' ";
}
if($description != null) {
    $description = str_replace(' ', '%', trim($description));
    $description = Xcrud_db::get_instance()->escape('%' . $description . '%');
    $q .= "AND a.description LIKE {$description} ";
}

$archives = $db->fetchQuery($q);
$document_types = $db->fetch('document_types');
$folders = $db->fetch('archive_document_folders');
$sub_folders = $db->fetch('archive_document_sub_folders');
$user_id = $user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);
?>
<br />
<div class="container-default">
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group" aria-label="Basic example">
                <a href="./?p=settings" class="btn btn-custom "><i class="fa fa-arrow-left">&nbsp;</i> Back</a>
                <a href="./?p=settings_edited_files" class="btn btn-custom "><i class="fa fa-folder">&nbsp;</i> Edited Files Setting</a>
            </div>
        </div>
    </div>
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group" aria-label="Basic example">
<!--                <button data-toggle="modal" data-target="#add-archive-modal" class="btn btn-custom registerArchive" type="button"><i-->
<!--                            class="fa fa-plus">&nbsp;</i>Add Archive-->
<!--                </button>-->
            </div>
            <div class="float-left">
                <h3>Files</h3>
            </div>
        </div>
    </div>
    <form class="search-form" id="search-form" accept-charset="utf-8" action="" method="post" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?php echo md5(time()); ?>"/>
            <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label class="control-label">Year</label>
                    <select name="year" id="input-year" class="form-control">
                        <option></option>
                        <?php
                        foreach(range(date('Y'), 2008) as $year) {
                            echo "<option value='{$year}'>{$year}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Folder</label>
                    <select name="folder_id" id="input-folder" class="form-control">
                        <option></option>
                        <?php
                        foreach ($folders as $index => $folder) {
                            echo "<option value='{$folder['id']}'>{$folder['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Sub Folder</label>
                    <select name="sub_folder_id" id="input-sub-folder" class="form-control">
                        <option></option>
                        <?php
                        foreach ($sub_folders as $index => $sub_folder) {
                            echo "<option value='{$sub_folder['id']}'>{$sub_folder['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Document Type</label>
                    <select name="document_type_id" id="input-document_type" class="form-control">
                        <option></option>
                        <?php
                        foreach ($document_types as $index => $type) {
                            echo "<option value='{$type['id']}'>{$type['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Details</label>
                    <textarea name="description" id="input-description" rows="1" class="form-control"></textarea>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Search</label>
                    <input type="submit" name="search_form" class="btn btn-custom" value="Go" />
                </div>
            </div>
        </div>
    </form>
    <div class="search-result" id="search-result" style="min-height:400px; ">
        <div class="row">
            <div class="col-md-12">
                <table id="table" data-search="true" data-pagination="true" data-page-size="200" data-show-custom-view="false" data-custom-view="customViewFormatter" data-show-custom-view-button="true">
                    <thead>
                    <tr>
                        <th class="text-center">#</th>
                        <th class="text-center">Name</th>
                        <th class="text-center">Description</th>
                        <th class="text-center">Sub Folder</th>
                        <th class="text-center">Folder</th>
                        <th class="text-center">Document Type</th>
                        <th class="text-center">Year</th>
                        <th class="text-center">Document Date</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $no = 1;
                    foreach ($archives as $index => $archive) {
                        ?>
                        <tr id="archive-row-<?=$archive['id']?>">
                            <td><?=$no?></td>
                            <td class="text-left"><a href="<?=$archive['path']?>"><?=$archive['name']?></a></td>
                            <td  class="text-left"><?=$archive['description']?></td>
                            <td  class="text-left"><?=$archive['sub_folder_name']?></td>
                            <td  class="text-left"><?=$archive['folder_name']?></td>
                            <td  class="text-left"><?=$archive['document_type_name']?></td>
                            <td  class="text-left"><?=$archive['year']?></td>
                            <td  class="text-left"><?=$archive['document_date']?></td>
                            <td   class="text-left">
                                <div class='btn-group'>
                                    <?php if ($user->can('EDITED_FILE_EDITION')){
                                    ?>
                                    <button data-toggle="modal" data-target="#add-archive-modal" title='Edit'
                                            class="btn btn-custom btn-xs edit_archive_btn"
                                            data-name='<?= $archive['name']; ?>'
                                            data-description='<?= $archive['description']; ?>'
                                            data-year='<?= $archive['year']; ?>'
                                            data-document_type='<?= $archive['document_type']; ?>'
                                            data-sub_folder_id='<?= $archive['sub_folder_id']; ?>'
                                            data-document_date='<?= $archive['document_date']; ?>'
                                            data-id='<?= $archive['id']; ?>'><i class='fa fa-edit'></i></button>
                                        <?php
                                    }
                                    ?>
                                    <?php if ($user->can('EDITED_FILE_DELETION')){
                                    ?>
                                    <a onclick='deleteArchive(<?=$archive["id"]?>)' data-toggle='tooltip' title='Delete'
                                       class='btn text-danger btn-xs'><i class='fa fa-trash'></i></a>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </td>
                        </tr>
                        <?php
                        $no++;}
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <br>
</div>
<div id="add-archive-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
     aria-hidden="true" style="display: none;">
    <!--<div class="modal" id="add-archive-modal" tabindex="-1" role="dialog">-->
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="add_archive_form" accept-charset="utf-8" action="" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="token" value="<?php echo md5(time()); ?>"/>
                    <input type="hidden" name="id" id="id" class="form-control">
                    <div class="form-group">
                        <label >Name *</label>
                        <input class="form-control" type="text" name="name" id="name" required />
                    </div>
                    <div class="form-group">
                        <label >Description</label>
                        <textarea class="form-control" type="text" name="description" id="description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Document Type</label>
                        <select name="document_type" id="document_type" class="form-control">
                            <option></option>
                            <?php
                            foreach ($document_types as $index => $type) {
                                echo "<option value='{$type['id']}'>{$type['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sub Folder</label>
                        <select name="sub_folder_id" id="sub_folder_id" class="form-control">
                            <option></option>
                            <?php
                            foreach ($sub_folders as $index => $sub_folder) {
                                echo "<option value='{$sub_folder['id']}'>{$sub_folder['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Year</label>
                        <select name="year" id="year" class="form-control">
                            <option></option>
                            <?php
                            foreach(range(date('Y'), 2008) as $year) {
                                echo "<option value='{$year}'>{$year}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label >Document Date</label>
                        <input class="form-control datepicker"  autocomplete="off" type="text" name="document_date" id="document_date" required />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default waves-effect btn-danger" data-dismiss="modal">Close</button>
                    <button type="submit" id="add-archive-btn" name="addArchive" class="btn btn-info waves-effect waves-light btn-custom">Submit</button>
                    <button style="display:none;" type="submit" id="edit-archive-btn" name="editArchive" class="btn btn-info waves-effect waves-light btn-custom">Update</button>
                </div>
            </form>

        </div>
    </div>
</div>



<script>
    // function addArchive() {
    //     $("#add-archive-modal").modal('show');
    // }
    $(function() {
        $('#table').bootstrapTable({

        })
    });


    $(document).ready(function() {

        $(document).on('click', '.edit_archive_btn', function(){

            $('#id').val($(this).data('id'));
            $('#name').val($(this).data('name'));
            $('#document_type').val($(this).data('document_type'));
            $('#sub_folder_id').val($(this).data('sub_folder_id'));
            $('#document_date').val($(this).data('document_date'));
            $('#description').val($(this).data('description'));
            $('#year').val($(this).data('year'));
            $('#add-archive-btn').hide();
            $('#edit-archive-btn').show();
            $('.modal-title').html("Edit Archive");
        });
        $(document).on('click', '.registerArchive', function(){
            $('#add-archive-btn').show();
            $('#edit-archive-btn').hide();
            $('.modal-title').html("Add Archive");
        });
    });

    function deleteArchive(id) {
        var my_row_id = "archive-row-" + id;
        swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#F08C00CC',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!',
        }).then((result) => {
            if (result.value){
                $.ajax({
                    url: './ajax.php?fx=delete',
                    type: 'POST',
                    data: {table: 'archives', id: id},
                })
                    .done(function(response){
                        $('#'+my_row_id).hide('slow');
                        swal.fire('Deleted!', "Archive has been deleted!", "success");
                    })
                    .fail(function(){
                        swal.fire('Oops...', 'Something went wrong with ajax !', 'error');
                    });
            }

        })

    }



</script>