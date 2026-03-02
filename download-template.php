<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: landing.php"); exit(); }

// Force download as CSV (opens fine in Excel)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="asset_upload_template.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$conn = getDBConnection();

// Fetch all categories and sub-categories for the notes sheet
$catRows = [];
$result  = $conn->query("SELECT c.name AS category, sc.name AS sub_category FROM sub_categories sc JOIN categories c ON sc.category_id = c.id ORDER BY c.name, sc.name");
while ($row = $result->fetch_assoc()) $catRows[] = $row;

$out = fopen('php://output', 'w');

// ── HEADER ROW (must match manual form fields)
fputcsv($out, [
    'category',        // required - must match exactly (case-insensitive)
    'sub_category',    // required
    'brand',           // optional
    'model',           // optional
    'serial_number',   // optional
    'quantity',        // required - beginning balance count
    'condition',       // required - NEW or USED
    'status',          // required - WORKING / FOR CHECKING / NOT WORKING
    'location',        // required
    'sub_location',    // optional
    'description',     // optional
]);

// ── SAMPLE ROW
fputcsv($out, [
    'Network Equipment',   // category
    'Others',              // sub_category
    'Logitech',            // brand
    'MX Master 3',         // model
    'SN-00123',            // serial_number
    '5',                   // quantity
    'NEW',                 // condition
    'WORKING',             // status
    'Main Warehouse',      // location
    'Rack A Shelf 1',      // sub_location
    'Sample description',  // description
]);

// ── BLANK ROWS for user to fill
for ($i = 0; $i < 10; $i++) {
    fputcsv($out, ['','','','','','','','','','','']);
}

// ── REFERENCE SECTION (separator then valid values)
fputcsv($out, []);
fputcsv($out, ['=== VALID VALUES REFERENCE (do not include this section in your data) ===']);
fputcsv($out, ['condition values:', 'NEW', 'USED']);
fputcsv($out, ['status values:',    'WORKING', 'FOR CHECKING', 'NOT WORKING']);
fputcsv($out, []);
fputcsv($out, ['category', 'sub_category']);
foreach ($catRows as $row) {
    fputcsv($out, [$row['category'], $row['sub_category']]);
}

fclose($out);
exit();