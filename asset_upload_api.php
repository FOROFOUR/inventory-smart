<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$conn     = getDBConnection();
$userName = $_SESSION['name'] ?? 'Unknown';

// ── Read POST fields (FormData / multipart)
$categoryId    = intval($_POST['category_id']      ?? 0);
$subCategoryId = intval($_POST['sub_category_id']  ?? 0);
$brand         = trim($_POST['brand']              ?? '');
$model         = trim($_POST['model']              ?? '');
$serialNumber  = trim($_POST['serial_number']      ?? '');
$begBalance    = intval($_POST['beg_balance_count'] ?? 1);
$condition     = strtoupper(trim($_POST['condition'] ?? 'NEW'));
$status        = strtoupper(trim($_POST['status']   ?? 'WORKING'));
$location      = trim($_POST['location']            ?? '');
$subLocation   = trim($_POST['sub_location']        ?? '');
$description   = trim($_POST['description']         ?? '');
$trackingType  = trim($_POST['tracking_type']       ?? 'BULK');

// ── Validate required
$errors = [];
if (!$categoryId)    $errors[] = 'Category is required';
if (!$subCategoryId) $errors[] = 'Sub-Category is required';
if (!$location)      $errors[] = 'Location is required';
if ($begBalance < 1) $begBalance = 1;

$validConditions = ['NEW', 'USED'];
$validStatuses   = ['WORKING', 'FOR CHECKING', 'NOT WORKING'];
if (!in_array($condition, $validConditions)) $condition = 'NEW';
if (!in_array($status,    $validStatuses))   $status    = 'WORKING';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
    exit();
}

try {
    $conn->begin_transaction();

    // Insert asset
    $stmt = $conn->prepare("
        INSERT INTO assets 
            (category_id, sub_category_id, brand, model, serial_number,
             beg_balance_count, `condition`, status, location, sub_location,
             description, tracking_type, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param("iisssissssss",
        $categoryId, $subCategoryId, $brand, $model, $serialNumber,
        $begBalance, $condition, $status, $location, $subLocation,
        $description, $trackingType
    );

    if (!$stmt->execute()) {
        throw new Exception('Insert failed: ' . $stmt->error);
    }

    $assetId = $conn->insert_id;

    // ── Handle image uploads (up to 3)
    $uploadDir = __DIR__ . '/uploads/assets/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $imageFields = ['images[0]', 'images[1]', 'images[2]'];
    foreach ($imageFields as $field) {
        // FormData sends array-style: images[0], images[1], images[2]
        // PHP parses as $_FILES['images']['name'][0], etc.
    }

    // Handle $_FILES['images'] array if present
    if (!empty($_FILES['images']['name'][0])) {
        $imgStmt = $conn->prepare("INSERT INTO asset_images (asset_id, image_path) VALUES (?, ?)");
        foreach ($_FILES['images']['name'] as $idx => $name) {
            if (empty($name) || $_FILES['images']['error'][$idx] !== UPLOAD_ERR_OK) continue;
            $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed)) continue;

            $filename = 'asset_' . $assetId . '_' . $idx . '_' . time() . '.' . $ext;
            $dest     = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$idx], $dest)) {
                $imgPath = 'uploads/assets/' . $filename;
                $imgStmt->bind_param("is", $assetId, $imgPath);
                $imgStmt->execute();
            }
        }
    }

    // Activity log
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'ADD_ASSET', ?)");
    $desc    = "Added asset #$assetId: $brand $model by $userName";
    $logStmt->bind_param("ss", $userName, $desc);
    $logStmt->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Asset saved successfully!', 'asset_id' => $assetId]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}