<?php
include_once ("./config.php");
$db = new DB();

class DatabaseRecordsCleaner {
    private $archivesPath;
    private $db;
    private $filesInFolder = [];
    private $recordsInDatabase = [];
    private $recordsToDelete = [];

    public function getExistingFiles() {
        return $this->filesInFolder;
    }

    public function getDatabaseRecords() {
        return $this->recordsInDatabase;
    }

    public function __construct($archivesPath, $db) {
        $this->archivesPath = rtrim($archivesPath, '/');
        $this->db = $db;
    }

    private function getFilesFromFolder() {
        if (!is_dir($this->archivesPath)) {
            throw new Exception("Directory not found: " . $this->archivesPath);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->archivesPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $this->filesInFolder[] = basename($file->getPathname());
            }
        }
    }

    private function getRecordsFromDatabase() {
        $query = "SELECT id, path FROM archives WHERE path IS NOT NULL AND path != ''";
        $result = $this->db->query($query);

        if (!$result) {
            throw new Exception("Database query failed: " . $this->db->error);
        }

        while ($row = $result->fetch_assoc()) {
            if (!empty($row['path'])) {
                $filename = basename($row['path']);
                $this->recordsInDatabase[] = [
                    'id' => $row['id'],
                    'filename' => $filename,
                    'full_path' => $row['path']
                ];
            }
        }
    }

    private function identifyRecordsToDelete() {
        foreach ($this->recordsInDatabase as $record) {
            if (!in_array($record['filename'], $this->filesInFolder)) {
                $this->recordsToDelete[] = $record;
            }
        }
    }

    public function analyzeRecords() {
        try {
            $this->getFilesFromFolder();
            $this->getRecordsFromDatabase();
            $this->identifyRecordsToDelete();
            return $this->recordsToDelete;
        } catch (Exception $e) {
            throw new Exception("Analysis failed: " . $e->getMessage());
        }
    }
}

$archivesPath = '../allfiles/pf-archives/';
$cleaner = new DatabaseRecordsCleaner($archivesPath, $db);
$orphanedRecords = $cleaner->analyzeRecords();

$user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);
?>

<br />
<div class="container">
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group">
                <a href="./?p=settings" class="btn btn-custom"><i class="fa fa-arrow-left">&nbsp;</i> Back</a>
            </div>
        </div>
    </div>
    <div class="row p-3">
        <div class="col-12">
            <div class="float-left">
                <h3>Orphaned Database Records</h3>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-box">
                <h4><i class="fas fa-folder"></i> Files in Directory</h4>
                <h2><?php echo count($cleaner->getExistingFiles()); ?></h2>
                <small>Total files in archives folder</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-box">
                <h4><i class="fas fa-database"></i> Database Records</h4>
                <h2><?php echo count($cleaner->getDatabaseRecords()); ?></h2>
                <small>Total records in database</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-box">
                <h4><i class="fas fa-exclamation-triangle"></i> Orphaned Records</h4>
                <h2><?php echo count($orphanedRecords); ?></h2>
                <small>Records without files</small>
            </div>
        </div>
    </div>
    <div class="search-result" id="search-result" style="min-height:400px;">
        <div class="row">
            <div class="col-md-12">
                <table id="table" data-search="true" data-pagination="true" data-page-size="200">
                    <thead>
                    <tr>
                        <th class="text-center">#</th>
                        <th class="text-center">Record ID</th>
                        <th class="text-center">File Name</th>
                        <th class="text-center">Full Path</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $no = 1;
                    foreach ($orphanedRecords as $record) {
                        ?>
                        <tr id="record-row-<?=$record['id']?>">
                            <td><?=$no?></td>
                            <td class="text-center"><?=$record['id']?></td>
                            <td class="text-left"><?=$record['filename']?></td>
                            <td class="text-left"><?=$record['full_path']?></td>
                            <td>
                                <div class='btn-groups'>
                                    <?php if ($user->can('RECORD_DELETION')){ ?>
                                        <a onclick='deleteRecord(<?=$record["id"]?>)' data-toggle='tooltip' title='Delete'
                                           class='btn text-danger btn-xs'><i class='fa fa-trash'></i></a>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                        <?php
                        $no++;
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <br>
</div>

<script>
    $(function() {
        $('#table').bootstrapTable({});
    });

    function deleteRecord(id) {
        var my_row_id = "record-row-" + id;
        swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#F08C00CC',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.value) {
                $.ajax({
                    url: './ajax.php?fx=delete',
                    type: 'POST',
                    data: {table: 'archives', id: id},
                })
                    .done(function(response){
                        $('#'+my_row_id).hide('slow');
                        swal.fire('Deleted!', "Database record has been deleted!", "success");
                    })
                    .fail(function(){
                        swal.fire('Oops...', 'Something went wrong with ajax !', 'error');
                    });
            }
        })
    }
</script>