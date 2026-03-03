<?php
require_once 'phpqrcode/qrlib.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) exit;

$url = "http://10.20.80.43/inventory-smart/asset-view.php?id=" . $id;

header('Content-Type: image/png');

QRcode::png($url, false, QR_ECLEVEL_L, 4, 2);
exit;