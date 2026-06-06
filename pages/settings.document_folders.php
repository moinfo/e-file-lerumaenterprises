<?php
include_once ("./config.php");
$db = new DB();
if (isset($_POST['addFolder']) && Utility::isNewSubmit()) {
    $folder = new ArchiveDocumentFolder();
    $data = [
        'name' => $_POST['name'],
        'description' => $_POST['description']
    ];
    if (Utility::insert('archive_document_folders', $data)) {
        Utility::notify('Folder Added', 'success', 'Successful!');
    } else {
        Utility::notify('Failed to Add Folder', 'error', 'Failed!');
    }
}

if (isset($_POST['editFolder']) && Utility::isNewSubmit()) {

    $data = [
        'name' => $_POST['name'],
        'description' => $_POST['description'],
        'id' => $_POST['id']
    ];
    $id = $data['id'];
    if (Utility::update('archive_document_folders', $id, $data)) {
        Utility::notify('Updated', 'success', 'Successful!');
    } else {
        Utility::notify('Failed to update', 'error', 'Failed!');
    }

}
$folders = Utility::selectAll('archive_document_folders');
$user_id = $user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);
?>
<style>
    /* ===== CRUD page polish (reusable, scoped to .crud-page) ===== */
    .crud-page { animation: crudIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both; }
    @keyframes crudIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }

    .crud-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin: 0.5rem 0 1.4rem; }
    .crud-title {
        font-family: var(--font-display, 'Bricolage Grotesque', sans-serif);
        font-weight: 800; font-size: 2rem; line-height: 1; letter-spacing: -0.02em; margin: 0;
        background: linear-gradient(100deg, #fff 35%, var(--light-orange, #ffb24d));
        -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    }
    .crud-sub { color: var(--text-muted, #9c9389); margin: 0.5rem 0 0; font-size: 0.95rem; display: flex; align-items: center; gap: 0.7rem; flex-wrap: wrap; }
    .crud-chip {
        display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.35rem 0.75rem; border-radius: 999px;
        background: rgba(240, 140, 0, 0.10); border: 1px solid var(--border-color, rgba(240,140,0,0.16));
        color: #f6efe5; font-size: 0.8rem; font-weight: 600;
    }
    .crud-chip i { color: var(--primary-orange, #f08c00); }
    .crud-head-actions { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }

    /* Buttons */
    .crud-page .btn-custom {
        display: inline-flex; align-items: center; gap: 0.45rem; width: auto;
        font-family: var(--font-body, 'Hanken Grotesk', sans-serif); font-weight: 600; font-size: 0.88rem;
        text-transform: none; letter-spacing: 0; padding: 0.55rem 1.05rem; border-radius: 11px; cursor: pointer; text-decoration: none;
        color: var(--light-orange, #ffb24d); background: rgba(240, 140, 0, 0.08); border: 1px solid var(--border-color, rgba(240,140,0,0.2));
        transition: all 0.2s ease;
    }
    .crud-page .btn-custom:hover { background: rgba(240, 140, 0, 0.16); border-color: var(--primary-orange, #f08c00); color: #fff; }
    .crud-page .registerFolder, .crud-page .crud-add {
        background: linear-gradient(135deg, var(--light-orange, #ffb24d), var(--dark-orange, #d97706)); color: #241400; border-color: transparent; font-weight: 700;
    }
    .crud-page .registerFolder:hover, .crud-page .crud-add:hover { filter: brightness(1.05); color: #241400; }

    /* Table container */
    .crud-page .search-result {
        background: linear-gradient(180deg, rgba(31, 26, 21, 0.92), rgba(20, 17, 13, 0.95));
        border: 1px solid var(--border-color, rgba(240,140,0,0.16)); border-radius: 16px; padding: 1.2rem 1.3rem;
    }
    .crud-page .fixed-table-toolbar .search .form-control,
    .crud-page .fixed-table-toolbar input[type="text"] {
        background: var(--bg-dark, #17130f) !important; border: 1.5px solid rgba(255, 255, 255, 0.08) !important;
        color: #f6efe5 !important; border-radius: 10px !important; padding: 0.5rem 0.85rem !important;
    }
    .crud-page .fixed-table-toolbar input:focus { border-color: var(--primary-orange, #f08c00) !important; box-shadow: 0 0 0 3px rgba(240, 140, 0, 0.12) !important; outline: none !important; }
    .crud-page table.table { color: #e7e0d6; }
    .crud-page table.table thead th {
        color: var(--light-orange, #ffb24d) !important; text-transform: uppercase; font-size: 0.74rem; letter-spacing: 0.07em; font-weight: 700;
        border-bottom: 1px solid var(--border-color, rgba(240,140,0,0.2)) !important; background: transparent !important;
    }
    .crud-page table.table td { border-color: rgba(255, 255, 255, 0.05) !important; color: #e7e0d6; vertical-align: middle; }
    .crud-page table.table tbody tr { transition: background 0.18s ease; }
    .crud-page table.table tbody tr:hover { background: rgba(240, 140, 0, 0.05); }
    .crud-page .pagination .page-link { background: transparent; border: 1px solid var(--border-color, rgba(240,140,0,0.2)); color: var(--text-muted, #9c9389); }
    .crud-page .pagination .page-item.active .page-link { background: var(--primary-orange, #f08c00); border-color: transparent; color: #241400; font-weight: 700; }
    .crud-page .fixed-table-pagination .pagination-detail, .crud-page .fixed-table-pagination span { color: var(--text-muted, #9c9389) !important; }

    /* Row action buttons */
    .crud-page .btn-groups { display: flex; gap: 0.35rem; justify-content: center; }
    .crud-page td .btn-xs { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; }
    .crud-page td .text-danger {
        background: rgba(255, 107, 93, 0.12) !important; border: 1px solid rgba(255, 107, 93, 0.25) !important; color: #ff7a66 !important;
    }
    .crud-page td .text-danger:hover { background: rgba(255, 107, 93, 0.22) !important; color: #ff9b8c !important; }

    /* Modal */
    #add-folder-modal .modal-content { background: #110e0b !important; border: 1px solid var(--border-color, rgba(240,140,0,0.2)) !important; border-radius: 16px !important; color: #f6efe5; }
    #add-folder-modal .modal-header, #add-folder-modal .modal-footer { border-color: var(--border-color, rgba(240,140,0,0.16)) !important; }
    #add-folder-modal .modal-title { font-family: var(--font-display, 'Bricolage Grotesque', sans-serif); color: #fff; }
    #add-folder-modal label { color: var(--text-muted, #9c9389); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
    #add-folder-modal .form-control { background: var(--bg-dark, #17130f) !important; border: 1.5px solid rgba(255, 255, 255, 0.08) !important; color: #f6efe5 !important; border-radius: 10px !important; }
    #add-folder-modal .form-control:focus { border-color: var(--primary-orange, #f08c00) !important; box-shadow: 0 0 0 3px rgba(240, 140, 0, 0.12) !important; }
    #add-folder-modal .close { color: var(--text-muted, #9c9389); opacity: 1; text-shadow: none; background: none; border: none; font-size: 1.5rem; cursor: pointer; }
    #add-folder-modal .close:hover { color: var(--primary-orange, #f08c00); }
    #add-folder-modal .btn-danger { background: rgba(255, 107, 93, 0.14) !important; border: 1px solid rgba(255, 107, 93, 0.3) !important; color: #ff7a66 !important; }
    #add-folder-modal .btn-info { background: linear-gradient(135deg, var(--light-orange, #ffb24d), var(--dark-orange, #d97706)) !important; border: none !important; color: #241400 !important; font-weight: 700; }

    @media (max-width: 575px) { .crud-title { font-size: 1.7rem; } }
</style>

<div class="container crud-page">
    <div class="crud-head">
        <div>
            <h1 class="crud-title">Document Folders</h1>
            <p class="crud-sub">
                Add, edit and remove document folders.
                <span class="crud-chip"><i class="fa fa-folder"></i> <?= number_format(count($folders)) ?> folders</span>
            </p>
        </div>
        <div class="crud-head-actions">
            <a href="./?p=settings" class="btn btn-custom"><i class="fa fa-arrow-left"></i> Back</a>
            <?php if ($user->can('FOLDER_ADDITION')) { ?>
                <button data-toggle="modal" data-target="#add-folder-modal" class="btn btn-custom registerFolder" type="button"><i class="fa fa-plus"></i> Add Folder</button>
            <?php } ?>
        </div>
    </div>
    <div class="search-result" id="search-result" style="min-height:400px; ">
        <div class="row">
            <div class="col-md-12">
                <table id="table" data-search="true" data-pagination="true" data-page-size="200" data-show-custom-view="false" data-custom-view="customViewFormatter" data-show-custom-view-button="true">
                    <thead>
                    <tr>
                        <th class="text-center">#</th>
                        <th class="text-center">Name</th>
                        <th class="text-center">Description</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $no = 1;
                    foreach ($folders as $index => $folder) {
                        ?>
                        <tr id="folder-row-<?=$folder['id']?>">
                            <td><?=$no?></td>
                            <td class="text-left"><?=$folder['name']?></td>
                            <td  class="text-left"><?=$folder['description']?></td>
                            <td>
                                <div class='btn-groups'>
                                    <?php if ($user->can('FOLDER_EDITION')){
                                    ?>
                                    <button data-toggle="modal" data-target="#add-folder-modal" title='Edit'
                                            class="btn btn-custom btn-xs edit_folder_btn"
                                            data-name='<?= $folder['name']; ?>'
                                            data-description='<?= $folder['description']; ?>'
                                            data-id='<?= $folder['id']; ?>'><i class='fa fa-edit'></i></button>
                                        <?php
                                    }
                                    ?>
                                    <?php if ($user->can('FOLDER_DELETION')){
                                    ?>
                                    <a onclick='deleteFolder(<?=$folder["id"]?>)' data-toggle='tooltip' title='Delete'
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
<div id="add-folder-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
     aria-hidden="true" style="display: none;">
    <!--<div class="modal" id="add-folder-modal" tabindex="-1" role="dialog">-->
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="add_folder_form" accept-charset="utf-8" action="" method="post" enctype="multipart/form-data">
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default waves-effect btn-danger" data-dismiss="modal">Close</button>
                    <button type="submit" id="add-folder-btn" name="addFolder" class="btn btn-info waves-effect waves-light btn-custom">Submit</button>
                    <button style="display:none;" type="submit" id="edit-folder-btn" name="editFolder" class="btn btn-info waves-effect waves-light btn-custom">Update</button>
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

        $(document).on('click', '.edit_folder_btn', function(){

            $('#id').val($(this).data('id'));
            $('#name').val($(this).data('name'));
            $('#description').val($(this).data('description'));
            $('#add-folder-btn').hide();
            $('#edit-folder-btn').show();
            $('.modal-title').html("Edit Folder");
        });
        $(document).on('click', '.registerFolder', function(){
            $('#add-folder-btn').show();
            $('#edit-folder-btn').hide();
            $('.modal-title').html("Add Folder");
        });
    });

        function deleteFolder(id) {
            var my_row_id = "folder-row-" + id;
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
                        data: {table: 'archive_document_folders', id: id},
                    })
                        .done(function(response){
                            $('#'+my_row_id).hide('slow');
                            swal.fire('Deleted!', "Folder has been deleted!", "success");
                        })
                        .fail(function(){
                            swal.fire('Oops...', 'Something went wrong with ajax !', 'error');
                        });
                }

            })

        }




</script>