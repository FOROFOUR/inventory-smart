<?php
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'PHP Fatal Error: ' . $error['message']]);
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
$conn = getDBConnection();

if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
    exit();
}

// ── Auto-create / migrate asset_staging ──────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS `asset_staging` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `session_token` VARCHAR(64) NOT NULL,
      `row_num` INT(11) NOT NULL,
      `category_id` INT(11) DEFAULT NULL,
      `sub_category_id` INT(11) DEFAULT NULL,
      `brand` VARCHAR(255) DEFAULT NULL,
      `model` VARCHAR(255) DEFAULT NULL,
      `serial_number` VARCHAR(255) DEFAULT NULL,
      `beg_balance_count` INT(11) DEFAULT 1,
      `condition` VARCHAR(10) DEFAULT NULL,
      `status` VARCHAR(50) DEFAULT NULL,
      `location` VARCHAR(255) DEFAULT NULL,
      `sub_location` VARCHAR(255) DEFAULT NULL,
      `description` TEXT DEFAULT NULL,
      `photo_drive_url_1` VARCHAR(500) DEFAULT NULL,
      `photo_drive_url_2` VARCHAR(500) DEFAULT NULL,
      `photo_drive_url_3` VARCHAR(500) DEFAULT NULL,
      `is_valid` TINYINT(1) DEFAULT 1,
      `error_notes` TEXT DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      INDEX `idx_session_token` (`session_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$file = $_FILES['excel_file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Only CSV, XLSX, XLS allowed.']);
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// XLSX READER
// ════════════════════════════════════════════════════════════════════════════
function readXlsxRows(string $filePath): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive extension missing. Enable php_zip in php.ini and restart Apache.');
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new RuntimeException('Cannot open XLSX file. It may be corrupt or password-protected.');
    }

    $sharedStrings = [];
    $ssRaw = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssRaw !== false) {
        $dom = new DOMDocument();
        $dom->loadXML($ssRaw, LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ($dom->getElementsByTagName('si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagName('t') as $t) $text .= $t->nodeValue;
            $sharedStrings[] = $text;
        }
    }

    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wbRelsRaw = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wbRelsRaw !== false) {
        $dom = new DOMDocument();
        $dom->loadXML($wbRelsRaw, LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            if (strpos($rel->getAttribute('Type'), 'worksheet') !== false) {
                $target    = $rel->getAttribute('Target');
                $sheetPath = (strpos($target, 'xl/') === 0) ? $target : 'xl/' . ltrim($target, '/');
                break;
            }
        }
    }

    $sheetRaw = $zip->getFromName($sheetPath);
    $zip->close();
    if ($sheetRaw === false) throw new RuntimeException('Cannot read worksheet data from XLSX file.');

    $dom = new DOMDocument();
    $dom->loadXML($sheetRaw, LIBXML_NOERROR | LIBXML_NOWARNING);
    $result = [];

    foreach ($dom->getElementsByTagName('row') as $rowNode) {
        $rowArr  = [];
        $prevCol = 0;
        foreach ($rowNode->getElementsByTagName('c') as $c) {
            preg_match('/^([A-Z]+)/', $c->getAttribute('r'), $m);
            $colIdx = 0;
            foreach (str_split($m[1]) as $letter) $colIdx = $colIdx * 26 + (ord($letter) - 64);
            while ($prevCol < $colIdx - 1) { $rowArr[] = ''; $prevCol++; }
            $prevCol = $colIdx;
            $type    = $c->getAttribute('t');
            $vNodes  = $c->getElementsByTagName('v');
            $value   = $vNodes->length > 0 ? $vNodes->item(0)->nodeValue : '';
            if ($type === 's') {
                $value = $sharedStrings[(int)$value] ?? '';
            } elseif ($type === 'inlineStr') {
                $tNodes = $c->getElementsByTagName('t');
                $value  = $tNodes->length > 0 ? $tNodes->item(0)->nodeValue : '';
            }
            $rowArr[] = $value;
        }
        $result[] = $rowArr;
    }
    return $result;
}

// ── Helpers ───────────────────────────────────────────────────────────────
function normalizeField($raw) {
    $val = strtolower(trim($raw ?? ''));
    return in_array($val, ['', 'undefined', 'n/a', 'na', '-', 'none', 'null', 'no sn', 'no serial']) ? null : trim($raw);
}

function normalizeCondition($raw) {
    $clean = strtolower(preg_replace('/[^a-zA-Z]/', '', $raw ?? ''));
    if ($clean === '') return '';
    if (str_starts_with($clean, 'u')) return 'USED';
    if (str_starts_with($clean, 'n')) return 'NEW';
    return 'NEW';
}

function normalizeStatus($raw) {
    $clean = strtolower(trim(preg_replace('/[*\s]+/', ' ', $raw ?? '')));
    $map = [
        'working'       => 'WORKING',
        'ok'            => 'WORKING',
        'good'          => 'WORKING',
        'not working'   => 'NOT WORKING',
        'notworking'    => 'NOT WORKING',
        'not-working'   => 'NOT WORKING',
        'broken'        => 'NOT WORKING',
        'defective'     => 'NOT WORKING',
        'for checking'  => 'FOR CHECKING',
        'forchecking'   => 'FOR CHECKING',
        'for-checking'  => 'FOR CHECKING',
        'check'         => 'FOR CHECKING',
        'for repair'    => 'FOR CHECKING',
    ];
    return $map[$clean] ?? strtoupper(trim($raw ?? ''));
}

// ── FIXED: now accepts Google Drive file links AND Google Sheets image URLs ──
function validateDriveUrl($raw) {
    $url = trim($raw ?? '');

    // Empty / placeholder values → skip (not an error)
    if ($url === '' || in_array(strtolower($url), ['n/a', 'na', '-', 'none', 'null'])) {
        return null;
    }

    // ✅ Google Sheets embedded image URL
    if (strpos($url, 'docs.google.com/sheets-images-rt/') !== false) {
        return $url;
    }

    // ✅ Google Drawings image URL
    if (strpos($url, 'docs.google.com/drawings/') !== false) {
        return $url;
    }

    // ✅ Google Drive FILE link (not folder)
    if (strpos($url, 'drive.google.com') !== false) {
        if (strpos($url, '/folders/') !== false) {
            return false; // ❌ folder links not allowed
        }
        return $url;
    }

    // ❌ Everything else is invalid
    return false;
}

// ── Helper: get value from row by multiple possible column names ───────────
function getCol(array $row, array $keys, string $default = ''): string {
    foreach ($keys as $k) {
        if (isset($row[$k]) && trim((string)$row[$k]) !== '') {
            return (string)$row[$k];
        }
    }
    return $default;
}

// ── Read rows ─────────────────────────────────────────────────────────────
$rows   = [];
$header = null;

try {
    if ($ext === 'csv') {
        $handle = fopen($file['tmp_name'], 'r');
        $bom    = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);
        while (($line = fgetcsv($handle)) !== false) {
            if (!$header) { $header = array_map('strtolower', array_map('trim', $line)); continue; }
            if (isset($line[0]) && strpos((string)$line[0], '===') !== false) break;
            if (array_filter($line, fn($v) => trim($v) !== ''))
                $rows[] = array_combine($header, array_pad($line, count($header), ''));
        }
        fclose($handle);
    } else {
        $allRows = readXlsxRows($file['tmp_name']);
        foreach ($allRows as $line) {
            if (empty(array_filter($line, fn($v) => trim((string)$v) !== ''))) continue;
            if (isset($line[0]) && strpos((string)$line[0], '===') !== false) break;
            if (!$header) {
                $candidate = array_map('strtolower', array_map('trim', $line));
                if (!in_array('category', $candidate)) continue;
                $header = $candidate;
                continue;
            }
            $first = strtolower(trim((string)($line[0] ?? '')));
            if (strpos($first, 'required') !== false || strpos($first, 'optional') !== false) continue;
            $combined = implode('|', array_map('strtolower', array_map('trim', $line)));
            if (strpos($combined, 'sn-dell-001') !== false || strpos($combined, 'example_') !== false) continue;
            $rows[] = array_combine($header, array_pad($line, count($header), ''));
        }
    }
} catch (RuntimeException $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}

if (empty($rows)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'No data rows found. Please fill in your data starting from row 6 of the template.']);
    exit();
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

// ── Prepare staging insert ────────────────────────────────────────────────
$token     = bin2hex(random_bytes(16));
$stageStmt = $conn->prepare("
    INSERT INTO asset_staging
        (session_token, row_num, category_id, sub_category_id,
         brand, model, serial_number, beg_balance_count,
         `condition`, status, location, sub_location,
         description, photo_drive_url_1, photo_drive_url_2, photo_drive_url_3,
         is_valid, error_notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stageStmt) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Staging prepare failed: ' . $conn->error]);
    exit();
}

// ── Process rows ──────────────────────────────────────────────────────────
$batchSerials = [];
$validRows    = 0;
$invalidRows  = 0;
$preview      = [];
$dataRowNum   = 0;

foreach ($rows as $row) {
    $dataRowNum++;
    $rowNum = $dataRowNum;

    if (empty(array_filter(array_values($row), fn($v) => trim((string)$v) !== ''))) continue;

    $rowErrors = [];

    // ── Required field check ──────────────────────────────────────────────
    foreach (['category', 'sub_category', 'quantity', 'condition', 'status', 'location'] as $req) {
        if (empty(trim((string)($row[$req] ?? '')))) $rowErrors[] = "Missing: $req";
    }

    // ── Category lookup ───────────────────────────────────────────────────
    $catKey = strtolower(trim((string)($row['category'] ?? '')));
    $catId  = $catMap[$catKey] ?? null;
    if (!$catId && !empty($catKey)) $rowErrors[] = "Unknown category: \"{$row['category']}\"";

    // ── Sub-category lookup ───────────────────────────────────────────────
    $subCatKey = strtolower(trim((string)($row['sub_category'] ?? '')));
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
        if (!$subCatId && !empty($subCatKey)) $rowErrors[] = "Unknown sub-category: \"{$row['sub_category']}\"";
    }

    // ── Normalize fields ──────────────────────────────────────────────────
    $condition   = normalizeCondition($row['condition'] ?? '');
    $status      = normalizeStatus($row['status'] ?? '');
    $qty         = max(1, intval($row['quantity'] ?? 1));
    $brand       = trim((string)($row['brand']        ?? ''));
    $model       = trim((string)($row['model']        ?? ''));
    $location    = trim((string)($row['location']     ?? ''));
    $subLocation = trim((string)($row['sub_location'] ?? ''));
    $description = trim((string)($row['description']  ?? ''));
    $serial      = normalizeField($row['serial_number'] ?? '');

    // ── Validate condition/status ─────────────────────────────────────────
    if (!empty($condition) && !in_array($condition, ['NEW', 'USED']))
        $rowErrors[] = "Invalid condition: \"{$row['condition']}\" — use New or Used";
    if (!empty($status) && !in_array($status, ['WORKING', 'FOR CHECKING', 'NOT WORKING']))
        $rowErrors[] = "Invalid status: \"{$row['status']}\" — use Working, Not Working, or For Checking";

    // ── Serial duplicate check ─────────────────────────────────────────────
    if ($serial !== null) {
        $sk = strtolower($serial);
        if (isset($existingSerials[$sk]))  $rowErrors[] = "Serial \"$serial\" already exists in database";
        elseif (isset($batchSerials[$sk])) $rowErrors[] = "Serial \"$serial\" duplicated in this file";
        else                               $batchSerials[$sk] = $rowNum;
    }

    // ── Photo URL validation — supports Drive files AND Sheets image URLs ──
    $photoUrls = [];
    foreach ([1, 2, 3] as $n) {
        $aliases = ["photo_drive_url_$n"];
        if ($n === 1) $aliases[] = 'photo_drive_url'; // backward compat
        $rawUrl = getCol($row, $aliases);
        $result = validateDriveUrl($rawUrl);
        if ($result === false) {
            $rowErrors[] = "Invalid photo_drive_url_$n: must be a Google Drive file link or Google Sheets image URL.";
            $photoUrls[$n] = null;
        } else {
            $photoUrls[$n] = $result;
        }
    }

    // ── Stage row ─────────────────────────────────────────────────────────
    $isValid    = empty($rowErrors) ? 1 : 0;
    $errorNotes = $isValid ? null : implode('; ', $rowErrors);
    if ($isValid) $validRows++; else $invalidRows++;

    $catIdVal    = ($catId    !== null && $catId    !== '') ? (int)$catId    : null;
    $subCatIdVal = ($subCatId !== null && $subCatId !== '') ? (int)$subCatId : null;
    $brandVal    = ($brand       !== '') ? $brand       : null;
    $modelVal    = ($model       !== '') ? $model       : null;
    $serialVal   = ($serial !== null && $serial !== '') ? $serial : null;
    $qtyVal      = (int)$qty;

    $condVal = ($condition !== '') ? trim(preg_replace('/[^A-Z]/', '', strtoupper($condition))) : null;
    if ($condVal !== null && !in_array($condVal, ['NEW', 'USED'])) $condVal = null;

    $statusVal   = ($status      !== '') ? $status      : null;
    $locationVal = ($location    !== '') ? $location    : null;
    $subLocVal   = ($subLocation !== '') ? $subLocation : null;
    $descVal     = ($description !== '') ? $description : null;
    $url1        = $photoUrls[1] ?? null;
    $url2        = $photoUrls[2] ?? null;
    $url3        = $photoUrls[3] ?? null;

    $stageStmt->bind_param(
        'siiisssissssssssis',
        $token, $rowNum, $catIdVal, $subCatIdVal,
        $brandVal, $modelVal, $serialVal, $qtyVal,
        $condVal, $statusVal, $locationVal, $subLocVal,
        $descVal, $url1, $url2, $url3,
        $isValid, $errorNotes
    );
    if (!$stageStmt->execute()) error_log("Staging row $rowNum failed: " . $stageStmt->error);

    $preview[] = [
        'row'               => $rowNum,
        'category'          => $row['category']    ?? '',
        'sub_category'      => $row['sub_category'] ?? '',
        'brand'             => $brand  ?: '—',
        'model'             => $model  ?: '—',
        'serial'            => $serial ?? '—',
        'qty'               => $qty,
        'condition'         => $condition,
        'status'            => $status,
        'location'          => $location,
        'sub_location'      => $subLocation ?: '—',
        'photo_drive_url_1' => $photoUrls[1],
        'photo_drive_url_2' => $photoUrls[2],
        'photo_drive_url_3' => $photoUrls[3],
        'is_valid'          => (bool)$isValid,
        'errors'            => $rowErrors,
    ];
}

$stageStmt->close();
ob_end_clean();

echo json_encode([
    'success'      => true,
    'token'        => $token,
    'valid_rows'   => $validRows,
    'invalid_rows' => $invalidRows,
    'total_rows'   => count($preview),
    'preview'      => $preview,
]);