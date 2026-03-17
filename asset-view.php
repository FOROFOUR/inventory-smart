<?php
require_once __DIR__ . '/config.php';

$conn = getDBConnection();

$assetId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$assetId) {
    die('<div style="text-align:center;padding:3rem;font-family:sans-serif;"><h2>❌ Invalid Asset ID</h2></div>');
}

// Fetch asset with category and sub-category names
$stmt = $conn->prepare("
    SELECT 
        a.*,
        c.name  AS category_name,
        sc.name AS sub_category_name
    FROM assets a
    LEFT JOIN categories c      ON a.category_id     = c.id
    LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
    WHERE a.id = ?
");
$stmt->bind_param('i', $assetId);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$asset) {
    die('<div style="text-align:center;padding:3rem;font-family:sans-serif;"><h2>❌ Asset Not Found</h2></div>');
}

// ── Helper: extract Google Drive file ID ──────────────────────────────────
function getDriveFileId($url) {
    if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $url, $m)) return $m[1];
    if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $url, $m)) return $m[1];
    return null;
}

// ── Helper: check if Sheets/Drawings image URL ────────────────────────────
function isSheetsImageUrl($url) {
    return strpos($url, 'docs.google.com/sheets-images-rt/') !== false
        || strpos($url, 'docs.google.com/drawings/') !== false;
}

// ── Fetch images — supports uploaded files AND Google Drive/Sheets URLs ────
$images = []; // each: ['type' => 'uploaded'|'gdrive'|'sheets', 'src' => '...', 'href' => '...']

$imgStmt = $conn->prepare("SELECT image_path, drive_url FROM asset_images WHERE asset_id = ? ORDER BY id ASC LIMIT 3");
if ($imgStmt) {
    $imgStmt->bind_param('i', $assetId);
    $imgStmt->execute();
    $imgResult = $imgStmt->get_result();
    while ($row = $imgResult->fetch_assoc()) {
        if ($row['image_path'] === 'gdrive_folder' && !empty($row['drive_url'])) {
            $url = $row['drive_url'];

            if (isSheetsImageUrl($url)) {
                // Google Sheets embedded image — use URL directly as src
                $images[] = ['type' => 'sheets', 'src' => 'image-proxy.php?url=' . urlencode($url), 'href' => $url];

            } else {
                $fileId = getDriveFileId($url);
                if ($fileId) {
                    // Google Drive file — use thumbnail API for display, original for click
                    $images[] = [
                        'type' => 'gdrive',
                        'src'  => "image-proxy.php?url=" . urlencode("https://drive.google.com/thumbnail?id={$fileId}&sz=w800"),
                        'href' => $url,
                    ];
                } else {
                    // Folder or unrecognized — show link tile
                    $images[] = ['type' => 'gdrive_folder', 'src' => null, 'href' => $url];
                }
            }
        } elseif (!empty($row['image_path']) && $row['image_path'] !== 'gdrive_folder') {
            // Regular uploaded image
            $images[] = ['type' => 'uploaded', 'src' => '/' . ltrim($row['image_path'], '/'), 'href' => null];
        }
    }
    $imgStmt->close();
}

// Status color
$statusColors = [
    'WORKING'      => ['bg' => '#dcfce7', 'text' => '#15803d', 'dot' => '#22c55e'],
    'FOR CHECKING' => ['bg' => '#fef9c3', 'text' => '#a16207', 'dot' => '#eab308'],
    'NOT WORKING'  => ['bg' => '#fee2e2', 'text' => '#b91c1c', 'dot' => '#ef4444'],
];
$sc = $statusColors[$asset['status']] ?? ['bg' => '#f3f4f6', 'text' => '#374151', 'dot' => '#9ca3af'];

$conditionLabel = $asset['condition'] === 'NEW' ? '🆕 New' : '🔄 Used';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset #<?php echo $assetId; ?> — Inventory Smart</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:  #695CFE;
            --surface:  #ffffff;
            --bg:       #f4f3ff;
            --border:   #e5e3ff;
            --text:     #1a1523;
            --muted:    #6b7280;
            --radius:   16px;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 1.5rem 1rem 3rem;
        }

        /* ── Header ── */
        .header { text-align: center; margin-bottom: 1.5rem; animation: fadeDown 0.5s ease both; }
        .header .logo {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: var(--primary); color: white;
            padding: 0.4rem 1rem; border-radius: 999px;
            font-size: 0.8rem; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 0.75rem;
        }
        .header h1 { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
        .header .sub { color: var(--muted); font-size: 0.85rem; margin-top: 0.25rem; font-family: 'DM Mono', monospace; }

        /* ── Card ── */
        .card {
            background: var(--surface); border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: 0 2px 16px rgba(105,92,254,0.06);
            overflow: hidden; max-width: 520px; margin: 0 auto 1rem;
            animation: fadeUp 0.5s ease both;
        }
        .card + .card { animation-delay: 0.08s; }
        .card-header {
            padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 0.5rem;
            font-weight: 600; font-size: 0.9rem; color: var(--primary); background: #faf9ff;
        }
        .card-header i { font-size: 1.1rem; }

        /* ── Image Carousel ── */
        .image-section { position: relative; background: #f8f7ff; overflow: hidden; }
        .image-slides  { display: flex; transition: transform 0.35s cubic-bezier(0.4,0,0.2,1); }
        .image-slide   { min-width: 100%; aspect-ratio: 4/3; display: flex; align-items: center; justify-content: center; position: relative; }

        /* Uploaded image */
        .image-slide img {
            width: 100%; height: 100%; object-fit: contain; padding: 1rem;
        }

        /* Google Drive / Sheets image — fill the slide */
        .image-slide .drive-img {
            width: 100%; height: 100%; object-fit: cover;
            display: block; cursor: pointer;
        }

        /* Fallback tile for folder links */
        .gdrive-tile {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; height: 100%; gap: 0.75rem;
            color: #1a73e8; text-decoration: none;
            background: linear-gradient(135deg, #e8f0fe, #f1f8e9);
        }
        .gdrive-tile i   { font-size: 3rem; }
        .gdrive-tile span { font-size: 0.85rem; font-weight: 600; }

        /* Open in Drive badge — shown on drive/sheets images */
        .drive-badge {
            position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%);
            display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.92); border-radius: 999px;
            padding: 4px 12px; font-size: 0.75rem; font-weight: 600;
            color: #1a73e8; box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            text-decoration: none; white-space: nowrap;
            backdrop-filter: blur(4px);
            transition: background 0.2s;
        }
        .drive-badge:hover { background: white; }
        .drive-badge i { font-size: 1rem; }

        .no-image {
            aspect-ratio: 4/3; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            color: #c4b5fd; gap: 0.5rem; font-size: 0.85rem;
        }
        .no-image i { font-size: 3rem; }

        .image-dots {
            position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%);
            display: flex; gap: 6px;
        }
        /* Push dots up if there's a drive badge */
        .has-drive-badge .image-dots { bottom: 44px; }

        .dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: rgba(105,92,254,0.3); cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }
        .dot.active { background: var(--primary); transform: scale(1.3); }

        .img-nav {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,0.85); border: none;
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            font-size: 1rem; color: var(--primary); transition: background 0.2s;
        }
        .img-nav:hover { background: white; }
        .img-nav.prev { left: 10px; }
        .img-nav.next { right: 10px; }

        /* ── Info Grid ── */
        .info-grid { padding: 1.25rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .info-item { display: flex; flex-direction: column; gap: 0.2rem; }
        .info-item.full { grid-column: 1 / -1; }
        .info-label { font-size: 0.72rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; color: var(--muted); }
        .info-value { font-size: 0.95rem; font-weight: 500; color: var(--text); }
        .info-value.mono {
            font-family: 'DM Mono', monospace; font-size: 0.85rem;
            background: #f4f3ff; padding: 0.3rem 0.6rem; border-radius: 6px; display: inline-block;
        }

        /* ── Status Badge ── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.3rem 0.75rem; border-radius: 999px;
            font-size: 0.8rem; font-weight: 600;
            background: <?php echo $sc['bg']; ?>; color: <?php echo $sc['text']; ?>;
        }
        .status-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: <?php echo $sc['dot']; ?>; animation: pulse 1.8s infinite;
        }

        /* ── Description ── */
        .description-text { padding: 0 1.25rem 1.25rem; font-size: 0.9rem; color: #4b5563; line-height: 1.6; }

        /* ── Footer ── */
        .footer { text-align: center; color: var(--muted); font-size: 0.75rem; margin-top: 1.5rem; animation: fadeUp 0.5s 0.2s ease both; }

        @keyframes fadeDown { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeUp   { from { opacity:0; transform:translateY(16px);  } to { opacity:1; transform:translateY(0); } }
        @keyframes pulse    { 0%,100% { opacity:1; } 50% { opacity:0.4; } }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="logo"><i class='bx bx-qr-scan'></i> Inventory Smart</div>
    <h1><?php echo htmlspecialchars($asset['category_name'] ?? 'Asset'); ?></h1>
    <div class="sub">Asset ID #<?php echo $assetId; ?> &nbsp;•&nbsp; <?php echo htmlspecialchars($asset['qr_code'] ?? ''); ?></div>
</div>

<!-- Images -->
<div class="card" style="animation-delay:0s;">
    <?php if (!empty($images)): ?>
    <?php
        // Check if any slide has a drive badge (for dot positioning)
        $anyDriveBadge = false;
        foreach ($images as $img) {
            if (in_array($img['type'], ['gdrive','sheets'])) { $anyDriveBadge = true; break; }
        }
    ?>
    <div class="image-section <?php echo $anyDriveBadge && count($images) > 1 ? 'has-drive-badge' : ''; ?>" id="imageSection">
        <div class="image-slides" id="imageSlides">
            <?php foreach ($images as $img): ?>
            <div class="image-slide">
                <?php if ($img['type'] === 'uploaded'): ?>
                    <img src="<?php echo htmlspecialchars($img['src']); ?>" alt="Asset Image"
                         onerror="this.parentElement.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;color:#c4b5fd;font-size:0.85rem;\'>⚠️ Image not found</div>'">

                <?php elseif ($img['type'] === 'gdrive' || $img['type'] === 'sheets'): ?>
                    <img class="drive-img"
                         src="<?php echo htmlspecialchars($img['src']); ?>"
                         alt="Asset Photo"
                         onclick="window.open('<?php echo htmlspecialchars($img['href']); ?>','_blank')"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='none';this.parentElement.innerHTML+='<div style=\'display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:0.5rem;color:#c4b5fd;font-size:0.85rem;\'><i class=\'bx bx-image\' style=\'font-size:3rem;\'></i><span>Image unavailable</span></div>'">
                    <a class="drive-badge" href="<?php echo htmlspecialchars($img['href']); ?>" target="_blank" rel="noopener">
                        <i class='bx bxl-google'></i> Open in Drive
                    </a>

                <?php elseif ($img['type'] === 'gdrive_folder'): ?>
                    <a class="gdrive-tile" href="<?php echo htmlspecialchars($img['href']); ?>" target="_blank" rel="noopener">
                        <i class='bx bxl-google'></i>
                        <span>View on Google Drive</span>
                    </a>

                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($images) > 1): ?>
        <button class="img-nav prev" onclick="slideImg(-1)"><i class='bx bx-chevron-left'></i></button>
        <button class="img-nav next" onclick="slideImg(1)"><i class='bx bx-chevron-right'></i></button>
        <div class="image-dots" id="imageDots">
            <?php foreach ($images as $k => $_): ?>
            <div class="dot <?php echo $k===0?'active':''; ?>" onclick="goSlide(<?php echo $k; ?>)"></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="no-image">
        <i class='bx bx-image'></i>
        <span>No image available</span>
    </div>
    <?php endif; ?>
</div>

<!-- Asset Details -->
<div class="card" style="animation-delay:0.05s;">
    <div class="card-header"><i class='bx bx-info-circle'></i> Asset Details</div>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Category</span>
            <span class="info-value"><?php echo htmlspecialchars($asset['category_name'] ?? '—'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Sub-Category</span>
            <span class="info-value"><?php echo htmlspecialchars($asset['sub_category_name'] ?? '—'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Brand</span>
            <span class="info-value"><?php echo htmlspecialchars($asset['brand'] ?? '—'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Model</span>
            <span class="info-value"><?php echo htmlspecialchars($asset['model'] ?? '—'); ?></span>
        </div>
        <div class="info-item full">
            <span class="info-label">Serial Number</span>
            <span class="info-value mono"><?php echo htmlspecialchars($asset['serial_number'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Condition</span>
            <span class="info-value"><?php echo $conditionLabel; ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Status</span>
            <span class="status-badge">
                <span class="status-dot"></span>
                <?php echo htmlspecialchars($asset['status']); ?>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">Quantity</span>
            <span class="info-value"><?php echo intval($asset['beg_balance_count']); ?> unit(s)</span>
        </div>
        <div class="info-item">
            <span class="info-label">Tracking</span>
            <span class="info-value"><?php echo htmlspecialchars($asset['tracking_type'] ?? '—'); ?></span>
        </div>
    </div>
</div>

<!-- Location -->
<div class="card" style="animation-delay:0.10s;">
    <div class="card-header"><i class='bx bx-map-pin'></i> Location</div>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Location</span>
            <span class="info-value"><?php echo htmlspecialchars($asset['location'] ?? '—'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Sub-Location</span>
            <span class="info-value"><?php echo htmlspecialchars($asset['sub_location'] ?? '—'); ?></span>
        </div>
    </div>
</div>

<!-- Description -->
<?php if (!empty($asset['description'])): ?>
<div class="card" style="animation-delay:0.15s;">
    <div class="card-header"><i class='bx bx-file-blank'></i> Description</div>
    <p class="description-text"><?php echo nl2br(htmlspecialchars($asset['description'])); ?></p>
</div>
<?php endif; ?>

<!-- Timestamps -->
<div class="card" style="animation-delay:0.18s;">
    <div class="card-header"><i class='bx bx-time'></i> Record Info</div>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Date Added</span>
            <span class="info-value"><?php echo date('M d, Y', strtotime($asset['created_at'])); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Last Updated</span>
            <span class="info-value"><?php echo date('M d, Y', strtotime($asset['updated_at'])); ?></span>
        </div>
    </div>
</div>

<div class="footer">
    <p>📍 Scanned via QR Code &nbsp;•&nbsp; Inventory Smart</p>
    <p style="margin-top:0.25rem;">© <?php echo date('Y'); ?> — Internal Use Only</p>
</div>

<script>
let currentSlide = 0;
const totalSlides = <?php echo count($images); ?>;

function goSlide(n) {
    currentSlide = n;
    document.getElementById('imageSlides').style.transform = `translateX(-${n * 100}%)`;
    document.querySelectorAll('.dot').forEach((d, i) => d.classList.toggle('active', i === n));
}

function slideImg(dir) {
    goSlide((currentSlide + dir + totalSlides) % totalSlides);
}
</script>

</body>
</html>