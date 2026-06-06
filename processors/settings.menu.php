<?php
$page_options = Utility::getUrlOptions();

function newMenu() {
    unset($_POST['new_menu_submit']);

    $query = Utility::prepareInsertQuery('menu', $_POST);
    Utility::query($query, 'INSERT');

    return "<script>toastr['success']('Data has been saved successfully!')</script>";
}

function updateMenu() {
    $id = $_POST['id'];
    unset($_POST['id']);

    unset($_POST['edit_menu_submit']);


    $query = Utility::prepareUpdateQuery('menu', $id, $_POST);
    Utility::query($query, 'UPDATE');

    return "<script>toastr['success']('Data has been saved successfully!')</script>";
}

function showMenuList() {
    $page_options = Utility::getUrlOptions();

    unset($_GET['id']);
    unset($_GET['opt']);

    $page_options = Utility::getUrlOptions();

    $query = "SELECT * FROM menu order by parent_menu asc";
    $db = Xcrud_db::get_instance();
    $db->query($query);

    $results = $db->result();

    $head = "<table class='table table-bordered table-striped table-hover data-table' width='100%'><thead>"
            . "<tr>"
            . "<th width='5%'>#</th>"
            . "<th width='20%'>Name</th>"
            . "<th width='25%'>Link</th>"
            . "<th width='20%'>Parent Menu</th>"
            . "<th width='5%'>List Order</th>"
            . "<th width='15%'>icon</th>"
            . "<th width='10%'></th>"
            . "</thead><tbody>";

    $body = NULL;

    $i = 0;
   
    foreach ($results as $result) {
        $i++;

        if ($result['parent_menu'] > 0) {
            $result_parent = Utility::selectUniqueId('menu', $result['parent_menu']);
            $result_parent = $result_parent['name'] ?? '-';
        } else {
            $result_parent = 'No Parent';
        }


        $body .= "
         <tr>
                 <td>$i</td> 
                 <td>{$result['name']}</td>
                 <td>{$result['link']}</td>
                 <td>{$result_parent}</td>
                 <td>{$result['list_order']}</td>
                 <td>{$result['icon']}</td>
                 
                 <td style='text-align: center;'>
                    <div class='btn-group'>
                      <a href='?$page_options&id={$result["id"]}&opt=edit_menu' data-toggle='tooltip' title='Edit' class='btn btn-warning btn-xs'><i class='fa fa-edit'></i></a>
                      <a onclick='deleteMenu({$result["id"]})' data-toggle='tooltip' title='Delete' class='btn btn-danger btn-xs'><i class='fa fa-close'></i></a>
                    </div>
                 </td>
         </tr>        
        ";
    }


    return '<div class="table table-responsive">' . $head . $body . '</tbody></table></div>';
}
?>


<script>
    function deleteMenu(id) {
        swal.fire({
            title: "Are you sure?",
            text: "You will not be able to redo this action!",
            type: "error",
            showCancelButton: true,
            confirmButtonClass: 'btn-danger waves-effect waves-light',
            confirmButtonText: 'Delete !'
        },
                function (isConfirm) {
                    if (isConfirm) {
                        swal.fire("Deleted!", "Menu has been deleted!", "success");
                        window.location.href = '?<?php echo $page_options; ?>&id=' + id + '&opt=delete_menu';

                    } else {
                        swal.fire("Cancelled", "Okay, nothing was done!)", "error");
                    }
                });
        // alert(id);

    }




</script>

