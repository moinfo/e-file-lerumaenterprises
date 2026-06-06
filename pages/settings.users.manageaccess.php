<?php

    if (isset($_POST['config_access_submit']) && Utility::isNewSubmit()) {
        $save_group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : null;

        $delete_query1 = "DELETE from config_access_rights WHERE user_group= '{$save_group_id}'";
        $delete_query2 = "DELETE from config_role_access WHERE access_type = 'GROUP' AND  user_id = '{$save_group_id}'";
        Utility::query($delete_query1, 'DELETE');
        Utility::query($delete_query2, 'DELETE');


        /**
         * TODO create a single query and run it at once to reduce resource misuse
         */
        foreach ($_POST as $element => $value) {
            $parts = explode('-', $element);
            if(count($parts) > 1) {
                $type = $parts[0];
                if($type == 'menu') {
                    $data = ['user_group' => $save_group_id, 'menu' => (int)$parts[1]];
                    $ins =  Utility::insert('config_access_rights', $data);
                } else if($type == 'role') {
                    $data = ['access_type' => 'GROUP', 'user_id' => $save_group_id, 'role_id' => (int)$parts[1], 'access' => isset($parts[2]) ? (int)$parts[2] : 0];
                    $ins =  Utility::insert('config_role_access', $data);
                }
            }
        }

        Utility::notify('Access rights updated successfully!', 'success',  'Successful');

}

if (isset($_GET['g_id'])) {
    $group_id = isset($_GET['g_id']) ? (int)$_GET['g_id'] : null;
    $user_group_details = Utility::selectUniqueId('user_groups', $group_id);
?>
<br>
<h5>Manage Access for : <span style="color:#10c469"><?php echo strtoupper($user_group_details['name']); ?></span> Group<span class="pull-right"></h5>
<br>
<form action="" method="post">
    <input type="hidden" name="token" value="<?php echo md5(time()); ?>" />
<div class="row">

<?php
$result = Utility::query("select * from menu where parent_menu='0'");
$x=0;

foreach ($result as $menu_parent) {
$x++;


$result_parent_check = Utility::query("SELECT * FROM config_access_rights WHERE menu=".$menu_parent["id"]." AND user_group=".$group_id);

if (count($result_parent_check) > 0) {
	$check_parent = 'checked';
}
else{
	$check_parent = '';
}





$result_sub = Utility::query("select * from menu where parent_menu=".$menu_parent["id"]);
	
	echo '
			<div class="col-md-4 col-sm-6">
			<div class="content-box">
      		<div class="content-box-header"><b>'.$x.'.&nbsp;'.$menu_parent["name"].'</b></div>
      		<div class="content-box-body">
      			<div class="checkbox checkbox-primary">
      				<input id="" type="checkbox" name="menu-'.$menu_parent['id'].'" '.$check_parent.'>
                            <label for="checkbox">
                                '.$menu_parent["name"].'
                            </label><br>';

      			foreach ($result_sub as $menu_child) {


      					$db_child_check = Xcrud_db::get_instance();
						$query_child_check = "select * from config_access_rights where menu=".$menu_child["id"]." and user_group=".$group_id;
						$db_child_check->query($query_child_check);
						$result_child_check = $db_child_check->result();

						if (count($result_child_check) > 0) {
							$check_child = 'checked';
						}
						else{
							$check_child = '';
						}


      				echo '  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input id="" type="checkbox" name="menu-'.$menu_child['id'].'" '.$check_child.'><label for="checkbox">
                                '.$menu_child["name"].'
                            </label><br>
                          ';
					// echo $menu_child['name'];

                          $menu_3 = Utility::query("select * from menu where parent_menu=".$menu_child["id"],'SELECT');

                          foreach ($menu_3 as $menu_row) {
                          	
							$query_menu_check = Utility::query("select * from config_access_rights where menu=".$menu_row["id"]." and user_group=".$group_id,'SELECT');
							
							$result_menu_check = count($query_menu_check);

							
							if ($result_menu_check > 0) {
								$check_3 = 'checked';
							}

							else{
								$check_3 = '';
							}
						
	      					echo '  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input id="" type="checkbox" name="menu-'.$menu_row['id'].'" '.$check_3.'><label for="checkbox">
	                                '.$menu_row["name"].'
	                            </label><br>
	                          ';

	                          // dump($check_3);
                          }


                          
				}

	echo '
			</div>
      		</div>
   			</div>
   			</div>';
		

		
}

?>

<?php } ?>


</div>
    <hr />
<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-primary">
            <div class="panel-heading"><b>Role Access for the group <?php echo strtoupper($user_group_details['name']); ?></b></div>
            <div class="panel-body row ">
                <div class=" col-md-6 table-responsive">
                    <table class="table table-bordered table-striped table-condensed">
                        <thead><tr><th width="40"></th><th width="35%">Role</th><th>Description</th><th><i class="fa fa-check-square-o"></i></th> </tr></thead>
                    <?php
                        $roles = Utility::query("SELECT r.*, (SELECT COUNT(id) FROM config_role_access WHERE access_type = 'GROUP' AND user_id = '{$group_id}' AND role_id = r.id) as existing FROM config_roles r WHERE r.id > 1 ORDER BY r.id");
                        foreach ($roles as $index => $role) {
                            $checked = $role['existing'] > 0 ? 'checked' : '';
                            echo "<tr>
                                        <td>".($index + 1)."</td>
                                        <td>{$role['keyword']}</td>
                                        <td>{$role['description']}</td>
                                        <td>
                                            <div class='checkbox-primary'>
                                                <input id='role-{$role['id']}-1' type='checkbox' name='role-{$role['id']}-1' {$checked} /><label style='padding: 0px;'></label>
                                            </div>
                                        </td>
                                </tr>";
                        }
                    ?>
                    </table>
                </div>

                <div class="col-md-6">
                    <div class="jumbotron">
                        To completely forbid users from accessing a role make sure to uncheck the role access in their <a href="./?p=settings.systemusers">profiles</a> too.
                    </div>
                </div>
            </div>
            <div class="panel-footer"></div>
        </div>
    </div>
</div>
<br>
<input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
    <div class="row">
        <div class="col-xs-6 col-xs-offset-6 col-md-4 col-md-offset-4">
            <button type="submit" class="btn btn-success btn-lg btn-block" name="config_access_submit">Save Changes</button>
        </div>
    </div>
</form>


