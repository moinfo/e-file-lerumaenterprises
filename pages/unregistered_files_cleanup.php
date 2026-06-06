<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once ("./config.php");
$db = new DB();


class ArchivesCleaner {
    private $archivesPath;
    private $db;
    private $filesInFolder = [];
    private $filesInDatabase = [];
    private $filesToDelete = [];

    public function __construct($archivesPath, $db) {
        // Keep the relative path as is
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
                // Keep the relative path format
                $this->filesInFolder[] = str_replace('\\', '/', $file->getPathname());
            }
        }
    }

    private function getFilesFromDatabase() {
        $query = "SELECT path FROM archives WHERE path IS NOT NULL AND path != ''";
        $result = $this->db->query($query);

        if (!$result) {
            throw new Exception("Database query failed: " . $this->db->error);
        }

        while ($row = $result->fetch_assoc()) {
            if (!empty($row['path'])) {
                $filename = basename($row['path']);
                // Use the same relative path format
                $fullPath = $this->archivesPath . '/' . $filename;
                $this->filesInDatabase[] = $fullPath;
            }
        }
    }

    public function deleteFile($filePath) {
        try {
            // Keep the path as is
            if (!file_exists($filePath)) {
                throw new Exception("File not found: " . $filePath);
            }

            if (!is_writable($filePath)) {
                throw new Exception("File is not writable: " . $filePath);
            }

            // Check if file is in the archives directory
            $normalizedPath = str_replace('\\', '/', $filePath);
            $normalizedArchivesPath = str_replace('\\', '/', $this->archivesPath);

            if (strpos($normalizedPath, $normalizedArchivesPath) !== 0) {
                throw new Exception("File is outside of archives directory");
            }

            if (unlink($filePath)) {
                return [
                    'success' => true,
                    'message' => 'File deleted successfully',
                    'path' => $filePath
                ];
            } else {
                throw new Exception("Failed to delete file");
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'path' => $filePath
            ];
        }
    }

    private function identifyFilesToDelete() {
        $this->filesToDelete = array_diff($this->filesInFolder, $this->filesInDatabase);
    }

    private function getFileInfo($filePath) {
        return [
            'name' => basename($filePath),
            'size' => filesize($filePath),
            'modified' => date("Y-m-d H:i:s", filemtime($filePath)),
            'mime' => mime_content_type($filePath),
            'path' => $filePath
        ];
    }

    public function analyzeFiles() {
        try {
            $this->getFilesFromFolder();
            $this->getFilesFromDatabase();
            $this->identifyFilesToDelete();

            $fileDetails = [];
            foreach ($this->filesToDelete as $file) {
                $fileDetails[] = $this->getFileInfo($file);
            }

            return [
                'total_files_in_folder' => count($this->filesInFolder),
                'total_files_in_database' => count($this->filesInDatabase),
                'files_to_delete' => count($this->filesToDelete),
                'file_details' => $fileDetails
            ];
        } catch (Exception $e) {
            throw new Exception("Analysis failed: " . $e->getMessage());
        }
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        if (!isset($_POST['file_path'])) {
            throw new Exception("No file path provided");
        }

        $cleaner = new ArchivesCleaner('../allfiles/pf-archives/', $db);

        if ($_POST['action'] === 'delete_file') {
            $result = $cleaner->deleteFile($_POST['file_path']);
            echo json_encode($result);
        } else {
            throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Main display
$user_id = $_SESSION[SESSION_NAME]['user_id'];
$user = new User($user_id);

try {
    $archivesPath = '../allfiles/pf-archives/';
    $cleaner = new ArchivesCleaner($archivesPath, $db);
    $analysisResults = $cleaner->analyzeFiles();
    ?>

<div class="container mt-4">
        <div class="row mb-3">
            <div class="col-12">
                <div class="btn-group float-end" role="group">
                    <a href="./?p=settings" class="btn btn-custom">
                        <i class="fa fa-arrow-left">&nbsp;</i> Back
                    </a>
                </div>
            </div>
        </div>

        <h2 class="mb-4">Unregistered Files Cleanup</h2>

        <!-- Stats Boxes -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-box">
                    <h4><i class="fas fa-folder"></i> Files in Folder</h4>
                    <h2><?php echo $analysisResults['total_files_in_folder']; ?></h2>
                    <small>Total files in pf-archives folder</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-box">
                    <h4><i class="fas fa-database"></i> Database Records</h4>
                    <h2><?php echo $analysisResults['total_files_in_database']; ?></h2>
                    <small>Files registered in database</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Unregistered Files</h4>
                    <h2><?php echo $analysisResults['files_to_delete']; ?></h2>
                    <small>Files to be reviewed and deleted</small>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['debug'])): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Debug Information</h5>
                    <div class="alert alert-info">
                        <p><strong>Archives Path:</strong> <?php echo htmlspecialchars($archivesPath); ?></p>
                        <p><strong>Sample Files in Directory:</strong></p>
                        <?php
                        $files = glob($archivesPath . '/*');
                        $sampleFiles = array_slice($files, 0, 5);
                        if (!empty($sampleFiles)) {
                            echo "<ul>";
                            foreach ($sampleFiles as $file) {
                                echo "<li>" . htmlspecialchars(basename($file)) . "</li>";
                            }
                            echo "</ul>";
                        } else {
                            echo "<p>No files found in directory</p>";
                        }
                        ?>

                        <p><strong>Sample Database Records:</strong></p>
                        <?php
                        $debugQuery = "SELECT path FROM archives WHERE path IS NOT NULL ORDER BY id DESC LIMIT 5";
                        $debugResult = $db->query($debugQuery);
                        if ($debugResult && $debugResult->num_rows > 0) {
                            echo "<ul>";
                            while ($row = $debugResult->fetch_assoc()) {
                                echo "<li>DB Path: " . htmlspecialchars($row['path']) . "<br>";
                                echo "Filename: " . htmlspecialchars(basename($row['path'])) . "</li>";
                            }
                            echo "</ul>";
                        } else {
                            echo "<p>No paths found in database</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($analysisResults['file_details'])): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>File Name</th>
                        <th>Size</th>
                        <th>Modified Date</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $no = 1;
                    foreach ($analysisResults['file_details'] as $file): ?>
                        <tr class="file-row" id="row-<?php echo md5($file['path']); ?>">
                            <td><?php echo $no; ?></td>
                            <td><?php echo htmlspecialchars($file['name']); ?></td>
                            <td class="file-size"><?php echo number_format($file['size'] / 1024, 2); ?> KB</td>
                            <td><?php echo $file['modified']; ?></td>
                            <td><?php echo $file['mime']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary preview-btn"
                                        data-path="<?php echo htmlspecialchars($file['path']); ?>"
                                        data-mime="<?php echo htmlspecialchars($file['mime']); ?>">
                                    <i class="fas fa-eye"></i> Preview
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn"
                                        data-path="<?php echo htmlspecialchars($file['path']); ?>">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php
                        $no++;
                    endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Preview Modal -->
            <div class="modal fade" id="previewModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">File Preview</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="preview-container"></div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-info">No unregistered files found.</div>
        <?php endif; ?>
</div>

<script>
        $(document).ready(function() {
            // Preview functionality
            $('.preview-btn').click(function() {
                const filePath = $(this).data('path');
                const mimeType = $(this).data('mime');
                const previewContainer = $('#preview-container');
                // Use serve_file.php to serve files
                const serveUrl = '<?php echo BASE_URL; ?>serve_file.php?file=' + encodeURIComponent(filePath);

                previewContainer.empty();

                if (mimeType.startsWith('image/')) {
                    previewContainer.html(`<img src="${serveUrl}" class="img-fluid" />`);
                } else if (mimeType === 'application/pdf') {
                    previewContainer.html(`<iframe src="${serveUrl}" class="preview-frame"></iframe>`);
                } else {
                    previewContainer.html('<div class="alert alert-warning">Preview not available for this file type</div>');
                }

                $('#previewModal').modal('show');
            });

            // Delete functionality
            $('.delete-btn').click(function() {
                const btn = $(this);
                const filePath = btn.data('path');
                const row = btn.closest('tr');

                if (confirm('Are you sure you want to delete this file?')) {
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');

                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            action: 'delete_file',
                            file_path: filePath
                        },
                        success: function(response) {
                            if (response.success) {
                                row.fadeOut(400, function() {
                                    row.remove();
                                    updateCounters();
                                });
                            } else {
                                alert('Error: ' + response.message);
                                btn.prop('disabled', false)
                                    .html('<i class="fas fa-trash"></i> Delete');
                            }
                        },
                        error: function() {
                            alert('Server error occurred');
                            btn.prop('disabled', false)
                                .html('<i class="fas fa-trash"></i> Delete');
                        }
                    });
                }
            });

            // Function to update counters after deletion
            function updateCounters() {
                const remainingFiles = $('.file-row').length;
                $('.stats-box:last-child h2').text(remainingFiles);
            }
        });
</script>

<?php
} catch (Exception $e) {
    echo "<div class='alert alert-danger' style='margin: 20px; background-color: #404040; color: #f08c00; border-color: #f08c00;'>";
    echo "Error: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>