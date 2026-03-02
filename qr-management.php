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
    <title>Generate QR</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/style.css">
    <!-- QR Code Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #695CFE;
            --primary-dark: #3aa896;
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
            background: linear-gradient(135deg, #263dc7 0%, var(--primary-dark) 100%);
            border-radius: 20px 20px 0 0;
            padding: 3rem 2rem 2rem;
            color: white;
            box-shadow: 0 8px 24px rgba(72, 201, 176, 0.3);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        .controls-section {
            background: white;
            padding: 1.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: 0 2px 8px var(--shadow);
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(72, 201, 176, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.2rem;
        }

        .filter-group {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-filter {
            background: var(--bg-main);
            color: var(--text-primary);
            border: 2px solid var(--border);
        }

        .btn-filter:hover {
            border-color: var(--primary);
        }

        select.btn {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23636e72' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 3rem;
        }

        .table-container {
            background: white;
            box-shadow: 0 4px 12px var(--shadow);
            border-radius: 0 0 20px 20px;
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
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
            padding: 1.25rem 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: all 0.2s;
        }

        tbody tr:hover {
            background: var(--bg-main);
        }

        tbody td {
            padding: 1.25rem 1rem;
            white-space: nowrap;
        }

        .qr-cell {
            text-align: center;
        }

        .qr-preview {
            width: 80px;
            height: 80px;
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 4px;
            background: white;
        }

        .print-btn {
            padding: 0.5rem 1rem;
            background: #695CFE;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .print-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(72, 201, 176, 0.3);
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

        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .print-area, .print-area * {
                visibility: visible;
            }
            
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .qr-print-item {
                page-break-inside: avoid;
                margin-bottom: 20mm;
            }
        }

        .print-area {
            display: none;
        }

        .qr-print-item {
            text-align: center;
            padding: 20px;
            border: 2px solid #000;
            margin: 10px;
            display: inline-block;
        }

        .qr-print-item canvas {
            display: block;
            margin: 0 auto 10px;
        }

        .qr-print-item .asset-info {
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }

        .qr-print-item .qr-code-text {
            font-size: 12px;
            font-family: monospace;
            margin-top: 5px;
        }

        .bulk-actions {
            background: white;
            padding: 1rem 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            box-shadow: 0 2px 8px var(--shadow);
        }

        .bulk-actions label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .btn-primary {
            background:#695CFE;
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(72, 201, 176, 0.3);
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>Generate QR</h1>
        <p>Generates and prints QR codes for each asset for quick identification and tracking.</p>
    </div>

    <!-- Bulk Actions -->
    <div class="bulk-actions">
        <label>
            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
            Select All
        </label>
        <button class="btn btn-primary" onclick="printSelectedQR()">
            <i class='bx bx-printer'></i>
            Print Selected
        </button>
    </div>

    <!-- Controls -->
    <div class="controls-section">
        <div class="search-box">
            <i class='bx bx-search'></i>
            <input type="text" id="searchInput" placeholder="Search by asset, brand, model, or QR code...">
        </div>
        <div class="filter-group">
            <select id="categoryFilter" class="btn btn-filter">
                <option value="">All Categories</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="table-container">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" style="display: none;">
                        </th>
                        <th>Asset</th>
                        <th>Category</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Serial No</th>
                        <th>QR Code</th>
                        <th>QR Preview</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="qrTableBody">
                    <!-- Data will be loaded here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Hidden Print Area -->
<div class="print-area" id="printArea"></div>

<script>
    let assetsData = [];
    let selectedAssets = new Set();

    async function loadAssets() {
        try {
            const search = document.getElementById('searchInput').value;
            const category = document.getElementById('categoryFilter').value;
            
            const params = new URLSearchParams({
                action: 'get_assets_for_qr',
                search: search,
                category: category
            });

            const response = await fetch(`qr_api.php?${params}`);
            const result = await response.json();

            if (result.success) {
                assetsData = result.data;
                renderTable();
            } else {
                showNotification('Error loading assets', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Failed to load assets', 'error');
        }
    }

    function renderTable() {
        const tbody = document.getElementById('qrTableBody');
        tbody.innerHTML = '';

        if (assetsData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class='bx bx-qr'></i>
                            <h3>No assets found</h3>
                            <p>Try adjusting your search or filters</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        assetsData.forEach(asset => {
            const row = document.createElement('tr');
            const qrId = `qr-${asset.id}`;
            
            row.innerHTML = `
                <td>
                    <input type="checkbox" class="asset-checkbox" value="${asset.id}" 
                           onchange="toggleAssetSelection(${asset.id})">
                </td>
                <td><strong>${asset.asset_type || 'N/A'}</strong></td>
                <td>${asset.category || 'N/A'}</td>
                <td>${asset.brand || 'N/A'}</td>
                <td>${asset.model || 'N/A'}</td>
                <td><code style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px;">${asset.serial_number || 'N/A'}</code></td>
                <td><code style="background: #e8f5f3; padding: 4px 8px; border-radius: 4px; color: var(--primary); font-weight: 600;">${asset.qr_code || 'N/A'}</code></td>
                <td class="qr-cell">
                    <canvas id="${qrId}" class="qr-preview"></canvas>
                </td>
                <td>
                    <button class="print-btn" onclick="printSingleQR(${asset.id})">
                        <i class='bx bx-printer'></i>
                        Print
                    </button>
                </td>
            `;
            
            tbody.appendChild(row);

            // Generate QR code
            if (asset.qr_code) {
                setTimeout(() => {
                    const canvas = document.getElementById(qrId);
                    if (canvas) {
                        QRCode.toCanvas(canvas, asset.qr_code, {
                            width: 80,
                            margin: 1,
                            color: {
                                dark: '#000000',
                                light: '#FFFFFF'
                            }
                        });
                    }
                }, 0);
            }
        });
    }

    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll').checked;
        const checkboxes = document.querySelectorAll('.asset-checkbox');
        
        checkboxes.forEach(cb => {
            cb.checked = selectAll;
            const assetId = parseInt(cb.value);
            if (selectAll) {
                selectedAssets.add(assetId);
            } else {
                selectedAssets.delete(assetId);
            }
        });
    }

    function toggleAssetSelection(assetId) {
        if (selectedAssets.has(assetId)) {
            selectedAssets.delete(assetId);
        } else {
            selectedAssets.add(assetId);
        }
        
        // Update select all checkbox
        const totalCheckboxes = document.querySelectorAll('.asset-checkbox').length;
        document.getElementById('selectAll').checked = selectedAssets.size === totalCheckboxes;
    }

    function printSingleQR(assetId) {
        const asset = assetsData.find(a => a.id === assetId);
        if (!asset) return;

        generatePrintContent([asset]);
        window.print();
    }

    function printSelectedQR() {
        if (selectedAssets.size === 0) {
            showNotification('Please select at least one asset', 'error');
            return;
        }

        const selectedData = assetsData.filter(a => selectedAssets.has(a.id));
        generatePrintContent(selectedData);
        window.print();
    }

    function generatePrintContent(assets) {
        const printArea = document.getElementById('printArea');
        printArea.innerHTML = '';
        printArea.style.display = 'block';

        assets.forEach(asset => {
            const printItem = document.createElement('div');
            printItem.className = 'qr-print-item';
            
            const canvas = document.createElement('canvas');
            canvas.id = `print-qr-${asset.id}`;
            
            const info = document.createElement('div');
            info.className = 'asset-info';
            info.innerHTML = `
                <div><strong>${asset.category || 'N/A'} - ${asset.asset_type || 'N/A'}</strong></div>
                <div>${asset.brand || ''} ${asset.model || ''}</div>
                <div class="qr-code-text">${asset.qr_code || 'N/A'}</div>
            `;
            
            printItem.appendChild(canvas);
            printItem.appendChild(info);
            printArea.appendChild(printItem);

            // Generate QR for print
            if (asset.qr_code) {
                QRCode.toCanvas(canvas, asset.qr_code, {
                    width: 200,
                    margin: 2
                });
            }
        });
    }

    async function loadCategories() {
        try {
            const response = await fetch('inventory_api.php?action=get_categories');
            const result = await response.json();
            
            if (result.success) {
                const select = document.getElementById('categoryFilter');
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
            animation: slideIn 0.3s ease;
        `;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => notification.remove(), 3000);
    }

    // Search
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadAssets, 500);
    });

    // Category filter
    document.getElementById('categoryFilter').addEventListener('change', loadAssets);

    // After print, hide print area
    window.addEventListener('afterprint', function() {
        document.getElementById('printArea').style.display = 'none';
    });

    // Load on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadCategories();
        loadAssets();
    });
</script>

</body>
</html>
<?php ob_end_flush(); ?>