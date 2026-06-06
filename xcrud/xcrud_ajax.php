<?php
include_once('../config.php');
include ('xcrud.php');
header('Content-Type: text/html; charset=' . Xcrud_config::$mbencoding);
echo Xcrud::get_requested_instance();
