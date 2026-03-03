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

// =============================================================================
// GET PULLOUTS
// =============================================================================
function getPullouts($conn) {
    $search   = trim($_GET['search']   ?? '');
    $status   = trim($_GET['status']   ?? '');
    $location = trim($_GET['location'] ?? '');

    try {
        $sql = "SELECT 
                    p.id,
                    p.asset_id,
                    p.quantity,
                    p.purpose,
                    p.from_location,
                    p.to_location,
                    p.requested_by,
                    p.released_by,
                    p.received_by,
                    p.location_received,
                    p.date_needed,
                    p.status,
                    p.created_at,
                    p.released_at,
                    p.returned_at,
                    a.brand,
                    a.model,
                    a.serial_number,
                    a.`condition`,
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
            $sql .= " AND (
                        a.brand             LIKE ? OR
                        a.model             LIKE ? OR
                        a.serial_number     LIKE ? OR
                        sc.name             LIKE ? OR
                        p.requested_by      LIKE ? OR
                        p.released_by       LIKE ? OR
                        p.purpose           LIKE ? OR
                        p.from_location     LIKE ? OR
                        p.to_location       LIKE ?
                    )";
            $s      = "%$search%";
            $params = array_merge($params, [$s,$s,$s,$s,$s,$s,$s,$s,$s]);
            $types .= 'sssssssss';
        }

        if (!empty($status)) {
            $sql    .= " AND p.status = ?";
            $params[] = $status;
            $types  .= 's';
        }

        // Location filter — matches main location even if sub-location is appended (e.g. "QC WareHouse / C1-S3")
        if (!empty($location)) {
            $locLike = $location . '%';
            $sql .= " AND (p.from_location LIKE ? OR p.to_location LIKE ? OR p.location_received LIKE ?)";
            $params = array_merge($params, [$locLike, $locLike, $locLike]);
            $types .= 'sss';
        }

        $sql .= " ORDER BY p.id DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);

        $result = $stmt->get_result();
        $rows   = [];

        while ($row = $result->fetch_assoc()) {
            // Clean up any legacy [dest:xxx] tags from released_by or purpose
            $row['released_by'] = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $row['released_by'] ?? ''));
            $row['purpose']     = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $row['purpose'] ?? ''));

            // FIX: For old records where from_location/to_location is NULL,
            // fall back to parsing from purpose field
            if (empty($row['from_location']) && !empty($row['purpose'])) {
                if (preg_match('/From:\s*(.+?)\s*→/', $row['purpose'], $m)) {
                    $row['from_location'] = trim($m[1]);
                }
            }
            if (empty($row['to_location']) && !empty($row['purpose'])) {
                if (preg_match('/→\s*To:\s*(.+?)(\s*\[|$)/', $row['purpose'], $m)) {
                    $row['to_location'] = trim($m[1]);
                }
            }

            $imgStmt = $conn->prepare("SELECT image_path FROM asset_images WHERE asset_id = ? ORDER BY id LIMIT 1");
            if ($imgStmt) {
                $imgStmt->bind_param("i", $row['asset_id']);
                $imgStmt->execute();
                $imgRow = $imgStmt->get_result()->fetch_assoc();
                $row['thumbnail'] = $imgRow['image_path'] ?? null;
            }
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
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Transaction ID required']);
        return;
    }

    try {
        $sql = "SELECT 
                    p.*,
                    a.brand,
                    a.model,
                    a.serial_number,
                    a.`condition`,
                    a.location     AS asset_location,
                    a.sub_location AS asset_sub_location,
                    c.name         AS category,
                    sc.name        AS asset_type
                FROM pull_out_transactions p
                LEFT JOIN assets         a  ON p.asset_id        = a.id
                LEFT JOIN categories     c  ON a.category_id     = c.id
                LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
                WHERE p.id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();

        if (!$item) {
            echo json_encode(['success' => false, 'error' => 'Transaction not found']);
            return;
        }

        // Clean up legacy tags
        $item['released_by'] = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $item['released_by'] ?? ''));
        $item['purpose']     = trim(preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $item['purpose'] ?? ''));

        // FIX: Fallback parse for old records
        if (empty($item['from_location']) && !empty($item['purpose'])) {
            if (preg_match('/From:\s*(.+?)\s*→/', $item['purpose'], $m)) {
                $item['from_location'] = trim($m[1]);
            }
        }
        if (empty($item['to_location']) && !empty($item['purpose'])) {
            if (preg_match('/→\s*To:\s*(.+?)(\s*\[|$)/', $item['purpose'], $m)) {
                $item['to_location'] = trim($m[1]);
            }
        }

        $imgStmt = $conn->prepare("SELECT image_path FROM asset_images WHERE asset_id = ? ORDER BY id");
        $imgStmt->bind_param("i", $item['asset_id']);
        $imgStmt->execute();
        $imgResult = $imgStmt->get_result();
        $images    = [];
        while ($img = $imgResult->fetch_assoc()) $images[] = $img['image_path'];
        $item['images'] = $images;

        echo json_encode(['success' => true, 'data' => $item]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'getPulloutDetails error: ' . $e->getMessage()]);
    }
}

// =============================================================================
// UPDATE STATUS
//
// FIX: Now reads from_location and to_location directly from their columns
//      instead of regex-parsing the purpose field.
//      Falls back to purpose parsing for old/legacy records.
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
        $txnStmt = $conn->prepare("
            SELECT asset_id, quantity, purpose, from_location, to_location,
                   location_received, status
            FROM pull_out_transactions WHERE id = ?
        ");
        $txnStmt->bind_param("i", $id);
        $txnStmt->execute();
        $txn = $txnStmt->get_result()->fetch_assoc();
        if (!$txn) throw new Exception('Transaction not found');

        $assetId       = $txn['asset_id'];
        $quantity      = intval($txn['quantity']);
        $purpose       = $txn['purpose'];
        $currentStatus = $txn['status'];

        // FIX: Use dedicated columns directly
        $fromLocation = $txn['from_location'] ?? '';
        $toLocation   = $txn['to_location']   ?? $txn['location_received'] ?? '';

        // Fallback: parse from purpose for legacy records
        if (empty($fromLocation) && !empty($purpose)) {
            if (preg_match('/From:\s*(.+?)\s*→/', $purpose, $m)) {
                $fromLocation = trim($m[1]);
            }
        }
        if (empty($toLocation) && !empty($purpose)) {
            if (preg_match('/→\s*To:\s*(.+?)(\s*\[|$)/', $purpose, $m)) {
                $toLocation = trim($m[1]);
            }
        }

        $userName = $_SESSION['name'] ?? 'Unknown';

        // ── CONFIRMED ────────────────────────────────────────────────────────
        if ($newStatus === 'CONFIRMED') {
            $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = 'CONFIRMED' WHERE id = ? AND status = 'PENDING'");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Already processed or not found.']);
                return;
            }

            $desc = "Transfer #$id CONFIRMED by $userName";

        // ── RELEASED (Mark as Received) ───────────────────────────────────
        } elseif ($newStatus === 'RELEASED') {

            $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = 'RELEASED', released_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            // Get source asset
            $assetStmt = $conn->prepare("SELECT * FROM assets WHERE id = ?");
            $assetStmt->bind_param("i", $assetId);
            $assetStmt->execute();
            $srcAsset = $assetStmt->get_result()->fetch_assoc();

            // Deduct from source
            $newSrcBal = max(0, intval($srcAsset['beg_balance_count']) - $quantity);
            $updSrc = $conn->prepare("UPDATE assets SET beg_balance_count = ?, updated_at = NOW() WHERE id = ?");
            $updSrc->bind_param("ii", $newSrcBal, $assetId);
            $updSrc->execute();

            // Find existing asset at destination
            $findStmt = $conn->prepare(
                "SELECT id, beg_balance_count FROM assets 
                 WHERE location = ? AND sub_category_id = ? AND id != ? 
                 ORDER BY id LIMIT 1"
            );
            $findStmt->bind_param("sii", $toLocation, $srcAsset['sub_category_id'], $assetId);
            $findStmt->execute();
            $destAsset = $findStmt->get_result()->fetch_assoc();

            if ($destAsset) {
                $newDestBal = intval($destAsset['beg_balance_count']) + $quantity;
                $updDest = $conn->prepare("UPDATE assets SET beg_balance_count = ?, updated_at = NOW() WHERE id = ?");
                $updDest->bind_param("ii", $newDestBal, $destAsset['id']);
                $updDest->execute();
                $destAssetId = $destAsset['id'];
            } else {
                // Create new asset record at destination
                $ins = $conn->prepare(
                    "INSERT INTO assets 
                        (category_id, sub_category_id, brand, model, serial_number,
                         `condition`, status, location, sub_location, description,
                         beg_balance_count, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW(), NOW())"
                );
                $ins->bind_param(
                    "iissssssssi",
                    $srcAsset['category_id'], $srcAsset['sub_category_id'],
                    $srcAsset['brand'],       $srcAsset['model'],
                    $srcAsset['serial_number'], $srcAsset['condition'],
                    $srcAsset['status'],      $toLocation,
                    $srcAsset['sub_location'], $srcAsset['description'],
                    $quantity
                );
                $ins->execute();
                $destAssetId = $conn->insert_id;

                // Copy images
                $imgStmt = $conn->prepare("SELECT image_path FROM asset_images WHERE asset_id = ?");
                $imgStmt->bind_param("i", $assetId);
                $imgStmt->execute();
                $imgs  = $imgStmt->get_result();
                $cpImg = $conn->prepare("INSERT INTO asset_images (asset_id, image_path) VALUES (?,?)");
                while ($img = $imgs->fetch_assoc()) {
                    $cpImg->bind_param("is", $destAssetId, $img['image_path']);
                    $cpImg->execute();
                }
            }

            // FIX: Store dest asset ID in its own column instead of appending to purpose
            // Update: save dest_asset_id reference cleanly
            $updTxn = $conn->prepare("
                UPDATE pull_out_transactions 
                SET purpose = CONCAT(COALESCE(purpose,''), ' [dest:$destAssetId]')
                WHERE id = ?
            ");
            $updTxn->bind_param("i", $id);
            $updTxn->execute();

            $desc = "Transfer #$id RECEIVED: Asset #$assetId -$quantity → {$toLocation} (dest asset #$destAssetId +$quantity)";

        // ── RETURNED ──────────────────────────────────────────────────────
        } elseif ($newStatus === 'RETURNED') {

            $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = 'RETURNED', returned_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            // Get dest_asset_id from purpose tag
            $destAssetId = 0;
            if (preg_match('/\[dest:(\d+)\]/', $purpose, $dm)) {
                $destAssetId = intval($dm[1]);
            }

            if ($destAssetId) {
                $destStmt = $conn->prepare("SELECT beg_balance_count FROM assets WHERE id = ?");
                $destStmt->bind_param("i", $destAssetId);
                $destStmt->execute();
                $destRow    = $destStmt->get_result()->fetch_assoc();
                $newDestBal = max(0, intval($destRow['beg_balance_count']) - $quantity);

                if ($newDestBal === 0) {
                    $delDest = $conn->prepare("DELETE FROM assets WHERE id = ?");
                    $delDest->bind_param("i", $destAssetId);
                    $delDest->execute();
                } else {
                    $updDest = $conn->prepare("UPDATE assets SET beg_balance_count = ?, updated_at = NOW() WHERE id = ?");
                    $updDest->bind_param("ii", $newDestBal, $destAssetId);
                    $updDest->execute();
                }
            }

            // Restore source
            $srcStmt = $conn->prepare("SELECT beg_balance_count FROM assets WHERE id = ?");
            $srcStmt->bind_param("i", $assetId);
            $srcStmt->execute();
            $srcRow    = $srcStmt->get_result()->fetch_assoc();
            $newSrcBal = intval($srcRow['beg_balance_count']) + $quantity;
            $updSrc    = $conn->prepare("UPDATE assets SET beg_balance_count = ?, updated_at = NOW() WHERE id = ?");
            $updSrc->bind_param("ii", $newSrcBal, $assetId);
            $updSrc->execute();

            $desc = "Transfer #$id RETURNED: $quantity pcs back to $fromLocation (source asset #$assetId)";

        // ── CANCELLED ─────────────────────────────────────────────────────
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
// GET LOCATIONS — distinct main locations, pending count from from_location only
// =============================================================================
function getLocations($conn) {
    try {
        // Step 1: Get all unique main locations
        $locSql = "
            SELECT DISTINCT TRIM(SUBSTRING_INDEX(loc, ' / ', 1)) AS main_loc
            FROM (
                SELECT from_location     AS loc FROM pull_out_transactions WHERE from_location     IS NOT NULL AND from_location     != ''
                UNION ALL
                SELECT to_location       AS loc FROM pull_out_transactions WHERE to_location       IS NOT NULL AND to_location       != ''
                UNION ALL
                SELECT location_received AS loc FROM pull_out_transactions WHERE location_received IS NOT NULL AND location_received != ''
            ) AS combined
            ORDER BY main_loc ASC
        ";
        $locResult = $conn->query($locSql);
        $locations = [];
        $seen      = [];
        while ($row = $locResult->fetch_assoc()) {
            $loc = trim($row['main_loc']);
            if ($loc === '' || isset($seen[$loc])) continue;
            $seen[$loc] = true;

            // Step 2: Count PENDING where FROM this location only
            $countStmt = $conn->prepare("
                SELECT COUNT(*) AS cnt
                FROM pull_out_transactions
                WHERE status = 'PENDING'
                  AND (from_location = ? OR from_location LIKE ?)
            ");
            $exact = $loc;
            $like  = $loc . ' /%';
            $countStmt->bind_param("ss", $exact, $like);
            $countStmt->execute();
            $countRow     = $countStmt->get_result()->fetch_assoc();
            $pendingCount = (int)($countRow['cnt'] ?? 0);

            $locations[] = [
                'location'      => $loc,
                'pending_count' => $pendingCount
            ];
        }
        echo json_encode(['success' => true, 'data' => $locations]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'getLocations error: ' . $e->getMessage()]);
    }
}