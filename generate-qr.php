<?php
require_once "phpqrcode/qrlib.php";

if (!isset($_GET['data']) || empty($_GET['data'])) {
    die("No QR data provided.");
}

$data = $_GET['data'];

ob_start();
QRcode::png($data, null, QR_ECLEVEL_H, 12, 2);
$imageString = base64_encode(ob_get_contents());
ob_end_clean();
?>

<!DOCTYPE html>
<html>
<head>
    <title>QR Preview</title>
    <style>
        body {
            display:flex;
            justify-content:center;
            align-items:center;
            height:100vh;
            background:white;
        }
    </style>
</head>
<body>
    <img src="data:image/png;base64,<?= $imageString ?>">
</body>
</html>
