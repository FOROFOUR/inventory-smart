<?php
/**
 * image-proxy.php
 * Fetches a Google Drive / Sheets image server-side and streams it to the browser.
 * Usage: image-proxy.php?url=<encoded_drive_url>
 */

// Only allow Google URLs
$url = trim($_GET['url'] ?? '');
if (empty($url)) { http_response_code(400); exit('Missing url'); }

$decoded = urldecode($url);

// Whitelist: only Google domains
$allowed = ['drive.google.com', 'docs.google.com', 'lh3.googleusercontent.com', 'lh4.googleusercontent.com'];
$host    = parse_url($decoded, PHP_URL_HOST);
$isAllowed = false;
foreach ($allowed as $a) {
    if ($host === $a || str_ends_with($host, '.'.$a)) { $isAllowed = true; break; }
}
if (!$isAllowed) { http_response_code(403); exit('Forbidden'); }

// Fetch with curl
$ch = curl_init($decoded);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; InventorySmart/1.0)',
    CURLOPT_HTTPHEADER     => ['Accept: image/*,*/*'],
]);

$body    = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpCode !== 200 || empty($body)) {
    http_response_code(502);
    exit('Could not fetch image');
}

// Strip charset if present (e.g. "image/jpeg; charset=UTF-8")
$mime = explode(';', $contentType)[0];
if (!str_starts_with($mime, 'image/')) $mime = 'image/jpeg';

// Cache for 1 hour
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
echo $body;
