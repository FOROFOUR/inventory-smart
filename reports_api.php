<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'generate_report') {
        generateReport($conn);
    } elseif ($action === 'get_locations') {
        getLocations($conn);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ── Returns distinct non-null locations for the filter dropdown ──
function getLocations($conn) {
    $result = $conn->query("
        SELECT DISTINCT location
        FROM assets
        WHERE location IS NOT NULL AND location != ''
        ORDER BY location ASC
    ");

    $locations = [];
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row['location'];
    }

    echo json_encode(['success' => true, 'data' => $locations]);
}

function generateReport($conn) {
    $type = $_GET['type'] ?? 'assets';
    
    if ($type === 'assets') {
        generateAssetsReport($conn);
    } elseif ($type === 'pullout') {
        generatePulloutReport($conn);
    } elseif ($type === 'summary') {
        generateSummaryReport($conn);
    }
}

function generateAssetsReport($conn) {
    $category  = $_GET['category']  ?? '';
    $status    = $_GET['status']    ?? '';
    $condition = $_GET['condition'] ?? '';
    $location  = $_GET['location']  ?? '';   // ← NEW
    
    try {
        $sql = "SELECT 
                    a.id,
                    c.name  AS category,
                    sc.name AS asset_type,
                    a.brand,
                    a.model,
                    a.serial_number,
                    a.condition,
                    a.status,
                    a.location,
                    a.sub_location,
                    a.beg_balance_count,
                    (
                        a.beg_balance_count - 
                        COALESCE(SUM(CASE WHEN p.status = 'RELEASED' THEN p.quantity ELSE 0 END), 0) +
                        COALESCE(SUM(CASE WHEN p.status = 'RETURNED' THEN p.quantity ELSE 0 END), 0)
                    ) AS active_count
                FROM assets a
                LEFT JOIN categories c       ON a.category_id     = c.id
                LEFT JOIN sub_categories sc  ON a.sub_category_id = sc.id
                LEFT JOIN pull_out_transactions p ON a.id = p.asset_id
                WHERE 1=1";
        
        $params = [];
        $types  = '';
        
        if (!empty($category)) {
            $sql .= " AND c.id = ?";
            $params[] = $category;
            $types   .= 'i';
        }
        
        if (!empty($status)) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
            $types   .= 's';
        }
        
        if (!empty($condition)) {
            $sql .= " AND a.condition = ?";
            $params[] = $condition;
            $types   .= 's';
        }

        // ── Location filter ──
        if (!empty($location)) {
            $sql .= " AND a.location = ?";
            $params[] = $location;
            $types   .= 's';
        }
        
        $sql .= " GROUP BY a.id ORDER BY a.id DESC";
        
        $stmt = $conn->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $assets = [];
        while ($row = $result->fetch_assoc()) {
            $assets[] = $row;
        }
        
        // Summary counts
        $summary = [
            'total'        => count($assets),
            'working'      => 0,
            'for_checking' => 0,
            'not_working'  => 0,
        ];
        
        foreach ($assets as $asset) {
            if ($asset['status'] === 'WORKING')      $summary['working']++;
            elseif ($asset['status'] === 'FOR CHECKING') $summary['for_checking']++;
            elseif ($asset['status'] === 'NOT WORKING')  $summary['not_working']++;
        }
        
        echo json_encode([
            'success' => true,
            'data'    => $assets,
            'summary' => $summary,
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function generatePulloutReport($conn) {
    $status   = $_GET['status']    ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo   = $_GET['date_to']   ?? '';
    
    try {
        $sql = "SELECT 
                    p.id,
                    p.quantity,
                    p.purpose,
                    p.requested_by,
                    p.date_needed,
                    p.released_by,
                    p.status,
                    p.created_at,
                    CONCAT(
                        COALESCE(a.brand, ''), ' ',
                        COALESCE(a.model, ''), ' (',
                        COALESCE(sc.name, 'N/A'), ')'
                    ) AS asset_name
                FROM pull_out_transactions p
                LEFT JOIN assets a           ON p.asset_id         = a.id
                LEFT JOIN sub_categories sc  ON a.sub_category_id  = sc.id
                WHERE 1=1";
        
        $params = [];
        $types  = '';
        
        if (!empty($status)) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
            $types   .= 's';
        }
        
        if (!empty($dateFrom)) {
            $sql .= " AND DATE(p.created_at) >= ?";
            $params[] = $dateFrom;
            $types   .= 's';
        }
        
        if (!empty($dateTo)) {
            $sql .= " AND DATE(p.created_at) <= ?";
            $params[] = $dateTo;
            $types   .= 's';
        }
        
        $sql .= " ORDER BY p.id DESC";
        
        $stmt = $conn->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        
        $summary = [
            'total'    => count($transactions),
            'pending'  => 0,
            'released' => 0,
            'returned' => 0,
        ];
        
        foreach ($transactions as $trans) {
            if ($trans['status'] === 'PENDING')  $summary['pending']++;
            elseif ($trans['status'] === 'RELEASED') $summary['released']++;
            elseif ($trans['status'] === 'RETURNED') $summary['returned']++;
        }
        
        echo json_encode([
            'success' => true,
            'data'    => $transactions,
            'summary' => $summary,
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function generateSummaryReport($conn) {
    $period = $_GET['period'] ?? 'all';
    
    try {
        $totalAssets = $conn->query("SELECT COUNT(*) AS total FROM assets")->fetch_assoc()['total'];
        
        $activeItems = $conn->query("
            SELECT SUM(
                a.beg_balance_count - 
                COALESCE((SELECT SUM(quantity) FROM pull_out_transactions WHERE asset_id = a.id AND status = 'RELEASED'), 0) +
                COALESCE((SELECT SUM(quantity) FROM pull_out_transactions WHERE asset_id = a.id AND status = 'RETURNED'), 0)
            ) AS active
            FROM assets a
        ")->fetch_assoc()['active'] ?? 0;
        
        $pulledOut = $conn->query("
            SELECT COALESCE(SUM(quantity), 0) AS total 
            FROM pull_out_transactions 
            WHERE status = 'RELEASED'
        ")->fetch_assoc()['total'];
        
        $totalTransactions = $conn->query("SELECT COUNT(*) AS total FROM pull_out_transactions")->fetch_assoc()['total'];
        
        $categoryResult = $conn->query("
            SELECT 
                c.name AS category,
                COUNT(a.id) AS total,
                SUM(
                    a.beg_balance_count - 
                    COALESCE((SELECT SUM(quantity) FROM pull_out_transactions WHERE asset_id = a.id AND status = 'RELEASED'), 0) +
                    COALESCE((SELECT SUM(quantity) FROM pull_out_transactions WHERE asset_id = a.id AND status = 'RETURNED'), 0)
                ) AS active,
                SUM(CASE WHEN a.status = 'WORKING'      THEN 1 ELSE 0 END) AS working,
                SUM(CASE WHEN a.status = 'FOR CHECKING' THEN 1 ELSE 0 END) AS for_checking,
                SUM(CASE WHEN a.status = 'NOT WORKING'  THEN 1 ELSE 0 END) AS not_working
            FROM assets a
            LEFT JOIN categories c ON a.category_id = c.id
            GROUP BY c.id, c.name
            ORDER BY total DESC
        ");
        
        $byCategory = [];
        while ($row = $categoryResult->fetch_assoc()) {
            $byCategory[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_assets'       => $totalAssets,
                'active_items'       => $activeItems,
                'pulled_out'         => $pulledOut,
                'total_transactions' => $totalTransactions,
                'by_category'        => $byCategory,
            ],
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>