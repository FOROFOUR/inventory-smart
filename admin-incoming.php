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

    // ── GET DETAILS (for view modal) ──────────────────────────────────────────
    if ($action === 'get_details') {
        $stmt = $conn->prepare("
            SELECT p.*, a.brand, a.model, a.serial_number, a.condition,
                   a.location AS asset_location, a.sub_location AS asset_sub_location,
                   a.description AS asset_description, a.beg_balance_count,
                   c.name AS category_name, sc.name AS subcategory_name
            FROM pull_out_transactions p
            LEFT JOIN assets         a  ON p.asset_id        = a.id
            LEFT JOIN categories     c  ON a.category_id     = c.id
            LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
            WHERE p.id = ? AND p.status = 'PENDING'
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) { echo json_encode(['success' => false, 'message' => 'Not found.']); exit(); }

        $row['purpose'] = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $row['purpose'] ?? ''));
        $row['purpose'] = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $row['purpose']));

        // All images
        $imgStmt = $conn->prepare("SELECT image_path, drive_url FROM asset_images WHERE asset_id = ? ORDER BY id LIMIT 10");
        $imgStmt->bind_param("i", $row['asset_id']);
        $imgStmt->execute();
        $imgResult = $imgStmt->get_result();
        $images = [];
        while ($img = $imgResult->fetch_assoc()) {
            if ($img['image_path'] === 'gdrive_folder' && !empty($img['drive_url'])) {
                if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $img['drive_url'], $m) ||
                    preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $img['drive_url'], $m)) {
                    $images[] = ['type' => 'gdrive', 'thumb' => "https://drive.google.com/thumbnail?id={$m[1]}&sz=w400", 'url' => $img['drive_url']];
                } else {
                    $images[] = ['type' => 'gdrive_folder', 'url' => $img['drive_url'], 'thumb' => null];
                }
            } elseif (!empty($img['image_path']) && $img['image_path'] !== 'gdrive_folder') {
                $images[] = ['type' => 'image', 'url' => '/' . ltrim($img['image_path'], '/'), 'thumb' => null];
            }
        }
        $imgStmt->close();
        $row['images'] = $images;

        echo json_encode(['success' => true, 'data' => $row]);
        exit();
    }

    if ($action === 'confirm') {
        $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = 'CONFIRMED' WHERE id = ? AND status = 'PENDING'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $desc = "Pull-out #$id CONFIRMED by $user_name";
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
        $purpose       = htmlspecialchars(trim($_POST['purpose']       ?? ''));
        $date_needed   = trim($_POST['date_needed']   ?? '');
        $from_location = htmlspecialchars(trim($_POST['from_location'] ?? ''));
        $to_location   = htmlspecialchars(trim($_POST['to_location']   ?? ''));
        $quantity      = (int)($_POST['quantity'] ?? 1);

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

// ── Fetch PENDING ─────────────────────────────────────────────────────────────
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

$countResult  = $conn->query("SELECT COUNT(*) FROM pull_out_transactions WHERE status='PENDING'");
$totalPending = $countResult->fetch_row()[0];

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
    --bg: #f4f6f9;      --white: #fff;
    --text: #2c3e50;    --muted: #7f8c8d;
    --border: #e8ecf0;  --shadow: 0 2px 12px rgba(0,0,0,.07);
    --radius: 14px;
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Space Grotesk',sans-serif; }
body { background: var(--bg); color: var(--text); line-height:1.5; }

.content { 
    margin-left: 88px; 
    padding: 1.5rem; 
    transition: margin-left .3s; 
    min-height: 100vh; 
}
.sidebar:not(.close) ~ .content { margin-left: 260px; }

/* Page Header */
.page-header { 
    background: linear-gradient(135deg, #263dc7 0%, var(--primary-dark) 100%); 
    border-radius: 16px; 
    padding: 2rem 1.75rem; 
    color: white; 
    margin-bottom: 1.5rem; 
    position: relative; 
    overflow: hidden; 
}
.page-header h1 { 
    font-size: 1.85rem; 
    font-weight:700; 
    display:flex; 
    align-items:center; 
    gap:.6rem; 
}

/* Stats */
.stats-row { 
    display:grid; 
    grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); 
    gap:1rem; 
    margin-bottom:1.75rem; 
}
.stat-card { 
    background:var(--white); 
    border-radius:var(--radius); 
    padding:1.25rem; 
    box-shadow:var(--shadow); 
    display:flex; 
    align-items:center; 
    gap:1rem; 
}

/* Filters */
.filters { 
    background:var(--white); 
    border-radius:var(--radius); 
    padding:1.25rem; 
    box-shadow:var(--shadow); 
    display:flex; 
    gap:1rem; 
    flex-wrap:wrap; 
    align-items:center; 
    margin-bottom:1.5rem; 
}
.search-wrap { position:relative; flex:1; min-width:220px; }
.search-wrap input { padding-left:2.5rem; width:100%; padding:.75rem 1rem; border:1.5px solid var(--border); border-radius:8px; }
.search-wrap i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--muted); }

/* Table */
.table-wrap { 
    background:var(--white); 
    border-radius:var(--radius); 
    box-shadow:var(--shadow); 
    overflow:hidden; 
}
.table-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
table { 
    width:100%; 
    min-width:980px; 
    border-collapse:collapse; 
    font-size:.87rem; 
}
thead th { 
    padding:1rem .85rem; 
    background:linear-gradient(135deg,#2d3436 0%,#34495e 100%); 
    color:white; 
    text-align:left; 
    font-size:.78rem; 
    text-transform:uppercase; 
    letter-spacing:.5px; 
    white-space:nowrap; 
}
tbody td { padding:.9rem .85rem; vertical-align:middle; }

/* Modals */
.modal-overlay { 
    display:none; 
    position:fixed; 
    inset:0; 
    background:rgba(0,0,0,.6); 
    backdrop-filter:blur(5px); 
    z-index:9999; 
    align-items:center; 
    justify-content:center; 
    padding:1rem; 
}
.modal-overlay.active { display:flex; }
.modal { 
    background:var(--white); 
    border-radius:16px; 
    width:100%; 
    max-width:680px; 
    max-height:92vh; 
    overflow-y:auto; 
    box-shadow:0 20px 60px rgba(0,0,0,.3); 
}
.modal-wide { max-width:720px; }

/* Responsive */
@media (max-width: 768px) {
    .content { margin-left:0 !important; padding:1rem; }
    .page-header { padding:1.75rem 1.5rem; }
    .page-header h1 { font-size:1.65rem; }
    
    .stats-row { grid-template-columns: 1fr 1fr; }
    
    .filters { 
        flex-direction:column; 
        align-items:stretch; 
    }
    .search-wrap { min-width:100%; }
    
    .actions { flex-direction:column; gap:.5rem; }
    .btn { width:100%; justify-content:center; }
}

@media (max-width: 480px) {
    table { font-size:.82rem; }
    thead th, tbody td { padding:.8rem .6rem; }
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Space Grotesk',sans-serif; }



.page-header::before { content:''; position:absolute; top:-50%; right:-5%; width:300px; height:300px; background:rgba(255,255,255,.08); border-radius:50%; }
.page-header p  { font-size:.9rem; opacity:.85; margin-top:.25rem; position:relative; z-index:1; }
.stat-icon { width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
.stat-icon.amber { background:#fef3cd; color:var(--amber); }
.stat-icon.blue  { background:#d6eaf8; color:var(--info); }
.stat-value { font-size:1.6rem; font-weight:700; line-height:1; }
.stat-label { font-size:.75rem; color:var(--muted); margin-top:2px; text-transform:uppercase; letter-spacing:.5px; }
.filters input, .filters select { border:1.5px solid var(--border); border-radius:8px; padding:.6rem 1rem; font-family:'Space Grotesk',sans-serif; font-size:.875rem; color:var(--text); outline:none; background:white; transition:border-color .2s; }
.filters input:focus, .filters select:focus { border-color:var(--primary); }

.search-wrap i { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--muted); }
.btn { padding:.6rem 1.2rem; border:none; border-radius:8px; font-family:'Space Grotesk',sans-serif; font-size:.875rem; font-weight:600; cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:.4rem; }
.btn-primary  { background:var(--primary); color:white; }
.btn-primary:hover { background:var(--primary-dark); }
.btn-success  { background:var(--green); color:white; }
.btn-success:hover { background:#219a52; }
.btn-danger   { background:var(--red); color:white; }
.btn-danger:hover { background:#c0392b; }
.btn-warning  { background:var(--amber); color:white; }
.btn-warning:hover { background:#e67e22; }
.btn-secondary { background:#ecf0f1; color:var(--text); }
.btn-secondary:hover { background:#d5dbdb; }
.btn-export   { background:#16a085; color:white; }
.btn-export:hover { background:#138d75; }
.btn-view     { background:#2980b9; color:white; }
.btn-view:hover { background:#2471a3; }

table { width:100%; border-collapse:collapse; font-size:.85rem; }
thead { background:linear-gradient(135deg,#2d3436 0%,#34495e 100%); color:white; }
tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
tbody tr:hover { background:#f8f9ff; }

.asset-cell .brand { font-weight:600; }
.asset-cell .model  { font-size:.8rem; color:var(--muted); }
.asset-cell .serial { font-size:.76rem; background:#f1f2f6; padding:1px 6px; border-radius:4px; font-family:monospace; color:var(--muted); display:inline-block; margin-top:2px; }
.route-cell .from  { font-size:.8rem; color:var(--muted); }
.route-cell .arrow { color:var(--primary); font-size:.75rem; font-weight:700; }
.route-cell .to    { font-weight:600; }
.actions { display:flex; gap:.4rem; flex-wrap:wrap; }
.empty-state { text-align:center; padding:3.5rem; color:var(--muted); }
.empty-state i { font-size:3.5rem; opacity:.25; display:block; margin-bottom:.75rem; }

/* ── MODALS ── */

.modal.modal-wide { max-width:680px; }
@keyframes slideUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
.modal-header { padding:1.25rem 1.5rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:2; background:white; }
.modal-header h3 { font-size:1.1rem; font-weight:700; display:flex; align-items:center; gap:.5rem; }
.modal-close { background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--muted); border-radius:6px; padding:.2rem .4rem; }
.modal-close:hover { background:var(--border); }
.modal-body { padding:1.5rem; }
.modal-footer { padding:1rem 1.5rem; border-top:1px solid var(--border); display:flex; gap:.75rem; justify-content:flex-end; background:white; position:sticky; bottom:0; }
.form-group { margin-bottom:1rem; }
.form-group label { display:block; font-size:.8rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:.4rem; }
.form-group input, .form-group textarea, .form-group select { width:100%; border:1.5px solid var(--border); border-radius:8px; padding:.7rem 1rem; font-family:'Space Grotesk',sans-serif; font-size:.9rem; outline:none; transition:border-color .2s; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color:var(--primary); }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }

/* ── VIEW MODAL STYLES ── */
.view-gallery { display:grid; grid-template-columns:repeat(auto-fill, minmax(120px,1fr)); gap:.75rem; margin-bottom:1.5rem; }
.view-gallery-item { border-radius:10px; overflow:hidden; aspect-ratio:1; background:#f4f6f9; border:2px solid var(--border); cursor:pointer; transition:all .2s; position:relative; }
.view-gallery-item:hover { border-color:var(--primary); transform:translateY(-2px); box-shadow:0 6px 16px rgba(38,61,199,.15); }
.view-gallery-item img { width:100%; height:100%; object-fit:cover; display:block; }
.view-gallery-placeholder { display:flex; align-items:center; justify-content:center; height:100%; color:var(--muted); font-size:2.5rem; }
.view-gdrive-badge { position:absolute; bottom:0; left:0; right:0; background:linear-gradient(transparent,rgba(0,0,0,.55)); padding:.3rem .5rem; display:flex; align-items:center; gap:.3rem; }
.view-gdrive-badge i { color:white; font-size:.85rem; }
.view-gdrive-badge span { color:white; font-size:.65rem; font-weight:600; }
.view-section-title { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin:1.25rem 0 .75rem; padding-bottom:.4rem; border-bottom:2px solid var(--border); }
.view-detail-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:.75rem; }
.view-detail-item { background:#f8f9fa; border-radius:8px; padding:.75rem 1rem; border-left:3px solid var(--primary); }
.view-detail-item.full { grid-column:1/-1; }
.view-detail-label { font-size:.7rem; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); font-weight:700; margin-bottom:.25rem; }
.view-detail-value { font-size:.92rem; font-weight:600; color:var(--text); word-break:break-word; }
.view-asset-name { font-size:1.3rem; font-weight:800; color:var(--text); margin-bottom:.2rem; letter-spacing:-.3px; }
.view-asset-type { font-size:.85rem; color:var(--muted); margin-bottom:.75rem; }
.view-serial-badge { display:inline-flex; align-items:center; gap:.4rem; background:#2c3e50; color:#fff; font-family:'Courier New',monospace; font-weight:700; font-size:.82rem; letter-spacing:.5px; padding:.35rem .85rem; border-radius:6px; margin-bottom:1rem; }
.view-serial-badge i { font-size:.9rem; opacity:.7; }
.view-route-row { display:flex; align-items:center; gap:.75rem; background:#e8eaf6; border-radius:10px; padding:.85rem 1rem; margin:.75rem 0; }
.view-route-row .vr-loc { flex:1; }
.view-route-row .vr-label { font-size:.7rem; text-transform:uppercase; letter-spacing:.5px; color:var(--primary); font-weight:700; }
.view-route-row .vr-value { font-size:.95rem; font-weight:700; color:var(--text); }
.view-route-row .vr-arrow { font-size:1.4rem; color:var(--primary); flex-shrink:0; }
.badge-condition { display:inline-block; padding:.25rem .65rem; border-radius:6px; font-size:.78rem; font-weight:700; text-transform:uppercase; }
.badge-new  { background:#d5f5e3; color:#1e8449; }
.badge-used { background:#fef3cd; color:#856404; }

.toast { position:fixed; top:20px; right:20px; padding:.9rem 1.4rem; border-radius:10px; color:white; font-weight:600; font-size:.875rem; z-index:10000; animation:toastIn .3s ease; box-shadow:0 4px 16px rgba(0,0,0,.2); }
.toast.success { background:var(--green); }
.toast.error   { background:var(--red); }
@keyframes toastIn { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:translateX(0); } }
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">
    <div class="page-header">
        <h1><i class='bx bx-box'></i> Incoming Transfer Requests</h1>
        <p>Review, edit, confirm or cancel pending asset transfer requests</p>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3cd;color:var(--amber);"><i class='bx bx-time-five'></i></div>
            <div>
                <div class="stat-value" id="statPending"><?php echo $totalPending; ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#d6eaf8;color:var(--info);"><i class='bx bx-map-pin'></i></div>
            <div>
                <div class="stat-value"><?php echo count($locations); ?></div>
                <div class="stat-label">Active Locations</div>
            </div>
        </div>
    </div>


<div class="filters">
        <div class="search-wrap">
            <i class='bx bx-search'></i>
            <input type="text" id="searchInput" placeholder="Search asset, requester, location..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <input type="date" id="dateInput" value="<?php echo htmlspecialchars($dateFilter); ?>" style="padding:.75rem 1rem;border:1.5px solid var(--border);border-radius:8px;">
        
        <select id="locSelect" style="padding:.75rem 1rem;border:1.5px solid var(--border);border-radius:8px;">
            <option value="">All Locations</option>
            <?php foreach ($locations as $loc): ?>
                <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $locFilter === $loc ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($loc); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-export" onclick="exportCSV()" style="background:#16a085;color:white;">
            <i class='bx bx-download'></i> Export CSV
        </button>
    </div>

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
                    if ($from === '—' && !empty($r['purpose'])) { 
                        if (preg_match('/From:\s*(.+?)\s*→/', $r['purpose'], $m)) $from = trim($m[1]); 
                    }
                    if ($to   === '—' && !empty($r['purpose'])) { 
                        if (preg_match('/→\s*To:\s*(.+?)(\s*\[|$)/', $r['purpose'], $m)) $to = trim($m[1]); 
                    }
                    $purpose = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/','', $r['purpose'] ?? '');
                    $purpose = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i','', $purpose)) ?: '—';
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
                    <td style="max-width:140px;white-space:normal;"><?php echo htmlspecialchars($purpose); ?></td>
                    <td><?php echo htmlspecialchars($r['requested_by'] ?? '—'); ?></td>
                    <td><?php echo $r['date_needed'] ? date('M j, Y', strtotime($r['date_needed'])) : '—'; ?></td>
                    <td style="font-size:.8rem;color:var(--muted);"><?php echo date('M j, Y', strtotime($r['created_at'])); ?></td>
                    <td>
                        <div class="actions">
                            <button class="btn btn-view"    onclick="openView(<?php echo $r['id']; ?>)" title="View"><i class='bx bx-show'></i></button>
                            <button class="btn btn-warning" onclick="openEdit(<?php echo $r['id']; ?>)" title="Edit"><i class='bx bx-edit'></i></button>
                            <button class="btn btn-danger"  onclick="openReject(<?php echo $r['id']; ?>)" title="Cancel"><i class='bx bx-x'></i></button>
                            <button class="btn btn-success" onclick="confirmAction(<?php echo $r['id']; ?>,'confirm')" title="Confirm"><i class='bx bx-check'></i></button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (empty($rows)): ?>
                <tr><td colspan="10"><div class="empty-state"><i class='bx bx-inbox'></i><p>No pending requests found</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     VIEW MODAL
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="viewModal">
    <div class="modal modal-wide">
        <div class="modal-header" style="background:linear-gradient(135deg,#263dc7 0%,var(--primary-dark) 100%);color:white;border-bottom:none;">
            <h3 style="color:white;"><i class='bx bx-info-circle'></i> Request Details</h3>
            <button class="modal-close" onclick="closeModal('viewModal')" style="color:white;background:rgba(255,255,255,.15);"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <div id="viewLoading" style="text-align:center;padding:2rem;color:var(--muted);">
                <i class='bx bx-loader-alt bx-spin' style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>Loading details...
            </div>
            <div id="viewContent" style="display:none;">
                <div id="viewAssetName" class="view-asset-name"></div>
                <div id="viewAssetType" class="view-asset-type"></div>
                <div id="viewSerialBadge" style="display:none;" class="view-serial-badge"><i class='bx bx-barcode'></i><span id="viewSerialText"></span></div>

                <div class="view-section-title"><i class='bx bx-image-alt' style="margin-right:.3rem;"></i>Photos</div>
                <div class="view-gallery" id="viewGallery"></div>

                <div class="view-section-title"><i class='bx bx-transfer-alt' style="margin-right:.3rem;"></i>Transfer Info</div>
                <div class="view-route-row">
                    <div class="vr-loc"><div class="vr-label">From</div><div class="vr-value" id="viewFrom">—</div></div>
                    <i class='bx bx-right-arrow-alt vr-arrow'></i>
                    <div class="vr-loc"><div class="vr-label">To</div><div class="vr-value" id="viewTo">—</div></div>
                </div>
                <div class="view-detail-grid" id="viewTxnGrid"></div>

                <div class="view-section-title"><i class='bx bx-package' style="margin-right:.3rem;"></i>Asset Info</div>
                <div class="view-detail-grid" id="viewAssetGrid"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('viewModal')"><i class='bx bx-x'></i> Close</button>
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
                <div class="form-group"><label>From Location</label><input type="text" id="editFrom" placeholder="e.g. QC WareHouse"></div>
                <div class="form-group"><label>To Location</label><input type="text" id="editTo" placeholder="e.g. CFE"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Quantity</label><input type="number" id="editQty" min="1"></div>
                <div class="form-group"><label>Date Needed</label><input type="date" id="editDate"></div>
            </div>
            <div class="form-group"><label>Purpose</label><textarea id="editPurpose" rows="3" placeholder="Purpose of transfer..."></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
            <button class="btn btn-warning" onclick="saveEdit()"><i class='bx bx-save'></i> Save Changes</button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class='bx bx-x-circle' style="color:var(--red)"></i> Cancel Transfer Request</h3>
            <button class="modal-close" onclick="closeModal('rejectModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="rejectId">
            <p style="margin-bottom:1rem;color:var(--muted);">This will cancel the pending request. Asset will remain at source location.</p>
            <div class="form-group"><label>Reason (optional)</label><textarea id="rejectReason" rows="3" placeholder="Enter reason for cancellation..."></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('rejectModal')">Back</button>
            <button class="btn btn-danger" onclick="submitReject()"><i class='bx bx-block'></i> Confirm Cancel</button>
        </div>
    </div>
</div>

<script>
const rowData = <?php
    $out = [];
    foreach ($rows as $r) {
        $from    = $r['from_location'] ?: '';
        $to      = $r['to_location']   ?: ($r['location_received'] ?: '');
        $purpose = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $r['purpose'] ?? '');
        $purpose = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $purpose));
        $out[$r['id']] = ['from' => $from, 'to' => $to, 'purpose' => $purpose, 'quantity' => (int)$r['quantity'], 'date_needed' => $r['date_needed'] ?? ''];
    }
    echo json_encode($out);
?>;

function applyFilters() {
    const p = new URLSearchParams({ search: document.getElementById('searchInput').value, date: document.getElementById('dateInput').value, location: document.getElementById('locSelect').value });
    window.location.href = 'admin-incoming.php?' + p.toString();
}
document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
document.getElementById('dateInput').addEventListener('change', applyFilters);
document.getElementById('locSelect').addEventListener('change', applyFilters);

// ── VIEW MODAL ────────────────────────────────────────────────────
async function openView(id) {
    document.getElementById('viewLoading').style.display = 'block';
    document.getElementById('viewContent').style.display = 'none';
    document.getElementById('viewModal').classList.add('active');

    try {
        const body = new FormData();
        body.append('action', 'get_details'); body.append('id', id);
        const res    = await fetch('admin-incoming.php', { method: 'POST', body });
        const result = await res.json();
        if (!result.success) { showToast('Could not load details.', 'error'); closeModal('viewModal'); return; }

        const d = result.data;

        document.getElementById('viewAssetName').textContent = `${d.brand || ''} ${d.model || ''}`.trim() || '—';
        document.getElementById('viewAssetType').textContent = [d.category_name, d.subcategory_name].filter(Boolean).join(' › ') || '';

        const serialBadge = document.getElementById('viewSerialBadge');
        if (d.serial_number) {
            document.getElementById('viewSerialText').textContent = d.serial_number;
            serialBadge.style.display = 'inline-flex';
        } else { serialBadge.style.display = 'none'; }

        // Gallery
        const gallery = document.getElementById('viewGallery');
        gallery.innerHTML = '';
        if (d.images && d.images.length > 0) {
            d.images.forEach(img => {
                const tile = document.createElement('div');
                tile.className = 'view-gallery-item';
                if (img.type === 'gdrive' && img.thumb) {
                    tile.onclick = () => window.open(img.url, '_blank');
                    tile.innerHTML = `<img src="${img.thumb}" alt="photo" onerror="this.parentElement.innerHTML='<div class=\\"view-gallery-placeholder\\"><i class=\\"bx bxl-google\\"></i></div>'">
                        <div class="view-gdrive-badge"><i class='bx bxl-google'></i><span>Open in Drive</span></div>`;
                } else if (img.type === 'gdrive_folder') {
                    tile.onclick = () => window.open(img.url, '_blank');
                    tile.style.cssText = 'background:linear-gradient(135deg,#e8f5e9,#f1f8e9);border:2px solid #a5d6a7;';
                    tile.innerHTML = `<div class="view-gallery-placeholder" style="flex-direction:column;gap:.4rem;"><i class='bx bxl-google' style="font-size:2rem;color:#1a73e8;"></i><span style="font-size:.7rem;color:#1a73e8;font-weight:600;">View on Drive</span></div>`;
                } else {
                    tile.onclick = () => window.open(img.url, '_blank');
                    tile.innerHTML = `<img src="${img.url}" alt="photo" onerror="this.parentElement.innerHTML='<div class=\\"view-gallery-placeholder\\"><i class=\\"bx bx-image\\"></i></div>'">`;
                }
                gallery.appendChild(tile);
            });
        } else {
            gallery.innerHTML = `<div class="view-gallery-item"><div class="view-gallery-placeholder"><i class='bx bx-image'></i></div></div>`;
        }

        // Route
        document.getElementById('viewFrom').textContent = d.from_location || '—';
        document.getElementById('viewTo').textContent   = d.to_location   || '—';

        // Txn details
        const purpose = (d.purpose || '').replace(/\s*\[(dest_asset_id|dest):\d+\]/g,'').replace(/\s*\|?\s*From:.+?→.+$/i,'').trim() || '—';
        document.getElementById('viewTxnGrid').innerHTML = `
            <div class="view-detail-item"><div class="view-detail-label">Transaction #</div><div class="view-detail-value">#${d.id}</div></div>
            <div class="view-detail-item"><div class="view-detail-label">Quantity</div><div class="view-detail-value"><strong style="font-size:1.1rem;">${d.quantity} pcs</strong></div></div>
            <div class="view-detail-item"><div class="view-detail-label">Requested By</div><div class="view-detail-value">${d.requested_by || '—'}</div></div>
            <div class="view-detail-item"><div class="view-detail-label">Date Needed</div><div class="view-detail-value">${d.date_needed ? new Date(d.date_needed).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—'}</div></div>
            <div class="view-detail-item"><div class="view-detail-label">Submitted</div><div class="view-detail-value">${d.created_at ? new Date(d.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—'}</div></div>
            <div class="view-detail-item full"><div class="view-detail-label">Purpose</div><div class="view-detail-value">${purpose}</div></div>`;

        // Asset info
        document.getElementById('viewAssetGrid').innerHTML = `
            <div class="view-detail-item"><div class="view-detail-label">Category</div><div class="view-detail-value">${d.category_name || '—'}</div></div>
            <div class="view-detail-item"><div class="view-detail-label">Type</div><div class="view-detail-value">${d.subcategory_name || '—'}</div></div>
            <div class="view-detail-item"><div class="view-detail-label">Condition</div><div class="view-detail-value"><span class="badge-condition badge-${(d.condition||'').toLowerCase()}">${d.condition || '—'}</span></div></div>
            <div class="view-detail-item"><div class="view-detail-label">Current Location</div><div class="view-detail-value">${d.asset_location || '—'}</div></div>
            ${d.asset_sub_location ? `<div class="view-detail-item"><div class="view-detail-label">Sub-Location</div><div class="view-detail-value">${d.asset_sub_location}</div></div>` : ''}
            ${d.asset_description ? `<div class="view-detail-item full"><div class="view-detail-label">Description</div><div class="view-detail-value">${d.asset_description}</div></div>` : ''}`;

        document.getElementById('viewLoading').style.display = 'none';
        document.getElementById('viewContent').style.display = 'block';
    } catch(e) { showToast('Failed to load details.', 'error'); closeModal('viewModal'); }
}

// ── EDIT ──────────────────────────────────────────────────────────
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
    body.append('action', 'edit'); body.append('id', id);
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

// ── CONFIRM ───────────────────────────────────────────────────────
async function confirmAction(id, action) {
    const body = new FormData();
    body.append('action', action); body.append('id', id);
    const res    = await fetch('admin-incoming.php', { method:'POST', body });
    const result = await res.json();
    showToast(result.message, result.success ? 'success' : 'error');
    if (result.success) {
        document.getElementById('row-' + id)?.remove();
        const stat = document.getElementById('statPending');
        if (stat) stat.textContent = Math.max(0, parseInt(stat.textContent) - 1);
    }
}

// ── REJECT ────────────────────────────────────────────────────────
function openReject(id) {
    document.getElementById('rejectId').value     = id;
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectModal').classList.add('active');
}
async function submitReject() {
    const id   = document.getElementById('rejectId').value;
    const body = new FormData();
    body.append('action', 'reject'); body.append('id', id);
    body.append('reason', document.getElementById('rejectReason').value);
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

// ── CSV ───────────────────────────────────────────────────────────
function exportCSV() {
    const table = document.getElementById('mainTable');
    const rows  = [...table.querySelectorAll('tr')];
    const csv   = rows.map(row => [...row.querySelectorAll('th,td')].slice(0,-1).map(cell => `"${cell.innerText.replace(/"/g,'""').replace(/\n/g,' ')}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type:'text/csv' }));
    a.download = `incoming_requests_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
}

function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); }));
function showToast(msg, type = 'success') {
    const t = document.createElement('div'); t.className = `toast ${type}`; t.textContent = msg;
    document.body.appendChild(t); setTimeout(() => t.remove(), 3000);
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>