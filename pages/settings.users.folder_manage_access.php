<?php

    if (isset($_POST['config_access_submit']) && Utility::isNewSubmit()) {
        $save_group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : null;



        $delete_query1 = "DELETE from config_folder_access_rights WHERE user_group= '{$save_group_id}'";
        Utility::query($delete_query1, 'DELETE');


        /**
         * TODO create a single query and run it at once to reduce resource misuse
         */
        foreach ($_POST as $element => $value) {
            $parts = explode('-', $element);
            if(count($parts) > 1) {
                $type = $parts[0];
                if($type == 'menu') {
                    $data = ['user_group' => $save_group_id, 'folder_sub_id' => $parts[1], 'type' => 'FOLDER'];
                    $ins =  Utility::insert('config_folder_access_rights', $data);
                } else if($type == 'sub_menu') {
                    $data = ['user_group' => $save_group_id, 'folder_sub_id' => $parts[1], 'type' => 'SUB FOLDER'];
                    $ins =  Utility::insert('config_folder_access_rights', $data);
                }else if($type == 'document_type') {
                    $data = ['user_group' => $save_group_id, 'folder_sub_id' => $parts[1], 'type' => 'DOCUMENT TYPE'];
                    $ins =  Utility::insert('config_folder_access_rights', $data);
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
<div class="container">
    <h5 class="access-page-title">
        <i class="fa fa-folder-open"></i>
        Folder and Sub Folder Manage Access for: <span><?php echo strtoupper($user_group_details['name']); ?></span> Group
    </h5>

    <form action="" method="post">
        <input type="hidden" name="token" value="<?php echo md5(time()); ?>" />
        <div class="row">
    <div class="col-md-4 col-sm-6">
        <div class="content-box">
            <div class="content-box-header"><i class="fa fa-file-alt"></i> <b>Document Types</b></div>
            <div class="content-box-body">
                <?php
                $document_types = Utility::selectAll('document_types');

                foreach ($document_types as $index => $document_type) {
                    $menu_parent_id = $document_type['id'];
                    $result_parent_check = Utility::query("SELECT * FROM config_folder_access_rights WHERE type = 'DOCUMENT TYPE' AND folder_sub_id= $menu_parent_id AND user_group= $group_id ");

                    if (count($result_parent_check) > 0) {
                        $check_parent = 'checked';
                    }
                    else{
                        $check_parent = '';
                    }
                    ?>
                    <div class="checkbox">
                        <input id="" type="checkbox" name="document_type-<?=$document_type['id']?>" <?=$check_parent?>>
                        <label for="checkbox">
                           <?=$document_type['name'] ?>
                        </label>
                    </div>
                <?php
                }
                ?>

            </div>
        </div>
    </div>
<?php


$result = Utility::query("select * from archive_document_folders");
$x=0;
$type = 'FOLDER';
foreach ($result as $menu_parent) {
$x++;
    $menu_parent_id = $menu_parent["id"];

$result_parent_check = Utility::query("SELECT * FROM config_folder_access_rights WHERE type = 'FOLDER' AND folder_sub_id= $menu_parent_id AND user_group= $group_id ");

if (count($result_parent_check) > 0) {
	$check_parent = 'checked';
}
else{
	$check_parent = '';
}

$result_sub = Utility::query("select * from archive_document_sub_folders where archive_document_folder_id=".$menu_parent["id"]);
	
	echo '
			<div class="col-md-4 col-sm-6">
			<div class="content-box">
      		<div class="content-box-header"><i class="fa fa-folder"></i> <b>'.$x.'.&nbsp;'.$menu_parent["name"].'</b></div>
      		<div class="content-box-body">
      			<div class="checkbox parent-checkbox">
      				<input id="" type="checkbox" name="menu-'.$menu_parent['id'].'" '.$check_parent.'>
                            <label for="checkbox">
                                '.$menu_parent["name"].'
                            </label>
                    </div>';

      			foreach ($result_sub as $menu_child) {
                    $menu_child_id = $menu_child["id"];

      					$db_child_check = Xcrud_db::get_instance();
						$query_child_check = "select * from config_folder_access_rights where type = 'SUB FOLDER' AND folder_sub_id= $menu_child_id and user_group= $group_id ";
						$db_child_check->query($query_child_check);
						$result_child_check = $db_child_check->result();

						if (count($result_child_check) > 0) {
							$check_child = 'checked';
						}
						else{
							$check_child = '';
						}


      				echo '
                        <div class="checkbox sub-checkbox">
      				        <input id="" type="checkbox" name="sub_menu-'.$menu_child['id'].'" '.$check_child.'>
      				        <label for="checkbox">
                                '.$menu_child["name"].'
                            </label>
                        </div>
                          ';
					// echo $menu_child['name'];




                          
				}

	echo '
      		</div>
   			</div>
   			</div>';
		

		
}

?>

<?php } ?>

        </div> <!-- Close row -->

        <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">

        <div class="access-save-container">
            <div class="row">
                <div class="col-12 col-md-6 col-lg-4 mx-auto">
                    <button type="submit" class="btn btn-success btn-lg" name="config_access_submit">
                        <i class="fa fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>


