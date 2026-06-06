<?php

    $db = new DB();
    $user_id = $_SESSION[SESSION_NAME]['user_id'];
    $user = $db->query("SELECT * FROm users WHERE id='{$user_id}'", 'SELECT', 1);
    $all_files = $db->query("SELECT COUNT(id) as `count` FROM archives", "SELECT", 1);
    $all_edited_files = $db->query("SELECT COUNT(id) as `count` FROM archives WHERE completed = 1", "SELECT", 10);
    $my_edited_files = $db->query("SELECT COUNT(id) as total FROM archives WHERE edited_by='{$user_id}' AND completed = 1", "SELECT", 1);
    $my_daily_edited_files = $db->query("SELECT  DISTINCT(DATE(updated_at)) as edit_date, COUNT(id) as total FROM archives WHERE edited_by='{$user_id}' AND completed = 1 GROUP BY DATE(updated_at)", "SELECT");
    $my_dupes = $db->query("SELECT  COUNT(id) as total FROM archives WHERE edited_by='{$user_id}' AND completed = 1 AND duplicate = 1", "SELECT", 1);
    $progress = (($all_edited_files['count'] / $all_files['count']) * 100);
    $my_progress = (($my_edited_files['total'] / $all_files['count']) * 100);

    ?>

<div class="content-box">
    <div class="content-box-header">SUMMARY</div>
    <div class="table-responsive content-box-body">
            <table class="table table-bordered">
                <thead></thead>
                <tbody>
                    <tr>
                        <th width="20%">User</th>
                        <th><?php echo $user['username']; ?></th>
                    </tr>
                    <tr>
                        <th width="20%">Total files Edited</th>
                        <th><?php echo $my_edited_files['total']; ?></th>
                    </tr>
                    <?php

                        foreach ($my_daily_edited_files as $index => $file) {
                            echo "
                            <tr>
                                <th width='20%'>{$file['edit_date']}</th>
                                <th>{$file['total']}</th>
                            </tr>
                            ";
                        }
                    ?>

                    <tr>
                        <th width="20%">Duplicate Files</th>
                        <th><?php echo $my_dupes['total']; ?></th>
                    </tr>

                    <tr>
                        <th width="20%">Progress</th>
                        <th><div class="progress">
                                <div class="progress-bar progress-bar-striped" role="progressbar" style="width:<?php echo $progress; ?>%;" aria-valuenow="<?php echo $all_edited_files['count']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $all_files['count']; ?>">
                                    <?php echo number_format($progress, 1); ?>%
                                </div>
                            </div></th>
                    </tr>
                </tbody>
            </table>
    </div>
</div>