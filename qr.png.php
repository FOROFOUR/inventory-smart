<?php
require 'vendor/autoload.php'; // make sure this exists
use Endroid\QrCode\Builder\Builder;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) exit;

$url = "https://example.com/asset.php?id=$id";

header('Content-Type: image/png');

$result = Builder::create()
    ->data($url)
    ->size(150)
    ->margin(10)
    ->build();

$result->saveToFile('php://output'); // ✅ reliable
exit;