<?php
include_once ("./config.php");
$db = new DB();

$uploads = Utility::selectAll('uploads');
$user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);

// Apply filters
$filtered_uploads = $uploads;

// Debug information
$debug = false;  // Set to true when debugging needed
if($debug) {
    echo "Initial count: " . count($filtered_uploads) . "<br>";
}

if (!empty($_GET['system'])) {
    $filtered_uploads = array_filter($filtered_uploads, function($upload) {
        return $upload['system'] == $_GET['system'];
    });
    if($debug) {
        echo "After system filter: " . count($filtered_uploads) . "<br>";
        echo "Selected system: " . $_GET['system'] . "<br>";
    }
}

if (!empty($_GET['category'])) {
    $filtered_uploads = array_filter($filtered_uploads, function($upload) {
        return $upload['category'] == $_GET['category'];
    });
    if($debug) {
        echo "After category filter: " . count($filtered_uploads) . "<br>";
        echo "Selected category: " . $_GET['category'] . "<br>";
    }
}

if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $filtered_uploads = array_filter($filtered_uploads, function($upload) {
        $upload_date = strtotime($upload['uploaded_time']);
        $start_date = strtotime($_GET['start_date']);
        $end_date = strtotime($_GET['end_date'] . ' 23:59:59');
        return $upload_date >= $start_date && $upload_date <= $end_date;
    });
    if($debug) {
        echo "After date filter: " . count($filtered_uploads) . "<br>";
        echo "Date range: " . $_GET['start_date'] . " to " . $_GET['end_date'] . "<br>";
    }
}

// Convert filtered_uploads back to array after filtering
$filtered_uploads = array_values($filtered_uploads);

// Check if it's a download request
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    // Use XAMPP's temp directory
    $tempDir = '/Applications/XAMPP/xamppfiles/temp/';
    $zipName = 'documents_' . date('Y-m-d_His') . '.zip';
    $zipPath = $tempDir . $zipName;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        die("Cannot create zip file");
    }

    // Debug information
    echo "Filtered uploads count: " . count($filtered_uploads) . "<br>";

    // Add files to zip
    $fileCount = 0;
    foreach ($filtered_uploads as $upload) {
        $filepath = $upload['path'];
        echo "Checking file: " . $filepath . "<br>"; // Debug line
        if (file_exists($filepath)) {
            echo "File exists, adding to zip<br>"; // Debug line
            // Get just the filename from the path
            $filename = basename($filepath);
            // Add file to zip
            $zip->addFile($filepath, $filename);
            $fileCount++;
        } else {
            echo "File does not exist: " . $filepath . "<br>"; // Debug line
        }
    }

    $zip->close();

    // Check if any files were added
    if ($fileCount == 0) {
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
        echo "Debug information:<br>";
        echo "No files were added to the zip.<br>";
        echo "Filtered uploads array:<br>";
        print_r($filtered_uploads);
        die();
    }

    // Send the file to the browser
    if (file_exists($zipPath)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($zipPath);

        // Clean up
        unlink($zipPath);
        exit;
    } else {
        die("Error creating zip file");
    }
}
?>

<br />
<div class="container">
    <div class="row p-3">
        <div class="col-12">
            <div class="btn-group float-right" role="group" aria-label="Basic example">
                <a href="./?p=settings" class="btn btn-custom"><i class="fa fa-arrow-left">&nbsp;</i> Back</a>
                <a href="./?p=incoming_system_uploads" class="btn btn-custom"><i class="fa fa-folder">&nbsp;</i> Incoming System Uploaded</a>
            </div>
        </div>
    </div>

    <div class="row p-3">
        <div class="col-12">
            <div class="float-left">
                <h3>Incoming System Uploaded</h3>
            </div>
            <div class="float-right">
                <?php if ($user->can('DOWNLOAD_FILES')){ ?>
                <button id="downloadAll" class="btn btn-custom">
                    <i class="fa fa-download"></i> Download All
                </button>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="search-form">
        <form method="GET" action="" id="filterForm">
            <input type="hidden" name="p" value="incoming_system_uploads">  <!-- Changed this line -->
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>System</label>
                        <select name="system" class="form-control">
                            <option value="">All Systems</option>
                            <?php
                            $systems = array_unique(array_column($uploads, 'system'));
                            foreach($systems as $system) {
                                $selected = (isset($_GET['system']) && $_GET['system'] == $system) ? 'selected' : '';
                                echo "<option value='".htmlspecialchars($system)."' $selected>".htmlspecialchars($system)."</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <option value="">All Categories</option>
                            <?php
                            $categories = array_unique(array_column($uploads, 'category'));
                            foreach($categories as $category) {
                                $selected = (isset($_GET['category']) && $_GET['category'] == $category) ? 'selected' : '';
                                echo "<option value='".htmlspecialchars($category)."' $selected>".htmlspecialchars($category)."</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control"
                               value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control"
                               value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                    </div>
                </div>
            </div>
        </form>
    </div>
    <?php if(empty($filtered_uploads)): ?>
        <div class="alert alert-warning" style="background-color: #404040; color: #f08c00; border-color: #f08c00;">
            No records found matching the selected filters.
        </div>
    <?php else: ?>

        <div class="search-result" id="search-result" style="min-height:400px;">
            <div class="row">
                <div class="col-md-12">
                    <table id="table" data-search="true" data-pagination="true" data-page-size="200">
                        <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th class="text-center">Uploaded Time</th>
                            <th class="text-center">System</th>
                            <th class="text-center">Category</th>
                            <th class="text-center">File</th>
                            <th class="text-center">Uploaded User</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $no = 1;
                        foreach ($filtered_uploads as $index => $upload) {
                            $filename = preg_replace('/^.*\/([^\/]+)$/', '$1', $upload['path']);
                            $displayName = preg_replace('/\.pdf$/', '', $filename);
                            ?>
                            <tr id="upload-row-<?=$upload['id']?>">
                                <td><?=$no?></td>
                                <td class="text-left"><?=$upload['uploaded_time']?></td>
                                <td class="text-left"><?=$upload['system']?></td>
                                <td class="text-left"><?=$upload['category']?></td>
                                <td class="text-left">
                                    <a href="#" class="view-pdf" data-path="<?=htmlspecialchars($upload['path'])?>" style="color: #f08c00;">
                                        <i class="fa fa-file-pdf-o file-icon"></i><?=htmlspecialchars($displayName)?>
                                    </a>
                                </td>
                                <td class="text-left"><?=$upload['uploaded_user']?></td>
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
    <?php endif; ?>

</div>

<!-- PDF Viewer Modal -->
<div class="modal fade" id="pdfModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="color: #f08c00;">View PDF</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" style="color: #f08c00;">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <iframe id="pdfFrame" style="width: 100%; height: 500px;" frameborder="0"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
    $(function() {
        // Initialize bootstrap table
        $('#table').bootstrapTable({});

        // Handle PDF viewing
        $('.view-pdf').click(function(e) {
            e.preventDefault();
            var pdfPath = $(this).data('path');
            // Use serve_file.php to serve files
            $('#pdfFrame').attr('src', '<?php echo BASE_URL; ?>serve_file.php?file=' + encodeURIComponent(pdfPath));
            $('#pdfModal').modal('show');
        });

        // Handle download all
        // Handle download all
        $('#downloadAll').click(function(e) {
            e.preventDefault();
            var currentSystem = $('select[name="system"]').val();
            var currentCategory = $('select[name="category"]').val();
            var startDate = $('input[name="start_date"]').val();
            var endDate = $('input[name="end_date"]').val();

            window.location.href = './?p=incoming_system_uploads&action=download' +
                '&system=' + encodeURIComponent(currentSystem) +
                '&category=' + encodeURIComponent(currentCategory) +
                '&start_date=' + encodeURIComponent(startDate) +
                '&end_date=' + encodeURIComponent(endDate);
        });

        // Auto-submit form when filters change
        // Auto-submit form when filters change
        $('select[name="system"], select[name="category"], input[name="start_date"], input[name="end_date"]').change(function() {
            // Get the current form
            var form = $('#filterForm');

            // Update hidden input value
            form.find('input[name="p"]').val('incoming_system_uploads');

            // Submit the form
            form.submit();
        });
    });
</script>