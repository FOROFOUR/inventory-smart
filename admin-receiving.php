<?php
ob_start();

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn      = getDBConnection();
$user_name = $_SESSION['name'] ?? 'Admin';

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

    // ── GET DETAILS (for receive modal preview) ───────────────────────────────
    if ($action === 'get_details') {
        $stmt = $conn->prepare("
            SELECT p.*, a.brand, a.model, a.serial_number, a.condition,
                   a.location AS asset_location, a.sub_location AS asset_sub_location,
                   a.beg_balance_count,
                   c.name AS category_name, sc.name AS subcategory_name
            FROM pull_out_transactions p
            LEFT JOIN assets         a  ON p.asset_id        = a.id
            LEFT JOIN categories     c  ON a.category_id     = c.id
            LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
            WHERE p.id = ? AND p.status = 'RELEASED'
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Not found or already received.']);
            exit();
        }

        // Clean purpose
        $row['purpose'] = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $row['purpose'] ?? ''));
        $row['purpose'] = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $row['purpose']));

        // Parse to_location for pre-fill
        $toParts = explode(' / ', $row['to_location'] ?? '', 2);
        $row['to_main_location'] = trim($toParts[0]);
        $row['to_sub_location']  = isset($toParts[1]) ? trim($toParts[1]) : '';

        // Thumbnail
        $imgStmt = $conn->prepare("SELECT image_path FROM asset_images WHERE asset_id = ? ORDER BY id LIMIT 1");
        $imgStmt->bind_param("i", $row['asset_id']);
        $imgStmt->execute();
        $img = $imgStmt->get_result()->fetch_assoc();
        $row['thumbnail'] = $img ? '/' . ltrim($img['image_path'], '/') : null;

        echo json_encode(['success' => true, 'data' => $row]);
        exit();
    }

    // ── MARK AS RECEIVED ──────────────────────────────────────────────────────
    if ($action === 'receive') {
        $received_by  = htmlspecialchars(trim($_POST['received_by']    ?? ''));
        $to_sub_input = htmlspecialchars(trim($_POST['to_sub_location'] ?? ''));

        if (empty($received_by)) {
            echo json_encode(['success' => false, 'message' => 'Received By is required.']);
            exit();
        }

        // Fetch transaction
        $txnStmt = $conn->prepare("
            SELECT id, asset_id, quantity, from_location, to_location, purpose
            FROM pull_out_transactions WHERE id = ? AND status = 'RELEASED'
        ");
        $txnStmt->bind_param("i", $id);
        $txnStmt->execute();
        $txn = $txnStmt->get_result()->fetch_assoc();

        if (!$txn) {
            echo json_encode(['success' => false, 'message' => 'Already processed or not found.']);
            exit();
        }

        $assetId    = $txn['asset_id'];
        $quantity   = (int) $txn['quantity'];
        $toLocation = $txn['to_location'] ?? '';

        // Parse main location
        $toParts        = explode(' / ', $toLocation, 2);
        $toMainLocation = trim($toParts[0]);

        // Use admin-provided sub-location, fallback to existing
        $toSubLocation = $to_sub_input !== ''
            ? $to_sub_input
            : (isset($toParts[1]) ? trim($toParts[1]) : '');

        // Update asset sub_location to finalize placement
        $updAsset = $conn->prepare("UPDATE assets SET sub_location = ?, updated_at = NOW() WHERE id = ?");
        $updAsset->bind_param("si", $toSubLocation, $assetId);
        $updAsset->execute();

        $finalToLocation = $toSubLocation !== ''
            ? $toMainLocation . ' / ' . $toSubLocation
            : $toMainLocation;

        $updTxnLoc = $conn->prepare("UPDATE pull_out_transactions SET to_location = ? WHERE id = ?");
        $updTxnLoc->bind_param("si", $finalToLocation, $id);
        $updTxnLoc->execute();

        // Mark transaction as RECEIVED
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
$search     = trim($_GET['search']   ?? '');
$dateFilter = trim($_GET['date']     ?? '');
$locFilter  = trim($_GET['location'] ?? '');

$sql    = "SELECT p.*, a.brand, a.model, a.serial_number,
               c.name AS category_name, sc.name AS subcategory_name
           FROM pull_out_transactions p
           LEFT JOIN assets         a  ON p.asset_id        = a.id
           LEFT JOIN categories     c  ON a.category_id     = c.id
           LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
           WHERE p.status = 'RELEASED'";
$params = [];
$types  = '';

if ($search !== '') {
    $sql    .= " AND (a.brand LIKE ? OR a.model LIKE ? OR p.requested_by LIKE ? OR p.from_location LIKE ? OR p.to_location LIKE ?)";
    $l       = "%$search%";
    $params  = array_merge($params, [$l, $l, $l, $l, $l]);
    $types  .= 'sssss';
}
if ($dateFilter !== '') {
    $sql    .= " AND DATE(p.released_at) = ?";
    $params[] = $dateFilter;
    $types  .= 's';
}
if ($locFilter !== '') {
    $sql    .= " AND (p.from_location LIKE ? OR p.to_location LIKE ?)";
    $ll      = $locFilter . '%';
    $params  = array_merge($params, [$ll, $ll]);
    $types  .= 'ss';
}
$sql .= " ORDER BY p.released_at ASC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows   = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;

$totalReleased = (int) $conn->query("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RELEASED'")->fetch_row()[0];
$totalReceived = (int) $conn->query("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RECEIVED'")->fetch_row()[0];

$locRows = $conn->query("
    SELECT DISTINCT TRIM(SUBSTRING_INDEX(loc, ' / ', 1)) AS main_loc
    FROM (
        SELECT to_location AS loc FROM pull_out_transactions WHERE to_location IS NOT NULL AND to_location != '' AND status = 'RELEASED'
    ) x ORDER BY main_loc
");
$locations = [];
while ($lr = $locRows->fetch_assoc()) {
    if ($lr['main_loc']) $locations[] = $lr['main_loc'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receiving — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --teal: #16a085;
            --green: #27ae60;
            --red: #e74c3c;
            --amber: #f39c12;
            --purple: #8e44ad;
            --blue: #2980b9;
            --bg: #f4f6f9;
            --white: #fff;
            --text: #2c3e50;
            --muted: #7f8c8d;
            --border: #e8ecf0;
            --shadow: 0 2px 12px rgba(0, 0, 0, .07);
            --radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Space Grotesk', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
        }

        .content {
            margin-left: 88px;
            padding: 2rem;
            transition: margin-left .3s;
        }

        .sidebar:not(.close)~.content {
            margin-left: 260px;
        }

        .page-header {
            background: linear-gradient(135deg, #16a085 0%, #0e6655 100%);
            border-radius: 16px;
            padding: 2rem 2rem 1.5rem;
            color: white;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, .08);
            border-radius: 50%;
        }

        .page-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .page-header p {
            font-size: .9rem;
            opacity: .85;
            margin-top: .25rem;
            position: relative;
            z-index: 1;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.25rem 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .stat-icon.teal  { background: #d1f2eb; color: var(--teal); }
        .stat-icon.green { background: #d5f5e3; color: var(--green); }
        .stat-icon.amber { background: #fef3cd; color: var(--amber); }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1;
        }

        .stat-label {
            font-size: .75rem;
            color: var(--muted);
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .filters {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            box-shadow: var(--shadow);
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 1.25rem;
        }

        .filters input,
        .filters select {
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: .6rem 1rem;
            font-family: 'Space Grotesk', sans-serif;
            font-size: .875rem;
            color: var(--text);
            outline: none;
            background: white;
            transition: border-color .2s;
        }

        .filters input:focus,
        .filters select:focus { border-color: var(--teal); }

        .search-wrap {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .search-wrap input { padding-left: 2.4rem; width: 100%; }

        .search-wrap i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
        }

        .btn {
            padding: .6rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
        }

        .btn:disabled { opacity: .45; cursor: not-allowed; }

        .btn-teal      { background: var(--teal); color: white; }
        .btn-teal:hover:not(:disabled) { background: #138d75; }
        .btn-secondary { background: #ecf0f1; color: var(--text); }
        .btn-secondary:hover { background: #d5dbdb; }
        .btn-export    { background: var(--purple); color: white; }
        .btn-export:hover { background: #7d3c98; }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.25rem;
        }

        .recv-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border-left: 4px solid var(--teal);
            padding: 1.25rem 1.5rem;
            transition: transform .2s, box-shadow .2s;
        }

        .recv-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: .9rem;
        }

        .req-id    { font-size: .78rem; color: var(--muted); font-weight: 600; text-transform: uppercase; }
        .req-asset { font-size: 1rem; font-weight: 700; color: var(--text); }
        .req-model { font-size: .82rem; color: var(--muted); }
        .req-serial {
            font-size: .75rem;
            background: #f1f2f6;
            padding: 1px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 3px;
            font-family: monospace;
            color: var(--muted);
        }

        .status-badge {
            padding: .3rem .7rem;
            border-radius: 6px;
            font-size: .74rem;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
            background: #d1f2eb;
            color: var(--teal);
        }

        /* Personnel chips row */
        .personnel-chips {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-bottom: .85rem;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .78rem;
            font-weight: 600;
            padding: .3rem .7rem;
            border-radius: 6px;
            white-space: nowrap;
        }

        .chip-purple {
            background: #f0e6f6;
            color: var(--purple);
        }

        .chip-blue {
            background: #d6eaf8;
            color: var(--blue);
        }

        .released-time {
            font-size: .78rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: .3rem;
            margin-bottom: .75rem;
        }

        .route-box {
            display: flex;
            align-items: center;
            gap: .6rem;
            background: #f8f9fa;
            border-radius: 8px;
            padding: .7rem 1rem;
            margin: .75rem 0;
        }

        .route-box .loc { flex: 1; }

        .route-box .loc-label {
            font-size: .72rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            font-weight: 600;
        }

        .route-box .loc-value { font-size: .9rem; font-weight: 600; }
        .route-box .arrow     { color: var(--teal); font-size: 1.2rem; }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .5rem;
            font-size: .82rem;
            margin-bottom: 1rem;
        }

        .meta-item .mlabel { color: var(--muted); font-size: .74rem; text-transform: uppercase; letter-spacing: .4px; }
        .meta-item .mvalue { font-weight: 600; color: var(--text); }

        .card-actions {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            margin-top: .75rem;
            padding-top: .75rem;
            border-top: 1px solid var(--border);
        }

        .empty-state {
            text-align: center;
            padding: 3.5rem;
            color: var(--muted);
            grid-column: 1/-1;
        }

        .empty-state i  { font-size: 3.5rem; opacity: .25; display: block; margin-bottom: .75rem; }
        .empty-state p  { font-size: 1rem; margin-bottom: .4rem; }
        .empty-state small { font-size: .85rem; }

        /* ── Modal ────────────────────────────────────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .55);
            backdrop-filter: blur(3px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active { display: flex; }

        .modal {
            background: var(--white);
            border-radius: 16px;
            width: 95%;
            max-width: 540px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
            animation: slideUp .25s ease;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: var(--muted);
            border-radius: 6px;
            padding: .2rem .4rem;
        }

        .modal-close:hover { background: var(--border); }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            gap: .75rem;
            justify-content: flex-end;
            flex-shrink: 0;
        }

        .modal-asset-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.25rem;
            border: 1px solid var(--border);
        }

        .modal-thumb {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: #e8ecf0;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--muted);
            overflow: hidden;
        }

        .modal-thumb img { width: 100%; height: 100%; object-fit: cover; }

        .modal-asset-info .a-name   { font-size: 1rem; font-weight: 700; }
        .modal-asset-info .a-type   { font-size: .82rem; color: var(--muted); }
        .modal-asset-info .a-serial {
            font-size: .75rem;
            font-family: monospace;
            background: #e8ecf0;
            padding: 1px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 3px;
        }

        .modal-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .6rem .75rem;
            margin-bottom: 1.25rem;
        }

        .md-item .md-label {
            font-size: .72rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .4px;
            font-weight: 600;
        }

        .md-item .md-value { font-size: .88rem; font-weight: 600; color: var(--text); }
        .md-item.full { grid-column: 1/-1; }

        /* Personnel row inside modal */
        .modal-personnel-row {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-bottom: 1.25rem;
        }

        .modal-personnel-chip {
            display: flex;
            align-items: center;
            gap: .4rem;
            border-radius: 8px;
            padding: .55rem 1rem;
            font-size: .84rem;
        }

        .modal-personnel-chip i { font-size: 1.05rem; }

        .modal-personnel-chip span { color: var(--muted); margin-right: .15rem; }

        .modal-personnel-chip.purple { background: #f0e6f6; color: var(--purple); }
        .modal-personnel-chip.blue   { background: #d6eaf8; color: var(--blue); }

        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 1.25rem 0;
        }

        .form-group { margin-bottom: 1rem; }

        .form-group label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: .4rem;
        }

        .form-group input {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: .7rem 1rem;
            font-family: 'Space Grotesk', sans-serif;
            font-size: .9rem;
            outline: none;
            transition: border-color .2s;
        }

        .form-group input:focus { border-color: var(--teal); }

        .form-group small {
            font-size: .78rem;
            color: var(--muted);
            margin-top: .3rem;
            display: block;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: .9rem 1.4rem;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: .875rem;
            z-index: 10000;
            animation: toastIn .3s ease;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .2);
        }

        .toast.success { background: var(--green); }
        .toast.error   { background: var(--red); }

        @keyframes toastIn {
            from { opacity: 0; transform: translateX(40px); }
            to   { opacity: 1; transform: translateX(0); }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="content">

        <div class="page-header">
            <h1><i class='bx bx-package'></i> Receiving</h1>
            <p>Confirm delivery of released assets — set destination sub-location and mark as received</p>
        </div>

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

        <div class="filters">
            <div class="search-wrap">
                <i class='bx bx-search'></i>
                <input type="text" id="searchInput" placeholder="Search asset, requester, location..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <input type="date" id="dateInput" value="<?php echo htmlspecialchars($dateFilter); ?>" title="Filter by released date">
            <select id="locSelect">
                <option value="">All Destinations</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $locFilter === $loc ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-export" onclick="exportCSV()"><i class='bx bx-download'></i> Export CSV</button>
        </div>

        <div class="cards-grid" id="cardsGrid">
            <?php if (empty($rows)): ?>
                <div class="empty-state">
                    <i class='bx bx-package'></i>
                    <p>No items awaiting receipt</p>
                    <small>Released assets will appear here once they're ready to be confirmed.</small>
                </div>
            <?php else: ?>
                <?php foreach ($rows as $r):
                    $from    = $r['from_location'] ?: '—';
                    $to      = $r['to_location']   ?: ($r['location_received'] ?: '—');
                    $purpose = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $r['purpose'] ?? '');
                    $purpose = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $purpose)) ?: '—';
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

                        <!-- Personnel chips: Released By + Delivered By -->
                        <?php if ($r['released_by'] || !empty($r['delivered_by'])): ?>
                            <div class="personnel-chips">
                                <?php if ($r['released_by']): ?>
                                    <span class="chip chip-purple">
                                        <i class='bx bx-user-check'></i>
                                        Released by: <?php echo htmlspecialchars($r['released_by']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($r['delivered_by'])): ?>
                                    <span class="chip chip-blue">
                                        <i class='bx bx-car'></i>
                                        Delivered by: <?php echo htmlspecialchars($r['delivered_by']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="released-time">
                            <i class='bx bx-time-five'></i>
                            Released: <?php echo $releasedAt; ?>
                        </div>

                        <div class="route-box">
                            <div class="loc">
                                <div class="loc-label">From</div>
                                <div class="loc-value"><?php echo htmlspecialchars($from); ?></div>
                            </div>
                            <i class='bx bx-right-arrow-alt arrow'></i>
                            <div class="loc">
                                <div class="loc-label">Destination</div>
                                <div class="loc-value"><?php echo htmlspecialchars($to); ?></div>
                            </div>
                        </div>

                        <div class="meta-grid">
                            <div class="meta-item">
                                <div class="mlabel">Quantity</div>
                                <div class="mvalue"><?php echo $r['quantity']; ?> pcs</div>
                            </div>
                            <div class="meta-item">
                                <div class="mlabel">Type</div>
                                <div class="mvalue"><?php echo htmlspecialchars($r['subcategory_name'] ?? '—'); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="mlabel">Requested By</div>
                                <div class="mvalue"><?php echo htmlspecialchars($r['requested_by'] ?? '—'); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="mlabel">Date Needed</div>
                                <div class="mvalue"><?php echo $r['date_needed'] ? date('M j, Y', strtotime($r['date_needed'])) : '—'; ?></div>
                            </div>
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

    <!-- ── Receive Modal ──────────────────────────────────────────────────── -->
    <div class="modal-overlay" id="receiveModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class='bx bx-check-double' style="color:var(--teal)"></i> Confirm Receipt</h3>
                <button class="modal-close" onclick="closeModal('receiveModal')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="receiveId">

                <!-- Asset card -->
                <div class="modal-asset-card">
                    <div class="modal-thumb" id="receiveThumb"><i class='bx bx-image-alt'></i></div>
                    <div class="modal-asset-info">
                        <div class="a-name" id="receiveAssetName">—</div>
                        <div class="a-type" id="receiveAssetType"></div>
                        <div class="a-serial" id="receiveAssetSerial" style="display:none;"></div>
                    </div>
                </div>

                <!-- Transaction details -->
                <div class="modal-details">
                    <div class="md-item">
                        <div class="md-label">From</div>
                        <div class="md-value" id="receiveFrom">—</div>
                    </div>
                    <div class="md-item">
                        <div class="md-label">Destination</div>
                        <div class="md-value" id="receiveTo">—</div>
                    </div>
                    <div class="md-item">
                        <div class="md-label">Quantity</div>
                        <div class="md-value" id="receiveQty">—</div>
                    </div>
                    <div class="md-item">
                        <div class="md-label">Requested By</div>
                        <div class="md-value" id="receiveReqBy">—</div>
                    </div>
                    <div class="md-item">
                        <div class="md-label">Date Needed</div>
                        <div class="md-value" id="receiveDateNeeded">—</div>
                    </div>
                    <div class="md-item">
                        <div class="md-label">Purpose</div>
                        <div class="md-value" id="receivePurpose">—</div>
                    </div>
                </div>

                <!-- Released By + Delivered By chips inside modal -->
                <div class="modal-personnel-row" id="receivePersonnelRow" style="display:none;">
                    <div class="modal-personnel-chip purple" id="receiveReleasedByChip" style="display:none;">
                        <i class='bx bx-user-check'></i>
                        <span>Released by</span>
                        <strong id="receiveReleasedBy">—</strong>
                    </div>
                    <div class="modal-personnel-chip blue" id="receiveDeliveredByChip" style="display:none;">
                        <i class='bx bx-car'></i>
                        <span>Delivered by</span>
                        <strong id="receiveDeliveredBy">—</strong>
                    </div>
                </div>

                <hr class="divider">

                <!-- Admin inputs -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Destination Sub-Location</label>
                        <input type="text" id="receiveSubLocation" placeholder="e.g. Shelf 3, Rack B...">
                        <small>Exact spot in <strong id="receiveToHint">destination</strong></small>
                    </div>
                    <div class="form-group">
                        <label>Received By <span style="color:var(--red)">*</span></label>
                        <input type="text" id="receiveReceivedBy" placeholder="Name of person receiving">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('receiveModal')">Cancel</button>
                <button class="btn btn-teal" onclick="submitReceive()">
                    <i class='bx bx-check-double'></i> Confirm Receipt
                </button>
            </div>
        </div>
    </div>

    <script>
        function applyFilters() {
            const p = new URLSearchParams({
                search: document.getElementById('searchInput').value,
                date: document.getElementById('dateInput').value,
                location: document.getElementById('locSelect').value,
            });
            window.location.href = 'admin-receiving.php?' + p.toString();
        }
        document.getElementById('searchInput').addEventListener('keydown', e => {
            if (e.key === 'Enter') applyFilters();
        });
        document.getElementById('dateInput').addEventListener('change', applyFilters);
        document.getElementById('locSelect').addEventListener('change', applyFilters);

        async function openReceive(id) {
            // Reset all fields
            document.getElementById('receiveId').value = id;
            document.getElementById('receiveReceivedBy').value = '';
            document.getElementById('receiveSubLocation').value = '';
            document.getElementById('receiveAssetName').textContent = 'Loading...';
            document.getElementById('receiveAssetType').textContent = '';
            document.getElementById('receiveAssetSerial').style.display = 'none';
            document.getElementById('receiveFrom').textContent = '—';
            document.getElementById('receiveTo').textContent = '—';
            document.getElementById('receiveQty').textContent = '—';
            document.getElementById('receiveReqBy').textContent = '—';
            document.getElementById('receiveDateNeeded').textContent = '—';
            document.getElementById('receivePurpose').textContent = '—';
            document.getElementById('receiveToHint').textContent = 'destination';
            document.getElementById('receivePersonnelRow').style.display = 'none';
            document.getElementById('receiveReleasedByChip').style.display = 'none';
            document.getElementById('receiveDeliveredByChip').style.display = 'none';

            const thumb = document.getElementById('receiveThumb');
            thumb.innerHTML = "<i class='bx bx-image-alt'></i>";

            document.getElementById('receiveModal').classList.add('active');

            try {
                const body = new FormData();
                body.append('action', 'get_details');
                body.append('id', id);
                const res = await fetch('admin-receiving.php', { method: 'POST', body });
                const result = await res.json();
                if (!result.success) { showToast('Could not load details.', 'error'); return; }

                const d = result.data;

                // Asset info
                document.getElementById('receiveAssetName').textContent = `${d.brand || ''} ${d.model || ''}`.trim() || '—';
                document.getElementById('receiveAssetType').textContent = d.subcategory_name || '';
                if (d.serial_number) {
                    const s = document.getElementById('receiveAssetSerial');
                    s.textContent = d.serial_number;
                    s.style.display = 'inline-block';
                }
                if (d.thumbnail) {
                    thumb.innerHTML = `<img src="${d.thumbnail}" onerror="this.parentElement.innerHTML='<i class=\\'bx bx-image-alt\\'></i>'">`;
                }

                // Transfer details
                document.getElementById('receiveFrom').textContent = d.from_location || '—';
                document.getElementById('receiveTo').textContent = d.to_main_location || d.to_location || '—';
                document.getElementById('receiveQty').textContent = `${d.quantity || '—'} pcs`;
                document.getElementById('receiveReqBy').textContent = d.requested_by || '—';
                document.getElementById('receiveDateNeeded').textContent = d.date_needed
                    ? new Date(d.date_needed).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                    : '—';
                document.getElementById('receivePurpose').textContent = d.purpose || '—';
                document.getElementById('receiveToHint').textContent = d.to_main_location || 'destination';

                // Personnel chips
                let showPersonnel = false;
                if (d.released_by) {
                    document.getElementById('receiveReleasedBy').textContent = d.released_by;
                    document.getElementById('receiveReleasedByChip').style.display = 'flex';
                    showPersonnel = true;
                }
                if (d.delivered_by) {
                    document.getElementById('receiveDeliveredBy').textContent = d.delivered_by;
                    document.getElementById('receiveDeliveredByChip').style.display = 'flex';
                    showPersonnel = true;
                }
                if (showPersonnel) {
                    document.getElementById('receivePersonnelRow').style.display = 'flex';
                }

                // Pre-fill sub-location if set
                if (d.to_sub_location) {
                    document.getElementById('receiveSubLocation').value = d.to_sub_location;
                }

            } catch (e) {
                showToast('Failed to load details.', 'error');
            }
        }

        async function submitReceive() {
            const id     = document.getElementById('receiveId').value;
            const rb     = document.getElementById('receiveReceivedBy').value.trim();
            const subLoc = document.getElementById('receiveSubLocation').value.trim();

            if (!rb) { showToast('Please enter who received the asset.', 'error'); return; }

            const body = new FormData();
            body.append('action', 'receive');
            body.append('id', id);
            body.append('received_by', rb);
            body.append('to_sub_location', subLoc);

            const res = await fetch('admin-receiving.php', { method: 'POST', body });
            const result = await res.json();
            showToast(result.message, result.success ? 'success' : 'error');
            if (result.success) {
                closeModal('receiveModal');
                document.getElementById('card-' + id)?.remove();
                updateStat(-1);
            }
        }

        function exportCSV() {
            const headers = ['ID', 'Brand', 'Model', 'Serial', 'Type', 'Qty', 'From', 'To', 'Purpose', 'Requested By', 'Date Needed', 'Released By', 'Delivered By', 'Released At'];
            const data = <?php
                $out = [];
                foreach ($rows as $r) {
                    $from = $r['from_location'] ?: '—';
                    $to   = $r['to_location']   ?: ($r['location_received'] ?: '—');
                    $p    = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $r['purpose'] ?? '');
                    $p    = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $p));
                    $out[] = [
                        $r['id'],
                        $r['brand']            ?? '',
                        $r['model']            ?? '',
                        $r['serial_number']    ?? '',
                        $r['subcategory_name'] ?? '',
                        $r['quantity'],
                        $from,
                        $to,
                        $p,
                        $r['requested_by']     ?? '',
                        $r['date_needed']      ?? '',
                        $r['released_by']      ?? '',
                        $r['delivered_by']     ?? '',
                        $r['released_at']      ?? '',
                    ];
                }
                echo json_encode($out);
            ?>;
            const csv = [headers, ...data].map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
            a.download = 'receiving_' + new Date().toISOString().slice(0, 10) + '.csv';
            a.click();
        }

        function updateStat(delta) {
            const s = document.getElementById('statReleased');
            if (s) s.textContent = Math.max(0, parseInt(s.textContent) + delta);
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
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