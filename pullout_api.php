<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        case 'get_pullouts':        getPullouts($conn);       break;
        case 'get_pullout_details': getPulloutDetails($conn); break;
        case 'update_status':       updateStatus($conn);      break;
        case 'get_locations':       getLocations($conn);      break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}

// ── Helper: check if URL is a Google Sheets embedded image ───────────────────
function isSheetsImageUrl($url) {
    return strpos($url, 'docs.google.com/sheets-images-rt/') !== false
        || strpos($url, 'docs.google.com/drawings/') !== false;
}

// ── Helper: extract Google Drive file ID ─────────────────────────────────────
function getDriveFileId($url) {
    if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $url, $m)) return $m[1];
    if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $url, $m)) return $m[1];
    return null;
}

// ── Helper: build full image list ────────────────────────────────────────────
function buildImageList($conn, $assetId) {
    $stmt = $conn->prepare("SELECT image_path, drive_url FROM asset_images WHERE asset_id = ? ORDER BY id ASC LIMIT 10");
    if (!$stmt) return [];
    $stmt->bind_param("i", $assetId);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = [];
    while ($img = $result->fetch_assoc()) {
        if ($img['image_path'] === 'gdrive_folder' && !empty($img['drive_url'])) {
            $url = $img['drive_url'];
            if (isSheetsImageUrl($url)) {
                $images[] = ['type' => 'gdrive', 'url' => $url, 'thumb' => $url];
                continue;
            }
            $fileId = getDriveFileId($url);
            if ($fileId) {
                $images[] = [
                    'type'    => 'gdrive',
                    'url'     => $url,
                    'thumb'   => "https://drive.google.com/thumbnail?id={$fileId}&sz=w400",
                    'file_id' => $fileId,
                ];
            } else {
                $images[] = ['type' => 'gdrive_folder', 'url' => $url, 'thumb' => null];
            }
        } elseif (!empty($img['image_path']) && $img['image_path'] !== 'gdrive_folder') {
            $images[] = ['type' => 'image', 'url' => '/' . ltrim($img['image_path'], '/')];
        }
    }
    $stmt->close();
    return $images;
}

// ── Helper: get first image for thumbnail ────────────────────────────────────
function getFirstImage($conn, $assetId) {
    $stmt = $conn->prepare("SELECT image_path, drive_url FROM asset_images WHERE asset_id = ? ORDER BY id LIMIT 1");
    if (!$stmt) return ['thumb' => null, 'type' => null];
    $stmt->bind_param("i", $assetId);
    $stmt->execute();
    $img = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$img) return ['thumb' => null, 'type' => null];

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
    return [
        'thumb' => '/' . ltrim($img['image_path'], '/'),
        'type'  => 'image',
        'url'   => null,
    ];
}

// =============================================================================
// GET PULLOUTS
// =============================================================================
function getPullouts($conn) {
    $search   = trim($_GET['search']   ?? '');
    $status   = trim($_GET['status']   ?? '');
    $location = trim($_GET['location'] ?? '');

    try {
        $sql = "SELECT 
                    p.id, p.asset_id, p.quantity, p.purpose,
                    p.from_location, p.to_location,
                    p.requested_by, p.released_by, p.delivered_by, p.received_by,
                    p.location_received, p.date_needed, p.status, p.prep_step,
                    p.created_at, p.released_at, p.returned_at,
                    a.brand, a.model, a.serial_number, a.`condition`,
                    c.name  AS category,
                    sc.name AS asset_type
                FROM pull_out_transactions p
                LEFT JOIN assets         a  ON p.asset_id        = a.id
                LEFT JOIN categories     c  ON a.category_id     = c.id
                LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
                WHERE 1=1";

        $params = [];
        $types  = '';

        if (!empty($search)) {
            $sql .= " AND (a.brand LIKE ? OR a.model LIKE ? OR a.serial_number LIKE ? OR sc.name LIKE ?
                          OR p.requested_by LIKE ? OR p.released_by LIKE ? OR p.delivered_by LIKE ?
                          OR p.purpose LIKE ? OR p.from_location LIKE ? OR p.to_location LIKE ?)";
            $s      = "%$search%";
            $params = array_merge($params, [$s,$s,$s,$s,$s,$s,$s,$s,$s,$s]);
            $types .= 'ssssssssss';
        }
        if (!empty($status))   { $sql .= " AND p.status = ?"; $params[] = $status; $types .= 's'; }
      $userRole = $_SESSION['role'] ?? '';
if (strtoupper($userRole) === 'EMPLOYEE') {
    $userName = $_SESSION['name'] ?? '';
    $sql .= " AND p.requested_by = ?";
    $params[] = $userName;
    $types .= 's';
}

        $sql .= " ORDER BY p.id DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);

        $result = $stmt->get_result();
        $rows   = [];

        while ($row = $result->fetch_assoc()) {
            $row['released_by'] = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $row['released_by'] ?? ''));
            $row['purpose']     = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $row['purpose'] ?? ''));

            if (empty($row['from_location']) && !empty($row['purpose'])) {
                if (preg_match('/From:\s*(.+?)\s*→/', $row['purpose'], $m)) $row['from_location'] = trim($m[1]);
            }
            if (empty($row['to_location']) && !empty($row['purpose'])) {
                if (preg_match('/→\s*To:\s*(.+?)(\s*\[|$)/', $row['purpose'], $m)) $row['to_location'] = trim($m[1]);
            }

            $first = getFirstImage($conn, $row['asset_id']);
            $row['thumbnail']      = $first['thumb'];
            $row['thumbnail_type'] = $first['type'];
            $row['thumbnail_url']  = $first['url'] ?? null;

            $rows[] = $row;
        }

        echo json_encode(['success' => true, 'data' => $rows, 'count' => count($rows)]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'getPullouts error: ' . $e->getMessage()]);
    }
}

// =============================================================================
// GET PULLOUT DETAILS
// =============================================================================
function getPulloutDetails($conn) {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Transaction ID required']); return; }

    try {
        $sql = "SELECT p.*, p.prep_step, p.delivered_by,
                    a.brand, a.model, a.serial_number, a.`condition`,
                    a.location AS asset_location, a.sub_location AS asset_sub_location,
                    c.name AS category, sc.name AS asset_type
                FROM pull_out_transactions p
                LEFT JOIN assets         a  ON p.asset_id        = a.id
                LEFT JOIN categories     c  ON a.category_id     = c.id
                LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
                WHERE p.id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        if (!$item) { echo json_encode(['success' => false, 'error' => 'Transaction not found']); return; }

        $item['released_by'] = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $item['released_by'] ?? ''));
        $item['purpose']     = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $item['purpose'] ?? ''));

        if (empty($item['from_location']) && !empty($item['purpose'])) {
            if (preg_match('/From:\s*(.+?)\s*→/', $item['purpose'], $m)) $item['from_location'] = trim($m[1]);
        }
        if (empty($item['to_location']) && !empty($item['purpose'])) {
            if (preg_match('/→\s*To:\s*(.+?)(\s*\[|$)/', $item['purpose'], $m)) $item['to_location'] = trim($m[1]);
        }

        $item['images'] = buildImageList($conn, $item['asset_id']);

        echo json_encode(['success' => true, 'data' => $item]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'getPulloutDetails error: ' . $e->getMessage()]);
    }
}

// =============================================================================
// UPDATE STATUS
// FIX: RELEASED block now only updates the transaction status — NO asset
//      table changes. All inventory updates (balance deduction, location
//      move, new asset record) happen in admin-receiving.php on RECEIVED.
// =============================================================================
function updateStatus($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        return;
    }

    $data      = json_decode(file_get_contents('php://input'), true);
    $id        = intval($data['id']   ?? 0);
    $newStatus = trim($data['status'] ?? '');

    $allowed = ['RELEASED', 'RETURNED', 'CANCELLED', 'CONFIRMED'];
    if (!$id || !in_array($newStatus, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        return;
    }

    try {
        $txnStmt = $conn->prepare("SELECT asset_id, quantity, purpose, from_location, to_location, location_received, status FROM pull_out_transactions WHERE id = ?");
        $txnStmt->bind_param("i", $id);
        $txnStmt->execute();
        $txn = $txnStmt->get_result()->fetch_assoc();
        if (!$txn) throw new Exception('Transaction not found');

        $assetId      = $txn['asset_id'];
        $quantity     = intval($txn['quantity']);
        $purpose      = $txn['purpose'];
        $fromLocation = $txn['from_location'] ?? '';
        $toLocation   = $txn['to_location']   ?? $txn['location_received'] ?? '';

        if (empty($fromLocation) && !empty($purpose)) {
            if (preg_match('/From:\s*(.+?)\s*→/', $purpose, $m)) $fromLocation = trim($m[1]);
        }
        if (empty($toLocation) && !empty($purpose)) {
            if (preg_match('/→\s*To:\s*(.+?)(\s*\[|$)/', $purpose, $m)) $toLocation = trim($m[1]);
        }

        $userName = $_SESSION['name'] ?? 'Unknown';

        // ── CONFIRMED ─────────────────────────────────────────────────────────
        if ($newStatus === 'CONFIRMED') {
            $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = 'CONFIRMED' WHERE id = ? AND status = 'PENDING'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Already processed or not found.']);
                return;
            }
            $desc = "Transfer #$id CONFIRMED by $userName";

        // ── RELEASED — STATUS UPDATE ONLY ────────────────────────────────────
        // NO asset table changes here. Inventory update happens in
        // admin-receiving.php when the asset is physically received.
        } elseif ($newStatus === 'RELEASED') {
            $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = 'RELEASED', released_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Already processed or not found.']);
                return;
            }
            $desc = "Transfer #$id RELEASED: Asset #$assetId x{$quantity} from {$fromLocation} → {$toLocation}. Awaiting receipt — inventory not yet updated.";

        // ── RETURNED ──────────────────────────────────────────────────────────
        } elseif ($newStatus === 'RETURNED') {
            $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = 'RETURNED', returned_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            // Find dest asset ID tag written during RECEIVED
            $destAssetId = 0;
            if (preg_match('/\[dest:(\d+)\]/', $purpose, $dm)) $destAssetId = intval($dm[1]);

            if ($destAssetId && $destAssetId === $assetId) {
                // Full transfer was done — move back to source
                $updSrc = $conn->prepare("UPDATE assets SET location = ?, beg_balance_count = ?, updated_at = NOW() WHERE id = ?");
                $updSrc->bind_param("sii", $fromLocation, $quantity, $assetId);
                $updSrc->execute();
            } else {
                // Partial transfer — reduce dest, restore source
                if ($destAssetId) {
                    $destStmt = $conn->prepare("SELECT beg_balance_count FROM assets WHERE id = ?");
                    $destStmt->bind_param("i", $destAssetId);
                    $destStmt->execute();
                    $destRow    = $destStmt->get_result()->fetch_assoc();
                    $newDestBal = max(0, intval($destRow['beg_balance_count'] ?? 0) - $quantity);
                    if ($newDestBal === 0) {
                        $delImg = $conn->prepare("DELETE FROM asset_images WHERE asset_id = ?");
                        $delImg->bind_param("i", $destAssetId);
                        $delImg->execute();
                        $delDest = $conn->prepare("DELETE FROM assets WHERE id = ?");
                        $delDest->bind_param("i", $destAssetId);
                        $delDest->execute();
                    } else {
                        $updDest = $conn->prepare("UPDATE assets SET beg_balance_count = ?, updated_at = NOW() WHERE id = ?");
                        $updDest->bind_param("ii", $newDestBal, $destAssetId);
                        $updDest->execute();
                    }
                }
                // Restore source balance
                $srcStmt = $conn->prepare("SELECT beg_balance_count FROM assets WHERE id = ?");
                $srcStmt->bind_param("i", $assetId);
                $srcStmt->execute();
                $srcRow    = $srcStmt->get_result()->fetch_assoc();
                $newSrcBal = intval($srcRow['beg_balance_count'] ?? 0) + $quantity;
                $updSrc    = $conn->prepare("UPDATE assets SET beg_balance_count = ?, updated_at = NOW() WHERE id = ?");
                $updSrc->bind_param("ii", $newSrcBal, $assetId);
                $updSrc->execute();
            }
            $desc = "Transfer #$id RETURNED: {$quantity} pcs back to {$fromLocation} (source asset #{$assetId})";

        // ── CANCELLED ─────────────────────────────────────────────────────────
        } else {
            $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = 'CANCELLED' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $desc = "Transfer #$id CANCELLED: Asset #$assetId ($quantity pcs) — no balance change";
        }

        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'UPDATE_TRANSFER', ?)");
        $logStmt->bind_param("ss", $userName, $desc);
        $logStmt->execute();

        echo json_encode(['success' => true, 'message' => "Status updated to $newStatus"]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'updateStatus error: ' . $e->getMessage()]);
    }
}

// =============================================================================
// GET LOCATIONS
// =============================================================================
function getLocations($conn) {
    try {
        $locSql = "SELECT DISTINCT TRIM(SUBSTRING_INDEX(loc, ' / ', 1)) AS main_loc FROM (
                    SELECT from_location AS loc FROM pull_out_transactions WHERE from_location IS NOT NULL AND from_location != ''
                    UNION ALL SELECT to_location AS loc FROM pull_out_transactions WHERE to_location IS NOT NULL AND to_location != ''
                    UNION ALL SELECT location_received AS loc FROM pull_out_transactions WHERE location_received IS NOT NULL AND location_received != ''
                ) AS combined ORDER BY main_loc ASC";
        $locResult = $conn->query($locSql);
        $locations = [];
        $seen      = [];
        while ($row = $locResult->fetch_assoc()) {
            $loc = trim($row['main_loc']);
            if ($loc === '' || isset($seen[$loc])) continue;
            $seen[$loc] = true;
            $countStmt  = $conn->prepare("SELECT COUNT(*) AS cnt FROM pull_out_transactions WHERE status = 'PENDING' AND (from_location = ? OR from_location LIKE ?)");
            $exact = $loc;
            $like  = $loc . ' /%';
            $countStmt->bind_param("ss", $exact, $like);
            $countStmt->execute();
            $countRow    = $countStmt->get_result()->fetch_assoc();
            $locations[] = ['location' => $loc, 'pending_count' => (int) ($countRow['cnt'] ?? 0)];
        }
        echo json_encode(['success' => true, 'data' => $locations]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'getLocations error: ' . $e->getMessage()]);
    }
}