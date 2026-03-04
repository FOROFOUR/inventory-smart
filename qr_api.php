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

/* ── Dynamic Base URL (Auto-detect IP / Domain) ───────────────────────── */

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
    ? "https://" 
    : "http://";

$host = $_SERVER['HTTP_HOST'];

// Get folder path (ex: /inventory-smart)
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

define('BASE_URL', $protocol . $host . $basePath);

/* ───────────────────────────────────────────────────────────────────────── */

try {
    $conn = getDBConnection();
    if (!$conn) throw new Exception('Database connection failed');
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_assets_for_qr':
            getAssetsForQR($conn);
            break;

        default:
            echo json_encode([
                'success' => false,
                'error'   => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

/* ── Fetch Assets for QR ───────────────────────────────────────────────── */

function getAssetsForQR($conn) {

    $search   = $_GET['search']   ?? '';
    $category = $_GET['category'] ?? '';

    try {
        $sql = "SELECT 
                    a.id,
                    a.brand,
                    a.model,
                    a.serial_number,
                    a.condition,
                    a.status,
                    a.location,
                    a.qr_code,
                    c.name  AS category,
                    sc.name AS asset_type
                FROM assets a
                LEFT JOIN categories c  ON a.category_id     = c.id
                LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
                WHERE 1=1";

        $params = [];
        $types  = '';

        if (!empty($search)) {
            $sql .= " AND (
                        a.brand LIKE ? OR 
                        a.model LIKE ? OR 
                        a.serial_number LIKE ? OR 
                        a.qr_code LIKE ? OR 
                        c.name LIKE ? OR 
                        sc.name LIKE ?
                      )";

            $searchParam = "%$search%";
            $params = array_merge($params, [
                $searchParam,
                $searchParam,
                $searchParam,
                $searchParam,
                $searchParam,
                $searchParam
            ]);

            $types .= 'ssssss';
        }

        if (!empty($category)) {
            $sql .= " AND c.id = ?";
            $params[] = $category;
            $types .= 'i';
        }

        $sql .= " ORDER BY a.id DESC";

        $stmt = $conn->prepare($sql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $assets = [];

        while ($row = $result->fetch_assoc()) {

            // Auto-generated scannable URL
            $row['qr_url'] = BASE_URL . '/asset-view.php?id=' . $row['id'];

            // Keep original QR label for printing
            $row['qr_label'] = $row['qr_code'] 
                ?? ('ASSET-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT));

            $assets[] = $row;
        }

        echo json_encode([
            'success' => true,
            'data'    => $assets,
            'count'   => count($assets)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error'   => 'Error fetching assets: ' . $e->getMessage()
        ]);
    }
}
?>