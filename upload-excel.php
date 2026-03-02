<?php
// Catch fatal errors and return as JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'PHP Fatal Error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit();
}

header('Content-Type: application/json');
$conn     = getDBConnection();
$userName = $_SESSION['name'] ?? 'Unknown';

if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']); exit();
}

// ── Auto-create asset_staging if missing ─────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS `asset_staging` (
      `id`                INT(11)      NOT NULL AUTO_INCREMENT,
      `session_token`     VARCHAR(64)  NOT NULL,
      `row_num`           INT(11)      NOT NULL,
      `category_id`       INT(11)      DEFAULT NULL,
      `sub_category_id`   INT(11)      DEFAULT NULL,
      `brand`             VARCHAR(255) DEFAULT NULL,
      `model`             VARCHAR(255) DEFAULT NULL,
      `serial_number`     VARCHAR(255) DEFAULT NULL,
      `beg_balance_count` INT(11)      DEFAULT 1,
      `condition`         ENUM('NEW','USED') DEFAULT NULL,
      `status`            VARCHAR(50)  DEFAULT NULL,
      `location`          VARCHAR(255) DEFAULT NULL,
      `sub_location`      VARCHAR(255) DEFAULT NULL,
      `description`       TEXT         DEFAULT NULL,
      `is_valid`          TINYINT(1)   DEFAULT 1,
      `error_notes`       TEXT         DEFAULT NULL,
      `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      INDEX `idx_session_token` (`session_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$file = $_FILES['excel_file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['csv','xlsx','xls'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Only CSV, XLSX, XLS allowed.']); exit();
}

// ── Helpers ───────────────────────────────────────────────────────────────
function normalizeField($raw) {
    $val = strtolower(trim($raw ?? ''));
    return in_array($val, ['','undefined','n/a','na','-','none','null','no sn','no serial'])
        ? null : trim($raw);
}
function normalizeCondition($raw) {
    $map = ['new'=>'NEW','used'=>'USED'];
    return $map[strtolower(trim($raw ?? ''))] ?? strtoupper(trim($raw ?? ''));
}
function normalizeStatus($raw) {
    $map = [
        'working'=>'WORKING','not working'=>'NOT WORKING','notworking'=>'NOT WORKING',
        'for checking'=>'FOR CHECKING','forchecking'=>'FOR CHECKING','for-checking'=>'FOR CHECKING',
    ];
    return $map[strtolower(trim($raw ?? ''))] ?? strtoupper(trim($raw ?? ''));
}

// ── Read rows ─────────────────────────────────────────────────────────────
$rows = [];
if ($ext === 'csv') {
    $handle = fopen($file['tmp_name'], 'r');
    $bom    = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    $header = null;
    while (($line = fgetcsv($handle)) !== false) {
        if (!$header) { $header = array_map('strtolower', array_map('trim', $line)); continue; }
        if (isset($line[0]) && strpos($line[0], '===') !== false) break;
        if (array_filter($line)) $rows[] = array_combine($header, array_pad($line, count($header), ''));
    }
    fclose($handle);
} else {
    $tmpCsv = tempnam(sys_get_temp_dir(), 'xl_') . '.csv';
    $script = 'import sys, csv
try:
    import openpyxl
    wb = openpyxl.load_workbook(sys.argv[1], data_only=True)
    ws = wb.active
    with open(sys.argv[2], "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        for row in ws.iter_rows(values_only=True):
            writer.writerow(["" if v is None else str(v) for v in row])
    print("ok")
except Exception as e:
    print("error:"+str(e))';
    $sf  = tempnam(sys_get_temp_dir(), 'xlscript_') . '.py';
    file_put_contents($sf, $script);
    $out = shell_exec("python3 ".escapeshellarg($sf)." ".escapeshellarg($file['tmp_name'])." ".escapeshellarg($tmpCsv)." 2>&1");
    unlink($sf);
    if (!file_exists($tmpCsv) || strpos($out, 'error:') !== false) {
        ob_end_clean();
        echo json_encode(['success'=>false,'error'=>'Could not read XLSX. Detail: '.$out]); exit();
    }
    $handle = fopen($tmpCsv, 'r'); $header = null;
    while (($line = fgetcsv($handle)) !== false) {
        if (!$header) { $header = array_map('strtolower', array_map('trim', $line)); continue; }
        if (isset($line[0]) && strpos($line[0], '===') !== false) break;
        if (array_filter($line)) $rows[] = array_combine($header, array_pad($line, count($header), ''));
    }
    fclose($handle); unlink($tmpCsv);
}

if (empty($rows)) {
    ob_end_clean();
    echo json_encode(['success'=>false,'error'=>'No data rows found.']); exit();
}

// ── Lookup maps ───────────────────────────────────────────────────────────
$catMap = [];
$r = $conn->query("SELECT id, LOWER(TRIM(name)) AS name FROM categories");
while ($row = $r->fetch_assoc()) $catMap[$row['name']] = $row['id'];

$subCatMap = [];
$r = $conn->query("SELECT sc.id, LOWER(TRIM(sc.name)) AS name, LOWER(TRIM(c.name)) AS category
                   FROM sub_categories sc JOIN categories c ON sc.category_id = c.id");
while ($row = $r->fetch_assoc()) $subCatMap[$row['category']][$row['name']] = $row['id'];

$subCatAliases = [
    'laptop unit'=>'laptop','laptops'=>'laptop','hdd'=>'hard drive','ssd'=>'hard drive',
    'cctv unit'=>'cctv','cctv camera'=>'cctv','cctv accessories'=>'security & cctv accessories',
    'bracket mounting kit'=>'access point mounting kit','faceplate'=>'plate faceplates',
    'faceplates'=>'plate faceplates','hdcvi transceiver'=>'network transceiver modules',
    'hd video balun'=>'network transceiver modules',
];

$existingSerials = [];
$r = $conn->query("SELECT serial_number FROM assets WHERE serial_number IS NOT NULL AND TRIM(serial_number) != ''");
while ($row = $r->fetch_assoc()) $existingSerials[strtolower(trim($row['serial_number']))] = true;

$token = bin2hex(random_bytes(16));

$stageStmt = $conn->prepare("
    INSERT INTO asset_staging
        (session_token, row_num, category_id, sub_category_id,
         brand, model, serial_number, beg_balance_count,
         `condition`, status, location, sub_location,
         description, is_valid, error_notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stageStmt) {
    ob_end_clean();
    echo json_encode(['success'=>false,'error'=>'Staging prepare failed: '.$conn->error]); exit();
}

$batchSerials = []; $validRows = 0; $invalidRows = 0; $preview = [];

foreach ($rows as $i => $row) {
    $rowNum = $i + 2;
    if (isset($row['brand']) && strtolower(trim($row['brand'])) === 'logitech' &&
        isset($row['serial_number']) && strtolower(trim($row['serial_number'])) === 'sn-00123') continue;
    if (empty(array_filter(array_values($row), fn($v) => trim($v) !== ''))) continue;

    $rowErrors = [];
    foreach (['category','sub_category','quantity','condition','status','location'] as $req) {
        if (empty(trim($row[$req] ?? ''))) $rowErrors[] = "Missing: $req";
    }

    $catKey = strtolower(trim($row['category'] ?? ''));
    $catId  = $catMap[$catKey] ?? null;
    if (!$catId) $rowErrors[] = "Unknown category: \"{$row['category']}\"";

    $subCatKey = strtolower(trim($row['sub_category'] ?? ''));
    $subCatId  = null;
    if ($catId) {
        if (isset($subCatMap[$catKey][$subCatKey])) {
            $subCatId = $subCatMap[$catKey][$subCatKey];
        } elseif (isset($subCatAliases[$subCatKey])) {
            $alias = $subCatAliases[$subCatKey];
            foreach ($subCatMap as $subs) {
                if (isset($subs[$alias])) { $subCatId = $subs[$alias]; break; }
            }
        }
        if (!$subCatId) {
            foreach ($subCatMap[$catKey] ?? [] as $dbName => $dbId) {
                if (str_contains($dbName, $subCatKey) || str_contains($subCatKey, $dbName)) {
                    $subCatId = $dbId; break;
                }
            }
        }
        if (!$subCatId) $rowErrors[] = "Unknown sub-category: \"{$row['sub_category']}\"";
    }

    $condition   = normalizeCondition($row['condition'] ?? '');
    $status      = normalizeStatus($row['status'] ?? '');
    $qty         = max(1, intval($row['quantity'] ?? 1));
    $brand       = trim($row['brand']        ?? '');
    $model       = trim($row['model']        ?? '');
    $location    = trim($row['location']     ?? '');
    $subLocation = trim($row['sub_location'] ?? '');
    $description = trim($row['description']  ?? '');
    $serial      = normalizeField($row['serial_number'] ?? '');

    if (!in_array($condition, ['NEW','USED']))
        $rowErrors[] = "Invalid condition: \"$condition\"";
    if (!in_array($status, ['WORKING','FOR CHECKING','NOT WORKING']))
        $rowErrors[] = "Invalid status: \"$status\"";

    if ($serial !== null) {
        $sk = strtolower($serial);
        if (isset($existingSerials[$sk]))      $rowErrors[] = "Serial \"$serial\" already exists in database";
        elseif (isset($batchSerials[$sk]))     $rowErrors[] = "Serial \"$serial\" duplicated in file (row {$batchSerials[$sk]})";
        else                                   $batchSerials[$sk] = $rowNum;
    }

    $isValid    = empty($rowErrors) ? 1 : 0;
    $errorNotes = $isValid ? null : implode('; ', $rowErrors);
    if ($isValid) $validRows++; else $invalidRows++;

    // 15 params: s i i i s s s i s s s s s i s
    $stageStmt->bind_param('siiisssisssssis',
        $token, $rowNum, $catId, $subCatId,
        $brand, $model, $serial, $qty,
        $condition, $status, $location, $subLocation,
        $description, $isValid, $errorNotes
    );
    if (!$stageStmt->execute()) error_log("Staging row $rowNum failed: ".$stageStmt->error);

    $preview[] = [
        'row'=>$rowNum,'category'=>$row['category']??'','sub_category'=>$row['sub_category']??'',
        'brand'=>$brand?:'—','model'=>$model?:'—','serial'=>$serial??'—',
        'qty'=>$qty,'condition'=>$condition,'status'=>$status,'location'=>$location,
        'sub_location'=>$subLocation,'is_valid'=>(bool)$isValid,'errors'=>$rowErrors,
    ];
}

$stageStmt->close();
ob_end_clean(); // discard any stray PHP warnings before JSON output

echo json_encode([
    'success'=>true,'token'=>$token,
    'valid_rows'=>$validRows,'invalid_rows'=>$invalidRows,
    'total_rows'=>count($preview),'preview'=>$preview,
]);