<?php
ob_start();
include 'sidebar.php';

require_once 'config.php';
$conn = getDBConnection();

$statsQuery  = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'PENDING'   THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'RELEASED'  THEN 1 ELSE 0 END) as released,
    SUM(CASE WHEN status = 'RETURNED'  THEN 1 ELSE 0 END) as returned
    FROM pull_out_transactions";
$statsResult = $conn->query($statsQuery);
$stats       = $statsResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Transfer Request</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #48c9b0; --primary-dark: #2f538a; --secondary: #00b894;
            --danger: #d63031; --warning: #f39c12; --info: #3498db;
            --success: #27ae60; --bg-main: #f8f9fa; --bg-card: #ffffff;
            --text-primary: #2d3436; --text-secondary: #636e72;
            --border: #dfe6e9; --shadow: rgba(0,0,0,0.08);
        }
        body { font-family: 'Space Grotesk', sans-serif; background: var(--bg-main); color: var(--text-primary); }
        .content { margin-left: 88px; padding: 2rem; transition: margin-left 0.3s ease; }
        .sidebar:not(.close) ~ .content { margin-left: 260px; }
        .page-header { background: linear-gradient(135deg, #263dc7 0%, var(--primary-dark) 100%); border-radius: 20px 20px 0 0; padding: 3rem 2rem 2rem; color: white; box-shadow: 0 8px 24px rgba(31,85,121,0.3); position: relative; overflow: hidden; }
        .page-header::before { content: ''; position: absolute; top: -50%; right: -10%; width: 400px; height: 400px; background: rgba(255,255,255,0.1); border-radius: 50%; }
        .page-header h1 { font-size: 2.5rem; font-weight: 700; position: relative; z-index: 1; }
        .page-header p  { font-size: 1.1rem; opacity: 0.9; position: relative; z-index: 1; margin-top: 0.25rem; }
        .stats-container { background: white; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 2rem; box-shadow: 0 4px 12px var(--shadow); }
        .stat-card { padding: 2rem; text-align: center; border-right: 1px solid var(--border); transition: all 0.3s; }
        .stat-card:last-child { border-right: none; }
        .stat-card:hover { background: var(--bg-main); transform: translateY(-2px); }
        .stat-label { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary); margin-bottom: 0.75rem; font-weight: 600; }
        .stat-value { font-size: 2.5rem; font-weight: 700; line-height: 1; }
        .stat-card:nth-child(1) .stat-value { color: var(--text-primary); }
        .stat-card:nth-child(2) .stat-value { color: var(--warning); }
        .stat-card:nth-child(3) .stat-value { color: var(--success); }
        .stat-card:nth-child(4) .stat-value { color: var(--info); }
        .controls-section { background: white; padding: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; box-shadow: 0 2px 8px var(--shadow); }
        .search-box { flex: 1; min-width: 250px; position: relative; }
        .search-box input { width: 100%; padding: 0.875rem 1rem 0.875rem 3rem; border: 2px solid var(--border); border-radius: 10px; font-family: 'Space Grotesk', sans-serif; font-size: 0.95rem; transition: all 0.3s; }
        .search-box input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(72,201,176,0.1); }
        .search-box i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 1.2rem; }
        .filter-group { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .btn { padding: 0.875rem 1.5rem; border: none; border-radius: 10px; font-family: 'Space Grotesk', sans-serif; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-filter { background: var(--bg-main); color: var(--text-primary); border: 2px solid var(--border); }
        .btn-filter.active { background: #695CFE; color: white; border-color: #695CFE; }
        .btn-filter:hover { border-color: var(--primary); }
        .btn-primary   { background: var(--primary); color: white; }
        .btn-primary:hover   { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-success   { background: var(--success); color: white; }
        .btn-success:hover   { background: #219a52; transform: translateY(-2px); }
        .btn-danger    { background: var(--danger); color: white; }
        .btn-danger:hover    { background: #c0392b; transform: translateY(-2px); }
        .btn-secondary { background: var(--bg-main); color: var(--text-primary); }
        .btn-secondary:hover { background: var(--border); }
        .table-container { background: white; box-shadow: 0 4px 12px var(--shadow); border-radius: 0 0 20px 20px; overflow: hidden; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        thead { background: linear-gradient(135deg, #2d3436 0%, #34495e 100%); color: white; }
        thead th { padding: 1.1rem 0.9rem; text-align: left; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid var(--border); transition: all 0.2s; cursor: pointer; }
        tbody tr:hover { background: #f0f4ff; }
        tbody tr.row-ready { border-left: 3px solid #27ae60; }
        tbody tr.row-ready:hover { background: #f0fff4; }
        tbody td { padding: 1rem 0.9rem; white-space: nowrap; }
        tbody td.wrap { white-space: normal; max-width: 180px; }
        .asset-cell { line-height: 1.4; }
        .asset-main  { font-weight: 600; color: var(--text-primary); }
        .asset-model { font-size: 0.82rem; color: var(--text-secondary); }
        .asset-serial { display: inline-block; margin-top: 2px; font-size: 0.78rem; background: #f1f2f6; padding: 1px 6px; border-radius: 4px; font-family: monospace; color: var(--text-secondary); }
        .route-cell { line-height: 1.5; min-width: 140px; }
        .route-from { font-size: 0.82rem; color: var(--text-secondary); }
        .route-arrow { font-size: 0.75rem; color: var(--primary); font-weight: 700; }
        .route-to   { font-size: 0.88rem; font-weight: 600; color: var(--text-primary); }
        .route-cell.returned .route-from, .route-cell.returned .route-arrow, .route-cell.returned .route-to { color: var(--info); }
        .thumb-cell { width: 44px; }
        .thumb-img { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; border: 2px solid var(--border); }
        .thumb-placeholder { width: 40px; height: 40px; border-radius: 8px; background: var(--bg-main); border: 2px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--text-secondary); font-size: 1.1rem; }
        .badge { display: inline-block; padding: 0.35rem 0.8rem; border-radius: 8px; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .badge-pending   { background: #fff3cd; color: #856404; }
        .badge-released  { background: #d4edda; color: #155724; }
        .badge-returned  { background: #d1ecf1; color: #0c5460; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        .badge-confirmed { background: #e8daef; color: #6c3483; }
        .badge-ready     { background: #d5f5e3; color: #1e8449; }
        .action-btn { padding: 0.45rem; background: none; border: none; cursor: pointer; color: var(--text-secondary); font-size: 1.15rem; border-radius: 6px; transition: all 0.2s; }
        .action-btn:hover { background: var(--bg-main); color: var(--primary); transform: scale(1.1); }
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-secondary); }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.3; display: block; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; border-radius: 20px; max-width: 750px; width: 95%; max-height: 92vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { background: linear-gradient(135deg, #263dc7 0%, var(--primary-dark) 100%); color: white; padding: 1.5rem 2rem; border-radius: 20px 20px 0 0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 2; }
        .modal-header h2 { font-size: 1.4rem; display: flex; align-items: center; gap: 0.75rem; }
        .modal-close { background: rgba(255,255,255,0.2); border: none; color: white; font-size: 1.5rem; cursor: pointer; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .modal-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }
        .modal-body { padding: 2rem; }
        .modal-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem; margin-bottom: 1.75rem; }
        .modal-gallery-item { border-radius: 10px; overflow: hidden; aspect-ratio: 1; background: var(--bg-main); border: 2px solid var(--border); cursor: pointer; transition: all 0.25s; position: relative; }
        .modal-gallery-item:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,0.15); border-color: var(--primary); }
        .modal-gallery-item img { width: 100%; height: 100%; object-fit: cover; }
        .modal-gallery-placeholder { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-secondary); font-size: 2.5rem; }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; }
        .detail-item { background: var(--bg-main); padding: 0.9rem 1rem; border-radius: 8px; border-left: 4px solid var(--primary); }
        .detail-item.full { grid-column: 1 / -1; }
        .detail-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); margin-bottom: 0.3rem; font-weight: 600; }
        .detail-value { font-size: 0.95rem; color: var(--text-primary); font-weight: 500; }
        .route-display { display: flex; align-items: center; gap: 0.75rem; background: var(--bg-main); padding: 0.9rem 1rem; border-radius: 8px; border-left: 4px solid var(--primary); grid-column: 1 / -1; }
        .route-display .loc { flex: 1; }
        .route-display .loc-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); font-weight: 600; }
        .route-display .loc-value { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); }
        .route-display .arrow-icon { font-size: 1.5rem; color: var(--primary); flex-shrink: 0; }
        .section-divider { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; color: var(--text-secondary); margin: 1.5rem 0 0.75rem; padding-bottom: 0.4rem; border-bottom: 2px solid var(--border); }
        .modal-actions { display: flex; gap: 1rem; justify-content: flex-end; padding: 1.25rem 2rem; border-top: 2px solid var(--border); background: var(--bg-main); border-radius: 0 0 20px 20px; position: sticky; bottom: 0; }
        mark { background: #fdcb6e; color: #2d3436; border-radius: 3px; padding: 0 2px; }
        @media (max-width: 768px) { .content { margin-left: 0; padding: 1rem; } .stats-container { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>

<div class="content">
    <div class="page-header">
        <h1><i class='bx bx-transfer-alt' style="font-size:2.2rem; vertical-align:middle; margin-right:0.5rem;"></i>Asset Transfer Request</h1>
        <p>Click any row to view details and update status</p>
    </div>

    <div class="stats-container">
        <div class="stat-card"><div class="stat-label">Total</div><div class="stat-value" id="statTotal"><?php echo $stats['total']; ?></div></div>
        <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value" id="statPending"><?php echo $stats['pending']; ?></div></div>
        <div class="stat-card"><div class="stat-label">Received</div><div class="stat-value" id="statReleased"><?php echo $stats['released']; ?></div></div>
        <div class="stat-card"><div class="stat-label">Returned</div><div class="stat-value" id="statReturned"><?php echo $stats['returned']; ?></div></div>
    </div>

    <div class="controls-section">
        <div class="search-box">
            <i class='bx bx-search'></i>
            <input type="text" id="searchInput" placeholder="Search by asset, requester, purpose, location...">
        </div>
        <div style="position:relative;">
            <i class='bx bx-map-pin' style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-secondary); pointer-events:none; font-size:1.1rem;"></i>
            <select id="locationFilter" style="padding:0.875rem 1rem 0.875rem 2.5rem; border:2px solid var(--border); border-radius:10px; font-family:'Space Grotesk',sans-serif; font-size:0.9rem; color:var(--text-primary); background:white; cursor:pointer; outline:none; min-width:180px; appearance:none;" onchange="applyFilters()">
                <option value="">All Locations</option>
            </select>
        </div>
        <div class="filter-group">
            <button class="btn btn-filter active" data-status="">All</button>
            <button class="btn btn-filter" data-status="PENDING" id="btnPending">
                Pending <span id="pendingCount" style="display:none; background:var(--warning); color:white; border-radius:20px; padding:1px 8px; font-size:0.75rem; margin-left:2px;"></span>
            </button>
            <button class="btn btn-filter" data-status="RELEASED">Received</button>
            <button class="btn btn-filter" data-status="RETURNED">Returned</button>
            <button class="btn btn-filter" data-status="CANCELLED">Cancelled</button>
        </div>
    </div>

    <div class="table-container">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:50px;"></th>
                        <th>Asset</th><th>Type</th><th>Qty</th><th>From → To</th>
                        <th>Purpose</th><th>Requested By</th><th>Released By</th>
                        <th>Delivered By</th><th>Received By</th><th>Date Needed</th><th>Status</th>
                    </tr>
                </thead>
                <tbody id="pulloutBody">
                    <tr><td colspan="12"><div class="empty-state"><i class='bx bx-loader-alt bx-spin'></i><p>Loading...</p></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View / Update Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <h2><i class='bx bx-transfer-alt'></i> Transfer Details</h2>
            <button class="modal-close" onclick="closeModal()"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <div class="modal-gallery" id="modalGallery"></div>
            <div class="section-divider">Asset Info</div>
            <div class="detail-grid" id="assetInfo"></div>
            <div class="section-divider">Transfer Info</div>
            <div class="detail-grid" id="txnInfo"></div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal()"><i class='bx bx-x'></i> Close</button>
            <button class="btn btn-danger" id="modalCancelBtn" style="display:none;" onclick="confirmCancel(currentId)"><i class='bx bx-block'></i> Cancel Transfer</button>
            <button class="btn btn-primary" id="modalActionBtn" style="display:none;">Update Status</button>
        </div>
    </div>
</div>

<script>
    let pulloutData     = [];
    let currentFilter   = '';
    let currentLocation = '';
    let currentId       = null;

    function renderStatus(item) {
        const s    = (item.status || '').toUpperCase();
        const step = parseInt(item.prep_step ?? 0);

        if (s === 'CONFIRMED' && step >= 4) {
            return `<span class="badge badge-ready">✓ Ready to Release</span>`;
        }

        const statusMap = {
            PENDING:   { label: 'PENDING',   class: 'badge-pending' },
            RELEASED:  { label: 'RECEIVED',  class: 'badge-released' },
            RETURNED:  { label: 'RETURNED',  class: 'badge-returned' },
            CANCELLED: { label: 'CANCELLED', class: 'badge-cancelled' },
            CONFIRMED: { label: 'CONFIRMED', class: 'badge-confirmed' }
        };

        const cfg = statusMap[s] || { label: s, class: 'badge-pending' };
        return `<span class="badge ${cfg.class}">${cfg.label}</span>`;
    }

    async function loadLocations() {
        try {
            const res    = await fetch('pullout_api.php?action=get_locations');
            const result = await res.json();
            if (!result.success) return;
            const select = document.getElementById('locationFilter');
            result.data.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.location;
                opt.textContent = item.pending_count > 0 ? `${item.location}  (${item.pending_count})` : item.location;
                if (item.pending_count > 0) opt.style.fontWeight = '600';
                select.appendChild(opt);
            });
        } catch(e) { console.error('loadLocations:', e); }
    }

    function applyFilters() { currentLocation = document.getElementById('locationFilter').value; loadPullouts(); }

    async function loadPullouts() {
        try {
            const search = document.getElementById('searchInput').value;
            const params = new URLSearchParams({ action: 'get_pullouts', search, status: currentFilter, location: currentLocation });
            const res    = await fetch(`pullout_api.php?${params}`);
            const result = await res.json();
            if (result.success) { pulloutData = result.data; renderTable(); refreshStats(); }
            else showNotification('Error loading data', 'error');
        } catch (e) { showNotification('Failed to load data', 'error'); }
    }

    async function refreshStats() {
        try {
            const res    = await fetch('pullout_api.php?action=get_pullouts&search=&status=');
            const result = await res.json();
            if (!result.success) return;
            const all     = result.data;
            const pending = all.filter(r => r.status === 'PENDING').length;
            document.getElementById('statTotal').textContent    = all.length;
            document.getElementById('statPending').textContent  = pending;
            document.getElementById('statReleased').textContent = all.filter(r => r.status === 'RELEASED').length;
            document.getElementById('statReturned').textContent = all.filter(r => r.status === 'RETURNED').length;
            const badge = document.getElementById('pendingCount');
            if (pending > 0) { badge.textContent = pending; badge.style.display = 'inline'; } else { badge.style.display = 'none'; }
        } catch(e) {}
    }

    function getRoute(item) {
        let from = (item.from_location || '').trim();
        let to   = (item.to_location   || '').trim();
        if (!from || !to) {
            const clean = (item.purpose || '').replace(/\s*\[(dest_asset_id|dest):\d+\]/g, '').trim();
            const match = clean.match(/From:\s*(.+?)\s*→\s*To:\s*(.+)$/);
            if (match) { if (!from) from = match[1].trim(); if (!to) to = match[2].trim(); }
        }
        const isReturned = item.status === 'RETURNED';
        return {
            from:     isReturned ? (to   || '—') : (from || '—'),
            to:       isReturned ? (from || '—') : (to   || '—'),
            label:    (item.purpose || '').replace(/\s*\[(dest_asset_id|dest):\d+\]/g, '').replace(/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i, '').trim() || '—',
            returned: isReturned
        };
    }

    function cleanPurpose(purpose) {
        if (!purpose) return '—';
        return purpose.replace(/\s*\[(dest_asset_id|dest):\d+\]/g, '').replace(/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i, '').trim() || '—';
    }

    function cleanReceivedBy(val) { if (!val) return '—'; return val.replace(/\s*\[(dest_asset_id|dest):\d+\]/g, '').trim() || '—'; }
    function hl(val, term) { if (!term || !val) return val || ''; const esc = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); return String(val).replace(new RegExp(`(${esc})`, 'gi'), '<mark>$1</mark>'); }

    function renderTable() {
        const tbody = document.getElementById('pulloutBody');
        const term  = document.getElementById('searchInput').value.trim();
        tbody.innerHTML = '';
        if (pulloutData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="12"><div class="empty-state"><i class='bx bx-transfer-alt'></i><h3>No transfer requests found</h3><p>Try adjusting your filters</p></div></td></tr>`;
            return;
        }
        pulloutData.forEach(item => {
            const route   = getRoute(item);
            const purpose = cleanPurpose(item.purpose);

            let thumbHtml;
            if (item.thumbnail && item.thumbnail_type === 'gdrive') {
                thumbHtml = `<img class="thumb-img gdrive-thumb"
    src="${encodeURI(item.thumbnail)}"
    data-url="${encodeURI(item.thumbnail_url || item.thumbnail)}"
    alt="asset"
    style="cursor:pointer;"
    onerror="this.outerHTML='<div class=thumb-placeholder><i class=bx bxl-google></i></div>'">`;
            } else if (item.thumbnail) {
                thumbHtml = `<img class="thumb-img" src="${encodeURI(item.thumbnail)}" alt="asset">`;
            } else {
                thumbHtml = `<div class="thumb-placeholder"><i class='bx bx-image-alt'></i></div>`;
            }

            const row = document.createElement('tr');
            if (item.status === 'CONFIRMED' && parseInt(item.prep_step ?? 0) >= 4) row.classList.add('row-ready');
            row.onclick = () => openViewModal(item.id);
            row.innerHTML = `
                <td class="thumb-cell">${thumbHtml}</td>
                <td><div class="asset-cell">
                    <div class="asset-main">${hl(item.brand, term) || '—'}</div>
                    ${item.model ? `<div class="asset-model">${hl(item.model, term)}</div>` : ''}
                    ${item.serial_number ? `<span class="asset-serial">${hl(item.serial_number, term)}</span>` : ''}
                </div></td>
                <td>${hl(item.asset_type, term) || '—'}</td>
                <td><strong>${item.quantity}</strong></td>
                <td><div class="route-cell${route.returned ? ' returned' : ''}">
                    <div class="route-from">${hl(route.from, term)}</div>
                    <div class="route-arrow">↓${route.returned ? ' ↩' : ''}</div>
                    <div class="route-to">${hl(route.to, term)}</div>
                </div></td>
                <td class="wrap">${hl(purpose, term)}</td>
                <td>${hl(item.requested_by, term) || '—'}</td>
                <td>${hl(item.released_by, term) || '—'}</td>
                <td>${hl(item.delivered_by, term) || '—'}</td>
                <td>${hl(cleanReceivedBy(item.received_by), term)}</td>
                <td>${formatDate(item.date_needed)}</td>
                <td>${renderStatus(item)}</td>`;
            tbody.appendChild(row);
        });
    }

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('gdrive-thumb')) {
            e.stopPropagation();
            const url = e.target.dataset.url;
            if (url) window.open(url, '_blank');
        }
    });

    async function openViewModal(id) {
        currentId = id;
        try {
            const res    = await fetch(`pullout_api.php?action=get_pullout_details&id=${id}`);
            const result = await res.json();
            if (!result.success) { showNotification('Failed to load details', 'error'); return; }

            const item    = result.data;
            const route   = getRoute(item);
            const purpose = cleanPurpose(item.purpose);

            const gallery = document.getElementById('modalGallery');
            gallery.innerHTML = '';
            if (item.images && item.images.length > 0) {
                item.images.forEach(img => {
                    const tile = document.createElement('div');
                    tile.className = 'modal-gallery-item';
                    if (img.type === 'gdrive' && img.thumb) {
                        tile.style.cssText = 'cursor:pointer;position:relative;';
                        tile.onclick = () => window.open(img.url, '_blank');
                        tile.innerHTML = `
                            <img src="${encodeURI(img.thumb)}" alt="Asset photo"
                                 style="width:100%;height:100%;object-fit:cover;"
                                 onerror="this.parentElement.innerHTML='<div class=\\"modal-gallery-placeholder\\"><i class=\\"bx bxl-google\\"></i></div>'">
                            <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,0.55));padding:.3rem .5rem;display:flex;align-items:center;gap:.3rem;">
                                <i class='bx bxl-google' style="color:white;font-size:.85rem;flex-shrink:0;"></i>
                                <span style="color:white;font-size:.68rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Open in Drive</span>
                            </div>`;
                    } else if (img.type === 'gdrive_folder' || (img.type === 'gdrive' && !img.thumb)) {
                        tile.style.cssText = 'display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#e8f5e9,#f1f8e9);border:2px solid #a5d6a7;cursor:pointer;';
                        tile.onclick = () => window.open(img.url, '_blank');
                        tile.innerHTML = `<div style="text-align:center;padding:.75rem;"><i class='bx bxl-google' style="font-size:2rem;color:#1a73e8;display:block;margin-bottom:.4rem;"></i><span style="font-size:.72rem;font-weight:600;color:#1a73e8;line-height:1.3;display:block;">View on<br>Google Drive</span></div>`;
                    } else {
                        const url = img.url || img;
                        tile.onclick = () => window.open(encodeURI(url), '_blank');
                        tile.innerHTML = `<img src="${encodeURI(url)}" alt="Asset photo" style="width:100%;height:100%;object-fit:cover;">`;
                    }
                    gallery.appendChild(tile);
                });
            } else {
                gallery.innerHTML = `<div class="modal-gallery-item"><div class="modal-gallery-placeholder"><i class='bx bx-image'></i></div></div>`;
            }

            document.getElementById('assetInfo').innerHTML = `
                <div class="detail-item"><div class="detail-label">Category</div><div class="detail-value">${item.category || '—'}</div></div>
                <div class="detail-item"><div class="detail-label">Asset Type</div><div class="detail-value">${item.asset_type || '—'}</div></div>
                <div class="detail-item"><div class="detail-label">Brand</div><div class="detail-value">${item.brand || '—'}</div></div>
                <div class="detail-item"><div class="detail-label">Model</div><div class="detail-value">${item.model || '—'}</div></div>
                <div class="detail-item"><div class="detail-label">Serial No</div><div class="detail-value"><code style="font-size:0.9rem;">${item.serial_number || '—'}</code></div></div>
                <div class="detail-item"><div class="detail-label">Condition</div><div class="detail-value"><span class="badge badge-${(item.condition||'new').toLowerCase()}">${item.condition || '—'}</span></div></div>
                <div class="detail-item"><div class="detail-label">Asset Location</div><div class="detail-value">${item.asset_location || '—'}</div></div>
                <div class="detail-item"><div class="detail-label">Sub-Location</div><div class="detail-value">${item.asset_sub_location || '—'}</div></div>`;

            document.getElementById('txnInfo').innerHTML = `
                <div class="detail-item"><div class="detail-label">Transaction #</div><div class="detail-value">#${item.id}</div></div>
                <div class="detail-item"><div class="detail-label">Quantity</div><div class="detail-value"><strong style="font-size:1.2rem;">${item.quantity}</strong></div></div>
                <div class="route-display" style="${route.returned ? 'border-left-color:#3498db;' : ''}">
                    <div class="loc"><div class="loc-label">${route.returned ? 'Returning From' : 'From (Source)'}</div><div class="loc-value">${route.from}</div></div>
                    <i class='bx bx-right-arrow-alt arrow-icon' style="${route.returned ? 'color:#3498db;' : ''}"></i>
                    <div class="loc"><div class="loc-label">${route.returned ? 'Returning To' : 'To (Recipient)'}</div><div class="loc-value">${route.to}</div></div>
                </div>
                <div class="detail-item"><div class="detail-label">Purpose</div><div class="detail-value">${purpose}</div></div>
                <div class="detail-item"><div class="detail-label">Requested By</div><div class="detail-value">${item.requested_by || '—'}</div></div>
                <div class="detail-item"><div class="detail-label">Released By</div><div class="detail-value">${item.released_by || '—'}</div></div>
                <div class="detail-item"><div class="detail-label">Delivered By</div><div class="detail-value">${item.delivered_by || '—'}</div></div>
                <div class="detail-item"><div class="detail-label">Received By</div><div class="detail-value">${cleanReceivedBy(item.received_by)}</div></div>
                <div class="detail-item"><div class="detail-label">Date Needed</div><div class="detail-value">${formatDate(item.date_needed)}</div></div>
                <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">${renderStatus(item)}</div></div>
                <div class="detail-item"><div class="detail-label">Received At</div><div class="detail-value">${item.released_at ? formatDateTime(item.released_at) : '—'}</div></div>
                <div class="detail-item"><div class="detail-label">Returned At</div><div class="detail-value">${item.returned_at ? formatDateTime(item.returned_at) : '—'}</div></div>`;

            const actionBtn = document.getElementById('modalActionBtn');
            const cancelBtn = document.getElementById('modalCancelBtn');
            actionBtn.style.display = cancelBtn.style.display = 'none';
            actionBtn.className = 'btn';

            if (item.status === 'PENDING') {
                cancelBtn.style.display = '';
            } else if (item.status === 'RELEASED') {
                actionBtn.style.display = '';
                actionBtn.innerHTML = '<i class="bx bx-undo"></i> Mark as Returned';
                actionBtn.classList.add('btn-primary');
                actionBtn.onclick = () => updateStatus(id, 'RETURNED');
            }

            document.getElementById('viewModal').classList.add('active');
        } catch (e) { showNotification('Failed to load details', 'error'); }
    }

    function closeModal() {
        document.getElementById('viewModal').classList.remove('active');
        currentId = null;
        const block = document.getElementById('releaseConfirmBlock');
        if (block) block.remove();
        document.getElementById('modalCancelBtn').style.display = 'none';
        document.getElementById('modalActionBtn').style.display = 'none';
    }

    function confirmCancel(id) {
        const existing = document.getElementById('releaseConfirmBlock');
        if (existing) existing.remove();
        document.getElementById('txnInfo').insertAdjacentHTML('afterend', `
            <div id="releaseConfirmBlock" style="margin-top:1rem; background:#fdecea; border:2px solid #d63031; border-radius:10px; padding:1.25rem; text-align:center;">
                <div style="font-size:1.75rem; margin-bottom:0.4rem;">⚠️</div>
                <div style="font-weight:600; font-size:1rem; color:#721c24;">Cancel this transfer request?</div>
                <div style="font-size:0.85rem; color:#636e72; margin-top:0.25rem;">Asset quantity will be restored at the source location.</div>
            </div>`);
        const cancelBtn = document.getElementById('modalCancelBtn');
        cancelBtn.innerHTML = '<i class="bx bx-block"></i> Confirm Cancel';
        cancelBtn.onclick = () => updateStatus(id, 'CANCELLED');
    }

    async function updateStatus(id, newStatus) {
        try {
            const res    = await fetch('pullout_api.php?action=update_status', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, status: newStatus }) });
            const result = await res.json();
            if (result.success) {
                const label = newStatus === 'RELEASED' ? 'RECEIVED' : newStatus === 'CANCELLED' ? 'CANCELLED' : 'RETURNED';
                showNotification(`Transfer marked as ${label}`, 'success');
                closeModal();
                document.getElementById('locationFilter').innerHTML = '<option value="">All Locations</option>';
                loadLocations(); loadPullouts();
            } else { showNotification(result.error || 'Failed to update', 'error'); }
        } catch (e) { showNotification('Failed to update status', 'error'); }
    }

    function formatDate(d) { if (!d) return '—'; return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
    function formatDateTime(d) { if (!d) return '—'; return new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
    function showNotification(message, type = 'info') {
        const n = document.createElement('div');
        n.style.cssText = `position:fixed;top:20px;right:20px;padding:1rem 1.5rem;background:${type==='success'?'#27ae60':'#d63031'};color:white;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,0.2);z-index:10000;font-weight:500;font-size:0.95rem;animation:slideInRight 0.3s ease;`;
        n.textContent = message; document.body.appendChild(n);
        setTimeout(() => n.remove(), 3500);
    }

    document.querySelectorAll('.btn-filter').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.getAttribute('data-status');
            loadPullouts();
        });
    });

    document.getElementById('searchInput').addEventListener('input', function() {
        renderTable();
        clearTimeout(window._st);
        window._st = setTimeout(loadPullouts, 300);
    });

    document.getElementById('viewModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
    document.addEventListener('DOMContentLoaded', () => { loadLocations(); loadPullouts(); });

    const _style = document.createElement('style');
    _style.textContent = `@keyframes slideInRight { from { transform:translateX(100%); opacity:0; } to { transform:translateX(0); opacity:1; } }`;
    document.head.appendChild(_style);
</script>
</body>
</html>
<?php ob_end_flush(); ?>