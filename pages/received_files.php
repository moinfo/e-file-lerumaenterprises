<?php
require_once './models/ConnectedSystem.php';

$db  = new DB();
$csm = new ConnectedSystem();

// ── Data ─────────────────────────────────────────────────────────────────────
$systems       = $csm->all();
$filterSystem  = isset($_GET['system_id']) ? (int) $_GET['system_id'] : null;
// Whitelist the status so it's safe to interpolate into hrefs/queries everywhere.
$filterStatus  = $_GET['status'] ?? 'pending';
if (!in_array($filterStatus, ['all', 'pending', 'completed', 'deleted'], true)) {
    $filterStatus = 'pending';
}
// Date range (on the Received date, efr.created_at). Validate YYYY-MM-DD.
$validDate     = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? (string) $d : '';
$filterFrom    = $validDate($_GET['from'] ?? '');
$filterTo      = $validDate($_GET['to'] ?? '');
// Reusable query-string fragment so cards/tabs preserve the date range.
$datePart      = ($filterFrom ? '&from=' . urlencode($filterFrom) : '')
               . ($filterTo   ? '&to='   . urlencode($filterTo)   : '');

// Build file list query
$whereParts = [];
$params     = [];

if ($filterSystem) {
    $whereParts[] = 'efr.connected_system_id = ?';
    $params[]     = $filterSystem;
}
if ($filterStatus !== 'all') {
    if ($filterStatus === 'pending') {
        $whereParts[] = 'efr.status = "active" AND a.completed = 0';
    } elseif ($filterStatus === 'completed') {
        $whereParts[] = 'a.completed = 1';
    } elseif ($filterStatus === 'deleted') {
        $whereParts[] = 'efr.status = "deleted_by_source"';
    }
}
if ($filterFrom) { $whereParts[] = 'DATE(efr.created_at) >= ?'; $params[] = $filterFrom; }
if ($filterTo)   { $whereParts[] = 'DATE(efr.created_at) <= ?'; $params[] = $filterTo; }

$where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

if ($params) {
    $files = $db->query(
        "SELECT efr.id, efr.external_ref_id, efr.local_id, efr.archive_id, efr.status, efr.created_at,
                a.name AS archive_name, a.path, a.completed, a.description, a.document_date,
                a.sub_folder_id, a.document_type,
                cs.name AS system_name, cs.id AS system_id, cs.slug AS system_slug
         FROM external_file_refs efr
         JOIN archives a ON a.id = efr.archive_id
         JOIN connected_systems cs ON cs.id = efr.connected_system_id
         {$where}
         ORDER BY efr.created_at DESC
         LIMIT 200",
        'SELECT', false, $params
    );
} else {
    $files = $db->query(
        "SELECT efr.id, efr.external_ref_id, efr.local_id, efr.archive_id, efr.status, efr.created_at,
                a.name AS archive_name, a.path, a.completed, a.description, a.document_date,
                a.sub_folder_id, a.document_type,
                cs.name AS system_name, cs.id AS system_id, cs.slug AS system_slug
         FROM external_file_refs efr
         JOIN archives a ON a.id = efr.archive_id
         JOIN connected_systems cs ON cs.id = efr.connected_system_id
         {$where}
         ORDER BY efr.created_at DESC
         LIMIT 200",
        'SELECT'
    );
}
$files = is_array($files) ? $files : [];
?>

<style>
.rf-system-card {
    background: var(--card-bg, #1a1a1a);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    padding: 18px 22px;
    cursor: pointer;
    transition: border-color .18s, box-shadow .18s;
    text-decoration: none;
    display: block;
    color: inherit;
}
.rf-system-card:hover, .rf-system-card.active {
    border-color: #f08c00;
    box-shadow: 0 0 0 2px rgba(240,140,0,.15);
    text-decoration: none;
    color: inherit;
}
.rf-system-card .sys-count {
    font-size: 2rem;
    font-weight: 700;
    color: #f08c00;
    line-height: 1;
}
.rf-system-card .sys-name { font-weight: 600; font-size: 1rem; margin-top: 4px; }
.rf-system-card .sys-sub  { font-size: .78rem; color: #aaa; margin-top: 2px; }
.status-pill {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.pill-active    { background: rgba(34,197,94,.15);  color: #22c55e; }
.pill-completed { background: rgba(99,102,241,.15); color: #818cf8; }
.pill-deleted   { background: rgba(239,68,68,.12);  color: #f87171; }
.rf-filters a { margin-right: 8px; }
</style>

<div class="container-fluid py-4">

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0 fw-bold">Received Files</h4>
            <small class="text-muted">Documents pushed into e-file from connected external systems</small>
        </div>
        <a href="?p=settings.connected_systems" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-plug me-1"></i>Manage Systems
        </a>
    </div>

    <!-- System summary cards -->
    <?php if (!empty($systems)): ?>
    <div class="row g-3 mb-4">
        <div class="col-auto">
            <a href="?p=received_files<?= $filterStatus !== 'all' ? '&status='.$filterStatus : '' ?><?= $datePart ?>"
               class="rf-system-card <?= !$filterSystem ? 'active' : '' ?>" style="min-width:130px">
                <div class="sys-count"><?= array_sum(array_column($systems, 'file_count')) ?></div>
                <div class="sys-name">All Systems</div>
                <div class="sys-sub"><?= count($systems) ?> system<?= count($systems) !== 1 ? 's' : '' ?></div>
            </a>
        </div>
        <?php foreach ($systems as $sys): ?>
        <div class="col-auto">
            <a href="?p=received_files&system_id=<?= $sys['id'] ?><?= $filterStatus !== 'all' ? '&status='.$filterStatus : '' ?><?= $datePart ?>"
               class="rf-system-card <?= $filterSystem === (int)$sys['id'] ? 'active' : '' ?>" style="min-width:130px">
                <div class="sys-count"><?= (int)$sys['file_count'] ?></div>
                <div class="sys-name"><?= htmlspecialchars($sys['name']) ?></div>
                <div class="sys-sub <?= $sys['is_active'] ? 'text-success' : 'text-danger' ?>">
                    <?= $sys['is_active'] ? 'Active' : 'Disabled' ?>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="alert alert-secondary mb-4">No connected systems yet. <a href="?p=settings.connected_systems">Register one</a>.</div>
    <?php endif; ?>

    <!-- Status filter tabs -->
    <div class="rf-filters mb-3">
        <?php
        $base = '?p=received_files' . ($filterSystem ? '&system_id='.$filterSystem : '');
        $tabs = ['all' => 'All', 'pending' => 'Pending / Editable', 'completed' => 'Completed', 'deleted' => 'Deleted'];
        foreach ($tabs as $val => $label):
            $active = $filterStatus === $val ? 'btn-warning' : 'btn-outline-secondary';
        ?>
        <a href="<?= $base ?>&status=<?= $val ?><?= $datePart ?>" class="btn btn-sm <?= $active ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <!-- System + date-range filter bar -->
    <form method="get" action="" class="d-flex flex-wrap align-items-end gap-2 mb-3">
        <input type="hidden" name="p" value="received_files">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus, ENT_QUOTES) ?>">
        <div>
            <label class="d-block small text-muted mb-1">System</label>
            <select name="system_id" class="form-select form-select-sm" style="min-width:170px;background:#1a1a1a;color:inherit;border-color:rgba(255,255,255,.15)">
                <option value="">All systems</option>
                <?php foreach ($systems as $sys): ?>
                <option value="<?= (int)$sys['id'] ?>" <?= $filterSystem === (int)$sys['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sys['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="d-block small text-muted mb-1">From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($filterFrom, ENT_QUOTES) ?>"
                   class="form-control form-control-sm" style="background:#1a1a1a;color:inherit;border-color:rgba(255,255,255,.15)">
        </div>
        <div>
            <label class="d-block small text-muted mb-1">To</label>
            <input type="date" name="to" value="<?= htmlspecialchars($filterTo, ENT_QUOTES) ?>"
                   class="form-control form-control-sm" style="background:#1a1a1a;color:inherit;border-color:rgba(255,255,255,.15)">
        </div>
        <div>
            <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-filter me-1"></i>Apply</button>
            <?php if ($filterSystem || $filterFrom || $filterTo): ?>
            <a href="?p=received_files<?= $filterStatus !== 'all' ? '&status='.$filterStatus : '' ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Files table -->
    <div class="card" style="background:var(--card-bg,#1a1a1a);border:1px solid rgba(255,255,255,0.08);">
        <div class="card-body p-0">
            <?php if (empty($files)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-inbox fa-3x d-block mb-2"></i>
                No files found<?= $filterSystem ? ' for this system' : '' ?>.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="color:inherit">
                    <thead style="border-bottom:1px solid rgba(255,255,255,0.1)">
                        <tr>
                            <th class="ps-4">Document</th>
                            <th>System</th>
                            <th>Ref ID</th>
                            <th>Received</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($files as $f):
                        $isPending   = $f['status'] === 'active' && !(int)$f['completed'];
                        $isCompleted = (int)$f['completed'] === 1;
                        $isDeleted   = $f['status'] === 'deleted_by_source';
                        // View via view_by_ref.php: login-required and folder-scoped
                        // (looks up by external_ref_id, applies group access, serves
                        // through serve_file.php). Avoids trusting a client-supplied path.
                        $fileUrl     = 'view_by_ref.php?ref=' . urlencode($f['external_ref_id']);
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-semibold"><?= htmlspecialchars($f['archive_name']) ?></div>
                            <?php if ($f['description']): ?>
                            <small class="text-muted"><?= htmlspecialchars(substr($f['description'], 0, 60)) ?></small>
                            <?php endif; ?>
                            <?php if ($f['document_date']): ?>
                            <br><small class="text-muted"><i class="fas fa-calendar-alt me-1"></i><?= htmlspecialchars($f['document_date']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($f['system_name']) ?></span></td>
                        <td><code class="small"><?= htmlspecialchars($f['external_ref_id']) ?></code></td>
                        <td><small><?= date('d M Y H:i', strtotime($f['created_at'])) ?></small></td>
                        <td>
                            <?php if ($isDeleted): ?>
                                <span class="status-pill pill-deleted">Deleted</span>
                            <?php elseif ($isCompleted): ?>
                                <span class="status-pill pill-completed">Completed</span>
                            <?php else: ?>
                                <span class="status-pill pill-active">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4" style="white-space:nowrap">
                            <a href="<?= htmlspecialchars($fileUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-info me-1">
                                <i class="fas fa-eye me-1"></i>View
                            </a>
                            <?php if ($isPending): ?>
                            <a href="?p=editor&archive_id=<?= (int)$f['archive_id'] ?>"
                               class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-pencil-alt mr-1"></i>Edit
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-2 text-muted small border-top" style="border-color:rgba(255,255,255,.08)!important">
                Showing <?= count($files) ?> file<?= count($files) !== 1 ? 's' : '' ?>
                <?= count($files) === 200 ? ' (limited to 200 — use filters to narrow results)' : '' ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

