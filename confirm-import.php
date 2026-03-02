<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit();
}

header('Content-Type: application/json');
$conn     = getDBConnection();
$userName = $_SESSION['name'] ?? 'Unknown';

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');

if (!$token) {
    echo json_encode(['success'=>false,'error'=>'Missing session token.']); exit();
}

// Fetch only valid staged rows for this token
$stmt = $conn->prepare("
    SELECT * FROM asset_staging 
    WHERE session_token = ? AND is_valid = 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);

if (empty($rows)) {
    echo json_encode(['success'=>false,'error'=>'No valid rows found for this token. They may have already been imported or expired.']); exit();
}

$insertStmt = $conn->prepare("
    INSERT INTO assets 
        (category_id, sub_category_id, brand, model, serial_number, beg_balance_count,
         `condition`, status, location, sub_location, description, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
");

$logStmt = $conn->prepare("
    INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'EXCEL_UPLOAD', ?)
");

$inserted = 0;
$failed   = 0;

$conn->begin_transaction();
try {
    foreach ($rows as $row) {
        $insertStmt->bind_param("iisssisssss",
            $row['category_id'], $row['sub_category_id'],
            $row['brand'], $row['model'], $row['serial_number'],
            $row['beg_balance_count'], $row['condition'], $row['status'],
            $row['location'], $row['sub_location'], $row['description']
        );
        if ($insertStmt->execute()) {
            $inserted++;
            $desc = "Bulk imported: {$row['brand']} {$row['model']} (staged row {$row['row_num']}) by $userName";
            $logStmt->bind_param("ss", $userName, $desc);
            $logStmt->execute();
        } else {
            $failed++;
        }
    }

    // Delete staging rows for this token (both valid and invalid — cleanup)
    $del = $conn->prepare("DELETE FROM asset_staging WHERE session_token = ?");
    $del->bind_param("s", $token);
    $del->execute();

    $conn->commit();
    echo json_encode([
        'success'  => true,
        'inserted' => $inserted,
        'failed'   => $failed,
        'message'  => "$inserted asset(s) successfully imported.",
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'error'=>'Transaction failed: '.$e->getMessage()]);
}