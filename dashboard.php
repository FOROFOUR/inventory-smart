<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: landing.php");
    exit();
}

$conn = getDBConnection();

$totalAssets   = (float)($conn->query("SELECT SUM(beg_balance_count) as total FROM assets")->fetch_assoc()['total'] ?? 0);
$workingAssets = (float)($conn->query("SELECT SUM(beg_balance_count) as total FROM assets WHERE status = 'WORKING'")->fetch_assoc()['total'] ?? 0);

$statusResult = $conn->query("SELECT status, SUM(beg_balance_count) as count FROM assets GROUP BY status");
$statusData = [];
while ($row = $statusResult->fetch_assoc()) $statusData[$row['status']] = (float)($row['count'] ?? 0);

$conditionResult = $conn->query("SELECT `condition`, SUM(beg_balance_count) as count FROM assets GROUP BY `condition`");
$conditionData = [];
while ($row = $conditionResult->fetch_assoc()) $conditionData[$row['condition']] = (float)($row['count'] ?? 0);

$categoryData = [];
$catResult = $conn->query("SELECT c.name, SUM(a.beg_balance_count) as count FROM assets a JOIN categories c ON a.category_id=c.id GROUP BY c.id,c.name ORDER BY count DESC");
while ($row = $catResult->fetch_assoc()) $categoryData[] = $row;

$locationData = [];
$locResult = $conn->query("SELECT location, SUM(beg_balance_count) as count FROM assets WHERE location != '' GROUP BY location ORDER BY count DESC LIMIT 8");
while ($row = $locResult->fetch_assoc()) $locationData[] = $row;

$txnStats = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='PENDING' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN status='RELEASED' THEN 1 ELSE 0 END) as received, SUM(CASE WHEN status='RETURNED' THEN 1 ELSE 0 END) as returned, SUM(CASE WHEN status='CANCELLED' THEN 1 ELSE 0 END) as cancelled FROM pull_out_transactions")->fetch_assoc();
$txnStats['total'] = (float)($txnStats['total'] ?? 0);
$txnStats['pending'] = (float)($txnStats['pending'] ?? 0);
$txnStats['received'] = (float)($txnStats['received'] ?? 0);
$txnStats['returned'] = (float)($txnStats['returned'] ?? 0);
$txnStats['cancelled'] = (float)($txnStats['cancelled'] ?? 0);

// Recent transfers — fetch from_location & to_location directly
$recentTxns = [];
$txnResult = $conn->query("
    SELECT p.id, p.quantity, p.purpose, p.requested_by, p.released_by,
           p.from_location, p.to_location, p.status, p.created_at,
           a.brand, a.model
    FROM pull_out_transactions p
    JOIN assets a ON p.asset_id = a.id
    ORDER BY p.created_at DESC LIMIT 6
");
while ($row = $txnResult->fetch_assoc()) $recentTxns[] = $row;

$lowStock = [];
$lsResult = $conn->query("SELECT a.brand, a.model, a.beg_balance_count, a.location, c.name as cat FROM assets a JOIN categories c ON a.category_id=c.id WHERE a.beg_balance_count <= 5 ORDER BY a.beg_balance_count ASC LIMIT 5");
while ($row = $lsResult->fetch_assoc()) $lowStock[] = $row;

$totalLocations = $conn->query("SELECT COUNT(DISTINCT location) as cnt FROM assets WHERE location != ''")->fetch_assoc()['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Inventory Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #2d3436; --secondary: #00b894; --accent: #6c5ce7;
            --danger: #d63031; --warning: #e17055; --info: #0984e3;
            --bg-main: #f0f2f5; --bg-card: #ffffff;
            --text-primary: #2d3436; --text-secondary: #636e72;
            --border: #dfe6e9; --shadow: rgba(0,0,0,0.07); --shadow-lg: rgba(0,0,0,0.13);
        }
        body { font-family: 'Space Grotesk', sans-serif; background: var(--bg-main); color: var(--text-primary); }

        .content { margin-left: 88px; padding: 2rem; transition: margin-left 0.3s ease; min-height: 100vh; }
        .sidebar:not(.close) ~ .content { margin-left: 260px; }

        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, #0984e3 100%);
            border-radius: 16px; padding: 2rem 2.5rem; margin-bottom: 2rem;
            color: white; box-shadow: 0 8px 24px rgba(9,132,227,0.25);
            display: flex; align-items: center; justify-content: space-between;
        }
        .page-header h1 { font-size: 2rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
        .page-header h1 i { font-size: 2.2rem; }
        .page-header p { opacity: 0.85; font-size: 0.95rem; margin-top: 0.25rem; }
        .header-meta { text-align: right; }
        .header-meta .date-label { font-size: 0.8rem; opacity: 0.7; text-transform: uppercase; letter-spacing: 1px; }
        .header-meta .date-value { font-size: 1.1rem; font-weight: 600; }

        .kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 1.25rem; margin-bottom: 1.75rem; }
        .kpi-card {
            background: var(--bg-card); border-radius: 14px; padding: 1.5rem;
            box-shadow: 0 2px 10px var(--shadow); display: flex; align-items: center;
            gap: 1.25rem; transition: all 0.3s; position: relative; overflow: hidden;
        }
        .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-card.blue::before   { background: linear-gradient(90deg,#0984e3,#74b9ff); }
        .kpi-card.green::before  { background: linear-gradient(90deg,#00b894,#55efc4); }
        .kpi-card.orange::before { background: linear-gradient(90deg,#e17055,#fdcb6e); }
        .kpi-card.purple::before { background: linear-gradient(90deg,#6c5ce7,#a29bfe); }
        .kpi-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px var(--shadow-lg); }
        .kpi-icon { width:56px; height:56px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.6rem; flex-shrink:0; }
        .kpi-card.blue   .kpi-icon { background:rgba(9,132,227,0.12);  color:#0984e3; }
        .kpi-card.green  .kpi-icon { background:rgba(0,184,148,0.12);  color:#00b894; }
        .kpi-card.orange .kpi-icon { background:rgba(225,112,85,0.12); color:#e17055; }
        .kpi-card.purple .kpi-icon { background:rgba(108,92,231,0.12); color:#6c5ce7; }
        .kpi-info { flex: 1; min-width: 0; }
        .kpi-value { font-size:2rem; font-weight:700; line-height:1.1; }
        .kpi-label { font-size:0.82rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-top:0.2rem; font-weight:500; }
        .kpi-sub   { font-size:0.78rem; color:var(--text-secondary); margin-top:0.3rem; }

        .charts-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.75rem; }
        .chart-card  { background:var(--bg-card); border-radius:14px; padding:1.5rem; box-shadow:0 2px 10px var(--shadow); }
        .card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; padding-bottom:0.75rem; border-bottom:2px solid var(--border); }
        .card-header h3 { font-size:0.95rem; font-weight:700; display:flex; align-items:center; gap:0.5rem; text-transform:uppercase; letter-spacing:0.5px; }
        .card-header h3 i { font-size:1.1rem; color:var(--secondary); }
        .chart-wrap { position:relative; height:240px; }

        .bottom-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; margin-bottom:1.75rem; }
        .info-card   { background:var(--bg-card); border-radius:14px; padding:1.5rem; box-shadow:0 2px 10px var(--shadow); }

        .txn-pills { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
        .txn-pill  { background:var(--bg-main); border-radius:10px; padding:1rem; text-align:center; border-left:3px solid; }
        .txn-pill.total { border-color:var(--primary); }
        .txn-pill.pend  { border-color:#f39c12; }
        .txn-pill.recv  { border-color:var(--secondary); }
        .txn-pill.ret   { border-color:var(--info); }
        .pill-val   { font-size:1.8rem; font-weight:700; line-height:1; }
        .txn-pill.pend .pill-val { color:#f39c12; }
        .txn-pill.recv .pill-val { color:var(--secondary); }
        .txn-pill.ret  .pill-val { color:var(--info); }
        .pill-label { font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-secondary); margin-top:0.25rem; font-weight:600; }

        .low-stock-list { display:flex; flex-direction:column; gap:0.6rem; }
        .low-stock-item { display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; background:var(--bg-main); border-radius:10px; border-left:3px solid var(--danger); }
        .ls-icon { width:32px; height:32px; background:rgba(214,48,49,0.1); border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--danger); font-size:1rem; flex-shrink:0; }
        .ls-name { font-size:0.88rem; font-weight:600; }
        .ls-loc  { font-size:0.75rem; color:var(--text-secondary); }
        .ls-qty  { font-size:0.8rem; font-weight:700; background:#ffeaea; color:var(--danger); padding:0.2rem 0.6rem; border-radius:6px; white-space:nowrap; }
        .no-alert { text-align:center; padding:1.5rem; color:var(--text-secondary); }
        .no-alert i { font-size:2.5rem; display:block; margin-bottom:0.5rem; color:var(--secondary); opacity:0.7; }

        /* ── RECENT TRANSFERS TABLE ── */
        .table-card { background:var(--bg-card); border-radius:14px; box-shadow:0 2px 10px var(--shadow); overflow:hidden; margin-bottom:1.75rem; }
        .table-card .card-header { padding:1.25rem 1.5rem; margin-bottom:0; border-radius:0; }
        .view-all-btn { font-size:0.82rem; font-weight:600; color:var(--secondary); text-decoration:none; padding:0.4rem 0.9rem; border:2px solid var(--secondary); border-radius:8px; transition:all 0.2s; }
        .view-all-btn:hover { background:var(--secondary); color:white; }
        table { width:100%; border-collapse:collapse; font-size:0.88rem; }
        thead { background:linear-gradient(135deg,#2d3436,#34495e); color:white; }
        thead th { padding:0.9rem 1rem; text-align:left; font-weight:600; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; white-space:nowrap; }
        tbody tr { border-bottom:1px solid var(--border); transition:background 0.15s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:#f8f9fa; }
        tbody td { padding:0.9rem 1rem; color:var(--text-primary); }
        .asset-cell .a-brand { font-weight:600; }
        .asset-cell .a-model { font-size:0.8rem; color:var(--text-secondary); }

        /* Route column */
        .route-cell { display:flex; flex-direction:column; gap:2px; }
        .route-cell .r-from  { font-size:0.78rem; color:var(--text-secondary); }
        .route-cell .r-arrow { font-size:0.72rem; color:var(--secondary); font-weight:700; display:flex; align-items:center; gap:2px; }
        .route-cell .r-to    { font-size:0.85rem; font-weight:600; color:var(--text-primary); }

        /* ── STATUS BADGES — colored with border + icon ── */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 0.3rem 0.8rem; border-radius: 20px;
            font-size: 0.73rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.3px;
            white-space: nowrap; border: 1px solid;
        }
        .badge i { font-size: 0.8rem; }
        .badge-pending   { background:#fff8e1; color:#b45309; border-color:#fcd34d; }
        .badge-confirmed { background:#f5f3ff; color:#6d28d9; border-color:#c4b5fd; }
        .badge-released  { background:#ecfdf5; color:#065f46; border-color:#6ee7b7; }
        .badge-received  { background:#eff6ff; color:#1e40af; border-color:#93c5fd; }
        .badge-returned  { background:#eff6ff; color:#1e40af; border-color:#93c5fd; }
        .badge-cancelled { background:#fef2f2; color:#991b1b; border-color:#fca5a5; }

        @media(max-width:1200px){ .kpi-grid{grid-template-columns:repeat(2,1fr);} .charts-grid,.bottom-grid{grid-template-columns:1fr;} }
        @media(max-width:768px) { .content{margin-left:0;padding:1rem;} .kpi-grid{grid-template-columns:1fr 1fr;} .page-header{flex-direction:column;gap:1rem;} }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="content">

    <div class="page-header">
        <div>
            <h1><i class='bx bx-grid-alt'></i> Dashboard</h1>
            <p>Real-time overview of your inventory system</p>
        </div>
        <div class="header-meta">
            <div class="date-label">Today</div>
            <div class="date-value"><?= date('M d, Y') ?></div>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card blue">
            <div class="kpi-icon"><i class='bx bx-package'></i></div>
            <div class="kpi-info">
                <div class="kpi-value"><?= number_format($totalAssets) ?></div>
                <div class="kpi-label">Total Assets</div>
                <div class="kpi-sub"><?= $totalLocations ?> locations</div>
            </div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-icon"><i class='bx bx-check-shield'></i></div>
            <div class="kpi-info">
                <div class="kpi-value"><?= number_format($workingAssets) ?></div>
                <div class="kpi-label">Working Assets</div>
                <div class="kpi-sub"><?= number_format($totalAssets - $workingAssets) ?> need attention</div>
            </div>
        </div>
        <div class="kpi-card orange">
            <div class="kpi-icon"><i class='bx bx-time-five'></i></div>
            <div class="kpi-info">
                <div class="kpi-value"><?= number_format($statusData['FOR CHECKING'] ?? 0) ?></div>
                <div class="kpi-label">For Checking</div>
                <div class="kpi-sub"><?= number_format($statusData['NOT WORKING'] ?? 0) ?> not working</div>
            </div>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-icon"><i class='bx bx-transfer-alt'></i></div>
            <div class="kpi-info">
                <div class="kpi-value"><?= number_format($txnStats['pending']) ?></div>
                <div class="kpi-label">In Transit</div>
                <div class="kpi-sub"><?= number_format($txnStats['total']) ?> total transfers</div>
            </div>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <div class="card-header"><h3><i class='bx bx-bar-chart-alt-2'></i> Assets by Category</h3></div>
            <div class="chart-wrap"><canvas id="categoryChart"></canvas></div>
        </div>
        <div class="chart-card">
            <div class="card-header"><h3><i class='bx bx-map-pin'></i> Top Locations</h3></div>
            <div class="chart-wrap"><canvas id="locationChart"></canvas></div>
        </div>
        <div class="chart-card">
            <div class="card-header"><h3><i class='bx bx-pie-chart-alt-2'></i> Asset Status</h3></div>
            <div class="chart-wrap" style="height:200px;"><canvas id="statusChart"></canvas></div>
        </div>
        <div class="chart-card">
            <div class="card-header"><h3><i class='bx bx-doughnut-chart'></i> Condition</h3></div>
            <div class="chart-wrap" style="height:200px;"><canvas id="conditionChart"></canvas></div>
        </div>
    </div>

    <div class="bottom-grid">
        <div class="info-card">
            <div class="card-header"><h3><i class='bx bx-transfer-alt'></i> Transfer Summary</h3></div>
            <div class="txn-pills">
                <div class="txn-pill total">
                    <div class="pill-val" style="color:var(--primary);"><?= number_format($txnStats['total']) ?></div>
                    <div class="pill-label">Total</div>
                </div>
                <div class="txn-pill pend">
                    <div class="pill-val"><?= number_format($txnStats['pending']) ?></div>
                    <div class="pill-label">Pending</div>
                </div>
                <div class="txn-pill recv">
                    <div class="pill-val"><?= number_format($txnStats['received']) ?></div>
                    <div class="pill-label">Received</div>
                </div>
                <div class="txn-pill ret">
                    <div class="pill-val"><?= number_format($txnStats['returned']) ?></div>
                    <div class="pill-label">Returned</div>
                </div>
            </div>
        </div>

        <div class="info-card">
            <div class="card-header">
                <h3><i class='bx bx-error'></i> Low Stock Alerts</h3>
                <?php if (count($lowStock)): ?>
                    <span style="font-size:0.78rem;background:#ffeaea;color:var(--danger);padding:0.2rem 0.6rem;border-radius:6px;font-weight:700;"><?= count($lowStock) ?> items</span>
                <?php endif; ?>
            </div>
            <?php if (count($lowStock)): ?>
                <div class="low-stock-list">
                    <?php foreach ($lowStock as $item): ?>
                        <div class="low-stock-item">
                            <div class="ls-icon"><i class='bx bx-error-circle'></i></div>
                            <div style="flex:1;min-width:0;">
                                <div class="ls-name"><?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?></div>
                                <div class="ls-loc"><i class='bx bx-map-pin' style="vertical-align:middle;font-size:0.8rem;"></i> <?= htmlspecialchars($item['location'] ?: '—') ?></div>
                            </div>
                            <div class="ls-qty"><?= $item['beg_balance_count'] ?> left</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-alert">
                    <i class='bx bx-check-circle'></i>
                    <p>All stock levels are healthy</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Transfers -->
    <div class="table-card">
        <div class="card-header">
            <h3><i class='bx bx-history'></i> Recent Transfers</h3>
            <a href="pullout.php" class="view-all-btn">View All</a>
        </div>
        <?php if (count($recentTxns)): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Asset</th>
                    <th>Qty</th>
                    <th>Route</th>
                    <th>Requested By</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentTxns as $txn):
                    $from = htmlspecialchars($txn['from_location'] ?: '—');
                    $to   = htmlspecialchars($txn['to_location']   ?: '—');

                    // Badge config: [class, boxicon, label]
                    $badge = match($txn['status']) {
                        'PENDING'   => ['badge-pending',   'bx-time-five',      'Pending'],
                        'CONFIRMED' => ['badge-confirmed', 'bx-loader-circle',  'Confirmed'],
                        'RELEASED'  => ['badge-released',  'bx-paper-plane',    'Released'],
                        'RECEIVED'  => ['badge-received',  'bx-check-double',   'Received'],
                        'RETURNED'  => ['badge-returned',  'bx-undo',           'Returned'],
                        'CANCELLED' => ['badge-cancelled', 'bx-x-circle',       'Cancelled'],
                        default     => ['badge-pending',   'bx-circle',          $txn['status']],
                    };
                ?>
                <tr>
                    <td style="color:var(--text-secondary);font-size:0.8rem;">#<?= $txn['id'] ?></td>
                    <td>
                        <div class="asset-cell">
                            <div class="a-brand"><?= htmlspecialchars($txn['brand']) ?></div>
                            <div class="a-model"><?= htmlspecialchars($txn['model']) ?></div>
                        </div>
                    </td>
                    <td><strong><?= $txn['quantity'] ?></strong></td>
                    <td>
                        <div class="route-cell">
                            <span class="r-from"><?= $from ?></span>
                            <span class="r-arrow"><i class='bx bx-down-arrow-alt'></i></span>
                            <span class="r-to"><?= $to ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($txn['requested_by']) ?></td>
                    <td style="color:var(--text-secondary);font-size:0.82rem;white-space:nowrap;"><?= date('M d, Y', strtotime($txn['created_at'])) ?></td>
                    <td>
                        <span class="badge <?= $badge[0] ?>">
                            <i class='bx <?= $badge[1] ?>'></i>
                            <?= $badge[2] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="padding:3rem;text-align:center;color:var(--text-secondary);">
            <i class='bx bx-transfer-alt' style="font-size:3rem;display:block;margin-bottom:0.75rem;opacity:0.3;"></i>
            <p>No recent transfer transactions</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
const font = 'Space Grotesk';
Chart.defaults.font.family = font;
Chart.defaults.color = '#636e72';

new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($categoryData, 'name')) ?>,
        datasets: [{ label:'Assets', data: <?= json_encode(array_column($categoryData, 'count')) ?>,
            backgroundColor: ['rgba(9,132,227,.75)','rgba(0,184,148,.75)','rgba(108,92,231,.75)','rgba(225,112,85,.75)','rgba(253,203,110,.75)','rgba(116,185,255,.75)','rgba(214,48,49,.75)','rgba(99,110,114,.75)'],
            borderRadius: 6, borderSkipped: false }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}},
        scales:{ y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.04)'},ticks:{precision:0}}, x:{grid:{display:false}} } }
});

new Chart(document.getElementById('locationChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($locationData, 'location')) ?>,
        datasets: [{ label:'Assets', data: <?= json_encode(array_column($locationData, 'count')) ?>, backgroundColor:'rgba(0,184,148,.7)', borderRadius:4 }]
    },
    options: { indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}},
        scales:{ x:{beginAtZero:true,grid:{color:'rgba(0,0,0,.04)'},ticks:{precision:0}}, y:{grid:{display:false},ticks:{font:{size:11}}} } }
});

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: { labels:['Working','For Checking','Not Working'],
        datasets:[{ data:[<?= $statusData['WORKING']??0 ?>,<?= $statusData['FOR CHECKING']??0 ?>,<?= $statusData['NOT WORKING']??0 ?>],
            backgroundColor:['#00b894','#e17055','#d63031'], borderWidth:3, borderColor:'#fff', hoverOffset:6 }] },
    options: { responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{legend:{position:'right',labels:{boxWidth:12,padding:14,font:{size:12}}}} }
});

new Chart(document.getElementById('conditionChart'), {
    type: 'doughnut',
    data: { labels:['New','Used'],
        datasets:[{ data:[<?= $conditionData['NEW']??0 ?>,<?= $conditionData['USED']??0 ?>],
            backgroundColor:['#0984e3','#6c5ce7'], borderWidth:3, borderColor:'#fff', hoverOffset:6 }] },
    options: { responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{legend:{position:'right',labels:{boxWidth:12,padding:14,font:{size:12}}}} }
});
</script>
</body>
</html>