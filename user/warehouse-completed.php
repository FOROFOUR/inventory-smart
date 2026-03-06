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
$search       = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status']      ?? '';
$dateFilter   = trim($_GET['date']   ?? '');

$validStatuses = ['RELEASED', 'RETURNED', 'CANCELLED'];

// Only show transactions that passed through this warehouse (released_by = current user)
$sql = "
    SELECT p.*, a.brand, a.model, a.serial_number,
           c.name AS category_name, sc.name AS subcategory_name
    FROM pull_out_transactions p
    LEFT JOIN assets         a  ON p.asset_id        = a.id
    LEFT JOIN categories     c  ON a.category_id     = c.id
    LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
    WHERE p.status IN ('RELEASED', 'RETURNED', 'CANCELLED')
      AND p.released_by = ?
";
$params = [$user_name];
$types  = 's';

if ($search !== '') {
    $sql   .= " AND (a.brand LIKE ? OR a.model LIKE ? OR p.requested_by LIKE ? OR a.serial_number LIKE ? OR p.received_by LIKE ?)";
    $like   = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
    $types .= 'sssss';
}
if ($statusFilter !== '' && in_array($statusFilter, $validStatuses)) {
    $sql    .= " AND p.status = ?";
    $params[] = $statusFilter;
    $types  .= 's';
}
if ($dateFilter !== '') {
    $sql    .= " AND DATE(p.released_at) = ?";
    $params[] = $dateFilter;
    $types  .= 's';
}

$sql .= " ORDER BY p.released_at DESC, p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows   = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;

// ── Count per status (for THIS warehouse user only) ─────────────────────────
$counts = [];
foreach ($validStatuses as $s) {
    $q = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = ? AND released_by = ?");
    $q->bind_param("ss", $s, $user_name);
    $q->execute();
    $q->bind_result($counts[$s]);
    $q->fetch();
    $q->close();
}

// ── Today released by this user ──────────────────────────────────────────────
$q = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RELEASED' AND released_by = ? AND DATE(released_at) = CURDATE()");
$q->bind_param("s", $user_name);
$q->execute();
$q->bind_result($today_released);
$q->fetch();
$q->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Release History — QC Warehouse</title>
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

        /* ── LAYOUT ── */
        .main-content {
            position: relative; left: 250px;
            width: calc(100% - 250px); min-height: 100vh;
            background: var(--bg); padding: 2rem;
            transition: all .3s ease;
        }
        .sidebar.close ~ .main-content { left: 88px; width: calc(100% - 88px); }

        /* ── PAGE HEADER ── */
        .page-header {
            background: linear-gradient(135deg, #27ae60 0%, #1e8449 100%);
            border-radius: 16px; padding: 2rem 2rem 1.5rem;
            color: white; margin-bottom: 1.5rem;
            position: relative; overflow: hidden;
        }
        .page-header::before {
            content: ''; position: absolute; top: -50%; right: -5%;
            width: 300px; height: 300px;
            background: rgba(255,255,255,.08); border-radius: 50%;
        }
        .page-header h1 {
            font-size: 1.6rem; font-weight: 700;
            position: relative; z-index: 1;
            display: flex; align-items: center; gap: .6rem;
        }
        .page-header p {
            font-size: .9rem; opacity: .85; margin-top: .25rem;
            position: relative; z-index: 1;
        }

        /* ── STATS ── */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem; margin-bottom: 1.5rem;
        }
        .stat-card {
            background: var(--white); border-radius: var(--radius);
            padding: 1.25rem 1.5rem; box-shadow: var(--shadow);
            display: flex; align-items: center; gap: 1rem;
            cursor: pointer; text-decoration: none; color: inherit;
            transition: transform .2s, box-shadow .2s;
            border: 2px solid transparent;
        }
        .stat-card:hover        { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.1); }
        .stat-card.active-all      { border-color: var(--amber); }
        .stat-card.active-released { border-color: var(--green); }
        .stat-card.active-returned { border-color: var(--blue);  }
        .stat-card.active-cancelled{ border-color: var(--red);   }

        .stat-icon {
            width: 46px; height: 46px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }
        .stat-icon.green   { background: #d5f5e3; color: var(--green); }
        .stat-icon.blue    { background: #d6eaf8; color: var(--blue);  }
        .stat-icon.red     { background: #fadbd8; color: var(--red);   }
        .stat-icon.amber   { background: #fef3cd; color: var(--amber); }
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
            display: inline-flex; align-items: center; gap: .4rem;
            text-decoration: none;
        }
        .btn-export   { background: #16a085; color: white; }
        .btn-export:hover { background: #138d75; }
        .btn-secondary { background: #ecf0f1; color: var(--text); }
        .btn-secondary:hover { background: #d5dbdb; }

        /* ── TABLE CARD ── */
        .card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .card-header {
            padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h3 { font-size: .95rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
        .result-count { font-size: .8rem; color: var(--muted); font-weight: 500; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        thead th {
            background: #fafbfc; padding: .75rem 1rem; text-align: left;
            color: var(--muted); font-weight: 600; font-size: .75rem;
            text-transform: uppercase; letter-spacing: .5px;
            border-bottom: 1px solid var(--border);
        }
        tbody td {
            padding: .85rem 1rem; border-bottom: 1px solid #f0f3f6;
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #fafbfc; }

        .badge {
            display: inline-flex; align-items: center; gap: .3rem;
            padding: .25rem .7rem; border-radius: 20px;
            font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
        }
        .badge-released  { background: #d5f5e3; color: #1e8449; }
        .badge-returned  { background: #d6eaf8; color: #1a5276; }
        .badge-cancelled { background: #fadbd8; color: #922b21; }

        .asset-name   { font-weight: 600; color: var(--text); }
        .asset-serial {
            font-size: .73rem; font-family: monospace;
            background: #f1f2f6; padding: 1px 5px;
            border-radius: 3px; display: inline-block; margin-top: 2px; color: var(--muted);
        }
        .sub-text { font-size: .75rem; color: var(--muted); margin-top: 1px; }

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
        <p>All items you have released — <?= $today_released ?> released today</p>
    </div>

    <!-- Stats / Quick Filter Tabs -->
    <div class="stats-row">
        <a href="warehouse-completed.php" class="stat-card <?= $statusFilter === '' ? 'active-all' : '' ?>">
            <div class="stat-icon amber"><i class='bx bx-list-ul'></i></div>
            <div>
                <div class="stat-value" style="color:var(--amber);"><?= array_sum($counts) ?></div>
                <div class="stat-label">All Time</div>
            </div>
        </a>
        <a href="warehouse-completed.php?status=RELEASED" class="stat-card <?= $statusFilter === 'RELEASED' ? 'active-released' : '' ?>">
            <div class="stat-icon green"><i class='bx bx-check-double'></i></div>
            <div>
                <div class="stat-value" style="color:var(--green);"><?= $counts['RELEASED'] ?></div>
                <div class="stat-label">Released</div>
            </div>
        </a>
        <a href="warehouse-completed.php?status=RETURNED" class="stat-card <?= $statusFilter === 'RETURNED' ? 'active-returned' : '' ?>">
            <div class="stat-icon blue"><i class='bx bx-undo'></i></div>
            <div>
                <div class="stat-value" style="color:var(--blue);"><?= $counts['RETURNED'] ?></div>
                <div class="stat-label">Returned</div>
            </div>
        </a>
        <a href="warehouse-completed.php?status=CANCELLED" class="stat-card <?= $statusFilter === 'CANCELLED' ? 'active-cancelled' : '' ?>">
            <div class="stat-icon red"><i class='bx bx-x-circle'></i></div>
            <div>
                <div class="stat-value" style="color:var(--red);"><?= $counts['CANCELLED'] ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </a>
    </div>

    <!-- Filters -->
    <div class="filters">
        <?php if ($statusFilter): ?>
            <!-- preserve status in form -->
        <?php endif; ?>
        <div class="search-wrap">
            <i class='bx bx-search'></i>
            <input type="text" id="searchInput" placeholder="Search asset, requester, serial, received by..."
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <input type="date" id="dateInput" value="<?= htmlspecialchars($dateFilter) ?>" title="Filter by release date">
        <button class="btn btn-export" onclick="exportCSV()"><i class='bx bx-download'></i> Export CSV</button>
        <?php if ($search || $dateFilter): ?>
            <a href="warehouse-completed.php<?= $statusFilter ? '?status=' . $statusFilter : '' ?>" class="btn btn-secondary">
                <i class='bx bx-x'></i> Clear
            </a>
        <?php endif; ?>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class='bx bx-transfer' style="color:var(--green)"></i> Transaction History</h3>
            <span class="result-count"><?= count($rows) ?> record<?= count($rows) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="table-wrap">
            <?php if (!empty($rows)): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Asset</th>
                        <th>Route</th>
                        <th>Qty</th>
                        <th>Requested By</th>
                        <th>Received By</th>
                        <th>Released At</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row):
                        $assetName = trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? '')) ?: '—';
                        $from      = $row['from_location'] ?: '—';
                        $to        = $row['to_location']   ?: ($row['location_received'] ?: '—');
                        $s         = strtolower($row['status']);
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-weight:600;">#<?= $row['id'] ?></td>
                        <td>
                            <div class="asset-name"><?= htmlspecialchars($assetName) ?></div>
                            <?php if ($row['serial_number']): ?>
                                <span class="asset-serial"><?= htmlspecialchars($row['serial_number']) ?></span>
                            <?php endif; ?>
                            <div class="sub-text"><?= htmlspecialchars($row['subcategory_name'] ?? '') ?></div>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;">
                                <span style="background:#f0f3f6;padding:2px 7px;border-radius:4px;font-weight:500;color:var(--text);">
                                    <?= htmlspecialchars($from) ?>
                                </span>
                                <i class='bx bx-right-arrow-alt' style="color:var(--green);font-size:1rem;flex-shrink:0;"></i>
                                <span style="background:#f0f3f6;padding:2px 7px;border-radius:4px;font-weight:500;color:var(--text);">
                                    <?= htmlspecialchars($to) ?>
                                </span>
                            </div>
                        </td>
                        <td style="font-weight:600;"><?= $row['quantity'] ?> pc<?= $row['quantity'] > 1 ? 's' : '' ?></td>
                        <td><?= htmlspecialchars($row['requested_by'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['received_by'] ?? '—') ?></td>
                        <td>
                            <?php if ($row['released_at']): ?>
                                <div style="font-weight:600;"><?= date('M j, Y', strtotime($row['released_at'])) ?></div>
                                <div class="sub-text"><?= date('g:i A', strtotime($row['released_at'])) ?></div>
                            <?php else: ?>
                                <span style="color:var(--muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                                $icons = ['released' => 'bx-check-double', 'returned' => 'bx-undo', 'cancelled' => 'bx-x-circle'];
                            ?>
                            <span class="badge badge-<?= $s ?>">
                                <i class='bx <?= $icons[$s] ?? 'bx-circle' ?>'></i>
                                <?= ucfirst($s) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class='bx bx-history'></i>
                    <p>No release history yet</p>
                    <small>Items you release from the Preparing page will appear here.</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /main-content -->

<script>
function applyFilters() {
 const p = new URLSearchParams();
p.append('search', document.getElementById('searchInput').value);
p.append('date', document.getElementById('dateInput').value);
    window.location.href = 'warehouse-completed.php?' + p.toString();
}
document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
document.getElementById('dateInput').addEventListener('change', applyFilters);

function exportCSV() {
    const headers = ['ID', 'Brand', 'Model', 'Serial', 'Type', 'Qty', 'From', 'To', 'Requested By', 'Received By', 'Released By', 'Released At', 'Status'];
    const data = <?php
        $out = [];
        foreach ($rows as $r) {
            $from = $r['from_location'] ?: '—';
            $to   = $r['to_location']   ?: ($r['location_received'] ?: '—');
            $out[] = [
                $r['id'], $r['brand'] ?? '', $r['model'] ?? '', $r['serial_number'] ?? '',
                $r['subcategory_name'] ?? '', $r['quantity'], $from, $to,
                $r['requested_by'] ?? '', $r['received_by'] ?? '', $r['released_by'] ?? '',
                $r['released_at'] ?? '', $r['status'] ?? ''
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