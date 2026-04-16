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

$txnStats = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='PENDING' THEN 1 ELSE 0 END) as pending, 
                          SUM(CASE WHEN status='RELEASED' THEN 1 ELSE 0 END) as received, 
                          SUM(CASE WHEN status='RETURNED' THEN 1 ELSE 0 END) as returned, 
                          SUM(CASE WHEN status='CANCELLED' THEN 1 ELSE 0 END) as cancelled 
                          FROM pull_out_transactions")->fetch_assoc();

$txnStats['total'] = (float)($txnStats['total'] ?? 0);
$txnStats['pending'] = (float)($txnStats['pending'] ?? 0);
$txnStats['received'] = (float)($txnStats['received'] ?? 0);
$txnStats['returned'] = (float)($txnStats['returned'] ?? 0);
$txnStats['cancelled'] = (float)($txnStats['cancelled'] ?? 0);

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
$lsResult = $conn->query("SELECT a.brand, a.model, a.beg_balance_count, a.location, c.name as cat 
                          FROM assets a JOIN categories c ON a.category_id=c.id 
                          WHERE a.beg_balance_count <= 5 
                          ORDER BY a.beg_balance_count ASC LIMIT 5");
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
            --border: #dfe6e9; --shadow: rgba(0,0,0,0.07);
        }

        body { 
            font-family: 'Space Grotesk', sans-serif; 
            background: var(--bg-main); 
            color: var(--text-primary); 
            line-height: 1.5;
        }

        .content { 
            margin-left: 88px; 
            padding: 1.5rem; 
            transition: margin-left 0.3s ease; 
            min-height: 100vh; 
        }

        .sidebar:not(.close) ~ .content { margin-left: 260px; }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, #0984e3 100%);
            border-radius: 16px; 
            padding: 1.75rem 2rem; 
            margin-bottom: 1.75rem;
            color: white; 
            box-shadow: 0 8px 24px rgba(9,132,227,0.25);
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-header h1 { 
            font-size: 1.85rem; 
            font-weight: 700; 
            display: flex; 
            align-items: center; 
            gap: 0.75rem; 
        }
        .header-meta .date-value { font-size: 1.1rem; font-weight: 600; }

        /* KPI Grid - Fully Responsive */
        .kpi-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
            gap: 1.25rem; 
            margin-bottom: 1.75rem; 
        }

        .kpi-card {
            background: var(--bg-card); 
            border-radius: 14px; 
            padding: 1.4rem; 
            box-shadow: 0 2px 10px var(--shadow); 
            display: flex; 
            align-items: center;
            gap: 1.25rem; 
            transition: all 0.3s;
        }
        .kpi-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px var(--shadow); }

        .kpi-icon { 
            width:52px; height:52px; 
            border-radius:12px; 
            display:flex; align-items:center; justify-content:center; 
            font-size:1.7rem; flex-shrink:0; 
        }

        .kpi-value { font-size:1.9rem; font-weight:700; }
        .kpi-label { font-size:0.82rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; }

        /* Charts */
        .charts-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 1.25rem; 
            margin-bottom: 1.75rem; 
        }

        .chart-card { 
            background:var(--bg-card); 
            border-radius:14px; 
            padding:1.5rem; 
            box-shadow:0 2px 10px var(--shadow); 
        }

        .chart-wrap { position:relative; height:260px; }

        /* Bottom Grid */
        .bottom-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 1.25rem; 
            margin-bottom: 1.75rem; 
        }

        /* Table */
        .table-card { 
            background:var(--bg-card); 
            border-radius:14px; 
            box-shadow:0 2px 10px var(--shadow); 
            overflow:hidden; 
            margin-bottom: 2rem; 
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table { 
            width:100%; 
            min-width: 800px; /* Para magkaroon ng horizontal scroll sa phone */
            border-collapse:collapse; 
            font-size:0.87rem; 
        }

        /* Status Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 0.35rem 0.85rem; border-radius: 20px;
            font-size: 0.73rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.3px;
            white-space: nowrap; border: 1px solid;
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .content { 
                margin-left: 0 !important; 
                padding: 1rem; 
            }
            
            .page-header {
                padding: 1.4rem 1.5rem;
                flex-direction: column;
                text-align: center;
            }
            
            .kpi-card {
                padding: 1.2rem;
            }
            
            .kpi-value { font-size: 1.75rem; }
            
            .chart-wrap { height: 240px; }
            
            .low-stock-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.8rem;
            }
            
            .ls-qty {
                align-self: flex-start;
            }
        }

        @media (max-width: 480px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-wrap { height: 220px; }
            
            .page-header h1 { font-size: 1.65rem; }
        }
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
            <div style="font-size:0.8rem;opacity:0.8;text-transform:uppercase;letter-spacing:1px;">Today</div>
            <div style="font-size:1.15rem;font-weight:600;"><?= date('M d, Y') ?></div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-top:4px solid #0984e3;">
            <div class="kpi-icon" style="background:rgba(9,132,227,0.12);color:#0984e3;"><i class='bx bx-package'></i></div>
            <div>
                <div class="kpi-value"><?= number_format($totalAssets) ?></div>
                <div class="kpi-label">Total Assets</div>
                <small style="color:var(--text-secondary);"><?= $totalLocations ?> locations</small>
            </div>
        </div>

        <div class="kpi-card" style="border-top:4px solid #00b894;">
            <div class="kpi-icon" style="background:rgba(0,184,148,0.12);color:#00b894;"><i class='bx bx-check-shield'></i></div>
            <div>
                <div class="kpi-value"><?= number_format($workingAssets) ?></div>
                <div class="kpi-label">Working Assets</div>
                <small style="color:var(--text-secondary);"><?= number_format($totalAssets - $workingAssets) ?> need attention</small>
            </div>
        </div>

        <div class="kpi-card" style="border-top:4px solid #e17055;">
            <div class="kpi-icon" style="background:rgba(225,112,85,0.12);color:#e17055;"><i class='bx bx-time-five'></i></div>
            <div>
                <div class="kpi-value"><?= number_format($statusData['FOR CHECKING'] ?? 0) ?></div>
                <div class="kpi-label">For Checking</div>
                <small style="color:var(--text-secondary);"><?= number_format($statusData['NOT WORKING'] ?? 0) ?> not working</small>
            </div>
        </div>

        <div class="kpi-card" style="border-top:4px solid #6c5ce7;">
            <div class="kpi-icon" style="background:rgba(108,92,231,0.12);color:#6c5ce7;"><i class='bx bx-transfer-alt'></i></div>
            <div>
                <div class="kpi-value"><?= number_format($txnStats['pending']) ?></div>
                <div class="kpi-label">In Transit</div>
                <small style="color:var(--text-secondary);"><?= number_format($txnStats['total']) ?> total transfers</small>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3 style="margin-bottom:1rem;font-size:0.95rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;">
                <i class='bx bx-bar-chart-alt-2'></i> Assets by Category
            </h3>
            <div class="chart-wrap"><canvas id="categoryChart"></canvas></div>
        </div>

        <div class="chart-card">
            <h3 style="margin-bottom:1rem;font-size:0.95rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;">
                <i class='bx bx-map-pin'></i> Top Locations
            </h3>
            <div class="chart-wrap"><canvas id="locationChart"></canvas></div>
        </div>

        <div class="chart-card">
            <h3 style="margin-bottom:1rem;font-size:0.95rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;">
                <i class='bx bx-pie-chart-alt-2'></i> Asset Status
            </h3>
            <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
        </div>

        <div class="chart-card">
            <h3 style="margin-bottom:1rem;font-size:0.95rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;">
                <i class='bx bx-doughnut-chart'></i> Condition
            </h3>
            <div class="chart-wrap"><canvas id="conditionChart"></canvas></div>
        </div>
    </div>

    <!-- Bottom Section -->
    <div class="bottom-grid">
        <!-- Transfer Summary -->
        <div class="chart-card">
            <h3 style="margin-bottom:1.25rem;"><i class='bx bx-transfer-alt'></i> Transfer Summary</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:0.8rem;">
                <div style="background:var(--bg-main);padding:1rem;border-radius:10px;text-align:center;border-left:4px solid var(--primary);">
                    <div style="font-size:1.6rem;font-weight:700;color:var(--primary);"><?= number_format($txnStats['total']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">TOTAL</div>
                </div>
                <div style="background:var(--bg-main);padding:1rem;border-radius:10px;text-align:center;border-left:4px solid #f39c12;">
                    <div style="font-size:1.6rem;font-weight:700;color:#f39c12;"><?= number_format($txnStats['pending']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">PENDING</div>
                </div>
                <div style="background:var(--bg-main);padding:1rem;border-radius:10px;text-align:center;border-left:4px solid var(--secondary);">
                    <div style="font-size:1.6rem;font-weight:700;color:var(--secondary);"><?= number_format($txnStats['received']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">RECEIVED</div>
                </div>
                <div style="background:var(--bg-main);padding:1rem;border-radius:10px;text-align:center;border-left:4px solid #0984e3;">
                    <div style="font-size:1.6rem;font-weight:700;color:#0984e3;"><?= number_format($txnStats['returned']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">RETURNED</div>
                </div>
            </div>
        </div>

        <!-- Low Stock -->
        <div class="chart-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <h3><i class='bx bx-error'></i> Low Stock Alerts</h3>
                <?php if (count($lowStock)): ?>
                    <span style="background:#ffeaea;color:var(--danger);padding:0.25rem 0.7rem;border-radius:6px;font-size:0.78rem;font-weight:700;">
                        <?= count($lowStock) ?> items
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if (count($lowStock)): ?>
                <div style="display:flex;flex-direction:column;gap:0.7rem;">
                    <?php foreach ($lowStock as $item): ?>
                        <div style="background:var(--bg-main);padding:1rem;border-radius:10px;display:flex;align-items:center;justify-content:space-between;">
                            <div>
                                <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?></div>
                                <div style="font-size:0.78rem;color:var(--text-secondary);"><?= htmlspecialchars($item['location'] ?: 'No location') ?></div>
                            </div>
                            <div style="background:#ffeaea;color:var(--danger);padding:0.35rem 0.8rem;border-radius:8px;font-weight:700;font-size:0.85rem;">
                                <?= $item['beg_balance_count'] ?> left
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:2.5rem 1rem;color:var(--text-secondary);">
                    <i class='bx bx-check-circle' style="font-size:3rem;opacity:0.6;"></i>
                    <p style="margin-top:0.5rem;">All stock levels are healthy</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Transfers -->
    <div class="table-card">
        <div style="padding:1.25rem 1.5rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);">
            <h3><i class='bx bx-history'></i> Recent Transfers</h3>
            <a href="pullout.php" style="font-size:0.85rem;font-weight:600;color:var(--secondary);text-decoration:none;padding:0.5rem 1rem;border:2px solid var(--secondary);border-radius:8px;">
                View All
            </a>
        </div>
        
        <div class="table-responsive">
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
                        <td style="color:var(--text-secondary);">#<?= $txn['id'] ?></td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($txn['brand']) ?></div>
                            <div style="font-size:0.8rem;color:var(--text-secondary);"><?= htmlspecialchars($txn['model']) ?></div>
                        </td>
                        <td><strong><?= $txn['quantity'] ?></strong></td>
                        <td>
                            <div style="font-size:0.82rem;">
                                <span style="color:var(--text-secondary);"><?= $from ?></span> 
                                <span style="color:var(--secondary);">→</span> 
                                <span style="font-weight:600;"><?= $to ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($txn['requested_by']) ?></td>
                        <td style="white-space:nowrap;color:var(--text-secondary);font-size:0.82rem;">
                            <?= date('M d, Y', strtotime($txn['created_at'])) ?>
                        </td>
                        <td>
                            <span class="badge <?= $badge[0] ?>">
                                <i class='bx <?= $badge[1] ?>'></i> <?= $badge[2] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="padding:3rem;text-align:center;color:var(--text-secondary);">
                <i class='bx bx-transfer-alt' style="font-size:3.5rem;opacity:0.3;display:block;margin-bottom:1rem;"></i>
                <p>No recent transfer transactions yet.</p>
            </div>
            <?php endif; ?>
        </div>
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
        datasets: [{ 
            label:'Assets', 
            data: <?= json_encode(array_column($categoryData, 'count')) ?>,
            backgroundColor: ['#0984e3','#00b894','#6c5ce7','#e17055','#fdcb6e','#74b9ff','#d63031','#636e72'],
            borderRadius: 6 
        }]
    },
    options: { 
        responsive:true, 
        maintainAspectRatio:false, 
        plugins:{legend:{display:false}},
        scales:{ 
            y:{beginAtZero:true, ticks:{precision:0}},
            x:{grid:{display:false}}
        }
    }
});

new Chart(document.getElementById('locationChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($locationData, 'location')) ?>,
        datasets: [{ 
            label:'Assets', 
            data: <?= json_encode(array_column($locationData, 'count')) ?>, 
            backgroundColor:'rgba(0,184,148,0.75)', 
            borderRadius:4 
        }]
    },
    options: { 
        indexAxis:'y', 
        responsive:true, 
        maintainAspectRatio:false, 
        plugins:{legend:{display:false}},
        scales:{ 
            x:{beginAtZero:true, ticks:{precision:0}},
            y:{ticks:{font:{size:11}}}
        }
    }
});

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: { 
        labels:['Working','For Checking','Not Working'],
        datasets:[{
            data:[<?= $statusData['WORKING']??0 ?>,<?= $statusData['FOR CHECKING']??0 ?>,<?= $statusData['NOT WORKING']??0 ?>],
            backgroundColor:['#00b894','#e17055','#d63031'],
            borderWidth:4,
            borderColor:'#fff'
        }]
    },
    options: { 
        responsive:true, 
        maintainAspectRatio:false, 
        cutout:'68%',
        plugins:{legend:{position:'right', labels:{boxWidth:12, padding:12, font:{size:13}}}}
    }
});

new Chart(document.getElementById('conditionChart'), {
    type: 'doughnut',
    data: { 
        labels:['New','Used'],
        datasets:[{
            data:[<?= $conditionData['NEW']??0 ?>,<?= $conditionData['USED']??0 ?>],
            backgroundColor:['#0984e3','#6c5ce7'],
            borderWidth:4,
            borderColor:'#fff'
        }]
    },
    options: { 
        responsive:true, 
        maintainAspectRatio:false, 
        cutout:'68%',
        plugins:{legend:{position:'right', labels:{boxWidth:12, padding:12, font:{size:13}}}}
    }
});
</script>
</body>
</html>