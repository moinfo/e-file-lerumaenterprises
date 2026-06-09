<?php
include_once("./config.php");
$db = new DB();

$q = "SELECT adf.*, COUNT(a.sub_folder_id) AS total_folders FROM archive_document_folders adf
    JOIN archive_document_sub_folders adsf ON (adsf.archive_document_folder_id = adf.id)
    JOIN archives a ON (a.sub_folder_id = adsf.id) group by a.sub_folder_id order by count(a.sub_folder_id) DESC LIMIT 15 ";
$folders = $db->query($q, 'SELECT');

$file_path = '../allfiles/pf-archives/';
$local_files = @scandir($file_path);
$uploaded = is_array($local_files) ? count($local_files) - 2 : 0;

$unedited_files_query = "SELECT *  FROM archives WHERE completed = 0";
$unedited_files_ = $db->query($unedited_files_query, 'SELECT');
$unedited_files = count($unedited_files_);

$completed_files_query = "SELECT *  FROM archives WHERE completed = 1";
$completed_files_ = $db->query($completed_files_query, 'SELECT');
$completed_files = count($completed_files_);

$syn = max(0, $uploaded - ($unedited_files + $completed_files));

$document_types_query = "SELECT dt.*, COUNT(a.document_type) AS total_document_types FROM document_types dt JOIN archives a ON (a.document_type = dt.id) group by a.document_type order by count(a.document_type) DESC LIMIT 15 ";
$document_types = $db->query($document_types_query, 'SELECT');

$total_docs = $unedited_files + $completed_files;

// Total storage size of archived files (archives.size is stored in bytes).
$size_rows  = $db->query("SELECT COALESCE(SUM(size),0) AS total_size FROM archives", 'SELECT');
$total_size = (is_array($size_rows) && isset($size_rows[0]['total_size'])) ? (float) $size_rows[0]['total_size'] : 0;
$fmtSize = function ($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
    return round($bytes, ($i > 0 && $bytes < 100) ? 1 : 0) . ' ' . $units[$i];
};
$total_size_h = $fmtSize($total_size);
?>

<style>
    /* ===== Dashboard polish (scoped to .dash-page) ===== */
    .dash-page { animation: dashIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both; }
    @keyframes dashIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

    .dash-header {
        display: flex; align-items: flex-end; justify-content: space-between;
        gap: 1rem; flex-wrap: wrap; margin: 0.5rem 0 1.8rem;
    }
    .dash-title {
        font-family: var(--font-display, 'Bricolage Grotesque', sans-serif);
        font-weight: 800; font-size: 2.1rem; line-height: 1; letter-spacing: -0.02em;
        margin: 0;
        background: linear-gradient(100deg, #fff 35%, var(--light-orange, #ffb24d));
        -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    }
    .dash-sub { color: var(--text-muted, #9c9389); margin: 0.45rem 0 0; font-size: 0.96rem; }
    .dash-chips { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .dash-chip {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.55rem 0.95rem; border-radius: 999px;
        background: rgba(240, 140, 0, 0.10);
        border: 1px solid var(--border-color, rgba(240,140,0,0.16));
        color: #f6efe5; font-size: 0.86rem; font-weight: 600;
    }
    .dash-chip i { color: var(--primary-orange, #f08c00); }

    /* Stat cards */
    .dash-page .stat {
        position: relative; overflow: hidden;
        border-radius: 18px !important;
        animation: dashIn 0.6s ease-out both;
    }
    .dash-page .stat:nth-child(1), .dash-page .row > div:nth-child(1) .stat { animation-delay: 0.05s; }
    .dash-page .stat-grid > div:nth-child(2) .stat { animation-delay: 0.12s; }
    .dash-page .stat-grid > div:nth-child(3) .stat { animation-delay: 0.19s; }
    .dash-page .stat-grid > div:nth-child(4) .stat { animation-delay: 0.26s; }
    .dash-page .stat::before {
        content: ''; position: absolute; inset: 0 0 auto 0; height: 3px;
        background: var(--accent); opacity: 0.9;
    }
    .dash-page .stat::after {
        content: ''; position: absolute; width: 150px; height: 150px;
        top: -60px; right: -50px; border-radius: 50%;
        background: radial-gradient(circle, var(--accent-bg), transparent 70%);
        transition: transform 0.5s ease;
    }
    .dash-page .stat:hover::after { transform: scale(1.4); }
    .dash-page .stat .card-body { padding: 1.4rem 1.5rem; }
    .stat-row { display: flex; align-items: center; gap: 1rem; }
    .stat-tile {
        flex: 0 0 auto; width: 58px; height: 58px;
        display: grid; place-items: center; border-radius: 16px;
        background: var(--accent-bg); color: var(--accent);
        font-size: 1.5rem;
        border: 1px solid color-mix(in srgb, var(--accent) 25%, transparent);
        transition: transform 0.3s ease;
    }
    .dash-page .stat:hover .stat-tile { transform: translateY(-3px) rotate(-4deg); }
    .stat-meta { min-width: 0; }
    .stat-number {
        font-family: var(--font-display, 'Bricolage Grotesque', sans-serif);
        font-weight: 800; font-size: 2rem; line-height: 1; letter-spacing: -0.02em;
        color: #f8f3ea;
    }
    .stat-label {
        color: var(--text-muted, #9c9389); font-size: 0.8rem; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.06em; margin-top: 0.35rem;
    }

    /* Card header icon tiles */
    .dash-page .card-title { display: inline-flex; align-items: center; gap: 0.65rem; font-weight: 700; }
    .dash-page .card-title .htile {
        width: 30px; height: 30px; border-radius: 9px;
        display: grid; place-items: center; font-size: 0.85rem;
        background: rgba(240, 140, 0, 0.12); color: var(--primary-orange, #f08c00);
        border: 1px solid var(--border-color, rgba(240,140,0,0.16));
    }
    .dash-page .card-title .htile i { margin: 0 !important; }

    /* Ranked lists */
    .dash-page .folder-list, .dash-page .document-list { list-style: none; margin: 0; padding: 0; }
    .dash-page .folder-list li, .dash-page .document-list li {
        display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
        padding: 0.7rem 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.05);
        transition: background 0.2s ease, padding-left 0.2s ease; border-radius: 8px;
    }
    .dash-page .folder-list li:last-child, .dash-page .document-list li:last-child { border-bottom: none; }
    .dash-page .folder-list li:hover, .dash-page .document-list li:hover {
        background: rgba(240, 140, 0, 0.06); padding-left: 0.85rem;
    }
    .rank-item { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
    .rank-no {
        flex: 0 0 auto; width: 26px; height: 26px; border-radius: 8px;
        display: grid; place-items: center; font-size: 0.78rem; font-weight: 700;
        background: rgba(255,255,255,0.05); color: var(--text-muted, #9c9389);
    }
    .dash-page .folder-list li:nth-child(1) .rank-no,
    .dash-page .document-list li:nth-child(1) .rank-no {
        background: linear-gradient(135deg, var(--light-orange, #ffb24d), var(--dark-orange, #d97706));
        color: #241400;
    }
    .rank-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #ece5db; font-weight: 500; }
    .rank-name i { color: var(--primary-orange, #f08c00); margin-right: 0.5rem; }
    .dash-page .badge-custom {
        background: rgba(240, 140, 0, 0.14) !important;
        color: var(--light-orange, #ffb24d) !important;
        border: 1px solid var(--border-color, rgba(240,140,0,0.16));
        border-radius: 999px; padding: 0.3rem 0.7rem; font-weight: 700; font-size: 0.78rem;
        flex: 0 0 auto;
    }

    @media (max-width: 575px) {
        .dash-title { font-size: 1.7rem; }
        .stat-number { font-size: 1.7rem; }
        .stat-tile { width: 50px; height: 50px; font-size: 1.3rem; }
    }
</style>

<div class="container dash-page">

    <div class="dash-header">
        <div>
            <h1 class="dash-title">Dashboard</h1>
            <p class="dash-sub">Overview of your document archive and processing status.</p>
        </div>
        <div class="dash-chips">
            <span class="dash-chip"><i class="fa fa-database"></i> <?= number_format($total_docs) ?> archived</span>
            <span class="dash-chip"><i class="fa fa-folder"></i> <?= number_format(count($folders)) ?> folders</span>
            <span class="dash-chip"><i class="fa fa-hdd"></i> <?= $total_size_h ?> stored</span>
        </div>
    </div>

    <!-- Stats Cards Row -->
    <div class="row g-4 mb-4 stat-grid">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat" style="--accent:#ffb24d; --accent-bg:rgba(240,140,0,0.14);">
                <div class="card-body">
                    <div class="stat-row">
                        <div class="stat-tile"><i class="fa fa-cloud-upload-alt"></i></div>
                        <div class="stat-meta">
                            <div class="stat-number"><?= number_format($uploaded) ?></div>
                            <div class="stat-label">Uploaded Files</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat" style="--accent:#ff7a66; --accent-bg:rgba(255,122,102,0.14);">
                <div class="card-body">
                    <div class="stat-row">
                        <div class="stat-tile"><i class="fa fa-edit"></i></div>
                        <div class="stat-meta">
                            <div class="stat-number"><?= number_format($unedited_files) ?></div>
                            <div class="stat-label">Unedited Files</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat" style="--accent:#34d399; --accent-bg:rgba(52,211,153,0.14);">
                <div class="card-body">
                    <div class="stat-row">
                        <div class="stat-tile"><i class="fa fa-check-circle"></i></div>
                        <div class="stat-meta">
                            <div class="stat-number"><?= number_format($completed_files) ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat" style="--accent:#56b6ff; --accent-bg:rgba(86,182,255,0.14);">
                <div class="card-body">
                    <div class="stat-row">
                        <div class="stat-tile"><i class="fa fa-sync"></i></div>
                        <div class="stat-meta">
                            <div class="stat-number"><?= number_format($syn) ?></div>
                            <div class="stat-label">Wait Synchro</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><span class="htile"><i class="fa fa-chart-pie"></i></span>Document Types Distribution</h5>
                </div>
                <div class="card-body"><canvas id="documentTypesChart" height="300"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><span class="htile"><i class="fa fa-chart-line"></i></span>File Status Overview</h5>
                </div>
                <div class="card-body"><canvas id="fileStatusChart" height="300"></canvas></div>
            </div>
        </div>
    </div>

    <!-- More Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><span class="htile"><i class="fa fa-chart-bar"></i></span>Top Folders by Size</h5>
                </div>
                <div class="card-body"><canvas id="foldersBarChart" height="300"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><span class="htile"><i class="fa fa-chart-area"></i></span>Upload Trends</h5>
                </div>
                <div class="card-body"><canvas id="uploadTrendsChart" height="300"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Lists Row -->
    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><span class="htile"><i class="fa fa-folder"></i></span>Top 10 Document Folders</h5>
                </div>
                <div class="card-body">
                    <ul class="folder-list">
                        <?php
                        $no = 1;
                        foreach ($folders as $index => $folder) {
                            if ($no > 10) break;
                            ?>
                            <li>
                                <span class="rank-item">
                                    <span class="rank-no"><?= $no ?></span>
                                    <span class="rank-name"><i class="fa fa-folder"></i><?= htmlspecialchars($folder['name']) ?></span>
                                </span>
                                <span class="badge badge-custom"><?= $folder['total_folders'] ?></span>
                            </li>
                            <?php
                            $no++;
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><span class="htile"><i class="fa fa-file-alt"></i></span>Top 10 Document Types</h5>
                </div>
                <div class="card-body">
                    <ul class="document-list">
                        <?php
                        $no = 1;
                        foreach ($document_types as $index => $document_type) {
                            if ($no > 10) break;
                            ?>
                            <li>
                                <span class="rank-item">
                                    <span class="rank-no"><?= $no ?></span>
                                    <span class="rank-name"><i class="fa fa-file-alt"></i><?= htmlspecialchars($document_type['name']) ?></span>
                                </span>
                                <span class="badge badge-custom"><?= $document_type['total_document_types'] ?></span>
                            </li>
                            <?php
                            $no++;
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    // Prepare PHP data for JavaScript
    const documentTypesData = <?php echo json_encode(array_map(function($item) {
        return ['name' => $item['name'], 'count' => $item['total_document_types']];
    }, $document_types)); ?>;

    const foldersData = <?php echo json_encode(array_map(function($item) {
        return ['name' => $item['name'], 'count' => $item['total_folders']];
    }, $folders)); ?>;

    const fileStatusData = {
        uploaded: <?php echo $uploaded; ?>,
        unedited: <?php echo $unedited_files; ?>,
        completed: <?php echo $completed_files; ?>,
        syncing: <?php echo $syn; ?>
    };

    document.addEventListener('DOMContentLoaded', function() {
        // Brand palette
        const AMBER = '#f08c00', AMBER_L = '#ffb24d', CORAL = '#ff7a66', EMERALD = '#34d399', SKY = '#56b6ff';
        const TEXT = '#cfc6ba', GRID = 'rgba(240, 140, 0, 0.08)';
        const legendPos = window.innerWidth < 768 ? 'bottom' : 'right'; // avoid clipped legends on mobile
        const AMBER_SCALE = ['#f08c00', '#ffa733', '#ffbe66', '#ffd699', '#ffe6c2', '#d97706', '#b45c00'];

        if (window.Chart) {
            Chart.defaults.font.family = "'Hanken Grotesk', 'Segoe UI', sans-serif";
            Chart.defaults.color = TEXT;
        }

        const baseConfig = {
            plugins: {
                legend: { labels: { color: TEXT, font: { size: 12 }, padding: 16, usePointStyle: true } },
                tooltip: {
                    backgroundColor: 'rgba(11,9,7,0.95)', titleColor: '#fff', bodyColor: TEXT,
                    borderColor: AMBER, borderWidth: 1, padding: 12, cornerRadius: 10, usePointStyle: true
                }
            },
            scales: {
                x: { ticks: { color: TEXT }, grid: { color: GRID, borderColor: 'rgba(240,140,0,0.2)' } },
                y: { ticks: { color: TEXT }, grid: { color: GRID, borderColor: 'rgba(240,140,0,0.2)' } }
            }
        };

        // Document Types Pie
        new Chart(document.getElementById('documentTypesChart'), {
            type: 'doughnut',
            data: {
                labels: documentTypesData.map(i => i.name),
                datasets: [{ data: documentTypesData.map(i => i.count), backgroundColor: AMBER_SCALE, borderColor: 'rgba(11,9,7,0.6)', borderWidth: 2 }]
            },
            options: { ...baseConfig, responsive: true, maintainAspectRatio: false, cutout: '58%',
                plugins: { ...baseConfig.plugins, legend: { position: legendPos, labels: { color: TEXT, padding: 16, usePointStyle: true } } } }
        });

        // File Status Doughnut — semantic colors match the stat cards
        new Chart(document.getElementById('fileStatusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Uploaded', 'Unedited', 'Completed', 'Syncing'],
                datasets: [{
                    data: [fileStatusData.uploaded, fileStatusData.unedited, fileStatusData.completed, fileStatusData.syncing],
                    backgroundColor: [AMBER_L, CORAL, EMERALD, SKY], borderColor: 'rgba(11,9,7,0.6)', borderWidth: 2
                }]
            },
            options: { ...baseConfig, responsive: true, maintainAspectRatio: false, cutout: '62%',
                plugins: { ...baseConfig.plugins, legend: { position: legendPos, labels: { color: TEXT, padding: 16, usePointStyle: true } } } }
        });

        // Folders Bar
        new Chart(document.getElementById('foldersBarChart'), {
            type: 'bar',
            data: {
                labels: foldersData.slice(0, 5).map(i => i.name),
                datasets: [{ label: 'Files', data: foldersData.slice(0, 5).map(i => i.count),
                    backgroundColor: 'rgba(240,140,0,0.75)', hoverBackgroundColor: AMBER_L, borderRadius: 6, borderSkipped: false }]
            },
            options: { ...baseConfig, responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                plugins: { ...baseConfig.plugins, legend: { display: false } } }
        });

        // Upload Trends Area
        const dates = Array.from({length: 7}, (_, i) => { const d = new Date(); d.setDate(d.getDate() - i); return d.toLocaleDateString(); }).reverse();
        const ctx = document.getElementById('uploadTrendsChart').getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 300);
        grad.addColorStop(0, 'rgba(240,140,0,0.35)');
        grad.addColorStop(1, 'rgba(240,140,0,0.01)');
        new Chart(ctx, {
            type: 'line',
            data: { labels: dates, datasets: [{ label: 'Uploads', data: [65, 78, 90, 85, 95, 110, 120],
                fill: true, backgroundColor: grad, borderColor: AMBER, pointBackgroundColor: AMBER_L, pointRadius: 3, tension: 0.4 }] },
            options: { ...baseConfig, responsive: true, maintainAspectRatio: false }
        });
    });

    // Search functionality (used by embedded search widgets)
    function showFile(path) {
        var file_path = "<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>serve_file.php?file=" + encodeURIComponent(path);
        PDFObject.embed(file_path, "#search-result");
    }

    $(document).ready(function() {
        $("#input-description").on('keyup', function() { if ($(this).val().length > 3) search(); });
        $("#input-number").on('keyup', function() { var t = $(this).val(); if (t.length > 2 || t == '') search(); });
    });

    function search() {
        var document_type = $("#input-document_type").val();
        var year = $("#input-year").val();
        var description = $("#input-description").val();
        var number = $("#input-number").val();

        $("#search-result").html("<div class='loader-icon'><i class='fa fa-spinner fa-spin fa-3x'></i></div>");
        if (year == '' || (description == '' && number == '' && document_type == '')) return;

        $.ajax({
            type: "POST", url: "./ajax.php?fx=search_file",
            data: "document_type=" + document_type + "&year=" + year + "&description=" + description + "&number=" + number,
            cache: false,
            success: function(data) {
                if (data && (data != '')) {
                    json_data = JSON.parse(data);
                    if (typeof(json_data) === 'object') {
                        if (json_data.id !== undefined) {
                            showFile(data.path);
                        } else if (json_data.length > 0) {
                            $("#search-result").html('');
                            var html = "<ul class='list-group'>";
                            for (var i = 0; i < json_data.length; i++) {
                                var obj = json_data[i];
                                html += "<li class='list-group-item' onclick='showFile(\"" + obj['path'] + "\")'>PF/ARCH/" + obj['id'] + " [" + obj['name'] + "] " + trimText(obj['description']) + "</li>";
                            }
                            html += "</ul>";
                            $("#search-result").append(html);
                        } else {
                            $("#search-result").html("<div class='loader-icon text-center'>No result!</div>");
                        }
                    } else {
                        $("#search-result").html("Invalid result!");
                    }
                } else {
                    Swal.fire('Search Failed');
                }
            }
        });
    }

    function trimText(text) { return text.substring(0, 60) + "..."; }
</script>
