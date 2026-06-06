<?php
include_once ("./config.php");
$db = new DB();
if (isset($_POST['addSubFolder']) && Utility::isNewSubmit()) {
    $sub_folder = new ArchiveDocumentSubFolder();
    $data = [
        'name' => $_POST['name'],
        'description' => $_POST['description'],
        'archive_document_folder_id' => $_POST['archive_document_folder_id']
    ];
    if (Utility::insert('archive_document_sub_folders', $data)) {
        Utility::notify('SubFolder Added', 'success', 'Successful!');
    } else {
        Utility::notify('Failed to Add SubFolder', 'error', 'Failed!');
    }
}

if (isset($_POST['editSubFolder']) && Utility::isNewSubmit()) {

    $data = [
        'name' => $_POST['name'],
        'archive_document_folder_id' => $_POST['archive_document_folder_id'],
        'description' => $_POST['description'],
        'id' => $_POST['id']
    ];
    $id = $data['id'];
    if (Utility::update('archive_document_sub_folders', $id, $data)) {
        Utility::notify('Updated', 'success', 'Successful!');
    } else {
        Utility::notify('Failed to update', 'error', 'Failed!');
    }

}
$sub_folders = Utility::query('SELECT adsf.*, adf.name AS folder_name FROM archive_document_sub_folders adsf JOIN
archive_document_folders adf ON (adf.id = adsf.archive_document_folder_id)');
$folders = $db->fetch('archive_document_folders');
$user_id = $user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);
?>
<br />
<div class="container">
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group" aria-label="Basic example">
                <a href="./?p=settings" class="btn btn-custom "><i class="fa fa-arrow-left">&nbsp;</i> Back</a>
                <a href="./?p=settings.document_sub_folders" class="btn btn-custom "><i class="fa fa-sub_folder">&nbsp;</i> Document SubFolders Setting</a>
            </div>
        </div>
    </div>
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group" aria-label="Basic example">
                <?php if ($user->can('SUB_FOLDER_ADDITION')){
                ?>
                <button data-toggle="modal" data-target="#add-sub_folder-modal" class="btn btn-custom registerSubFolder" type="button"><i
                            class="fa fa-plus">&nbsp;</i>Add Sub Folder
                </button>
                    <?php
                }
                ?>
            </div>
            <div class="float-left">
                <h3>Sub Folders</h3>
            </div>
        </div>
    </div>
    <div class="search-result" id="search-result" style="min-height:400px; ">
        <div class="row">
            <div class="col-md-12">
                <table id="table" data-search="true" data-pagination="true" data-page-size="200" data-show-custom-view="false" data-custom-view="customViewFormatter" data-show-custom-view-button="true">
                    <thead>
                    <tr>
                        <th class="text-center">#</th>
                        <th class="text-center">Sub Folder Name</th>
                        <th class="text-center">Folder Name</th>
                        <th class="text-center">Description</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $no = 1;
                    foreach ($sub_folders as $index => $sub_folder) {
                        ?>
                        <tr id="sub_folder-row-<?=$sub_folder['id']?>">
                            <td><?=$no?></td>
                            <td class="text-left"><?=$sub_folder['name']?></td>
                            <td class="text-left"><?=$sub_folder['folder_name']?></td>
                            <td  class="text-left"><?=$sub_folder['description']?></td>
                            <td>
                                <div class='btn-groups'>
                                    <?php if ($user->can('SUB_FOLDER_EDITION')){
                                    ?>
                                    <button data-toggle="modal" data-target="#add-sub_folder-modal" title='Edit'
                                            class="btn btn-custom btn-xs edit_sub_folder_btn"
                                            data-name='<?= $sub_folder['name']; ?>'
                                            data-archive_document_folder_id='<?= $sub_folder['archive_document_folder_id']; ?>'
                                            data-description='<?= $sub_folder['description']; ?>'
                                            data-id='<?= $sub_folder['id']; ?>'><i class='fa fa-edit'></i></button>
                                        <?php
                                    }
                                    ?>
                                    <?php if ($user->can('SUB_FOLDER_DELETION')){
                                    ?>
                                    <a onclick='deleteSubFolder(<?=$sub_folder["id"]?>)' data-toggle='tooltip' title='Delete'
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
<div id="add-sub_folder-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
     aria-hidden="true" style="display: none;">
    <!--<div class="modal" id="add-sub_folder-modal" tabindex="-1" role="dialog">-->
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="add_sub_folder_form" accept-charset="utf-8" action="" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="token" value="<?php echo md5(time()); ?>"/>
                    <input type="hidden" name="id" id="id" class="form-control">
                    <div class="form-group">
                        <label >Name *</label>
                        <input class="form-control" type="text" name="name" id="name" required />
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
                        <textarea class="form-control" type="text" name="description" id="description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default waves-effect btn-danger" data-dismiss="modal">Close</button>
                    <button type="submit" id="add-sub_folder-btn" name="addSubFolder" class="btn btn-info waves-effect waves-light btn-custom">Submit</button>
                    <button style="display:none;" type="submit" id="edit-sub_folder-btn" name="editSubFolder" class="btn btn-info waves-effect waves-light btn-custom">Update</button>
                </div>
            </form>

        </div>
    </div>
</div>



<script>
    $(function() {
        $('#table').bootstrapTable({

        })
    });

    $(document).ready(function() {

        $(document).on('click', '.edit_sub_folder_btn', function(){

            $('#id').val($(this).data('id'));
            $('#name').val($(this).data('name'));
            $('#archive_document_folder_id').val($(this).data('archive_document_folder_id'));
            $('#description').val($(this).data('description'));
            $('#add-sub_folder-btn').hide();
            $('#edit-sub_folder-btn').show();
            $('.modal-title').html("Edit Sub Folder");
        });
        $(document).on('click', '.registerSubFolder', function(){
            $('#add-sub_folder-btn').show();
            $('#edit-sub_folder-btn').hide();
            $('.modal-title').html("Add Sub Folder");
        });
    });

        function deleteSubFolder(id) {
            var my_row_id = "sub_folder-row-" + id;
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
                        data: {table: 'archive_document_sub_folders', id: id},
                    })
                        .done(function(response){
                            $('#'+my_row_id).hide('slow');
                            swal.fire('Deleted!', "Sub Folder has been deleted!", "success");
                        })
                        .fail(function(){
                            swal.fire('Oops...', 'Something went wrong with ajax !', 'error');
                        });
                }

            })

        }




</script>