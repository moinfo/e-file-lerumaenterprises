<?php
include_once ("./config.php");
$db = new DB();
if (isset($_POST['addUser']) && Utility::isNewSubmit()) {
    $user = new User();
    $_POST['password'] = md5($_POST['password']);
    $user->patch($_POST);
    $save = $user->add();
    if ($save) {
        Utility::notify('User Added', 'success', 'Successful!');
    } else {
        Utility::notify('Failed to Add User', 'error', 'Failed!');
    }
}

if (isset($_POST['editUser']) && Utility::isNewSubmit()) {

    $data = [
        'username' => $_POST['username'],
        'password' => md5($_POST['password']),
        'id' => $_POST['id']
    ];
    $id = $data['id'];
    if (Utility::update('users', $id, $data)) {
        Utility::notify('Updated', 'success', 'Successful!');
    } else {
        Utility::notify('Failed to update', 'error', 'Failed!');
    }

}
$users = Utility::selectAll('users');

$user_id = $user_id = $_SESSION[SESSION_NAME]['user_id'];
$user_one = new User($user_id);
?>
<br />
<div class="container">
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group" aria-label="Basic example">
                <a href="./?p=settings" class="btn btn-custom "><i class="fa fa-arrow-left">&nbsp;</i> Back</a>
                <a href="./?p=settings_users" class="btn btn-custom "><i class="fa fa-folder">&nbsp;</i> Users Setting</a>
            </div>
        </div>
    </div>
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group" aria-label="Basic example">
                <?php if ($user_one->can('USER_ADDITION')){
                ?>
                <button class="btn btn-custom registerUser" type="button"><i
                            class="fa fa-plus">&nbsp;</i>Add User
                </button>
                    <?php
                }
                ?>
            </div>
            <div class="float-left">
                <h3>Users</h3>
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
                        <th class="text-center">Username</th>
                        <th class="text-center">Password</th>
                        <th class="text-center">Last Login</th>
                        <th class="text-center">Current Files</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $no = 1;
                    foreach ($users as $index => $user) {
                        ?>
                        <tr id="user-row-<?=$user['id']?>">
                            <td><?=$no?></td>
                            <td><?=$user['username']?></td>
                            <td><?=$user['password']?></td>
                            <td><?=$user['last_login']?></td>
                            <td><?=$user['current_file']?></td>
                            <td>
                                <div class='btn-groups'>
                                    <?php
                                    if ($user_one->can('USER_EDITION')){
                                    ?>
                                    <button title='Edit'
                                            class="btn btn-custom btn-xs edit_user_btn"
                                            data-username='<?= $user['username']; ?>'
                                            data-password='<?= $user['password']; ?>'
                                            data-id='<?= $user['id']; ?>'><i class='fa fa-edit'></i></button>
                                        <?php
                                    }
                                    ?>
                                    <?php if ($user_one->can('USER_DELETION')){
                                    ?>
                                    <a onclick='deleteUser(<?=$user["id"]?>)' data-toggle='tooltip' title='Delete'
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
<!-- User Modal -->
<div id="add-user-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">Add User</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="add_user_form" accept-charset="utf-8" action="" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="token" value="<?php echo md5(time()); ?>"/>
                    <input type="hidden" name="id" id="id" class="form-control">
                    <div class="form-group">
                        <label for="username">Username Name *</label>
                        <input class="form-control" type="text" name="username" id="username" required />
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input class="form-control" type="text" name="password" id="password" required />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">
                        <i class="fa fa-times"></i> Close
                    </button>
                    <button type="submit" id="add-user-btn" name="addUser" class="btn btn-custom">
                        <i class="fa fa-plus"></i> Submit
                    </button>
                    <button style="display:none;" type="submit" id="edit-user-btn" name="editUser" class="btn btn-custom">
                        <i class="fa fa-save"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



<script>
    $(function() {
        $('#table').bootstrapTable({});
    });

    $(document).ready(function() {

        // Fix for Bootstrap Table + Modal interaction issue
        // Ensure modal and its contents are always clickable
        $(document).on('show.bs.modal', '#add-user-modal', function() {
            // Remove any pointer-events blocking
            $('#add-user-modal, .modal-backdrop').css('pointer-events', 'auto');
            $('#add-user-modal *').css('pointer-events', 'auto');
        });

        // Reset form when opening modal for new user
        $(document).on('click', '.registerUser', function(e){
            e.preventDefault();
            e.stopPropagation();

            $('#add_user_form')[0].reset();
            $('#id').val('');
            $('#username').val('');
            $('#password').val('');
            $('#add-user-btn').show();
            $('#edit-user-btn').hide();
            $('#userModalLabel').html('<i class="fa fa-user-plus"></i> Add User');

            // Show modal
            $('#add-user-modal').modal({
                backdrop: 'static',
                keyboard: true,
                focus: true,
                show: true
            });
        });

        // Populate form when editing user
        $(document).on('click', '.edit_user_btn', function(e){
            e.preventDefault();
            e.stopPropagation();

            $('#id').val($(this).data('id'));
            $('#username').val($(this).data('username'));
            $('#password').val($(this).data('password'));
            $('#add-user-btn').hide();
            $('#edit-user-btn').show();
            $('#userModalLabel').html('<i class="fa fa-user-edit"></i> Edit User');

            // Show modal
            $('#add-user-modal').modal({
                backdrop: 'static',
                keyboard: true,
                focus: true,
                show: true
            });
        });

        // Ensure modal resets when closed
        $('#add-user-modal').on('hidden.bs.modal', function () {
            if (!$('#id').val()) {
                $('#add_user_form')[0].reset();
            }
        });

        // Debug: Log when modal is shown
        $('#add-user-modal').on('shown.bs.modal', function() {
            console.log('Modal shown successfully');
            $(this).find('input:first').focus();
        });
    });


    function deleteUser(id) {
        var my_row_id = "user-row-" + id;
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
                    data: {table: 'users', id: id},
                })
                    .done(function(response){
                        $('#'+my_row_id).hide('slow');
                        swal.fire('Deleted!', "User has been deleted!", "success");
                    })
                    .fail(function(){
                        swal.fire('Oops...', 'Something went wrong with ajax !', 'error');
                    });
            }

        })

    }



</script>
