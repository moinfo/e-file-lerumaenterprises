<?php

$page_options = Utility::getUrlOptions();

function newUserGroup(){
    unset($_POST['new_user_group_submit']);
    $query = Utility::prepareInsertQuery('user_groups', $_POST);
    Utility::query($query, 'INSERT');

    return "<script>toastr['success']('Data has been saved successfully!')</script>";
}


function newUser(){
    unset($_POST['add_user_submit']);
    $query = Utility::prepareInsertQuery('user_group_relation', $_POST);
    Utility::query($query, 'INSERT');

    return "<script>toastr['success']('Data has been saved successfully!')</script>";
}

function updateUserGroup(){
    $id = $_POST['id'];
    unset($_POST['id']);
    
    unset($_POST['edit_user_group_submit']);
    
    $query = Utility::prepareUpdateQuery('user_groups',$id, $_POST);
    Utility::query($query, 'UPDATE');

    return "<script>toastr['success']('Data has been saved successfully!')</script>";
}

 
function showUserGroups(){
    $user_id = $_SESSION[SESSION_NAME]['user_id'];
    $user = new User($user_id);
$page_options = Utility::getUrlOptions(); 
    /**
     * @todo Data for SP should be coming from a global session variable, which is populated
     * as user logs in
     */
   // $sp = Utility::getCurrentSp();;
   // unset($_GET['id']);
   // unset($_GET['opt']);
   
   // $page_options = Utility::getUrlOptions();
   
   $query="SELECT * FROM user_groups WHERE 1 order by system_group ASC, name asc";
   $db = Xcrud_db::get_instance();
   $db -> query($query);
   
   $results = $db -> result();
   
   $head ="<table class='table table-bordered table-striped' width='100%'><thead>"
           . "<tr>"
           . "<th width='5%'>#</th>"
           . "<th width='25%'>Name</th>"
           . "<th width='25%'>Folder & Sub Folder Access</th>"
           . "<th width='17%'>Access Rights</th>"
           . "<th width='17%'>Group Users</th>"
           . "<th width='15%'></th>"
           . "</tr>"
           . "</thead><tbody>";
   
   $body = NULL;
   
   $i = 0;
   // $indicator = NULL;
   // $indicator2 = NULL;
   foreach ($results as $result) {
       $i++;
       
      
       
       
       $body .= "
         <tr>
                 <td>$i</td> 
                 <td>{$result['name']}</td>
                 <td>";

                    if ($user->can('MANAGE_ACCESS')) {
                        $body .= "  
                 <a href='?$page_options&sp=settings.users.folder_manage_access&g_id={$result["id"]}' class='btn btn-custom btn-xs'>Folder Access</a>
                 ";
                    }

                        $body .= "     
                 </td>         
                 <td>";

       if ($user->can('MANAGE_ACCESS')) {
           $body .= "  
                 <a href='?$page_options&sp=settings.users.manageaccess&g_id={$result["id"]}' class='btn btn-custom btn-xs'>Manage Access</a>
                 ";
       }

       $body .= "     
                 </td>         
                 <td>
                 ";
                        if ($user->can('MANAGE_USER')) {
                            $body .= "       
                        
                 <a href='?$page_options&g_id={$result["id"]}' class='btn btn-custom btn-xs'>Manage Users</a>
                  ";
                        }
                            $body .= "   
                 </td>
                 <td style='text-align: center;'>
                    <div class='btn-group'>";
                        if($result['system_group'] == 0) {
                            if ($user->can('USER_GROUP_EDITION')) {
                                $body .= "
                <a href = '?$page_options&id={$result["id"]}&opt=edit_user_group' data-toggle = 'tooltip' title = 'Edit' class='btn btn-custom btn-xs' ><i class='fa fa-edit' ></i ></a >
                                 ";
                            }
if ($user->can('USER_GROUP_DELETION')) {
    $body .= " 
                                 <a onclick = 'deleteUserGroup({$result["id"]})' data-toggle = 'tooltip' title = 'Delete' class='btn btn-custom btn-xs' ><i class='fa fa-trash' ></i ></a >";
}
                            }

                    $body .= "  </div>
                 </td>
         </tr>        

        ";                                   
    
        }                                      
   
   
   return $head.$body.'</tbody></table>';
}


function showUsers($group_id = null){
  // echo $group_id;

$page_options = Utility::getUrlOptions(); 
    /**
     * @todo Data for SP should be coming from a global session variable, which is populated
     * as user logs in
     */
   // $sp = Utility::getCurrentSp();;
   // unset($_GET['id']);
   // unset($_GET['opt']);
   
   // $page_options = Utility::getUrlOptions();
   
   $query="SELECT users.username,users.id FROM users,user_group_relation WHERE users.id = user_group_relation.user AND user_group_relation.user_group = '$group_id'";
   $db = Xcrud_db::get_instance();
   $db -> query($query);
   
   $results = $db -> result();
   // var_dump($results);
   // exit();

   $head ="<table class='table table-bordered table-striped' width='60%'><thead>"
           . "<tr>"
           . "<th width='1%'>#</th>"
           . "<th width='30%'>User</th>"
           . "<th width='3%'>Action</th>"
           . "</tr>"
           . "</thead><tbody>";
   
   $body = NULL;
   
   $i = 0;
   // $indicator = NULL;
   // $indicator2 = NULL;
   foreach ($results as $result) {
       $i++;
       
      
       
       
       $body .= "
         <tr id='folder-row-{$result["id"]}'>
                 <td>$i</td> 
                 <td>{$result['username']}</td>
                 <td style='text-align: center;'>
                    <div class='btn-group'>
                     
                      <button type='button' onclick='deleteUser({$result["id"]}, {$group_id})' data-toggle='tooltip' title='Remove' class='btn btn-custom btn-xs'><i class='fa fa-trash'></i></button>
                    </div>
                 </td>
         </tr>        

        ";                                   
    
        }                                      
   
   
   return $head.$body.'</tbody></table>';
}




?>

<form id="remove-user-form" method="post" style="display: none;">
    <input type="hidden" name="token" value="<?=md5(time())?>" />
    <input id="input-remove-user-id" type="hidden" name="user_id" value="" />
    <input id="input-remove-user-group_id" type="hidden" name="group_id" value="" />
    <input id="input-remove-user-submit" type="hidden" name="removeUser" value="<?=md5(time())?>" />
</form>

<script>
  function deleteUserGroup(id){
      swal.fire('Delete is currently Disabled!'); /// This is disabled for now // TO DO make it work better
      return;
     swal.fire({
          title: "Are you sure?",
          text: "You will not be able to redo this action!",
          type: "error",
          showCancelButton: true,
          confirmButtonClass: 'btn-danger waves-effect waves-light',
          confirmButtonText: 'Delete !'
      },

      function(isConfirm) {
        if (isConfirm) {
            swal.fire("Deleted!", "Your imaginary file has been deleted.", "success");
            window.location.href='?<?php echo $page_options;?>&id='+id+'&opt=delete_user_group';
            
        } else {
            swal.fire("Cancelled", "Your imaginary file is safe :)", "error");
        }
    });
    // alert(id);

  }



  // function deleteUser(id,group_id){
  //    swal.fire({
  //         title: "Remove User From Group?",
  //         text: "Are you sure you want to remove this user?",
  //         type: "error",
  //         showCancelButton: true,
  //         confirmButtonClass: 'btn-danger waves-effect waves-light',
  //         confirmButtonText: 'Remove!'
  //     },
  //
  //     function(isConfirm) {
  //       if (isConfirm) {
  //           $("#input-remove-user-id").val(id);
  //           $("#input-remove-user-group_id").val(group_id);
  //           $("#remove-user-form").submit();
  //       }
  //   });
  //   // alert(id);
  //
  // }

  function deleteUser(id,group_id){
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
                  url: './ajax.php?fx=delete_user_from_group',
                  type: 'POST',
                  data: {table: 'user_group_relation', id: id,group_id: group_id},
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



