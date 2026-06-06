<?php
include_once ("./config.php");
$db = new DB();
include './processors/settings.users.php';


?>
<br />
<div class="default-container">
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group" aria-label="Basic example">
                <a href="./?p=settings" class="btn btn-custom "><i class="fa fa-arrow-left">&nbsp;</i> Back</a>
                <a href="./?p=settings.users" class="btn btn-custom "><i class="fa fa-folder">&nbsp;</i> User Access & Group</a>
            </div>
        </div>
    </div>
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group" aria-label="Basic example">
<!--                <button data-toggle="modal" data-target="#add-archive-modal" class="btn btn-custom registerArchive" type="button"><i-->
<!--                            class="fa fa-plus">&nbsp;</i>Add Archive-->
<!--                </button>-->
            </div>
            <div class="float-left">
                <h3>User Access & Group</h3>
            </div>
        </div>
    </div>
    <div class="row p-3">

        <?php
        if(!isset($_GET['sp']) ){
            Router::load('settings.users.users');
        }else{
            Router::load($_GET['sp']);
        }
        ?>

    </div>

    <br>
</div>



<script>
    $(function() {
        $('#table').bootstrapTable({

        })
    });

</script>