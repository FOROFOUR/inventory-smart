<?php
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../permissions_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']); exit();
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT role, warehouse_location, name FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();

if (!$me || $me['role'] !== 'WAREHOUSE') {
    echo json_encode(['success' => false, 'message' => 'Access denied.']); exit();
}

$myLocation = $me['warehouse_location'] ?? '';

// ── Route ──────────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw    = file_get_contents('php://input');
    $body   = json_decode($raw, true) ?? [];
    $action = $body['action'] ?? $action;
} else {
    $body = [];
}

switch ($action) {
    case 'get_assets':        getAssets($conn, $myLocation);                        break;
    case 'get_incoming':      getIncoming($conn, $myLocation);                      break;
    case 'get_notifications': getNotifications($conn, $myLocation);                 break;
    case 'mark_received':     markReceived($conn, $body, $myLocation, $me['name']); break;
    case 'debug_location':    debugLocation($conn, $myLocation);                    break;
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

ob_end_flush();

// =============================================================================
// GET ASSETS — filtered to this warehouse's location
// =============================================================================
function getAssets($conn, $location) {
    if (!$location) {
        echo json_encode(['success' => true, 'data' => [], 'message' => 'No location assigned.']);
        return;
    }
    $sql = "
        SELECT a.id, a.brand, a.model, a.serial_number, a.tracking_type,
               a.condition, a.status, a.location, a.sub_location,
               a.beg_balance_count, a.created_at,
               c.name  AS category_name,
               sc.name AS sub_category_name
        FROM assets a
        LEFT JOIN categories     c  ON c.id  = a.category_id
        LEFT JOIN sub_categories sc ON sc.id = a.sub_category_id
        WHERE TRIM(a.location) = TRIM(?)
        ORDER BY c.name, sc.name, a.brand, a.model
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $location);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows, 'location' => $location, 'count' => count($rows)]);
}

// =============================================================================
// GET INCOMING — items coming TO this warehouse (RELEASED only — para sa Receiving page)
// =============================================================================
function getIncoming($conn, $location) {
    if (!$location) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }

    // Only RELEASED items heading TO this warehouse
    $sql = "
        SELECT pt.id, pt.asset_id, pt.from_location, pt.to_location,
               pt.quantity, pt.purpose, pt.requested_by, pt.date_needed,
               pt.released_by, pt.delivered_by, pt.status,
               pt.created_at, pt.released_at,
               a.brand, a.model, a.serial_number,
               c.name  AS category_name,
               sc.name AS sub_category_name
        FROM pull_out_transactions pt
        LEFT JOIN assets          a  ON a.id  = pt.asset_id
        LEFT JOIN categories      c  ON c.id  = a.category_id
        LEFT JOIN sub_categories  sc ON sc.id = a.sub_category_id
        WHERE TRIM(pt.to_location) = TRIM(?)
          AND pt.status = 'RELEASED'
        ORDER BY pt.released_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $location);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
}

// =============================================================================
// GET NOTIFICATIONS — bell icon data
// Returns:
//   - CONFIRMED + from_location = this warehouse  → needs to be prepared & released
//   - RELEASED  + to_location   = this warehouse  → incoming, needs to be received
// =============================================================================
function getNotifications($conn, $location) {
    if (!$location) {
        echo json_encode(['success' => true, 'data' => [], 'total' => 0, 'preparing' => 0, 'receiving' => 0]);
        return;
    }

    $locLike = $location . '%';

    $sql = "
        SELECT pt.id, pt.asset_id, pt.from_location, pt.to_location,
               pt.quantity, pt.purpose,
               pt.released_by, pt.delivered_by, pt.status,
               pt.created_at, pt.released_at,
               a.brand, a.model, a.serial_number
        FROM pull_out_transactions pt
        LEFT JOIN assets a ON a.id = pt.asset_id
        WHERE (
            (pt.status = 'CONFIRMED' AND pt.from_location LIKE ?)
            OR
            (pt.status = 'RELEASED'  AND pt.to_location   LIKE ?)
        )
        ORDER BY
            FIELD(pt.status, 'RELEASED', 'CONFIRMED'),
            pt.created_at DESC
        LIMIT 50
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $locLike, $locLike);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $preparing = count(array_filter($rows, fn($r) => $r['status'] === 'CONFIRMED'));
    $receiving = count(array_filter($rows, fn($r) => $r['status'] === 'RELEASED'));

    echo json_encode([
        'success'   => true,
        'data'      => $rows,
        'total'     => count($rows),
        'preparing' => $preparing,
        'receiving' => $receiving,
    ]);
}

// =============================================================================
// DEBUG — check location mismatches
// =============================================================================
function debugLocation($conn, $location) {
    $out = [
        'my_warehouse_location' => $location,
        'my_location_length'    => strlen($location),
        'my_location_hex'       => bin2hex($location),
    ];
    $r = $conn->query("SELECT DISTINCT location, COUNT(*) as cnt FROM assets GROUP BY location ORDER BY cnt DESC LIMIT 30");
    $locs = [];
    while ($row = $r->fetch_assoc()) {
        $locs[] = [
            'location' => $row['location'],
            'count'    => $row['cnt'],
            'hex'      => bin2hex($row['location']),
            'matches'  => (trim($row['location']) === trim($location)) ? '✅ MATCH' : '❌'
        ];
    }
    $out['asset_locations'] = $locs;
    $s = $conn->prepare("SELECT COUNT(*) as cnt FROM assets WHERE TRIM(location) = TRIM(?)");
    $s->bind_param("s", $location);
    $s->execute();
    $out['assets_found_with_trim'] = $s->get_result()->fetch_assoc()['cnt'];
    echo json_encode(['success' => true, 'debug' => $out], JSON_PRETTY_PRINT);
}

// =============================================================================
// MARK RECEIVED
// =============================================================================
function markReceived($conn, $data, $myLocation, $actorName) {
    $id    = (int)($data['id']          ?? 0);
    $rcvBy = trim($data['received_by'] ?? '');
    $notes = trim($data['notes']       ?? '');

    if (!$id || !$rcvBy) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        return;
    }

    $locLike = $myLocation . '%';
    $chk = $conn->prepare(
        "SELECT id, asset_id, to_location, status FROM pull_out_transactions
         WHERE id = ? AND status = 'RELEASED' AND to_location LIKE ?"
    );
    $chk->bind_param("is", $id, $locLike);
    $chk->execute();
    $tx = $chk->get_result()->fetch_assoc();

    if (!$tx) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found or not eligible for receipt.']);
        return;
    }

    $toMainLocation = $tx['to_location'];

    $conn->begin_transaction();
    try {
        $upd = $conn->prepare(
            "UPDATE pull_out_transactions
             SET status='RECEIVED', received_by=?, received_at=NOW(), location_received=?
             WHERE id=?"
        );
        $upd->bind_param("ssi", $rcvBy, $toMainLocation, $id);
        $upd->execute();

        // Update asset location AND sub_location
        $updA = $conn->prepare("UPDATE assets SET location=?, sub_location=NULL, updated_at=NOW() WHERE id=?");
        $updA->bind_param("si", $toMainLocation, $tx['asset_id']);
        $updA->execute();

        $log  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'RECEIVE_ASSET', ?)");
        $desc = "Transaction #{$id} received at '{$toMainLocation}' by {$rcvBy}" . ($notes ? " — Notes: {$notes}" : '');
        $log->bind_param("ss", $actorName, $desc);
        $log->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Item received successfully. Inventory updated.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}