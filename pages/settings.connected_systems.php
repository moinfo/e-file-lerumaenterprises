<?php
require_once './models/ConnectedSystem.php';

// ── Access control ────────────────────────────────────────────────────────────
// settings.connected_systems is not in Router's public whitelist, so Router
// already ran its DB-backed group check before including this file.
// As an additional safety layer, we re-validate against the 'settings' page
// — the same access level as every other settings sub-page.
if (!Router::validateAccess('settings', $user_id)) {
    Utility::errorPage("Administrator access required to manage connected systems.");
    exit;
}

// ── CSRF token (one per session, regenerated on login) ────────────────────────
if (empty($_SESSION['cs_csrf'])) {
    $_SESSION['cs_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['cs_csrf'];

$csm = new ConnectedSystem();
$db2 = new DB();

// ── Helper: render form fields (used in both modals) ─────────────────────────
function csFormFields(array $subFolders, array $docTypes, array $sys = []): string {
    $sfOpts = '<option value="">— None (API caller must provide) —</option>';
    foreach ($subFolders as $sf) {
        $sel     = isset($sys['default_sub_folder_id']) && (int)$sf['id'] === (int)$sys['default_sub_folder_id'] ? ' selected' : '';
        $sfOpts .= '<option value="' . (int)$sf['id'] . '"' . $sel . '>' . htmlspecialchars($sf['folder_name'] . ' / ' . $sf['name']) . '</option>';
    }
    $dtOpts = '<option value="">— None (API caller must provide) —</option>';
    foreach ($docTypes as $dt) {
        $sel     = isset($sys['default_document_type_id']) && (int)$dt['id'] === (int)$sys['default_document_type_id'] ? ' selected' : '';
        $dtOpts .= '<option value="' . (int)$dt['id'] . '"' . $sel . '>' . htmlspecialchars($dt['name']) . '</option>';
    }
    $name    = htmlspecialchars($sys['name'] ?? '');
    $desc    = htmlspecialchars($sys['description'] ?? '');
    $exts    = htmlspecialchars($sys['allowed_extensions'] ?? 'pdf,jpg,jpeg,png,docx,xlsx');
    $maxMb   = (int)($sys['max_file_size_mb'] ?? 25);
    return '
    <div class="form-group">
        <label>System Name *</label>
        <input type="text" name="name" class="form-control" value="' . $name . '" placeholder="e.g. Mainstore" required>
    </div>
    <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="2" placeholder="What system is this?">' . $desc . '</textarea>
    </div>
    <div class="form-row">
        <div class="form-group col-md-6">
            <label>Default Sub-Folder</label>
            <select name="default_sub_folder_id" class="form-control">' . $sfOpts . '</select>
        </div>
        <div class="form-group col-md-6">
            <label>Default Document Type</label>
            <select name="default_document_type_id" class="form-control">' . $dtOpts . '</select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group col-md-8">
            <label>Allowed Extensions (comma-separated)</label>
            <input type="text" name="allowed_extensions" class="form-control" value="' . $exts . '">
        </div>
        <div class="form-group col-md-4">
            <label>Max File Size (MB)</label>
            <input type="number" name="max_file_size_mb" class="form-control" value="' . $maxMb . '" min="1" max="500">
        </div>
    </div>';
}

// ── POST handler (PRG: process then redirect) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF guard — every state-changing action must include the session token
    if (!hash_equals($csrf, $_POST['cs_csrf'] ?? '')) {
        http_response_code(403);
        Utility::errorPage("Request validation failed. Please reload the page and try again.");
        exit;
    }

    $action = $_POST['action'] ?? '';
    $sysId  = (int)($_POST['id'] ?? 0);

    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                $_SESSION['cs_flash'] = ['type' => 'error', 'message' => 'System name is required.'];
            } else {
                $result = $csm->create(
                    $name,
                    trim($_POST['description'] ?? ''),
                    (int)($_POST['default_sub_folder_id'] ?? 0) ?: null,
                    (int)($_POST['default_document_type_id'] ?? 0) ?: null,
                    preg_replace('/[^a-z0-9,.]/', '', strtolower(trim($_POST['allowed_extensions'] ?? 'pdf'))),
                    max(1, min(500, (int)($_POST['max_file_size_mb'] ?? 25)))
                );
                $_SESSION['cs_flash'] = [
                    'type'    => 'new_key',
                    'message' => 'System <strong>' . htmlspecialchars($name) . '</strong> created. Save this API key — it cannot be recovered later.',
                    'key'     => $result['api_key'],
                ];
            }
            break;

        case 'update':
            if ($sysId) {
                $csm->update(
                    $sysId,
                    trim($_POST['name'] ?? ''),
                    trim($_POST['description'] ?? ''),
                    (int)($_POST['default_sub_folder_id'] ?? 0) ?: null,
                    (int)($_POST['default_document_type_id'] ?? 0) ?: null,
                    preg_replace('/[^a-z0-9,.]/', '', strtolower(trim($_POST['allowed_extensions'] ?? 'pdf'))),
                    max(1, min(500, (int)($_POST['max_file_size_mb'] ?? 25)))
                );
                $_SESSION['cs_flash'] = ['type' => 'success', 'message' => 'System updated.'];
            }
            break;

        case 'rotate_key':
            if ($sysId) {
                $newKey  = $csm->rotateKey($sysId);
                $sysInfo = $csm->find($sysId);
                $_SESSION['cs_flash'] = [
                    'type'    => 'new_key',
                    'message' => 'API key rotated for <strong>' . htmlspecialchars($sysInfo['name'] ?? '') . '</strong>. Update all connected systems immediately — the old key is now invalid.',
                    'key'     => $newKey,
                ];
            }
            break;

        case 'toggle':
            if ($sysId) {
                $active = (bool)(int)($_POST['active'] ?? 0);
                $csm->toggle($sysId, $active);
                $_SESSION['cs_flash'] = ['type' => 'success', 'message' => 'System ' . ($active ? 'enabled' : 'disabled') . '.'];
            }
            break;

        case 'delete':
            if ($sysId) {
                $sysInfo = $csm->find($sysId);
                $csm->delete($sysId);
                $_SESSION['cs_flash'] = ['type' => 'success', 'message' => 'System <strong>' . htmlspecialchars($sysInfo['name'] ?? '') . '</strong> and its file references deleted.'];
            }
            break;
    }

    header('Location: ./?p=settings.connected_systems');
    exit;
}

// ── Load page data ────────────────────────────────────────────────────────────
$flash      = $_SESSION['cs_flash'] ?? null;
unset($_SESSION['cs_flash']);

$systems    = $csm->all();
$recentRefs = $csm->recentRefs(60);
$subFolders = $db2->query(
    "SELECT adsf.id, adsf.name, adf.name AS folder_name
     FROM archive_document_sub_folders adsf
     JOIN archive_document_folders adf ON adf.id = adsf.archive_document_folder_id
     ORDER BY folder_name, adsf.name",
    'SELECT'
) ?: [];
$docTypes = $db2->query("SELECT id, name FROM document_types ORDER BY name", 'SELECT') ?: [];

// Convenience: CSRF hidden field rendered once, reused in every form
$csrfField = '<input type="hidden" name="cs_csrf" value="' . htmlspecialchars($csrf) . '">';
?>
<!-- ======================================================================== -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.1/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
.cs-page { animation:csIn 0.55s cubic-bezier(0.16,1,0.3,1) both; }
@keyframes csIn { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }

.cs-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin:0.5rem 0 1.8rem; }
.cs-title { font-family:var(--font-display,'Bricolage Grotesque',sans-serif); font-weight:800; font-size:2rem; line-height:1; letter-spacing:-0.02em; margin:0;
    background:linear-gradient(100deg,#fff 35%,var(--light-orange,#ffb24d)); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; }
.cs-sub { color:var(--text-muted,#9c9389); font-size:0.92rem; margin:0.35rem 0 0; }

.cs-flash { border-radius:14px; padding:1rem 1.2rem; margin-bottom:1.5rem; display:flex; align-items:flex-start; gap:0.75rem; }
.cs-flash.success { background:rgba(52,211,153,0.1);  border:1px solid rgba(52,211,153,0.28); color:#6ee7b7; }
.cs-flash.error   { background:rgba(255,107,93,0.1);  border:1px solid rgba(255,107,93,0.28);  color:#fca5a5; }
.cs-flash.new_key { background:rgba(240,140,0,0.08);  border:1px solid rgba(240,140,0,0.28);   color:#f6efe5; }
.cs-flash .fi     { font-size:1.1rem; flex:0 0 auto; margin-top:0.1rem; }
.cs-flash.success .fi { color:#34d399; } .cs-flash.error .fi { color:#ff7a66; } .cs-flash.new_key .fi { color:#ffb24d; }
.key-reveal-box { display:flex; align-items:center; gap:0.5rem; background:rgba(0,0,0,0.3); border:1px solid rgba(240,140,0,0.25);
    border-radius:10px; padding:0.55rem 0.8rem; margin-top:0.7rem; font-family:monospace; font-size:0.8rem; color:#ffe0a0; flex-wrap:wrap; }
.key-reveal-box .kv { flex:1; word-break:break-all; }
.btn-copy-key { background:rgba(240,140,0,0.15); border:1px solid rgba(240,140,0,0.25); color:#ffb24d; border-radius:7px; padding:0.2rem 0.55rem; font-size:0.74rem; cursor:pointer; flex:0 0 auto; }
.btn-copy-key:hover { background:rgba(240,140,0,0.25); }
.key-warning { font-size:0.76rem; color:#9c9389; margin-top:0.45rem; }
.key-warning i { color:#ffb24d; }

.sys-card { background:linear-gradient(180deg,rgba(31,26,21,0.95),rgba(20,17,13,0.97));
    border:1px solid rgba(240,140,0,0.14); border-radius:18px; padding:1.4rem; height:100%;
    transition:border-color 0.2s,box-shadow 0.2s; }
.sys-card:hover { border-color:rgba(240,140,0,0.32); box-shadow:0 12px 30px -14px rgba(0,0,0,0.6); }
.sys-card-top { display:flex; align-items:center; justify-content:space-between; gap:0.5rem; margin-bottom:0.6rem; }
.sys-name { font-family:var(--font-display,'Bricolage Grotesque',sans-serif); font-weight:700; font-size:1.1rem; color:#f4eee4; }
.sys-badge { display:inline-flex; align-items:center; gap:0.3rem; font-size:0.68rem; font-weight:700; padding:0.18rem 0.55rem; border-radius:999px; letter-spacing:0.04em; text-transform:uppercase; }
.sys-badge.on  { background:rgba(52,211,153,0.14); color:#34d399; border:1px solid rgba(52,211,153,0.28); }
.sys-badge.off { background:rgba(150,140,130,0.1); color:#9c9389; border:1px solid rgba(150,140,130,0.22); }
.sys-desc { color:#7d756b; font-size:0.83rem; line-height:1.4; margin-bottom:0.9rem; min-height:2.4rem; }
.sys-meta { display:flex; gap:0.9rem; flex-wrap:wrap; margin-bottom:1rem; }
.sys-meta span { font-size:0.76rem; color:#6f675e; display:flex; align-items:center; gap:0.28rem; }
.sys-meta i { color:#f08c00; font-size:0.72rem; }

.api-key-row { display:flex; align-items:center; gap:0.45rem; background:rgba(0,0,0,0.22); border:1px solid rgba(255,255,255,0.06); border-radius:9px; padding:0.5rem 0.7rem; margin-bottom:1rem; }
.api-key-label { font-size:0.66rem; font-weight:700; color:#6f675e; text-transform:uppercase; letter-spacing:0.05em; flex:0 0 auto; }
.api-key-text { flex:1; font-family:monospace; font-size:0.76rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:pointer; }
.api-key-text.masked { color:#5a524a; letter-spacing:0.1em; }
.api-key-text.revealed { color:#ffe0a0; }
.btn-xs { background:transparent; border:1px solid rgba(255,255,255,0.07); border-radius:6px; color:#9c9389; width:24px; height:24px; display:grid; place-items:center; font-size:0.7rem; cursor:pointer; transition:all 0.15s; flex:0 0 auto; }
.btn-xs:hover { background:rgba(240,140,0,0.1); border-color:rgba(240,140,0,0.28); color:#ffb24d; }

.sys-actions { display:flex; flex-wrap:wrap; gap:0.35rem; }
.btn-act { display:inline-flex; align-items:center; gap:0.3rem; font-size:0.76rem; padding:0.32rem 0.7rem; border-radius:8px; border:1px solid rgba(255,255,255,0.08); background:transparent; color:#9c9389; cursor:pointer; transition:all 0.15s; }
.btn-act:hover { background:rgba(240,140,0,0.09); border-color:rgba(240,140,0,0.28); color:#ffb24d; }
.btn-act.red:hover { background:rgba(255,107,93,0.09); border-color:rgba(255,107,93,0.28); color:#ff7a66; }
.btn-act.add { background:linear-gradient(135deg,#ffb24d,#d97706); border:none; color:#1a0e00; font-weight:700; font-size:0.88rem; padding:0.52rem 1.2rem; border-radius:10px; }
.btn-act.add:hover { filter:brightness(1.08); color:#1a0e00; }

.cs-section { margin:2.5rem 0 1rem; }
.cs-section h2 { font-family:var(--font-display,'Bricolage Grotesque',sans-serif); font-weight:700; font-size:1.3rem; color:#f4eee4; margin:0 0 0.25rem; }
.cs-section p { color:#7d756b; font-size:0.85rem; margin:0; }

.act-table { width:100%; border-collapse:collapse; font-size:0.83rem; }
.act-table th { color:#6f675e; font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; padding:0.5rem 0.7rem; border-bottom:1px solid rgba(255,255,255,0.05); }
.act-table td { padding:0.6rem 0.7rem; border-bottom:1px solid rgba(255,255,255,0.04); color:#c9b8a0; }
.act-table tr:last-child td { border-bottom:none; }
.sys-tag { display:inline-flex; align-items:center; gap:0.25rem; background:rgba(240,140,0,0.09); border:1px solid rgba(240,140,0,0.18); color:#ffb24d; border-radius:6px; font-size:0.7rem; font-weight:600; padding:0.12rem 0.45rem; }
.pill { display:inline-block; padding:0.12rem 0.5rem; border-radius:999px; font-size:0.68rem; font-weight:700; text-transform:uppercase; }
.pill.active { background:rgba(52,211,153,0.13); color:#34d399; }
.pill.completed { background:rgba(86,182,255,0.13); color:#56b6ff; }
.pill.deleted_by_source { background:rgba(255,107,93,0.11); color:#ff7a66; }

.guide-block { background:rgba(0,0,0,0.18); border:1px solid rgba(255,255,255,0.05); border-radius:13px; padding:1.1rem 1.3rem; margin-bottom:0.9rem; }
.guide-block h4 { font-size:0.92rem; font-weight:700; color:#f4eee4; margin:0 0 0.55rem; }
.guide-block pre { background:rgba(0,0,0,0.32); border:1px solid rgba(240,140,0,0.13); border-radius:9px; padding:0.8rem 0.9rem; font-size:0.75rem; color:#ffe0a0; white-space:pre-wrap; word-break:break-all; margin:0; }
.m-tag { display:inline-block; padding:0.08rem 0.45rem; border-radius:5px; font-size:0.68rem; font-weight:700; margin-right:0.3rem; }
.m-post   { background:rgba(52,211,153,0.14); color:#34d399; }
.m-get    { background:rgba(86,182,255,0.14); color:#56b6ff; }
.m-delete { background:rgba(255,107,93,0.14); color:#ff7a66; }

.empty-cs { text-align:center; padding:3rem 1rem; }
.empty-cs .ei { width:62px; height:62px; background:rgba(240,140,0,0.09); border:1px solid rgba(240,140,0,0.18); border-radius:18px; display:grid; place-items:center; margin:0 auto 1.1rem; font-size:1.5rem; color:#f08c00; }

.modal-content { background:#1a150f !important; border:1px solid rgba(240,140,0,0.18) !important; border-radius:16px !important; color:#f6efe5; }
.modal-header, .modal-footer { border-color:rgba(240,140,0,0.1) !important; }
.modal-title { font-family:var(--font-display,'Bricolage Grotesque',sans-serif); color:#fff !important; font-size:1.05rem !important; }
.modal .close { color:#9c9389 !important; opacity:1; text-shadow:none; }
.modal .close:hover { color:#ffb24d !important; }
.modal label { color:#7d756b; font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:0.28rem; display:block; }
.modal .form-control { background:#120f0b; border:1px solid rgba(255,255,255,0.09); color:#f6efe5; border-radius:8px; }
.modal .form-control:focus { border-color:rgba(240,140,0,0.45); box-shadow:0 0 0 3px rgba(240,140,0,0.09); outline:none; }
.modal select.form-control option { background:#1a150f; }
.modal .btn-primary { background:linear-gradient(135deg,#ffb24d,#d97706); border:none; color:#1a0e00; font-weight:700; }
.modal .btn-secondary { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.09); color:#9c9389; }

@media(max-width:576px) { .cs-head{flex-direction:column} .sys-actions{flex-direction:column} }
</style>

<div class="container cs-page">

    <!-- Header -->
    <div class="cs-head">
        <div>
            <h1 class="cs-title">Connected Systems</h1>
            <p class="cs-sub">Register external systems that push documents into e-file via the ingest API.</p>
        </div>
        <button class="btn-act add" data-toggle="modal" data-target="#addSysModal">
            <i class="bi bi-plus-circle"></i> Add System
        </button>
    </div>

    <!-- Flash -->
    <?php if ($flash): ?>
    <div class="cs-flash <?= htmlspecialchars($flash['type']) ?>">
        <span class="fi">
            <?php if ($flash['type'] === 'error') echo '<i class="fa fa-exclamation-circle"></i>';
            elseif ($flash['type'] === 'new_key') echo '<i class="fa fa-key"></i>';
            else echo '<i class="fa fa-check-circle"></i>'; ?>
        </span>
        <div style="flex:1">
            <div><?= $flash['message'] ?></div>
            <?php if (!empty($flash['key'])): ?>
            <div class="key-reveal-box">
                <span class="kv" id="flash-key"><?= htmlspecialchars($flash['key']) ?></span>
                <button class="btn-copy-key" onclick="copyEl('flash-key', this)"><i class="fa fa-copy"></i> Copy</button>
            </div>
            <div class="key-warning"><i class="fa fa-exclamation-triangle"></i> Update your external system with this key now. It cannot be retrieved again.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Systems grid -->
    <?php if (empty($systems)): ?>
    <div class="empty-cs">
        <div class="ei"><i class="bi bi-plug"></i></div>
        <h3 style="font-family:var(--font-display,'Bricolage Grotesque',sans-serif);font-weight:700;color:#f4eee4">No Connected Systems Yet</h3>
        <p style="color:#7d756b">Click <strong style="color:#f4eee4">Add System</strong> to register an external system and generate an API key.</p>
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($systems as $i => $sys):
            $masked   = str_repeat('●', 20) . substr($sys['api_key'], -8);
            $isActive = (bool)(int)$sys['is_active'];
            $keyId    = 'k' . $sys['id'];
        ?>
        <div class="col-12 col-md-6 col-lg-4 mb-4">
            <div class="sys-card" style="animation:csIn .5s ease-out <?= $i * 0.06 ?>s both">
                <div class="sys-card-top">
                    <div class="sys-name"><?= htmlspecialchars($sys['name']) ?></div>
                    <span class="sys-badge <?= $isActive ? 'on' : 'off' ?>">
                        <i class="bi bi-<?= $isActive ? 'wifi' : 'wifi-off' ?>"></i>
                        <?= $isActive ? 'Active' : 'Inactive' ?>
                    </span>
                </div>

                <div class="sys-desc"><?= htmlspecialchars($sys['description'] ?: 'No description set.') ?></div>

                <div class="sys-meta">
                    <span><i class="fa fa-file-alt"></i> <?= (int)$sys['file_count'] ?> files</span>
                    <span><i class="fa fa-weight"></i> Max <?= (int)$sys['max_file_size_mb'] ?>MB</span>
                    <span><i class="fa fa-tag"></i> <?= htmlspecialchars($sys['allowed_extensions']) ?></span>
                </div>

                <div class="api-key-row">
                    <span class="api-key-label">API Key</span>
                    <span class="api-key-text masked" id="<?= $keyId ?>"
                          data-masked="<?= htmlspecialchars($masked) ?>"
                          data-real="<?= htmlspecialchars($sys['api_key']) ?>"
                          onclick="toggleKey('<?= $keyId ?>')"
                          title="Click to reveal"><?= htmlspecialchars($masked) ?></span>
                    <button class="btn-xs" onclick="toggleKey('<?= $keyId ?>')" title="Reveal key"><i class="fa fa-eye" id="eye-<?= $sys['id'] ?>"></i></button>
                    <button class="btn-xs" onclick="copyText('<?= htmlspecialchars($sys['api_key']) ?>', this)" title="Copy key"><i class="fa fa-copy"></i></button>
                </div>

                <div class="sys-actions">
                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('Rotate API key for <?= htmlspecialchars(addslashes($sys['name'])) ?>?\n\nThe old key will stop working immediately. Update your external system with the new key.')">
                        <?= $csrfField ?>
                        <input type="hidden" name="action" value="rotate_key">
                        <input type="hidden" name="id" value="<?= $sys['id'] ?>">
                        <button type="submit" class="btn-act"><i class="fa fa-sync"></i> Rotate Key</button>
                    </form>

                    <button class="btn-act" onclick="openEdit(<?= htmlspecialchars(json_encode($sys), ENT_QUOTES) ?>)">
                        <i class="fa fa-edit"></i> Edit
                    </button>

                    <form method="POST" style="display:inline">
                        <?= $csrfField ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $sys['id'] ?>">
                        <input type="hidden" name="active" value="<?= $isActive ? 0 : 1 ?>">
                        <button type="submit" class="btn-act">
                            <?= $isActive ? '<i class="fa fa-ban"></i> Disable' : '<i class="fa fa-play-circle"></i> Enable' ?>
                        </button>
                    </form>

                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($sys['name'])) ?> and all its file references?\n\nThis cannot be undone.')">
                        <?= $csrfField ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $sys['id'] ?>">
                        <button type="submit" class="btn-act red"><i class="fa fa-trash"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Recent activity -->
    <div class="cs-section">
        <h2>Recent Activity</h2>
        <p>Latest 60 files received across all connected systems</p>
    </div>

    <?php if (!empty($recentRefs)): ?>
    <div style="margin-bottom:0.8rem;display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap">
        <span style="font-size:0.8rem;color:#7d756b">Filter:</span>
        <select onchange="filterRows(this.value)" style="background:#120f0b;border:1px solid rgba(255,255,255,0.09);color:#f6efe5;border-radius:8px;padding:0.35rem 0.6rem;font-size:0.82rem">
            <option value="">All systems</option>
            <?php foreach ($systems as $s): ?>
            <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="background:rgba(0,0,0,0.18);border:1px solid rgba(255,255,255,0.05);border-radius:14px;overflow:hidden;">
        <table class="act-table">
            <thead><tr>
                <th>System</th><th>External Ref ID</th><th>File Name</th><th>Status</th><th>Completed</th><th>Received</th>
            </tr></thead>
            <tbody id="act-body">
            <?php foreach ($recentRefs as $ref): ?>
            <tr data-sys="<?= htmlspecialchars($ref['system_name']) ?>">
                <td><span class="sys-tag"><i class="bi bi-plug"></i> <?= htmlspecialchars($ref['system_name']) ?></span></td>
                <td><code style="font-size:0.72rem;color:#c9b8a0;background:rgba(0,0,0,0.18);padding:0.1rem 0.35rem;border-radius:4px"><?= htmlspecialchars($ref['external_ref_id']) ?></code></td>
                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($ref['archive_name']) ?>"><?= htmlspecialchars($ref['archive_name']) ?></td>
                <td><span class="pill <?= htmlspecialchars($ref['status']) ?>"><?= str_replace('_', ' ', $ref['status']) ?></span></td>
                <td><?= (int)$ref['completed'] ? '<i class="fa fa-check-circle" style="color:#34d399"></i> Yes' : '<span style="color:#4a4540">No</span>' ?></td>
                <td style="color:#6f675e;font-size:0.76rem"><?= htmlspecialchars(date('d M Y H:i', strtotime($ref['created_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="background:rgba(0,0,0,0.14);border:1px solid rgba(255,255,255,0.05);border-radius:14px;padding:2rem;text-align:center;color:#6f675e;font-size:0.88rem">
        <i class="bi bi-inbox" style="font-size:1.4rem;display:block;margin-bottom:0.5rem"></i>
        No files received yet. Once external systems push files they will appear here.
    </div>
    <?php endif; ?>

    <!-- API Guide -->
    <div class="cs-section">
        <h2>API Integration Guide</h2>
        <p>How to call the ingest API from external systems</p>
    </div>

    <div class="guide-block">
        <h4><span class="m-tag m-post">POST</span> Upload a file &mdash; <code style="font-size:0.8rem;color:#9c9389">/api/v1/ingest/upload</code></h4>
        <pre>curl -X POST <?= BASE_URL ?>api/v1/ingest/upload \
  -H "X-API-Key: YOUR_API_KEY" \
  -F "file=@document.pdf" \
  -F "external_ref_id=mainstore-doc-123" \
  -F "description=Invoice Q4 2026" \
  -F "document_date=2026-01-15"
  # Optional: -F "sub_folder_id=5" -F "document_type_id=3"

# Response → { "efile_id": 42, "external_ref_id": "mainstore-doc-123", "status": "active" }</pre>
    </div>

    <div class="guide-block">
        <h4><span class="m-tag m-get">GET</span> Check file status &mdash; <code style="font-size:0.8rem;color:#9c9389">/api/v1/ingest/file/{ref_id}</code></h4>
        <pre>curl -H "X-API-Key: YOUR_API_KEY" \
  <?= BASE_URL ?>api/v1/ingest/file/mainstore-doc-123

# Response → { "efile_id": 42, "completed": false, "editable": true, "status": "active" }</pre>
    </div>

    <div class="guide-block">
        <h4><span class="m-tag m-delete">DELETE</span> Delete file (only if not completed) &mdash; <code style="font-size:0.8rem;color:#9c9389">/api/v1/ingest/file/{ref_id}</code></h4>
        <pre>curl -X DELETE -H "X-API-Key: YOUR_API_KEY" \
  <?= BASE_URL ?>api/v1/ingest/file/mainstore-doc-123</pre>
    </div>

    <div class="guide-block">
        <h4><span class="m-tag m-get">GET</span> Stats for this system &mdash; <code style="font-size:0.8rem;color:#9c9389">/api/v1/ingest/stats</code></h4>
        <pre>curl -H "X-API-Key: YOUR_API_KEY" \
  <?= BASE_URL ?>api/v1/ingest/stats
# Response → { "total": 120, "files": { "active": 80, "completed": 35, "deleted_by_source": 5 } }</pre>
    </div>

</div><!-- .cs-page -->


<!-- Add System Modal -->
<div class="modal fade" id="addSysModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle" style="color:#ffb24d;margin-right:.35rem"></i> Add Connected System</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <?= csFormFields($subFolders, $docTypes) ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Create &amp; Generate Key</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit System Modal -->
<div class="modal fade" id="editSysModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square" style="color:#ffb24d;margin-right:.35rem"></i> Edit System</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body" id="edit-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// API key reveal toggle
function toggleKey(id) {
    const el  = document.getElementById(id);
    const eye = document.getElementById('eye-' + id.slice(1));
    if (el.classList.contains('masked')) {
        el.textContent = el.dataset.real;
        el.classList.replace('masked', 'revealed');
        if (eye) eye.className = 'fa fa-eye-slash';
    } else {
        el.textContent = el.dataset.masked;
        el.classList.replace('revealed', 'masked');
        if (eye) eye.className = 'fa fa-eye';
    }
}

// Copy text to clipboard
function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-check"></i>';
        btn.style.color = '#34d399';
        setTimeout(() => { btn.innerHTML = orig; btn.style.color = ''; }, 1600);
    });
}

// Copy text content of an element by ID
function copyEl(elId, btn) {
    copyText(document.getElementById(elId).textContent, btn);
}

// Filter activity table
function filterRows(sys) {
    document.querySelectorAll('#act-body tr').forEach(r => {
        r.style.display = (!sys || r.dataset.sys === sys) ? '' : 'none';
    });
}

// Edit modal — inject server data as JSON then build fields client-side
const _sf = <?= json_encode(array_map(fn($s) => ['id' => $s['id'], 'label' => $s['folder_name'] . ' / ' . $s['name']], $subFolders)) ?>;
const _dt = <?= json_encode(array_map(fn($d) => ['id' => $d['id'], 'label' => $d['name']], $docTypes)) ?>;

function openEdit(sys) {
    document.getElementById('edit-id').value = sys.id;
    document.getElementById('edit-body').innerHTML = buildEditFields(sys);
    $('#editSysModal').modal('show');
}

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function sel(arr, val) {
    return arr.map(o => `<option value="${o.id}"${o.id == val?' selected':''}>${esc(o.label)}</option>`).join('');
}

function buildEditFields(sys) {
    return `
    <div class="form-group">
        <label>System Name *</label>
        <input type="text" name="name" class="form-control" value="${esc(sys.name)}" required>
    </div>
    <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="2">${esc(sys.description||'')}</textarea>
    </div>
    <div class="form-row">
        <div class="form-group col-md-6">
            <label>Default Sub-Folder</label>
            <select name="default_sub_folder_id" class="form-control">
                <option value="">— None —</option>${sel(_sf, sys.default_sub_folder_id)}
            </select>
        </div>
        <div class="form-group col-md-6">
            <label>Default Document Type</label>
            <select name="default_document_type_id" class="form-control">
                <option value="">— None —</option>${sel(_dt, sys.default_document_type_id)}
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group col-md-8">
            <label>Allowed Extensions</label>
            <input type="text" name="allowed_extensions" class="form-control" value="${esc(sys.allowed_extensions)}">
        </div>
        <div class="form-group col-md-4">
            <label>Max File Size (MB)</label>
            <input type="number" name="max_file_size_mb" class="form-control" value="${parseInt(sys.max_file_size_mb)||25}" min="1" max="500">
        </div>
    </div>`;
}
</script>
