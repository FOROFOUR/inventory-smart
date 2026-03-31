<?php
ob_start();

require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$conn      = getDBConnection();
$user_name = $_SESSION['name'] ?? 'Warehouse Staff';

// =============================================================================
// PAGE DATA
// =============================================================================
$search     = trim($_GET['search'] ?? '');
$dateFilter = trim($_GET['date']   ?? '');

$sql = "
    SELECT p.id, p.asset_id, p.from_location, p.to_location,
           p.quantity, p.purpose, p.requested_by, p.date_needed,
           p.released_by, p.delivered_by, p.status, p.released_at,
           a.brand, a.model, a.serial_number,
           c.name  AS category_name,
           sc.name AS subcategory_name
    FROM pull_out_transactions p
    LEFT JOIN assets         a  ON p.asset_id        = a.id
    LEFT JOIN categories     c  ON a.category_id     = c.id
    LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
    WHERE p.status IN ('RELEASED', 'RECEIVED')
";
$params = [];
$types  = '';

if ($search !== '') {
    $sql   .= " AND (a.brand LIKE ? OR a.model LIKE ? OR a.serial_number LIKE ?
                     OR p.requested_by LIKE ? OR p.released_by LIKE ?
                     OR p.delivered_by LIKE ? OR p.from_location LIKE ? OR p.to_location LIKE ?)";
    $like   = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like]);
    $types .= 'ssssssss';
}
if ($dateFilter !== '') {
    $sql    .= " AND DATE(p.released_at) = ?";
    $params[] = $dateFilter;
    $types  .= 's';
}

$sql .= " ORDER BY p.released_at DESC, p.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows   = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;

// ── Summary counts ────────────────────────────────────────────────────────────
$totalReleased = (int) $conn->query("SELECT COUNT(*) FROM pull_out_transactions WHERE status IN ('RELEASED','RECEIVED')")->fetch_row()[0];

$q = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status IN ('RELEASED','RECEIVED') AND DATE(released_at) = CURDATE()");
$q->execute();
$q->bind_result($today_released);
$q->fetch();
$q->close();

$q2 = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status IN ('RELEASED','RECEIVED') AND released_by = ?");
$q2->bind_param("s", $user_name);
$q2->execute();
$q2->bind_result($my_released);
$q2->fetch();
$q2->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Release History — Warehouse</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --amber:  #f39c12;
            --green:  #27ae60;
            --red:    #e74c3c;
            --blue:   #2980b9;
            --bg:     #f4f6f9;
            --white:  #fff;
            --text:   #2c3e50;
            --muted:  #7f8c8d;
            --border: #e8ecf0;
            --shadow: 0 2px 12px rgba(0,0,0,.07);
            --radius: 12px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Space Grotesk', sans-serif; }
        body { background: var(--bg); color: var(--text); }

        .main-content {
            position: relative; left: 250px;
            width: calc(100% - 250px); min-height: 100vh;
            background: var(--bg); padding: 2rem;
            transition: all .3s ease;
        }
        .sidebar.close ~ .main-content { left: 88px; width: calc(100% - 88px); }

        .page-header {
            background: linear-gradient(135deg, #27ae60 0%, #1e8449 100%);
            border-radius: 16px; padding: 2rem 2rem 1.5rem;
            color: white; margin-bottom: 1.5rem;
            position: relative; overflow: hidden;
        }
        .page-header::before {
            content: ''; position: absolute; top: -50%; right: -5%;
            width: 300px; height: 300px; background: rgba(255,255,255,.08); border-radius: 50%;
        }
        .page-header h1 {
            font-size: 1.6rem; font-weight: 700; position: relative; z-index: 1;
            display: flex; align-items: center; gap: .6rem;
        }
        .page-header p { font-size: .9rem; opacity: .85; margin-top: .25rem; position: relative; z-index: 1; }

        /* ── STATS ── */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem; margin-bottom: 1.5rem;
        }
        .stat-card {
            background: var(--white); border-radius: var(--radius);
            padding: 1.25rem 1.5rem; box-shadow: var(--shadow);
            display: flex; align-items: center; gap: 1rem;
        }
        .stat-icon {
            width: 46px; height: 46px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }
        .stat-icon.green  { background: #d5f5e3; color: var(--green); }
        .stat-icon.amber  { background: #fef3cd; color: var(--amber); }
        .stat-icon.blue   { background: #d6eaf8; color: var(--blue);  }
        .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1; }
        .stat-label { font-size: .75rem; color: var(--muted); margin-top: 2px; text-transform: uppercase; letter-spacing: .5px; }

        /* ── FILTERS ── */
        .filters {
            background: var(--white); border-radius: var(--radius);
            padding: 1rem 1.25rem; box-shadow: var(--shadow);
            display: flex; gap: .75rem; flex-wrap: wrap; align-items: center;
            margin-bottom: 1.25rem;
        }
        .filters input {
            border: 1.5px solid var(--border); border-radius: 8px;
            padding: .6rem 1rem; font-family: 'Space Grotesk', sans-serif;
            font-size: .875rem; color: var(--text);
            outline: none; background: white; transition: border-color .2s;
        }
        .filters input:focus { border-color: var(--green); }
        .search-wrap { position: relative; flex: 1; min-width: 220px; }
        .search-wrap input { padding-left: 2.4rem; width: 100%; }
        .search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); }
        .btn {
            padding: .6rem 1.2rem; border: none; border-radius: 8px;
            font-family: 'Space Grotesk', sans-serif; font-size: .875rem; font-weight: 600;
            cursor: pointer; transition: all .2s;
            display: inline-flex; align-items: center; gap: .4rem; text-decoration: none;
        }
        .btn-export    { background: #16a085; color: white; }
        .btn-export:hover { background: #138d75; }
        .btn-secondary { background: #ecf0f1; color: var(--text); }
        .btn-secondary:hover { background: #d5dbdb; }

        /* ── TABLE ── */
        .card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .card-header {
            padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h3 { font-size: .95rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
        .result-count { font-size: .8rem; color: var(--muted); font-weight: 500; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        thead { background: linear-gradient(135deg,#1e293b,#334155); }
        thead th {
            padding: .85rem 1rem; text-align: left;
            color: #fff; font-weight: 600; font-size: .74rem;
            text-transform: uppercase; letter-spacing: .5px;
            white-space: nowrap;
        }
        tbody td {
            padding: .85rem 1rem; border-bottom: 1px solid #f0f3f6;
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #f8fafc; }

        .asset-name   { font-weight: 700; color: var(--text); }
        .asset-model  { font-size: .78rem; color: var(--muted); margin-top: 1px; }
        .asset-serial {
            font-size: .72rem; font-family: monospace;
            background: #f1f2f6; padding: 1px 5px;
            border-radius: 3px; display: inline-block; margin-top: 3px; color: var(--muted);
        }
        .sub-text { font-size: .75rem; color: var(--muted); margin-top: 1px; }

        /* Route arrow */
        .route-wrap { display: flex; flex-direction: column; gap: 2px; font-size: .82rem; }
        .route-from  { color: var(--muted); }
        .route-arrow { color: var(--green); font-size: .85rem; display: flex; align-items: center; gap: 3px; font-weight: 700; }
        .route-to    { font-weight: 700; color: var(--text); }

        /* Person cells */
        .person-cell { display: flex; align-items: center; gap: .35rem; font-size: .84rem; font-weight: 500; }
        .person-cell i { font-size: 1rem; flex-shrink: 0; }
        .person-none  { color: var(--muted); font-style: italic; font-size: .8rem; }

        .badge-released { display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .7rem; border-radius: 20px; font-size: .74rem; font-weight: 700; text-transform: uppercase; background: #d5f5e3; color: #1e8449; }

        /* ── EMPTY ── */
        .empty-state { text-align: center; padding: 4rem; color: var(--muted); }
        .empty-state i { font-size: 3.5rem; opacity: .2; display: block; margin-bottom: .75rem; }
        .empty-state p { font-weight: 600; }
        .empty-state small { font-size: .82rem; color: #bdc3c7; margin-top: .3rem; display: block; }
    </style>
</head>
<body>

<?php include 'warehouse_sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1><i class='bx bx-history'></i> Release History</h1>
        <p>All released items — <?= $today_released ?> released today · <?= $my_released ?> released by you</p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon green"><i class='bx bx-check-double'></i></div>
            <div>
                <div class="stat-value" style="color:var(--green);"><?= $totalReleased ?></div>
                <div class="stat-label">Total Released</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber"><i class='bx bx-calendar-check'></i></div>
            <div>
                <div class="stat-value" style="color:var(--amber);"><?= $today_released ?></div>
                <div class="stat-label">Released Today</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class='bx bx-user-check'></i></div>
            <div>
                <div class="stat-value" style="color:var(--blue);"><?= $my_released ?></div>
                <div class="stat-label">Released by Me</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters">
        <div class="search-wrap">
            <i class='bx bx-search'></i>
            <input type="text" id="searchInput"
                   placeholder="Search asset, serial, requester, released by, delivered by..."
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <input type="date" id="dateInput" value="<?= htmlspecialchars($dateFilter) ?>" title="Filter by release date">
        <button class="btn btn-export" onclick="exportCSV()"><i class='bx bx-download'></i> Export CSV</button>
        <?php if ($search || $dateFilter): ?>
            <a href="warehouse-completed.php" class="btn btn-secondary"><i class='bx bx-x'></i> Clear</a>
        <?php endif; ?>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class='bx bx-paper-plane' style="color:var(--green)"></i> Released Items</h3>
            <span class="result-count"><?= count($rows) ?> record<?= count($rows) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="table-wrap">
            <?php if (!empty($rows)): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Asset</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>From → To</th>
                        <th>Purpose</th>
                        <th>Requested By</th>
                        <th>Released By</th>
                        <th><i class='bx bx-truck' style="vertical-align:middle;margin-right:3px;"></i>Delivered By</th>
                        <th>Date Needed</th>
                        <th>Released At</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row):
                        $assetName = trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? '')) ?: '—';
                        $from      = $row['from_location'] ?: '—';
                        $to        = $row['to_location']   ?: '—';
                        $purpose   = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $row['purpose'] ?? '');
                        $purpose   = trim(preg_replace('/\s*\|?\s*From:.+?→\s*To:.+$/i', '', $purpose)) ?: '—';
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-weight:600;font-size:.8rem;">#<?= $row['id'] ?></td>
                        <td>
                            <div class="asset-name"><?= htmlspecialchars($row['brand'] ?? '—') ?></div>
                            <?php if ($row['model']): ?>
                                <div class="asset-model"><?= htmlspecialchars($row['model']) ?></div>
                            <?php endif; ?>
                            <?php if ($row['serial_number']): ?>
                                <span class="asset-serial"><?= htmlspecialchars($row['serial_number']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-size:.82rem;font-weight:500;">
                                <?= htmlspecialchars($row['subcategory_name'] ?? '—') ?>
                            </span>
                        </td>
                        <td style="font-weight:700;text-align:center;"><?= $row['quantity'] ?></td>
                        <td>
                            <div class="route-wrap">
                                <span class="route-from"><?= htmlspecialchars($from) ?></span>
                                <span class="route-arrow"><i class='bx bx-down-arrow-alt'></i></span>
                                <span class="route-to"><?= htmlspecialchars($to) ?></span>
                            </div>
                        </td>
                        <td style="font-size:.82rem;max-width:120px;">
                            <?= htmlspecialchars($purpose) ?>
                        </td>
                        <td>
                            <div class="person-cell">
                                <i class='bx bx-user' style="color:var(--muted);"></i>
                                <?= htmlspecialchars($row['requested_by'] ?? '—') ?>
                            </div>
                        </td>
                        <td>
                            <div class="person-cell">
                                <i class='bx bx-paper-plane' style="color:var(--amber);"></i>
                                <?= htmlspecialchars($row['released_by'] ?? '—') ?>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($row['delivered_by'])): ?>
                                <div class="person-cell">
                                    <i class='bx bx-truck' style="color:var(--green);"></i>
                                    <strong><?= htmlspecialchars($row['delivered_by']) ?></strong>
                                </div>
                            <?php else: ?>
                                <span class="person-none">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.82rem;white-space:nowrap;">
                            <?= $row['date_needed'] ? date('M j, Y', strtotime($row['date_needed'])) : '—' ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($row['released_at']): ?>
                                <div style="font-weight:600;font-size:.82rem;"><?= date('M j, Y', strtotime($row['released_at'])) ?></div>
                                <div class="sub-text"><?= date('g:i A', strtotime($row['released_at'])) ?></div>
                            <?php else: ?>
                                <span style="color:var(--muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-released">
                                <i class='bx bx-check-double'></i> Released
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-history'></i>
                    <p>No released items yet</p>
                    <small>Items released from the Preparing page will appear here.</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function applyFilters() {
    const p = new URLSearchParams();
    p.append('search', document.getElementById('searchInput').value);
    p.append('date',   document.getElementById('dateInput').value);
    window.location.href = 'warehouse-completed.php?' + p.toString();
}
document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
document.getElementById('dateInput').addEventListener('change', applyFilters);

function exportCSV() {
    const headers = ['ID','Brand','Model','Serial','Type','Qty','From','To','Purpose','Requested By','Released By','Delivered By','Date Needed','Released At'];
    const data = <?php
        $out = [];
        foreach ($rows as $r) {
            $from = $r['from_location'] ?: '—';
            $to   = $r['to_location']   ?: '—';
            $p    = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/', '', $r['purpose'] ?? '');
            $p    = trim(preg_replace('/\s*\|?\s*From:.+?→\s*To:.+$/i', '', $p));
            $out[] = [
                $r['id'],
                $r['brand']            ?? '',
                $r['model']            ?? '',
                $r['serial_number']    ?? '',
                $r['subcategory_name'] ?? '',
                $r['quantity'],
                $from, $to, $p,
                $r['requested_by']  ?? '',
                $r['released_by']   ?? '',
                $r['delivered_by']  ?? '',
                $r['date_needed']   ?? '',
                $r['released_at']   ?? '',
            ];
        }
        echo json_encode($out);
    ?>;
    const csv = [headers, ...data].map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
    const a   = document.createElement('a');
    a.href    = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    a.download = 'release_history_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
}
</script>

</body>
</html>
<?php ob_end_flush(); ?>