<?php 
    $entry_id = $_GET['id'];
    $query="select * from user_groups where id ='$entry_id'";
    $db = Xcrud_db::get_instance();
    
    $db -> query($query);
    $entry_details = $db -> row();
    
    
    
    /**
     * Cleaning $_GET
     * 
     * This is important for the form, that it may not return to the same 
     * editing state after posting data
     */

    unset($_GET['id']);
    unset($_GET['opt']);
?>
<h6 class="modal-title">Edit User Group</h6>
<form id="edit_user_group_form" class="form-gray-border" action="?<?php echo Utility::getUrlOptions(); ?>" method="post">
            
    <div class="modal-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="field-1" class="control-label">Name</label>
                    <input type="text" name="name" class="form-control" id="field-1" value="<?php echo $entry_details['name']; ?>">
                    <input class="form-control" name="id"  value="<?php echo $entry_details['id'] ?>" type="hidden">
                </div>
                <button name="close_form" type="submit" class="btn btn-custom waves-effect" data-dismiss="modal">Close</button>
                <button type="submit" name="edit_user_group_submit" class="btn btn-custom waves-effect waves-light">Update</button>
            </div>
            
        </div>
        
        
    </div>    
</form>