<?php
ob_start();

require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../landing.php");
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

$user_name         = $user['name']              ?? 'Warehouse Staff';
$warehouseLocation = $user['warehouse_location'] ?? '';

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

    // ── GET DETAILS ───────────────────────────────────────────────────────────
    if ($action === 'get_details') {
        $stmt = $conn->prepare("
            SELECT p.*, a.brand, a.model, a.serial_number, a.condition,
                   a.location AS asset_location, a.sub_location AS asset_sub_location,
                   c.name AS category_name, sc.name AS subcategory_name
            FROM pull_out_transactions p
            LEFT JOIN assets         a  ON p.asset_id        = a.id
            LEFT JOIN categories     c  ON a.category_id     = c.id
            LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
            WHERE p.id = ? AND p.status = 'RELEASED'
              AND (p.to_location LIKE ? OR p.to_location = ?)
        ");
        $locLike = $warehouseLocation . '%';
        $stmt->bind_param("iss", $id, $locLike, $warehouseLocation);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Not found or not assigned to your warehouse.']);
            exit();
        }

        $row['purpose'] = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $row['purpose'] ?? ''));
        $row['purpose'] = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $row['purpose']));

        $toParts = explode(' / ', $row['to_location'] ?? '', 2);
        $row['to_main_location'] = trim($toParts[0]);
        $row['to_sub_location']  = isset($toParts[1]) ? trim($toParts[1]) : '';

        // Thumbnail — gdrive-aware
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
                    $row['thumbnail'] = null; $row['thumbnail_type'] = 'gdrive_folder'; $row['thumbnail_url'] = $img['drive_url'];
                }
            } else {
                $row['thumbnail']      = '/' . ltrim($img['image_path'], '/');
                $row['thumbnail_type'] = 'image';
                $row['thumbnail_url']  = null;
            }
        } else {
            $row['thumbnail'] = null; $row['thumbnail_type'] = null; $row['thumbnail_url'] = null;
        }

        echo json_encode(['success' => true, 'data' => $row]);
        exit();
    }

    // ── MARK AS RECEIVED ──────────────────────────────────────────────────────
    if ($action === 'receive') {
        $received_by  = htmlspecialchars(trim($_POST['received_by']     ?? ''));
        $to_sub_input = htmlspecialchars(trim($_POST['to_sub_location']  ?? ''));

        if (empty($received_by)) {
            echo json_encode(['success' => false, 'message' => 'Received By is required.']);
            exit();
        }

        // Verify it belongs to this warehouse
        $txnStmt = $conn->prepare("
            SELECT id, asset_id, quantity, from_location, to_location
            FROM pull_out_transactions
            WHERE id = ? AND status = 'RELEASED'
              AND (to_location LIKE ? OR to_location = ?)
        ");
        $locLike = $warehouseLocation . '%';
        $txnStmt->bind_param("iss", $id, $locLike, $warehouseLocation);
        $txnStmt->execute();
        $txn = $txnStmt->get_result()->fetch_assoc();

        if (!$txn) {
            echo json_encode(['success' => false, 'message' => 'Already processed or not assigned to your warehouse.']);
            exit();
        }

        $assetId    = $txn['asset_id'];
        $quantity   = (int) $txn['quantity'];
        $toLocation = $txn['to_location'] ?? '';

        $toParts        = explode(' / ', $toLocation, 2);
        $toMainLocation = trim($toParts[0]);
        $toSubLocation  = $to_sub_input !== '' ? $to_sub_input : (isset($toParts[1]) ? trim($toParts[1]) : '');

        // Update asset sub_location
        // Update BOTH main location AND sub_location so inventory reflects the new location
        $updAsset = $conn->prepare("UPDATE assets SET location = ?, sub_location = ?, updated_at = NOW() WHERE id = ?");
        $updAsset->bind_param("ssi", $toMainLocation, $toSubLocation, $assetId);
        $updAsset->execute();

        // Update to_location with sub
        $finalToLocation = $toSubLocation !== '' ? $toMainLocation . ' / ' . $toSubLocation : $toMainLocation;
        $updTxnLoc = $conn->prepare("UPDATE pull_out_transactions SET to_location = ? WHERE id = ?");
        $updTxnLoc->bind_param("si", $finalToLocation, $id);
        $updTxnLoc->execute();

        // Mark received
        $stmt = $conn->prepare("
            UPDATE pull_out_transactions
            SET status = 'RECEIVED', received_at = NOW(), received_by = ?
            WHERE id = ? AND status = 'RELEASED'
        ");
        $stmt->bind_param("si", $received_by, $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Already processed.']);
            exit();
        }

        $desc = "Pull-out #$id RECEIVED by $received_by at {$toMainLocation}" . ($toSubLocation ? " / $toSubLocation" : '') . ". Asset #{$assetId} x{$quantity}.";
        $log  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'RECEIVE_PULLOUT', ?)");
        $log->bind_param("ss", $user_name, $desc);
        $log->execute();

        echo json_encode(['success' => true, 'message' => "Request #$id marked as Received."]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit();
}

// =============================================================================
// PAGE DATA
// =============================================================================
$search     = trim($_GET['search'] ?? '');
$dateFilter = trim($_GET['date']   ?? '');

// Base query — only items heading TO this warehouse
$sql    = "
    SELECT p.*, a.brand, a.model, a.serial_number,
           c.name AS category_name, sc.name AS subcategory_name
    FROM pull_out_transactions p
    LEFT JOIN assets         a  ON p.asset_id        = a.id
    LEFT JOIN categories     c  ON a.category_id     = c.id
    LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
    WHERE p.status = 'RELEASED'
      AND (p.to_location LIKE ? OR p.to_location = ?)
";
$locLike = $warehouseLocation . '%';
$params  = [$locLike, $warehouseLocation];
$types   = 'ss';

if ($search !== '') {
    $sql    .= " AND (a.brand LIKE ? OR a.model LIKE ? OR p.requested_by LIKE ? OR p.from_location LIKE ?)";
    $l       = "%$search%";
    $params  = array_merge($params, [$l, $l, $l, $l]);
    $types  .= 'ssss';
}
if ($dateFilter !== '') {
    $sql    .= " AND DATE(p.released_at) = ?";
    $params[] = $dateFilter;
    $types   .= 's';
}
$sql .= " ORDER BY p.released_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows   = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;

// Stats
$stRelStmt = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RELEASED' AND (to_location LIKE ? OR to_location = ?)");
$stRelStmt->bind_param("ss", $locLike, $warehouseLocation);
$stRelStmt->execute();
$totalReleased = (int) $stRelStmt->get_result()->fetch_row()[0];

$stRecStmt = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RECEIVED' AND (to_location LIKE ? OR to_location = ?)");
$stRecStmt->bind_param("ss", $locLike, $warehouseLocation);
$stRecStmt->execute();
$totalReceived = (int) $stRecStmt->get_result()->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receiving — Warehouse</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --teal: #16a085; --green: #27ae60; --red: #e74c3c; --amber: #f39c12;
            --purple: #8e44ad; --blue: #2980b9; --bg: #f4f6f9; --white: #fff;
            --text: #2c3e50; --muted: #7f8c8d; --border: #e8ecf0;
            --shadow: 0 2px 12px rgba(0,0,0,.07); --radius: 12px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Space Grotesk', sans-serif; }
        body { background: var(--bg); color: var(--text); }

        /* ── Layout — matches warehouse sidebar width ── */
        .content { margin-left: 88px; padding: 2rem; transition: margin-left .3s; min-height: 100vh; }
        .sidebar:not(.close) ~ .content { margin-left: 250px; }

        /* ── Page header ── */
        .page-header {
            background: linear-gradient(135deg, #16a085 0%, #0e6655 100%);
            border-radius: 16px; padding: 2rem 2rem 1.5rem;
            color: white; margin-bottom: 1.5rem; position: relative; overflow: hidden;
        }
        .page-header::before {
            content: ''; position: absolute; top: -50%; right: -5%;
            width: 300px; height: 300px; background: rgba(255,255,255,.08); border-radius: 50%;
        }
        .page-header h1 { font-size: 1.6rem; font-weight: 700; position: relative; z-index: 1; display: flex; align-items: center; gap: .6rem; }
        .page-header p  { font-size: .9rem; opacity: .85; margin-top: .25rem; position: relative; z-index: 1; }
        .warehouse-pill {
            display: inline-flex; align-items: center; gap: .4rem;
            background: rgba(255,255,255,.18); border-radius: 20px;
            padding: .3rem .9rem; font-size: .8rem; font-weight: 600;
            margin-top: .6rem; position: relative; z-index: 1;
        }

        /* ── Stats ── */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--white); border-radius: var(--radius); padding: 1.25rem 1.5rem; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1rem; }
        .stat-icon { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .stat-icon.teal  { background: #d1f2eb; color: var(--teal); }
        .stat-icon.green { background: #d5f5e3; color: var(--green); }
        .stat-icon.amber { background: #fef3cd; color: var(--amber); }
        .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1; }
        .stat-label { font-size: .75rem; color: var(--muted); margin-top: 2px; text-transform: uppercase; letter-spacing: .5px; }

        /* ── Filters ── */
        .filters { background: var(--white); border-radius: var(--radius); padding: 1rem 1.25rem; box-shadow: var(--shadow); display: flex; gap: .75rem; flex-wrap: wrap; align-items: center; margin-bottom: 1.25rem; }
        .filters input { border: 1.5px solid var(--border); border-radius: 8px; padding: .6rem 1rem; font-family: 'Space Grotesk', sans-serif; font-size: .875rem; color: var(--text); outline: none; background: white; transition: border-color .2s; }
        .filters input:focus { border-color: var(--teal); }
        .search-wrap { position: relative; flex: 1; min-width: 220px; }
        .search-wrap input { padding-left: 2.4rem; width: 100%; }
        .search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); }
        .btn { padding: .6rem 1.2rem; border: none; border-radius: 8px; font-family: 'Space Grotesk', sans-serif; font-size: .875rem; font-weight: 600; cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: .4rem; }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .btn-teal      { background: var(--teal); color: white; }
        .btn-teal:hover:not(:disabled) { background: #138d75; }
        .btn-secondary { background: #ecf0f1; color: var(--text); }
        .btn-secondary:hover { background: #d5dbdb; }

        /* ── Cards ── */
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.25rem; }
        .recv-card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); border-left: 4px solid var(--teal); padding: 1.25rem 1.5rem; transition: transform .2s, box-shadow .2s; }
        .recv-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: .9rem; }
        .req-id    { font-size: .78rem; color: var(--muted); font-weight: 600; text-transform: uppercase; }
        .req-asset { font-size: 1rem; font-weight: 700; color: var(--text); }
        .req-model { font-size: .82rem; color: var(--muted); }
        .req-serial { font-size: .75rem; background: #f1f2f6; padding: 1px 6px; border-radius: 4px; display: inline-block; margin-top: 3px; font-family: monospace; color: var(--muted); }
        .status-badge { padding: .3rem .7rem; border-radius: 6px; font-size: .74rem; font-weight: 700; text-transform: uppercase; white-space: nowrap; background: #d1f2eb; color: var(--teal); display: flex; align-items: center; gap: .3rem; }
        .personnel-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .85rem; }
        .chip { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; padding: .3rem .7rem; border-radius: 6px; white-space: nowrap; }
        .chip-purple { background: #f0e6f6; color: var(--purple); }
        .chip-blue   { background: #d6eaf8; color: var(--blue); }
        .released-time { font-size: .78rem; color: var(--muted); display: flex; align-items: center; gap: .3rem; margin-bottom: .75rem; }
        .route-box { display: flex; align-items: center; gap: .6rem; background: #f8f9fa; border-radius: 8px; padding: .7rem 1rem; margin: .75rem 0; }
        .route-box .loc { flex: 1; }
        .route-box .loc-label { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; font-weight: 600; }
        .route-box .loc-value { font-size: .9rem; font-weight: 600; }
        .route-box .arrow { color: var(--teal); font-size: 1.2rem; flex-shrink: 0; }
        .route-box .loc.destination .loc-label { color: var(--teal); }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; font-size: .82rem; margin-bottom: 1rem; }
        .meta-item .mlabel { color: var(--muted); font-size: .74rem; text-transform: uppercase; letter-spacing: .4px; }
        .meta-item .mvalue { font-weight: 600; color: var(--text); }
        .card-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .75rem; padding-top: .75rem; border-top: 1px solid var(--border); }
        .empty-state { text-align: center; padding: 3.5rem; color: var(--muted); grid-column: 1/-1; }
        .empty-state i { font-size: 3.5rem; opacity: .25; display: block; margin-bottom: .75rem; }

        /* ── Modal ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(3px); z-index: 9999; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--white); border-radius: 16px; width: 95%; max-width: 540px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.25); animation: slideUp .25s ease; max-height: 90vh; display: flex; flex-direction: column; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .modal-header h3 { font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
        .modal-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--muted); border-radius: 6px; padding: .2rem .4rem; }
        .modal-close:hover { background: var(--border); }
        .modal-body { padding: 1.5rem; overflow-y: auto; }
        .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; gap: .75rem; justify-content: flex-end; flex-shrink: 0; }
        .modal-asset-card { display: flex; align-items: center; gap: 1rem; background: #f8f9fa; border-radius: 10px; padding: 1rem; margin-bottom: 1.25rem; border: 1px solid var(--border); }
        .modal-thumb { width: 60px; height: 60px; border-radius: 8px; background: #e8ecf0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--muted); overflow: hidden; }
        .modal-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .modal-asset-info .a-name   { font-size: 1rem; font-weight: 700; }
        .modal-asset-info .a-type   { font-size: .82rem; color: var(--muted); }
        .modal-asset-info .a-serial { font-size: .75rem; font-family: monospace; background: #e8ecf0; padding: 1px 6px; border-radius: 4px; display: inline-block; margin-top: 3px; }
        .modal-details { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem .75rem; margin-bottom: 1.25rem; }
        .md-item .md-label { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; font-weight: 600; }
        .md-item .md-value { font-size: .88rem; font-weight: 600; color: var(--text); }
        .md-item.full { grid-column: 1/-1; }
        .modal-personnel-row { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.25rem; }
        .modal-personnel-chip { display: flex; align-items: center; gap: .4rem; border-radius: 8px; padding: .55rem 1rem; font-size: .84rem; }
        .modal-personnel-chip i { font-size: 1.05rem; }
        .modal-personnel-chip span { color: var(--muted); margin-right: .15rem; }
        .modal-personnel-chip.purple { background: #f0e6f6; color: var(--purple); }
        .modal-personnel-chip.blue   { background: #d6eaf8; color: var(--blue); }
        .divider { border: none; border-top: 1px solid var(--border); margin: 1.25rem 0; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: .8rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: .4rem; }
        .form-group input { width: 100%; border: 1.5px solid var(--border); border-radius: 8px; padding: .7rem 1rem; font-family: 'Space Grotesk', sans-serif; font-size: .9rem; outline: none; transition: border-color .2s; }
        .form-group input:focus { border-color: var(--teal); }
        .form-group small { font-size: .78rem; color: var(--muted); margin-top: .3rem; display: block; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }

        /* ── Toast ── */
        .toast { position: fixed; top: 20px; right: 20px; padding: .9rem 1.4rem; border-radius: 10px; color: white; font-weight: 600; font-size: .875rem; z-index: 10000; animation: toastIn .3s ease; box-shadow: 0 4px 16px rgba(0,0,0,.2); }
        .toast.success { background: var(--green); }
        .toast.error   { background: var(--red); }
        @keyframes toastIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }
    </style>
</head>
<body>
    <?php include 'warehouse_sidebar.php'; ?>

    <div class="content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class='bx bx-package'></i> Receiving</h1>
            <p>Confirm delivery of assets coming into your warehouse</p>
            <?php if ($warehouseLocation): ?>
            <div class="warehouse-pill"><i class='bx bxs-warehouse'></i> <?php echo htmlspecialchars($warehouseLocation); ?></div>
            <?php endif; ?>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon teal"><i class='bx bx-package'></i></div>
                <div>
                    <div class="stat-value" id="statReleased"><?php echo $totalReleased; ?></div>
                    <div class="stat-label">Awaiting Receipt</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class='bx bx-check-double'></i></div>
                <div>
                    <div class="stat-value"><?php echo $totalReceived; ?></div>
                    <div class="stat-label">Total Received</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber"><i class='bx bx-filter'></i></div>
                <div>
                    <div class="stat-value"><?php echo count($rows); ?></div>
                    <div class="stat-label">Showing</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <div class="search-wrap">
                <i class='bx bx-search'></i>
                <input type="text" id="searchInput" placeholder="Search asset, requester, origin..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <input type="date" id="dateInput" value="<?php echo htmlspecialchars($dateFilter); ?>" title="Filter by released date">
        </div>

        <!-- Cards -->
        <div class="cards-grid" id="cardsGrid">
            <?php if (empty($rows)): ?>
                <div class="empty-state">
                    <i class='bx bx-package'></i>
                    <p>No incoming assets to receive</p>
                    <small>Items released and headed to <?php echo htmlspecialchars($warehouseLocation ?: 'your warehouse'); ?> will appear here.</small>
                </div>
            <?php else: ?>
                <?php foreach ($rows as $r):
                    $from       = $r['from_location'] ?: '—';
                    $to         = $r['to_location']   ?: '—';
                    $purpose    = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $r['purpose'] ?? '');
                    $purpose    = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $purpose)) ?: '—';
                    $releasedAt = $r['released_at'] ? date('M j, Y g:i A', strtotime($r['released_at'])) : '—';
                ?>
                <div class="recv-card" id="card-<?php echo $r['id']; ?>">
                    <div class="card-header">
                        <div>
                            <div class="req-id">Request #<?php echo $r['id']; ?></div>
                            <div class="req-asset"><?php echo htmlspecialchars($r['brand'] ?? '—'); ?></div>
                            <?php if ($r['model']): ?><div class="req-model"><?php echo htmlspecialchars($r['model']); ?></div><?php endif; ?>
                            <?php if ($r['serial_number']): ?><div class="req-serial"><?php echo htmlspecialchars($r['serial_number']); ?></div><?php endif; ?>
                        </div>
                        <span class="status-badge"><i class='bx bx-paper-plane'></i> Released</span>
                    </div>

                    <?php if ($r['released_by'] || !empty($r['delivered_by'])): ?>
                    <div class="personnel-chips">
                        <?php if ($r['released_by']): ?><span class="chip chip-purple"><i class='bx bx-user-check'></i> Released by: <?php echo htmlspecialchars($r['released_by']); ?></span><?php endif; ?>
                        <?php if (!empty($r['delivered_by'])): ?><span class="chip chip-blue"><i class='bx bx-car'></i> Delivered by: <?php echo htmlspecialchars($r['delivered_by']); ?></span><?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="released-time"><i class='bx bx-time-five'></i> Released: <?php echo $releasedAt; ?></div>

                    <div class="route-box">
                        <div class="loc">
                            <div class="loc-label">From</div>
                            <div class="loc-value"><?php echo htmlspecialchars($from); ?></div>
                        </div>
                        <i class='bx bx-right-arrow-alt arrow'></i>
                        <div class="loc destination">
                            <div class="loc-label">Your Warehouse</div>
                            <div class="loc-value"><?php echo htmlspecialchars($to); ?></div>
                        </div>
                    </div>

                    <div class="meta-grid">
                        <div class="meta-item"><div class="mlabel">Quantity</div><div class="mvalue"><?php echo $r['quantity']; ?> pcs</div></div>
                        <div class="meta-item"><div class="mlabel">Type</div><div class="mvalue"><?php echo htmlspecialchars($r['subcategory_name'] ?? '—'); ?></div></div>
                        <div class="meta-item"><div class="mlabel">Requested By</div><div class="mvalue"><?php echo htmlspecialchars($r['requested_by'] ?? '—'); ?></div></div>
                        <div class="meta-item"><div class="mlabel">Date Needed</div><div class="mvalue"><?php echo $r['date_needed'] ? date('M j, Y', strtotime($r['date_needed'])) : '—'; ?></div></div>
                        <?php if ($purpose !== '—'): ?>
                        <div class="meta-item" style="grid-column:1/-1;">
                            <div class="mlabel">Purpose</div>
                            <div class="mvalue"><?php echo htmlspecialchars($purpose); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-actions">
                        <button class="btn btn-teal" onclick="openReceive(<?php echo $r['id']; ?>)">
                            <i class='bx bx-check-double'></i> Mark as Received
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Receive Modal -->
    <div class="modal-overlay" id="receiveModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class='bx bx-check-double' style="color:var(--teal)"></i> Confirm Receipt</h3>
                <button class="modal-close" onclick="closeModal('receiveModal')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="receiveId">

                <div class="modal-asset-card">
                    <div class="modal-thumb" id="receiveThumb"><i class='bx bx-image-alt'></i></div>
                    <div class="modal-asset-info">
                        <div class="a-name"   id="receiveAssetName">—</div>
                        <div class="a-type"   id="receiveAssetType"></div>
                        <div class="a-serial" id="receiveAssetSerial" style="display:none;"></div>
                    </div>
                </div>

                <div class="modal-details">
                    <div class="md-item"><div class="md-label">From</div><div class="md-value" id="receiveFrom">—</div></div>
                    <div class="md-item"><div class="md-label">Destination</div><div class="md-value" id="receiveTo">—</div></div>
                    <div class="md-item"><div class="md-label">Quantity</div><div class="md-value" id="receiveQty">—</div></div>
                    <div class="md-item"><div class="md-label">Requested By</div><div class="md-value" id="receiveReqBy">—</div></div>
                    <div class="md-item"><div class="md-label">Date Needed</div><div class="md-value" id="receiveDateNeeded">—</div></div>
                    <div class="md-item full"><div class="md-label">Purpose</div><div class="md-value" id="receivePurpose">—</div></div>
                </div>

                <div class="modal-personnel-row" id="receivePersonnelRow" style="display:none;">
                    <div class="modal-personnel-chip purple" id="receiveReleasedByChip" style="display:none;"><i class='bx bx-user-check'></i><span>Released by</span><strong id="receiveReleasedBy">—</strong></div>
                    <div class="modal-personnel-chip blue"   id="receiveDeliveredByChip" style="display:none;"><i class='bx bx-car'></i><span>Delivered by</span><strong id="receiveDeliveredBy">—</strong></div>
                </div>

                <hr class="divider">

                <div class="form-row">
                    <div class="form-group">
                        <label>Exact Sub-Location</label>
                        <input type="text" id="receiveSubLocation" placeholder="e.g. Rack B, Shelf 3...">
                        <small>Exact spot inside <strong id="receiveToHint">warehouse</strong></small>
                    </div>
                    <div class="form-group">
                        <label>Received By <span style="color:var(--red)">*</span></label>
                        <input type="text" id="receiveReceivedBy" placeholder="Your name">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('receiveModal')">Cancel</button>
                <button class="btn btn-teal" id="confirmReceiveBtn" onclick="submitReceive()">
                    <i class='bx bx-check-double'></i> Confirm Receipt
                </button>
            </div>
        </div>
    </div>

    <script>
        // ── Filters ──────────────────────────────────────────────────────────
        function applyFilters() {
            const p = new URLSearchParams({
                search: document.getElementById('searchInput').value,
                date:   document.getElementById('dateInput').value
            });
            window.location.href = 'warehouse-receiving.php?' + p.toString();
        }
        document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
        document.getElementById('dateInput').addEventListener('change', applyFilters);

        // ── Open modal ───────────────────────────────────────────────────────
        async function openReceive(id) {
            // Reset modal
            document.getElementById('receiveId').value = id;
            document.getElementById('receiveReceivedBy').value  = '';
            document.getElementById('receiveSubLocation').value = '';
            document.getElementById('receiveAssetName').textContent    = 'Loading...';
            document.getElementById('receiveAssetType').textContent    = '';
            document.getElementById('receiveAssetSerial').style.display = 'none';
            document.getElementById('receiveFrom').textContent         = '—';
            document.getElementById('receiveTo').textContent           = '—';
            document.getElementById('receiveQty').textContent          = '—';
            document.getElementById('receiveReqBy').textContent        = '—';
            document.getElementById('receiveDateNeeded').textContent   = '—';
            document.getElementById('receivePurpose').textContent      = '—';
            document.getElementById('receiveToHint').textContent       = 'warehouse';
            document.getElementById('receivePersonnelRow').style.display    = 'none';
            document.getElementById('receiveReleasedByChip').style.display  = 'none';
            document.getElementById('receiveDeliveredByChip').style.display = 'none';
            document.getElementById('receiveThumb').innerHTML = "<i class='bx bx-image-alt'></i>";

            document.getElementById('receiveModal').classList.add('active');

            try {
                const body = new FormData();
                body.append('action', 'get_details');
                body.append('id', id);
                const res    = await fetch('warehouse-receiving.php', { method: 'POST', body });
                const result = await res.json();
                if (!result.success) { showToast(result.message || 'Could not load details.', 'error'); return; }

                const d = result.data;
                document.getElementById('receiveAssetName').textContent = `${d.brand || ''} ${d.model || ''}`.trim() || '—';
                document.getElementById('receiveAssetType').textContent  = d.subcategory_name || '';

                if (d.serial_number) {
                    const s = document.getElementById('receiveAssetSerial');
                    s.textContent = d.serial_number;
                    s.style.display = 'inline-block';
                }

                // Thumbnail
                const thumb = document.getElementById('receiveThumb');
                if (d.thumbnail && d.thumbnail_type === 'gdrive') {
                    thumb.innerHTML = `<img src="${d.thumbnail}" style="width:100%;height:100%;object-fit:cover;cursor:pointer;"
                        onerror="this.parentElement.innerHTML='<i class=\\'bx bxl-google\\'></i>'"
                        onclick="window.open('${d.thumbnail_url || d.thumbnail}','_blank')">`;
                } else if (d.thumbnail) {
                    thumb.innerHTML = `<img src="${d.thumbnail}" onerror="this.parentElement.innerHTML='<i class=\\'bx bx-image-alt\\'></i>'">`;
                }

                document.getElementById('receiveFrom').textContent = d.from_location || '—';
                document.getElementById('receiveTo').textContent   = d.to_main_location || d.to_location || '—';
                document.getElementById('receiveQty').textContent  = `${d.quantity || '—'} pcs`;
                document.getElementById('receiveReqBy').textContent      = d.requested_by || '—';
                document.getElementById('receiveDateNeeded').textContent = d.date_needed
                    ? new Date(d.date_needed).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
                document.getElementById('receivePurpose').textContent = d.purpose || '—';
                document.getElementById('receiveToHint').textContent  = d.to_main_location || 'warehouse';

                let showPersonnel = false;
                if (d.released_by)  { document.getElementById('receiveReleasedBy').textContent  = d.released_by;  document.getElementById('receiveReleasedByChip').style.display  = 'flex'; showPersonnel = true; }
                if (d.delivered_by) { document.getElementById('receiveDeliveredBy').textContent = d.delivered_by; document.getElementById('receiveDeliveredByChip').style.display = 'flex'; showPersonnel = true; }
                if (showPersonnel)    document.getElementById('receivePersonnelRow').style.display = 'flex';

                if (d.to_sub_location) document.getElementById('receiveSubLocation').value = d.to_sub_location;

            } catch (e) { showToast('Failed to load details.', 'error'); }
        }

        // ── Submit receive ───────────────────────────────────────────────────
        async function submitReceive() {
            const id     = document.getElementById('receiveId').value;
            const rb     = document.getElementById('receiveReceivedBy').value.trim();
            const subLoc = document.getElementById('receiveSubLocation').value.trim();

            if (!rb) { showToast('Please enter who received the asset.', 'error'); return; }

            const btn = document.getElementById('confirmReceiveBtn');
            btn.disabled = true;
            btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Saving...";

            const body = new FormData();
            body.append('action', 'receive');
            body.append('id', id);
            body.append('received_by', rb);
            body.append('to_sub_location', subLoc);

            const res    = await fetch('warehouse-receiving.php', { method: 'POST', body });
            const result = await res.json();

            btn.disabled = false;
            btn.innerHTML = "<i class='bx bx-check-double'></i> Confirm Receipt";

            showToast(result.message, result.success ? 'success' : 'error');
            if (result.success) {
                closeModal('receiveModal');
                document.getElementById('card-' + id)?.remove();
                updateStat(-1);
                // Show empty state if no more cards
                if (!document.querySelector('.recv-card')) {
                    document.getElementById('cardsGrid').innerHTML = `
                        <div class="empty-state">
                            <i class='bx bx-check-circle' style="color:var(--green);opacity:.5"></i>
                            <p>All items received!</p>
                            <small>No more pending items for your warehouse.</small>
                        </div>`;
                }
            }
        }

        function updateStat(delta) {
            const s = document.getElementById('statReleased');
            if (s) s.textContent = Math.max(0, parseInt(s.textContent) + delta);
        }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); });
        });
        function showToast(msg, type = 'success') {
            const t = document.createElement('div');
            t.className   = 'toast ' + type;
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 3500);
        }

        // ── Auto-refresh every 30s to catch newly released items ─────────────
        setInterval(() => { window.location.reload(); }, 30000);
    </script>
</body>
</html>
<?php ob_end_flush(); ?>