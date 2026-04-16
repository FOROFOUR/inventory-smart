<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$conn        = getDBConnection();
$user_name   = $_SESSION['name'] ?? 'Admin';
$sessionRole = $_SESSION['role'] ?? '';
$sessionName = $_SESSION['name'] ?? '';
$isAdmin     = strtoupper($sessionRole) === 'ADMIN';

// =============================================================================
// POST HANDLERS
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json');

    $id     = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'];

    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID.']); exit(); }

    // ── GET DETAILS ───────────────────────────────────────────────────────────
    if ($action === 'get_details') {
        try {
            $stmt = $conn->prepare("
                SELECT p.*, a.brand, a.model, a.serial_number, a.condition,
                       a.location AS asset_location, a.sub_location AS asset_sub_location,
                       a.description AS asset_description, a.beg_balance_count,
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
            $stmt->close();

            if (!$row) { echo json_encode(['success' => false, 'message' => 'Not found or already received.']); exit(); }

            $row['purpose'] = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $row['purpose'] ?? ''));
            $row['purpose'] = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $row['purpose']));

            $toParts = explode(' / ', $row['to_location'] ?? '', 2);
            $row['to_main_location'] = trim($toParts[0]);
            $row['to_sub_location']  = isset($toParts[1]) ? trim($toParts[1]) : '';

            // All images for view modal
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

            // Thumbnail (first image) for receive modal card
            if (!empty($images)) {
                $first = $images[0];
                $row['thumbnail']      = $first['thumb'] ?? $first['url'];
                $row['thumbnail_type'] = $first['type'];
                $row['thumbnail_url']  = $first['url'];
            } else {
                $row['thumbnail'] = null; $row['thumbnail_type'] = null; $row['thumbnail_url'] = null;
            }

            echo json_encode(['success' => true, 'data' => $row]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }

    // ── MARK AS RECEIVED ──────────────────────────────────────────────────────
    if ($action === 'receive') {
        try {
            $received_by  = htmlspecialchars(trim($_POST['received_by']    ?? ''));
            $to_sub_input = htmlspecialchars(trim($_POST['to_sub_location'] ?? ''));

            if (empty($received_by)) { echo json_encode(['success' => false, 'message' => 'Received By is required.']); exit(); }

            $txnStmt = $conn->prepare("SELECT id, asset_id, quantity, from_location, to_location, purpose FROM pull_out_transactions WHERE id = ? AND status = 'RELEASED'");
            $txnStmt->bind_param("i", $id);
            $txnStmt->execute();
            $txn = $txnStmt->get_result()->fetch_assoc();
            $txnStmt->close();

            if (!$txn) { echo json_encode(['success' => false, 'message' => 'Already processed or not found.']); exit(); }

            $assetId      = $txn['asset_id'];
            $quantity     = (int) $txn['quantity'];
            $toLocation   = $txn['to_location']  ?? '';
            $fromLocation = $txn['from_location'] ?? '';
            $purpose      = $txn['purpose']       ?? '';

            $toParts        = explode(' / ', $toLocation, 2);
            $toMainLocation = trim($toParts[0]);
            $toSubLocation  = $to_sub_input !== '' ? $to_sub_input : (isset($toParts[1]) ? trim($toParts[1]) : '');
            $fromParts      = explode(' / ', $fromLocation, 2);
            $fromMainLocation = trim($fromParts[0]);

            $assetStmt = $conn->prepare("SELECT * FROM assets WHERE id = ?");
            $assetStmt->bind_param("i", $assetId);
            $assetStmt->execute();
            $srcAsset = $assetStmt->get_result()->fetch_assoc();
            $assetStmt->close();

            if (!$srcAsset) { echo json_encode(['success' => false, 'message' => 'Source asset not found.']); exit(); }

            $availStmt = $conn->prepare("
                SELECT (a.beg_balance_count - COALESCE(SUM(CASE WHEN p.status IN ('PENDING','CONFIRMED','RELEASED') THEN p.quantity ELSE 0 END), 0)) AS true_available
                FROM assets a
                LEFT JOIN pull_out_transactions p ON a.id = p.asset_id AND p.id != ?
                WHERE a.id = ? GROUP BY a.id
            ");
            $availStmt->bind_param("ii", $id, $assetId);
            $availStmt->execute();
            $availRow      = $availStmt->get_result()->fetch_assoc();
            $availStmt->close();

            $trueAvailable = intval($availRow['true_available'] ?? $srcAsset['beg_balance_count']);
            $newSrcBal     = max(0, intval($srcAsset['beg_balance_count']) - $quantity);
            $willBeEmpty   = ($trueAvailable - $quantity) <= 0;

            if ($willBeEmpty) {
                $updSrc = $conn->prepare("UPDATE assets SET location = ?, sub_location = ?, beg_balance_count = ?, updated_at = NOW() WHERE id = ?");
                $updSrc->bind_param("ssii", $toMainLocation, $toSubLocation, $quantity, $assetId);
                $updSrc->execute(); $updSrc->close();
                $destAssetId = $assetId;
            } else {
                $updSrc = $conn->prepare("UPDATE assets SET beg_balance_count = ?, updated_at = NOW() WHERE id = ?");
                $updSrc->bind_param("ii", $newSrcBal, $assetId);
                $updSrc->execute(); $updSrc->close();

                $findStmt = $conn->prepare("SELECT id, beg_balance_count FROM assets WHERE location = ? AND sub_category_id = ? AND brand = ? AND id != ? ORDER BY id LIMIT 1");
                $findStmt->bind_param("sisi", $toMainLocation, $srcAsset['sub_category_id'], $srcAsset['brand'], $assetId);
                $findStmt->execute();
                $destAsset = $findStmt->get_result()->fetch_assoc();
                $findStmt->close();

                if ($destAsset) {
                    $newDestBal = intval($destAsset['beg_balance_count']) + $quantity;
                    $updDest    = $conn->prepare("UPDATE assets SET beg_balance_count = ?, location = ?, sub_location = ?, updated_at = NOW() WHERE id = ?");
                    $updDest->bind_param("issi", $newDestBal, $toMainLocation, $toSubLocation, $destAsset['id']);
                    $updDest->execute(); $updDest->close();
                    $destAssetId = $destAsset['id'];
                } else {
                    $ins = $conn->prepare("INSERT INTO assets (category_id, sub_category_id, brand, model, serial_number, `condition`, status, location, sub_location, description, beg_balance_count, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    $ins->bind_param("iissssssssi", $srcAsset['category_id'], $srcAsset['sub_category_id'], $srcAsset['brand'], $srcAsset['model'], $srcAsset['serial_number'], $srcAsset['condition'], $srcAsset['status'], $toMainLocation, $toSubLocation, $srcAsset['description'], $quantity);
                    $ins->execute();
                    $destAssetId = $conn->insert_id;
                    $ins->close();

                    $imgFetchStmt = $conn->prepare("SELECT image_path, drive_url FROM asset_images WHERE asset_id = ?");
                    $imgFetchStmt->bind_param("i", $assetId);
                    $imgFetchStmt->execute();
                    $imgFetchResult = $imgFetchStmt->get_result();
                    $imgCopyStmt    = $conn->prepare("INSERT INTO asset_images (asset_id, image_path, drive_url) VALUES (?, ?, ?)");
                    while ($imgRow = $imgFetchResult->fetch_assoc()) {
                        $imgCopyStmt->bind_param("iss", $destAssetId, $imgRow['image_path'], $imgRow['drive_url']);
                        $imgCopyStmt->execute();
                    }
                    $imgFetchStmt->close(); $imgCopyStmt->close();
                }
            }

            $saveDestStmt = $conn->prepare("UPDATE pull_out_transactions SET purpose = CONCAT(COALESCE(purpose, ''), ?) WHERE id = ?");
            $destTag = " [dest:$destAssetId]";
            $saveDestStmt->bind_param("si", $destTag, $id);
            $saveDestStmt->execute(); $saveDestStmt->close();

            $finalToLocation = $toSubLocation !== '' ? $toMainLocation . ' / ' . $toSubLocation : $toMainLocation;
            $updTxnLoc       = $conn->prepare("UPDATE pull_out_transactions SET to_location = ? WHERE id = ?");
            $updTxnLoc->bind_param("si", $finalToLocation, $id);
            $updTxnLoc->execute(); $updTxnLoc->close();

            $recvStmt = $conn->prepare("UPDATE pull_out_transactions SET status = 'RECEIVED', received_at = NOW(), received_by = ? WHERE id = ? AND status = 'RELEASED'");
            $recvStmt->bind_param("si", $received_by, $id);
            $recvStmt->execute();
            if ($recvStmt->affected_rows === 0) { $recvStmt->close(); echo json_encode(['success' => false, 'message' => 'Already processed.']); exit(); }
            $recvStmt->close();

            $subLocNote = $toSubLocation ? " / $toSubLocation" : '';
            $desc = "Pull-out #$id RECEIVED by $received_by at {$toMainLocation}{$subLocNote}. Asset #{$assetId} -{$quantity} @ {$fromMainLocation} → {$toMainLocation} (dest asset #{$destAssetId} +{$quantity}).";
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'RECEIVE_PULLOUT', ?)");
            $logStmt->bind_param("ss", $user_name, $desc);
            $logStmt->execute(); $logStmt->close();

            echo json_encode(['success' => true, 'message' => "Request #$id marked as Received. Inventory updated — Asset moved to {$toMainLocation}{$subLocNote}."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit();
}

// =============================================================================
// PAGE DATA
// =============================================================================
ob_start();

$search     = trim($_GET['search']   ?? '');
$dateFilter = trim($_GET['date']     ?? '');
$locFilter  = trim($_GET['location'] ?? '');

$sql    = "SELECT p.*, a.brand, a.model, a.serial_number, c.name AS category_name, sc.name AS subcategory_name
           FROM pull_out_transactions p
           LEFT JOIN assets         a  ON p.asset_id        = a.id
           LEFT JOIN categories     c  ON a.category_id     = c.id
           LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
           WHERE p.status = 'RELEASED'";
$params = []; $types = '';

// Non-admin: show only their own requests
if (!$isAdmin) {
    $sql    .= " AND p.requested_by = ?";
    $params[] = $sessionName;
    $types   .= 's';
}

if ($search !== '') {
    $sql    .= " AND (a.brand LIKE ? OR a.model LIKE ? OR p.requested_by LIKE ? OR p.from_location LIKE ? OR p.to_location LIKE ?)";
    $l       = "%$search%";
    $params  = array_merge($params, [$l, $l, $l, $l, $l]); $types .= 'sssss';
}
if ($dateFilter !== '') { $sql .= " AND DATE(p.released_at) = ?"; $params[] = $dateFilter; $types .= 's'; }
if ($locFilter !== '') {
    $sql .= " AND (p.from_location LIKE ? OR p.to_location LIKE ?)";
    $ll = $locFilter . '%'; $params = array_merge($params, [$ll, $ll]); $types .= 'ss';
}
$sql .= " ORDER BY p.released_at ASC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows   = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;
$stmt->close();

$totalReleased = $isAdmin
    ? (int) $conn->query("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RELEASED'")->fetch_row()[0]
    : (int) $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RELEASED' AND requested_by = ?")->bind_param('s', $sessionName) + 0;

$totalReceived = $isAdmin
    ? (int) $conn->query("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RECEIVED'")->fetch_row()[0]
    : (int) $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RECEIVED' AND requested_by = ?")->bind_param('s', $sessionName) + 0;

// Proper counts for non-admin using prepared statements
if (!$isAdmin) {
    $stRel = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RELEASED' AND requested_by = ?");
    $stRel->bind_param('s', $sessionName); $stRel->execute();
    $totalReleased = (int) $stRel->get_result()->fetch_row()[0]; $stRel->close();

    $stRec = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RECEIVED' AND requested_by = ?");
    $stRec->bind_param('s', $sessionName); $stRec->execute();
    $totalReceived = (int) $stRec->get_result()->fetch_row()[0]; $stRec->close();
}

$locRows   = $conn->query("SELECT DISTINCT TRIM(SUBSTRING_INDEX(loc, ' / ', 1)) AS main_loc FROM (SELECT to_location AS loc FROM pull_out_transactions WHERE to_location IS NOT NULL AND to_location != '' AND status = 'RELEASED') x ORDER BY main_loc");
$locations = [];
while ($lr = $locRows->fetch_assoc()) { if ($lr['main_loc']) $locations[] = $lr['main_loc']; }
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
            --teal: #16a085; --green: #27ae60; --red: #e74c3c; --amber: #f39c12;
            --bg: #f4f6f9; --white: #fff; --text: #2c3e50; --muted: #7f8c8d;
            --border: #e8ecf0; --shadow: 0 2px 12px rgba(0,0,0,.07); --radius: 14px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Space Grotesk', sans-serif; }
        body { background: var(--bg); color: var(--text); line-height: 1.5; }

        .content { 
            margin-left: 88px; 
            padding: 1.5rem; 
            transition: margin-left .3s; 
            min-height: 100vh; 
        }
        .sidebar:not(.close)~.content { margin-left: 260px; }

        .page-header { 
            background: linear-gradient(135deg, #16a085 0%, #0e6655 100%); 
            border-radius: 16px; 
            padding: 2rem 1.75rem; 
            color: white; 
            margin-bottom: 1.75rem; 
            position: relative; 
            overflow: hidden; 
        }
        .page-header h1 { font-size: 1.85rem; font-weight: 700; }

        /* Stats */
        .stats-row { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 1rem; 
            margin-bottom: 1.75rem; 
        }
        .stat-card { 
            background: var(--white); 
            border-radius: var(--radius); 
            padding: 1.25rem; 
            box-shadow: var(--shadow); 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
        }

        /* Filters */
        .filters { 
            background: var(--white); 
            border-radius: var(--radius); 
            padding: 1.25rem; 
            box-shadow: var(--shadow); 
            display: flex; 
            gap: 1rem; 
            flex-wrap: wrap; 
            align-items: center; 
            margin-bottom: 1.5rem; 
        }
        .search-wrap { position: relative; flex: 1; min-width: 220px; }
        .search-wrap input { padding-left: 2.5rem; width: 100%; padding: .75rem 1rem; border: 1.5px solid var(--border); border-radius: 8px; }

        /* Cards */
        .cards-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
            gap: 1.25rem; 
        }
        .recv-card { 
            background: var(--white); 
            border-radius: var(--radius); 
            box-shadow: var(--shadow); 
            border-left: 5px solid var(--teal); 
            padding: 1.25rem; 
            transition: all .3s; 
        }
        .recv-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,.12); }

        /* Responsive */
        @media (max-width: 768px) {
            .content { margin-left: 0 !important; padding: 1rem; }
            .page-header { padding: 1.75rem 1.5rem; }
            .page-header h1 { font-size: 1.65rem; }
            
            .stats-row { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
            
            .filters { 
                flex-direction: column; 
                align-items: stretch; 
            }
            .search-wrap { min-width: 100%; }
            
            .cards-grid { 
                grid-template-columns: 1fr; 
                gap: 1rem; 
            }
            
            .recv-card { padding: 1.1rem; }
        }

        @media (max-width: 480px) {
            .meta-grid { grid-template-columns: 1fr; }
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Space Grotesk', sans-serif; }
        .page-header::before { content: ''; position: absolute; top: -50%; right: -5%; width: 300px; height: 300px; background: rgba(255,255,255,.08); border-radius: 50%; }
        .page-header h1 { font-size: 1.6rem; font-weight: 700; position: relative; z-index: 1; display: flex; align-items: center; gap: .6rem; }
        .page-header p  { font-size: .9rem; opacity: .85; margin-top: .25rem; position: relative; z-index: 1; }
      
        .stat-icon { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .stat-icon.teal  { background: #d1f2eb; color: var(--teal); }
        .stat-icon.green { background: #d5f5e3; color: var(--green); }
        .stat-icon.amber { background: #fef3cd; color: var(--amber); }
        .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1; }
        .stat-label { font-size: .75rem; color: var(--muted); margin-top: 2px; text-transform: uppercase; letter-spacing: .5px; }
        .filters input, .filters select { border: 1.5px solid var(--border); border-radius: 8px; padding: .6rem 1rem; font-family: 'Space Grotesk', sans-serif; font-size: .875rem; color: var(--text); outline: none; background: white; transition: border-color .2s; }
        .filters input:focus, .filters select:focus { border-color: var(--teal); }
      
        .search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); }
        .btn { padding: .6rem 1.2rem; border: none; border-radius: 8px; font-family: 'Space Grotesk', sans-serif; font-size: .875rem; font-weight: 600; cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: .4rem; }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .btn-teal      { background: var(--teal); color: white; }
        .btn-teal:hover:not(:disabled) { background: #138d75; }
        .btn-view      { background: var(--blue); color: white; }
        .btn-view:hover { background: #2471a3; }
        .btn-secondary { background: #ecf0f1; color: var(--text); }
        .btn-secondary:hover { background: #d5dbdb; }
        .btn-export    { background: var(--purple); color: white; }
        .btn-export:hover { background: #7d3c98; }
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
        .route-box .arrow { color: var(--teal); font-size: 1.2rem; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; font-size: .82rem; margin-bottom: 1rem; }
        .meta-item .mlabel { color: var(--muted); font-size: .74rem; text-transform: uppercase; letter-spacing: .4px; }
        .meta-item .mvalue { font-weight: 600; color: var(--text); }
        .card-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .75rem; padding-top: .75rem; border-top: 1px solid var(--border); }
        .empty-state { text-align: center; padding: 3.5rem; color: var(--muted); grid-column: 1/-1; }
        .empty-state i { font-size: 3.5rem; opacity: .25; display: block; margin-bottom: .75rem; }

        /* ── MODALS ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(3px); z-index: 9999; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--white); border-radius: 16px; width: 95%; max-width: 540px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.25); animation: slideUp .25s ease; max-height: 90vh; display: flex; flex-direction: column; }
        .modal.modal-wide { max-width: 680px; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .modal-header h3 { font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
        .modal-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--muted); border-radius: 6px; padding: .2rem .4rem; }
        .modal-close:hover { background: var(--border); }
        .modal-body { padding: 1.5rem; overflow-y: auto; }
        .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; gap: .75rem; justify-content: flex-end; flex-shrink: 0; }

        /* Receive modal styles */
        .modal-asset-card { display: flex; align-items: center; gap: 1rem; background: #f8f9fa; border-radius: 10px; padding: 1rem; margin-bottom: 1.25rem; border: 1px solid var(--border); }
        .modal-thumb { width: 60px; height: 60px; border-radius: 8px; background: #e8ecf0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--muted); overflow: hidden; }
        .modal-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .modal-asset-info .a-name   { font-size: 1.05rem; font-weight: 800; color: var(--text); letter-spacing: -.2px; }
        .modal-asset-info .a-type   { font-size: .82rem; color: var(--muted); margin-top: 2px; }
        .modal-asset-info .a-serial { font-size: .78rem; font-family: 'Courier New', monospace; font-weight: 700; background: #2c3e50; color: #fff; padding: 2px 8px; border-radius: 4px; display: inline-block; margin-top: 5px; letter-spacing: .5px; }
        .modal-details { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem .75rem; margin-bottom: 1.25rem; }
        .md-item .md-label { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; font-weight: 600; }
        .md-item .md-value { font-size: .88rem; font-weight: 600; color: var(--text); }
        .md-item.full { grid-column: 1/-1; }
        .modal-personnel-row { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.25rem; }
        .modal-personnel-chip { display: flex; align-items: center; gap: .4rem; border-radius: 8px; padding: .55rem 1rem; font-size: .84rem; }
        .modal-personnel-chip.purple { background: #f0e6f6; color: var(--purple); }
        .modal-personnel-chip.blue   { background: #d6eaf8; color: var(--blue); }
        .divider { border: none; border-top: 1px solid var(--border); margin: 1.25rem 0; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: .8rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: .4rem; }
        .form-group input { width: 100%; border: 1.5px solid var(--border); border-radius: 8px; padding: .7rem 1rem; font-family: 'Space Grotesk', sans-serif; font-size: .9rem; outline: none; transition: border-color .2s; }
        .form-group input:focus { border-color: var(--teal); }
        .form-group small { font-size: .78rem; color: var(--muted); margin-top: .3rem; display: block; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .update-preview { background: #e8f8f5; border: 1px solid #a9dfce; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .82rem; }
        .update-preview .preview-title { font-weight: 700; color: #16a085; margin-bottom: .3rem; display: flex; align-items: center; gap: .35rem; }

        /* View modal styles */
        .view-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px,1fr)); gap: .75rem; margin-bottom: 1.5rem; }
        .view-gallery-item { border-radius: 10px; overflow: hidden; aspect-ratio: 1; background: #f4f6f9; border: 2px solid var(--border); cursor: pointer; transition: all .2s; position: relative; }
        .view-gallery-item:hover { border-color: var(--teal); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(22,160,133,.15); }
        .view-gallery-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .view-gallery-placeholder { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--muted); font-size: 2.5rem; }
        .view-gdrive-badge { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,.55)); padding: .3rem .5rem; display: flex; align-items: center; gap: .3rem; }
        .view-gdrive-badge i { color: white; font-size: .85rem; }
        .view-gdrive-badge span { color: white; font-size: .65rem; font-weight: 600; }
        .view-section-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin: 1.25rem 0 .75rem; padding-bottom: .4rem; border-bottom: 2px solid var(--border); }
        .view-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); gap: .75rem; }
        .view-detail-item { background: #f8f9fa; border-radius: 8px; padding: .75rem 1rem; border-left: 3px solid var(--teal); }
        .view-detail-item.full { grid-column: 1/-1; }
        .view-detail-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); font-weight: 700; margin-bottom: .25rem; }
        .view-detail-value { font-size: .92rem; font-weight: 600; color: var(--text); word-break: break-word; }
        .view-asset-name { font-size: 1.3rem; font-weight: 800; color: var(--text); margin-bottom: .2rem; letter-spacing: -.3px; }
        .view-asset-type { font-size: .85rem; color: var(--muted); margin-bottom: .75rem; }
        .view-serial-badge { display: inline-flex; align-items: center; gap: .4rem; background: #2c3e50; color: #fff; font-family: 'Courier New', monospace; font-weight: 700; font-size: .82rem; letter-spacing: .5px; padding: .35rem .85rem; border-radius: 6px; margin-bottom: 1rem; }
        .view-serial-badge i { font-size: .9rem; opacity: .7; }
        .view-route-row { display: flex; align-items: center; gap: .75rem; background: #e8f8f5; border-radius: 10px; padding: .85rem 1rem; margin: .75rem 0; }
        .view-route-row .vr-loc { flex: 1; }
        .view-route-row .vr-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; color: var(--teal); font-weight: 700; }
        .view-route-row .vr-value { font-size: .95rem; font-weight: 700; color: var(--text); }
        .view-route-row .vr-arrow { font-size: 1.4rem; color: var(--teal); flex-shrink: 0; }
        .badge-condition { display: inline-block; padding: .25rem .65rem; border-radius: 6px; font-size: .78rem; font-weight: 700; text-transform: uppercase; }
        .badge-new  { background: #d5f5e3; color: #1e8449; }
        .badge-used { background: #fef3cd; color: #856404; }

        .toast { position: fixed; top: 20px; right: 20px; padding: .9rem 1.4rem; border-radius: 10px; color: white; font-weight: 600; font-size: .875rem; z-index: 10000; animation: toastIn .3s ease; box-shadow: 0 4px 16px rgba(0,0,0,.2); }
        .toast.success { background: var(--green); }
        .toast.error   { background: var(--red); }
        @keyframes toastIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <div class="page-header">
            <h1><i class='bx bx-package'></i> Receiving</h1>
            <p>Confirm delivery of released assets — inventory updates happen here upon receipt</p>
        </div>
        <div class="stats-row">
            <div class="stat-card"><div class="stat-icon teal"><i class='bx bx-package'></i></div><div><div class="stat-value" id="statReleased"><?php echo $totalReleased; ?></div><div class="stat-label">Awaiting Receipt</div></div></div>
            <div class="stat-card"><div class="stat-icon green"><i class='bx bx-check-double'></i></div><div><div class="stat-value"><?php echo $totalReceived; ?></div><div class="stat-label">Total Received</div></div></div>
            <div class="stat-card"><div class="stat-icon amber"><i class='bx bx-filter'></i></div><div><div class="stat-value"><?php echo count($rows); ?></div><div class="stat-label">Showing</div></div></div>
        </div>
        <div class="filters">
            <div class="search-wrap"><i class='bx bx-search'></i><input type="text" id="searchInput" placeholder="Search asset, requester, location..." value="<?php echo htmlspecialchars($search); ?>"></div>
            <input type="date" id="dateInput" value="<?php echo htmlspecialchars($dateFilter); ?>" title="Filter by released date">
            <select id="locSelect">
                <option value="">All Destinations</option>
                <?php foreach ($locations as $loc): ?><option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $locFilter === $loc ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc); ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-export" onclick="exportCSV()"><i class='bx bx-download'></i> Export CSV</button>
        </div>

        <div class="cards-grid" id="cardsGrid">
            <?php if (empty($rows)): ?>
                <div class="empty-state"><i class='bx bx-package'></i><p>No items awaiting receipt</p><small>Released assets will appear here once they're ready to be confirmed.</small></div>
            <?php else: ?>
                <?php foreach ($rows as $r):
                    $from       = $r['from_location'] ?: '—';
                    $to         = $r['to_location']   ?: ($r['location_received'] ?: '—');
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
                        <div class="loc"><div class="loc-label">From</div><div class="loc-value"><?php echo htmlspecialchars($from); ?></div></div>
                        <i class='bx bx-right-arrow-alt arrow'></i>
                        <div class="loc"><div class="loc-label">Destination</div><div class="loc-value"><?php echo htmlspecialchars($to); ?></div></div>
                    </div>
                    <div class="meta-grid">
                        <div class="meta-item"><div class="mlabel">Quantity</div><div class="mvalue"><?php echo $r['quantity']; ?> pcs</div></div>
                        <div class="meta-item"><div class="mlabel">Type</div><div class="mvalue"><?php echo htmlspecialchars($r['subcategory_name'] ?? '—'); ?></div></div>
                        <div class="meta-item"><div class="mlabel">Requested By</div><div class="mvalue"><?php echo htmlspecialchars($r['requested_by'] ?? '—'); ?></div></div>
                        <div class="meta-item"><div class="mlabel">Date Needed</div><div class="mvalue"><?php echo $r['date_needed'] ? date('M j, Y', strtotime($r['date_needed'])) : '—'; ?></div></div>
                        <?php if ($purpose !== '—'): ?><div class="meta-item" style="grid-column:1/-1;"><div class="mlabel">Purpose</div><div class="mvalue"><?php echo htmlspecialchars($purpose); ?></div></div><?php endif; ?>
                    </div>
                    <div class="card-actions">
                        <button class="btn btn-view" onclick="openView(<?php echo $r['id']; ?>)"><i class='bx bx-show'></i> View</button>
                        <button class="btn btn-teal" onclick="openReceive(<?php echo $r['id']; ?>)"><i class='bx bx-check-double'></i> Mark as Received</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         VIEW MODAL
    ═══════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal modal-wide" style="display:flex;flex-direction:column;">
            <div class="modal-header" style="background:linear-gradient(135deg,#16a085 0%,#0e6655 100%);color:white;border-bottom:none;flex-shrink:0;">
                <h3 style="color:white;"><i class='bx bx-info-circle'></i> Item Details</h3>
                <button class="modal-close" onclick="closeModal('viewModal')" style="color:white;background:rgba(255,255,255,.15);"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body" style="overflow-y:auto;flex:1;">
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
            <div class="modal-footer" style="flex-shrink:0;">
                <button class="btn btn-secondary" onclick="closeModal('viewModal')"><i class='bx bx-x'></i> Close</button>
                <button class="btn btn-teal" id="viewReceiveBtn" onclick="openReceiveFromView()"><i class='bx bx-check-double'></i> Mark as Received</button>
            </div>
        </div>
    </div>

    <!-- RECEIVE MODAL -->
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
                    <div class="modal-personnel-chip purple" id="receiveReleasedByChip" style="display:none;"><i class='bx bx-user-check'></i><span style="color:var(--muted);margin-right:.15rem;">Released by</span><strong id="receiveReleasedBy">—</strong></div>
                    <div class="modal-personnel-chip blue"   id="receiveDeliveredByChip" style="display:none;"><i class='bx bx-car'></i><span style="color:var(--muted);margin-right:.15rem;">Delivered by</span><strong id="receiveDeliveredBy">—</strong></div>
                </div>
                <hr class="divider">
                <div class="update-preview">
                    <div class="preview-title"><i class='bx bx-info-circle'></i> Inventory update upon receipt</div>
                    <div>Asset location will be updated to: <strong id="locationPreview">—</strong></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Destination Sub-Location</label>
                        <input type="text" id="receiveSubLocation" placeholder="e.g. Shelf 3, Rack B..." oninput="updateLocationPreview()">
                        <small>Exact spot inside <strong id="receiveToHint">destination</strong></small>
                    </div>
                    <div class="form-group">
                        <label>Received By <span style="color:var(--red)">*</span></label>
                        <input type="text" id="receiveReceivedBy" placeholder="Name of person receiving">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('receiveModal')">Cancel</button>
                <button class="btn btn-teal" id="confirmReceiveBtn" onclick="submitReceive()"><i class='bx bx-check-double'></i> Confirm Receipt</button>
            </div>
        </div>
    </div>

    <script>
        let currentToMain   = '';
        let currentViewId   = null;
        let cachedDetails   = {};

        function updateLocationPreview() {
            const sub     = document.getElementById('receiveSubLocation').value.trim();
            const preview = document.getElementById('locationPreview');
            if (preview) preview.textContent = sub ? `${currentToMain} / ${sub}` : currentToMain || '—';
        }

        function applyFilters() {
            const p = new URLSearchParams({ search: document.getElementById('searchInput').value, date: document.getElementById('dateInput').value, location: document.getElementById('locSelect').value });
            window.location.href = 'admin-receiving.php?' + p.toString();
        }
        document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
        document.getElementById('dateInput').addEventListener('change', applyFilters);
        document.getElementById('locSelect').addEventListener('change', applyFilters);

        // ── VIEW MODAL ────────────────────────────────────────────────────────
        async function openView(id) {
            currentViewId = id;
            document.getElementById('viewLoading').style.display = 'block';
            document.getElementById('viewContent').style.display = 'none';
            document.getElementById('viewModal').classList.add('active');

            try {
                const body = new FormData();
                body.append('action', 'get_details'); body.append('id', id);
                const res    = await fetch('admin-receiving.php', { method: 'POST', body });
                const result = await res.json();
                if (!result.success) { showToast('Could not load details.', 'error'); closeModal('viewModal'); return; }

                const d = result.data;
                cachedDetails[id] = d;

                document.getElementById('viewAssetName').textContent = `${d.brand || ''} ${d.model || ''}`.trim() || '—';
                document.getElementById('viewAssetType').textContent = [d.category_name, d.subcategory_name].filter(Boolean).join(' › ') || '';

                const serialBadge = document.getElementById('viewSerialBadge');
                if (d.serial_number) { document.getElementById('viewSerialText').textContent = d.serial_number; serialBadge.style.display = 'inline-flex'; }
                else { serialBadge.style.display = 'none'; }

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

                document.getElementById('viewFrom').textContent = d.from_location || '—';
                document.getElementById('viewTo').textContent   = d.to_main_location || d.to_location || '—';

                const purpose = (d.purpose || '').replace(/\s*\[(dest_asset_id|dest):\d+\]/g,'').replace(/\s*\|?\s*From:.+?→.+$/i,'').trim() || '—';
                document.getElementById('viewTxnGrid').innerHTML = `
                    <div class="view-detail-item"><div class="view-detail-label">Transaction #</div><div class="view-detail-value">#${d.id}</div></div>
                    <div class="view-detail-item"><div class="view-detail-label">Quantity</div><div class="view-detail-value"><strong style="font-size:1.1rem;">${d.quantity} pcs</strong></div></div>
                    <div class="view-detail-item"><div class="view-detail-label">Requested By</div><div class="view-detail-value">${d.requested_by || '—'}</div></div>
                    <div class="view-detail-item"><div class="view-detail-label">Date Needed</div><div class="view-detail-value">${d.date_needed ? new Date(d.date_needed).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—'}</div></div>
                    ${d.released_by ? `<div class="view-detail-item"><div class="view-detail-label">Released By</div><div class="view-detail-value">${d.released_by}</div></div>` : ''}
                    ${d.delivered_by ? `<div class="view-detail-item"><div class="view-detail-label">Delivered By</div><div class="view-detail-value">${d.delivered_by}</div></div>` : ''}
                    <div class="view-detail-item full"><div class="view-detail-label">Purpose</div><div class="view-detail-value">${purpose}</div></div>`;

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

        function openReceiveFromView() {
            closeModal('viewModal');
            openReceive(currentViewId);
        }

        // ── RECEIVE MODAL ─────────────────────────────────────────────────────
        async function openReceive(id) {
            document.getElementById('receiveId').value          = id;
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
            document.getElementById('receiveToHint').textContent       = 'destination';
            document.getElementById('locationPreview').textContent     = '—';
            document.getElementById('receivePersonnelRow').style.display    = 'none';
            document.getElementById('receiveReleasedByChip').style.display  = 'none';
            document.getElementById('receiveDeliveredByChip').style.display = 'none';
            document.getElementById('receiveThumb').innerHTML = "<i class='bx bx-image-alt'></i>";
            currentToMain = '';
            document.getElementById('receiveModal').classList.add('active');

            try {
                // Use cached if available
                let d = cachedDetails[id];
                if (!d) {
                    const body = new FormData();
                    body.append('action', 'get_details'); body.append('id', id);
                    const res    = await fetch('admin-receiving.php', { method: 'POST', body });
                    const result = await res.json();
                    if (!result.success) { showToast('Could not load details.', 'error'); return; }
                    d = result.data;
                    cachedDetails[id] = d;
                }

                currentToMain = d.to_main_location || d.to_location || '';
                document.getElementById('receiveAssetName').textContent = `${d.brand || ''} ${d.model || ''}`.trim() || '—';
                document.getElementById('receiveAssetType').textContent  = d.subcategory_name || '';
                if (d.serial_number) {
                    const s = document.getElementById('receiveAssetSerial');
                    s.textContent = d.serial_number; s.style.display = 'inline-block';
                }
                if (d.thumbnail && d.thumbnail_type === 'gdrive') {
                    document.getElementById('receiveThumb').innerHTML = `<img src="${d.thumbnail}" style="width:100%;height:100%;object-fit:cover;cursor:pointer;" onerror="this.parentElement.innerHTML='<i class=\\'bx bxl-google\\'></i>'" onclick="window.open('${d.thumbnail_url || d.thumbnail}','_blank')">`;
                } else if (d.thumbnail) {
                    document.getElementById('receiveThumb').innerHTML = `<img src="${d.thumbnail}" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='<i class=\\'bx bx-image-alt\\'></i>'">`;
                }
                document.getElementById('receiveFrom').textContent        = d.from_location || '—';
                document.getElementById('receiveTo').textContent          = d.to_main_location || d.to_location || '—';
                document.getElementById('receiveQty').textContent         = `${d.quantity || '—'} pcs`;
                document.getElementById('receiveReqBy').textContent       = d.requested_by || '—';
                document.getElementById('receiveDateNeeded').textContent  = d.date_needed ? new Date(d.date_needed).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
                document.getElementById('receivePurpose').textContent     = d.purpose || '—';
                document.getElementById('receiveToHint').textContent      = d.to_main_location || 'destination';
                document.getElementById('locationPreview').textContent    = d.to_main_location || '—';

                let showPersonnel = false;
                if (d.released_by)  { document.getElementById('receiveReleasedBy').textContent  = d.released_by;  document.getElementById('receiveReleasedByChip').style.display  = 'flex'; showPersonnel = true; }
                if (d.delivered_by) { document.getElementById('receiveDeliveredBy').textContent = d.delivered_by; document.getElementById('receiveDeliveredByChip').style.display = 'flex'; showPersonnel = true; }
                if (showPersonnel) document.getElementById('receivePersonnelRow').style.display = 'flex';
                if (d.to_sub_location) { document.getElementById('receiveSubLocation').value = d.to_sub_location; updateLocationPreview(); }
            } catch(e) { showToast('Failed to load details.', 'error'); }
        }

        async function submitReceive() {
            const id     = document.getElementById('receiveId').value;
            const rb     = document.getElementById('receiveReceivedBy').value.trim();
            const subLoc = document.getElementById('receiveSubLocation').value.trim();
            if (!rb) { showToast('Please enter who received the asset.', 'error'); return; }

            const btn = document.getElementById('confirmReceiveBtn');
            btn.disabled = true; btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Saving...";

            const body = new FormData();
            body.append('action', 'receive'); body.append('id', id);
            body.append('received_by', rb); body.append('to_sub_location', subLoc);
            const res    = await fetch('admin-receiving.php', { method: 'POST', body });
            const result = await res.json();

            btn.disabled = false; btn.innerHTML = "<i class='bx bx-check-double'></i> Confirm Receipt";
            showToast(result.message, result.success ? 'success' : 'error');
            if (result.success) {
                closeModal('receiveModal');
                document.getElementById('card-' + id)?.remove();
                updateStat(-1);
                delete cachedDetails[id];
            }
        }

        function exportCSV() {
            const headers = ['ID','Brand','Model','Serial','Type','Qty','From','To','Purpose','Requested By','Date Needed','Released By','Delivered By','Released At'];
            const data = <?php
                $out = [];
                foreach ($rows as $r) {
                    $from = $r['from_location'] ?: '—';
                    $to   = $r['to_location']   ?: ($r['location_received'] ?: '—');
                    $p    = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $r['purpose'] ?? '');
                    $p    = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $p));
                    $out[] = [$r['id'], $r['brand'] ?? '', $r['model'] ?? '', $r['serial_number'] ?? '', $r['subcategory_name'] ?? '', $r['quantity'], $from, $to, $p, $r['requested_by'] ?? '', $r['date_needed'] ?? '', $r['released_by'] ?? '', $r['delivered_by'] ?? '', $r['released_at'] ?? ''];
                }
                echo json_encode($out);
            ?>;
            const csv = [headers, ...data].map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
            const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
            a.download = 'receiving_' + new Date().toISOString().slice(0,10) + '.csv'; a.click();
        }

        function updateStat(delta) { const s = document.getElementById('statReleased'); if (s) s.textContent = Math.max(0, parseInt(s.textContent) + delta); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        document.querySelectorAll('.modal-overlay').forEach(m => { m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); }); });
        function showToast(msg, type = 'success') {
            const t = document.createElement('div'); t.className = 'toast ' + type; t.textContent = msg;
            document.body.appendChild(t); setTimeout(() => t.remove(), 3000);
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>