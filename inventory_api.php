<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized - Please login first'
    ]);
    exit();
}

try {
    $conn = getDBConnection();
    if (!$conn) throw new Exception('Database connection failed');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection error: ' . $e->getMessage()]);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_inventory':         getInventory($conn);            break;
        case 'get_asset_details':     getAssetDetails($conn);         break;
        case 'submit_pullout':        submitPullout($conn);           break;
        case 'get_categories':        getCategories($conn);           break;
        case 'get_subcategories':     getSubcategories($conn);        break;
        case 'verify_admin_password': verifyAdminPassword($conn);     break;
        case 'update_asset':          updateAsset($conn);             break;
        case 'get_asset_transfers':   getAssetTransfers($conn);       break;
        default:
            echo json_encode([
                'success'           => false,
                'error'             => 'Invalid action',
                'available_actions' => [
                    'get_inventory', 'get_asset_details', 'submit_pullout',
                    'get_categories', 'verify_admin_password', 'update_asset'
                ]
            ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}

// ─────────────────────────────────────────────────────────────────────────────
// GET INVENTORY
// ─────────────────────────────────────────────────────────────────────────────
function getInventory($conn) {
    $search   = $_GET['search']   ?? '';
    $category = $_GET['category'] ?? '';
    $status   = $_GET['status']   ?? '';
    $location = $_GET['location'] ?? '';

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
                    a.created_at,
                    (
                        a.beg_balance_count
                        - COALESCE(SUM(CASE WHEN p.status = 'PENDING' THEN p.quantity ELSE 0 END), 0)
                    ) AS active_count,
                    COALESCE(SUM(CASE WHEN p.status = 'PENDING' THEN p.quantity ELSE 0 END), 0) AS for_pullout
                FROM assets a
                LEFT JOIN categories     c  ON a.category_id     = c.id
                LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
                LEFT JOIN pull_out_transactions p ON a.id = p.asset_id
                WHERE 1=1";

        $params = [];
        $types  = '';

        if (!empty($search)) {
            $sql .= " AND (
                        c.name          LIKE ? OR
                        sc.name         LIKE ? OR
                        a.brand         LIKE ? OR
                        a.model         LIKE ? OR
                        a.serial_number LIKE ? OR
                        a.condition     LIKE ? OR
                        a.status        LIKE ? OR
                        a.location      LIKE ? OR
                        a.sub_location  LIKE ?
                    )";
            $s       = "%$search%";
            $params  = array_merge($params, [$s, $s, $s, $s, $s, $s, $s, $s, $s]);
            $types  .= 'sssssssss';
        }

        if (!empty($category)) {
            $sql    .= " AND c.id = ?";
            $params[] = $category;
            $types  .= 'i';
        }

        if (!empty($status)) {
            $sql    .= " AND a.status = ?";
            $params[] = $status;
            $types  .= 's';
        }

        if (!empty($location)) {
            $sql    .= " AND a.location = ?";
            $params[] = $location;
            $types  .= 's';
        }

        $sql .= " GROUP BY a.id ORDER BY a.id DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

        if (!empty($params)) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);

        $result    = $stmt->get_result();
        $inventory = [];

        while ($row = $result->fetch_assoc()) {
            $imgStmt = $conn->prepare("SELECT image_path FROM asset_images WHERE asset_id = ? LIMIT 3");
            if ($imgStmt) {
                $imgStmt->bind_param("i", $row['id']);
                $imgStmt->execute();
                $imgResult = $imgStmt->get_result();
                $images    = [];
                while ($img = $imgResult->fetch_assoc()) $images[] = '/' . ltrim($img['image_path'], '/');

                $row['images'] = $images;
            } else {
                $row['images'] = [];
            }
            $inventory[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $inventory, 'count' => count($inventory)]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'getInventory error: ' . $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GET ASSET DETAILS
// ─────────────────────────────────────────────────────────────────────────────
function getAssetDetails($conn) {
    $assetId = intval($_GET['asset_id'] ?? 0);
    if (!$assetId) {
        echo json_encode(['success' => false, 'error' => 'Asset ID required']);
        return;
    }

    try {
        $sql = "SELECT 
                    a.*,
                    c.name  AS category,
                    sc.name AS asset_type,
                    (
                        a.beg_balance_count
                        - COALESCE(SUM(CASE WHEN p.status = 'PENDING' THEN p.quantity ELSE 0 END), 0)
                    ) AS active_count,
                    COALESCE(SUM(CASE WHEN p.status = 'PENDING' THEN p.quantity ELSE 0 END), 0) AS for_pullout
                FROM assets a
                LEFT JOIN categories     c  ON a.category_id     = c.id
                LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
                LEFT JOIN pull_out_transactions p ON a.id = p.asset_id
                WHERE a.id = ?
                GROUP BY a.id";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $assetId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($asset = $result->fetch_assoc()) {
            $imgStmt = $conn->prepare("SELECT image_path FROM asset_images WHERE asset_id = ? LIMIT 3");
            $imgStmt->bind_param("i", $assetId);
            $imgStmt->execute();
            $imgResult = $imgStmt->get_result();
            $images    = [];
          while ($img = $imgResult->fetch_assoc()) $images[] = '/' . ltrim($img['image_path'], '/');

            $asset['images'] = $images;

            echo json_encode(['success' => true, 'data' => $asset]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Asset not found']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'getAssetDetails error: ' . $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// VERIFY ADMIN PASSWORD
// ─────────────────────────────────────────────────────────────────────────────
function verifyAdminPassword($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        return;
    }

    $data     = json_decode(file_get_contents('php://input'), true);
    $password = trim($data['password'] ?? '');

    if (empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Password is required']);
        return;
    }

    try {
        $userId = $_SESSION['user_id'];

        $stmt = $conn->prepare("SELECT password, role FROM users WHERE id = ? AND status = 'ACTIVE' LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }

        if ($user['role'] !== 'ADMIN') {
            echo json_encode(['success' => false, 'error' => 'Access denied. Admin role required.']);
            return;
        }

        if (password_verify($password, $user['password'])) {
            $userName = $_SESSION['name'] ?? 'Unknown';
            $logStmt  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'ADMIN_VERIFY', 'Admin password verified for asset edit')");
            $logStmt->bind_param("s", $userName);
            $logStmt->execute();

            echo json_encode(['success' => true, 'message' => 'Password verified']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Incorrect password']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'verifyAdminPassword error: ' . $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// UPDATE ASSET
// ─────────────────────────────────────────────────────────────────────────────
function updateAsset($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        return;
    }

    $data    = json_decode(file_get_contents('php://input'), true);
    $assetId = intval($data['asset_id'] ?? 0);

    if (!$assetId) {
        echo json_encode(['success' => false, 'error' => 'Asset ID required']);
        return;
    }

    $allowedConditions = ['NEW', 'USED'];
    $allowedStatuses   = ['WORKING', 'NOT WORKING', 'FOR CHECKING'];

    $brand        = trim($data['brand']           ?? '');
    $model        = trim($data['model']           ?? '');
    $serial       = trim($data['serial_number']   ?? '');
    $condition    = in_array($data['condition'] ?? '', $allowedConditions) ? $data['condition'] : 'NEW';
    $status       = in_array($data['status']    ?? '', $allowedStatuses)   ? $data['status']    : 'WORKING';
    $location     = trim($data['location']        ?? '');
    $subLocation  = trim($data['sub_location']    ?? '');
    $description  = trim($data['description']     ?? '');
    $begBalance   = intval($data['beg_balance_count'] ?? -1);
    $activeAdjust = isset($data['active_count']) ? intval($data['active_count']) : -1;

    try {
        if ($activeAdjust >= 0) {
            $calcSql  = "SELECT 
                            COALESCE(SUM(CASE WHEN p.status = 'RELEASED' THEN p.quantity ELSE 0 END), 0) AS released,
                            COALESCE(SUM(CASE WHEN p.status = 'RETURNED'  THEN p.quantity ELSE 0 END), 0) AS returned
                         FROM pull_out_transactions p
                         WHERE p.asset_id = ?";
            $calcStmt = $conn->prepare($calcSql);
            $calcStmt->bind_param("i", $assetId);
            $calcStmt->execute();
            $txn = $calcStmt->get_result()->fetch_assoc();

            $released   = intval($txn['released'] ?? 0);
            $returned   = intval($txn['returned'] ?? 0);
            $begBalance = $activeAdjust + $released - $returned;
            if ($begBalance < 0) $begBalance = 0;
        }

        if ($begBalance >= 0) {
            $sql  = "UPDATE assets 
                     SET brand = ?, model = ?, serial_number = ?, `condition` = ?,
                         status = ?, location = ?, sub_location = ?, description = ?,
                         beg_balance_count = ?,
                         updated_at = NOW()
                     WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param(
                "ssssssssii",
                $brand, $model, $serial, $condition,
                $status, $location, $subLocation, $description,
                $begBalance, $assetId
            );
        } else {
            $sql  = "UPDATE assets 
                     SET brand = ?, model = ?, serial_number = ?, `condition` = ?,
                         status = ?, location = ?, sub_location = ?, description = ?,
                         updated_at = NOW()
                     WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param(
                "ssssssssi",
                $brand, $model, $serial, $condition,
                $status, $location, $subLocation, $description,
                $assetId
            );
        }

        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);

        $userName = $_SESSION['name'] ?? 'Unknown';
        $logStmt  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'UPDATE_ASSET', ?)");
        $desc     = "Updated asset #$assetId by $userName";
        $logStmt->bind_param("ss", $userName, $desc);
        $logStmt->execute();

        echo json_encode(['success' => true, 'message' => 'Asset updated successfully']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'updateAsset error: ' . $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SUBMIT ASSET TRANSFER REQUEST
// ─────────────────────────────────────────────────────────────────────────────
function submitPullout($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $assetId         = intval($data['asset_id']          ?? 0);
    $quantity        = intval($data['quantity']           ?? 1);
    $dateNeeded      = trim($data['date_needed']          ?? '');
    $fromLocation    = trim($data['from_location']        ?? '');
    $fromSubLocation = trim($data['from_sub_location']    ?? '');
    $toLocation      = trim($data['to_location']          ?? '');
    $toSubLocation   = trim($data['to_sub_location']      ?? '');
    $purpose         = trim($data['purpose']              ?? '');
    $requestedBy     = trim($data['requested_by']         ?? '');
    $receivedBy      = trim($data['received_by']          ?? '');

    if (!$assetId || empty($dateNeeded) || empty($fromLocation) || empty($toLocation)
        || empty($purpose) || empty($requestedBy) || empty($receivedBy)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    if ($quantity < 1) {
        echo json_encode(['success' => false, 'error' => 'Quantity must be at least 1']);
        return;
    }

    if ($fromLocation === $toLocation) {
        echo json_encode(['success' => false, 'error' => 'Source and recipient location cannot be the same']);
        return;
    }

    try {
        // Check available stock
        $checkSql  = "SELECT 
                        a.location,
                        (
                            a.beg_balance_count
                            - COALESCE(SUM(CASE WHEN p.status = 'PENDING' THEN p.quantity ELSE 0 END), 0)
                        ) AS active_count
                      FROM assets a
                      LEFT JOIN pull_out_transactions p ON a.id = p.asset_id
                      WHERE a.id = ?
                      GROUP BY a.id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $assetId);
        $checkStmt->execute();
        $activeData = $checkStmt->get_result()->fetch_assoc();

        if (!$activeData || $activeData['active_count'] < $quantity) {
            echo json_encode(['success' => false, 'error' => 'Insufficient active items for transfer']);
            return;
        }

        // ── FIX: Build full labels WITH sub-location for display purposes ──
        $fromLabel = $fromLocation . ($fromSubLocation ? " / $fromSubLocation" : '');
        $toLabel   = $toLocation   . ($toSubLocation   ? " / $toSubLocation"   : '');

        // ── FIX: purpose is now CLEAN — no location embedded ──
        // from_location and to_location are saved in their own dedicated columns

        // ── FIX: Updated INSERT — now saves from_location and to_location properly ──
        $insertSql = "INSERT INTO pull_out_transactions 
                        (asset_id, quantity, purpose, requested_by, date_needed,
                         from_location, to_location, location_received,
                         released_by, status)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')";

        $insertStmt = $conn->prepare($insertSql);
        if (!$insertStmt) throw new Exception('Prepare failed: ' . $conn->error);

        // location_received = toLabel (includes sub-location for display)
        // from_location     = fromLabel (includes sub-location)
        // to_location       = toLabel
        $insertStmt->bind_param("iisssssss",
            $assetId,
            $quantity,
            $purpose,       // ← clean purpose only, no location embedded
            $requestedBy,
            $dateNeeded,
            $fromLabel,     // ← from_location column
            $toLabel,       // ← to_location column
            $toLabel,       // ← location_received (kept for backwards compat)
            $receivedBy
        );

        if (!$insertStmt->execute()) throw new Exception('Insert failed: ' . $insertStmt->error);

        $transactionId = $conn->insert_id;

        $userName = $_SESSION['name'] ?? 'Unknown';
        $logStmt  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'ASSET_TRANSFER', ?)");
        $desc     = "Transfer #$transactionId: Asset #$assetId ($quantity pcs) from $fromLabel → $toLabel by $requestedBy [PENDING]";
        $logStmt->bind_param("ss", $userName, $desc);
        $logStmt->execute();

        echo json_encode([
            'success'        => true,
            'message'        => 'Asset transfer request submitted successfully.',
            'transaction_id' => $transactionId
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'submitTransfer error: ' . $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GET SUBCATEGORIES by category_id
// ─────────────────────────────────────────────────────────────────────────────
function getSubcategories($conn) {
    $categoryId = intval($_GET['category_id'] ?? 0);
    if (!$categoryId) {
        echo json_encode(['success' => false, 'error' => 'category_id required']);
        return;
    }
    try {
        $stmt = $conn->prepare("SELECT id, name FROM sub_categories WHERE category_id = ? ORDER BY name");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data   = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'getSubcategories error: ' . $e->getMessage()]);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GET CATEGORIES
// ─────────────────────────────────────────────────────────────────────────────
function getCategories($conn) {
    try {
        $result     = $conn->query("SELECT id, name FROM categories ORDER BY name");
        if (!$result) throw new Exception('Query failed: ' . $conn->error);

        $categories = [];
        while ($row = $result->fetch_assoc()) $categories[] = $row;

        echo json_encode(['success' => true, 'data' => $categories, 'count' => count($categories)]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'getCategories error: ' . $e->getMessage()]);
    }
}