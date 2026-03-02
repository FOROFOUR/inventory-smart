<?php
ob_start();
include 'sidebar.php';
require_once 'config.php';
$conn = getDBConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #6c5ce7;
            --primary-dark: #5f4dd1;
            --secondary: #00b894;
            --danger: #d63031;
            --warning: #f39c12;
            --info: #3498db;
            --success: #27ae60;
            --bg-main: #f8f9fa;
            --bg-card: #ffffff;
            --text-primary: #2d3436;
            --text-secondary: #636e72;
            --border: #dfe6e9;
            --shadow: rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
        }

        .content {
            margin-left: 88px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .sidebar:not(.close) ~ .content {
            margin-left: 260px;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: 0 8px 24px rgba(108, 92, 231, 0.3);
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            font-size: 2.5rem;
        }

        .page-header p {
            opacity: 0.95;
            font-size: 1rem;
        }

        .report-selector {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px var(--shadow);
        }

        .report-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .report-type-card {
            padding: 1.5rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .report-type-card:hover {
            border-color: var(--primary);
            background: rgba(108, 92, 231, 0.05);
        }

        .report-type-card.active {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }

        .report-type-card i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            display: block;
        }

        .report-type-card h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .report-type-card p {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px var(--shadow);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 92, 231, 0.3);
        }

        .btn-secondary {
            background: var(--bg-main);
            color: var(--text-primary);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            border-color: var(--primary);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #229954;
        }

        .report-preview {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px var(--shadow);
            overflow: hidden;
            display: none;
        }

        .report-preview.active {
            display: block;
        }

        .report-header {
            padding: 2rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 2px solid var(--border);
            text-align: center;
        }

        .report-header h2 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .report-meta {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .report-body {
            padding: 2rem;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--bg-main) 0%, white 100%);
            border-radius: 10px;
            border-left: 4px solid var(--primary);
        }

        .summary-card h4 {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #2d3436 0%, #34495e 100%);
            color: white;
        }

        thead th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
        }

        tbody tr:hover {
            background: var(--bg-main);
        }

        tbody td {
            padding: 1rem;
        }

        .badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-new { background: #d4edda; color: #155724; }
        .badge-used { background: #fff3cd; color: #856404; }
        .badge-working { background: #d1ecf1; color: #0c5460; }
        .badge-not-working { background: #f8d7da; color: #721c24; }
        .badge-for-checking { background: #e2e3e5; color: #383d41; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-released { background: #d4edda; color: #155724; }
        .badge-returned { background: #d1ecf1; color: #0c5460; }

        /* ──────────────────────────────────────
           PROFESSIONAL PRINT STYLES
        ────────────────────────────────────── */
        @media print {
            .sidebar,
            .page-header,
            .report-selector,
            .filters-section,
            .filter-actions,
            .btn {
                display: none !important;
            }

            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            body {
                background: white !important;
                font-family: Arial, sans-serif !important;
                font-size: 10pt !important;
                color: #000 !important;
            }

            .content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .report-preview {
                box-shadow: none !important;
                border-radius: 0 !important;
                display: block !important;
            }

            /* ── Professional Print Header ── */
            .report-header {
                background: white !important;
                border-bottom: 3px solid #2c3e50 !important;
                padding: 1rem 1.5rem 0.75rem !important;
                text-align: left !important;
                margin-bottom: 0.75rem;
            }

            .report-header h2 {
                font-size: 15pt !important;
                font-weight: bold;
                color: #2c3e50 !important;
                margin: 0 0 0.25rem !important;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .report-meta {
                font-size: 8pt !important;
                color: #555 !important;
                display: flex !important;
                gap: 2rem;
                margin-top: 0.4rem !important;
                justify-content: flex-start !important;
            }

            .report-meta i { display: none; }

            /* ── Summary Cards → Single horizontal bar ── */
            .summary-cards {
                display: flex !important;
                flex-direction: row !important;
                gap: 0 !important;
                border: 1.5px solid #2c3e50;
                border-radius: 0 !important;
                margin-bottom: 1rem;
                overflow: hidden;
            }

            .summary-card {
                flex: 1;
                border-left: none !important;
                border-radius: 0 !important;
                padding: 0.5rem 0.75rem !important;
                background: #f5f6fa !important;
                border-right: 1px solid #ccc;
                text-align: center;
            }

            .summary-card:last-child {
                border-right: none;
            }

            .summary-card h4 {
                font-size: 6.5pt !important;
                color: #555 !important;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 0.15rem !important;
            }

            .summary-card .value {
                font-size: 14pt !important;
                font-weight: bold !important;
                color: #2c3e50 !important;
            }

            /* ── Professional Table ── */
            table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 7pt !important;
                margin-top: 0.25rem;
                page-break-inside: auto;
            }

            thead {
                background: #2c3e50 !important;
                color: white !important;
            }

            thead th {
                padding: 0.35rem 0.4rem !important;
                font-size: 6.5pt !important;
                text-transform: uppercase;
                letter-spacing: 0.2px;
                border: 1px solid #1a252f !important;
                font-weight: 600;
                color: #fff !important;
                white-space: nowrap;
            }

            tbody tr {
                border-bottom: 1px solid #ddd !important;
                page-break-inside: avoid;
            }

            tbody tr:nth-child(even) {
                background: #f8f9fa !important;
            }

            tbody tr:hover {
                background: inherit !important;
            }

            tbody td {
                padding: 0.3rem 0.4rem !important;
                border: 1px solid #ddd !important;
                vertical-align: middle;
                word-break: break-word;
                max-width: 120px;
            }

            /* ── Badges → Plain bold text ── */
            .badge {
                background: transparent !important;
                font-weight: bold;
                font-size: 7.5pt !important;
                padding: 0 !important;
                border: none !important;
                border-radius: 0 !important;
            }

            .badge-new           { color: #155724 !important; }
            .badge-used          { color: #856404 !important; }
            .badge-working       { color: #0c5460 !important; }
            .badge-not-working   { color: #c0392b !important; }
            .badge-for-checking  { color: #6c757d !important; }
            .badge-pending       { color: #856404 !important; }
            .badge-released      { color: #155724 !important; }
            .badge-returned      { color: #0c5460 !important; }

            code {
                font-family: monospace;
                font-size: 8pt;
            }

            /* ── Section heading (Summary report) ── */
            .report-body h3 {
                font-size: 10pt !important;
                font-weight: bold;
                color: #2c3e50 !important;
                border-bottom: 1.5px solid #2c3e50;
                padding-bottom: 0.2rem;
                margin: 1rem 0 0.5rem !important;
            }

            .report-body {
                padding: 0.75rem 1.5rem 1.5rem !important;
            }

            @page {
                size: A4 landscape;
                margin: 1cm 1.2cm 1.5cm 1.2cm;
            }
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
    </style>
</head>
<body>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class='bx bx-file'></i>
            Reports
        </h1>
        <p>Generates filtered and printable reports of assets and transactions</p>
    </div>

    <!-- Report Type Selector -->
    <div class="report-selector">
        <h3 style="margin-bottom: 1.5rem; color: var(--text-primary);">Select Report Type</h3>
        <div class="report-types">
            <div class="report-type-card active" data-report="assets" onclick="selectReportType('assets')">
                <i class='bx bx-package'></i>
                <h3>Assets Inventory</h3>
                <p>Complete asset listing with status</p>
            </div>
            <div class="report-type-card" data-report="pullout" onclick="selectReportType('pullout')">
                <i class='bx bx-export'></i>
                <h3>Pull-Out Transactions</h3>
                <p>Asset pull-out history</p>
            </div>
            <div class="report-type-card" data-report="summary" onclick="selectReportType('summary')">
                <i class='bx bx-bar-chart'></i>
                <h3>Summary Report</h3>
                <p>Statistical overview</p>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <h3 style="margin-bottom: 1.5rem; color: var(--text-primary);">
            <i class='bx bx-filter'></i> Filters
        </h3>
        
        <div class="filters-grid" id="filtersGrid">
            <!-- Dynamic filters will load here -->
        </div>

        <div class="filter-actions">
            <button class="btn btn-secondary" onclick="resetFilters()">
                <i class='bx bx-reset'></i>
                Reset
            </button>
            <button class="btn btn-primary" onclick="generateReport()">
                <i class='bx bx-show'></i>
                Generate Report
            </button>
            <button class="btn btn-success" onclick="window.print()" id="printBtn" style="display: none;">
                <i class='bx bx-printer'></i>
                Print Report
            </button>
        </div>
    </div>

    <!-- Report Preview -->
    <div class="report-preview" id="reportPreview">
        <div class="report-header">
            <h2 id="reportTitle">Asset Inventory Report</h2>
            <div class="report-meta">
                <span><i class='bx bx-calendar'></i> <strong>Generated:</strong> <span id="reportDate"></span></span>
                <span><i class='bx bx-user'></i> <strong>By:</strong> <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
            </div>
        </div>

        <div class="report-body" id="reportBody">
            <!-- Report content will be loaded here -->
        </div>
    </div>
</div>

<script>
    let currentReportType = 'assets';
    let reportData = [];

    function selectReportType(type) {
        currentReportType = type;
        
        // Update active card
        document.querySelectorAll('.report-type-card').forEach(card => {
            card.classList.remove('active');
        });
        document.querySelector(`[data-report="${type}"]`).classList.add('active');

        // Load appropriate filters
        loadFilters(type);
        
        // Hide report preview
        document.getElementById('reportPreview').classList.remove('active');
        document.getElementById('printBtn').style.display = 'none';
    }

    function loadFilters(type) {
        const filtersGrid = document.getElementById('filtersGrid');
        
        let filtersHTML = '';
        
        if (type === 'assets') {
            filtersHTML = `
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
                <div class="filter-group">
                    <label>Location</label>
                    <select id="filterLocation">
                        <option value="">All Locations</option>
                    </select>
                </div>
            `;
        } else if (type === 'pullout') {
            filtersHTML = `
                <div class="filter-group">
                    <label>Status</label>
                    <select id="filterStatus">
                        <option value="">All Status</option>
                        <option value="PENDING">Pending</option>
                        <option value="RELEASED">Released</option>
                        <option value="RETURNED">Returned</option>
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
            filtersHTML = `
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
        
        filtersGrid.innerHTML = filtersHTML;
        
        // Load dynamic dropdowns if needed
        if (type === 'assets') {
            loadCategories();
            loadLocations();
        }
    }

    async function loadCategories() {
        try {
            const response = await fetch('inventory_api.php?action=get_categories');
            const result = await response.json();
            
            if (result.success) {
                const select = document.getElementById('filterCategory');
                result.data.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat.id;
                    option.textContent = cat.name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }

    async function loadLocations() {
        try {
            const response = await fetch('reports_api.php?action=get_locations');
            const result = await response.json();
            
            if (result.success) {
                const select = document.getElementById('filterLocation');
                result.data.forEach(loc => {
                    const option = document.createElement('option');
                    option.value = loc;
                    option.textContent = loc;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading locations:', error);
        }
    }

    async function generateReport() {
        try {
            let params = new URLSearchParams({
                action: 'generate_report',
                type: currentReportType
            });

            // Add filters based on report type
            if (currentReportType === 'assets') {
                const category = document.getElementById('filterCategory')?.value || '';
                const status   = document.getElementById('filterStatus')?.value || '';
                const condition = document.getElementById('filterCondition')?.value || '';
                const location = document.getElementById('filterLocation')?.value || '';
                
                if (category)  params.append('category', category);
                if (status)    params.append('status', status);
                if (condition) params.append('condition', condition);
                if (location)  params.append('location', location);

            } else if (currentReportType === 'pullout') {
                const status   = document.getElementById('filterStatus')?.value || '';
                const dateFrom = document.getElementById('filterDateFrom')?.value || '';
                const dateTo   = document.getElementById('filterDateTo')?.value || '';
                
                if (status)   params.append('status', status);
                if (dateFrom) params.append('date_from', dateFrom);
                if (dateTo)   params.append('date_to', dateTo);

            } else if (currentReportType === 'summary') {
                const period = document.getElementById('filterPeriod')?.value || 'all';
                params.append('period', period);
            }

            const response = await fetch(`reports_api.php?${params}`);
            const result = await response.json();

            if (result.success) {
                reportData = result.data;
                displayReport(result);
            } else {
                showNotification('Error generating report', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Failed to generate report', 'error');
        }
    }

    function displayReport(result) {
        const reportPreview = document.getElementById('reportPreview');
        const reportTitle   = document.getElementById('reportTitle');
        const reportDate    = document.getElementById('reportDate');
        const reportBody    = document.getElementById('reportBody');

        const titles = {
            'assets':  'Asset Inventory Report',
            'pullout': 'Pull-Out Transactions Report',
            'summary': 'Summary Report'
        };
        reportTitle.textContent = titles[currentReportType];

        reportDate.textContent = new Date().toLocaleDateString('en-US', {
            year: 'numeric', month: 'long', day: 'numeric'
        });

        if (currentReportType === 'assets') {
            reportBody.innerHTML = renderAssetsReport(result.data, result.summary);
        } else if (currentReportType === 'pullout') {
            reportBody.innerHTML = renderPulloutReport(result.data, result.summary);
        } else if (currentReportType === 'summary') {
            reportBody.innerHTML = renderSummaryReport(result.data);
        }

        reportPreview.classList.add('active');
        document.getElementById('printBtn').style.display = 'inline-flex';
        reportPreview.scrollIntoView({ behavior: 'smooth' });
    }

    function renderAssetsReport(data, summary) {
        if (!data || data.length === 0) {
            return '<div class="empty-state"><i class="bx bx-package"></i><h3>No assets found</h3></div>';
        }

        let html = `
            <div class="summary-cards">
                <div class="summary-card">
                    <h4>Total Assets</h4>
                    <div class="value">${summary.total}</div>
                </div>
                <div class="summary-card">
                    <h4>Working</h4>
                    <div class="value" style="color: var(--success)">${summary.working}</div>
                </div>
                <div class="summary-card">
                    <h4>For Checking</h4>
                    <div class="value" style="color: var(--warning)">${summary.for_checking}</div>
                </div>
                <div class="summary-card">
                    <h4>Not Working</h4>
                    <div class="value" style="color: var(--danger)">${summary.not_working}</div>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category</th>
                        <th>Asset Type</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Serial No</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Active Qty</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.forEach(item => {
            html += `
                <tr>
                    <td>#${item.id}</td>
                    <td>${item.category || 'N/A'}</td>
                    <td>${item.asset_type || 'N/A'}</td>
                    <td>${item.brand || 'N/A'}</td>
                    <td>${item.model || 'N/A'}</td>
                    <td><code>${item.serial_number || 'N/A'}</code></td>
                    <td><span class="badge badge-${(item.condition || '').toLowerCase()}">${item.condition || 'N/A'}</span></td>
                    <td><span class="badge badge-${(item.status || '').toLowerCase().replace(/ /g, '-')}">${item.status || 'N/A'}</span></td>
                    <td>${item.location || 'N/A'}${item.sub_location ? ' – ' + item.sub_location : ''}</td>
                    <td><strong>${item.active_count || 0}</strong></td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        return html;
    }

    function renderPulloutReport(data, summary) {
        if (!data || data.length === 0) {
            return '<div class="empty-state"><i class="bx bx-export"></i><h3>No pull-out transactions found</h3></div>';
        }

        let html = `
            <div class="summary-cards">
                <div class="summary-card">
                    <h4>Total Transactions</h4>
                    <div class="value">${summary.total}</div>
                </div>
                <div class="summary-card">
                    <h4>Pending</h4>
                    <div class="value" style="color: var(--warning)">${summary.pending}</div>
                </div>
                <div class="summary-card">
                    <h4>Released</h4>
                    <div class="value" style="color: var(--success)">${summary.released}</div>
                </div>
                <div class="summary-card">
                    <h4>Returned</h4>
                    <div class="value" style="color: var(--info)">${summary.returned}</div>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Asset</th>
                        <th>Quantity</th>
                        <th>Requested By</th>
                        <th>Purpose</th>
                        <th>Date Needed</th>
                        <th>Released By</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.forEach(item => {
            html += `
                <tr>
                    <td>#${item.id}</td>
                    <td>${item.asset_name || 'N/A'}</td>
                    <td>${item.quantity}</td>
                    <td>${item.requested_by}</td>
                    <td>${item.purpose ? (item.purpose.length > 30 ? item.purpose.substring(0, 30) + '…' : item.purpose) : 'N/A'}</td>
                    <td>${formatDate(item.date_needed)}</td>
                    <td>${item.released_by || '–'}</td>
                    <td><span class="badge badge-${item.status.toLowerCase()}">${item.status}</span></td>
                    <td>${formatDate(item.created_at)}</td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        return html;
    }

    function renderSummaryReport(data) {
        return `
            <div class="summary-cards">
                <div class="summary-card">
                    <h4>Total Assets</h4>
                    <div class="value">${data.total_assets}</div>
                </div>
                <div class="summary-card">
                    <h4>Active Items</h4>
                    <div class="value" style="color: var(--success)">${data.active_items}</div>
                </div>
                <div class="summary-card">
                    <h4>Pulled Out</h4>
                    <div class="value" style="color: var(--warning)">${data.pulled_out}</div>
                </div>
                <div class="summary-card">
                    <h4>Total Transactions</h4>
                    <div class="value" style="color: var(--info)">${data.total_transactions}</div>
                </div>
            </div>
            
            <h3 style="margin: 2rem 0 1rem; color: var(--text-primary);">Assets by Category</h3>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Total Assets</th>
                        <th>Active</th>
                        <th>Working</th>
                        <th>For Checking</th>
                        <th>Not Working</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.by_category.map(cat => `
                        <tr>
                            <td><strong>${cat.category}</strong></td>
                            <td>${cat.total}</td>
                            <td>${cat.active}</td>
                            <td><span class="badge badge-working">${cat.working}</span></td>
                            <td><span class="badge badge-for-checking">${cat.for_checking}</span></td>
                            <td><span class="badge badge-not-working">${cat.not_working}</span></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function resetFilters() {
        document.querySelectorAll('select, input').forEach(el => {
            if (el.tagName === 'SELECT') {
                el.selectedIndex = 0;
            } else {
                el.value = '';
            }
        });
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            background: ${type === 'success' ? '#27ae60' : '#d63031'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
        `;
        notification.textContent = message;
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 3000);
    }

    // Load default filters on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadFilters('assets');
    });
</script>

</body>
</html>
<?php ob_end_flush(); ?>