<?php
ob_start();
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../landing.php"); exit(); }

$conn   = getDBConnection();
$userId = $_SESSION['user_id'];

$uStmt = $conn->prepare("SELECT name, role, warehouse_location FROM users WHERE id = ?");
$uStmt->bind_param("i", $userId);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();

if (($user['role'] ?? '') !== 'WAREHOUSE') { header("Location: ../landing.php"); exit(); }

$warehouseLocation = $user['warehouse_location'] ?? '';
$userName          = $user['name'] ?? 'Warehouse Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — Warehouse</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root {
            --primary: #16a085; --primary-dark: #0e6655;
            --secondary: #2563eb; --danger: #d63031;
            --warning: #f39c12; --info: #3498db; --success: #27ae60;
            --bg: #f4f6f9; --white: #fff;
            --text: #2c3e50; --muted: #7f8c8d; --border: #e8ecf0;
            --shadow: 0 2px 12px rgba(0,0,0,.07); --radius: 12px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Space Grotesk', sans-serif; }
        body { background: var(--bg); color: var(--text); }

        /* ── Layout ── */
        .main-content { position: relative; left: 250px; width: calc(100% - 250px); min-height: 100vh; background: var(--bg); padding: 2rem; transition: all .3s ease; }
        .sidebar.close ~ .main-content { left: 88px; width: calc(100% - 88px); }

        /* ── Page header ── */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 16px; padding: 2rem; margin-bottom: 2rem;
            color: white; box-shadow: 0 8px 24px rgba(22,160,133,.3);
            position: relative; overflow: hidden;
        }
        .page-header::before { content: ''; position: absolute; top: -50%; right: -5%; width: 300px; height: 300px; background: rgba(255,255,255,.07); border-radius: 50%; }
        .page-header h1 { font-size: 1.7rem; font-weight: 700; margin-bottom: .5rem; display: flex; align-items: center; gap: .75rem; position: relative; z-index: 1; }
        .page-header h1 i { font-size: 2rem; }
        .page-header p { opacity: .9; font-size: .95rem; position: relative; z-index: 1; }
        .location-pill { display: inline-flex; align-items: center; gap: .4rem; background: rgba(255,255,255,.18); border-radius: 20px; padding: .3rem .9rem; font-size: .8rem; font-weight: 600; margin-top: .6rem; position: relative; z-index: 1; }

        /* ── Report type selector ── */
        .report-selector { background: var(--white); border-radius: var(--radius); padding: 1.75rem; margin-bottom: 1.5rem; box-shadow: var(--shadow); }
        .report-selector h3 { margin-bottom: 1.25rem; font-size: .95rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: .5rem; }
        .report-types { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        .report-type-card {
            padding: 1.25rem; border: 2px solid var(--border); border-radius: var(--radius);
            cursor: pointer; transition: all .25s; text-align: center;
        }
        .report-type-card:hover { border-color: var(--primary); background: rgba(22,160,133,.05); }
        .report-type-card.active { border-color: var(--primary); background: var(--primary); color: white; }
        .report-type-card i { font-size: 2.25rem; margin-bottom: .6rem; display: block; }
        .report-type-card h3 { font-size: 1rem; margin-bottom: .35rem; font-weight: 700; border: none; padding: 0; }
        .report-type-card p { font-size: .82rem; opacity: .8; margin: 0; }

        /* ── Filters ── */
        .filters-section { background: var(--white); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow); }
        .filters-section h3 { margin-bottom: 1.25rem; font-size: .95rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: .5rem; }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
        .filter-group { display: flex; flex-direction: column; gap: .4rem; }
        .filter-group label { font-size: .8rem; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: .4px; }
        .filter-group select, .filter-group input {
            padding: .65rem .85rem; border: 1.5px solid var(--border); border-radius: 8px;
            font-family: 'Space Grotesk', sans-serif; font-size: .88rem; outline: none;
            background: #f8fafc; transition: border-color .2s;
        }
        .filter-group select:focus, .filter-group input:focus { border-color: var(--primary); }
        .filter-actions { display: flex; gap: .75rem; justify-content: flex-end; flex-wrap: wrap; }

        /* ── Buttons ── */
        .btn { padding: .75rem 1.35rem; border: none; border-radius: 8px; font-family: 'Space Grotesk', sans-serif; font-size: .9rem; font-weight: 600; cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: .4rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(22,160,133,.35); }
        .btn-secondary { background: var(--bg); color: var(--text); border: 1.5px solid var(--border); }
        .btn-secondary:hover { border-color: var(--primary); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #229954; }

        /* ── Report preview ── */
        .report-preview { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; display: none; }
        .report-preview.active { display: block; }
        .report-header { padding: 2rem; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 2px solid var(--border); text-align: center; }
        .report-header h2 { font-size: 1.6rem; margin-bottom: .5rem; color: var(--primary); }
        .report-meta { display: flex; justify-content: center; gap: 2rem; margin-top: 1rem; font-size: .88rem; color: var(--muted); flex-wrap: wrap; }
        .report-body { padding: 2rem; }

        /* ── Summary cards ── */
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .summary-card { padding: 1.25rem; background: linear-gradient(135deg, var(--bg), var(--white)); border-radius: 10px; border-left: 4px solid var(--primary); }
        .summary-card h4 { font-size: .78rem; color: var(--muted); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .5px; }
        .summary-card .value { font-size: 1.8rem; font-weight: 700; color: var(--primary); }

        /* ── Table ── */
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: linear-gradient(135deg, #2d3436, #34495e); color: white; }
        thead th { padding: .9rem 1rem; text-align: left; font-weight: 600; font-size: .78rem; text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid var(--border); }
        tbody tr:hover { background: var(--bg); }
        tbody tr:nth-child(even) { background: #fafbfe; }
        tbody tr:nth-child(even):hover { background: var(--bg); }
        tbody td { padding: .85rem 1rem; font-size: .84rem; vertical-align: middle; }

        /* ── Badges ── */
        .badge { display: inline-block; padding: .28rem .7rem; border-radius: 6px; font-size: .75rem; font-weight: 600; }
        .badge-new          { background: #d4edda; color: #155724; }
        .badge-used         { background: #fff3cd; color: #856404; }
        .badge-working      { background: #d1ecf1; color: #0c5460; }
        .badge-not-working  { background: #f8d7da; color: #721c24; }
        .badge-for-checking { background: #e2e3e5; color: #383d41; }
        .badge-pending      { background: #fff3cd; color: #856404; }
        .badge-confirmed    { background: #cfe2ff; color: #084298; }
        .badge-released     { background: #d4edda; color: #155724; }
        .badge-received     { background: #d1f2eb; color: #0e6655; }
        .badge-cancelled    { background: #f8d7da; color: #721c24; }
        .badge-returned     { background: #d1ecf1; color: #0c5460; }

        /* ── Section heading (summary) ── */
        .section-heading { font-size: 1rem; font-weight: 700; color: var(--text); margin: 2rem 0 1rem; padding-bottom: .5rem; border-bottom: 2px solid var(--border); display: flex; align-items: center; gap: .5rem; }

        /* ── Scoped notice badge ── */
        .scope-notice { display: inline-flex; align-items: center; gap: .5rem; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; border-radius: 8px; padding: .5rem 1rem; font-size: .82rem; font-weight: 600; margin-bottom: 1.5rem; }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--muted); }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: .25; display: block; }
        .empty-state h3 { font-size: 1rem; font-weight: 700; }

        /* ══════════════════════════════════════════
           PRINT
        ══════════════════════════════════════════ */
        @media print {
            .sidebar, .page-header, .report-selector,
            .filters-section, .filter-actions, .btn, .scope-notice { display: none !important; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body { background: white !important; font-family: Arial, sans-serif !important; font-size: 10pt !important; color: #000 !important; }
            .main-content { left: 0 !important; width: 100% !important; padding: 0 !important; }
            .report-preview { box-shadow: none !important; border-radius: 0 !important; display: block !important; }
            .report-header { background: white !important; border-bottom: 3px solid #2c3e50 !important; padding: 1rem 1.5rem .75rem !important; text-align: left !important; }
            .report-header h2 { font-size: 14pt !important; font-weight: bold; color: #2c3e50 !important; margin: 0 0 .25rem !important; text-transform: uppercase; }
            .report-meta { font-size: 8pt !important; color: #555 !important; display: flex !important; gap: 2rem; margin-top: .4rem !important; justify-content: flex-start !important; }
            .report-meta i { display: none; }
            .summary-cards { display: flex !important; flex-direction: row !important; gap: 0 !important; border: 1.5px solid #2c3e50; overflow: hidden; margin-bottom: 1rem; border-radius: 0 !important; }
            .summary-card { flex: 1; border-left: none !important; border-radius: 0 !important; padding: .5rem .75rem !important; background: #f5f6fa !important; border-right: 1px solid #ccc; text-align: center; }
            .summary-card:last-child { border-right: none; }
            .summary-card h4 { font-size: 6.5pt !important; color: #555 !important; margin-bottom: .15rem !important; }
            .summary-card .value { font-size: 13pt !important; font-weight: bold !important; color: #2c3e50 !important; }
            .table-wrap { overflow: visible !important; }
            table { width: 100% !important; font-size: 7pt !important; margin-top: .25rem; page-break-inside: auto; }
            thead { background: #2c3e50 !important; color: white !important; }
            thead th { padding: .35rem .4rem !important; font-size: 6.5pt !important; border: 1px solid #1a252f !important; color: #fff !important; white-space: nowrap; }
            tbody tr { border-bottom: 1px solid #ddd !important; page-break-inside: avoid; }
            tbody tr:nth-child(even) { background: #f8f9fa !important; }
            tbody td { padding: .3rem .4rem !important; border: 1px solid #ddd !important; word-break: break-word; max-width: 120px; }
            .badge { background: transparent !important; font-weight: bold; font-size: 7.5pt !important; padding: 0 !important; border: none !important; border-radius: 0 !important; }
            .badge-new { color: #155724 !important; } .badge-used { color: #856404 !important; }
            .badge-working { color: #0c5460 !important; } .badge-not-working { color: #c0392b !important; }
            .badge-for-checking { color: #6c757d !important; } .badge-pending { color: #856404 !important; }
            .badge-released { color: #155724 !important; } .badge-received { color: #0e6655 !important; }
            .badge-confirmed { color: #084298 !important; } .badge-returned { color: #0c5460 !important; }
            .badge-cancelled { color: #c0392b !important; }
            .section-heading { font-size: 10pt !important; font-weight: bold; color: #2c3e50 !important; border-bottom: 1.5px solid #2c3e50; padding-bottom: .2rem; margin: 1rem 0 .5rem !important; }
            .report-body { padding: .75rem 1.5rem 1.5rem !important; }
            @page { size: A4 landscape; margin: 1cm 1.2cm 1.5cm 1.2cm; }
        }

        /* ══════════════════════════════════════════
           RESPONSIVE
        ══════════════════════════════════════════ */
        @media(max-width:1024px){
            .main-content{left:88px;width:calc(100% - 88px);padding:1.5rem;}
            .sidebar.close ~ .main-content{left:88px;width:calc(100% - 88px);}
        }
        @media(max-width:768px){
            .main-content{left:0!important;width:100%!important;padding:1rem;}
            .page-header{padding:1.4rem 1.25rem;border-radius:14px;margin-bottom:1.25rem;}
            .page-header h1{font-size:1.35rem;}
            .report-types{grid-template-columns:1fr;gap:.75rem;}
            .filter-actions{flex-direction:column;}
            .filter-actions .btn{width:100%;justify-content:center;}
            .summary-cards{grid-template-columns:1fr 1fr;}
            .report-meta{gap:1rem;justify-content:flex-start;}
        }
        @media(max-width:480px){
            .main-content{padding:.75rem;}
            .report-selector,.filters-section{padding:1.1rem;}
            .summary-cards{grid-template-columns:1fr 1fr;gap:.6rem;}
            .summary-card{padding:1rem;}
            .summary-card .value{font-size:1.5rem;}
            .report-body{padding:1.25rem;}
        }
    </style>
</head>
<body>

<?php include 'warehouse_sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1><i class='bx bx-file'></i> Reports</h1>
        <p>Printable asset and transaction reports for your warehouse</p>
        <?php if ($warehouseLocation): ?>
            <div class="location-pill"><i class='bx bxs-warehouse'></i> <?= htmlspecialchars($warehouseLocation) ?></div>
        <?php endif; ?>
    </div>

    <!-- Report Type Selector -->
    <div class="report-selector">
        <h3><i class='bx bx-list-ul'></i> Select Report Type</h3>
        <div class="report-types">
            <div class="report-type-card active" data-report="assets" onclick="selectReportType('assets')">
                <i class='bx bx-package'></i>
                <h3>Assets Inventory</h3>
                <p>Assets stored in your warehouse</p>
            </div>
            <div class="report-type-card" data-report="pullout" onclick="selectReportType('pullout')">
                <i class='bx bx-transfer'></i>
                <h3>Pull-Out Transactions</h3>
                <p>Transfers involving your warehouse</p>
            </div>
            <div class="report-type-card" data-report="summary" onclick="selectReportType('summary')">
                <i class='bx bx-bar-chart'></i>
                <h3>Summary Report</h3>
                <p>Statistical overview of your warehouse</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <h3><i class='bx bx-filter'></i> Filters</h3>
        <div class="filters-grid" id="filtersGrid"></div>
        <div class="filter-actions">
            <button class="btn btn-secondary" onclick="resetFilters()"><i class='bx bx-reset'></i> Reset</button>
            <button class="btn btn-primary" onclick="generateReport()"><i class='bx bx-show'></i> Generate Report</button>
            <button class="btn btn-success" id="printBtn" style="display:none;" onclick="window.print()"><i class='bx bx-printer'></i> Print Report</button>
        </div>
    </div>

    <!-- Report Preview -->
    <div class="report-preview" id="reportPreview">
        <div class="report-header">
            <h2 id="reportTitle">—</h2>
            <div class="report-meta">
                <span><i class='bx bx-map-pin'></i> <strong>Warehouse:</strong> <?= htmlspecialchars($warehouseLocation) ?></span>
                <span><i class='bx bx-calendar'></i> <strong>Generated:</strong> <span id="reportDate"></span></span>
                <span><i class='bx bx-user'></i> <strong>By:</strong> <?= htmlspecialchars($userName) ?></span>
            </div>
        </div>
        <div class="report-body" id="reportBody"></div>
    </div>

</div>

<script>
const WAREHOUSE_LOCATION = <?= json_encode($warehouseLocation) ?>;
let currentReportType = 'assets';

function selectReportType(type) {
    currentReportType = type;
    document.querySelectorAll('.report-type-card').forEach(c => c.classList.remove('active'));
    document.querySelector(`[data-report="${type}"]`).classList.add('active');
    loadFilters(type);
    document.getElementById('reportPreview').classList.remove('active');
    document.getElementById('printBtn').style.display = 'none';
}

function loadFilters(type) {
    const grid = document.getElementById('filtersGrid');
    if (type === 'assets') {
        grid.innerHTML = `
            <div class="filter-group">
                <label>Category</label>
                <select id="filterCategory">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select id="filterStatus">
                    <option value="">All Status</option>
                    <option value="WORKING">Working</option>
                    <option value="NOT WORKING">Not Working</option>
                    <option value="FOR CHECKING">For Checking</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Condition</label>
                <select id="filterCondition">
                    <option value="">All Conditions</option>
                    <option value="NEW">New</option>
                    <option value="USED">Used</option>
                </select>
            </div>
        `;
        loadCategories();
    } else if (type === 'pullout') {
        grid.innerHTML = `
            <div class="filter-group">
                <label>Transaction Type</label>
                <select id="filterTxnType">
                    <option value="">All Transactions</option>
                    <option value="outgoing">Outgoing (From My Warehouse)</option>
                    <option value="incoming">Incoming (To My Warehouse)</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select id="filterStatus">
                    <option value="">All Status</option>
                    <option value="PENDING">Pending</option>
                    <option value="CONFIRMED">Confirmed</option>
                    <option value="RELEASED">Released</option>
                    <option value="RECEIVED">Received</option>
                    <option value="CANCELLED">Cancelled</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" id="filterDateFrom">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" id="filterDateTo">
            </div>
        `;
    } else if (type === 'summary') {
        grid.innerHTML = `
            <div class="filter-group">
                <label>Period</label>
                <select id="filterPeriod">
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                </select>
            </div>
        `;
    }
}

async function loadCategories() {
    try {
        const r = await fetch('../inventory_api.php?action=get_categories');
        const d = await r.json();
        if (d.success) {
            const sel = document.getElementById('filterCategory');
            d.data.forEach(cat => {
                const o = document.createElement('option');
                o.value = cat.id; o.textContent = cat.name;
                sel.appendChild(o);
            });
        }
    } catch(e) { console.error(e); }
}

async function generateReport() {
    const btn = document.querySelector('.btn-primary');
    btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Generating...";
    btn.disabled = true;

    try {
        let params = new URLSearchParams({ action: 'generate_report', type: currentReportType });

        if (currentReportType === 'assets') {
            const cat  = document.getElementById('filterCategory')?.value  || '';
            const st   = document.getElementById('filterStatus')?.value    || '';
            const cond = document.getElementById('filterCondition')?.value || '';
            if (cat)  params.append('category', cat);
            if (st)   params.append('status', st);
            if (cond) params.append('condition', cond);
        } else if (currentReportType === 'pullout') {
            const txnType  = document.getElementById('filterTxnType')?.value  || '';
            const st       = document.getElementById('filterStatus')?.value    || '';
            const dateFrom = document.getElementById('filterDateFrom')?.value  || '';
            const dateTo   = document.getElementById('filterDateTo')?.value    || '';
            if (txnType)  params.append('txn_type', txnType);
            if (st)       params.append('status', st);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo)   params.append('date_to', dateTo);
        } else if (currentReportType === 'summary') {
            params.append('period', document.getElementById('filterPeriod')?.value || 'all');
        }

        const r = await fetch(`warehouse-reports-api.php?${params}`);
        const d = await r.json();

        if (d.success) {
            displayReport(d);
        } else {
            showToast(d.error || 'Failed to generate report', 'error');
        }
    } catch(e) {
        showToast('Connection error', 'error');
    } finally {
        btn.innerHTML = "<i class='bx bx-show'></i> Generate Report";
        btn.disabled = false;
    }
}

function displayReport(result) {
    const titles = { assets: 'Asset Inventory Report', pullout: 'Pull-Out Transactions Report', summary: 'Summary Report' };
    document.getElementById('reportTitle').textContent = titles[currentReportType];
    document.getElementById('reportDate').textContent  = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });

    if (currentReportType === 'assets')  document.getElementById('reportBody').innerHTML = renderAssets(result.data, result.summary);
    if (currentReportType === 'pullout') document.getElementById('reportBody').innerHTML = renderPullout(result.data, result.summary);
    if (currentReportType === 'summary') document.getElementById('reportBody').innerHTML = renderSummary(result.data);

    document.getElementById('reportPreview').classList.add('active');
    document.getElementById('printBtn').style.display = 'inline-flex';
    document.getElementById('reportPreview').scrollIntoView({ behavior: 'smooth' });
}

function renderAssets(data, summary) {
    if (!data?.length) return emptyState('bx-package', 'No assets found in your warehouse');
    return `
        <div class="summary-cards">
            <div class="summary-card"><h4>Total Assets</h4><div class="value">${summary.total}</div></div>
            <div class="summary-card"><h4>Working</h4><div class="value" style="color:var(--success)">${summary.working}</div></div>
            <div class="summary-card"><h4>For Checking</h4><div class="value" style="color:var(--warning)">${summary.for_checking}</div></div>
            <div class="summary-card"><h4>Not Working</h4><div class="value" style="color:var(--danger)">${summary.not_working}</div></div>
        </div>
        <div class="table-wrap">
        <table>
            <thead><tr>
                <th>#</th><th>Category</th><th>Type</th><th>Brand</th><th>Model</th>
                <th>Serial No.</th><th>Condition</th><th>Status</th><th>Sub-Location</th><th>Qty</th>
            </tr></thead>
            <tbody>
                ${data.map(a => `<tr>
                    <td style="color:#94a3b8;font-size:.78rem;">#${a.id}</td>
                    <td>${esc(a.category||'N/A')}</td>
                    <td>${esc(a.asset_type||'N/A')}</td>
                    <td style="font-weight:600;">${esc(a.brand||'N/A')}</td>
                    <td>${esc(a.model||'N/A')}</td>
                    <td><code style="font-size:.78rem;background:#f1f2f6;padding:1px 5px;border-radius:3px;">${esc(a.serial_number||'N/A')}</code></td>
                    <td><span class="badge badge-${(a.condition||'').toLowerCase()}">${a.condition||'N/A'}</span></td>
                    <td><span class="badge badge-${(a.status||'').toLowerCase().replace(/ /g,'-')}">${a.status||'N/A'}</span></td>
                    <td>${esc(a.sub_location||'—')}</td>
                    <td style="font-weight:700;text-align:center;">${a.active_count??0}</td>
                </tr>`).join('')}
            </tbody>
        </table>
        </div>`;
}

function renderPullout(data, summary) {
    if (!data?.length) return emptyState('bx-transfer', 'No transactions found for your warehouse');
    return `
        <div class="summary-cards">
            <div class="summary-card"><h4>Total</h4><div class="value">${summary.total}</div></div>
            <div class="summary-card"><h4>Pending</h4><div class="value" style="color:var(--warning)">${summary.pending}</div></div>
            <div class="summary-card"><h4>Confirmed</h4><div class="value" style="color:var(--info)">${summary.confirmed}</div></div>
            <div class="summary-card"><h4>Released</h4><div class="value" style="color:var(--success)">${summary.released}</div></div>
            <div class="summary-card"><h4>Received</h4><div class="value" style="color:#16a085">${summary.received}</div></div>
        </div>
        <div class="table-wrap">
        <table>
            <thead><tr>
                <th>#</th><th>Asset</th><th>Qty</th><th>Direction</th>
                <th>From</th><th>To</th><th>Requested By</th>
                <th>Released By</th><th>Delivered By</th><th>Status</th><th>Date</th>
            </tr></thead>
            <tbody>
                ${data.map(t => {
                    const isOut = (t.from_location||'').toLowerCase().includes(WAREHOUSE_LOCATION.toLowerCase());
                    const dir   = isOut
                        ? '<span style="background:#fef3cd;color:#92400e;padding:2px 8px;border-radius:4px;font-size:.72rem;font-weight:700;">📦 Outgoing</span>'
                        : '<span style="background:#d1f2eb;color:#0e6655;padding:2px 8px;border-radius:4px;font-size:.72rem;font-weight:700;">🚚 Incoming</span>';
                    return `<tr>
                        <td style="color:#94a3b8;font-size:.78rem;">#${t.id}</td>
                        <td style="font-weight:600;">${esc(t.asset_name||'N/A')}</td>
                        <td style="text-align:center;font-weight:700;">${t.quantity}</td>
                        <td>${dir}</td>
                        <td>${esc(t.from_location||'—')}</td>
                        <td>${esc(t.to_location||'—')}</td>
                        <td>${esc(t.requested_by||'—')}</td>
                        <td>${esc(t.released_by||'—')}</td>
                        <td>${esc(t.delivered_by||'—')}</td>
                        <td><span class="badge badge-${(t.status||'').toLowerCase()}">${t.status||'N/A'}</span></td>
                        <td style="white-space:nowrap;font-size:.8rem;">${fmtDate(t.created_at)}</td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>
        </div>`;
}

function renderSummary(data) {
    return `
        <div class="summary-cards">
            <div class="summary-card"><h4>Assets in Warehouse</h4><div class="value">${data.total_assets}</div></div>
            <div class="summary-card"><h4>Working</h4><div class="value" style="color:var(--success)">${data.working}</div></div>
            <div class="summary-card"><h4>For Checking</h4><div class="value" style="color:var(--warning)">${data.for_checking}</div></div>
            <div class="summary-card"><h4>Not Working</h4><div class="value" style="color:var(--danger)">${data.not_working}</div></div>
            <div class="summary-card"><h4>Outgoing (Released)</h4><div class="value" style="color:#f39c12">${data.outgoing}</div></div>
            <div class="summary-card"><h4>Incoming (Received)</h4><div class="value" style="color:#16a085">${data.incoming}</div></div>
        </div>

        <div class="section-heading"><i class='bx bx-list-ul'></i> Assets by Category</div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Category</th><th>Total</th><th>Working</th><th>For Checking</th><th>Not Working</th></tr></thead>
            <tbody>
                ${(data.by_category||[]).map(c => `<tr>
                    <td style="font-weight:700;">${esc(c.category||'Uncategorized')}</td>
                    <td>${c.total}</td>
                    <td><span class="badge badge-working">${c.working}</span></td>
                    <td><span class="badge badge-for-checking">${c.for_checking}</span></td>
                    <td><span class="badge badge-not-working">${c.not_working}</span></td>
                </tr>`).join('')}
            </tbody>
        </table>
        </div>

        <div class="section-heading"><i class='bx bx-transfer'></i> Recent Pull-Out Activity</div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Asset</th><th>Direction</th><th>From</th><th>To</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                ${(data.recent_txns||[]).map(t => {
                    const isOut = (t.from_location||'').toLowerCase().includes(WAREHOUSE_LOCATION.toLowerCase());
                    const dir = isOut ? '📦 Outgoing' : '🚚 Incoming';
                    return `<tr>
                        <td style="color:#94a3b8;">#${t.id}</td>
                        <td style="font-weight:600;">${esc(t.asset_name||'N/A')}</td>
                        <td style="font-size:.8rem;">${dir}</td>
                        <td>${esc(t.from_location||'—')}</td>
                        <td>${esc(t.to_location||'—')}</td>
                        <td><span class="badge badge-${(t.status||'').toLowerCase()}">${t.status}</span></td>
                        <td style="font-size:.8rem;">${fmtDate(t.created_at)}</td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>
        </div>`;
}

function emptyState(icon, msg) {
    return `<div class="empty-state"><i class='bx ${icon}'></i><h3>${msg}</h3></div>`;
}

function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function resetFilters() {
    document.querySelectorAll('.filters-grid select, .filters-grid input').forEach(el => {
        if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = '';
    });
}

function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;top:20px;right:20px;padding:1rem 1.5rem;background:${type==='success'?'#27ae60':'#d63031'};color:white;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.2);z-index:10000;font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:.9rem;`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

// Init
document.addEventListener('DOMContentLoaded', () => loadFilters('assets'));
</script>

</body>
</html>
<?php ob_end_flush(); ?>