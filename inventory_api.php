<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Please login first']);
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
        case 'get_inventory':         getInventory($conn);          break;
        case 'get_asset_details':     getAssetDetails($conn);       break;
        case 'submit_pullout':        submitPullout($conn);         break;
        case 'get_categories':        getCategories($conn);         break;
        case 'get_subcategories':     getSubcategories($conn);      break;
        case 'verify_admin_password': verifyAdminPassword($conn);   break;
        case 'update_asset':          updateAsset($conn);           break;
        case 'get_asset_transfers':   getAssetTransfers($conn);     break;
        case 'delete_asset_image':    deleteAssetImage($conn);      break;
        case 'add_asset_image':       addAssetImage($conn);         break;
        default: echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}

// ── Helper: check if URL is a Google Sheets embedded image ───────────────────
function isSheetsImageUrl($url) {
    return strpos($url, 'docs.google.com/sheets-images-rt/') !== false
        || strpos($url, 'docs.google.com/drawings/') !== false;
}

// ── Helper: build absolute proxy URL ─────────────────────────────────────────
function getProxyUrl($imageUrl) {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $base   = rtrim($scheme . '://' . $host . $script, '/');
    return $base . '/image-proxy.php?url=' . urlencode($imageUrl);
}

// ── Helper: extract Google Drive file ID ─────────────────────────────────────
function getDriveFileId($url) {
    if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $url, $m)) return $m[1];
    if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $url, $m)) return $m[1];
    return null;
}

// ── Helper: get first image for table thumbnail ───────────────────────────────
function getFirstImage($conn, $assetId) {
    $stmt = $conn->prepare("SELECT image_path, drive_url FROM asset_images WHERE asset_id = ? ORDER BY id LIMIT 1");
    if (!$stmt) return ['thumb' => null, 'type' => null, 'url' => null];
    $stmt->bind_param("i", $assetId);
    $stmt->execute();
    $img = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$img) return ['thumb' => null, 'type' => null, 'url' => null];

    if ($img['image_path'] === 'gdrive_folder' && !empty($img['drive_url'])) {
        $url = $img['drive_url'];
        if (isSheetsImageUrl($url)) {
            return ['thumb' => $url, 'type' => 'gdrive', 'url' => $url];
        }
        $fileId = getDriveFileId($url);
        if ($fileId) {
            return [
                'thumb' => "https://drive.google.com/thumbnail?id={$fileId}&sz=w80",
                'type'  => 'gdrive',
                'url'   => $url,
            ];
        }
        return ['thumb' => null, 'type' => 'gdrive_folder', 'url' => $url];
    }
    if (!empty($img['image_path']) && $img['image_path'] !== 'gdrive_folder') {
        return ['thumb' => '/' . ltrim($img['image_path'], '/'), 'type' => 'image', 'url' => null];
    }
    return ['thumb' => null, 'type' => null, 'url' => null];
}

// ── Helper: build full image list (now includes image_id for delete) ──────────
function buildImageList($conn, $assetId) {
    $stmt = $conn->prepare("SELECT id, image_path, drive_url FROM asset_images WHERE asset_id = ? ORDER BY id ASC LIMIT 20");
    if (!$stmt) return [];
    $stmt->bind_param("i", $assetId);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = [];
    while ($img = $result->fetch_assoc()) {
        if ($img['image_path'] === 'gdrive_folder' && !empty($img['drive_url'])) {
            $url = $img['drive_url'];
            if (isSheetsImageUrl($url)) {
                $images[] = ['image_id' => $img['id'], 'type' => 'gdrive', 'url' => $url, 'thumb' => getProxyUrl($url)];
                continue;
            }
            $fileId = getDriveFileId($url);
            if ($fileId) {
                $images[] = [
                    'image_id' => $img['id'],
                    'type'     => 'gdrive',
                    'url'      => $url,
                    'thumb'    => getProxyUrl("https://drive.google.com/thumbnail?id={$fileId}&sz=w400"),
                    'file_id'  => $fileId,
                ];
            } else {
                $images[] = ['image_id' => $img['id'], 'type' => 'gdrive_folder', 'url' => $url, 'thumb' => null];
            }
        } elseif (!empty($img['image_path']) && $img['image_path'] !== 'gdrive_folder') {
            $images[] = ['image_id' => $img['id'], 'type' => 'image', 'url' => '/' . ltrim($img['image_path'], '/')];
        }
    }
    $stmt->close();
    return $images;
}

// =============================================================================
// GET INVENTORY
// =============================================================================
function getInventory($conn)
{
    $search      = $_GET['search']      ?? '';
    $category    = $_GET['category']    ?? '';
    $subcategory = $_GET['subcategory'] ?? ''; // ← ADDED
    $status      = $_GET['status']      ?? '';
    $location    = $_GET['location']    ?? '';

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
                        - COALESCE(SUM(CASE WHEN p.status IN ('PENDING','CONFIRMED','RELEASED') THEN p.quantity ELSE 0 END), 0)
                    ) AS active_count,
                    COALESCE(SUM(CASE WHEN p.status IN ('PENDING','CONFIRMED','RELEASED') THEN p.quantity ELSE 0 END), 0) AS for_pullout
                FROM assets a
                LEFT JOIN categories     c  ON a.category_id     = c.id
                LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
                LEFT JOIN pull_out_transactions p ON a.id = p.asset_id
                WHERE 1=1";

        $params = [];
        $types  = '';

        if (!empty($search)) {
            $sql .= " AND (c.name LIKE ? OR sc.name LIKE ? OR a.brand LIKE ? OR a.model LIKE ? OR a.serial_number LIKE ? OR a.condition LIKE ? OR a.status LIKE ? OR a.location LIKE ? OR a.sub_location LIKE ?)";
            $s      = "%$search%";
            $params = array_merge($params, [$s,$s,$s,$s,$s,$s,$s,$s,$s]);
            $types .= 'sssssssss';
        }
        if (!empty($category))    { $sql .= " AND c.id = ?";              $params[] = $category;    $types .= 'i'; }
        if (!empty($subcategory)) { $sql .= " AND a.sub_category_id = ?"; $params[] = $subcategory; $types .= 'i'; } // ← ADDED
        if (!empty($status))      { $sql .= " AND a.status = ?";          $params[] = $status;      $types .= 's'; }
        if (!empty($location))    { $sql .= " AND a.location = ?";        $params[] = $location;    $types .= 's'; }

        $sql .= " GROUP BY a.id ORDER BY a.id DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);

        $result    = $stmt->get_result();
        $inventory = [];

        while ($row = $result->fetch_assoc()) {
            $first = getFirstImage($conn, $row['id']);
            $row['thumbnail']      = $first['thumb'];
            $row['thumbnail_type'] = $first['type'];
            $row['thumbnail_url']  = $first['url'];
            $inventory[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $inventory, 'count' => count($inventory)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'getInventory error: ' . $e->getMessage()]);
    }
}

// =============================================================================
// GET ASSET DETAILS
// =============================================================================
function getAssetDetails($conn)
{
    $assetId = intval($_GET['asset_id'] ?? 0);
    if (!$assetId) { echo json_encode(['success' => false, 'error' => 'Asset ID required']); return; }

    try {
        $sql = "SELECT 
                    a.*,
                    c.name  AS category,
                    sc.name AS asset_type,
                    (
                        a.beg_balance_count
                        - COALESCE(SUM(CASE WHEN p.status IN ('PENDING','CONFIRMED','RELEASED') THEN p.quantity ELSE 0 END), 0)
                    ) AS active_count,
                    COALESCE(SUM(CASE WHEN p.status IN ('PENDING','CONFIRMED','RELEASED') THEN p.quantity ELSE 0 END), 0) AS for_pullout
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
            $asset['images'] = buildImageList($conn, $assetId);
            echo json_encode(['success' => true, 'data' => $asset]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Asset not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'getAssetDetails error: ' . $e->getMessage()]);
    }
}

// =============================================================================
// VERIFY ADMIN PASSWORD
// =============================================================================
function verifyAdminPassword($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'Invalid request method']); return; }
    $data     = json_decode(file_get_contents('php://input'), true);
    $password = trim($data['password'] ?? '');
    if (empty($password)) { echo json_encode(['success'=>false,'error'=>'Password is required']); return; }

    try {
        $userId = $_SESSION['user_id'];
        $stmt   = $conn->prepare("SELECT password, role FROM users WHERE id = ? AND status = 'ACTIVE' LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) { echo json_encode(['success'=>false,'error'=>'User not found']); return; }
        if ($user['role'] !== 'ADMIN') { echo json_encode(['success'=>false,'error'=>'Access denied. Admin role required.']); return; }
        if (password_verify($password, $user['password'])) {
            $userName = $_SESSION['name'] ?? 'Unknown';
            $logStmt  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'ADMIN_VERIFY', 'Admin password verified for asset edit')");
            $logStmt->bind_param("s", $userName);
            $logStmt->execute();
            echo json_encode(['success'=>true,'message'=>'Password verified']);
        } else {
            echo json_encode(['success'=>false,'error'=>'Incorrect password']);
        }
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'verifyAdminPassword error: '.$e->getMessage()]);
    }
}

// =============================================================================
// UPDATE ASSET
// =============================================================================
function updateAsset($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'Invalid request method']); return; }
    $data    = json_decode(file_get_contents('php://input'), true);
    $assetId = intval($data['asset_id'] ?? 0);
    if (!$assetId) { echo json_encode(['success'=>false,'error'=>'Asset ID required']); return; }

    $allowedConditions = ['NEW','USED'];
    $allowedStatuses   = ['WORKING','NOT WORKING','FOR CHECKING'];

    $brand        = trim($data['brand']            ?? '');
    $model        = trim($data['model']            ?? '');
    $serial       = trim($data['serial_number']    ?? '');
    $condition    = in_array($data['condition']??'', $allowedConditions) ? $data['condition'] : 'NEW';
    $status       = in_array($data['status']??'',    $allowedStatuses)   ? $data['status']    : 'WORKING';
    $location     = trim($data['location']         ?? '');
    $subLocation  = trim($data['sub_location']     ?? '');
    $description  = trim($data['description']      ?? '');
    $begBalance   = intval($data['beg_balance_count'] ?? -1);
    $activeAdjust = isset($data['active_count']) ? intval($data['active_count']) : -1;

    try {
        if ($activeAdjust >= 0) {
            $calcStmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN p.status='RELEASED' THEN p.quantity ELSE 0 END),0) AS released, COALESCE(SUM(CASE WHEN p.status='RETURNED' THEN p.quantity ELSE 0 END),0) AS returned FROM pull_out_transactions p WHERE p.asset_id=?");
            $calcStmt->bind_param("i", $assetId);
            $calcStmt->execute();
            $txn        = $calcStmt->get_result()->fetch_assoc();
            $released   = intval($txn['released'] ?? 0);
            $returned   = intval($txn['returned'] ?? 0);
            $begBalance = $activeAdjust + $released - $returned;
            if ($begBalance < 0) $begBalance = 0;
        }

        if ($begBalance >= 0) {
            $stmt = $conn->prepare("UPDATE assets SET brand=?, model=?, serial_number=?, `condition`=?, status=?, location=?, sub_location=?, description=?, beg_balance_count=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("ssssssssii", $brand, $model, $serial, $condition, $status, $location, $subLocation, $description, $begBalance, $assetId);
        } else {
            $stmt = $conn->prepare("UPDATE assets SET brand=?, model=?, serial_number=?, `condition`=?, status=?, location=?, sub_location=?, description=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("ssssssssi", $brand, $model, $serial, $condition, $status, $location, $subLocation, $description, $assetId);
        }
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);

        $userName = $_SESSION['name'] ?? 'Unknown';
        $logStmt  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'UPDATE_ASSET', ?)");
        $desc     = "Updated asset #$assetId by $userName";
        $logStmt->bind_param("ss", $userName, $desc);
        $logStmt->execute();

        echo json_encode(['success'=>true,'message'=>'Asset updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'updateAsset error: '.$e->getMessage()]);
    }
}

// =============================================================================
// SUBMIT ASSET TRANSFER REQUEST
// =============================================================================
function submitPullout($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'Invalid request method']); return; }
    $data = json_decode(file_get_contents('php://input'), true);

    $assetId         = intval($data['asset_id']       ?? 0);
    $quantity        = intval($data['quantity']        ?? 1);
    $dateNeeded      = trim($data['date_needed']       ?? '');
    $fromLocation    = trim($data['from_location']     ?? '');
    $fromSubLocation = trim($data['from_sub_location'] ?? '');
    $toLocation      = trim($data['to_location']       ?? '');
    $purpose         = trim($data['purpose']           ?? '');
    $requestedBy     = trim($data['requested_by']      ?? '');

    if (!$assetId || empty($dateNeeded) || empty($fromLocation) || empty($toLocation) || empty($purpose) || empty($requestedBy)) {
        echo json_encode(['success'=>false,'error'=>'Missing required fields']); return;
    }
    if ($quantity < 1)                 { echo json_encode(['success'=>false,'error'=>'Quantity must be at least 1']); return; }
    if ($fromLocation === $toLocation) { echo json_encode(['success'=>false,'error'=>'Source and recipient location cannot be the same']); return; }

    try {
        // FIX: Gamitin ang PENDING+CONFIRMED+RELEASED para consistent sa getInventory()
        $checkStmt = $conn->prepare("
            SELECT (a.beg_balance_count 
                    - COALESCE(SUM(CASE WHEN p.status IN ('PENDING','CONFIRMED','RELEASED') 
                                   THEN p.quantity ELSE 0 END), 0)
                   ) AS active_count
            FROM assets a
            LEFT JOIN pull_out_transactions p ON a.id = p.asset_id
            WHERE a.id = ?
            GROUP BY a.id
        ");
        $checkStmt->bind_param("i", $assetId);
        $checkStmt->execute();
        $activeData = $checkStmt->get_result()->fetch_assoc();
        if (!$activeData || $activeData['active_count'] < $quantity) {
            echo json_encode(['success'=>false,'error'=>'Insufficient active items for transfer']); return;
        }

        $fromLabel = $fromLocation . ($fromSubLocation ? " / $fromSubLocation" : '');
        $toLabel   = $toLocation;

        $insertStmt = $conn->prepare("INSERT INTO pull_out_transactions (asset_id, quantity, purpose, requested_by, date_needed, from_location, to_location, location_received, released_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', 'PENDING')");
        $insertStmt->bind_param("iissssss", $assetId, $quantity, $purpose, $requestedBy, $dateNeeded, $fromLabel, $toLabel, $toLabel);
        if (!$insertStmt->execute()) throw new Exception('Insert failed: ' . $insertStmt->error);

        $transactionId = $conn->insert_id;
        $userName      = $_SESSION['name'] ?? 'Unknown';
        $logStmt       = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'ASSET_TRANSFER', ?)");
        $desc          = "Transfer #$transactionId: Asset #$assetId ($quantity pcs) from $fromLabel → $toLabel by $requestedBy [PENDING]";
        $logStmt->bind_param("ss", $userName, $desc);
        $logStmt->execute();

        echo json_encode(['success'=>true,'message'=>'Asset transfer request submitted successfully.','transaction_id'=>$transactionId]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'submitTransfer error: '.$e->getMessage()]);
    }
}

// =============================================================================
// GET SUBCATEGORIES
// =============================================================================
function getSubcategories($conn)
{
    $categoryId = intval($_GET['category_id'] ?? 0);
    if (!$categoryId) { echo json_encode(['success'=>false,'error'=>'category_id required']); return; }
    try {
        $stmt = $conn->prepare("SELECT id, name FROM sub_categories WHERE category_id = ? ORDER BY name");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data   = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        echo json_encode(['success'=>true,'data'=>$data]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'getSubcategories error: '.$e->getMessage()]);
    }
}

// =============================================================================
// GET CATEGORIES
// =============================================================================
function getCategories($conn)
{
    try {
        $result = $conn->query("SELECT id, name FROM categories ORDER BY name");
        if (!$result) throw new Exception('Query failed: ' . $conn->error);
        $categories = [];
        while ($row = $result->fetch_assoc()) $categories[] = $row;
        echo json_encode(['success'=>true,'data'=>$categories,'count'=>count($categories)]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'getCategories error: '.$e->getMessage()]);
    }
}

// =============================================================================
// GET ASSET TRANSFERS
// =============================================================================
function getAssetTransfers($conn)
{
    $assetId = intval($_GET['asset_id'] ?? 0);
    if (!$assetId) { echo json_encode(['success'=>false,'error'=>'Asset ID required']); return; }
    try {
        $stmt = $conn->prepare("SELECT * FROM pull_out_transactions WHERE asset_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $assetId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        echo json_encode(['success'=>true,'data'=>$rows]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'getAssetTransfers error: '.$e->getMessage()]);
    }
}

// =============================================================================
// DELETE ASSET IMAGE
// =============================================================================
function deleteAssetImage($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success'=>false,'error'=>'Invalid request method']); return;
    }
    $data    = json_decode(file_get_contents('php://input'), true);
    $imageId = intval($data['image_id'] ?? 0);
    $assetId = intval($data['asset_id'] ?? 0);

    if (!$imageId || !$assetId) {
        echo json_encode(['success'=>false,'error'=>'image_id and asset_id required']); return;
    }

    try {
        $stmt = $conn->prepare("SELECT image_path FROM asset_images WHERE id = ? AND asset_id = ?");
        $stmt->bind_param("ii", $imageId, $assetId);
        $stmt->execute();
        $img = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$img) { echo json_encode(['success'=>false,'error'=>'Image not found']); return; }

        if (!empty($img['image_path']) && $img['image_path'] !== 'gdrive_folder') {
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($img['image_path'], '/');
            if (file_exists($fullPath)) @unlink($fullPath);
        }

        $del = $conn->prepare("DELETE FROM asset_images WHERE id = ? AND asset_id = ?");
        $del->bind_param("ii", $imageId, $assetId);
        $del->execute();
        $del->close();

        $userName = $_SESSION['name'] ?? 'Unknown';
        $logStmt  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'DELETE_IMAGE', ?)");
        $desc     = "Deleted image #$imageId from asset #$assetId by $userName";
        $logStmt->bind_param("ss", $userName, $desc);
        $logStmt->execute();

        echo json_encode(['success'=>true,'message'=>'Image deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'deleteAssetImage error: '.$e->getMessage()]);
    }
}

// =============================================================================
// ADD ASSET IMAGE
// =============================================================================
function addAssetImage($conn)
{
    $assetId = intval($_POST['asset_id'] ?? 0);
    if (!$assetId) { echo json_encode(['success'=>false,'error'=>'asset_id required']); return; }

    try {
        // --- Option A: Drive / Sheets URL ---
        if (!empty($_POST['drive_url'])) {
            $driveUrl = trim($_POST['drive_url']);
            $stmt = $conn->prepare("INSERT INTO asset_images (asset_id, image_path, drive_url) VALUES (?, 'gdrive_folder', ?)");
            $stmt->bind_param("is", $assetId, $driveUrl);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();

            $userName = $_SESSION['name'] ?? 'Unknown';
            $logStmt  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'ADD_IMAGE', ?)");
            $desc     = "Added Drive URL image to asset #$assetId by $userName";
            $logStmt->bind_param("ss", $userName, $desc);
            $logStmt->execute();

            echo json_encode(['success'=>true,'message'=>'Drive URL added','image_id'=>$newId]);
            return;
        }

        // --- Option B: File upload ---
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success'=>false,'error'=>'No valid file uploaded. Please select a file or enter a Drive URL.']); return;
        }

        $file     = $_FILES['image'];
        $allowed  = ['image/jpeg','image/png','image/gif','image/webp'];
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowed)) {
            echo json_encode(['success'=>false,'error'=>'Invalid file type. Only JPG, PNG, GIF, WEBP are allowed.']); return;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success'=>false,'error'=>'File too large. Maximum size is 5MB.']); return;
        }

        $uploadDir = __DIR__ . '/uploads/assets/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
        $filename = 'asset_' . $assetId . '_' . uniqid() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success'=>false,'error'=>'Failed to save uploaded file. Check folder permissions.']); return;
        }

        $scriptDir    = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $basePath     = rtrim($scriptDir, '/');
        $relativePath = ltrim($basePath . '/uploads/assets/' . $filename, '/');
        $stmt = $conn->prepare("INSERT INTO asset_images (asset_id, image_path, drive_url) VALUES (?, ?, NULL)");
        $stmt->bind_param("is", $assetId, $relativePath);
        $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();

        $userName = $_SESSION['name'] ?? 'Unknown';
        $logStmt  = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'ADD_IMAGE', ?)");
        $desc     = "Uploaded image to asset #$assetId by $userName";
        $logStmt->bind_param("ss", $userName, $desc);
        $logStmt->execute();

        echo json_encode(['success'=>true,'message'=>'Image uploaded successfully','image_id'=>$newId,'path'=>'/'.$relativePath]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'addAssetImage error: '.$e->getMessage()]);
    }
}