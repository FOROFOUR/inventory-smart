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

    // ── UPDATE PREP STEP ─────────────────────────────────────────────────────
    if ($action === 'update_step') {
        $step   = max(0, min(4, (int) ($_POST['step'] ?? 0)));
        $labels = ['Not Started', 'Located', 'Checked', 'Packed', 'Ready to Release'];

        $stmt = $conn->prepare("UPDATE pull_out_transactions SET prep_step = ? WHERE id = ? AND status = 'CONFIRMED'");
        $stmt->bind_param("ii", $step, $id);
        $stmt->execute();

        $desc = "Pull-out #$id prep step: {$labels[$step]} by $user_name";
        $log  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'PREP_STEP', ?)");
        $log->bind_param("ss", $user_name, $desc);
        $log->execute();

        echo json_encode(['success' => true, 'step' => $step, 'label' => $labels[$step]]);
        exit();
    }

    // ── GET DETAILS (for release modal preview + view modal) ──────────────────
    if ($action === 'get_details') {
        $stmt = $conn->prepare("
            SELECT p.*, a.brand, a.model, a.serial_number, a.location AS asset_location,
                   a.sub_location AS asset_sub_location, a.condition, a.status AS asset_status,
                   a.description AS asset_description,
                   c.name AS category_name, sc.name AS subcategory_name
            FROM pull_out_transactions p
            LEFT JOIN assets         a  ON p.asset_id        = a.id
            LEFT JOIN categories     c  ON a.category_id     = c.id
            LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
            WHERE p.id = ? AND p.status = 'CONFIRMED'
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Not found.']);
            exit();
        }

        $row['purpose'] = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $row['purpose'] ?? ''));
        $row['purpose'] = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $row['purpose']));

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
                    $images[] = [
                        'type'  => 'gdrive',
                        'thumb' => "https://drive.google.com/thumbnail?id={$m[1]}&sz=w400",
                        'url'   => $img['drive_url'],
                    ];
                } else {
                    $images[] = ['type' => 'gdrive_folder', 'url' => $img['drive_url'], 'thumb' => null];
                }
            } else if (!empty($img['image_path']) && $img['image_path'] !== 'gdrive_folder') {
                $images[] = ['type' => 'image', 'url' => '/' . ltrim($img['image_path'], '/'), 'thumb' => null];
            }
        }
        $row['images'] = $images;

        // First image thumbnail
        if (!empty($images)) {
            $first = $images[0];
            $row['thumbnail']      = $first['thumb'] ?? $first['url'];
            $row['thumbnail_type'] = $first['type'];
            $row['thumbnail_url']  = $first['url'];
        } else {
            $row['thumbnail'] = null; $row['thumbnail_type'] = null; $row['thumbnail_url'] = null;
        }

        echo json_encode(['success' => true, 'data' => $row]);
        exit();
    }

    // ── RELEASE ───────────────────────────────────────────────────────────────
    if ($action === 'release') {
        $released_by  = htmlspecialchars(trim($_POST['released_by']  ?? ''));
        $delivered_by = htmlspecialchars(trim($_POST['delivered_by'] ?? ''));

        if (empty($released_by))  { echo json_encode(['success' => false, 'message' => 'Released By is required.']);  exit(); }
        if (empty($delivered_by)) { echo json_encode(['success' => false, 'message' => 'Delivered By is required.']); exit(); }

        $txnStmt = $conn->prepare("SELECT id, asset_id, quantity, from_location, to_location FROM pull_out_transactions WHERE id = ? AND status = 'CONFIRMED'");
        $txnStmt->bind_param("i", $id);
        $txnStmt->execute();
        $txn = $txnStmt->get_result()->fetch_assoc();

        if (!$txn) {
            echo json_encode(['success' => false, 'message' => 'Already processed or not found.']);
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

        if ($rel->affected_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Already processed.']);
            exit();
        }

        $fromLocation = $txn['from_location'] ?? '';
        $toLocation   = $txn['to_location']   ?? '';

        $desc = "Pull-out #$id RELEASED by $released_by (Delivered by: $delivered_by). Asset #{$txn['asset_id']} x{$txn['quantity']} from {$fromLocation} → {$toLocation}. Awaiting receipt confirmation.";
        $log  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'RELEASE_PULLOUT', ?)");
        $log->bind_param("ss", $user_name, $desc);
        $log->execute();

        echo json_encode(['success' => true, 'message' => "Request #$id marked as Released. Now awaiting confirmation in Receiving."]);
        exit();
    }

    // ── REVERT TO PENDING ─────────────────────────────────────────────────────
    if ($action === 'revert') {
        $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = 'PENDING', prep_step = 0 WHERE id = ? AND status = 'CONFIRMED'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $desc = "Pull-out #$id reverted to PENDING by $user_name";
            $log  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'REVERT_PULLOUT', ?)");
            $log->bind_param("ss", $user_name, $desc);
            $log->execute();
            echo json_encode(['success' => true, 'message' => "Request #$id reverted to Pending."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Already processed.']);
        }
        exit();
    }

    // ── CANCEL ────────────────────────────────────────────────────────────────
    if ($action === 'cancel') {
        $reason = htmlspecialchars(trim($_POST['reason'] ?? ''));
        $stmt   = $conn->prepare("UPDATE pull_out_transactions SET status = 'CANCELLED', prep_step = 0 WHERE id = ? AND status = 'CONFIRMED'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $desc = "Pull-out #$id CANCELLED by $user_name" . ($reason ? ". Reason: $reason" : '');
            $log  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'CANCEL_PULLOUT', ?)");
            $log->bind_param("ss", $user_name, $desc);
            $log->execute();
            echo json_encode(['success' => true, 'message' => "Request #$id cancelled."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Already processed.']);
        }
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
           WHERE p.status = 'CONFIRMED'";
$params = [];
$types  = '';

if ($search !== '') {
    $sql    .= " AND (a.brand LIKE ? OR a.model LIKE ? OR p.requested_by LIKE ? OR p.from_location LIKE ? OR p.to_location LIKE ?)";
    $l       = "%$search%";
    $params  = array_merge($params, [$l, $l, $l, $l, $l]);
    $types  .= 'sssss';
}
if ($dateFilter !== '') { $sql .= " AND DATE(p.created_at) = ?"; $params[] = $dateFilter; $types .= 's'; }
if ($locFilter !== '') {
    $sql .= " AND (p.from_location LIKE ? OR p.to_location LIKE ?)";
    $ll = $locFilter . '%'; $params = array_merge($params, [$ll, $ll]); $types .= 'ss';
}
$sql .= " ORDER BY p.created_at ASC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows   = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;

$totalConfirmed = (int) $conn->query("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'CONFIRMED'")->fetch_row()[0];
$readyCount     = count(array_filter($rows, fn($r) => ($r['prep_step'] ?? 0) >= 4));
$notStarted     = count(array_filter($rows, fn($r) => ($r['prep_step'] ?? 0) === 0));

$locRows   = $conn->query("
    SELECT DISTINCT TRIM(SUBSTRING_INDEX(loc, ' / ', 1)) AS main_loc
    FROM (
        SELECT from_location AS loc FROM pull_out_transactions WHERE from_location IS NOT NULL AND from_location != '' AND status = 'CONFIRMED'
        UNION ALL
        SELECT to_location   AS loc FROM pull_out_transactions WHERE to_location   IS NOT NULL AND to_location   != '' AND status = 'CONFIRMED'
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
    <title>Preparing — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --purple: #8e44ad; --green: #27ae60; --red: #e74c3c; --amber: #f39c12;
            --bg: #f4f6f9; --white: #fff; --text: #2c3e50; --muted: #7f8c8d;
            --border: #e8ecf0; --shadow: 0 2px 12px rgba(0,0,0,.07); --radius: 12px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Space Grotesk', sans-serif; }
        body { background: var(--bg); color: var(--text); }
        .content { margin-left: 88px; padding: 2rem; transition: margin-left .3s; }
        .sidebar:not(.close)~.content { margin-left: 260px; }
        .page-header { background: linear-gradient(135deg, #8e44ad 0%, #6c3483 100%); border-radius: 16px; padding: 2rem 2rem 1.5rem; color: white; margin-bottom: 1.5rem; position: relative; overflow: hidden; }
        .page-header::before { content: ''; position: absolute; top: -50%; right: -5%; width: 300px; height: 300px; background: rgba(255,255,255,.08); border-radius: 50%; }
        .page-header h1 { font-size: 1.6rem; font-weight: 700; position: relative; z-index: 1; display: flex; align-items: center; gap: .6rem; }
        .page-header p  { font-size: .9rem; opacity: .85; margin-top: .25rem; position: relative; z-index: 1; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--white); border-radius: var(--radius); padding: 1.25rem 1.5rem; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1rem; }
        .stat-icon { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .stat-icon.purple { background: #f0e6f6; color: var(--purple); }
        .stat-icon.green  { background: #d5f5e3; color: var(--green); }
        .stat-icon.amber  { background: #fef3cd; color: var(--amber); }
        .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1; }
        .stat-label { font-size: .75rem; color: var(--muted); margin-top: 2px; text-transform: uppercase; letter-spacing: .5px; }
        .filters { background: var(--white); border-radius: var(--radius); padding: 1rem 1.25rem; box-shadow: var(--shadow); display: flex; gap: .75rem; flex-wrap: wrap; align-items: center; margin-bottom: 1.25rem; }
        .filters input, .filters select { border: 1.5px solid var(--border); border-radius: 8px; padding: .6rem 1rem; font-family: 'Space Grotesk', sans-serif; font-size: .875rem; color: var(--text); outline: none; background: white; transition: border-color .2s; }
        .filters input:focus, .filters select:focus { border-color: var(--purple); }
        .search-wrap { position: relative; flex: 1; min-width: 220px; }
        .search-wrap input { padding-left: 2.4rem; width: 100%; }
        .search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); }
        .btn { padding: .6rem 1.2rem; border: none; border-radius: 8px; font-family: 'Space Grotesk', sans-serif; font-size: .875rem; font-weight: 600; cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: .4rem; }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .btn-success  { background: var(--green); color: white; }
        .btn-success:hover:not(:disabled) { background: #219a52; }
        .btn-danger   { background: var(--red); color: white; }
        .btn-danger:hover:not(:disabled)  { background: #c0392b; }
        .btn-warning  { background: var(--amber); color: white; }
        .btn-warning:hover:not(:disabled) { background: #e67e22; }
        .btn-secondary { background: #ecf0f1; color: var(--text); }
        .btn-secondary:hover { background: #d5dbdb; }
        .btn-export   { background: #16a085; color: white; }
        .btn-export:hover { background: #138d75; }
        .btn-view { background: #2980b9; color: white; }
        .btn-view:hover { background: #2471a3; }
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.25rem; }
        .prep-card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); border-left: 4px solid var(--purple); padding: 1.25rem 1.5rem; transition: transform .2s, box-shadow .2s, border-left-color .3s; }
        .prep-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
        .prep-card.step-ready { border-left-color: var(--green); }
        .prep-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: .9rem; }
        .req-id    { font-size: .78rem; color: var(--muted); font-weight: 600; text-transform: uppercase; }
        .req-asset { font-size: 1rem; font-weight: 700; color: var(--text); }
        .req-model { font-size: .82rem; color: var(--muted); }
        .step-progress { margin: .85rem 0 1rem; }
        .step-track    { display: flex; align-items: center; margin-bottom: .5rem; }
        .step-node { width: 30px; height: 30px; border-radius: 50%; background: #e8ecf0; color: var(--muted); display: flex; align-items: center; justify-content: center; font-size: .78rem; font-weight: 700; flex-shrink: 0; cursor: pointer; transition: all .22s; position: relative; z-index: 1; border: 2px solid #e8ecf0; user-select: none; }
        .step-node.done   { background: var(--purple); color: white; border-color: var(--purple); }
        .step-node.ready  { background: var(--green);  color: white; border-color: var(--green); }
        .step-node.active { border-color: var(--purple); background: white; color: var(--purple); box-shadow: 0 0 0 3px rgba(142,68,173,.18); }
        .step-node:hover  { transform: scale(1.12); }
        .step-line { flex: 1; height: 3px; background: #e8ecf0; transition: background .3s; }
        .step-line.done  { background: var(--purple); }
        .step-line.ready { background: var(--green); }
        .step-labels { display: flex; justify-content: space-between; margin-top: .3rem; }
        .step-label  { font-size: .68rem; color: var(--muted); text-align: center; flex: 1; line-height: 1.2; font-weight: 500; }
        .step-label.done  { color: var(--purple); font-weight: 600; }
        .step-label.ready { color: var(--green);  font-weight: 600; }
        .step-status-text { text-align: center; font-size: .82rem; font-weight: 600; margin-top: .5rem; padding: .35rem .75rem; border-radius: 6px; background: #f0e6f6; color: var(--purple); }
        .step-status-text.ready { background: #d5f5e3; color: #1e8449; }
        .prep-route { display: flex; align-items: center; gap: .6rem; background: #f8f9fa; border-radius: 8px; padding: .7rem 1rem; margin: .75rem 0; }
        .prep-route .loc       { flex: 1; }
        .prep-route .loc-label { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; font-weight: 600; }
        .prep-route .loc-value { font-size: .9rem; font-weight: 600; }
        .prep-route .arrow     { color: var(--purple); font-size: 1.2rem; }
        .prep-meta { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; font-size: .82rem; margin-bottom: 1rem; }
        .prep-meta-item .mlabel { color: var(--muted); font-size: .74rem; text-transform: uppercase; letter-spacing: .4px; }
        .prep-meta-item .mvalue { font-weight: 600; color: var(--text); }
        .prep-actions { display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .75rem; padding-top: .75rem; border-top: 1px solid var(--border); }
        .empty-state { text-align: center; padding: 3.5rem; color: var(--muted); grid-column: 1/-1; }
        .empty-state i { font-size: 3.5rem; opacity: .25; display: block; margin-bottom: .75rem; }

        /* ── MODALS ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(3px); z-index: 9999; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--white); border-radius: 16px; width: 95%; max-width: 460px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.25); animation: slideUp .25s ease; }
        .modal.modal-wide { max-width: 680px; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
        .modal-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--muted); border-radius: 6px; padding: .2rem .4rem; }
        .modal-close:hover { background: var(--border); }
        .modal-body   { padding: 1.5rem; max-height: 75vh; overflow-y: auto; }
        .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; gap: .75rem; justify-content: flex-end; }

        /* ── RELEASE MODAL ── */
        .release-asset-card { display: flex; align-items: center; gap: 1rem; background: #f8f9fa; border-radius: 10px; padding: 1rem; margin-bottom: 1.25rem; border: 1px solid var(--border); }
        .release-thumb { width: 58px; height: 58px; border-radius: 8px; background: #e8ecf0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--muted); overflow: hidden; }
        .release-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .release-info .asset-name   { font-size: 1.05rem; font-weight: 800; color: var(--text); letter-spacing: -.2px; }
        .release-info .asset-type   { font-size: .82rem; color: var(--muted); margin-top: 2px; }
        .release-info .asset-serial {
            font-size: .78rem; font-family: 'Courier New', monospace; font-weight: 700;
            background: #2c3e50; color: #fff;
            padding: 2px 8px; border-radius: 4px;
            display: inline-block; margin-top: 5px;
            letter-spacing: .5px;
        }
        .release-details { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem .75rem; margin-bottom: 1.25rem; }
        .rd-item .rd-label { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; font-weight: 600; }
        .rd-item .rd-value { font-size: .88rem; font-weight: 600; color: var(--text); }
        .divider { border: none; border-top: 1px solid var(--border); margin: 1.25rem 0; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: .8rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: .4rem; }
        .form-group input, .form-group textarea { width: 100%; border: 1.5px solid var(--border); border-radius: 8px; padding: .7rem 1rem; font-family: 'Space Grotesk', sans-serif; font-size: .9rem; outline: none; transition: border-color .2s; }
        .form-group input:focus, .form-group textarea:focus { border-color: var(--purple); }
        .info-note { background: #f0e6f6; border: 1px solid #d7bde2; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .82rem; color: #6c3483; display: flex; align-items: flex-start; gap: .5rem; }
        .info-note i { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

        /* ── VIEW DETAILS MODAL ── */
        .view-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: .75rem; margin-bottom: 1.5rem; }
        .view-gallery-item { border-radius: 10px; overflow: hidden; aspect-ratio: 1; background: #f4f6f9; border: 2px solid var(--border); cursor: pointer; transition: all .2s; position: relative; }
        .view-gallery-item:hover { border-color: var(--purple); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(142,68,173,.15); }
        .view-gallery-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .view-gallery-placeholder { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--muted); font-size: 2.5rem; }
        .view-gdrive-badge { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,.55)); padding: .3rem .5rem; display: flex; align-items: center; gap: .3rem; }
        .view-gdrive-badge i { color: white; font-size: .85rem; }
        .view-gdrive-badge span { color: white; font-size: .65rem; font-weight: 600; }

        .view-section-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin: 1.25rem 0 .75rem; padding-bottom: .4rem; border-bottom: 2px solid var(--border); }
        .view-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: .75rem; }
        .view-detail-item { background: #f8f9fa; border-radius: 8px; padding: .75rem 1rem; border-left: 3px solid var(--purple); }
        .view-detail-item.full { grid-column: 1 / -1; }
        .view-detail-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); font-weight: 700; margin-bottom: .25rem; }
        .view-detail-value { font-size: .92rem; font-weight: 600; color: var(--text); word-break: break-word; }
        .view-asset-name { font-size: 1.3rem; font-weight: 800; color: var(--text); margin-bottom: .2rem; letter-spacing: -.3px; }
        .view-asset-type { font-size: .85rem; color: var(--muted); margin-bottom: .75rem; }
        .view-serial-badge {
            display: inline-flex; align-items: center; gap: .4rem;
            background: #2c3e50; color: #fff;
            font-family: 'Courier New', monospace; font-weight: 700;
            font-size: .82rem; letter-spacing: .5px;
            padding: .35rem .85rem; border-radius: 6px;
            margin-bottom: 1rem;
        }
        .view-serial-badge i { font-size: .9rem; opacity: .7; }
        .view-route-row { display: flex; align-items: center; gap: .75rem; background: #f0e6f6; border-radius: 10px; padding: .85rem 1rem; margin: .75rem 0; }
        .view-route-row .vr-loc { flex: 1; }
        .view-route-row .vr-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; color: var(--purple); font-weight: 700; }
        .view-route-row .vr-value { font-size: .95rem; font-weight: 700; color: var(--text); }
        .view-route-row .vr-arrow { font-size: 1.4rem; color: var(--purple); flex-shrink: 0; }
        .badge-condition { display: inline-block; padding: .25rem .65rem; border-radius: 6px; font-size: .78rem; font-weight: 700; text-transform: uppercase; }
        .badge-new { background: #d5f5e3; color: #1e8449; }
        .badge-used { background: #fef3cd; color: #856404; }

        /* ── TOAST ── */
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
            <h1><i class='bx bx-loader-circle'></i> Preparing</h1>
            <p>Track and update preparation steps — tap each step node to progress</p>
        </div>
        <div class="stats-row">
            <div class="stat-card"><div class="stat-icon purple"><i class='bx bx-loader-circle'></i></div><div><div class="stat-value" id="statConfirmed"><?php echo $totalConfirmed; ?></div><div class="stat-label">Total Preparing</div></div></div>
            <div class="stat-card"><div class="stat-icon green"><i class='bx bx-check-circle'></i></div><div><div class="stat-value"><?php echo $readyCount; ?></div><div class="stat-label">Ready to Release</div></div></div>
            <div class="stat-card"><div class="stat-icon amber"><i class='bx bx-time'></i></div><div><div class="stat-value"><?php echo $notStarted; ?></div><div class="stat-label">Not Started</div></div></div>
        </div>
        <div class="filters">
            <div class="search-wrap"><i class='bx bx-search'></i><input type="text" id="searchInput" placeholder="Search asset, requester, location..." value="<?php echo htmlspecialchars($search); ?>"></div>
            <input type="date" id="dateInput" value="<?php echo htmlspecialchars($dateFilter); ?>">
            <select id="locSelect">
                <option value="">All Locations</option>
                <?php foreach ($locations as $loc): ?><option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $locFilter === $loc ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc); ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-export" onclick="exportCSV()"><i class='bx bx-download'></i> Export CSV</button>
        </div>

        <div class="cards-grid" id="cardsGrid">
            <?php if (empty($rows)): ?>
                <div class="empty-state"><i class='bx bx-loader-circle'></i><p>No items being prepared</p></div>
            <?php else: ?>
                <?php foreach ($rows as $r):
                    $from       = $r['from_location'] ?: '—';
                    $to         = $r['to_location'] ?: ($r['location_received'] ?: '—');
                    $purpose    = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $r['purpose'] ?? '');
                    $purpose    = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $purpose)) ?: '—';
                    $step       = (int) ($r['prep_step'] ?? 0);
                    $isReady    = $step >= 4;
                    $titles     = ['', 'Locate asset', 'Check condition', 'Pack & label', 'Ready to release'];
                    $stepLabels = ['', 'Located', 'Checked', 'Packed', 'Ready to Release'];
                ?>
                <div class="prep-card <?php echo $isReady ? 'step-ready' : ''; ?>" id="card-<?php echo $r['id']; ?>">
                    <div class="prep-card-header">
                        <div>
                            <div class="req-id">Request #<?php echo $r['id']; ?></div>
                            <div class="req-asset"><?php echo htmlspecialchars($r['brand'] ?? '—'); ?></div>
                            <?php if ($r['model']): ?><div class="req-model"><?php echo htmlspecialchars($r['model']); ?></div><?php endif; ?>
                            <?php if ($r['serial_number']): ?><div style="font-size:.75rem;background:#f1f2f6;padding:1px 6px;border-radius:4px;display:inline-block;margin-top:3px;font-family:monospace;color:var(--muted);"><?php echo htmlspecialchars($r['serial_number']); ?></div><?php endif; ?>
                        </div>
                        <span id="badge-<?php echo $r['id']; ?>" style="background:<?php echo $isReady ? '#d5f5e3' : '#f0e6f6'; ?>;color:<?php echo $isReady ? '#1e8449' : 'var(--purple)'; ?>;padding:.3rem .7rem;border-radius:6px;font-size:.74rem;font-weight:700;text-transform:uppercase;white-space:nowrap;">
                            <?php echo $isReady ? '✓ Ready' : 'Confirmed'; ?>
                        </span>
                    </div>
                    <div class="step-progress">
                        <div class="step-track">
                            <?php for ($i = 1; $i <= 4; $i++):
                                $isDone   = $step >= $i;
                                $isActive = !$isDone && $step === $i - 1;
                                $cls      = $isDone ? ($i === 4 ? 'ready' : 'done') : ($isActive ? 'active' : '');
                            ?>
                                <div class="step-node <?php echo $cls; ?>" id="node-<?php echo $r['id'] . '-' . $i; ?>" onclick="setStep(<?php echo $r['id'] . ',' . $i; ?>)" title="<?php echo $titles[$i]; ?>">
                                    <?php echo $isDone ? "<i class='bx bx-check' style='font-size:.9rem;'></i>" : $i; ?>
                                </div>
                                <?php if ($i < 4): ?>
                                    <div class="step-line <?php echo $step >= $i ? ($step >= 4 ? 'ready' : 'done') : ''; ?>" id="line-<?php echo $r['id'] . '-' . $i; ?>"></div>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <div class="step-labels">
                            <?php foreach (['Locate', 'Check', 'Pack', 'Ready'] as $idx => $lbl): ?>
                                <span class="step-label <?php echo $step >= ($idx + 1) ? ($step >= 4 ? 'ready' : 'done') : ''; ?>"><?php echo $lbl; ?></span>
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
                        <div class="loc"><div class="loc-label">From</div><div class="loc-value"><?php echo htmlspecialchars($from); ?></div></div>
                        <i class='bx bx-right-arrow-alt arrow'></i>
                        <div class="loc"><div class="loc-label">To</div><div class="loc-value"><?php echo htmlspecialchars($to); ?></div></div>
                    </div>
                    <div class="prep-meta">
                        <div class="prep-meta-item"><div class="mlabel">Quantity</div><div class="mvalue"><?php echo $r['quantity']; ?> pcs</div></div>
                        <div class="prep-meta-item"><div class="mlabel">Type</div><div class="mvalue"><?php echo htmlspecialchars($r['subcategory_name'] ?? '—'); ?></div></div>
                        <div class="prep-meta-item"><div class="mlabel">Requested By</div><div class="mvalue"><?php echo htmlspecialchars($r['requested_by'] ?? '—'); ?></div></div>
                        <div class="prep-meta-item"><div class="mlabel">Date Needed</div><div class="mvalue"><?php echo $r['date_needed'] ? date('M j, Y', strtotime($r['date_needed'])) : '—'; ?></div></div>
                        <?php if ($purpose !== '—'): ?>
                        <div class="prep-meta-item" style="grid-column:1/-1;"><div class="mlabel">Purpose</div><div class="mvalue"><?php echo htmlspecialchars($purpose); ?></div></div>
                        <?php endif; ?>
                    </div>
                    <div class="prep-actions">
                        <button class="btn btn-view" onclick="openView(<?php echo $r['id']; ?>)"><i class='bx bx-show'></i> View</button>
                        <button class="btn btn-danger"  onclick="openCancel(<?php echo $r['id']; ?>)"><i class='bx bx-x'></i> Cancel</button>
                        <button class="btn btn-warning" onclick="revertToPending(<?php echo $r['id']; ?>)"><i class='bx bx-undo'></i> Revert</button>
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

    <!-- ═══════════════════════════════════════════════════════════
         VIEW DETAILS MODAL
    ═══════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal modal-wide">
            <div class="modal-header" style="background: linear-gradient(135deg, #8e44ad 0%, #6c3483 100%); color: white; border-bottom: none;">
                <h3 style="color:white;"><i class='bx bx-info-circle'></i> Asset Details</h3>
                <button class="modal-close" onclick="closeModal('viewModal')" style="color:white; background:rgba(255,255,255,.15);"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <!-- Loading state -->
                <div id="viewLoading" style="text-align:center;padding:2rem;color:var(--muted);">
                    <i class='bx bx-loader-alt bx-spin' style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                    Loading details...
                </div>
                <!-- Content -->
                <div id="viewContent" style="display:none;">
                    <!-- Asset name + serial -->
                    <div id="viewAssetName" class="view-asset-name"></div>
                    <div id="viewAssetType" class="view-asset-type"></div>
                    <div id="viewSerialBadge" style="display:none;" class="view-serial-badge">
                        <i class='bx bx-barcode'></i>
                        <span id="viewSerialText"></span>
                    </div>

                    <!-- Photo gallery -->
                    <div class="view-section-title"><i class='bx bx-image-alt' style="margin-right:.3rem;"></i>Photos</div>
                    <div class="view-gallery" id="viewGallery"></div>

                    <!-- Transfer info -->
                    <div class="view-section-title"><i class='bx bx-transfer-alt' style="margin-right:.3rem;"></i>Transfer Info</div>
                    <div id="viewRouteRow" class="view-route-row">
                        <div class="vr-loc"><div class="vr-label">From</div><div class="vr-value" id="viewFrom">—</div></div>
                        <i class='bx bx-right-arrow-alt vr-arrow'></i>
                        <div class="vr-loc"><div class="vr-label">To</div><div class="vr-value" id="viewTo">—</div></div>
                    </div>
                    <div class="view-detail-grid" id="viewTxnGrid"></div>

                    <!-- Asset info -->
                    <div class="view-section-title"><i class='bx bx-package' style="margin-right:.3rem;"></i>Asset Info</div>
                    <div class="view-detail-grid" id="viewAssetGrid"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('viewModal')"><i class='bx bx-x'></i> Close</button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         RELEASE MODAL
    ═══════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="releaseModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class='bx bx-paper-plane' style="color:var(--purple)"></i> Confirm Release</h3>
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
                <div class="info-note">
                    <i class='bx bx-info-circle'></i>
                    <span>Releasing lang ang gagawin dito. Ang inventory update (location, balance) ay mangyayari sa <strong>Receiving</strong> kapag na-confirm na ang resibo.</span>
                </div>
                <hr class="divider">
                <div class="form-group"><label>Released By <span style="color:var(--red)">*</span></label><input type="text" id="releaseReleasedBy" placeholder="Name of person releasing the asset"></div>
                <div class="form-group"><label>Delivered By <span style="color:var(--red)">*</span></label><input type="text" id="releaseDeliveredBy" placeholder="Name of person delivering the asset"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('releaseModal')">Cancel</button>
                <button class="btn btn-success" onclick="submitRelease()"><i class='bx bx-paper-plane'></i> Confirm Release</button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         CANCEL MODAL
    ═══════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="cancelModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class='bx bx-x-circle' style="color:var(--red)"></i> Cancel Transfer</h3>
                <button class="modal-close" onclick="closeModal('cancelModal')"><i class='bx bx-x'></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cancelId">
                <div class="form-group"><label>Reason (optional)</label><textarea id="cancelReason" rows="3" placeholder="Enter reason..."></textarea></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('cancelModal')">Back</button>
                <button class="btn btn-danger" onclick="submitCancel()"><i class='bx bx-block'></i> Confirm Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const STEP_LABELS = ['Not Started', 'Located', 'Checked', 'Packed', 'Ready to Release'];

        function applyFilters() {
            const p = new URLSearchParams({ search: document.getElementById('searchInput').value, date: document.getElementById('dateInput').value, location: document.getElementById('locSelect').value });
            window.location.href = 'admin-preparing.php?' + p.toString();
        }
        document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
        document.getElementById('dateInput').addEventListener('change', applyFilters);
        document.getElementById('locSelect').addEventListener('change', applyFilters);

        async function setStep(id, clickedStep) {
            const cur     = getCurrentStep(id);
            const newStep = cur === clickedStep ? clickedStep - 1 : clickedStep;
            const body    = new FormData();
            body.append('action', 'update_step'); body.append('id', id); body.append('step', newStep);
            try {
                const res    = await fetch('admin-preparing.php', { method: 'POST', body });
                const result = await res.json();
                if (!result.success) { showToast(result.message, 'error'); return; }
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
                if (step >= i) { node.classList.add(i === 4 ? 'ready' : 'done'); node.innerHTML = "<i class='bx bx-check' style='font-size:.9rem;'></i>"; }
                else if (step === i - 1) { node.classList.add('active'); node.textContent = i; }
                else { node.textContent = i; }
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
            if (txt) { txt.className = 'step-status-text' + (isReady ? ' ready' : ''); txt.textContent = isReady ? '✓ Ready to Release' : step === 0 ? 'Tap a step to start preparing' : `Step ${step} / 4 — ${STEP_LABELS[step]}`; }
            const badge = document.getElementById('badge-' + id);
            if (badge) { badge.style.background = isReady ? '#d5f5e3' : '#f0e6f6'; badge.style.color = isReady ? '#1e8449' : 'var(--purple)'; badge.textContent = isReady ? '✓ Ready' : 'Confirmed'; }
            const card = document.getElementById('card-' + id);
            if (card) card.classList.toggle('step-ready', isReady);
            const btn = document.getElementById('releaseBtn-' + id);
            if (btn) { btn.disabled = !isReady; btn.title = isReady ? '' : 'Complete all steps first'; }
        }

        // ── VIEW DETAILS MODAL ───────────────────────────────────────────────
        async function openView(id) {
            document.getElementById('viewLoading').style.display  = 'block';
            document.getElementById('viewContent').style.display  = 'none';
            document.getElementById('viewModal').classList.add('active');

            try {
                const body = new FormData();
                body.append('action', 'get_details'); body.append('id', id);
                const res    = await fetch('admin-preparing.php', { method: 'POST', body });
                const result = await res.json();
                if (!result.success) { showToast('Could not load details.', 'error'); closeModal('viewModal'); return; }

                const d = result.data;

                // Asset name + serial
                document.getElementById('viewAssetName').textContent = `${d.brand || ''} ${d.model || ''}`.trim() || '—';
                document.getElementById('viewAssetType').textContent = [d.category_name, d.subcategory_name].filter(Boolean).join(' › ') || '';

                const serialBadge = document.getElementById('viewSerialBadge');
                if (d.serial_number) {
                    document.getElementById('viewSerialText').textContent = d.serial_number;
                    serialBadge.style.display = 'inline-flex';
                } else {
                    serialBadge.style.display = 'none';
                }

                // Gallery
                const gallery = document.getElementById('viewGallery');
                gallery.innerHTML = '';
                if (d.images && d.images.length > 0) {
                    d.images.forEach(img => {
                        const tile = document.createElement('div');
                        tile.className = 'view-gallery-item';
                        if (img.type === 'gdrive' && img.thumb) {
                            tile.onclick = () => window.open(img.url, '_blank');
                            tile.innerHTML = `
                                <img src="${img.thumb}" alt="Asset photo"
                                     onerror="this.parentElement.innerHTML='<div class=\\"view-gallery-placeholder\\"><i class=\\"bx bxl-google\\"></i></div>'">
                                <div class="view-gdrive-badge">
                                    <i class='bx bxl-google'></i>
                                    <span>Open in Drive</span>
                                </div>`;
                        } else if (img.type === 'gdrive_folder') {
                            tile.onclick = () => window.open(img.url, '_blank');
                            tile.style.background = 'linear-gradient(135deg,#e8f5e9,#f1f8e9)';
                            tile.style.border = '2px solid #a5d6a7';
                            tile.innerHTML = `<div class="view-gallery-placeholder" style="flex-direction:column;gap:.4rem;font-size:1rem;">
                                <i class='bx bxl-google' style="font-size:2rem;color:#1a73e8;"></i>
                                <span style="font-size:.7rem;color:#1a73e8;font-weight:600;">View on Drive</span>
                            </div>`;
                        } else {
                            tile.onclick = () => window.open(img.url, '_blank');
                            tile.innerHTML = `<img src="${img.url}" alt="Asset photo" onerror="this.parentElement.innerHTML='<div class=\\"view-gallery-placeholder\\"><i class=\\"bx bx-image\\"></i></div>'">`;
                        }
                        gallery.appendChild(tile);
                    });
                } else {
                    gallery.innerHTML = `<div class="view-gallery-item"><div class="view-gallery-placeholder"><i class='bx bx-image'></i></div></div>`;
                }

                // Route
                document.getElementById('viewFrom').textContent = d.from_location || '—';
                document.getElementById('viewTo').textContent   = d.to_location   || '—';

                // Transfer detail grid
                const purpose = (d.purpose || '').replace(/\s*\[(dest_asset_id|dest):\d+\]/g, '').replace(/\s*\|?\s*From:.+?→.+$/i, '').trim() || '—';
                document.getElementById('viewTxnGrid').innerHTML = `
                    <div class="view-detail-item"><div class="view-detail-label">Transaction #</div><div class="view-detail-value">#${d.id}</div></div>
                    <div class="view-detail-item"><div class="view-detail-label">Quantity</div><div class="view-detail-value"><strong style="font-size:1.1rem;">${d.quantity} pcs</strong></div></div>
                    <div class="view-detail-item"><div class="view-detail-label">Requested By</div><div class="view-detail-value">${d.requested_by || '—'}</div></div>
                    <div class="view-detail-item"><div class="view-detail-label">Date Needed</div><div class="view-detail-value">${d.date_needed ? new Date(d.date_needed).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—'}</div></div>
                    <div class="view-detail-item full"><div class="view-detail-label">Purpose</div><div class="view-detail-value">${purpose}</div></div>`;

                // Asset info grid
                document.getElementById('viewAssetGrid').innerHTML = `
                    <div class="view-detail-item"><div class="view-detail-label">Category</div><div class="view-detail-value">${d.category_name || '—'}</div></div>
                    <div class="view-detail-item"><div class="view-detail-label">Type</div><div class="view-detail-value">${d.subcategory_name || '—'}</div></div>
                    <div class="view-detail-item"><div class="view-detail-label">Condition</div><div class="view-detail-value"><span class="badge-condition badge-${(d.condition||'').toLowerCase()}">${d.condition || '—'}</span></div></div>
                    <div class="view-detail-item"><div class="view-detail-label">Asset Location</div><div class="view-detail-value">${d.asset_location || '—'}</div></div>
                    ${d.asset_sub_location ? `<div class="view-detail-item"><div class="view-detail-label">Sub-Location</div><div class="view-detail-value">${d.asset_sub_location}</div></div>` : ''}
                    ${d.asset_description ? `<div class="view-detail-item full"><div class="view-detail-label">Description</div><div class="view-detail-value">${d.asset_description}</div></div>` : ''}`;

                document.getElementById('viewLoading').style.display = 'none';
                document.getElementById('viewContent').style.display = 'block';

            } catch (e) { showToast('Failed to load details.', 'error'); closeModal('viewModal'); }
        }

        // ── RELEASE MODAL ────────────────────────────────────────────────────
        async function openRelease(id) {
            document.getElementById('releaseId').value          = id;
            document.getElementById('releaseReleasedBy').value  = '';
            document.getElementById('releaseDeliveredBy').value = '';
            document.getElementById('releaseAssetName').textContent = 'Loading...';
            document.getElementById('releaseAssetType').textContent = '';
            document.getElementById('releaseAssetSerial').style.display = 'none';
            document.getElementById('releaseFrom').textContent  = '—';
            document.getElementById('releaseTo').textContent    = '—';
            document.getElementById('releaseQty').textContent   = '—';
            document.getElementById('releaseReqBy').textContent = '—';
            document.getElementById('releaseThumb').innerHTML   = "<i class='bx bx-image-alt'></i>";
            document.getElementById('releaseModal').classList.add('active');

            try {
                const body = new FormData();
                body.append('action', 'get_details'); body.append('id', id);
                const res    = await fetch('admin-preparing.php', { method: 'POST', body });
                const result = await res.json();
                if (!result.success) { showToast('Could not load details.', 'error'); return; }

                const d = result.data;
                document.getElementById('releaseAssetName').textContent = `${d.brand || ''} ${d.model || ''}`.trim() || '—';
                document.getElementById('releaseAssetType').textContent = d.subcategory_name || '';

                if (d.serial_number) {
                    const s = document.getElementById('releaseAssetSerial');
                    s.textContent = d.serial_number;
                    s.style.display = 'inline-block';
                }

                // Thumbnail
                if (d.thumbnail && d.thumbnail_type === 'gdrive') {
                    document.getElementById('releaseThumb').innerHTML = `<img src="${d.thumbnail}" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='<i class=\\'bx bxl-google\\'></i>'">`;
                } else if (d.thumbnail) {
                    document.getElementById('releaseThumb').innerHTML = `<img src="${d.thumbnail}" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='<i class=\\'bx bx-image-alt\\'></i>'">`;
                }

                document.getElementById('releaseFrom').textContent  = d.from_location || '—';
                document.getElementById('releaseTo').textContent    = d.to_location   || '—';
                document.getElementById('releaseQty').textContent   = `${d.quantity   || '—'} pcs`;
                document.getElementById('releaseReqBy').textContent = d.requested_by  || '—';
            } catch (e) { showToast('Failed to load details.', 'error'); }
        }

        async function submitRelease() {
            const id = document.getElementById('releaseId').value;
            const rb = document.getElementById('releaseReleasedBy').value.trim();
            const db = document.getElementById('releaseDeliveredBy').value.trim();
            if (!rb) { showToast('Please enter who is releasing the asset.', 'error'); return; }
            if (!db) { showToast('Please enter who is delivering the asset.', 'error'); return; }
            const body = new FormData();
            body.append('action', 'release'); body.append('id', id);
            body.append('released_by', rb); body.append('delivered_by', db);
            const res    = await fetch('admin-preparing.php', { method: 'POST', body });
            const result = await res.json();
            showToast(result.message, result.success ? 'success' : 'error');
            if (result.success) { closeModal('releaseModal'); document.getElementById('card-' + id)?.remove(); updateStat(-1); }
        }

        async function revertToPending(id) {
            if (!confirm('Revert this back to Pending?')) return;
            const body = new FormData();
            body.append('action', 'revert'); body.append('id', id);
            const res    = await fetch('admin-preparing.php', { method: 'POST', body });
            const result = await res.json();
            showToast(result.message, result.success ? 'success' : 'error');
            if (result.success) { document.getElementById('card-' + id)?.remove(); updateStat(-1); }
        }

        function openCancel(id) {
            document.getElementById('cancelId').value     = id;
            document.getElementById('cancelReason').value = '';
            document.getElementById('cancelModal').classList.add('active');
        }

        async function submitCancel() {
            const id   = document.getElementById('cancelId').value;
            const body = new FormData();
            body.append('action', 'cancel'); body.append('id', id);
            body.append('reason', document.getElementById('cancelReason').value);
            const res    = await fetch('admin-preparing.php', { method: 'POST', body });
            const result = await res.json();
            showToast(result.message, result.success ? 'success' : 'error');
            if (result.success) { closeModal('cancelModal'); document.getElementById('card-' + id)?.remove(); updateStat(-1); }
        }

        function exportCSV() {
            const headers = ['ID', 'Brand', 'Model', 'Serial', 'Type', 'Qty', 'From', 'To', 'Purpose', 'Requested By', 'Date Needed', 'Prep Step'];
            const data = <?php
                $out = [];
                foreach ($rows as $r) {
                    $from = $r['from_location'] ?: '—';
                    $to   = $r['to_location'] ?: ($r['location_received'] ?: '—');
                    $p    = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $r['purpose'] ?? '');
                    $p    = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $p));
                    $sl   = ['Not Started', 'Located', 'Checked', 'Packed', 'Ready to Release'];
                    $out[] = [$r['id'], $r['brand'] ?? '', $r['model'] ?? '', $r['serial_number'] ?? '', $r['subcategory_name'] ?? '', $r['quantity'], $from, $to, $p, $r['requested_by'] ?? '', $r['date_needed'] ?? '', $sl[$r['prep_step'] ?? 0] ?? ''];
                }
                echo json_encode($out);
            ?>;
            const csv = [headers, ...data].map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
            const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
            a.download = 'preparing_' + new Date().toISOString().slice(0, 10) + '.csv'; a.click();
        }

        function updateStat(delta) { const s = document.getElementById('statConfirmed'); if (s) s.textContent = Math.max(0, parseInt(s.textContent) + delta); }
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