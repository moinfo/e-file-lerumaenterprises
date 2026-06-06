<?php
include_once ("./config.php");
$db = new DB();
if (isset($_POST['addDocumentType']) && Utility::isNewSubmit()) {
    $document_type = new DocumentType();
    $data = [
        'name' => $_POST['name'],
        'keyword' => $_POST['keyword']
    ];
    if (Utility::insert('document_types', $data)) {
        Utility::notify('DocumentType Added', 'success', 'Successful!');
    } else {
        Utility::notify('Failed to Add DocumentType', 'error', 'Failed!');
    }
}

if (isset($_POST['editDocumentType']) && Utility::isNewSubmit()) {

    $data = [
        'name' => $_POST['name'],
        'keyword' => $_POST['keyword'],
        'id' => $_POST['id']
    ];
    $id = $data['id'];
    if (Utility::update('document_types', $id, $data)) {
        Utility::notify('Updated', 'success', 'Successful!');
    } else {
        Utility::notify('Failed to update', 'error', 'Failed!');
    }

}
$document_types = Utility::selectAll('document_types');
$user_id = $user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);
?>
<br />
<div class="container">
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group" aria-label="Basic example">
                <a href="./?p=settings" class="btn btn-custom "><i class="fa fa-arrow-left">&nbsp;</i> Back</a>
                <a href="./?p=settings_document_types" class="btn btn-custom "><i class="fa fa-document_type">&nbsp;</i> Document Types Setting</a>
            </div>
        </div>
    </div>
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group" aria-label="Basic example">
                <?php if ($user->can('DOCUMENT_TYPE_ADDITION')){
                ?>
                <button data-toggle="modal" data-target="#add-document_type-modal" class="btn btn-custom registerDocumentType" type="button"><i
                            class="fa fa-plus">&nbsp;</i>Add Document Type
                </button>
                    <?php
                }
                ?>
            </div>
            <div class="float-left">
                <h3>Document Types</h3>
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
                        <th class="text-center">Name</th>
                        <th class="text-center">Keyword</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $no = 1;
                    foreach ($document_types as $index => $document_type) {
                        ?>
                        <tr id="document_type-row-<?=$document_type['id']?>">
                            <td><?=$no?></td>
                            <td class="text-left"><?=$document_type['name']?></td>
                            <td  class="text-left"><?=$document_type['keyword']?></td>
                            <td>
                                <div class='btn-groups'>
                                    <?php if ($user->can('DOCUMENT_TYPE_EDITION')){
                                    ?>
                                    <button data-toggle="modal" data-target="#add-document_type-modal" title='Edit'
                                            class="btn btn-custom btn-xs edit_document_type_btn"
                                            data-name='<?= $document_type['name']; ?>'
                                            data-keyword='<?= $document_type['keyword']; ?>'
                                            data-id='<?= $document_type['id']; ?>'><i class='fa fa-edit'></i></button>
                                        <?php
                                    }
                                    ?>
                                    <?php if ($user->can('DOCUMENT_TYPE_DELETION')){
                                    ?>
                                    <a onclick='deleteDocumentType(<?=$document_type["id"]?>)' data-toggle='tooltip' title='Delete'
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
<div id="add-document_type-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
     aria-hidden="true" style="display: none;">
    <!--<div class="modal" id="add-document_type-modal" tabindex="-1" role="dialog">-->
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="add_document_type_form" accept-charset="utf-8" action="" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="token" value="<?php echo md5(time()); ?>"/>
                    <input type="hidden" name="id" id="id" class="form-control">
                    <div class="form-group">
                        <label >Name *</label>
                        <input class="form-control" type="text" name="name" id="name" required />
                    </div>
                    <div class="form-group">
                        <label >Keyword</label>
                        <textarea class="form-control" type="text" name="keyword" id="keyword"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default waves-effect btn-danger" data-dismiss="modal">Close</button>
                    <button type="submit" id="add-document_type-btn" name="addDocumentType" class="btn btn-info waves-effect waves-light btn-custom">Submit</button>
                    <button style="display:none;" type="submit" id="edit-document_type-btn" name="editDocumentType" class="btn btn-info waves-effect waves-light btn-custom">Update</button>
                </div>
            </form>

        </div>
    </div>
</div>



<script>
    // function addDocumentType() {
    //     $("#add-document_type-modal").modal('show');
    // }
    $(function() {
        $('#table').bootstrapTable({

        })
    });


    $(document).ready(function() {

        $(document).on('click', '.edit_document_type_btn', function(){

            $('#id').val($(this).data('id'));
            $('#name').val($(this).data('name'));
            $('#keyword').val($(this).data('keyword'));
            $('#add-document_type-btn').hide();
            $('#edit-document_type-btn').show();
            $('.modal-title').html("Edit Document Type");
        });
        $(document).on('click', '.registerDocumentType', function(){
            $('#add-document_type-btn').show();
            $('#edit-document_type-btn').hide();
            $('.modal-title').html("Add Document Type");
        });
    });
    // $(".edit_document_type_btn").on('click', function () {
    //
    // });
    function deleteDocumentType(id) {
        var my_row_id = "document_type-row-" + id;
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
                    data: {table: 'document_types', id: id},
                })
                    .done(function(response){
                        $('#'+my_row_id).hide('slow');
                        swal.fire('Deleted!', "Document Type has been deleted!", "success");
                    })
                    .fail(function(){
                        swal.fire('Oops...', 'Something went wrong with ajax !', 'error');
                    });
            }

        })

    }


</script>