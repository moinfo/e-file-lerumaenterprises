<?php
ob_start();
session_start();

require_once ("./config.php"); // sets error_reporting / display_errors per environment
require_once ("./models/DB.php");
require_once ("./models/Entity.php");
require_once ("./models/File.php");
require_once ("./models/User.php");
require_once ("./models/ArchiveDocumentFolder.php");
require_once ("./models/ArchiveDocumentSubFolder.php");
require_once ("./models/DocumentType.php");
require_once ("./models/Utility.php");
require_once ("./models/Router.php");
require_once ("./models/Menu.php");
include './xcrud/xcrud.php';
include_once './models/Autoload.php';

if(!isset($_SESSION[SESSION_NAME]['user_id'])) {
    header("Location: ./login.php");
}

$active = isset($_GET['p']) ? $_GET['p'] : 'dashboard';
$menu = [
    ['name' => "dashboard", 'title' => 'Dashboard', 'link' => '.?p=dashboard', 'icon' => 'fa fa-cog'],
    ['name' => "editor", 'title' => 'Editor', 'link' => '.?p=editor', 'icon' => 'fa fa-cog'],
    ['name' => "uploads", 'title' => 'Upload Synchronization', 'link' => '.?p=uploads', 'icon' => 'fa fa-cog'],
    ['name' => "settings", 'title' => 'Settings', 'link' => '.?p=settings', 'icon' => 'fa fa-cog'],
    ['name' => "folders", 'title' => 'Folders', 'link' => '.?p=folders', 'icon' => 'fa fa-cog'],
    ['name' => "search", 'title' => 'Search', 'link' => '.?p=search', 'icon' => 'fa fa-search'],
    ['name' => "summary", 'title' => 'Summary', 'link' => '.?p=summary', 'icon' => 'fa fa-cog'],
];

$user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);

// Determine which page to load
$page_content_file = null;
if (isset($_GET['p'])) {
    $page = $_GET['p'];
    $page_path = './pages/' . $page . '.php';

    if (is_file($page_path)) {
        if (Router::validateAccess($page, $user_id)) {
            $page_content_file = $page_path;
        } else {
            Utility::errorPage("You are not allowed to use this feature");
            exit;
        }
    } else {
        $page_content_file = './pages/404.php';
    }
}

// Load the main layout
include('./layouts/main.php');
?>

