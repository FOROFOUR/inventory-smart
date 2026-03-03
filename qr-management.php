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
    <title>Generate QR — Inventory Smart</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/style.css">
    <!-- QRCode library — stored locally, no CDN needed -->
  
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary:      #695CFE;
            --primary-dark: #3aa896;
            --bg-main:      #f8f9fa;
            --bg-card:      #ffffff;
            --text-primary: #2d3436;
            --text-secondary:#636e72;
            --border:       #dfe6e9;
            --shadow:       rgba(0,0,0,0.08);
        }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
        }

        /* ── Layout ── */
        .content {
            margin-left: 88px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }
        .sidebar:not(.close) ~ .content { margin-left: 260px; }

        /* ── Page Header ── */
        .page-header {
            background: linear-gradient(135deg, #263dc7 0%, var(--primary-dark) 100%);
            border-radius: 20px 20px 0 0;
            padding: 3rem 2rem 2rem;
            color: white;
            box-shadow: 0 8px 24px rgba(72,201,176,0.3);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 400px; height: 400px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .page-header h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; position: relative; z-index: 1; }
        .page-header p  { font-size: 1.1rem; opacity: 0.95; position: relative; z-index: 1; }

        /* ── Network badge ── */
        .network-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-top: 0.75rem;
            position: relative;
            z-index: 1;
        }
        .network-badge .dot {
            width: 7px; height: 7px;
            background: #4ade80;
            border-radius: 50%;
            animation: pulse 1.8s infinite;
        }

        /* ── Bulk Actions ── */
        .bulk-actions {
            background: white;
            padding: 1rem 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            box-shadow: 0 2px 8px var(--shadow);
            flex-wrap: wrap;
        }
        .bulk-actions label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            cursor: pointer;
        }
        .selected-count {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-left: auto;
        }

        /* ── Controls ── */
        .controls-section {
            background: white;
            padding: 1.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: 0 2px 8px var(--shadow);
            border-top: 1px solid var(--border);
        }
        .search-box { flex: 1; min-width: 250px; position: relative; }
        .search-box input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        .search-box input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(105,92,254,0.1); }
        .search-box i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 1.2rem; }

        /* ── Buttons ── */
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
        .btn-primary  { background: var(--primary); color: white; }
        .btn-primary:hover { background: #5448e0; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(105,92,254,0.3); }
        .btn-outline  { background: transparent; color: var(--primary); border: 2px solid var(--primary); }
        .btn-outline:hover { background: var(--primary); color: white; }
        .btn-filter   { background: var(--bg-main); color: var(--text-primary); border: 2px solid var(--border); }
        .btn-filter:hover { border-color: var(--primary); }
        select.btn {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23636e72' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 3rem;
            cursor: pointer;
        }

        /* ── Table ── */
        .table-container {
            background: white;
            box-shadow: 0 4px 12px var(--shadow);
            border-radius: 0 0 20px 20px;
            overflow: hidden;
        }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: linear-gradient(135deg, #2d3436 0%, #34495e 100%); color: white; }
        thead th { padding: 1.25rem 1rem; text-align: left; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid var(--border); transition: background 0.2s; }
        tbody tr:hover { background: #f5f3ff; }
        tbody td { padding: 1rem 1rem; white-space: nowrap; vertical-align: middle; }

        .qr-cell { text-align: center; }
        .qr-preview {
            width: 80px; height: 80px;
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 3px;
            background: white;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .qr-preview:hover { transform: scale(1.1); box-shadow: 0 4px 12px rgba(105,92,254,0.2); }

        .print-btn {
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .print-btn:hover { background: #5448e0; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(105,92,254,0.3); }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.65rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-working     { background: #dcfce7; color: #15803d; }
        .status-checking    { background: #fef9c3; color: #a16207; }
        .status-notworking  { background: #fee2e2; color: #b91c1c; }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* URL chip */
        .url-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #ede9fe;
            color: var(--primary);
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Empty state */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-secondary); }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.3; display: block; }

        /* Loading skeleton */
        .skeleton-row td { padding: 1.25rem 1rem; }
        .skeleton-box {
            height: 16px;
            border-radius: 6px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }

        /* ── QR Modal ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px);
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            max-width: 340px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            animation: popIn 0.25s cubic-bezier(0.34,1.56,0.64,1);
        }
        .modal-box h3 { font-size: 1rem; margin-bottom: 0.25rem; }
        .modal-box p  { font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1rem; }
        .modal-box canvas { margin: 0 auto 1rem; border: 2px solid var(--border); border-radius: 10px; padding: 8px; }
        .modal-url {
            background: #f5f3ff;
            border: 1px solid #ede9fe;
            border-radius: 8px;
            padding: 0.6rem 0.75rem;
            font-size: 0.72rem;
            color: var(--primary);
            font-family: monospace;
            word-break: break-all;
            margin-bottom: 1rem;
        }
        .modal-close {
            position: absolute;
            top: 1rem; right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
        }
        .modal-box { position: relative; }

        /* ── Print Styles ── */
        @media print {
            body * { visibility: hidden; }
            .print-area, .print-area * { visibility: visible; }
            .print-area { position: absolute; left: 0; top: 0; width: 100%; }
            .qr-print-item { page-break-inside: avoid; }
        }
        .print-area { display: none; }
        .qr-print-item {
            text-align: center;
            padding: 16px;
            border: 2px solid #000;
            margin: 8px;
            display: inline-block;
            border-radius: 8px;
        }
        .qr-print-item canvas { display: block; margin: 0 auto 8px; }
        .qr-print-item .asset-info { font-size: 13px; font-weight: 700; }
        .qr-print-item .asset-sub  { font-size: 11px; color: #555; margin-top: 3px; }
        .qr-print-item .qr-code-text { font-size: 10px; font-family: monospace; margin-top: 4px; color: #777; }

        /* ── Animations ── */
        @keyframes pulse   { 0%,100%{opacity:1} 50%{opacity:0.4} }
        @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
        @keyframes popIn   { from{opacity:0;transform:scale(0.85)} to{opacity:1;transform:scale(1)} }

        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 1rem; }
        }
    </style>
</head>
<body>

<div class="content">

    <!-- Page Header -->
    <div class="page-header">
        <h1><i class='bx bx-qr' style="vertical-align:middle;margin-right:0.5rem;"></i>Generate QR</h1>
        <p>Scan any QR code to instantly view asset details on your phone or tablet.</p>
        <div class="network-badge">
            <span class="dot"></span>
            LAN: 10.20.80.43 &nbsp;•&nbsp; Same Wi-Fi required to scan
        </div>
    </div>

    <!-- Bulk Actions -->
    <div class="bulk-actions">
        <label>
            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
            Select All
        </label>
        <button class="btn btn-primary" onclick="printSelectedQR()">
            <i class='bx bx-printer'></i> Print Selected
        </button>
        <span class="selected-count" id="selectedCount">0 selected</span>
    </div>

    <!-- Controls -->
    <div class="controls-section">
        <div class="search-box">
            <i class='bx bx-search'></i>
            <input type="text" id="searchInput" placeholder="Search by asset, brand, model...">
        </div>
        <select id="categoryFilter" class="btn btn-filter">
            <option value="">All Categories</option>
        </select>
    </div>

    <!-- Table -->
    <div class="table-container">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th>Asset</th>
                        <th>Category</th>
                        <th>Brand / Model</th>
                        <th>Serial No</th>
                        <th>Status</th>
                        <th>QR URL</th>
                        <th>QR Preview</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="qrTableBody">
                    <!-- skeleton -->
                    <?php for($s=0;$s<5;$s++): ?>
                    <tr class="skeleton-row">
                        <td><div class="skeleton-box" style="width:18px;height:18px;border-radius:4px;"></div></td>
                        <td><div class="skeleton-box" style="width:110px;"></div></td>
                        <td><div class="skeleton-box" style="width:90px;"></div></td>
                        <td><div class="skeleton-box" style="width:130px;"></div></td>
                        <td><div class="skeleton-box" style="width:100px;"></div></td>
                        <td><div class="skeleton-box" style="width:80px;height:22px;border-radius:999px;"></div></td>
                        <td><div class="skeleton-box" style="width:160px;height:22px;border-radius:6px;"></div></td>
                        <td><div class="skeleton-box" style="width:80px;height:80px;border-radius:8px;"></div></td>
                        <td><div class="skeleton-box" style="width:70px;height:32px;border-radius:8px;"></div></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- QR Modal -->
<div class="modal-overlay" id="qrModal" onclick="closeModal(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('qrModal').classList.remove('active')">
            <i class='bx bx-x'></i>
        </button>
        <h3 id="modalTitle">Asset QR Code</h3>
        <p id="modalSub">Scan to view asset details</p>
        <canvas id="modalCanvas"></canvas>
        <div class="modal-url" id="modalUrl"></div>
        <button class="btn btn-primary" style="width:100%;justify-content:center;" onclick="printModalQR()">
            <i class='bx bx-printer'></i> Print This QR
        </button>
    </div>
</div>

<!-- Hidden Print Area -->
<div class="print-area" id="printArea"></div>

<script>
let assetsData  = [];
let selectedAssets = new Set();
let modalAsset  = null;

// ── Status helper ─────────────────────────────────────────────────────────
function statusBadge(status) {
    const map = {
        'WORKING':      ['status-working',    '✓'],
        'FOR CHECKING': ['status-checking',   '⚠'],
        'NOT WORKING':  ['status-notworking', '✕'],
    };
    const [cls, icon] = map[status] || ['', ''];
    return `<span class="status-badge ${cls}"><span class="status-dot"></span>${status}</span>`;
}

// ── Load Assets ───────────────────────────────────────────────────────────
async function loadAssets() {
    const search   = document.getElementById('searchInput').value;
    const category = document.getElementById('categoryFilter').value;

    const params = new URLSearchParams({ action:'get_assets_for_qr', search, category });

    try {
        const res    = await fetch(`qr_api.php?${params}`);
        const result = await res.json();
        if (result.success) {
            assetsData = result.data;
            renderTable();
        } else {
            showNotification('Error loading assets', 'error');
        }
    } catch (err) {
        showNotification('Failed to load assets', 'error');
    }
}

// ── Render Table ──────────────────────────────────────────────────────────
function renderTable() {
    const tbody = document.getElementById('qrTableBody');
    tbody.innerHTML = '';

    if (assetsData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9">
            <div class="empty-state">
                <i class='bx bx-qr'></i>
                <h3>No assets found</h3>
                <p>Try adjusting your search or filters</p>
            </div></td></tr>`;
        return;
    }

    assetsData.forEach(asset => {
        const qrId  = `qr-${asset.id}`;
        const url   = asset.qr_url;
        const label = asset.qr_label || ('ASSET-' + String(asset.id).padStart(5,'0'));
        const checked = selectedAssets.has(asset.id) ? 'checked' : '';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="checkbox" class="asset-checkbox" value="${asset.id}"
                    onchange="toggleAssetSelection(${asset.id})" ${checked}></td>
            <td><strong>${asset.asset_type || '—'}</strong></td>
            <td>${asset.category || '—'}</td>
            <td>${[asset.brand, asset.model].filter(Boolean).join(' ') || '—'}</td>
            <td><code style="background:#f0f0f0;padding:3px 7px;border-radius:5px;font-size:0.82rem;">${asset.serial_number || 'N/A'}</code></td>
            <td>${statusBadge(asset.status)}</td>
            <td>
                <span class="url-chip" title="${url}">
                    <i class='bx bx-link-alt'></i>${url}
                </span>
            </td>
           <td class="qr-cell">
    <img src="generate_qr.php?id=${asset.id}" 
         class="qr-preview"
         width="80"
         onclick="openModal(${asset.id})">
</td>
            <td>
                <button class="print-btn" onclick="printSingleQR(${asset.id})">
                    <i class='bx bx-printer'></i> Print
                </button>
            </td>`;

        tbody.appendChild(row);

        // Generate QR preview — use qr_url (LAN URL), fallback to asset ID
        const qrContent = asset.qr_url
            || asset.qr_code
            || `http://10.20.80.43/inventory-smart/asset-view.php?id=${asset.id}`;

        setTimeout(() => {
            const canvas = document.getElementById(qrId);
            if (canvas) {
                QRCode.toCanvas(canvas, qrContent, { width:80, margin:1,
                    color:{ dark:'#000000', light:'#FFFFFF' } });
            }
        }, 0);
    });

    updateSelectedCount();
}

// ── Modal ─────────────────────────────────────────────────────────────────
function openModal(assetId) {
    modalAsset = assetsData.find(a => a.id === assetId);
    if (!modalAsset) return;

    document.getElementById('modalTitle').textContent =
        `${modalAsset.category || ''} — ${modalAsset.asset_type || ''}`;
    document.getElementById('modalSub').textContent =
        [modalAsset.brand, modalAsset.model].filter(Boolean).join(' ') || 'Asset #' + assetId;
    document.getElementById('modalUrl').textContent = modalAsset.qr_url;

    const canvas = document.getElementById('modalCanvas');
    const modalQrContent = modalAsset.qr_url
        || modalAsset.qr_code
        || `http://10.20.80.43/inventory-smart/asset-view.php?id=${modalAsset.id}`;
    QRCode.toCanvas(canvas, modalQrContent, { width:220, margin:2 });

    document.getElementById('qrModal').classList.add('active');
}

function closeModal(e) {
    if (e.target === document.getElementById('qrModal'))
        document.getElementById('qrModal').classList.remove('active');
}

function printModalQR() {
    if (modalAsset) { generatePrintContent([modalAsset]); window.print(); }
}

// ── Selection ─────────────────────────────────────────────────────────────
function toggleSelectAll() {
    const checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.asset-checkbox').forEach(cb => {
        cb.checked = checked;
        const id = parseInt(cb.value);
        checked ? selectedAssets.add(id) : selectedAssets.delete(id);
    });
    updateSelectedCount();
}

function toggleAssetSelection(assetId) {
    selectedAssets.has(assetId) ? selectedAssets.delete(assetId) : selectedAssets.add(assetId);
    const total = document.querySelectorAll('.asset-checkbox').length;
    document.getElementById('selectAll').checked = selectedAssets.size === total;
    updateSelectedCount();
}

function updateSelectedCount() {
    document.getElementById('selectedCount').textContent =
        selectedAssets.size > 0 ? `${selectedAssets.size} selected` : '0 selected';
}

// ── Print ─────────────────────────────────────────────────────────────────
function printSingleQR(assetId) {
    const asset = assetsData.find(a => a.id === assetId);
    if (!asset) return;
    generatePrintContent([asset]);
    window.print();
}

function printSelectedQR() {
    if (selectedAssets.size === 0) { showNotification('Please select at least one asset', 'error'); return; }
    const selected = assetsData.filter(a => selectedAssets.has(a.id));
    generatePrintContent(selected);
    window.print();
}
function generatePrintContent(assets) {
    const printArea = document.getElementById('printArea');
    printArea.innerHTML = '';
    printArea.style.display = 'block';

    assets.forEach(asset => {
        const item = document.createElement('div');
        item.className = 'qr-print-item';

        const img = document.createElement('img');
        img.src = `generate_qr.php?id=${asset.id}`;
        img.style.width = "200px";
        img.style.display = "block";
        img.style.margin = "0 auto 8px";

        const info = document.createElement('div');
        info.innerHTML = `
            <div class="asset-info">${asset.category || ''} — ${asset.asset_type || ''}</div>
            <div class="asset-sub">${[asset.brand, asset.model].filter(Boolean).join(' ') || ''}</div>
            <div class="qr-code-text">${asset.qr_label || ''}</div>`;

        item.appendChild(img);
        item.appendChild(info);
        printArea.appendChild(item);
    });
}
// ── Categories ────────────────────────────────────────────────────────────
async function loadCategories() {
    try {
        const res    = await fetch('inventory_api.php?action=get_categories');
        const result = await res.json();
        if (result.success) {
            const sel = document.getElementById('categoryFilter');
            result.data.forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat.id; opt.textContent = cat.name;
                sel.appendChild(opt);
            });
        }
    } catch(e) {}
}

// ── Notification ──────────────────────────────────────────────────────────
function showNotification(message, type = 'info') {
    const n = document.createElement('div');
    n.style.cssText = `position:fixed;top:20px;right:20px;padding:1rem 1.5rem;
        background:${type==='success'?'#27ae60':'#d63031'};color:white;
        border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.2);z-index:10000;`;
    n.textContent = message;
    document.body.appendChild(n);
    setTimeout(() => n.remove(), 3000);
}

// ── Event listeners ───────────────────────────────────────────────────────
let searchTimeout;
document.getElementById('searchInput').addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(loadAssets, 500);
});
document.getElementById('categoryFilter').addEventListener('change', loadAssets);
window.addEventListener('afterprint', () => {
    document.getElementById('printArea').style.display = 'none';
});

// ── Init ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadCategories();
    loadAssets();
});
</script>

</body>
</html>
<?php ob_end_flush(); ?>