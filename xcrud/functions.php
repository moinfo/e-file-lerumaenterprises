<?php
function publish_action($xcrud)
{
    if ($xcrud->get('primary'))
    {
        $db = Xcrud_db::get_instance();
        $query = 'UPDATE base_fields SET `bool` = b\'1\' WHERE id = ' . (int)$xcrud->get('primary');
        $db->query($query);
    }
}
function unpublish_action($xcrud)
{
    if ($xcrud->get('primary'))
    {
        $db = Xcrud_db::get_instance();
        $query = 'UPDATE base_fields SET `bool` = b\'0\' WHERE id = ' . (int)$xcrud->get('primary');
        $db->query($query);
    }
}

function exception_example($postdata, $primary, $xcrud)
{
    // get random field from $postdata
    $postdata_prepared = array_keys($postdata->to_array());
    shuffle($postdata_prepared);
    $random_field = array_shift($postdata_prepared);
    // set error message
    $xcrud->set_exception($random_field, 'This is a test error', 'error');
}

function test_column_callback($value, $fieldname, $primary, $row, $xcrud)
{
    return $value . ' - nice!';
}

function after_upload_example($field, $file_name, $file_path, $params, $xcrud)
{
    $ext = trim(strtolower(strrchr($file_name, '.')), '.');
    if ($ext != 'pdf' && $field == 'uploads.simple_upload')
    {
        unlink($file_path);
        $xcrud->set_exception('simple_upload', 'This is not PDF', 'error');
    }
}

function movetop($xcrud)
{
    if ($xcrud->get('primary') !== false)
    {
        $primary = (int)$xcrud->get('primary');
        $db = Xcrud_db::get_instance();
        $query = 'SELECT `officeCode` FROM `offices` ORDER BY `ordering`,`officeCode`';
        $db->query($query);
        $result = $db->result();
        $count = count($result);

        $sort = array();
        foreach ($result as $key => $item)
        {
            if ($item['officeCode'] == $primary && $key != 0)
            {
                array_splice($result, $key - 1, 0, array($item));
                unset($result[$key + 1]);
                break;
            }
        }

        foreach ($result as $key => $item)
        {
            $query = 'UPDATE `offices` SET `ordering` = ' . $key . ' WHERE officeCode = ' . $item['officeCode'];
            $db->query($query);
        }
    }
}
function movebottom($xcrud)
{
    if ($xcrud->get('primary') !== false)
    {
        $primary = (int)$xcrud->get('primary');
        $db = Xcrud_db::get_instance();
        $query = 'SELECT `officeCode` FROM `offices` ORDER BY `ordering`,`officeCode`';
        $db->query($query);
        $result = $db->result();
        $count = count($result);

        $sort = array();
        foreach ($result as $key => $item)
        {
            if ($item['officeCode'] == $primary && $key != $count - 1)
            {
                unset($result[$key]);
                array_splice($result, $key + 1, 0, array($item));
                break;
            }
        }

        foreach ($result as $key => $item)
        {
            $query = 'UPDATE `offices` SET `ordering` = ' . $key . ' WHERE officeCode = ' . $item['officeCode'];
            $db->query($query);
        }
    }
}

function show_description($value, $fieldname, $primary_key, $row, $xcrud)
{
    $result = '';
    if ($value == '1')
    {
        $result = '<i class="fa fa-check" />' . 'OK';
    }
    elseif ($value == '2')
    {
        $result = '<i class="fa fa-circle-o" />' . 'Pending';
    }
    return $result;
}

function custom_field($value, $fieldname, $primary_key, $row, $xcrud)
{
    return '<input type="text" readonly class="xcrud-input" name="' . $xcrud->fieldname_encode($fieldname) . '" value="' . $value .
        '" />';
}
function unset_val($postdata)
{
    $postdata->del('Paid');
}

function format_phone($new_phone)
{
    $new_phone = preg_replace("/[^0-9]/", "", $new_phone);

    if (strlen($new_phone) == 7)
        return preg_replace("/([0-9]{3})([0-9]{4})/", "$1-$2", $new_phone);
    elseif (strlen($new_phone) == 10)
        return preg_replace("/([0-9]{3})([0-9]{3})([0-9]{4})/", "($1) $2-$3", $new_phone);
    else
        return $new_phone;
}

function before_list_example($list, $xcrud)
{
    var_dump($list);
}

function after_update_test($pd, $pm, $xc)
{
    $xc->search = 0;
}

    function after_save_callback($field, $file_name, $file_path, $params, $xcrud){
        exit('This is executed');
    }


/**
*   Right after an application is captured - the function add an entry into project_state table
*   in order to initialize the application to the first state (pre-screening)
*/
function initialize_application_state($postdata, $primary, $xcrud){
       
        $query= "INSERT INTO project_states SET 
            project = '$primary',
            state = '1'
        ";
        $db = Xcrud_db::get_instance();
        $db -> query($query);


         $org_id = $postdata -> get('organization');

         $proposal_call = $postdata -> get('proposal_call');
         $grant_type = $postdata -> get('grant_type');

         $query ="INSERT INTO app_application_number_tracking set call_for_proposal = '$proposal_call',application_id='$primary', grant_type ='$grant_type' ";
         $db = Xcrud_db::get_instance();
         $db -> query($query);

        if($db -> insert_id()  > '0'){
            echo " 
                 <div class='alert alert-success alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    Basic Information about the project was added. Now you can continue with filling more details.
                  </div>
                  <meta HTTP-EQUIV='REFRESH' content='6; url=application.capture.php?pid=$primary&org=$org_id#more'>

                  If this page does not change automatically, please <a href='application.capture.php?pid=$primary&org=$org_id#more' class='btn btn-danger'>click this button</a> 
                  ";
                  
        }else{
             echo " 
                 <div class='alert alert-error alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    <strong>Error!</strong> The added application failed to properly initialize. Please contact system administrator. <br/>
                    Application ID: $primary
                  </div>
                   <meta HTTP-EQUIV='REFRESH' content='6; url=application.capture.php?pid=$primary&org=$org_id#more'>
                  ";
        }
}    




/**
*   Right after an application is captured - the function add an entry into project_state table
*   in order to initialize the application to the first state (pre-screening)
*/
function initialize_application_state_dataentry($postdata, $primary, $xcrud){
       
        $query= "INSERT INTO project_states SET 
            project = '$primary',
            state = '1'
        ";
        $db = Xcrud_db::get_instance();
        $db -> query($query);


         $org_id = $postdata -> get('organization');

         $proposal_call = $postdata -> get('proposal_call');
         $grant_type = $postdata -> get('grant_type');

         $query ="INSERT INTO app_application_number_tracking set call_for_proposal = '$proposal_call',application_id='$primary', grant_type ='$grant_type' ";
         $db = Xcrud_db::get_instance();
         $db -> query($query);

        if($db -> insert_id()  > '0'){
            echo " 
                 <div class='alert alert-success alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    Basic Information about the project was added. Now you can continue with filling more details.
                  </div>
                  <meta HTTP-EQUIV='REFRESH' content='3; url=de.organization.details.upload.php?pid=$primary&org=$org_id&oid=$org_id#more'>

                  If this page does not change automatically, please <a href='de.organization.details.upload.php?pid=$primary&org=$org_id&oid=$org_id#more' class='btn btn-danger'>click this button</a> 
                  ";
                  
        }else{
             echo " 
                 <div class='alert alert-error alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    <strong>Error!</strong> The added application failed to properly initialize. Please contact system administrator. <br/>
                    Application ID: $primary
                  </div>
                   <meta HTTP-EQUIV='REFRESH' content='6; url=application.capture.php?pid=$primary&org=$org_id#more'>
                  ";
        }
}    




/**
*     Commit selection list, as per decisions made on each application
*     1: Move approved applicatons into the next state
*     2: Move rejected applications into 99 state
*
*
*     The function is used both for approval and selection stages
*/
function commitSelectionList($postdata, $primary, $xcrud){

    $rejected_apps =   $postdata -> get('rejected_applications');
    $approved_apps =   $postdata -> get('approved_applications');
    
    $user = $postdata -> get('entry_user');

    

    $query = " INSERT INTO `sgrants`.`project_states` (`id`, `project`, `state`, `user`, `entry_timestamp`) VALUES ";

    if(strlen($rejected_apps) >0){

                $rejected_array = explode(',', $rejected_apps );

                 foreach ($rejected_array as $key => $value) {
                        $query .="(NULL, '$value', '99', '$user', CURRENT_TIMESTAMP),";
                }
    }

    if(strlen($approved_apps) >0 ){

            $approved_array = explode(',', $approved_apps );

            $next_stage_for_approved = $approved_array[0];
            unset($approved_array[0]);

                 foreach ($approved_array as $key => $value) {
                        $query .="(NULL, '$value', '$next_stage_for_approved', '$user', CURRENT_TIMESTAMP),";
                }
    }

   $query = rtrim($query, ',');

   $db = Xcrud_db::get_instance();
   $db -> query($query);

    echo " 
                 <div class='alert alert-error alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    <strong>Selected list has been submitted!</strong>
                  </div>";
        

        echo "<meta HTTP-EQUIV='REFRESH' content='5; url=../reviews/review.apps.php'>";



}

/**
*   After data has been successfully put into the table, 
*   send an alert informing the user
*/
function success_alert($postdata, $primary, $xcrud){
            echo " 
                 <div class='alert alert-success alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    Data was successfully captured!
                  </div>"; 
}



/**
*   The function is used for processing application
*   details that are recorded via excel
*
*/
function process_uploaded_file($field,$filename,$file_path,$config,$xcrud){

		
                    $status = 0;
                    // we can proceed with uploading and reading process
                    // If you need to parse XLS files, include php-excel-reader
                    
                    require ('../excel_reader/php-excel-reader/excel_reader2.php');
                    require ('../modules/core/Upload.php');
                    require ('../excel_reader/SpreadsheetReader.php');
                    $Reader = new SpreadsheetReader ( $file_path );
                    
                    
                    
                    $i =0;

                    foreach ( $Reader as $Row ) {
                        
                        ++$i;
                        if ($i > 1 && strlen($Row['1']) > 1 ){

                                    
                                $staff = NULL;
                                $staff = explode('-', $Row[4]);
                                
                                $organization_name = $Row[1];
                                $region= $Row[9];
                                $district= $Row[10];
                                $address = $Row[2];
                                $staff_name =  @$staff[0];
                                $staff_phone = $Row[3];
                                $staff_email = $Row[13];
                                $staff_position = @$staff[1];
                                $project_name = $Row[5];
                                $project_no  = $Row[6];
                                $thematic = $Row[7];
                                $subresult = $Row[8];
                                $amount = $Row[12];
                                $impact_area = $Row[11];
                                
                                echo Upload::autoUpload($organization_name, $region, $district, $address, $staff_name, $staff_phone, $staff_email, $staff_position, $project_name, $project_no, $thematic, $subresult, $amount, $impact_area); 
                                
                        }
                    }

}


/**
*   Function for generating application number sequence root, for 
*   a particular call for proposal
*/
function applicationNumberSequenceRoot($postdata, $xcrud){

    $window = $postdata -> get('window');
    $year = date('y');
    $grant_type_id = $postdata -> get('grant_type');

    // Get Grant Type Code
    $query="select code from config_grant_types where id='$grant_type_id'";


    $db = Xcrud_db::get_instance();
    $db -> query($query);
    $result = $db -> row();

    $code = $result['code'];

    $sequence = "FCS/$code/$window/$year/";

    $postdata -> set('application_number_sequence', $sequence);


}


/**
*   Function for updating Application Number Sequence Root (FCS/INO/W1/17/023)
*   when update method is called on a call for proposal
*/
function applicationNumberSequenceRootUpdate($postdata,$primary, $xcrud){

    $window = $postdata -> get('window');
    $year = date('y');
    $grant_type_id = $postdata -> get('grant_type');

    // Get Grant Type Code
    $query="select code from config_grant_types where id='$grant_type_id'";


    $db = Xcrud_db::get_instance();
    $db -> query($query);
    $result = $db -> row();

    $code = $result['code'];

    $sequence = "FCS/$code/$window/$year/";

    $postdata -> set('application_number_sequence', $sequence);


}


/**
*   A "before insert" a new application into the database,
*   it checks the last number of a particular call for proposal then creates
*   a new number fo call of proposal basing on that number
*
*/
function getNewAppNumber($postdata, $xcrud){
    $call_id = $postdata -> get('proposal_call');
    $grant_type = $postdata -> get('grant_type');

    //1: get a seed for this call 
    $query="select application_number_sequence as code from proposal_call_windows where call_id ='$call_id' AND grant_type='$grant_type' ";

    $db = Xcrud_db::get_instance();
    $db -> query($query);
    

    $call_records = $db -> row();
    $code = $call_records['code'];

    //Get all number from database for this call

    $query ="SELECT max(application_id) as top_number from app_application_number_tracking where call_for_proposal = '$call_id' AND grant_type ='$grant_type'";
     $db = Xcrud_db::get_instance();
    $db -> query($query);

    $tracking = $db -> row();
    $top = $tracking['top_number'];
    
    $top = $top + 1;

    $project_number = $code.$top;

    $postdata -> set('project_no', $project_number);


}


/**
*  A quick function for editing application numbers for projects whose numbers were wrong, because of the system.
*
*/
function recreateNewAppNumber($postdata,$primary,$xcrud){
//    $call_id = $postdata -> get('proposal_call');
//    $grant_type = $postdata -> get('grant_type');
//
//    //1: get a seed for this call 
//    $query="select application_number_sequence as code from proposal_call_windows where call_id ='$call_id' AND grant_type='$grant_type' ";
//
//    $db = Xcrud_db::get_instance();
//    $db -> query($query);
//    
//
//    $call_records = $db -> row();
//    $code = $call_records['code'];
//
//    //Get all number from database for this call
//
//    $query ="SELECT max(application_id) as top_number from app_application_number_tracking where call_for_proposal = '$call_id' AND grant_type ='$grant_type'";
//     $db = Xcrud_db::get_instance();
//    $db -> query($query);
//
//    $tracking = $db -> row();
//    $top = $tracking['top_number'];
//    
//    $top = $top + 1;
//
//    $project_number = $code.$top;
//
//    $postdata -> set('project_no', $project_number);
//
//
//    // MAKE SURE DATA GETS UPDATES IN APPLICATION NUMBER TRACKING TABLE
//     $query ="INSERT INTO app_application_number_tracking set application_id ='$primary', call_for_proposal = '$call_id', grant_type ='$grant_type'";
//     $db = Xcrud_db::get_instance();
//    $db -> query($query);
}



function externalMatching($postdata,$xcrud){
   $proposal_call = $postdata-> get('proposal_call');
   $project_number =  $postdata-> get('project_number');
   $finance_marks =  $postdata-> get('finance_marks');
   $project_marks =  $postdata-> get('project_marks');
   
   $project_comments =  $postdata-> get('project_comments');
   $finance_comments =  $postdata-> get('finance_comments');
   
   // Cleaning comments
   $dirts = array("'");
   $project_comments = str_replace($dirts, "", $project_comments);
   $finance_comments = str_replace($dirts, "", $finance_comments);
   
    
    /**
     * 1: Check if project number exists
     */
     $dirts = array("'"," ");
     $project_number = str_replace($dirts, "", $project_number);
     $dirts = array("|");
     $project_number = str_replace($dirts, "/", $project_number);
     
     $query="select * from project where project_no ='$project_number' AND proposal_call ='$proposal_call'";
     
     $db = Xcrud_db::get_instance();
     $db -> query($query);
     $results = $db -> result();
     
     if(count($results) > 0){
         //Proceed with the rest of computations
         $project_id = $results[0]['id'];
         
         // Get id for stages that we are going to use
         $query ="select * from config_project_stages where call_for_proposal = '$proposal_call' AND (stage = 'Screening - Project' OR  stage ='Screening - Financial' OR stage='Selection')";
         $db = Xcrud_db::get_instance();
         $db -> query($query);
         $results = $db -> result();
         
         foreach ($results as $key => $value) {
             if($value['stage'] =='Screening - Project'){
                 $screening_project_id = $value['id'];
             }
              if($value['stage'] =='Screening - Financial'){
                 $screening_finance_id = $value['id'];
             }
             
             if($value['stage'] =='Selection'){
                 $selection_stage_id = $value['id'];
             }
         }
         
         /**
            * 2: Getting first question in Screening Project
            */
              $query="select id from config_stage_checklist where stage ='$screening_project_id' limit 0,1";
                $db = Xcrud_db::get_instance();
                $db -> query($query);
                $results = $db -> row();
                
                $project_question_id = $results['id'];

           /**
            * 3: Fill marks for the first question (screening project)
             */
                // 3.1: In making sure we do not enter duplicates, so delete all answers for such
                $query = "DELETE FROM app_checklist_result WHERE application ='$project_id' AND stage='$screening_project_id' AND checklist ='$project_question_id'";
                $db = Xcrud_db::get_instance();
                $db -> query($query);
                
                
                
                $query = "INSERT INTO app_checklist_result SET application ='$project_id', stage='$screening_project_id', checklist ='$project_question_id', result ='$project_marks'";
               $db = Xcrud_db::get_instance();
                $db -> query($query);

           /**
            * 4: Move the project to screening finance 
            */
                $query="INSERT INTO project_states set project = '$project_id', state= '$screening_finance_id'";
                $db = Xcrud_db::get_instance();
                $db -> query($query);



           /*
            * 5: Get the first question in screening finance 
            */
                $query="select id from config_stage_checklist where stage ='$screening_finance_id' limit 0,1";
                $db = Xcrud_db::get_instance();
                $db -> query($query);
                $results = $db -> row();
                
                $finance_question_id = $results['id'];  

            /**
            * 6: Fill marks for the first question (screening finance)
             */ 
                 // 6.1: In making sure we do not enter duplicates, so delete all answers for such
                $query = "DELETE FROM app_checklist_result WHERE application ='$project_id' AND stage='$screening_finance_id' AND checklist ='$finance_question_id'";
                $db = Xcrud_db::get_instance();
                $db -> query($query);
                
                
                $query = "INSERT INTO app_checklist_result SET application ='$project_id', stage='$screening_finance_id', checklist ='$finance_question_id', result ='$finance_marks'";
                $db = Xcrud_db::get_instance();
                $db -> query($query);




           /*
            * 7: Move the project to selection
            */
                sleep(2);
            // Sleeping is crucial, in order to allow proper ordering of project states between screening finance & selection stage    
                
                $query="INSERT INTO project_states set project = '$project_id', state= '$selection_stage_id'";
                $db = Xcrud_db::get_instance();
                $db -> query($query);
            
                
            /**
             * 8: INSERT COMMENTS FOR Project & Finance
             */    
                $query="INSERT INTO project_general_screening_comment set project = '$project_id', stage= 'SCREENING - PROJECT', comment ='$project_comments'";
                $db = Xcrud_db::get_instance();
                $db -> query($query); 
         
                $query="INSERT INTO project_general_screening_comment set project = '$project_id', stage= 'SCREENING - FINANCIAL', comment ='$finance_comments'";
                $db = Xcrud_db::get_instance();
                $db -> query($query); 
         
              
    
        /**
         * 9: Update processing status variable
         */
    
                
                $postdata -> set('matching_status', 'SUCCESS');
                
         echo "<div class='alert alert-success alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    Project number matched. All data have been saved!!!
                  </div>";       
         
     }else{
         /*
          *  We know that we've not found the right project number...so we save
          *  and let the user know that we have not found a match 
          */
            $postdata -> set('matching_status', 'FAILED');
            
             echo "<div class='alert alert-danger alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    Matching Project number failed. None the less, data has been saved. You can try to edit the project number and reprocess 'matching' again. 
                  </div>";       
         
     }
}





function externalMatchingFile($field,$filename,$file_path,$config,$xcrud){
    
     require ('../excel_reader/php-excel-reader/excel_reader2.php');
                    require ('../modules/core/Upload.php');
                    require ('../excel_reader/SpreadsheetReader.php');
                    $Reader = new SpreadsheetReader ( $file_path );
                    
                    
                    
                    $i =0;

                    foreach ( $Reader as $Row ) {
                        
                        ++$i;
                        if ($i > 1 && strlen($Row['1']) > 1 ){

                                    
                       
                    
                    
                    
   $proposal_call = $_SESSION['fcs.current_call'];
   $project_number =  $Row[2];
   $project_marks =  $Row[8];
   $finance_marks =  $Row[9];
   
   
   $project_comments =  $Row[10];
   $finance_comments =  $Row[11];
   
   // Cleaning comments
   $dirts = array("'");
   $project_comments = str_replace($dirts, "", $project_comments);
   $finance_comments = str_replace($dirts, "", $finance_comments);
   
    
    /**
     * 1: Check if project number exists
     */
     $dirts = array("'"," ");
     $project_number = str_replace($dirts, "", $project_number);
     $dirts = array("|");
     $project_number = str_replace($dirts, "/", $project_number);
     
     $query="select * from project where project_no ='$project_number' AND proposal_call ='$proposal_call'";
     
     $db = Xcrud_db::get_instance();
     $db -> query($query);
     $results = $db -> result();
     
     if(count($results) > 0){
         //Proceed with the rest of computations
         $project_id = $results[0]['id'];
         
         // Get id for stages that we are going to use
         $query ="select * from config_project_stages where call_for_proposal = '$proposal_call' AND (stage = 'Screening - Project' OR  stage ='Screening - Financial' OR stage='Selection')";
         $db = Xcrud_db::get_instance();
         $db -> query($query);
         $results = $db -> result();
         
         foreach ($results as $key => $value) {
             if($value['stage'] =='Screening - Project'){
                 $screening_project_id = $value['id'];
             }
              if($value['stage'] =='Screening - Financial'){
                 $screening_finance_id = $value['id'];
             }
             
             if($value['stage'] =='Selection'){
                 $selection_stage_id = $value['id'];
             }
         }
         
         /**
            * 2: Getting first question in Screening Project
            */
              $query="select id from config_stage_checklist where stage ='$screening_project_id' limit 0,1";
                $db = Xcrud_db::get_instance();
                $db -> query($query);
                $results = $db -> row();
                
                $project_question_id = $results['id'];

           /**
            * 3: Fill marks for the first question (screening project)
             */
                // 3.1: In making sure we do not enter duplicates, so delete all answers for such
                $query = "DELETE FROM app_checklist_result WHERE application ='$project_id' AND stage='$screening_project_id' AND checklist ='$project_question_id'";
                $db = Xcrud_db::get_instance();
                $db -> query($query);
                
                
                
                $query = "INSERT INTO app_checklist_result SET application ='$project_id', stage='$screening_project_id', checklist ='$project_question_id', result ='$project_marks'";
               $db = Xcrud_db::get_instance();
                $db -> query($query);

           /**
            * 4: Move the project to screening finance 
            */
                $query="INSERT INTO project_states set project = '$project_id', state= '$screening_finance_id'";
                $db = Xcrud_db::get_instance();
                $db -> query($query);



           /*
            * 5: Get the first question in screening finance 
            */
                $query="select id from config_stage_checklist where stage ='$screening_finance_id' limit 0,1";
                $db = Xcrud_db::get_instance();
                $db -> query($query);
                $results = $db -> row();
                
                $finance_question_id = $results['id'];  

            /**
            * 6: Fill marks for the first question (screening finance)
             */ 
                 // 6.1: In making sure we do not enter duplicates, so delete all answers for such
                $query = "DELETE FROM app_checklist_result WHERE application ='$project_id' AND stage='$screening_finance_id' AND checklist ='$finance_question_id'";
                $db = Xcrud_db::get_instance();
                $db -> query($query);
                
                
                $query = "INSERT INTO app_checklist_result SET application ='$project_id', stage='$screening_finance_id', checklist ='$finance_question_id', result ='$finance_marks'";
                $db = Xcrud_db::get_instance();
                $db -> query($query);




           /*
            * 7: Move the project to selection
            */
                sleep(2);
            // Sleeping is crucial, in order to allow proper ordering of project states between screening finance & selection stage    
                
                $query="INSERT INTO project_states set project = '$project_id', state= '$selection_stage_id'";
                $db = Xcrud_db::get_instance();
                $db -> query($query);
            
                
            /**
             * 8: INSERT COMMENTS FOR Project & Finance
             */    
                $query="INSERT INTO project_general_screening_comment set project = '$project_id', stage= 'SCREENING - PROJECT', comment ='$project_comments'";
                $db = Xcrud_db::get_instance();
                $db -> query($query); 
         
                $query="INSERT INTO project_general_screening_comment set project = '$project_id', stage= 'SCREENING - FINANCIAL', comment ='$finance_comments'";
                $db = Xcrud_db::get_instance();
                $db -> query($query); 
         
              
    
        /**
         * 9: Update processing status variable
         */
    
                
                $process_status= 'SUCCESS';
                
              
         
     }else{
         /*
          *  We know that we've not found the right project number...so we save
          *  and let the user know that we have not found a match 
          */
           $process_status  = 'FAILED';
            
                
         
     }
     
     /**
      * Insert row data into db
      */
     $query="INSERT INTO fix_external_app_matching SET  project_number  ='$project_number',project_marks ='$project_marks', finance_marks ='$finance_marks', project_comments ='$project_comments', finance_comments ='$finance_comments', matching_status ='$process_status', proposal_call ='$proposal_call' ";
     $db = Xcrud_db::get_instance();
     $db -> query($query);
    }
  }
  
  echo "<div class='alert alert-success alert-dismissible' role='alert'>
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                    File has been processed. See table below for status of individual imported entries!!!
                  </div>"; 
}