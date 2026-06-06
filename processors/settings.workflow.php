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

 
function showWorkflows(){
$page_options = Utility::getUrlOptions(); 
    /**
     * @todo Data for SP should be coming from a global session variable, which is populated
     * as user logs in
     */
   // $sp = Utility::getCurrentSp();;
   // unset($_GET['id']);
   // unset($_GET['opt']);
   
   // $page_options = Utility::getUrlOptions();
   
   $query="SELECT * FROM config_workflows order by name asc";
   $db = Xcrud_db::get_instance();
   $db -> query($query);
   
   $results = $db -> result();
   
   $head ="<table></table><table class='table table-bordered table-striped' width='100%'><thead>"
           . "<tr>"
           . "<th width='5%'>#</th>"
           . "<th width='40%'>Keyword</th>"
           . "<th width='40%'>Name</th>"
           . "<th width='20%'>View</th>"
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
                 <td>{$result['keyword']}</td>       
                 <td>{$result['name']}</td>       
                 <td><a href='?p=settings.workflow&workflow_id={$result["id"]}' class='btn btn-success btn-xs'>Workflow Details</a></td>
                 
         </tr>        

        ";                                   
    
        }                                      
   
   
   return $head.$body.'</tbody></table>';
}


function showWorkflowDetails($group_id = null){
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
   
   $query="SELECT * FROM config_workflow_details WHERE workflow_id = $group_id AND workflow_order != '0' order by workflow_order asc ";
   $db = Xcrud_db::get_instance();
   $db -> query($query);
   
   $results = $db -> result();
   // var_dump($results);
   // exit();

   $head ="<table class='table table-bordered table-striped' width='60%'><thead>"
           . "<tr style='background-color: #eee;'>"
           . "<th width='10%'>#</th>"
           . "<th width='30%'>Keyword</th>"
           . "<th width='20%'>Description</th>"
           . "</tr>"
           . "</thead><tbody>";
   
   $body = NULL;
   
   $i = 0;
   // $indicator = NULL;
   // $indicator2 = NULL;
   foreach ($results as $result) {
       $i++;
       
      
       
       
       $body = $body . "
         <tr>
                 <td>{$i}</td> 
                 <td>{$result['user_group_keyword']}</td>
                 <td>{$result['description']}</td>
                 
               
         </tr>        

        ";                                   
    
        }                                      
   
   
   return $head.$body.'</tbody></table>';
}




?>

<script>
  function deleteUserGroup(id){
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



  function deleteUser(id,group_id){
     swal.fire({
          title: "Are you sure?",
          text: "You will not be able to redo this action!",
          type: "error",
          showCancelButton: true,
          confirmButtonClass: 'btn-danger waves-effect waves-light',
          confirmButtonText: 'Remove !'
      },

      function(isConfirm) {
        if (isConfirm) {
            swal.fire("Deleted!", "Your imaginary file has been deleted.", "success");
            window.location.href='?<?php echo $page_options;?>&user_id='+id+'&group_id='+group_id+'&opt=delete_user';
            
        } else {
            swal.fire("Cancelled", "Your imaginary file is safe :)", "error");
        }
    });
    // alert(id);

  }
</script>



