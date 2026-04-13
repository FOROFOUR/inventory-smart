<?php
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>'PHP Fatal Error: '.$error['message']]);
    }
});

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit();
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
if (!$token) {
    ob_end_clean();
    echo json_encode(['success'=>false,'error'=>'No token provided.']); exit();
}

$conn     = getDBConnection();
$userName = $_SESSION['name'] ?? 'Unknown';

// ── Fetch valid staged rows ───────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM asset_staging WHERE session_token = ? AND is_valid = 1 ORDER BY row_num");
$stmt->bind_param('s', $token);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($rows)) {
    ob_end_clean();
    echo json_encode(['success'=>false,'error'=>'No valid rows found for this token.']); exit();
}

// ── Prepare statements ────────────────────────────────────────────────────
$assetStmt = $conn->prepare("
    INSERT INTO assets
        (category_id, sub_category_id, brand, model, serial_number,
         beg_balance_count, `condition`, status, location, sub_location,
         description, tracking_type, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'BULK', NOW())
");

$imageStmt = $conn->prepare("
    INSERT INTO asset_images (asset_id, image_path, drive_url, created_at)
    VALUES (?, 'gdrive_folder', ?, NOW())
");

$logStmt = $conn->prepare("
    INSERT INTO activity_logs (user_name, action, description, created_at)
    VALUES (?, 'BULK_IMPORT', ?, NOW())
");

$imported = 0;
$failed   = 0;

$conn->begin_transaction();
try {
    foreach ($rows as $row) {

        // NULL-safe values for bind_param
        $catId    = ($row['category_id']     !== null && $row['category_id']     !== '') ? (int)$row['category_id']     : null;
        $subCatId = ($row['sub_category_id'] !== null && $row['sub_category_id'] !== '') ? (int)$row['sub_category_id'] : null;
        $brand    = ($row['brand']       ?? '') !== '' ? $row['brand']       : null;
        $model    = ($row['model']       ?? '') !== '' ? $row['model']       : null;
        $serial   = ($row['serial_number']?? '') !== '' ? $row['serial_number']: null;
        $qty      = (int)($row['beg_balance_count'] ?? 1);

        // ── FIX: Strip ALL non-letter characters (invisible chars, BOM, NBSP, \r, etc.)
        //         before validating condition — this is the root cause of USED → NEW bug
       $condRaw = trim((string)($row['condition'] ?? ''));
// Handle numeric: 0 = NEW, 1 = USED (just in case stored as int)
if (is_numeric($condRaw)) {
    $condition = (intval($condRaw) === 1) ? 'USED' : 'NEW';
} else {
    $condClean = strtoupper(preg_replace('/[^A-Za-z]/', '', $condRaw));
    $condition = ($condClean === 'USED') ? 'USED' : 'NEW';
}
        // ── Same treatment for status ─────────────────────────────────────
        $statRaw = strtoupper(trim(preg_replace('/\s+/', ' ', $row['status'] ?? '')));
        $status  = in_array($statRaw, ['WORKING', 'NOT WORKING', 'FOR CHECKING']) ? $statRaw : 'WORKING';

        $location    = $row['location']     ?? null;
        $subLocation = ($row['sub_location']?? '') !== '' ? $row['sub_location'] : null;
        $description = ($row['description'] ?? '') !== '' ? $row['description']  : null;

        $assetStmt->bind_param(
            'iisssisssss',
            $catId, $subCatId, $brand, $model, $serial,
            $qty, $condition, $status, $location, $subLocation, $description
        );

        if (!$assetStmt->execute()) {
            error_log("Asset insert failed row {$row['row_num']}: " . $assetStmt->error);
            $failed++;
            continue;
        }

        $assetId = $conn->insert_id;
        $imported++;

        // Save up to 3 Drive photo URLs
        foreach ([1, 2, 3] as $n) {
            $url = $row["photo_drive_url_$n"] ?? '';
            if (!empty($url)) {
                $imageStmt->bind_param('is', $assetId, $url);
                if (!$imageStmt->execute()) {
                    error_log("Drive URL $n insert failed for asset $assetId: " . $imageStmt->error);
                }
            }
        }
    }

    $statsStmt = $conn->prepare("
    SELECT
        COUNT(*)                          AS total_rows,
        SUM(is_valid = 1)                 AS valid_rows,
        SUM(is_valid = 0)                 AS invalid_rows,
        MAX(created_at)                   AS batch_time
    FROM asset_staging
    WHERE session_token = ?
");
$statsStmt->bind_param('s', $token);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

// Retrieve the original filename stored in staging meta
// (If you store it: add a `filename` column to asset_staging,
//  OR pass it from the front-end in the confirm request body.)
// For now we fall back to a generated name.
$filename = $data['filename'] ?? ('import_' . date('Ymd_His') . '.xlsx');

$uploadedById   = $_SESSION['user_id'];
$uploadedByName = $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Unknown';

$histStmt = $conn->prepare("
    INSERT INTO excel_upload_history
        (batch_token, filename, uploaded_by, uploaded_by_name,
         total_rows, valid_rows, invalid_rows, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'SUCCESS')
");
$histStmt->bind_param(
    'ssissii',
    $token,
    $filename,
    $uploadedById,
    $uploadedByName,
    $stats['total_rows'],
    $stats['valid_rows'],
    $stats['invalid_rows']
);
$histStmt->execute();
    // Activity log
    $desc = "Bulk imported $imported asset(s) via Excel upload.";
    if ($failed > 0) $desc .= " $failed row(s) failed.";
    $logStmt->bind_param('ss', $userName, $desc);
    $logStmt->execute();

    $conn->commit();

    // Clean up staging rows
  $markStmt = $conn->prepare("UPDATE asset_staging SET is_valid = 2 WHERE session_token = ?");
$markStmt->bind_param('s', $token);
$markStmt->execute();
$markStmt->close();

    ob_end_clean();
    echo json_encode([
        'success'  => true,
        'imported' => $imported,
        'failed'   => $failed,
        'message'  => "$imported asset(s) imported successfully." . ($failed > 0 ? " $failed row(s) skipped." : ""),
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Bulk import exception: " . $e->getMessage());
    ob_end_clean();
    echo json_encode(['success'=>false,'error'=>'Import failed: '.$e->getMessage()]);
}

$assetStmt->close();
$imageStmt->close();
$logStmt->close();