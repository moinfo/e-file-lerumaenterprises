<?php
// set folder to upload files
define ('SITE_ROOT', realpath(dirname(__FILE__)));
//$output_dir = SITE_ROOT."../allfiles/pf-archives/";
$output_dir = "../../allfiles/pf-archives/";

if (isset($_FILES["myfile"])) {
    $ret = array();

//	This is for custom errors;
    /*	$custom_error= array();
        $custom_error['jquery-upload-file-error']="File already exists";
        echo json_encode($custom_error);
        die();
    */
    $error = $_FILES["myfile"]["error"];

// You need to handle both cases
// If any browser does not support serializing of multiple files using FormData()
    if (!is_array($_FILES["myfile"]["name"])) {  // Single file
        $fileName = $_FILES["myfile"]["name"];
        $timestamp = time();  // Get the current timestamp
        $newFileName = $timestamp . "_" . $fileName;  // Append the timestamp to the file name
        move_uploaded_file($_FILES["myfile"]["tmp_name"], $output_dir . $newFileName);
        $ret[] = $newFileName;
    } else {  // Multiple files, file[]
        $fileCount = count($_FILES["myfile"]["name"]);
        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $_FILES["myfile"]["name"][$i];
            $timestamp = time();  // Get the current timestamp
            $newFileName = $timestamp . "_" . $fileName;  // Append the timestamp to the file name
            move_uploaded_file($_FILES["myfile"]["tmp_name"][$i], $output_dir . $newFileName);
            $ret[] = $newFileName;
        }
    }
    echo json_encode($ret);
}

?>