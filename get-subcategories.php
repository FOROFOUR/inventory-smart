<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$categoryId = intval($_GET['category_id'] ?? 0);

if (!$categoryId) {
    echo json_encode([]);
    exit();
}

$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT id, name 
    FROM sub_categories 
    WHERE category_id = ? 
    ORDER BY name ASC
");
$stmt->bind_param("i", $categoryId);
$stmt->execute();
$result = $stmt->get_result();

$subcategories = [];
while ($row = $result->fetch_assoc()) {
    $subcategories[] = [
        'id'   => $row['id'],
        'name' => $row['name'],
    ];
}

echo json_encode($subcategories);