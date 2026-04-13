<?php
/**
 * get-upload-history.php
 * Returns paginated upload/add history (both Manual and Excel) with filters.
 *
 * GET  ?page=1&per_page=10&date_from=2026-01-01&date_to=2026-12-31
 *      &uploaded_by=1&search=filename.xlsx&source_type=EXCEL
 *
 * GET  ?mode=batch_detail&token=<batch_token>
 *      For EXCEL  → returns rows from asset_staging
 *      For MANUAL → returns the single asset row from assets table
 *
 * GET  ?mode=users
 *      Returns distinct uploaders for filter dropdown.
 */

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$conn = getDBConnection();
$mode = $_GET['mode'] ?? 'list';

// ── MODE: users list ──────────────────────────────────────────────────────────
if ($mode === 'users') {
    $rows = [];
    $res  = $conn->query("
        SELECT DISTINCT uploaded_by, uploaded_by_name
        FROM excel_upload_history
        ORDER BY uploaded_by_name ASC
    ");
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['success' => true, 'users' => $rows]);
    exit();
}

// ── MODE: batch detail ────────────────────────────────────────────────────────
if ($mode === 'batch_detail') {
    $token = trim($_GET['token'] ?? '');
    if (!$token) {
        echo json_encode(['success' => false, 'error' => 'No token provided']);
        exit();
    }

    $isManual = str_starts_with($token, 'manual_');

    if ($isManual) {
        $parts    = explode('_', $token);
        $asset_id = isset($parts[1]) ? (int)$parts[1] : 0;

        if (!$asset_id) {
            echo json_encode(['success' => false, 'error' => 'Invalid manual token']);
            exit();
        }

        $stmt = $conn->prepare("
            SELECT
                a.id,
                1                   AS row_num,
                c.name              AS category_name,
                sc.name             AS sub_category_name,
                a.brand,
                a.model,
                a.serial_number,
                a.beg_balance_count AS qty,
                a.`condition`,
                a.status,
                a.location,
                a.sub_location,
                a.description,
                1                   AS is_valid,
                NULL                AS error_notes,
                a.created_at
            FROM assets a
            LEFT JOIN categories     c  ON c.id  = a.category_id
            LEFT JOIN sub_categories sc ON sc.id = a.sub_category_id
            WHERE a.id = ?
        ");
        $stmt->bind_param('i', $asset_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Asset not found']);
            exit();
        }

        // Get photos separately
        $stmtImg = $conn->prepare("
            SELECT image_path, drive_url
            FROM asset_images
            WHERE asset_id = ?
            ORDER BY id ASC
            LIMIT 3
        ");
        $stmtImg->bind_param('i', $asset_id);
        $stmtImg->execute();
        $imgs = $stmtImg->get_result()->fetch_all(MYSQLI_ASSOC);

        $row['photo_drive_url_1'] = isset($imgs[0]) ? ($imgs[0]['drive_url'] ?: $imgs[0]['image_path']) : null;
        $row['photo_drive_url_2'] = isset($imgs[1]) ? ($imgs[1]['drive_url'] ?: $imgs[1]['image_path']) : null;
        $row['photo_drive_url_3'] = isset($imgs[2]) ? ($imgs[2]['drive_url'] ?: $imgs[2]['image_path']) : null;
        $row['source_type']       = 'MANUAL';

        echo json_encode(['success' => true, 'rows' => [$row], 'total' => 1, 'source_type' => 'MANUAL']);

    } else {
        // EXCEL — query asset_staging
        $stmt = $conn->prepare("
            SELECT
                s.id,
                s.row_num,
                c.name              AS category_name,
                sc.name             AS sub_category_name,
                s.brand,
                s.model,
                s.serial_number,
                s.beg_balance_count AS qty,
                s.`condition`,
                s.status,
                s.location,
                s.sub_location,
                s.description,
                s.photo_drive_url_1,
                s.photo_drive_url_2,
                s.photo_drive_url_3,
                s.is_valid,
                s.error_notes,
                s.created_at
            FROM asset_staging s
            LEFT JOIN categories     c  ON c.id  = s.category_id
            LEFT JOIN sub_categories sc ON sc.id = s.sub_category_id
            WHERE s.session_token = ?
            ORDER BY s.row_num ASC
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($r = $result->fetch_assoc()) $rows[] = $r;

        echo json_encode(['success' => true, 'rows' => $rows, 'total' => count($rows), 'source_type' => 'EXCEL']);
    }
    exit();
}

// ── MODE: list (paginated) ────────────────────────────────────────────────────
$page    = max(1, (int)($_GET['page']     ?? 1));
$perPage = max(5,  (int)($_GET['per_page'] ?? 10));
$offset  = ($page - 1) * $perPage;

$dateFrom   = $_GET['date_from']   ?? '';
$dateTo     = $_GET['date_to']     ?? '';
$uploadedBy = $_GET['uploaded_by'] ?? '';
$search     = trim($_GET['search'] ?? '');
$sourceType = $_GET['source_type'] ?? '';   // 'MANUAL' | 'EXCEL' | ''

$where  = [];
$params = [];
$types  = '';

$currentUserId = (int)$_SESSION['user_id'];
$userRole      = $_SESSION['role'] ?? 'EMPLOYEE';

if ($userRole !== 'ADMIN') {
    $where[]  = 'h.uploaded_by = ?';
    $params[] = $currentUserId;
    $types   .= 'i';
}

if ($dateFrom) {
    $where[]  = 'h.imported_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
    $types   .= 's';
}
if ($dateTo) {
    $where[]  = 'h.imported_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
    $types   .= 's';
}

if ($search !== '') {
    $where[]  = 'h.filename LIKE ?';
    $params[] = '%' . $search . '%';
    $types   .= 's';
}
if (in_array($sourceType, ['MANUAL', 'EXCEL'])) {
    $where[]  = 'h.source_type = ?';
    $params[] = $sourceType;
    $types   .= 's';
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
$countSQL = "SELECT COUNT(*) AS total FROM excel_upload_history h $whereSQL";
if ($params) {
    $stmtC = $conn->prepare($countSQL);
    $stmtC->bind_param($types, ...$params);
    $stmtC->execute();
    $total = $stmtC->get_result()->fetch_assoc()['total'];
} else {
    $total = $conn->query($countSQL)->fetch_assoc()['total'];
}

// Fetch page
$sql = "
    SELECT
        h.id,
        h.source_type,        -- ← IDAGDAG ITO
        h.batch_token,
        h.filename,
        h.uploaded_by,
        h.uploaded_by_name,
        h.total_rows,
        h.valid_rows,
        h.invalid_rows,
        h.status,
        h.imported_at
    FROM excel_upload_history h
    $whereSQL
    ORDER BY h.imported_at DESC
    LIMIT ? OFFSET ?
";

$allParams = [...$params, $perPage, $offset];
$allTypes  = $types . 'ii';

$stmtL = $conn->prepare($sql);
$stmtL->bind_param($allTypes, ...$allParams);
$stmtL->execute();
$result = $stmtL->get_result();

$rows = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;

// Ang JSON_INVALID_UTF8_SUBSTITUTE at JSON_UNESCAPED_UNICODE ay para "safe" itong maging JSON kahit may weird characters
// Idagdag ang ob_clean() bago mag echo
ob_clean();
echo json_encode([
    'success'     => true,
    'data'        => $rows,
    'total'       => (int)$total,
    'page'        => $page,
    'per_page'    => $perPage,
    'total_pages' => (int)ceil($total / $perPage),
]);
exit();