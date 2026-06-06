<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('./config.php');
file_put_contents('debug.log', print_r($_FILES, true));

if (isset($_FILES['file'])) {
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/allfiles/pf-archives/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    chmod($upload_dir, 0777);

    file_put_contents('debug.log', "\nAttempting upload to: " . $upload_dir, FILE_APPEND);

    if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $_FILES['file']['name'])) {
        file_put_contents('debug.log', "\nFile uploaded successfully", FILE_APPEND);

        try {
            $db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
            if ($db->connect_error) {
                throw new Exception("Database connection failed: " . $db->connect_error);
            }

            $query = "INSERT INTO `uploads` (system, category, path, uploaded_time, uploaded_user) 
                     VALUES (?, ?, ?, NOW(), ?)";
            
            $stmt = $db->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $db->error);
            }

            $path = '../allfiles/pf-archives/'.$_FILES['file']['name'];
            
            // Verify POST data exists
            if (!isset($_POST['system']) || !isset($_POST['category']) || !isset($_POST['uploaded_user'])) {
                throw new Exception("Missing required POST parameters");
            }
            
            // Bind parameters correctly
            if (!$stmt->bind_param('ssss', 
                $_POST['system'],
                $_POST['category'],
                $path,
                $_POST['uploaded_user']
            )) {
                throw new Exception("Binding parameters failed: " . $stmt->error);
            }
            
            // Execute with error checking
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            file_put_contents('debug.log', "\nException: " . $e->getMessage(), FILE_APPEND);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        $error = error_get_last();
        file_put_contents('debug.log', "\nUpload failed: " . print_r($error, true), FILE_APPEND);
        echo json_encode(['success' => false, 'error' => 'File upload failed']);
    }
}
?>