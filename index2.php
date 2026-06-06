<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once ("./config.php");
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
session_start();
if(!isset($_SESSION[SESSION_NAME]['user_id'])) {
    header("Location: ./login.php");
}
//#f08c00
//#222
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

$user_id = $user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);

?>

<!doctype html>

<html lang="en">
<head>
    <meta charset="utf-8">
    <base href="<?php echo BASE_URL; ?>" />
    <title>File Bridge</title>
    <meta name="description" content="TheBridge File Manager">
    <meta name="author" content="TheBridge TechCreative">

    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="node_modules/bootstrap-datepicker/dist/css/bootstrap-datepicker.css">
    <link rel="stylesheet" href="node_modules/sweetalert2/dist/sweetalert2.min.css">
<!--    <link rel="stylesheet" type="text/css" href="https://common.olemiss.edu/_js/sweet-alert/sweet-alert.css">-->
    <link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="node_modules/select2/dist/css/select2.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/uploadfile.css">
    <link rel="stylesheet" href="assets/css/uploadfile.custom.css">
    <link href="https://unpkg.com/bootstrap-table@1.22.1/dist/bootstrap-table.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://common.olemiss.edu/_js/sweet-alert/sweet-alert.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<!--    <link href="http://hayageek.github.io/jQuery-Upload-File/4.0.11/uploadfile.css" rel="stylesheet">-->

    <script src="node_modules/jquery/dist/jquery.min.js"></script>
    <script src="node_modules/bootstrap/dist/js/bootstrap.min.js"></script>
    <script src="node_modules/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>
    <script src="node_modules/sweetalert2/dist/sweetalert2.all.min.js"></script>
    <script src="assets/js/jquery.uploadfile.js"></script>
    <script src="assets/js/jquery.uploadfile.min.js"></script>
<!--    <script src="https://common.olemiss.edu/_js/sweet-alert/sweet-alert.min.js"></script>-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="node_modules/select2/dist/js/select2.full.min.js"></script>
    <script src="node_modules/pdfobject/pdfobject.min.js"></script>
    <script src="./assets/js/main.js"></script>
    <script src="https://unpkg.com/bootstrap-table@1.22.1/dist/bootstrap-table.min.js"></script>
    <script src="https://unpkg.com/bootstrap-table@1.22.1/dist/extensions/custom-view/bootstrap-table-custom-view.js"></script>

    <!--    <script src="http://hayageek.github.io/jQuery-Upload-File/4.0.11/jquery.uploadfile.min.js"></script>-->


</head>

<body>
<header>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark">
        <a class="navbar-brand mr-5 pt-0 pl-4" href="#">
            <img src="assets/img/bridge_logo.png" class="img-responsive" height="40"/>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarText" aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarText">
            <ul class="navbar-nav mr-auto">
                <?php
                echo Menu::getUserMenu($user_id,$active);
                ?>
<!--                --><?php
//                    foreach ($menu as $index => $item) {
//                        $is_active = ($active == $item['name']) ? 'active' : '';
//                        echo "
//                            <li class='nav-item {$is_active}'><a class='nav-link' href='{$item['link']}'#'>{$item['title']} <span class='sr-only'>()</span></a></li>
//                        ";
//                    }
//                ?>
            </ul>
            <span class="navbar-text">
       <a class="mr-3" href="./login.php?logout"><i class="fa fa fa-sign-out">&nbsp;</i>Logout</a>
    </span>
        </div>
    </nav>
</header>
<div class="content main-page">
    <?php
    if (isset($_GET['p'])) {
        $page = $_GET['p'];
        if (is_file('./pages/' . $page . '.php')) {
            if (Router::validateAccess($page, $user_id)) {
                Router::load($_GET['p']);
            } else {
                Utility::errorPage("You are not allowed to use this feature");
            }
        } else {
            Router::load("404");
        }

    } else {
        //                Router::load('dashboard');
//        Router::load('dashboard.notifications');
//        Router::load('dashboard.finance');
//        Router::load('dashboard.hr');
    }


    ?>
</div>

<script>

    $(document).ready(function() {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
        });

    });

</script>
</body>

</html>

