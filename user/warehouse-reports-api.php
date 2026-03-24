<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$conn = getDBConnection();

// ── Get warehouse location of this user ──────────────────────────────────────
$uStmt = $conn->prepare("SELECT role, warehouse_location FROM users WHERE id = ?");
$uStmt->bind_param("i", $_SESSION['user_id']);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();

if (($uRow['role'] ?? '') !== 'WAREHOUSE') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$warehouseLocation = $uRow['warehouse_location'] ?? '';
$locLike           = $warehouseLocation . '%';   // for LIKE matching

$action = $_GET['action'] ?? '';

try {
    if ($action === 'generate_report') {
        $type = $_GET['type'] ?? 'assets';
        if ($type === 'assets')  generateAssetsReport($conn, $locLike);
        elseif ($type === 'pullout')  generatePulloutReport($conn, $locLike, $warehouseLocation);
        elseif ($type === 'summary')  generateSummaryReport($conn, $locLike, $warehouseLocation);
        else echo json_encode(['success' => false, 'error' => 'Invalid type']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// ASSETS REPORT  — only assets WHERE location LIKE this warehouse
// ═══════════════════════════════════════════════════════════════════════════════
function generateAssetsReport($conn, $locLike) {
    $category  = $_GET['category']  ?? '';
    $status    = $_GET['status']    ?? '';
    $condition = $_GET['condition'] ?? '';

    $sql = "
        SELECT
            a.id,
            c.name  AS category,
            sc.name AS asset_type,
            a.brand, a.model, a.serial_number,
            a.condition, a.status,
            a.location, a.sub_location,
            a.beg_balance_count,
            (
                a.beg_balance_count
                - COALESCE(SUM(CASE WHEN p.status IN ('RELEASED','RECEIVED') THEN p.quantity ELSE 0 END), 0)
                + COALESCE(SUM(CASE WHEN p.status = 'RETURNED' THEN p.quantity ELSE 0 END), 0)
            ) AS active_count
        FROM assets a
        LEFT JOIN categories     c  ON a.category_id     = c.id
        LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
        LEFT JOIN pull_out_transactions p ON a.id = p.asset_id
        WHERE a.location LIKE ?
    ";
    $params = [$locLike];
    $types  = 's';

    if (!empty($category)) { $sql .= " AND c.id = ?";        $params[] = $category;  $types .= 'i'; }
    if (!empty($status))   { $sql .= " AND a.status = ?";    $params[] = $status;    $types .= 's'; }
    if (!empty($condition)){ $sql .= " AND a.condition = ?"; $params[] = $condition; $types .= 's'; }

    $sql .= " GROUP BY a.id ORDER BY c.name ASC, a.brand ASC, a.model ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $assets  = [];
    $summary = ['total' => 0, 'working' => 0, 'for_checking' => 0, 'not_working' => 0];

    while ($row = $result->fetch_assoc()) {
        $assets[] = $row;
        $summary['total']++;
        if ($row['status'] === 'WORKING')       $summary['working']++;
        elseif ($row['status'] === 'FOR CHECKING') $summary['for_checking']++;
        elseif ($row['status'] === 'NOT WORKING')  $summary['not_working']++;
    }

    echo json_encode(['success' => true, 'data' => $assets, 'summary' => $summary]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// PULL-OUT REPORT  — only transactions WHERE from OR to = this warehouse
// ═══════════════════════════════════════════════════════════════════════════════
function generatePulloutReport($conn, $locLike, $warehouseLocation) {
    $txnType  = $_GET['txn_type']  ?? '';   // 'outgoing' | 'incoming' | ''
    $status   = $_GET['status']    ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo   = $_GET['date_to']   ?? '';

    // Base: transactions that touch this warehouse
    $sql = "
        SELECT
            p.id, p.quantity, p.purpose,
            p.requested_by, p.date_needed,
            p.released_by, p.delivered_by, p.received_by,
            p.status, p.created_at, p.released_at, p.received_at,
            p.from_location, p.to_location,
            CONCAT(COALESCE(a.brand,''), ' ', COALESCE(a.model,'')) AS asset_name
        FROM pull_out_transactions p
        LEFT JOIN assets a ON p.asset_id = a.id
        WHERE (p.from_location LIKE ? OR p.to_location LIKE ?)
    ";
    $params = [$locLike, $locLike];
    $types  = 'ss';

    // Narrow to outgoing or incoming
    if ($txnType === 'outgoing') {
        $sql .= " AND p.from_location LIKE ?";
        $params[] = $locLike; $types .= 's';
    } elseif ($txnType === 'incoming') {
        $sql .= " AND p.to_location LIKE ?";
        $params[] = $locLike; $types .= 's';
    }

    if (!empty($status)) { $sql .= " AND p.status = ?"; $params[] = $status; $types .= 's'; }

    if (!empty($dateFrom)) { $sql .= " AND DATE(p.created_at) >= ?"; $params[] = $dateFrom; $types .= 's'; }
    if (!empty($dateTo))   { $sql .= " AND DATE(p.created_at) <= ?"; $params[] = $dateTo;   $types .= 's'; }

    $sql .= " ORDER BY p.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $txns    = [];
    $summary = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'released' => 0, 'received' => 0, 'cancelled' => 0, 'returned' => 0];

    while ($row = $result->fetch_assoc()) {
        // Clean up purpose noise
        $row['purpose'] = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $row['purpose'] ?? ''));
        $row['purpose'] = trim(preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i', '', $row['purpose']));
        $txns[] = $row;
        $summary['total']++;
        $s = strtolower($row['status'] ?? '');
        if (isset($summary[$s])) $summary[$s]++;
    }

    echo json_encode(['success' => true, 'data' => $txns, 'summary' => $summary]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// SUMMARY REPORT  — scoped entirely to this warehouse
// ═══════════════════════════════════════════════════════════════════════════════
function generateSummaryReport($conn, $locLike, $warehouseLocation) {
    $period = $_GET['period'] ?? 'all';

    // ── Date filter ──
    $dateFilter = '';
    $dateParams = [];
    $dateTypes  = '';
    if ($period === 'today') {
        $dateFilter = " AND DATE(p.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $dateFilter = " AND YEARWEEK(p.created_at, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($period === 'month') {
        $dateFilter = " AND YEAR(p.created_at) = YEAR(CURDATE()) AND MONTH(p.created_at) = MONTH(CURDATE())";
    } elseif ($period === 'year') {
        $dateFilter = " AND YEAR(p.created_at) = YEAR(CURDATE())";
    }

    // ── Asset totals ──
    $asStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status='WORKING'       THEN 1 ELSE 0 END) AS working,
            SUM(CASE WHEN status='FOR CHECKING'  THEN 1 ELSE 0 END) AS for_checking,
            SUM(CASE WHEN status='NOT WORKING'   THEN 1 ELSE 0 END) AS not_working,
            SUM(beg_balance_count) AS total_qty
        FROM assets WHERE location LIKE ?
    ");
    $asStmt->bind_param("s", $locLike);
    $asStmt->execute();
    $asRow = $asStmt->get_result()->fetch_assoc();

    // ── Outgoing transactions (from this warehouse, RELEASED/RECEIVED) ──
    $outStmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity),0) AS total
        FROM pull_out_transactions
        WHERE from_location LIKE ? AND status IN ('RELEASED','RECEIVED')
    ");
    $outStmt->bind_param("s", $locLike);
    $outStmt->execute();
    $outgoing = (int)($outStmt->get_result()->fetch_assoc()['total'] ?? 0);

    // ── Incoming transactions (to this warehouse, RECEIVED) ──
    $inStmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity),0) AS total
        FROM pull_out_transactions
        WHERE to_location LIKE ? AND status = 'RECEIVED'
    ");
    $inStmt->bind_param("s", $locLike);
    $inStmt->execute();
    $incoming = (int)($inStmt->get_result()->fetch_assoc()['total'] ?? 0);

    // ── By category ──
    $catStmt = $conn->prepare("
        SELECT
            COALESCE(c.name, 'Uncategorized') AS category,
            COUNT(a.id)                        AS total,
            SUM(CASE WHEN a.status='WORKING'       THEN 1 ELSE 0 END) AS working,
            SUM(CASE WHEN a.status='FOR CHECKING'  THEN 1 ELSE 0 END) AS for_checking,
            SUM(CASE WHEN a.status='NOT WORKING'   THEN 1 ELSE 0 END) AS not_working
        FROM assets a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.location LIKE ?
        GROUP BY c.id, c.name
        ORDER BY total DESC
    ");
    $catStmt->bind_param("s", $locLike);
    $catStmt->execute();
    $byCategory = [];
    $catResult  = $catStmt->get_result();
    while ($row = $catResult->fetch_assoc()) $byCategory[] = $row;

    // ── Recent 20 transactions involving this warehouse ──
    $txnStmt = $conn->prepare("
        SELECT
            p.id, p.status, p.quantity,
            p.from_location, p.to_location, p.created_at,
            CONCAT(COALESCE(a.brand,''), ' ', COALESCE(a.model,'')) AS asset_name
        FROM pull_out_transactions p
        LEFT JOIN assets a ON p.asset_id = a.id
        WHERE (p.from_location LIKE ? OR p.to_location LIKE ?)
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $txnStmt->bind_param("ss", $locLike, $locLike);
    $txnStmt->execute();
    $recentTxns = [];
    $txnResult  = $txnStmt->get_result();
    while ($row = $txnResult->fetch_assoc()) $recentTxns[] = $row;

    echo json_encode([
        'success' => true,
        'data' => [
            'total_assets'  => (int)($asRow['total']        ?? 0),
            'working'       => (int)($asRow['working']      ?? 0),
            'for_checking'  => (int)($asRow['for_checking'] ?? 0),
            'not_working'   => (int)($asRow['not_working']  ?? 0),
            'total_qty'     => (int)($asRow['total_qty']    ?? 0),
            'outgoing'      => $outgoing,
            'incoming'      => $incoming,
            'by_category'   => $byCategory,
            'recent_txns'   => $recentTxns,
        ],
    ]);
}
?>