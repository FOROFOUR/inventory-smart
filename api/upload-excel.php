<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_FILES['excel_file'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit();
}

$conn = getDBConnection();

try {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    use PhpOffice\PhpSpreadsheet\IOFactory;

    $file = $_FILES['excel_file'];
    $allowedTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-excel' // .xls
    ];

    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload .xlsx or .xls file']);
        exit();
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
        exit();
    }

    // Load spreadsheet
    $spreadsheet = IOFactory::load($file['tmp_name']);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    // Skip header row
    $header = array_shift($rows);

    $inserted = 0;
    $errors = [];

    // Get categories and subcategories for validation
    $categories = [];
    $result = $conn->query("SELECT id, name FROM categories");
    while ($row = $result->fetch_assoc()) {
        $categories[strtolower($row['name'])] = $row['id'];
    }

    $subcategories = [];
    $result = $conn->query("SELECT id, name, category_id FROM sub_categories");
    while ($row = $result->fetch_assoc()) {
        $key = $row['category_id'] . '_' . strtolower($row['name']);
        $subcategories[$key] = $row['id'];
    }

    foreach ($rows as $index => $row) {
        $rowNum = $index + 2; // +2 because we removed header and Excel is 1-indexed

        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }

        try {
            // Parse row data (adjust indices based on template)
            $categoryName = trim($row[0] ?? '');
            $subCategoryName = trim($row[1] ?? '');
            $brand = !empty($row[2]) ? trim($row[2]) : null;
            $model = !empty($row[3]) ? trim($row[3]) : null;
            $serialNumber = !empty($row[4]) ? trim($row[4]) : null;
            $description = !empty($row[5]) ? trim($row[5]) : null;
            $trackingType = strtoupper(trim($row[6] ?? 'BULK'));
            $condition = strtoupper(trim($row[7] ?? 'NEW'));
            $status = strtoupper(trim($row[8] ?? 'WORKING'));
            $location = trim($row[9] ?? '');
            $subLocation = !empty($row[10]) ? trim($row[10]) : null;
            $quantity = !empty($row[11]) ? intval($row[11]) : 1;

            // Validate required fields
            if (empty($categoryName)) {
                throw new Exception("Category is required");
            }
            if (empty($subCategoryName)) {
                throw new Exception("Sub-category is required");
            }
            if (empty($location)) {
                throw new Exception("Location is required");
            }

            // Get category ID
            $categoryKey = strtolower($categoryName);
            if (!isset($categories[$categoryKey])) {
                throw new Exception("Invalid category: $categoryName");
            }
            $categoryId = $categories[$categoryKey];

            // Get subcategory ID
            $subCategoryKey = $categoryId . '_' . strtolower($subCategoryName);
            if (!isset($subcategories[$subCategoryKey])) {
                throw new Exception("Invalid sub-category: $subCategoryName for category: $categoryName");
            }
            $subCategoryId = $subcategories[$subCategoryKey];

            // Validate enum values
            if (!in_array($trackingType, ['PER_ITEM', 'BULK'])) {
                throw new Exception("Invalid tracking type: $trackingType. Must be PER_ITEM or BULK");
            }
            if (!in_array($condition, ['NEW', 'USED'])) {
                throw new Exception("Invalid condition: $condition. Must be NEW or USED");
            }
            if (!in_array($status, ['FOR CHECKING', 'WORKING', 'NOT WORKING'])) {
                throw new Exception("Invalid status: $status");
            }

            // Generate QR code
            $qrCode = null;
            if ($trackingType === 'PER_ITEM' && !empty($serialNumber)) {
                $qrCode = 'ASSET-' . time() . '-' . strtoupper(substr($serialNumber, -6));
            } elseif ($trackingType === 'BULK') {
                $qrCode = 'ASSET-BULK-' . time() . '-' . $rowNum;
            }

            // Insert asset
            $stmt = $conn->prepare("
                INSERT INTO assets (
                    category_id, sub_category_id, brand, model, serial_number,
                    description, tracking_type, `condition`, status, location,
                    sub_location, beg_balance_count, qr_code
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iisssssssssis",
                $categoryId, $subCategoryId, $brand, $model, $serialNumber,
                $description, $trackingType, $condition, $status, $location,
                $subLocation, $quantity, $qrCode
            );

            if ($stmt->execute()) {
                $inserted++;
            } else {
                throw new Exception("Database error: " . $stmt->error);
            }

        } catch (Exception $e) {
            $errors[] = [
                'row' => $rowNum,
                'message' => $e->getMessage()
            ];
        }
    }

    // Log activity
    $userName = $_SESSION['user_name'] ?? 'Unknown';
    $action = 'UPLOAD_ASSETS_EXCEL';
    $logDescription = "Uploaded assets via Excel: $inserted assets added";

    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, ?, ?)");
    $logStmt->bind_param("sss", $userName, $action, $logDescription);
    $logStmt->execute();

    echo json_encode([
        'success' => $inserted > 0,
        'message' => "$inserted assets added successfully" . (count($errors) > 0 ? " with " . count($errors) . " errors" : ""),
        'inserted' => $inserted,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error processing file: ' . $e->getMessage()
    ]);
}