<?php
    session_start();
require_once ("./config.php");
require_once ("./models/DB.php");
require_once ("./models/Entity.php");
require_once ("./models/File.php");
include './xcrud/xcrud.php';

    if(isset($_REQUEST['fx'])) {
        $db = Xcrud_db::get_instance();
        $fx= $_REQUEST['fx'];
        switch ($fx) {
            case 'delete': //
                if(isset($_POST['table'],$_POST['id'])){
                    // Table is an identifier (can't be bound) — restrict to identifier chars; ids are integers.
                    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table']);
                    $id = (int) $_POST['id'];
                    $limit = isset($_POST['limit']) ? "LIMIT ".(int)$_POST['limit'] : "";
                    $query = "DELETE FROM `{$table}` WHERE id = '{$id}' {$limit}";
                    $res = $db->query($query);
                    echo $res;
                }
                break;
            case 'delete_user_from_group': //
                if(isset($_POST['table'],$_POST['id'],$_POST['group_id'])){
                    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table']);
                    $id = (int) $_POST['id'];
                    $group_id = (int) $_POST['group_id'];
                    $limit = isset($_POST['limit']) ? "LIMIT ".(int)$_POST['limit'] : "";
                    $query = "DELETE FROM `{$table}` WHERE user = '{$id}' AND user_group = '{$group_id}' {$limit}";
                    $res = $db->query($query);
                    echo $res;
                }
                break;
            case 'next_file':
                    $result = nextFile($_POST);
                break;
            case 'save_data':
                    $result = saveData($_POST);
                break;
            case 'new_folder':
                    $result = newFolder($_POST);
                break;
            case 'new_sub_folder':
                    $result = newSubFolder($_POST);
                break;
            case 'upload_files':
                    $result = uploadFiles($_POST);
                break;
            case 'load_folders':
                    exit(loadFolders($_POST));
                break;
            case 'load_sub_folders':
                    exit(loadSubFolders($_POST));
                break;
            case 'search_file':
                    $result = searchFile($_POST);
                break;
            default:
                    $result = [];
                break;
        }
        exit(json_encode($result));
    }

function uploadFiles($data) {

    /* Getting file name */
    $filename = $_FILES['file']['name'];

    /* Getting File size */
    $filesize = $_FILES['file']['size'];

    /* Location */
    $location = "../allfiles/pf-archives/".$filename;

    $return_arr = array();

    /* Upload file */
    if(move_uploaded_file($_FILES['file']['tmp_name'],$location)){
        $src = "default.png";

        // checking file is image or not
        if(is_array(getimagesize($location))){
            $src = $location;
        }
        $return_arr = array("name" => $filename,"size" => $filesize, "src"=> $src);
    }

    return json_encode($return_arr);
}

    // Return a path relative to FILES_PATH so serve_file.php can locate it.
    // Ingest API stores absolute paths; web uploads store "../allfiles/..." relative paths.
    function _normalizeArchivePath(string $storedPath): string {
        $base = rtrim(FILES_PATH, '/\\');
        if (strncmp($storedPath, $base, strlen($base)) === 0) {
            // Absolute path starting with FILES_PATH — strip that prefix
            return ltrim(substr($storedPath, strlen($base)), '/\\');
        }
        return $storedPath;
    }

    function nextFile($data) {
        $direction  = isset($data['direction']) ? $data['direction'] : "next";
        $current_user_id = isset($_SESSION[SESSION_NAME]) ? $_SESSION[SESSION_NAME]['user_id'] : null;

        $db = new DB();
        $file_ids = $db->query("SELECT id FROM archives WHERE completed != 1", "SELECT");
        if($current_user_id) {
            $current_user = $db->get("users", "*", "id", $current_user_id);
            $current_user = reset($current_user);
            $current_id = isset($current_user['current_file']) ? (int)$current_user['current_file'] : 0;
            if($direction == "previous") {
                $file_res = $db->query("SELECT * FROM archives WHERE id < {$current_id} AND completed = 0 AND (id NOT IN (SELECT current_file FROM users WHERE current_file IS NOT NULL)) ORDER BY id DESC", "SELECT", 1);
            } else {
                $file_res = $db->query("SELECT * FROM archives WHERE id > {$current_id} AND completed = 0 AND (id NOT IN (SELECT current_file FROM users WHERE current_file IS NOT NULL))", "SELECT", 1);
            }
            if($file_res) {
                $new_id = $file_res['id'];
                $file_data = [
                    "id" => $file_res['id'],
                    "year" => $file_res['year'] ?? date('Y'),
                    "name" => $file_res['name'],
                    "document_type" => $file_res['document_type'],
                    "number" => $file_res['number'],
                    "document_date" => $file_res['document_date'] ?? date('Y-m-d'),
                    "payee_name" => $file_res['payee_name'],
                    "sub_folder_id" => $file_res['sub_folder_id'],
                    "description" => $file_res['description'],
                    "duplicate" => $file_res['duplicate'],
                    "url" => _normalizeArchivePath($file_res['path'])
                ];
                $upd = $db->query("UPDATE users SET current_file = ? WHERE id = ?", "UPDATE", false, [$new_id, $current_user_id]);
                return $file_data;
            } else {
                return $db->errorMessage();
            }

        } else {
            $file_data = ["NOT_LOGGED_IN"];
        }

        return $file_data;
    }


    function saveData($data) {
        if($data) {
            $id = $data['id'];
            $file = new File($id);
            if($data['document_type'] == "") {
                unset($data['document_type']);
            }
            if($data['completed'] == '1') {
                $data['edited_by'] = $_SESSION[SESSION_NAME]['user_id'];
                $data['completed'] = 1;
            }
            if($data['duplicate'] == '1') {}
            $file->patch($data);
            if($res = $file->update())  {
                return $res;
            } else {
                echo $file->db->errorMessage();
                return 0;
            }
        } else {
            return 0;
        }
    }

    function newFolder($data) {
        $db = new DB();
        $new_data = ['name' => isset($data['name']) ? $data['name'] : null];
        if(isset($data['description'])) {
            $new_data['description'] = $data['description'];
        }
        $ins = $db->insert('archive_document_folders', $new_data);
        echo $db->errorMessage();
        return $ins ? 1 : 0;
    }

    function newSubFolder($data) {
        $db = new DB();
        $new_data = ['name' => isset($data['name']) ? $data['name'] : null];
        if(isset($data['description'])) {
            $new_data['description'] = $data['description'];
        }
        if(isset($data['archive_document_folder_id'])) {
            $new_data['archive_document_folder_id'] = $data['archive_document_folder_id'];
        }
        $ins = $db->insert('archive_document_sub_folders', $new_data);
        echo $db->errorMessage();
        return $ins ? 1 : 0;
    }

    function loadFolders($data) {
        $db = new DB();
        $folders = $db->fetch('archive_document_folders');
        $html = '<option></option>';
        foreach ($folders as $index => $folder) {
            $html .= "<option value='{$folder['id']}' title='{$folder['description']}'>{$folder['name']}</option>";
        }
        return $html;
    }

    function loadSubFolders($data) {
        $db = new DB();
        $sub_folders = $db->fetch('archive_document_sub_folders');
        $html = '<option></option>';
        foreach ($sub_folders as $index => $sub_folder) {
            $html .= "<option value='{$sub_folder['id']}' title='{$sub_folder['description']}'>{$sub_folder['name']}</option>";
        }
        return $html;
    }

    function searchFile($data) {
        $db = new DB();
        $year = isset($data['year']) ? $data['year'] : null;
        $document_type = isset($data['document_type']) ? $data['document_type'] : null;
        $document_date = isset($data['document_date']) ? $data['document_date'] : null;
        $folder_id = isset($data['folder_id']) ? $data['folder_id'] : null;
        $sub_folder_id = isset($data['sub_folder_id']) ? $data['sub_folder_id'] : null;
        $description = isset($data['description']) ? $data['description'] : null;
        $number = isset($data['number']) ? $data['number'] : null;
        // All user-supplied filters are bound as parameters — never interpolated.
        $q = "SELECT a.* FROM archives a
                JOIN archive_document_sub_folders adsf ON (adsf.id = a.sub_folder_id)
                JOIN archive_document_folders adf ON (adf.id = adsf.archive_document_folder_id)
                WHERE a.year = ? ";
        $params = [$year];
        if($document_type != null) {
            $q .= "AND a.document_type = ? ";
            $params[] = $document_type;
        }
        if($sub_folder_id != null) {
            $q .= "AND a.sub_folder_id = ? ";
            $params[] = $sub_folder_id;
        }
        if($folder_id != null) {
            $q .= "AND adf.id = ? ";
            $params[] = $folder_id;
        }
        if($document_date != null) {
            $q .= "AND a.document_date = ? ";
            $params[] = $document_date;
        }
        if($number != null) {
            $q .= "AND a.number LIKE ? ";
            $params[] = '%'.$number.'%';
        }
        if($description != null) {
            $description = str_replace(' ', '%', trim($description));
            $q .= "AND a.description LIKE ? ";
            $params[] = '%'.$description.'%';
        }

        $res = $db->query($q, 'SELECT', 'ALL', $params);
        if(is_array($res)) {
            if(count($res) == 1) {
                return reset($res);
            } else if(count($res) > 1) {
                return $res;
            } else {
                return [];
            }
        } else {
            return 0;
        }
    }
?>