<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_GET['category_id'])) {
    echo json_encode(['success' => false, 'message' => 'Category ID required']);
    exit();
}

$conn = getDBConnection();
$categoryId = intval($_GET['category_id']);

$stmt = $conn->prepare("SELECT id, name FROM sub_categories WHERE category_id = ? ORDER BY name");
$stmt->bind_param("i", $categoryId);
$stmt->execute();
$result = $stmt->get_result();

$subcategories = [];
while ($row = $result->fetch_assoc()) {
    $subcategories[] = $row;
}

echo json_encode([
    'success' => true,
    'subcategories' => $subcategories
]);