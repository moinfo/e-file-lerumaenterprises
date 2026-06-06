<?php
$user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);
if (isset($_POST['add_user_submit']) ) {
    $user_group = $_POST['user_group'] ?? null;
    $user = $_POST['user'] ?? null;
//    dd($_POST);
    if($user){
        if(is_array($user)){
              $data =  array_map(static function($item) use($user_group) {return ['user_group' => $user_group, 'user' => $item];}, $user);
                $res = Utility::bulkInsert('user_group_relation', ['user_group', 'user'], $data);
        } else {
            $res =  Utility::insert('user_group_relation', ['user' => $user, 'user_group' => $user_group]);
        }

        if($res) {
            echo  "<script>toastr['success']('User added successfully!')</script>";
        } else {
            echo  "<script>toastr['danger']('Failed to add user to the group!')</script>";
        }
    } else{
        echo "<script>toastr['error']('An error occured, please try again later.')</script>";
    }
    // echo newUser();
}

if (isset($_POST['new_user_group_submit']) ) {
    echo newUserGroup();
}

if (isset($_POST['edit_user_group_submit']) ) {
    echo updateUserGroup();
}


if(isset($_GET['opt']) AND $_GET['opt'] == 'delete_user_group' AND isset($_GET['id'])){
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $query="DELETE FROM user_groups where id='$id'";
    
    
    if(Utility::query($query, "DELETE")){
        echo "<script>toastr['success']('Data was deleted successifully')</script>"; 
    }else{
        echo "<script>toastr['error']('An error occured, please try again later.')</script>"; 
    }
}



if(isset($_GET['opt']) AND $_GET['opt'] == 'delete_user' AND isset($_GET['user_id']) AND isset($_GET['group_id'])){
    $id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;
    $query="DELETE FROM user_group_relation where user='$id' and user_group='$group_id'";
    
    
    if(Utility::query($query, "DELETE")){
        echo "<script>toastr['success']('Data was deleted successifully')</script>"; 
    }else{
        echo "<script>toastr['error']('An error occured, please try again later.')</script>"; 
    }
}


?>


<div class="col-md-6">

<div class="card-box"  style="min-height: 600px;">

	<?php

	if(isset($_GET['opt']) && $_GET['opt'] == 'edit_user_group' && ($_GET['id'] > 0) ){
            /**
             * Prep and load edit form
             */
            include './forms/form.usergroup.edit.php';
    }

    else{
        if ($user->can('USER_GROUP_ADDITION')) {
            echo '
    	<div>
			<a data-toggle="modal" data-target="#con-close-modal" class="btn btn-custom waves-effect waves-light"><i class="fa fa-plus-circle"></i>&nbsp; Add</a>
		</div>
		';
        }
    }

    	echo '<br>';
	       
		echo showUserGroups();
	?>
</div>
</div>






<!-- SUB PAGE -->

<?php
if(isset($_POST['removeUser']) && Utility::isNewSubmit()){
    $id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $query="DELETE FROM user_group_relation WHERE user='$id' AND user_group='$group_id'";
    if(Utility::query($query, "DELETE")){
        echo "<script>toastr['success']('Data was deleted successifully')</script>";
    }else{
        echo "<script>toastr['error']('An error occured, please try again later.')</script>";
    }
}
?>

 <?php



if (isset($_GET['g_id'])) {
    



$group_id = $_GET['g_id'];
unset($_GET['id']);
$user_group_details = Utility::selectUniqueId('user_groups', $group_id);

?>

<div class="col-md-6">
<div class="card-box" style="min-height: 600px;">
<h5>Add User in <font color="#10c469"><?php echo strtoupper($user_group_details['name']); ?></font> Group</h5>
    <form method="post">
        <div class="row">
            <div class="col-md-10">
               <select multiple="multiple" name="user[]" required class="form-control select2" >
                    <option value="">Select user</option>
                    <?php
                    $results = Utility::selectUserToAddInGroup('users',$group_id);
                    foreach ($results as $user) {
                        echo '<option value="'.$user["id"].'">'.$user["username"].'</option>';
                    }
                    ?>
               </select> 
               <input type="text" name="user_group" value="<?php echo $group_id; ?>" hidden="true">
           </div>
           <div class="col-md-2">
               <button type="submit" name="add_user_submit" class="btn btn-info btn-md">SAVE</button>
            </div>
        </div>
    </form>
    
    <?php
        echo '<br>';
        echo showUsers($group_id);
    ?>

</div>
</div>
<?php } ?>

<!-- END of SUB PAGE -->





<div id="con-close-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">New User Group</h4>
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
            </div>

            <form id="new_user_group_form" action="?<?php echo Utility::getUrlOptions(); ?>" method="post">
	        
	            <div class="modal-body">
	                <div class="row">
	                    <div class="col-md-12">
	                        <div class="form-group">
	                            <label for="field-1" class="control-label">Name</label>
	                            <input type="text" name="name" class="form-control" id="field-1" placeholder="Name Of the group">
	                        </div>
	                    </div>
	                    <div class="col-md-12">
	                        <div class="form-group">
	                            <label for="field-1" class="control-label">Keyword</label>
	                            <input type="text" name="keyword" class="form-control" id="field-2" placeholder="Short keyword for the group"  onkeyup="this.value = this.value.toUpperCase();">
	                        </div>
	                    </div>
	                    
	                </div>
	                
		            <div class="modal-footer">
		                <button type="button" class="btn btn-default waves-effect" data-dismiss="modal">Close</button>
		                <button type="submit" name="new_user_group_submit" class="btn btn-info waves-effect waves-light">Save</button>
		            </div>
	        	</div>    
        	</form>
    </div>
</div><!-- /.modal -->

