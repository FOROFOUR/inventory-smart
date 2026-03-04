<?php
ob_start();

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$conn      = getDBConnection();
$user_name = $_SESSION['name'] ?? 'Admin';

// ── AJAX handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'];

    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID.']); exit(); }

    if ($action === 'confirm') {
        $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = 'CONFIRMED' WHERE id = ? AND status = 'PENDING'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $desc = "Pull-out #$id CONFIRMED by $user_name";
            $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'CONFIRM_PULLOUT', ?)")->bind_param("ss", $user_name, $desc);
            $conn->query("INSERT INTO activity_logs (user_name, action, description) VALUES ('$user_name','CONFIRM_PULLOUT','$desc')");
            echo json_encode(['success' => true, 'message' => "Request #$id confirmed."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Already processed.']);
        }

    } elseif ($action === 'reject') {
        $reason = htmlspecialchars(trim($_POST['reason'] ?? ''));
        $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = 'CANCELLED' WHERE id = ? AND status = 'PENDING'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $desc = "Pull-out #$id CANCELLED by $user_name" . ($reason ? ". Reason: $reason" : "");
            $conn->query("INSERT INTO activity_logs (user_name, action, description) VALUES ('$user_name','CANCEL_PULLOUT','$desc')");
            echo json_encode(['success' => true, 'message' => "Request #$id cancelled."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Already processed.']);
        }

    } elseif ($action === 'edit') {
        $purpose      = htmlspecialchars(trim($_POST['purpose']      ?? ''));
        $date_needed  = trim($_POST['date_needed']  ?? '');
        $from_location = htmlspecialchars(trim($_POST['from_location'] ?? ''));
        $to_location   = htmlspecialchars(trim($_POST['to_location']   ?? ''));
        $quantity      = (int)($_POST['quantity'] ?? 1);

        $stmt = $conn->prepare("UPDATE pull_out_transactions SET purpose=?, date_needed=?, from_location=?, to_location=?, quantity=?, location_received=? WHERE id = ? AND status = 'PENDING'");
        $stmt->bind_param("sssssii", $purpose, $date_needed, $from_location, $to_location, $to_location, $quantity, $id);
        $stmt->bind_param("sssssii", $purpose, $date_needed, $from_location, $to_location, $quantity, $to_location, $id);

        // Clean bind
        $stmt2 = $conn->prepare("UPDATE pull_out_transactions SET purpose=?, date_needed=?, from_location=?, to_location=?, location_received=?, quantity=? WHERE id = ? AND status='PENDING'");
        $stmt2->bind_param("sssssii", $purpose, $date_needed, $from_location, $to_location, $to_location, $quantity, $id);
        $stmt2->execute();

        if ($stmt2->affected_rows >= 0) {
            $desc = "Pull-out #$id EDITED by $user_name";
            $conn->query("INSERT INTO activity_logs (user_name, action, description) VALUES ('$user_name','EDIT_PULLOUT','$desc')");
            echo json_encode(['success' => true, 'message' => "Request #$id updated."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Edit failed.']);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
    exit();
}

// ── Fetch PENDING ──────────────────────────────────────────────────────────────
$search     = trim($_GET['search']   ?? '');
$dateFilter = trim($_GET['date']     ?? '');
$locFilter  = trim($_GET['location'] ?? '');

$sql = "
    SELECT p.*, a.brand, a.model, a.serial_number, a.location AS asset_location,
           a.sub_location, c.name AS category_name, sc.name AS subcategory_name
    FROM pull_out_transactions p
    LEFT JOIN assets         a  ON p.asset_id        = a.id
    LEFT JOIN categories     c  ON a.category_id     = c.id
    LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
    WHERE p.status = 'PENDING'
";
$params = []; $types = '';

if ($search !== '') {
    $sql .= " AND (a.brand LIKE ? OR a.model LIKE ? OR p.requested_by LIKE ? OR a.serial_number LIKE ? OR p.from_location LIKE ? OR p.to_location LIKE ?)";
    $l = "%$search%";
    $params = array_merge($params, [$l,$l,$l,$l,$l,$l]); $types .= 'ssssss';
}
if ($dateFilter !== '') {
    $sql .= " AND DATE(p.created_at) = ?"; $params[] = $dateFilter; $types .= 's';
}
if ($locFilter !== '') {
    $sql .= " AND (p.from_location LIKE ? OR p.to_location LIKE ?)";
    $ll = $locFilter . '%'; $params = array_merge($params, [$ll, $ll]); $types .= 'ss';
}
$sql .= " ORDER BY p.created_at ASC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result();

$countResult = $conn->query("SELECT COUNT(*) FROM pull_out_transactions WHERE status='PENDING'");
$totalPending = $countResult->fetch_row()[0];

// Distinct locations for filter
$locRows = $conn->query("
    SELECT DISTINCT TRIM(SUBSTRING_INDEX(loc,' / ',1)) AS main_loc FROM (
        SELECT from_location AS loc FROM pull_out_transactions WHERE from_location IS NOT NULL AND from_location != '' AND status='PENDING'
        UNION ALL
        SELECT to_location   AS loc FROM pull_out_transactions WHERE to_location   IS NOT NULL AND to_location   != '' AND status='PENDING'
    ) x ORDER BY main_loc
");
$locations = [];
while ($lr = $locRows->fetch_assoc()) { if ($lr['main_loc']) $locations[] = $lr['main_loc']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Incoming Orders — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
:root {
    --primary: #263dc7; --primary-dark: #2f538a;
    --amber: #f39c12;   --amber-light: #fef9ed;
    --green: #27ae60;   --red: #e74c3c;
    --purple: #8e44ad;  --info: #3498db;
    --bg: #f4f6f9;      --white: #fff;
    --text: #2c3e50;    --muted: #7f8c8d;
    --border: #e8ecf0;  --shadow: 0 2px 12px rgba(0,0,0,.07);
    --radius: 12px;
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Space Grotesk',sans-serif; }
body { background: var(--bg); color: var(--text); }

.content { margin-left: 88px; padding: 2rem; transition: margin-left .3s; }
.sidebar:not(.close) ~ .content { margin-left: 260px; }

/* Header */
.page-header {
    background: linear-gradient(135deg, #263dc7 0%, var(--primary-dark) 100%);
    border-radius: 16px; padding: 2rem 2rem 1.5rem;
    color: white; margin-bottom: 1.5rem; position: relative; overflow: hidden;
}
.page-header::before { content:''; position:absolute; top:-50%; right:-5%; width:300px; height:300px; background:rgba(255,255,255,.08); border-radius:50%; }
.page-header h1 { font-size:1.6rem; font-weight:700; position:relative; z-index:1; display:flex; align-items:center; gap:.6rem; }
.page-header p  { font-size:.9rem; opacity:.85; margin-top:.25rem; position:relative; z-index:1; }

/* Stats row */
.stats-row { display:grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.stat-card { background:var(--white); border-radius:var(--radius); padding:1.25rem 1.5rem; box-shadow:var(--shadow); display:flex; align-items:center; gap:1rem; }
.stat-icon { width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
.stat-icon.amber  { background:#fef3cd; color:var(--amber); }
.stat-icon.blue   { background:#d6eaf8; color:var(--info); }
.stat-value { font-size:1.6rem; font-weight:700; line-height:1; }
.stat-label { font-size:.75rem; color:var(--muted); margin-top:2px; text-transform:uppercase; letter-spacing:.5px; }

/* Filters */
.filters {
    background:var(--white); border-radius:var(--radius); padding:1rem 1.25rem;
    box-shadow:var(--shadow); display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-bottom:1.25rem;
}
.filters input, .filters select {
    border:1.5px solid var(--border); border-radius:8px; padding:.6rem 1rem;
    font-family:'Space Grotesk',sans-serif; font-size:.875rem; color:var(--text); outline:none; background:white;
    transition:border-color .2s;
}
.filters input:focus, .filters select:focus { border-color:var(--primary); }
.search-wrap { position:relative; flex:1; min-width:220px; }
.search-wrap input { padding-left:2.4rem; width:100%; }
.search-wrap i { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--muted); }

.btn { padding:.6rem 1.2rem; border:none; border-radius:8px; font-family:'Space Grotesk',sans-serif; font-size:.875rem; font-weight:600; cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:.4rem; }
.btn-primary  { background:var(--primary); color:white; }
.btn-primary:hover { background:var(--primary-dark); transform:translateY(-1px); }
.btn-success  { background:var(--green); color:white; }
.btn-success:hover  { background:#219a52; }
.btn-danger   { background:var(--red); color:white; }
.btn-danger:hover   { background:#c0392b; }
.btn-warning  { background:var(--amber); color:white; }
.btn-warning:hover  { background:#e67e22; }
.btn-secondary { background:#ecf0f1; color:var(--text); }
.btn-secondary:hover { background:#d5dbdb; }
.btn-export   { background:#16a085; color:white; }
.btn-export:hover   { background:#138d75; }

/* Table */
.table-wrap { background:var(--white); border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
.table-wrap .table-scroll { overflow-x:auto; }
table { width:100%; border-collapse:collapse; font-size:.85rem; }
thead { background:linear-gradient(135deg,#2d3436 0%,#34495e 100%); color:white; }
thead th { padding:.9rem .85rem; text-align:left; font-size:.78rem; text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }
tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
tbody tr:hover { background:#f8f9ff; }
tbody td { padding:.85rem .85rem; vertical-align:middle; }

.asset-cell .brand { font-weight:600; }
.asset-cell .model { font-size:.8rem; color:var(--muted); }
.asset-cell .serial { font-size:.76rem; background:#f1f2f6; padding:1px 6px; border-radius:4px; font-family:monospace; color:var(--muted); display:inline-block; margin-top:2px; }

.route-cell .from { font-size:.8rem; color:var(--muted); }
.route-cell .arrow { color:var(--primary); font-size:.75rem; font-weight:700; }
.route-cell .to   { font-weight:600; }

.badge { display:inline-block; padding:.3rem .7rem; border-radius:6px; font-size:.74rem; font-weight:700; text-transform:uppercase; }
.badge-pending   { background:#fff3cd; color:#856404; }
.badge-urgent    { background:#fde8e8; color:#c0392b; }

.actions { display:flex; gap:.4rem; flex-wrap:wrap; }

.empty-state { text-align:center; padding:3.5rem; color:var(--muted); }
.empty-state i { font-size:3.5rem; opacity:.25; display:block; margin-bottom:.75rem; }

/* Modal */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); backdrop-filter:blur(3px); z-index:9999; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal { background:var(--white); border-radius:16px; width:95%; max-width:540px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.25); animation:slideUp .25s ease; }
@keyframes slideUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
.modal-header { padding:1.25rem 1.5rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
.modal-header h3 { font-size:1.1rem; font-weight:700; display:flex; align-items:center; gap:.5rem; }
.modal-close { background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--muted); border-radius:6px; padding:.2rem .4rem; transition:background .2s; }
.modal-close:hover { background:var(--border); }
.modal-body { padding:1.5rem; }
.modal-footer { padding:1rem 1.5rem; border-top:1px solid var(--border); display:flex; gap:.75rem; justify-content:flex-end; }

.form-group { margin-bottom:1rem; }
.form-group label { display:block; font-size:.8rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:.4rem; }
.form-group input, .form-group textarea, .form-group select {
    width:100%; border:1.5px solid var(--border); border-radius:8px; padding:.7rem 1rem;
    font-family:'Space Grotesk',sans-serif; font-size:.9rem; outline:none; transition:border-color .2s;
}
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color:var(--primary); }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }

.toast { position:fixed; top:20px; right:20px; padding:.9rem 1.4rem; border-radius:10px; color:white; font-weight:600; font-size:.875rem; z-index:10000; animation:toastIn .3s ease; box-shadow:0 4px 16px rgba(0,0,0,.2); }
.toast.success { background:var(--green); }
.toast.error   { background:var(--red); }
@keyframes toastIn { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:translateX(0); } }
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>
<div class="content">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1><i class='bx bx-box'></i> Incoming Transfer Requests</h1>
            <p>Review, edit, confirm or cancel pending asset transfer requests</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon amber"><i class='bx bx-time-five'></i></div>
            <div>
                <div class="stat-value" id="statPending"><?php echo $totalPending; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class='bx bx-map-pin'></i></div>
            <div>
                <div class="stat-value"><?php echo count($locations); ?></div>
                <div class="stat-label">Locations</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters">
        <div class="search-wrap">
            <i class='bx bx-search'></i>
            <input type="text" id="searchInput" placeholder="Search asset, requester, location..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <input type="date" id="dateInput" value="<?php echo htmlspecialchars($dateFilter); ?>">
        <select id="locSelect">
            <option value="">All Locations</option>
            <?php foreach ($locations as $loc): ?>
                <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $locFilter === $loc ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($loc); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-export" onclick="exportCSV()"><i class='bx bx-download'></i> Export CSV</button>
    </div>

    <!-- Table -->
    <div class="table-wrap">
        <div class="table-scroll">
            <table id="mainTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Asset</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>From → To</th>
                        <th>Purpose</th>
                        <th>Requested By</th>
                        <th>Date Needed</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                <?php
                $rows = [];
                while ($r = $requests->fetch_assoc()):
                    $rows[] = $r;
                    $from = $r['from_location'] ?: '—';
                    $to   = $r['to_location']   ?: ($r['location_received'] ?: '—');
                    // Fallback parse for legacy
                    if ($from === '—' && !empty($r['purpose'])) {
                        if (preg_match('/From:\s*(.+?)\s*→/', $r['purpose'], $m)) $from = trim($m[1]);
                    }
                    if ($to === '—' && !empty($r['purpose'])) {
                        if (preg_match('/→\s*To:\s*(.+?)(\s*\[|$)/', $r['purpose'], $m)) $to = trim($m[1]);
                    }
                    $purpose = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/','', $r['purpose'] ?? '');
                    $purpose = preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i','', $purpose);
                    $purpose = trim($purpose) ?: '—';
                ?>
                <tr id="row-<?php echo $r['id']; ?>">
                    <td><strong>#<?php echo $r['id']; ?></strong></td>
                    <td>
                        <div class="asset-cell">
                            <div class="brand"><?php echo htmlspecialchars($r['brand'] ?? '—'); ?></div>
                            <?php if ($r['model']): ?><div class="model"><?php echo htmlspecialchars($r['model']); ?></div><?php endif; ?>
                            <?php if ($r['serial_number']): ?><span class="serial"><?php echo htmlspecialchars($r['serial_number']); ?></span><?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($r['subcategory_name'] ?? '—'); ?></td>
                    <td><strong><?php echo $r['quantity']; ?></strong></td>
                    <td>
                        <div class="route-cell">
                            <div class="from"><?php echo htmlspecialchars($from); ?></div>
                            <div class="arrow">↓</div>
                            <div class="to"><?php echo htmlspecialchars($to); ?></div>
                        </div>
                    </td>
                    <td style="max-width:140px; white-space:normal;"><?php echo htmlspecialchars($purpose); ?></td>
                    <td><?php echo htmlspecialchars($r['requested_by'] ?? '—'); ?></td>
                    <td><?php echo $r['date_needed'] ? date('M j, Y', strtotime($r['date_needed'])) : '—'; ?></td>
                    <td style="font-size:.8rem; color:var(--muted);"><?php echo date('M j, Y', strtotime($r['created_at'])); ?></td>
                    <td>
                        <div class="actions">
                            <button class="btn btn-warning" onclick="openEdit(<?php echo $r['id']; ?>)" title="Edit">
                                <i class='bx bx-edit'></i>
                            </button>
                            <button class="btn btn-success" onclick="confirmAction(<?php echo $r['id']; ?>,'confirm')" title="Confirm">
                                <i class='bx bx-check'></i>
                            </button>
                            <button class="btn btn-danger" onclick="openReject(<?php echo $r['id']; ?>)" title="Cancel">
                                <i class='bx bx-x'></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (empty($rows)): ?>
                <tr><td colspan="10">
                    <div class="empty-state"><i class='bx bx-inbox'></i><p>No pending requests</p></div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class='bx bx-edit' style="color:var(--amber)"></i> Edit Transfer Request</h3>
            <button class="modal-close" onclick="closeModal('editModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editId">
            <div class="form-row">
                <div class="form-group">
                    <label>From Location</label>
                    <input type="text" id="editFrom" placeholder="e.g. QC WareHouse">
                </div>
                <div class="form-group">
                    <label>To Location</label>
                    <input type="text" id="editTo" placeholder="e.g. CFE">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" id="editQty" min="1">
                </div>
                <div class="form-group">
                    <label>Date Needed</label>
                    <input type="date" id="editDate">
                </div>
            </div>
            <div class="form-group">
                <label>Purpose</label>
                <textarea id="editPurpose" rows="3" placeholder="Purpose of transfer..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
            <button class="btn btn-warning" onclick="saveEdit()"><i class='bx bx-save'></i> Save Changes</button>
        </div>
    </div>
</div>

<!-- Reject/Cancel Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class='bx bx-x-circle' style="color:var(--red)"></i> Cancel Transfer Request</h3>
            <button class="modal-close" onclick="closeModal('rejectModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="rejectId">
            <p style="margin-bottom:1rem; color:var(--muted);">This will cancel the pending request. Asset will remain at source location.</p>
            <div class="form-group">
                <label>Reason (optional)</label>
                <textarea id="rejectReason" rows="3" placeholder="Enter reason for cancellation..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('rejectModal')">Back</button>
            <button class="btn btn-danger" onclick="submitReject()"><i class='bx bx-block'></i> Confirm Cancel</button>
        </div>
    </div>
</div>

<script>
// ── Row data cache from PHP ───────────────────────────────────────
const rowData = <?php
    $out = [];
    foreach ($rows as $r) {
        $from = $r['from_location'] ?: '';
        $to   = $r['to_location']   ?: ($r['location_received'] ?: '');
        $purpose = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $r['purpose'] ?? '');
        $purpose = preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $purpose);
        $out[$r['id']] = [
            'from'       => $from,
            'to'         => $to,
            'purpose'    => trim($purpose),
            'quantity'   => (int)$r['quantity'],
            'date_needed'=> $r['date_needed'] ?? '',
        ];
    }
    echo json_encode($out);
?>;

// ── Filters ───────────────────────────────────────────────────────
function applyFilters() {
    const p = new URLSearchParams({
        search  : document.getElementById('searchInput').value,
        date    : document.getElementById('dateInput').value,
        location: document.getElementById('locSelect').value
    });
    window.location.href = 'admin-incoming.php?' + p.toString();
}
document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
document.getElementById('dateInput').addEventListener('change', applyFilters);
document.getElementById('locSelect').addEventListener('change', applyFilters);

// ── Edit Modal ────────────────────────────────────────────────────
function openEdit(id) {
    const d = rowData[id];
    document.getElementById('editId').value      = id;
    document.getElementById('editFrom').value    = d.from;
    document.getElementById('editTo').value      = d.to;
    document.getElementById('editQty').value     = d.quantity;
    document.getElementById('editDate').value    = d.date_needed;
    document.getElementById('editPurpose').value = d.purpose;
    document.getElementById('editModal').classList.add('active');
}

async function saveEdit() {
    const id = document.getElementById('editId').value;
    const body = new FormData();
    body.append('action',        'edit');
    body.append('id',            id);
    body.append('from_location', document.getElementById('editFrom').value);
    body.append('to_location',   document.getElementById('editTo').value);
    body.append('quantity',      document.getElementById('editQty').value);
    body.append('date_needed',   document.getElementById('editDate').value);
    body.append('purpose',       document.getElementById('editPurpose').value);

    const res    = await fetch('admin-incoming.php', { method:'POST', body });
    const result = await res.json();
    showToast(result.message, result.success ? 'success' : 'error');
    if (result.success) { closeModal('editModal'); setTimeout(() => location.reload(), 800); }
}

// ── Confirm ───────────────────────────────────────────────────────
async function confirmAction(id, action) {
    const body = new FormData();
    body.append('action', action);
    body.append('id',     id);
    const res    = await fetch('admin-incoming.php', { method:'POST', body });
    const result = await res.json();
    showToast(result.message, result.success ? 'success' : 'error');
    if (result.success) {
        document.getElementById('row-' + id)?.remove();
        const stat = document.getElementById('statPending');
        if (stat) stat.textContent = Math.max(0, parseInt(stat.textContent) - 1);
    }
}

// ── Reject Modal ──────────────────────────────────────────────────
function openReject(id) {
    document.getElementById('rejectId').value     = id;
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModal').classList.add('active');
}
async function submitReject() {
    const id     = document.getElementById('rejectId').value;
    const reason = document.getElementById('rejectReason').value;
    const body   = new FormData();
    body.append('action', 'reject');
    body.append('id',     id);
    body.append('reason', reason);
    const res    = await fetch('admin-incoming.php', { method:'POST', body });
    const result = await res.json();
    showToast(result.message, result.success ? 'success' : 'error');
    if (result.success) {
        closeModal('rejectModal');
        document.getElementById('row-' + id)?.remove();
        const stat = document.getElementById('statPending');
        if (stat) stat.textContent = Math.max(0, parseInt(stat.textContent) - 1);
    }
}

// ── Export CSV ────────────────────────────────────────────────────
function exportCSV() {
    const table  = document.getElementById('mainTable');
    const rows   = [...table.querySelectorAll('tr')];
    const csv    = rows.map(row =>
        [...row.querySelectorAll('th,td')]
            .slice(0, -1) // exclude Actions column
            .map(cell => `"${cell.innerText.replace(/"/g,'""').replace(/\n/g,' ')}"`)
            .join(',')
    ).join('\n');
    const blob   = new Blob([csv], { type:'text/csv' });
    const url    = URL.createObjectURL(blob);
    const a      = document.createElement('a');
    a.href       = url;
    a.download   = `incoming_requests_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// ── Helpers ───────────────────────────────────────────────────────
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); }));

function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>