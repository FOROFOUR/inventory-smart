<?php
ob_start();

require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$conn      = getDBConnection();
$userId    = $_SESSION['user_id'];

// Get current warehouse user info
$uStmt = $conn->prepare("SELECT name, role, warehouse_location FROM users WHERE id = ?");
$uStmt->bind_param("i", $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();

if (($user['role'] ?? '') !== 'WAREHOUSE') {
    header("Location: ../landing.php");
    exit();
}

$user_name         = $user['name']               ?? 'Warehouse Staff';
$warehouseLocation = $user['warehouse_location']  ?? '';

// =============================================================================
// POST HANDLERS
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $id     = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'];

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit();
    }

    // ── UPDATE PREP STEP ─────────────────────────────────────────────────────
    if ($action === 'update_step') {
        $step   = max(0, min(4, (int) ($_POST['step'] ?? 0)));
        $labels = ['Not Started', 'Located', 'Checked', 'Packed', 'Ready to Release'];

        // Only allow if this item belongs to their warehouse (from_location)
        $chk = $conn->prepare("SELECT id FROM pull_out_transactions WHERE id = ? AND status = 'CONFIRMED' AND from_location LIKE ?");
        $locLike = $warehouseLocation . '%';
        $chk->bind_param("is", $id, $locLike);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'Not authorized.']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE pull_out_transactions SET prep_step = ? WHERE id = ? AND status = 'CONFIRMED'");
        $stmt->bind_param("ii", $step, $id);
        $stmt->execute();

        $desc = "Pull-out #$id prep step updated to: {$labels[$step]} by $user_name";
        $log  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'PREP_STEP', ?)");
        $log->bind_param("ss", $user_name, $desc);
        $log->execute();

        echo json_encode(['success' => true, 'step' => $step, 'label' => $labels[$step]]);
        exit();
    }

    // ── GET DETAILS ───────────────────────────────────────────────────────────
    if ($action === 'get_details') {
        $locLike = $warehouseLocation . '%';
        $stmt = $conn->prepare("
            SELECT p.*, a.brand, a.model, a.serial_number,
                   c.name AS category_name, sc.name AS subcategory_name
            FROM pull_out_transactions p
            LEFT JOIN assets         a  ON p.asset_id        = a.id
            LEFT JOIN categories     c  ON a.category_id     = c.id
            LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
            WHERE p.id = ? AND p.status = 'CONFIRMED'
              AND p.from_location LIKE ?
        ");
        $stmt->bind_param("is", $id, $locLike);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Not found or not your warehouse item.']);
            exit();
        }

        $row['purpose'] = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $row['purpose'] ?? ''));
        $row['purpose'] = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $row['purpose']));

        $imgStmt = $conn->prepare("SELECT image_path, drive_url FROM asset_images WHERE asset_id = ? ORDER BY id LIMIT 1");
        $imgStmt->bind_param("i", $row['asset_id']);
        $imgStmt->execute();
        $img = $imgStmt->get_result()->fetch_assoc();
        if ($img) {
            if ($img['image_path'] === 'gdrive_folder' && !empty($img['drive_url'])) {
                if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $img['drive_url'], $m) ||
                    preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $img['drive_url'], $m)) {
                    $row['thumbnail']      = "https://drive.google.com/thumbnail?id={$m[1]}&sz=w200";
                    $row['thumbnail_type'] = 'gdrive';
                    $row['thumbnail_url']  = $img['drive_url'];
                } else {
                    $row['thumbnail'] = null; $row['thumbnail_type'] = 'gdrive_folder';
                }
            } else {
                $row['thumbnail']      = '/' . ltrim($img['image_path'], '/');
                $row['thumbnail_type'] = 'image';
            }
        } else {
            $row['thumbnail'] = null; $row['thumbnail_type'] = null;
        }

        echo json_encode(['success' => true, 'data' => $row]);
        exit();
    }

    // ── RELEASE ───────────────────────────────────────────────────────────────
    if ($action === 'release') {
        $released_by  = htmlspecialchars(trim($_POST['released_by']  ?? ''));
        $delivered_by = htmlspecialchars(trim($_POST['delivered_by'] ?? ''));

        if (empty($released_by)) {
            echo json_encode(['success' => false, 'message' => 'Released By is required.']);
            exit();
        }

        // Verify this item belongs to THIS warehouse (from_location)
        $locLike = $warehouseLocation . '%';
        $txnStmt = $conn->prepare("SELECT * FROM pull_out_transactions WHERE id = ? AND status = 'CONFIRMED' AND from_location LIKE ?");
        $txnStmt->bind_param("is", $id, $locLike);
        $txnStmt->execute();
        $txn = $txnStmt->get_result()->fetch_assoc();

        if (!$txn) {
            echo json_encode(['success' => false, 'message' => 'Not authorized or already processed.']);
            exit();
        }

        $rel = $conn->prepare("
            UPDATE pull_out_transactions
            SET status = 'RELEASED', prep_step = 4, released_at = NOW(),
                released_by = ?, delivered_by = ?
            WHERE id = ? AND status = 'CONFIRMED'
        ");
        $rel->bind_param("ssi", $released_by, $delivered_by, $id);
        $rel->execute();

        if ($rel->affected_rows > 0) {
            $desc = "Pull-out #$id RELEASED by $released_by from {$warehouseLocation}"
                  . ($delivered_by ? ", to be delivered by $delivered_by" : '');
            $log  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'RELEASE_PULLOUT', ?)");
            $log->bind_param("ss", $user_name, $desc);
            $log->execute();
            echo json_encode(['success' => true, 'message' => "Request #$id has been marked as Released."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed. Request may have already been processed.']);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit();
}

// =============================================================================
// PAGE DATA — Only items FROM this warehouse
// =============================================================================
$search     = trim($_GET['search']   ?? '');
$dateFilter = trim($_GET['date']     ?? '');
$locFilter  = trim($_GET['location'] ?? '');

$locLike = $warehouseLocation . '%';

// KEY FIX: from_location LIKE this warehouse — these are items they need to prepare & release
$sql    = "SELECT p.*, a.brand, a.model, a.serial_number,
               c.name AS category_name, sc.name AS subcategory_name
           FROM pull_out_transactions p
           LEFT JOIN assets         a  ON p.asset_id        = a.id
           LEFT JOIN categories     c  ON a.category_id     = c.id
           LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
           WHERE p.status = 'CONFIRMED'
             AND p.from_location LIKE ?";
$params = [$locLike];
$types  = 's';

if ($search !== '') {
    $sql    .= " AND (a.brand LIKE ? OR a.model LIKE ? OR p.requested_by LIKE ? OR p.to_location LIKE ?)";
    $l       = "%$search%";
    $params  = array_merge($params, [$l, $l, $l, $l]);
    $types  .= 'ssss';
}
if ($dateFilter !== '') {
    $sql    .= " AND DATE(p.created_at) = ?";
    $params[] = $dateFilter;
    $types  .= 's';
}
$sql .= " ORDER BY p.date_needed ASC, p.created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows   = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;

// Stats — scoped to this warehouse
$stTotStmt = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'CONFIRMED' AND from_location LIKE ?");
$stTotStmt->bind_param("s", $locLike);
$stTotStmt->execute();
$totalConfirmed = (int) $stTotStmt->get_result()->fetch_row()[0];

$readyCount = count(array_filter($rows, fn($r) => ($r['prep_step'] ?? 0) >= 4));
$notStarted = count(array_filter($rows, fn($r) => ($r['prep_step'] ?? 0) === 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preparing — Warehouse</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --amber:  #f39c12;
            --green:  #27ae60;
            --red:    #e74c3c;
            --teal:   #16a085;
            --bg:     #f4f6f9;
            --white:  #fff;
            --text:   #2c3e50;
            --muted:  #7f8c8d;
            --border: #e8ecf0;
            --shadow: 0 2px 12px rgba(0,0,0,.07);
            --radius: 12px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Space Grotesk', sans-serif; }
        body { background: var(--bg); color: var(--text); }

        .main-content {
            position: relative; left: 250px;
            width: calc(100% - 250px); min-height: 100vh;
            background: var(--bg); padding: 2rem;
            transition: all .3s ease;
        }
        .sidebar.close ~ .main-content { left: 88px; width: calc(100% - 88px); }

        .page-header {
            background: linear-gradient(135deg, #f39c12 0%, #d68910 100%);
            border-radius: 16px; padding: 2rem 2rem 1.5rem;
            color: white; margin-bottom: 1.5rem;
            position: relative; overflow: hidden;
        }
        .page-header::before {
            content: ''; position: absolute; top: -50%; right: -5%;
            width: 300px; height: 300px;
            background: rgba(255,255,255,.08); border-radius: 50%;
        }
        .page-header h1 { font-size: 1.6rem; font-weight: 700; position: relative; z-index: 1; display: flex; align-items: center; gap: .6rem; }
        .page-header p  { font-size: .9rem; opacity: .85; margin-top: .25rem; position: relative; z-index: 1; }
        .warehouse-pill {
            display: inline-flex; align-items: center; gap: .4rem;
            background: rgba(255,255,255,.18); border-radius: 20px;
            padding: .3rem .9rem; font-size: .8rem; font-weight: 600;
            margin-top: .6rem; position: relative; z-index: 1;
        }

        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--white); border-radius: var(--radius); padding: 1.25rem 1.5rem; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1rem; }
        .stat-icon { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .stat-icon.amber { background: #fef3cd; color: var(--amber); }
        .stat-icon.green { background: #d5f5e3; color: var(--green); }
        .stat-icon.muted { background: #f0f3f6; color: var(--muted); }
        .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1; }
        .stat-label { font-size: .75rem; color: var(--muted); margin-top: 2px; text-transform: uppercase; letter-spacing: .5px; }

        .filters { background: var(--white); border-radius: var(--radius); padding: 1rem 1.25rem; box-shadow: var(--shadow); display: flex; gap: .75rem; flex-wrap: wrap; align-items: center; margin-bottom: 1.25rem; }
        .filters input { border: 1.5px solid var(--border); border-radius: 8px; padding: .6rem 1rem; font-family: 'Space Grotesk', sans-serif; font-size: .875rem; color: var(--text); outline: none; background: white; transition: border-color .2s; }
        .filters input:focus { border-color: var(--amber); }
        .search-wrap { position: relative; flex: 1; min-width: 220px; }
        .search-wrap input { padding-left: 2.4rem; width: 100%; }
        .search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); }

        .btn { padding: .6rem 1.2rem; border: none; border-radius: 8px; font-family: 'Space Grotesk', sans-serif; font-size: .875rem; font-weight: 600; cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: .4rem; }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .btn-success   { background: var(--green); color: white; }
        .btn-success:hover:not(:disabled) { background: #219a52; }
        .btn-export    { background: var(--teal); color: white; }
        .btn-export:hover { background: #138d75; }
        .btn-secondary { background: #ecf0f1; color: var(--text); }
        .btn-secondary:hover { background: #d5dbdb; }

        .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.25rem; }
        .prep-card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); border-left: 4px solid var(--amber); padding: 1.25rem 1.5rem; transition: transform .2s, box-shadow .2s, border-left-color .3s; }
        .prep-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
        .prep-card.step-ready { border-left-color: var(--green); }
        .prep-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: .9rem; }
        .req-id    { font-size: .78rem; color: var(--muted); font-weight: 600; text-transform: uppercase; margin-bottom: .15rem; display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
        .req-asset { font-size: 1rem; font-weight: 700; color: var(--text); }
        .req-model { font-size: .82rem; color: var(--muted); }

        .step-progress { margin: .85rem 0 1rem; }
        .step-track { display: flex; align-items: center; margin-bottom: .5rem; }
        .step-node { width: 30px; height: 30px; border-radius: 50%; background: #e8ecf0; color: var(--muted); display: flex; align-items: center; justify-content: center; font-size: .78rem; font-weight: 700; flex-shrink: 0; cursor: pointer; transition: all .22s; position: relative; z-index: 1; border: 2px solid #e8ecf0; user-select: none; }
        .step-node.done   { background: var(--amber); color: white; border-color: var(--amber); }
        .step-node.ready  { background: var(--green); color: white; border-color: var(--green); }
        .step-node.active { border-color: var(--amber); background: white; color: var(--amber); box-shadow: 0 0 0 3px rgba(243,156,18,.2); }
        .step-node:hover  { transform: scale(1.12); }
        .step-line { flex: 1; height: 3px; background: #e8ecf0; transition: background .3s; }
        .step-line.done  { background: var(--amber); }
        .step-line.ready { background: var(--green); }
        .step-labels { display: flex; justify-content: space-between; margin-top: .3rem; }
        .step-label { font-size: .68rem; color: var(--muted); text-align: center; flex: 1; line-height: 1.2; font-weight: 500; }
        .step-label.done  { color: var(--amber); font-weight: 600; }
        .step-label.ready { color: var(--green);  font-weight: 600; }
        .step-status-text { text-align: center; font-size: .82rem; font-weight: 600; margin-top: .5rem; padding: .35rem .75rem; border-radius: 6px; background: #fef9ed; color: var(--amber); }
        .step-status-text.ready { background: #d5f5e3; color: #1e8449; }

        .prep-route { display: flex; align-items: center; gap: .6rem; background: #f8f9fa; border-radius: 8px; padding: .7rem 1rem; margin: .75rem 0; }
        .prep-route .loc       { flex: 1; }
        .prep-route .loc-label { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; font-weight: 600; }
        .prep-route .loc-value { font-size: .9rem; font-weight: 600; }
        .prep-route .loc.from .loc-label { color: var(--amber); }
        .prep-route .arrow     { color: var(--amber); font-size: 1.2rem; }

        .prep-meta { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; font-size: .82rem; margin-bottom: 1rem; }
        .prep-meta-item .mlabel { color: var(--muted); font-size: .74rem; text-transform: uppercase; letter-spacing: .4px; }
        .prep-meta-item .mvalue { font-weight: 600; color: var(--text); }

        .prep-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .75rem; padding-top: .75rem; border-top: 1px solid var(--border); }

        .empty-state { text-align: center; padding: 3.5rem; color: var(--muted); grid-column: 1/-1; }
        .empty-state i { font-size: 3.5rem; opacity: .25; display: block; margin-bottom: .75rem; }

        /* ── Modal ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(3px); z-index: 9999; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--white); border-radius: 16px; width: 95%; max-width: 480px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.25); animation: slideUp .25s ease; max-height: 90vh; display: flex; flex-direction: column; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .modal-header h3 { font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
        .modal-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--muted); border-radius: 6px; padding: .2rem .4rem; }
        .modal-close:hover { background: var(--border); }
        .modal-body   { padding: 1.5rem; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; gap: .75rem; justify-content: flex-end; flex-shrink: 0; }

        .release-asset-card { display: flex; align-items: center; gap: 1rem; background: #f8f9fa; border-radius: 10px; padding: 1rem; margin-bottom: 1.25rem; border: 1px solid var(--border); }
        .release-thumb { width: 58px; height: 58px; border-radius: 8px; background: #e8ecf0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--muted); overflow: hidden; }
        .release-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .release-info .asset-name   { font-size: 1rem; font-weight: 700; }
        .release-info .asset-type   { font-size: .82rem; color: var(--muted); }
        .release-info .asset-serial { font-size: .75rem; font-family: monospace; background: #e8ecf0; padding: 1px 6px; border-radius: 4px; display: inline-block; margin-top: 3px; }

        .release-details { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem .75rem; margin-bottom: 1.25rem; }
        .rd-item .rd-label { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; font-weight: 600; }
        .rd-item .rd-value { font-size: .88rem; font-weight: 600; color: var(--text); }

        .divider { border: none; border-top: 1px solid var(--border); margin: 1.25rem 0; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: .8rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: .4rem; }
        .form-group input { width: 100%; border: 1.5px solid var(--border); border-radius: 8px; padding: .7rem 1rem; font-family: 'Space Grotesk', sans-serif; font-size: .9rem; outline: none; transition: border-color .2s; }
        .form-group input:focus { border-color: var(--amber); }

        .toast { position: fixed; top: 20px; right: 20px; padding: .9rem 1.4rem; border-radius: 10px; color: white; font-weight: 600; font-size: .875rem; z-index: 10000; animation: toastIn .3s ease; box-shadow: 0 4px 16px rgba(0,0,0,.2); }
        .toast.success { background: var(--green); }
        .toast.error   { background: var(--red); }
        @keyframes toastIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }
    </style>
</head>
<body>

<?php include 'warehouse_sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1><i class='bx bx-loader-circle'></i> Preparing</h1>
        <p>Items leaving your warehouse — locate, check, pack, then release</p>
        <?php if ($warehouseLocation): ?>
        <div class="warehouse-pill"><i class='bx bxs-warehouse'></i> <?php echo htmlspecialchars($warehouseLocation); ?></div>
        <?php endif; ?>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon amber"><i class='bx bx-loader-circle'></i></div>
            <div>
                <div class="stat-value" id="statConfirmed"><?php echo $totalConfirmed; ?></div>
                <div class="stat-label">Total Preparing</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class='bx bx-check-circle'></i></div>
            <div>
                <div class="stat-value"><?php echo $readyCount; ?></div>
                <div class="stat-label">Ready to Release</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon muted"><i class='bx bx-time'></i></div>
            <div>
                <div class="stat-value"><?php echo $notStarted; ?></div>
                <div class="stat-label">Not Started</div>
            </div>
        </div>
    </div>

    <div class="filters">
        <div class="search-wrap">
            <i class='bx bx-search'></i>
            <input type="text" id="searchInput" placeholder="Search asset, requester, destination..."
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <input type="date" id="dateInput" value="<?php echo htmlspecialchars($dateFilter); ?>">
        <button class="btn btn-export" onclick="exportCSV()"><i class='bx bx-download'></i> Export CSV</button>
    </div>

    <div class="cards-grid" id="cardsGrid">
        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <i class='bx bx-package'></i>
                <p>No items to prepare right now</p>
                <small style="font-size:.82rem;color:#bdc3c7;margin-top:.4rem;display:block;">
                    Items confirmed by admin that are coming <em>from</em> <?php echo htmlspecialchars($warehouseLocation ?: 'your warehouse'); ?> will appear here.
                </small>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $r):
                $from       = $r['from_location'] ?: '—';
                $to         = $r['to_location']   ?: ($r['location_received'] ?: '—');
                $purpose    = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $r['purpose'] ?? '');
                $purpose    = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $purpose)) ?: '—';
                $step       = (int) ($r['prep_step'] ?? 0);
                $isReady    = $step >= 4;
                $stepLabels = ['', 'Located', 'Checked', 'Packed', 'Ready to Release'];
                $titles     = ['', 'Locate asset', 'Check condition', 'Pack & label', 'Ready to release'];

                $urgencyHtml = '';
                if ($r['date_needed']) {
                    $daysLeft = (strtotime($r['date_needed']) - strtotime('today')) / 86400;
                    if ($daysLeft <= 0)
                        $urgencyHtml = '<span style="background:#fdedec;color:#c0392b;padding:.15rem .5rem;border-radius:5px;font-size:.7rem;font-weight:700;">Due Today!</span>';
                    elseif ($daysLeft <= 2)
                        $urgencyHtml = '<span style="background:#fef9ed;color:#d68910;padding:.15rem .5rem;border-radius:5px;font-size:.7rem;font-weight:700;">Due Soon</span>';
                }
            ?>
            <div class="prep-card <?php echo $isReady ? 'step-ready' : ''; ?>" id="card-<?php echo $r['id']; ?>">
                <div class="prep-card-header">
                    <div>
                        <div class="req-id">Request #<?php echo $r['id']; ?> <?php echo $urgencyHtml; ?></div>
                        <div class="req-asset"><?php echo htmlspecialchars($r['brand'] ?? '—'); ?></div>
                        <?php if ($r['model']): ?><div class="req-model"><?php echo htmlspecialchars($r['model']); ?></div><?php endif; ?>
                        <?php if ($r['serial_number']): ?>
                            <div style="font-size:.75rem;background:#f1f2f6;padding:1px 6px;border-radius:4px;display:inline-block;margin-top:3px;font-family:monospace;color:var(--muted);">
                                <?php echo htmlspecialchars($r['serial_number']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span id="badge-<?php echo $r['id']; ?>"
                          style="background:<?php echo $isReady ? '#d5f5e3' : '#fef9ed'; ?>;color:<?php echo $isReady ? '#1e8449' : 'var(--amber)'; ?>;padding:.3rem .7rem;border-radius:6px;font-size:.74rem;font-weight:700;text-transform:uppercase;white-space:nowrap;flex-shrink:0;margin-left:.5rem;">
                        <?php echo $isReady ? '✓ Ready' : 'Preparing'; ?>
                    </span>
                </div>

                <!-- Step Progress -->
                <div class="step-progress">
                    <div class="step-track">
                        <?php for ($i = 1; $i <= 4; $i++):
                            $isDone   = $step >= $i;
                            $isActive = !$isDone && $step === $i - 1;
                            $cls      = $isDone ? ($i === 4 ? 'ready' : 'done') : ($isActive ? 'active' : '');
                        ?>
                            <div class="step-node <?php echo $cls; ?>"
                                 id="node-<?php echo $r['id'] . '-' . $i; ?>"
                                 onclick="setStep(<?php echo $r['id'] . ',' . $i; ?>)"
                                 title="<?php echo $titles[$i]; ?>">
                                <?php echo $isDone ? "<i class='bx bx-check' style='font-size:.9rem;'></i>" : $i; ?>
                            </div>
                            <?php if ($i < 4): ?>
                                <div class="step-line <?php echo $step >= $i ? ($step >= 4 ? 'ready' : 'done') : ''; ?>"
                                     id="line-<?php echo $r['id'] . '-' . $i; ?>"></div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div class="step-labels">
                        <?php foreach (['Locate', 'Check', 'Pack', 'Ready'] as $idx => $lbl): ?>
                            <span class="step-label <?php echo $step >= ($idx + 1) ? ($step >= 4 ? 'ready' : 'done') : ''; ?>">
                                <?php echo $lbl; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="step-status-text <?php echo $isReady ? 'ready' : ''; ?>" id="steptext-<?php echo $r['id']; ?>">
                        <?php if ($isReady): echo '✓ Ready to Release';
                        elseif ($step === 0): echo 'Tap a step to start preparing';
                        else: echo "Step $step / 4 — {$stepLabels[$step]}";
                        endif; ?>
                    </div>
                </div>

                <div class="prep-route">
                    <div class="loc from">
                        <div class="loc-label">From (Your Warehouse)</div>
                        <div class="loc-value"><?php echo htmlspecialchars($from); ?></div>
                    </div>
                    <i class='bx bx-right-arrow-alt arrow'></i>
                    <div class="loc">
                        <div class="loc-label">Destination</div>
                        <div class="loc-value"><?php echo htmlspecialchars($to); ?></div>
                    </div>
                </div>

                <div class="prep-meta">
                    <div class="prep-meta-item">
                        <div class="mlabel">Quantity</div>
                        <div class="mvalue"><?php echo $r['quantity']; ?> pcs</div>
                    </div>
                    <div class="prep-meta-item">
                        <div class="mlabel">Type</div>
                        <div class="mvalue"><?php echo htmlspecialchars($r['subcategory_name'] ?? '—'); ?></div>
                    </div>
                    <div class="prep-meta-item">
                        <div class="mlabel">Requested By</div>
                        <div class="mvalue"><?php echo htmlspecialchars($r['requested_by'] ?? '—'); ?></div>
                    </div>
                    <div class="prep-meta-item">
                        <div class="mlabel">Date Needed</div>
                        <div class="mvalue"><?php echo $r['date_needed'] ? date('M j, Y', strtotime($r['date_needed'])) : '—'; ?></div>
                    </div>
                    <?php if ($purpose !== '—'): ?>
                        <div class="prep-meta-item" style="grid-column:1/-1;">
                            <div class="mlabel">Purpose</div>
                            <div class="mvalue"><?php echo htmlspecialchars($purpose); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="prep-actions">
                    <button class="btn btn-success" id="releaseBtn-<?php echo $r['id']; ?>"
                        onclick="openRelease(<?php echo $r['id']; ?>)"
                        <?php echo !$isReady ? 'disabled title="Complete all steps first"' : ''; ?>>
                        <i class='bx bx-paper-plane'></i> Release
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- ── Release Modal ── -->
<div class="modal-overlay" id="releaseModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class='bx bx-paper-plane' style="color:var(--amber)"></i> Confirm Release</h3>
            <button class="modal-close" onclick="closeModal('releaseModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="releaseId">
            <div class="release-asset-card">
                <div class="release-thumb" id="releaseThumb"><i class='bx bx-image-alt'></i></div>
                <div class="release-info">
                    <div class="asset-name"   id="releaseAssetName">—</div>
                    <div class="asset-type"   id="releaseAssetType"></div>
                    <div class="asset-serial" id="releaseAssetSerial" style="display:none;"></div>
                </div>
            </div>
            <div class="release-details">
                <div class="rd-item"><div class="rd-label">From</div><div class="rd-value" id="releaseFrom">—</div></div>
                <div class="rd-item"><div class="rd-label">To</div><div class="rd-value" id="releaseTo">—</div></div>
                <div class="rd-item"><div class="rd-label">Quantity</div><div class="rd-value" id="releaseQty">—</div></div>
                <div class="rd-item"><div class="rd-label">Requested By</div><div class="rd-value" id="releaseReqBy">—</div></div>
            </div>
            <hr class="divider">
            <div class="form-group">
                <label>Released By <span style="color:var(--red)">*</span></label>
                <input type="text" id="releaseReleasedBy" placeholder="Name of person releasing the asset">
            </div>
            <div class="form-group">
                <label><i class='bx bx-truck' style="vertical-align:middle;margin-right:3px;"></i> Delivered By</label>
                <input type="text" id="releaseDeliveredBy" placeholder="Courier / delivery person name">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('releaseModal')">Cancel</button>
            <button class="btn btn-success" id="confirmReleaseBtn" onclick="submitRelease()">
                <i class='bx bx-paper-plane'></i> Confirm Release
            </button>
        </div>
    </div>
</div>

<script>
const STEP_LABELS = ['Not Started', 'Located', 'Checked', 'Packed', 'Ready to Release'];

function applyFilters() {
    const p = new URLSearchParams({
        search: document.getElementById('searchInput').value,
        date:   document.getElementById('dateInput').value,
    });
    window.location.href = 'warehouse-preparing.php?' + p.toString();
}
document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
document.getElementById('dateInput').addEventListener('change', applyFilters);

async function setStep(id, clickedStep) {
    const cur     = getCurrentStep(id);
    const newStep = cur === clickedStep ? clickedStep - 1 : clickedStep;
    const body    = new FormData();
    body.append('action', 'update_step');
    body.append('id', id);
    body.append('step', newStep);
    try {
        const res    = await fetch('warehouse-preparing.php', { method: 'POST', body });
        const result = await res.json();
        if (!result.success) { showToast(result.message || 'Failed.', 'error'); return; }
        updateCardUI(id, newStep);
        showToast('Step: ' + STEP_LABELS[newStep], 'success');
    } catch (e) { showToast('Failed to save.', 'error'); }
}

function getCurrentStep(id) {
    for (let i = 4; i >= 1; i--) {
        const n = document.getElementById('node-' + id + '-' + i);
        if (n && (n.classList.contains('done') || n.classList.contains('ready'))) return i;
    }
    return 0;
}

function updateCardUI(id, step) {
    const isReady = step >= 4;
    for (let i = 1; i <= 4; i++) {
        const node = document.getElementById('node-' + id + '-' + i);
        if (!node) continue;
        node.className = 'step-node';
        if (step >= i) {
            node.classList.add(i === 4 ? 'ready' : 'done');
            node.innerHTML = "<i class='bx bx-check' style='font-size:.9rem;'></i>";
        } else if (step === i - 1) {
            node.classList.add('active');
            node.textContent = i;
        } else {
            node.textContent = i;
        }
    }
    for (let i = 1; i <= 3; i++) {
        const l = document.getElementById('line-' + id + '-' + i);
        if (!l) continue;
        l.className = 'step-line';
        if (step >= i) l.classList.add(isReady ? 'ready' : 'done');
    }
    document.querySelectorAll('#card-' + id + ' .step-label').forEach((lbl, idx) => {
        lbl.className = 'step-label';
        if (step >= idx + 1) lbl.classList.add(isReady ? 'ready' : 'done');
    });
    const txt = document.getElementById('steptext-' + id);
    if (txt) {
        txt.className   = 'step-status-text' + (isReady ? ' ready' : '');
        txt.textContent = isReady ? '✓ Ready to Release'
            : step === 0 ? 'Tap a step to start preparing'
            : `Step ${step} / 4 — ${STEP_LABELS[step]}`;
    }
    const badge = document.getElementById('badge-' + id);
    if (badge) {
        badge.style.background = isReady ? '#d5f5e3' : '#fef9ed';
        badge.style.color      = isReady ? '#1e8449' : 'var(--amber)';
        badge.textContent      = isReady ? '✓ Ready' : 'Preparing';
    }
    const card = document.getElementById('card-' + id);
    if (card) card.classList.toggle('step-ready', isReady);
    const btn = document.getElementById('releaseBtn-' + id);
    if (btn) { btn.disabled = !isReady; btn.title = isReady ? '' : 'Complete all steps first'; }
}

async function openRelease(id) {
    document.getElementById('releaseId').value              = id;
    document.getElementById('releaseReleasedBy').value      = '';
    document.getElementById('releaseDeliveredBy').value     = '';
    document.getElementById('releaseAssetName').textContent  = 'Loading...';
    document.getElementById('releaseAssetType').textContent  = '';
    document.getElementById('releaseAssetSerial').style.display = 'none';
    document.getElementById('releaseFrom').textContent      = '—';
    document.getElementById('releaseTo').textContent        = '—';
    document.getElementById('releaseQty').textContent       = '—';
    document.getElementById('releaseReqBy').textContent     = '—';
    document.getElementById('releaseThumb').innerHTML       = "<i class='bx bx-image-alt'></i>";
    document.getElementById('releaseModal').classList.add('active');

    try {
        const body = new FormData();
        body.append('action', 'get_details');
        body.append('id', id);
        const res    = await fetch('warehouse-preparing.php', { method: 'POST', body });
        const result = await res.json();
        if (!result.success) { showToast('Could not load details.', 'error'); return; }

        const d = result.data;
        document.getElementById('releaseAssetName').textContent = `${d.brand || ''} ${d.model || ''}`.trim() || '—';
        document.getElementById('releaseAssetType').textContent = d.subcategory_name || '';
        if (d.serial_number) {
            const s = document.getElementById('releaseAssetSerial');
            s.textContent = d.serial_number; s.style.display = 'inline-block';
        }
        if (d.thumbnail && d.thumbnail_type === 'gdrive') {
            document.getElementById('releaseThumb').innerHTML =
                `<img src="${d.thumbnail}" style="width:100%;height:100%;object-fit:cover;"
                 onerror="this.parentElement.innerHTML='<i class=\\'bx bxl-google\\'></i>'">`;
        } else if (d.thumbnail) {
            document.getElementById('releaseThumb').innerHTML =
                `<img src="${d.thumbnail}" onerror="this.parentElement.innerHTML='<i class=\\'bx bx-image-alt\\'></i>'">`;
        }
        document.getElementById('releaseFrom').textContent  = d.from_location || '—';
        document.getElementById('releaseTo').textContent    = d.to_location   || '—';
        document.getElementById('releaseQty').textContent   = `${d.quantity   || '—'} pcs`;
        document.getElementById('releaseReqBy').textContent = d.requested_by  || '—';
        setTimeout(() => document.getElementById('releaseReleasedBy').focus(), 250);
    } catch (e) { showToast('Failed to load details.', 'error'); }
}

async function submitRelease() {
    const id = document.getElementById('releaseId').value;
    const rb = document.getElementById('releaseReleasedBy').value.trim();
    if (!rb) { showToast('Please enter who is releasing the asset.', 'error'); return; }

    const btn = document.getElementById('confirmReleaseBtn');
    btn.disabled = true;
    btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Releasing...";

    const body = new FormData();
    body.append('action',       'release');
    body.append('id',           id);
    body.append('released_by',  rb);
    body.append('delivered_by', document.getElementById('releaseDeliveredBy').value.trim());

    const res    = await fetch('warehouse-preparing.php', { method: 'POST', body });
    const result = await res.json();

    btn.disabled = false;
    btn.innerHTML = "<i class='bx bx-paper-plane'></i> Confirm Release";

    showToast(result.message, result.success ? 'success' : 'error');
    if (result.success) {
        closeModal('releaseModal');
        const card = document.getElementById('card-' + id);
        if (card) {
            card.style.transition = 'opacity .4s, transform .4s';
            card.style.opacity    = '0';
            card.style.transform  = 'scale(.95)';
            setTimeout(() => {
                card.remove();
                updateStat(-1);
                if (!document.querySelector('.prep-card')) {
                    document.getElementById('cardsGrid').innerHTML = `
                        <div class="empty-state">
                            <i class='bx bx-check-circle' style="color:var(--green);opacity:.5"></i>
                            <p>All items released!</p>
                            <small>Nothing left to prepare from your warehouse.</small>
                        </div>`;
                }
            }, 400);
        }
    }
}

function exportCSV() {
    const headers = ['ID', 'Brand', 'Model', 'Serial', 'Type', 'Qty', 'From', 'To', 'Purpose', 'Requested By', 'Date Needed', 'Prep Step'];
    const data = <?php
        $out = [];
        foreach ($rows as $r) {
            $from = $r['from_location'] ?: '—';
            $to   = $r['to_location']   ?: ($r['location_received'] ?: '—');
            $p    = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $r['purpose'] ?? '');
            $p    = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $p));
            $sl   = ['Not Started', 'Located', 'Checked', 'Packed', 'Ready to Release'];
            $out[] = [
                $r['id'], $r['brand'] ?? '', $r['model'] ?? '', $r['serial_number'] ?? '',
                $r['subcategory_name'] ?? '', $r['quantity'], $from, $to, $p,
                $r['requested_by'] ?? '', $r['date_needed'] ?? '', $sl[$r['prep_step'] ?? 0] ?? ''
            ];
        }
        echo json_encode($out);
    ?>;
    const csv = [headers, ...data].map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
    const a   = document.createElement('a');
    a.href    = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    a.download = 'preparing_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
}

function updateStat(delta) {
    const s = document.getElementById('statConfirmed');
    if (s) s.textContent = Math.max(0, parseInt(s.textContent) + delta);
}
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); });
});
function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}
</script>

</body>
</html>
<?php ob_end_flush(); ?>    