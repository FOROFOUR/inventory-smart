<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login first']);
    exit();
}

try {
    $conn = getDBConnection();
    if (!$conn) throw new Exception('Database connection failed');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    // ── 1. GET & VALIDATE FIELDS ──────────────────────────────────────────────

    $category_id = intval($_POST['category_id'] ?? 0);
    if ($category_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select a category.']);
        exit();
    }

    // sub_category_id: accept numeric ID (from fixed JS) or fallback to name lookup
    $sub_category_id = NULL;
    $sub_cat_raw     = trim($_POST['sub_category_id'] ?? '');

    if (!empty($sub_cat_raw)) {
        if (is_numeric($sub_cat_raw) && intval($sub_cat_raw) > 0) {
            $sub_category_id = intval($sub_cat_raw);
        } else {
            // Fallback: lookup by name
            $scStmt = $conn->prepare(
                "SELECT id FROM sub_categories WHERE name = ? AND category_id = ? LIMIT 1"
            );
            $scStmt->bind_param("si", $sub_cat_raw, $category_id);
            $scStmt->execute();
            $scRow = $scStmt->get_result()->fetch_assoc();
            if ($scRow) {
                $sub_category_id = intval($scRow['id']);
            }
        }
    }

    $brand         = trim($_POST['brand']           ?? '');
    $model         = trim($_POST['model']           ?? '');
    $serial_number = trim($_POST['serial_number']   ?? '');
    $description   = trim($_POST['description']     ?? '');
    $tracking_type = 'BULK';
    $beg_balance   = max(1, intval($_POST['beg_balance_count'] ?? 1));
    $location      = trim($_POST['location']        ?? '');
    $sub_location  = trim($_POST['sub_location']    ?? '');

    $allowed_conditions = ['NEW', 'USED'];
    $allowed_statuses   = ['WORKING', 'NOT WORKING', 'FOR CHECKING'];

    $condition = in_array($_POST['condition'] ?? '', $allowed_conditions)
        ? $_POST['condition'] : 'NEW';
    $status    = in_array($_POST['status'] ?? '', $allowed_statuses)
        ? $_POST['status']    : 'WORKING';

    if (empty($location)) {
        echo json_encode(['success' => false, 'message' => 'Location is required.']);
        exit();
    }

    // ── 2. DUPLICATE SERIAL NUMBER CHECK ─────────────────────────────────────
    // Only check if serial number is provided (it is optional)
    if (!empty($serial_number)) {
        $dupStmt = $conn->prepare(
            "SELECT id, brand, model FROM assets WHERE serial_number = ? LIMIT 1"
        );
        $dupStmt->bind_param("s", $serial_number);
        $dupStmt->execute();
        $dupRow = $dupStmt->get_result()->fetch_assoc();

        if ($dupRow) {
            echo json_encode([
                'success'   => false,
                'duplicate' => true,
                'message'   => "Serial number \"$serial_number\" already exists (Asset ID: #{$dupRow['id']} — {$dupRow['brand']} {$dupRow['model']})."
            ]);
            exit();
        }
    }

    // ── 3. GENERATE QR CODE ───────────────────────────────────────────────────
    $qr_code = 'ASSET-BULK-' . time() . rand(100, 999);

    // ── 4. INSERT ASSET ───────────────────────────────────────────────────────
    $sql  = "INSERT INTO assets 
                (category_id, sub_category_id, brand, model, serial_number,
                 description, tracking_type, `condition`, status,
                 location, sub_location, beg_balance_count, qr_code)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);

    $stmt->bind_param(
        "iisssssssssis",
        $category_id,
        $sub_category_id,
        $brand,
        $model,
        $serial_number,
        $description,
        $tracking_type,
        $condition,
        $status,
        $location,
        $sub_location,
        $beg_balance,
        $qr_code
    );

    if (!$stmt->execute()) throw new Exception('Insert failed: ' . $stmt->error);

    $asset_id = $conn->insert_id;

    // ── 5. HANDLE IMAGE UPLOADS ───────────────────────────────────────────────
    $upload_dir = __DIR__ . '/../../uploads/assets/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_mime = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $max_size     = 5 * 1024 * 1024; // 5MB

    if (!empty($_FILES['images']['name'][0])) {
        $imgStmt = $conn->prepare(
            "INSERT INTO asset_images (asset_id, image_path) VALUES (?, ?)"
        );
        foreach ($_FILES['images']['tmp_name'] as $idx => $tmp_name) {
            if (empty($tmp_name) || $_FILES['images']['error'][$idx] !== UPLOAD_ERR_OK) continue;
            $file_size = $_FILES['images']['size'][$idx];
            $file_type = mime_content_type($tmp_name);
            if (!in_array($file_type, $allowed_mime)) continue;
            if ($file_size > $max_size)               continue;
            $ext       = pathinfo($_FILES['images']['name'][$idx], PATHINFO_EXTENSION);
            $file_name = 'asset_' . $asset_id . '_' . time() . '_' . $idx . '.' . strtolower($ext);
            $dest      = $upload_dir . $file_name;
            if (move_uploaded_file($tmp_name, $dest)) {
                $img_path = 'uploads/assets/' . $file_name;
                $imgStmt->bind_param("is", $asset_id, $img_path);
                $imgStmt->execute();
            }
        }
    }

    // ── 6. ACTIVITY LOG ───────────────────────────────────────────────────────
    $user_name = $_SESSION['name'] ?? 'Unknown';
    $log_desc  = "Added new asset: $brand $model (ID: $asset_id)";
    $logStmt   = $conn->prepare(
        "INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'ADD_ASSET', ?)"
    );
    $logStmt->bind_param("ss", $user_name, $log_desc);
    $logStmt->execute();

    echo json_encode([
        'success'  => true,
        'message'  => 'Asset added successfully!',
        'asset_id' => $asset_id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

ob_end_flush();