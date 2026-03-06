<?php
// Start output buffering to prevent header warnings
ob_start();

// Include sidebar (which has session_start)
include 'sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2d3436;
            --secondary: #00b894;
            --accent: #fdcb6e;
            --danger: #d63031;
            --warning: #e17055;
            --info: #74b9ff;
            --bg-main: #f8f9fa;
            --bg-card: #ffffff;
            --text-primary: #2d3436;
            --text-secondary: #636e72;
            --border: #dfe6e9;
            --shadow: rgba(0, 0, 0, 0.08);
            --shadow-lg: rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* ── LAYOUT ── */
        .content {
            margin-left: 88px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }
        .sidebar:not(.close) ~ .content {
            margin-left: 260px;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, #0984e3 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: 0 8px 24px var(--shadow-lg);
        }
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .page-header h1 i { font-size: 2.5rem; }
        .page-header p { opacity: 0.9; font-size: 1rem; }

        /* ── CONTROLS BAR ── */
        .controls-bar {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px var(--shadow);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .search-box { flex: 1; min-width: 200px; position: relative; }
        .search-box input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .search-box input:focus { outline: none; border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(0,184,148,0.1); }
        .search-box i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 1.2rem; }
        .filter-group { display: flex; gap: 0.75rem; flex-wrap: wrap; }

        /* ── BUTTONS ── */
        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }
        .btn-primary { background: var(--secondary); color: white; }
        .btn-primary:hover { background: #00a881; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,184,148,0.3); }
        .btn-secondary { background: var(--bg-main); color: var(--text-primary); border: 2px solid var(--border); }
        .btn-secondary:hover { border-color: var(--primary); background: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #c0392b; }

        select.btn {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23636e72' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 3rem;
        }

        /* ── TABLE ── */
        .table-container { background: var(--bg-card); border-radius: 12px; box-shadow: 0 2px 8px var(--shadow); overflow: hidden; }
        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        thead { background: linear-gradient(135deg, #2d3436 0%, #34495e 100%); color: white; }
        thead th { padding: 1rem; text-align: left; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid var(--border); transition: all 0.2s ease; cursor: pointer; }
        tbody tr:hover { background: #f8f9fa; }
        tbody td { padding: 1rem; color: var(--text-primary); white-space: nowrap; }
        tbody td.actions-cell { cursor: default; }

        /* ── BADGES ── */
        .badge { display: inline-block; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 500; font-family: 'JetBrains Mono', monospace; }
        .badge-new { background: #d4edda; color: #155724; }
        .badge-used { background: #fff3cd; color: #856404; }
        .badge-working { background: #d1ecf1; color: #0c5460; }
        .badge-not-working { background: #f8d7da; color: #721c24; }
        .badge-for-checking { background: #e2e3e5; color: #383d41; }

        /* ── ACTION BUTTONS ── */
        .action-btn { padding: 0.5rem; background: none; border: none; cursor: pointer; color: var(--text-secondary); font-size: 1.2rem; transition: all 0.2s ease; border-radius: 6px; }
        .action-btn:hover { background: var(--bg-main); transform: scale(1.1); }
        .action-btn.edit:hover { color: var(--info); }
        .action-btn.pullout:hover { color: var(--secondary); }

        /* ── MODALS ── */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 9999; animation: fadeIn 0.3s ease; }
        .modal-overlay.active { display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .modal { background: white; border-radius: 16px; max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.3s ease; }
        .modal-header { background: linear-gradient(135deg, var(--primary) 0%, #0984e3 100%); color: white; padding: 1.5rem 2rem; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1; }
        .modal-header h2 { font-size: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; }
        .modal-close { background: rgba(255,255,255,0.2); border: none; color: white; font-size: 1.5rem; cursor: pointer; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; flex-shrink: 0; }
        .modal-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }
        .modal-body { padding: 2rem; }

        /* ── IMAGE GALLERY ── */
        .image-gallery { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .image-item { position: relative; border-radius: 12px; overflow: hidden; aspect-ratio: 1; background: var(--bg-main); box-shadow: 0 4px 12px var(--shadow); cursor: pointer; transition: all 0.3s ease; }
        .image-item:hover { transform: translateY(-4px); box-shadow: 0 8px 24px var(--shadow-lg); }
        .image-item img { width: 100%; height: 100%; object-fit: cover; }
        .image-placeholder { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-secondary); font-size: 3rem; }

        /* ── DETAIL GRID ── */
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .detail-item { background: var(--bg-main); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--secondary); }
        .detail-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); margin-bottom: 0.25rem; font-weight: 600; }
        .detail-value { font-size: 1rem; color: var(--text-primary); font-weight: 500; word-break: break-word; }

        /* ── PULLOUT SECTION ── */
        .pullout-section { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 1.5rem; border-radius: 12px; border: 2px dashed var(--border); }
        .pullout-section h3 { margin-bottom: 1rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; }
        .pullout-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }

        /* ── FORM ── */
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { font-size: 0.85rem; font-weight: 600; color: var(--text-primary); }
        .form-group label .required { color: var(--danger); margin-left: 2px; }
        .form-group input, .form-group textarea, .form-group select { padding: 0.75rem; border: 2px solid var(--border); border-radius: 8px; font-family: 'Space Grotesk', sans-serif; font-size: 0.9rem; transition: all 0.3s ease; width: 100%; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(0,184,148,0.1); }
        .form-group small { font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem; }

        .form-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid var(--border); flex-wrap: wrap; }
        .modal-actions { display: flex; gap: 1rem; justify-content: flex-end; padding: 1.5rem 2rem; border-top: 2px solid var(--border); background: var(--bg-main); border-radius: 0 0 16px 16px; flex-wrap: wrap; position: sticky; bottom: 0; }
        .edit-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }

        /* ── PASSWORD MODAL ── */
        .password-lock-icon { text-align: center; margin-bottom: 1.5rem; }
        .password-lock-icon i { font-size: 3.5rem; color: var(--warning); }
        .password-error { color: var(--danger); font-size: 0.85rem; margin-top: 0.5rem; display: none; }

        /* ── ANIMATIONS ── */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 20%, 60% { transform: translateX(-8px); } 40%, 80% { transform: translateX(8px); } }
        .shake { animation: shake 0.4s ease; }

        /* ── STATS ROW ── */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px var(--shadow); border-left: 4px solid var(--secondary); }
        .stat-card h3 { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .value { font-size: 2rem; font-weight: 700; color: var(--primary); }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-secondary); }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.3; }
        .empty-state h3 { font-size: 1.25rem; margin-bottom: 0.5rem; }

        mark { background: #fdcb6e; color: var(--primary); border-radius: 3px; padding: 0 2px; }

        .location-cell { line-height: 1.4; }
        .location-main { font-weight: 500; color: var(--text-primary); }
        .location-sub  { font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.1rem; }

        /* ── CUSTOM SELECT ── */
        .custom-select-wrapper { position: relative; }
        .custom-select-input { width: 100%; padding: 0.75rem; border: 2px solid var(--border); border-radius: 8px; font-family: 'Space Grotesk', sans-serif; font-size: 0.9rem; transition: all 0.3s ease; background: white; }
        .custom-select-input:focus { outline: none; border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(0,184,148,0.1); }
        .custom-dropdown { display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: white; border: 2px solid var(--secondary); border-radius: 8px; max-height: 220px; overflow-y: auto; z-index: 99999; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
        .dropdown-item { padding: 0.6rem 1rem; cursor: pointer; font-size: 0.9rem; color: var(--text-primary); transition: background 0.15s ease; }
        .dropdown-item:hover { background: rgba(0,184,148,0.1); color: var(--secondary); }
        .dropdown-item.selected-loc { background: rgba(0,184,148,0.08); color: var(--secondary); font-weight: 600; }
        .dropdown-item.no-match { color: var(--text-secondary); font-style: italic; cursor: default; }
        .dropdown-item.no-match:hover { background: none; color: var(--text-secondary); }

        .subloc-group { margin-top: 0; }

        /* ══════════════════════════════════════════════
           RESPONSIVE — TABLET  (max 1024px)
        ══════════════════════════════════════════════ */
        @media (max-width: 1024px) {
            .content {
                margin-left: 88px;
                padding: 1.5rem;
            }
            .sidebar:not(.close) ~ .content {
                margin-left: 88px;
            }
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .page-header h1 { font-size: 1.6rem; }
            .page-header h1 i { font-size: 2rem; }
            /* Hide less critical columns on tablet */
            thead th:nth-child(3),
            tbody td:nth-child(3) { display: none; }
        }

        /* ══════════════════════════════════════════════
           RESPONSIVE — MOBILE  (max 768px)
        ══════════════════════════════════════════════ */
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 1rem;
                padding-bottom: 5rem; /* space for bottom nav if any */
            }
            .sidebar:not(.close) ~ .content {
                margin-left: 0;
            }

            /* Page Header */
            .page-header {
                padding: 1.25rem;
                border-radius: 12px;
                margin-bottom: 1rem;
            }
            .page-header h1 {
                font-size: 1.25rem;
                gap: 0.5rem;
            }
            .page-header h1 i { font-size: 1.5rem; }
            .page-header p { font-size: 0.85rem; }

            /* Stats */
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
                margin-bottom: 1rem;
            }
            .stat-card { padding: 1rem; border-radius: 10px; }
            .stat-card h3 { font-size: 0.75rem; }
            .stat-card .value { font-size: 1.5rem; }

            /* Controls Bar */
            .controls-bar {
                padding: 1rem;
                margin-bottom: 1rem;
                gap: 0.75rem;
                border-radius: 10px;
            }
            .search-box { min-width: 100%; }
            .filter-group {
                width: 100%;
                gap: 0.5rem;
            }
            .filter-group select,
            .filter-group .custom-select-wrapper {
                flex: 1;
                min-width: 0;
            }
            .btn {
                padding: 0.75rem 1rem;
                font-size: 0.85rem;
            }
            select.btn { padding-right: 2.5rem; }

            /* Table — card-style rows on mobile */
            .table-container { border-radius: 10px; }
            thead { display: none; }
            tbody tr {
                display: block;
                padding: 1rem;
                border-bottom: 2px solid var(--border);
                position: relative;
            }
            tbody tr:last-child { border-bottom: none; }
            tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.35rem 0;
                white-space: normal;
                font-size: 0.875rem;
                border: none;
            }
            tbody td::before {
                content: attr(data-label);
                font-size: 0.72rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.4px;
                color: var(--text-secondary);
                min-width: 90px;
                flex-shrink: 0;
            }
            tbody td.actions-cell {
                justify-content: flex-end;
                padding-top: 0.5rem;
                margin-top: 0.25rem;
                border-top: 1px solid var(--border);
            }
            tbody td.actions-cell::before { display: none; }

            /* Modal */
            .modal-overlay.active {
                align-items: flex-end;
                padding: 0;
            }
            .modal {
                border-radius: 20px 20px 0 0;
                max-height: 92vh;
                animation: slideUpMobile 0.35s ease;
            }
            .modal-header {
                padding: 1.25rem 1.5rem;
                border-radius: 20px 20px 0 0;
            }
            .modal-header h2 { font-size: 1.15rem; }
            .modal-body { padding: 1.25rem; }
            .modal-actions {
                padding: 1rem 1.25rem;
                border-radius: 0;
                gap: 0.75rem;
            }
            .modal-actions .btn,
            .form-actions .btn {
                flex: 1;
                justify-content: center;
            }

            /* Detail Grid */
            .detail-grid { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
            .detail-item { padding: 0.75rem; }
            .detail-value { font-size: 0.9rem; }

            /* Edit Form */
            .edit-form { grid-template-columns: 1fr; gap: 1rem; }
            .edit-form .form-group.full-width { grid-column: 1; }

            /* Pullout Form */
            .pullout-form { grid-template-columns: 1fr; gap: 1rem; }
            .pullout-form .form-group.full-width { grid-column: 1; }
            .pullout-section { padding: 1rem; }
            .form-actions { flex-direction: column-reverse; }

            /* Image Gallery */
            .image-gallery { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
        }

        /* ══════════════════════════════════════════════
           RESPONSIVE — SMALL MOBILE  (max 480px)
        ══════════════════════════════════════════════ */
        @media (max-width: 480px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
            .stat-card .value { font-size: 1.3rem; }
            .detail-grid { grid-template-columns: 1fr; }
            .image-gallery { grid-template-columns: repeat(2, 1fr); }
            .filter-group { flex-direction: column; }
            .filter-group select,
            .filter-group .custom-select-wrapper { width: 100%; }
            .modal-actions { flex-direction: column-reverse; }
            .modal-actions .btn { width: 100%; justify-content: center; }
        }

        @keyframes slideUpMobile {
            from { opacity: 0; transform: translateY(60px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="content">
    <div class="page-header">
        <h1><i class='bx bx-package'></i> Inventory Management</h1>
        <p>Click on any row to view details • Manage and track all your assets</p>
    </div>

    <div class="stats-row">
        <div class="stat-card"><h3>Stock Records</h3><div class="value" id="totalAssets">0</div></div>
        <div class="stat-card"><h3>Total Assets</h3><div class="value" id="activeItems">0</div></div>
        <div class="stat-card"><h3>In Transit</h3><div class="value" id="pulledOut">0</div></div>
        <div class="stat-card"><h3>For Checking</h3><div class="value" id="forChecking">0</div></div>
    </div>

    <div class="controls-bar">
        <div class="search-box">
            <i class='bx bx-search'></i>
            <input type="text" id="searchInput" placeholder="Search by brand, model, serial number...">
        </div>
        <div class="filter-group">
            <select id="categoryFilter" class="btn btn-secondary"><option value="">All Categories</option></select>
            <select id="statusFilter" class="btn btn-secondary">
                <option value="">All Status</option>
                <option value="WORKING">Working</option>
                <option value="NOT WORKING">Not Working</option>
                <option value="FOR CHECKING">For Checking</option>
            </select>
            <div class="custom-select-wrapper" style="position:relative; min-width:160px;">
                <input type="text" id="locationSearchInput" class="btn btn-secondary"
                       style="min-width:160px; cursor:text; text-align:left; appearance:none; padding-right:2rem; width:100%;"
                       placeholder="All Locations" autocomplete="off"
                       oninput="filterLocPanel()" onfocus="showLocPanel()" onblur="hideLocPanel()">
                <i class='bx bx-map-pin' style="position:absolute; right:0.75rem; top:50%; transform:translateY(-50%); color:var(--text-secondary); pointer-events:none;"></i>
                <div class="custom-dropdown" id="locPanelDropdown"></div>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-wrapper">
            <table id="inventoryTable">
                <thead>
                    <tr>
                        <th>Category / Type</th>
                        <th>Brand / Model</th>
                        <th>Serial No</th>
                        <th>Cond</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="inventoryBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <div class="modal-header">
            <h2><i class='bx bx-info-circle'></i> Asset Details</h2>
            <button class="modal-close" onclick="closeModal('viewModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <div class="image-gallery" id="imageGallery"></div>
            <div class="detail-grid" id="assetDetails"></div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal('viewModal')"><i class='bx bx-x'></i> Close</button>
            <button class="btn btn-primary" id="viewModalEditBtn" onclick="openEditFromView()"><i class='bx bx-edit'></i> Edit Asset</button>
            <button class="btn btn-primary" id="viewModalPulloutBtn" onclick="openPulloutFromView()"><i class='bx bx-transfer-alt'></i> Transfer</button>
        </div>
    </div>
</div>

<!-- Admin Password Modal -->
<div class="modal-overlay" id="adminPasswordModal">
    <div class="modal" style="max-width: 420px;">
        <div class="modal-header">
            <h2><i class='bx bx-lock-alt'></i> Admin Verification</h2>
            <button class="modal-close" onclick="closeModal('adminPasswordModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <div class="password-lock-icon"><i class='bx bx-shield-quarter'></i></div>
            <p style="text-align:center; color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.95rem;">Enter admin password to edit this asset.</p>
            <div class="form-group">
                <label>Admin Password <span class="required">*</span></label>
                <input type="password" id="adminPasswordInput" placeholder="Enter admin password..." onkeydown="if(event.key==='Enter') verifyAdminPassword()">
                <small class="password-error" id="passwordError"><i class='bx bx-error-circle'></i> Incorrect password. Please try again.</small>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal('adminPasswordModal')"><i class='bx bx-x'></i> Cancel</button>
            <button class="btn btn-primary" onclick="verifyAdminPassword()"><i class='bx bx-check-shield'></i> Verify & Continue</button>
        </div>
    </div>
</div>

<!-- Edit Asset Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h2><i class='bx bx-edit'></i> Edit Asset</h2>
            <button class="modal-close" onclick="closeModal('editModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <form id="editForm" class="edit-form">
                <input type="hidden" name="asset_id" id="editAssetId">
                <div class="form-group"><label>Brand</label><input type="text" name="brand" id="editBrand" placeholder="e.g. Dell, HP, Acer"></div>
                <div class="form-group"><label>Model</label><input type="text" name="model" id="editModel" placeholder="e.g. Latitude 5520"></div>
                <div class="form-group"><label>Serial Number</label><input type="text" name="serial_number" id="editSerial" placeholder="Serial / Asset Tag"></div>
                <div class="form-group"><label>Condition</label><select name="condition" id="editCondition"><option value="NEW">New</option><option value="USED">Used</option></select></div>
                <div class="form-group"><label>Status</label><select name="status" id="editStatus"><option value="WORKING">Working</option><option value="NOT WORKING">Not Working</option><option value="FOR CHECKING">For Checking</option></select></div>
                <div class="form-group"><label>Location</label><input type="text" name="location" id="editLocation" placeholder="e.g. Warehouse A"></div>
                <div class="form-group"><label>Sub-Location</label><input type="text" name="sub_location" id="editSubLocation" placeholder="e.g. Shelf 3"></div>
                <div class="form-group full-width"><label>Description</label><textarea name="description" id="editDescription" rows="3" placeholder="Additional notes or description..."></textarea></div>
                <div class="form-group"><label>Beginning Balance</label><input type="number" name="beg_balance_count" id="editBegBalance" min="0" placeholder="e.g. 10"><small>Total stock entered at the start.</small></div>
                <div class="form-group"><label>Active Count</label><input type="number" name="active_count" id="editActiveCount" min="0" placeholder="e.g. 8" oninput="previewBegBalance()"><small id="activeCountHint">Current available items. Adjusts Beginning Balance automatically.</small></div>
            </form>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal('editModal')"><i class='bx bx-x'></i> Cancel</button>
            <button class="btn btn-primary" onclick="submitEditAsset()"><i class='bx bx-save'></i> Save Changes</button>
        </div>
    </div>
</div>

<!-- Asset Transfer Request Modal -->
<div class="modal-overlay" id="pulloutModal">
    <div class="modal" style="max-width: 780px;">
        <div class="modal-header">
            <h2><i class='bx bx-transfer-alt'></i> Asset Transfer Request</h2>
            <button class="modal-close" onclick="closeModal('pulloutModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <div class="pullout-section">
                <h3><i class='bx bx-transfer-alt'></i> Fill Transfer Details</h3>
                <form class="pullout-form" id="pulloutForm">
                    <input type="hidden" name="asset_id" id="modalAssetId">

                    <div class="form-group">
                        <label>Quantity <span class="required">*</span></label>
                        <input type="number" name="quantity" id="pullout_quantity" min="1" value="1" required>
                        <small>Available: <strong id="available_qty">0</strong></small>
                    </div>
                    <div class="form-group">
                        <label>Date Needed <span class="required">*</span></label>
                        <input type="date" name="date_needed" required>
                    </div>

                    <div class="form-group">
                        <label>From (Source) <span class="required">*</span></label>
                        <div class="custom-select-wrapper">
                            <input type="text" id="from_search" class="custom-select-input" readonly
                                   style="background:#f1f2f6; color:var(--text-secondary); cursor:not-allowed;">
                            <input type="hidden" name="from_location" id="from_location_value">
                            <div class="custom-dropdown" id="from_dropdown"></div>
                        </div>
                        <small>Where is the asset coming from?</small>
                    </div>
                    <div class="form-group">
                        <label>To (Recipient) <span class="required">*</span></label>
                        <div class="custom-select-wrapper">
                            <input type="text" id="to_search" class="custom-select-input" placeholder="Search location..." autocomplete="off" oninput="filterDropdown('to')" onfocus="showDropdown('to')" onblur="hideDropdown('to')">
                            <input type="hidden" name="to_location" id="to_location_value">
                            <div class="custom-dropdown" id="to_dropdown"></div>
                        </div>
                        <small>Where is the asset going?</small>
                    </div>

                    <div class="form-group subloc-group">
                        <label>From Sub-Location</label>
                        <input type="text" name="from_sub_location" id="from_sub_location_value" readonly
                               style="background:#f1f2f6; color:var(--text-secondary); cursor:not-allowed;">
                    </div>

                    <div class="form-group full-width">
                        <label>Purpose <span class="required">*</span></label>
                        <div class="custom-select-wrapper">
                            <input type="text" id="purpose_search" class="custom-select-input" placeholder="Search purpose..." autocomplete="off" oninput="filterDropdown('purpose')" onfocus="showDropdown('purpose')" onblur="hideDropdown('purpose')">
                            <input type="hidden" name="purpose" id="purpose_value">
                            <div class="custom-dropdown" id="purpose_dropdown"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Requested By <span class="required">*</span></label>
                        <div class="custom-select-wrapper">
                            <input type="text" id="requested_by_search" class="custom-select-input" placeholder="Search name..." autocomplete="off" oninput="filterDropdown('requested_by')" onfocus="showDropdown('requested_by')" onblur="hideDropdown('requested_by')">
                            <input type="hidden" name="requested_by" id="requested_by_value">
                            <div class="custom-dropdown" id="requested_by_dropdown"></div>
                        </div>
                    </div>

                </form>
                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="closeModal('pulloutModal')"><i class='bx bx-x'></i> Cancel</button>
                    <button class="btn btn-primary" onclick="submitPullout()"><i class='bx bx-transfer-alt'></i> Submit Transfer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let inventoryData  = [];
    let currentAssetId = null;
    let pendingEditAssetId = null;
    let selectedLocation   = '';

    // ── LOCATION INLINE FILTER ────────────────────────────────────────────────
    function buildLocationMap(data) {
        const map = {};
        data.forEach(item => {
            const loc = (item.location || '').trim();
            if (!loc) return;
            map[loc] = (map[loc] || 0) + (parseInt(item.active_count) || 0);
        });
        return map;
    }

    function buildLocPanelDropdown(term) {
        const dd      = document.getElementById('locPanelDropdown');
        const map     = buildLocationMap(inventoryData);
        const matches = LOCATIONS.filter(l => !term || l.toLowerCase().includes(term.toLowerCase().trim()));
        dd.innerHTML  = '';

        const allItem = document.createElement('div');
        allItem.className = 'dropdown-item' + (!selectedLocation ? ' selected-loc' : '');
        allItem.innerHTML = '<em style="color:var(--text-secondary);">All Locations</em>';
        allItem.onmousedown = () => { selectedLocation = ''; document.getElementById('locationSearchInput').value = ''; dd.style.display = 'none'; loadInventory(); };
        dd.appendChild(allItem);

        if (matches.length === 0) {
            dd.innerHTML += '<div class="dropdown-item no-match">No results found</div>';
        } else {
            matches.forEach(loc => {
                const item  = document.createElement('div');
                const count = map[loc] !== undefined ? map[loc] : 0;
                item.className = 'dropdown-item' + (loc === selectedLocation ? ' selected-loc' : '');
                item.innerHTML = `${loc}<span style="float:right;opacity:0.45;font-size:0.78rem;margin-left:0.5rem;">${count > 0 ? count + ' active' : ''}</span>`;
                item.onmousedown = () => selectLocPanel(loc);
                dd.appendChild(item);
            });
        }
    }

    function filterLocPanel() { buildLocPanelDropdown(document.getElementById('locationSearchInput').value); document.getElementById('locPanelDropdown').style.display = 'block'; }
    function showLocPanel()   { buildLocPanelDropdown(document.getElementById('locationSearchInput').value); document.getElementById('locPanelDropdown').style.display = 'block'; }
    function hideLocPanel()   { setTimeout(() => { document.getElementById('locPanelDropdown').style.display = 'none'; document.getElementById('locationSearchInput').value = selectedLocation || ''; }, 200); }
    function selectLocPanel(loc) { selectedLocation = loc; document.getElementById('locationSearchInput').value = loc; document.getElementById('locPanelDropdown').style.display = 'none'; loadInventory(); }

    // ── LOAD & RENDER ─────────────────────────────────────────────────────────
    async function loadInventory() {
        try {
            const params = new URLSearchParams({
                action:   'get_inventory',
                search:   document.getElementById('searchInput').value,
                category: document.getElementById('categoryFilter').value,
                status:   document.getElementById('statusFilter').value,
                location: selectedLocation
            });
            const response = await fetch(`inventory_api.php?${params}`);
            const result   = await response.json();
            if (result.success) { inventoryData = result.data; renderInventoryTable(); updateStats(); }
            else showNotification('Error loading inventory', 'error');
        } catch (error) { showNotification('Failed to load inventory data', 'error'); }
    }

    function highlightText(text, term) {
        if (!term || !text) return text || '';
        const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return String(text).replace(new RegExp(`(${escaped})`, 'gi'), '<mark>$1</mark>');
    }

    function renderInventoryTable() {
        const tbody      = document.getElementById('inventoryBody');
        const searchTerm = document.getElementById('searchInput').value.trim();
        tbody.innerHTML  = '';

        if (inventoryData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:3rem;white-space:normal;"><div class="empty-state"><i class='bx bx-package'></i><h3>No assets found${selectedLocation ? ` in "${selectedLocation}"` : ''}</h3><p>Try adjusting your filters</p></div></td></tr>`;
            return;
        }

        inventoryData.forEach(item => {
            const activeCount = parseInt(item.active_count) || 0;
            const h = (val) => highlightText(val, searchTerm);
            const row = document.createElement('tr');
            row.setAttribute('data-id', item.id);
            row.onclick = function(e) { if (!e.target.closest('.action-btn')) openViewModal(item.id); };
            row.innerHTML = `
                <td data-label="Category"><div class="location-cell"><div class="location-main">${h(item.category)||'N/A'}</div>${item.asset_type?`<div class="location-sub">${h(item.asset_type)}</div>`:''}</div></td>
                <td data-label="Brand / Model"><div class="location-cell"><div class="location-main">${h(item.brand)||'N/A'}</div>${item.model?`<div class="location-sub">${h(item.model)}</div>`:''}</div></td>
                <td data-label="Serial No"><code class="badge">${h(item.serial_number)||'N/A'}</code></td>
                <td data-label="Condition"><span class="badge badge-${(item.condition||'').toLowerCase()}">${item.condition||'N/A'}</span></td>
                <td data-label="Status"><span class="badge badge-${(item.status||'').toLowerCase().replace(' ','-')}">${item.status||'N/A'}</span></td>
                <td data-label="Location"><div class="location-cell"><div class="location-main">${h(item.location)||'N/A'}</div>${item.sub_location?`<div class="location-sub">${h(item.sub_location)}</div>`:''}</div></td>
                <td data-label="Active"><strong style="color:var(--secondary);font-size:1.1rem;">${activeCount}</strong></td>
                <td class="actions-cell">
                    <button class="action-btn edit" onclick="event.stopPropagation(); openAdminPasswordModal(${item.id})" title="Edit Asset"><i class='bx bx-edit'></i></button>
                    <button class="action-btn pullout" onclick="event.stopPropagation(); openPulloutModal(${item.id})" title="Asset Transfer Request" ${activeCount<=0?'disabled style="opacity:0.3;cursor:not-allowed;"':''}>
                        <i class='bx bx-transfer-alt'></i>
                    </button>
                </td>`;
            tbody.appendChild(row);
        });
    }

    function updateStats() {
        document.getElementById('totalAssets').textContent = inventoryData.length;
        document.getElementById('activeItems').textContent = inventoryData.reduce((s,i) => s+(parseInt(i.active_count)||0), 0);
        document.getElementById('pulledOut').textContent   = inventoryData.reduce((s,i) => s+(parseInt(i.for_pullout)||0), 0);
        document.getElementById('forChecking').textContent = inventoryData.filter(i => i.status==='FOR CHECKING').length;
    }

    // ── VIEW MODAL ────────────────────────────────────────────────────────────
    async function openViewModal(id) {
        try {
            currentAssetId = id;
            const response = await fetch(`inventory_api.php?action=get_asset_details&asset_id=${id}`);
            const result   = await response.json();
            if (!result.success) { showNotification('Failed to load asset details', 'error'); return; }

            const item    = result.data;
            const gallery = document.getElementById('imageGallery');
            gallery.innerHTML = '';

            if (item.images && item.images.length > 0) {
                item.images.forEach(img => {
                    gallery.innerHTML += `<div class="image-item"><img src="${img}" alt="Asset Image" onclick="window.open('${img}','_blank')"></div>`;
                });
            } else {
                gallery.innerHTML = `<div class="image-item"><div class="image-placeholder"><i class='bx bx-image'></i></div></div>`;
            }

            document.getElementById('assetDetails').innerHTML = `
                <div class="detail-item"><div class="detail-label">Asset ID</div><div class="detail-value">#${item.id}</div></div>
                <div class="detail-item"><div class="detail-label">Category</div><div class="detail-value">${item.category||'N/A'}</div></div>
                <div class="detail-item"><div class="detail-label">Asset Type</div><div class="detail-value">${item.asset_type||'N/A'}</div></div>
                <div class="detail-item"><div class="detail-label">Brand</div><div class="detail-value">${item.brand||'N/A'}</div></div>
                <div class="detail-item"><div class="detail-label">Model</div><div class="detail-value">${item.model||'N/A'}</div></div>
                <div class="detail-item"><div class="detail-label">Serial Number</div><div class="detail-value">${item.serial_number||'N/A'}</div></div>
                <div class="detail-item"><div class="detail-label">Condition</div><div class="detail-value"><span class="badge badge-${(item.condition||'').toLowerCase()}">${item.condition||'N/A'}</span></div></div>
                <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value"><span class="badge badge-${(item.status||'').toLowerCase().replace(' ','-')}">${item.status||'N/A'}</span></div></div>
                <div class="detail-item"><div class="detail-label">Location</div><div class="detail-value">${item.location||'N/A'}</div></div>
                <div class="detail-item"><div class="detail-label">Sub-Location</div><div class="detail-value">${item.sub_location||'N/A'}</div></div>
                <div class="detail-item"><div class="detail-label">Beginning Balance</div><div class="detail-value">${item.beg_balance_count||0}</div></div>
                <div class="detail-item"><div class="detail-label">Active Count</div><div class="detail-value"><strong style="color:var(--secondary);font-size:1.5rem;">${item.active_count||0}</strong></div></div>
                <div class="detail-item"><div class="detail-label">Description</div><div class="detail-value">${item.description||'No description'}</div></div>
                <div class="detail-item"><div class="detail-label">QR Code</div><div class="detail-value"><code>${item.qr_code||'N/A'}</code></div></div>
            `;

            const availableQty  = parseInt(item.active_count) || 0;
            const pulloutBtn    = document.getElementById('viewModalPulloutBtn');
            pulloutBtn.disabled = availableQty <= 0;
            pulloutBtn.style.opacity = availableQty <= 0 ? '0.5' : '1';
            pulloutBtn.style.cursor  = availableQty <= 0 ? 'not-allowed' : 'pointer';

            document.getElementById('viewModal').classList.add('active');
        } catch (error) { showNotification('Failed to load asset details', 'error'); }
    }

    function openEditFromView()    { closeModal('viewModal'); openAdminPasswordModal(currentAssetId); }
    function openPulloutFromView() { closeModal('viewModal'); openPulloutModal(currentAssetId); }

    // ── ADMIN PASSWORD → EDIT ─────────────────────────────────────────────────
    function openAdminPasswordModal(id) {
        pendingEditAssetId = id;
        document.getElementById('adminPasswordInput').value = '';
        document.getElementById('passwordError').style.display = 'none';
        document.getElementById('adminPasswordModal').classList.add('active');
        setTimeout(() => document.getElementById('adminPasswordInput').focus(), 200);
    }

    async function verifyAdminPassword() {
        const password = document.getElementById('adminPasswordInput').value;
        if (!password) return;
        try {
            const response = await fetch('inventory_api.php?action=verify_admin_password', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password })
            });
            const result = await response.json();
            if (result.success) { closeModal('adminPasswordModal'); openEditModal(pendingEditAssetId); }
            else {
                document.getElementById('passwordError').style.display = 'block';
                const input = document.getElementById('adminPasswordInput');
                input.classList.add('shake'); input.value = '';
                setTimeout(() => input.classList.remove('shake'), 400);
            }
        } catch (error) { showNotification('Verification failed. Try again.', 'error'); }
    }

    async function openEditModal(id) {
        try {
            currentAssetId = id;
            const response = await fetch(`inventory_api.php?action=get_asset_details&asset_id=${id}`);
            const result   = await response.json();
            if (!result.success) { showNotification('Failed to load asset details', 'error'); return; }
            const item = result.data;
            document.getElementById('editAssetId').value     = item.id;
            document.getElementById('editBrand').value       = item.brand || '';
            document.getElementById('editModel').value       = item.model || '';
            document.getElementById('editSerial').value      = item.serial_number || '';
            document.getElementById('editCondition').value   = item.condition || 'NEW';
            document.getElementById('editStatus').value      = item.status || 'WORKING';
            document.getElementById('editLocation').value    = item.location || '';
            document.getElementById('editSubLocation').value = item.sub_location || '';
            document.getElementById('editDescription').value = item.description || '';
            document.getElementById('editBegBalance').value  = item.beg_balance_count ?? '';
            document.getElementById('editActiveCount').value = item.active_count ?? '';
            _editReleasedCount = parseInt(item.for_pullout) || 0;
            _editReturnedCount = parseInt(item.beg_balance_count) - parseInt(item.active_count) - _editReleasedCount || 0;
            document.getElementById('activeCountHint').textContent = 'Current available items. Adjusts Beginning Balance automatically.';
            document.getElementById('activeCountHint').style.color = '';
            document.getElementById('editModal').classList.add('active');
        } catch (error) { showNotification('Failed to open edit form', 'error'); }
    }

    async function submitEditAsset() {
        const data = Object.fromEntries(new FormData(document.getElementById('editForm')).entries());
        try {
            const response = await fetch('inventory_api.php?action=update_asset', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) { showNotification('Asset updated successfully!', 'success'); closeModal('editModal'); loadInventory(); }
            else showNotification(result.error || 'Failed to update asset', 'error');
        } catch (error) { showNotification('Failed to update asset', 'error'); }
    }

    // ── PULL-OUT MODAL ────────────────────────────────────────────────────────
    async function openPulloutModal(id) {
        try {
            currentAssetId = id;
            const response = await fetch(`inventory_api.php?action=get_asset_details&asset_id=${id}`);
            const result   = await response.json();
            if (!result.success) { showNotification('Failed to load asset details', 'error'); return; }

            const item         = result.data;
            const availableQty = parseInt(item.active_count) || 0;
            if (availableQty <= 0) { showNotification('No available items to transfer', 'error'); return; }

            document.getElementById('modalAssetId').value       = item.id;
            document.getElementById('available_qty').textContent = availableQty;
            document.getElementById('pullout_quantity').max      = availableQty;
            document.getElementById('pullout_quantity').value    = Math.min(1, availableQty);

            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="date_needed"]').min = today;

            const assetLoc    = (item.location     || '').trim();
            const assetSubLoc = (item.sub_location || '').trim();
            if (assetLoc)    { document.getElementById('from_search').value         = assetLoc; document.getElementById('from_location_value').value = assetLoc; }
            if (assetSubLoc) { document.getElementById('from_sub_location_value').value = assetSubLoc; }

            document.getElementById('pulloutModal').classList.add('active');
        } catch (error) { showNotification('Failed to open transfer form', 'error'); }
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
        if (modalId === 'pulloutModal') resetTransferForm();
        if (modalId !== 'adminPasswordModal') currentAssetId = null;
    }

    // ── DROPDOWN DATA ─────────────────────────────────────────────────────────
    const LOCATIONS = [
        "QC WareHouse","Pulilan WareHouse","CFE","CFE 2","GSI","ENT Vertis",
        "CBTL A30","CBTL FLZ","CBTL TRK","CBTL TR3","CBTL FVW","CBTL UT2","CBTL UTC","NN UTC",
        "CBTL RNG","CBTL GB5","CBTL BHS","CBTL ANE","CBTL ATC","CBTL CLN","CBTL LN2","CBTL GFD",
        "CBTL SMA","NN SMA","CBTL RAO","NN RAO","CBTL SFW","NN SFW","CBTL RPP","CBTL MID",
        "CBTL MPF","CBTL GAL","CBTL KCT","CBTL RGS","CBTL MRQ","NN CLN","CBTL GL2","CBTL CIT",
        "CBTL NUV","CBTL SPK","CBTL SRN","CBTL MAG","CBTL AB1","NN ABA","NN VER","CBTL VER",
        "NN NUV","CBTL HPT","CBTL CAP","CBTL B11","CBTL CBL","CBTL AY2","CBTL AYT","CBTL RGC",
        "CBTL RCY","CBTL SEA","CBTL CRO","CBTL CR2","CBTL SCP","CBTL GML","CBTL ABZ","CBTL LAN",
        "NN EVO","CBTL SMC","CBTL RWL","CBTL STM","NN LCT","CBTL VRT","CBTL RGT","DSY HSS",
        "DSY OPU","NN FIL","CBTL MIT","NN RML","NN EVI","CBTL MCK","CBTL SER","DSY OKA",
        "CBTL MPL","CBTL MOA","NN OKA","NN UPM","CBTL UPM","CBTL NPK","CBTL PDM","CBTL MG2",
        "CBTL MZA","CBTL JAZ","CBTL PQL","NN GWM","CBTL GWM","CBTL GWC","NN HSS","CBTL SGC",
        "CBTL TOL","CBTL AUR","CBTL LPA","CBTL SMB","CBTL PSG","CBTL BWG","CBTL TNZ","CBTL FMM",
        "CBTL SLN","NN LGC","CBTL LGC","CBTL RSJ","CBTL FIL","CBTL NDT","CBTL ACA","CBTL FSH",
        "CBTL CEL","NN RWL","CBTL LAC","CBTL CRK","CBTL MAK","CBTL BTN","CBTL CAU","CBTL HAI",
        "CBTL SSJ","CBTL SEO","CBTL SHW","CBTL MAX","CBTL SMX","NN KLT","NN 3CN","NN GFD",
        "CBTL SLE","CBTL NEO","CBTL SOL","CBTL BIC","NN BIC","NN TOL","CBTL EWM","CBTL CL1",
        "CBTL CL2","CBTL SMP","CBTL TEL","CBTL SMS","CBTL SMD","CBTL SMI","CBTL SMN","CBTL MEG",
        "NN SSR","CBTL SSR","NN SGC","CBTL WML","CBTL ORT","CBTL ADO","CBTL MSB","NN OPU",
        "NN GAL","CBTL SMG","CBTL SPP","CBTL FWI","CBTL ATR","CBTL BSD","CBTL SBD","CBTL CHM",
        "CBTL EB1","CBTL EB2","CBTL LSP","CBTL TEG","CBTL CH2","CBTL AZL","CBTL LKK","CBTL KCC",
        "CBTL KCZ","NN PDM","NN VIA","CBTL SMN-DS","CBTL AOA","CBTL BDO","CBTL BSM","CBTL BUR",
        "CBTL DDP","CBTL DFO","CBTL DLS","CBTL ENB","CBTL KAT","CBTL LAP","CBTL MOR","CBTL OPD",
        "CBTL SAL","CBTL SLX","CBTL SX2","CBTL WTC","CBTL TRG","CBTL UPN","CBTL UST","CBTL VRP",
        "CBTL PHN","CBTL PHL","CBTL SMS-DS","CBTL WFG","CBTL SMM-DS","CBTL MEN","CBTL SSR-DS",
        "CBTL SFW-DS","CBTL SBT-DS","CBTL SNV-DS","CBTL SSJ-DS","CBTL SRS-DS","CBTL STM-DS",
        "CBTL TNZ-DS","CBTL SRO-DS","CBTL WT1","CBTL OLO-DS","CBTL SUC-DS","CBTL CAU-DS",
        "CBTL SMT-DS","CBTL SPP-DS","CBTL DAE-DS","CBTL MAS-DS","CBTL ROX-DS","CBTL MLO-DS",
        "CBTL CLC-DS","CBTL DVO-DS","CBTL MEG-DS","CBTL SEA-DS","CBTL CON-DS","CBTL SMC-DS",
        "CBTL CLK-DS","CBTL TAR-DS","CBTL ILO-DS","CBTL BCD-DS","CBTL LAN-DS","CBTL SLZ-DS",
        "CBTL BAG-DS","CBTL MKT-DS","CBTL MPO-DS","CBTL TUG-DS","CBTL CUB-DS","CBTL BFP-DS",
        "CBTL BIC-DS","CBTL AUR-DS","CBTL MRK-DS","CBTL LOG-DS","CBTL SGC-DS","CBTL MSA-DS",
        "CBTL BWG-DS","CBTL BTN-DS","CBTL TRC-DS","CBTL SBR2-DS","CBTL EST-DS","CBTL TAY-DS",
        "CBTL SPB-DS","CBTL TEL-DS","CBTL BYD-DS","CBTL SEO-DS","CBTL SCP-DS","CBTL SFN-DS",
        "CBTL LUC-DS","CBTL LUN-DS"
    ];

    const PURPOSES   = ["Testing","Installation","Setup","Defective","Service Unit","Buffer"];
    const REQUESTERS = ["Allen","Arbie","Arwin","Christian","Darwin","Don","Dyzel","Gerr","Gheo","Gilbert","Jackie","Jake","JB","JC","Jessica","Jubilee","Lea","Mar","Patrick","Princess","Ricky","Ron","Rai","Toto","Admin"];

    const DROPDOWN_CONFIG = {
        from:         { data: LOCATIONS,  searchId: 'from_search',         valueId: 'from_location_value',  dropdownId: 'from_dropdown' },
        to:           { data: LOCATIONS,  searchId: 'to_search',           valueId: 'to_location_value',    dropdownId: 'to_dropdown' },
        purpose:      { data: PURPOSES,   searchId: 'purpose_search',      valueId: 'purpose_value',        dropdownId: 'purpose_dropdown' },
        requested_by: { data: REQUESTERS, searchId: 'requested_by_search', valueId: 'requested_by_value',   dropdownId: 'requested_by_dropdown' },
    };

    function buildDropdown(key) {
        const cfg     = DROPDOWN_CONFIG[key];
        const dd      = document.getElementById(cfg.dropdownId);
        const term    = document.getElementById(cfg.searchId).value.toLowerCase().trim();
        const matches = cfg.data.filter(v => v.toLowerCase().includes(term));
        dd.innerHTML  = '';
        if (matches.length === 0) { dd.innerHTML = '<div class="dropdown-item no-match">No results found</div>'; }
        else { matches.forEach(val => { const item = document.createElement('div'); item.className = 'dropdown-item'; item.textContent = val; item.onmousedown = () => selectDropdownItem(key, val); dd.appendChild(item); }); }
    }

    function filterDropdown(key)  { buildDropdown(key); document.getElementById(DROPDOWN_CONFIG[key].valueId).value = ''; document.getElementById(DROPDOWN_CONFIG[key].dropdownId).style.display = 'block'; }
    function showDropdown(key)    { buildDropdown(key); document.getElementById(DROPDOWN_CONFIG[key].dropdownId).style.display = 'block'; }
    function hideDropdown(key)    { setTimeout(() => { document.getElementById(DROPDOWN_CONFIG[key].dropdownId).style.display = 'none'; if (!document.getElementById(DROPDOWN_CONFIG[key].valueId).value) document.getElementById(DROPDOWN_CONFIG[key].searchId).value = ''; }, 200); }
    function selectDropdownItem(key, val) { document.getElementById(DROPDOWN_CONFIG[key].searchId).value = val; document.getElementById(DROPDOWN_CONFIG[key].valueId).value = val; document.getElementById(DROPDOWN_CONFIG[key].dropdownId).style.display = 'none'; }

    function resetTransferForm() {
        document.getElementById('pulloutForm').reset();
        Object.keys(DROPDOWN_CONFIG).forEach(key => {
            document.getElementById(DROPDOWN_CONFIG[key].searchId).value = '';
            document.getElementById(DROPDOWN_CONFIG[key].valueId).value  = '';
            document.getElementById(DROPDOWN_CONFIG[key].dropdownId).style.display = 'none';
        });
        document.getElementById('from_sub_location_value').value = '';
    }

    // ── SUBMIT TRANSFER ───────────────────────────────────────────────────────
    async function submitPullout() {
        const form     = document.getElementById('pulloutForm');
        const formData = new FormData(form);

        const data = {
            asset_id:          formData.get('asset_id'),
            quantity:          parseInt(formData.get('quantity')),
            date_needed:       formData.get('date_needed'),
            from_location:     formData.get('from_location'),
            from_sub_location: (document.getElementById('from_sub_location_value').value || '').trim(),
            to_location:       formData.get('to_location'),
            purpose:           formData.get('purpose'),
            requested_by:      formData.get('requested_by'),
        };

        const availableQty = parseInt(document.getElementById('available_qty').textContent);
        if (data.quantity > availableQty) { showNotification(`Cannot transfer ${data.quantity} items. Only ${availableQty} available.`, 'error'); return; }

        const missing = [];
        if (!data.quantity || data.quantity < 1) missing.push('Quantity');
        if (!data.date_needed)   missing.push('Date Needed');
        if (!data.from_location) missing.push('From (Source)');
        if (!data.to_location)   missing.push('To (Recipient)');
        if (!data.purpose)       missing.push('Purpose');
        if (!data.requested_by)  missing.push('Requested By');

        if (missing.length > 0) { showNotification(`Please fill in: ${missing.join(', ')}`, 'error'); return; }
        if (data.from_location === data.to_location) { showNotification('Source and recipient location cannot be the same.', 'error'); return; }

        try {
            const response = await fetch('inventory_api.php?action=submit_pullout', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) { showNotification(`Transfer request submitted for ${data.quantity} item(s)!`, 'success'); closeModal('pulloutModal'); loadInventory(); }
            else showNotification(result.error || 'Failed to submit transfer request', 'error');
        } catch (error) { showNotification('Failed to submit transfer request', 'error'); }
    }

    // ── UTILITIES ─────────────────────────────────────────────────────────────
    function showNotification(message, type = 'info') {
        const n = document.createElement('div');
        n.style.cssText = `position:fixed;top:20px;right:20px;left:20px;padding:1rem 1.5rem;background:${type==='success'?'var(--secondary)':'var(--danger)'};color:white;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.2);z-index:10000;animation:slideIn 0.3s ease;font-weight:500;max-width:400px;margin:0 auto;`;
        // On mobile, center it
        if (window.innerWidth > 600) { n.style.left = 'auto'; }
        n.textContent = message;
        document.body.appendChild(n);
        setTimeout(() => { n.style.animation = 'slideOut 0.3s ease'; setTimeout(() => n.remove(), 300); }, 4000);
    }

    async function loadCategories() {
        try {
            const response = await fetch('inventory_api.php?action=get_categories');
            const result   = await response.json();
            if (result.success) {
                const select = document.getElementById('categoryFilter');
                result.data.forEach(cat => { const o = document.createElement('option'); o.value = cat.id; o.textContent = cat.name; select.appendChild(o); });
            }
        } catch (error) { console.error('Error loading categories:', error); }
    }

    document.getElementById('searchInput').addEventListener('input', function() { renderInventoryTable(); clearTimeout(window._searchTimer); window._searchTimer = setTimeout(loadInventory, 300); });
    document.getElementById('categoryFilter').addEventListener('change', loadInventory);
    document.getElementById('statusFilter').addEventListener('change', loadInventory);
    document.querySelectorAll('.modal-overlay').forEach(overlay => { overlay.addEventListener('click', function(e) { if (e.target === this) closeModal(this.id); }); });

    document.addEventListener('DOMContentLoaded', function() { loadCategories(); loadInventory(); });

    let _editReleasedCount = 0;
    let _editReturnedCount = 0;

    function previewBegBalance() {
        const activeVal = parseInt(document.getElementById('editActiveCount').value);
        const hint      = document.getElementById('activeCountHint');
        if (isNaN(activeVal) || activeVal < 0) { hint.textContent = 'Current available items. Adjusts Beginning Balance automatically.'; hint.style.color = ''; return; }
        const newBeg = activeVal + _editReleasedCount - _editReturnedCount;
        if (newBeg < 0) { hint.innerHTML = `⚠️ Cannot set active to <strong>${activeVal}</strong> — would require a negative beginning balance.`; hint.style.color = 'var(--danger)'; }
        else { hint.innerHTML = `Beginning Balance will be set to <strong>${newBeg}</strong> (active ${activeVal} + released ${_editReleasedCount} − returned ${_editReturnedCount}).`; hint.style.color = 'var(--secondary)'; }
    }

    const style = document.createElement('style');
    style.textContent = `@keyframes slideIn{from{transform:translateX(100%);opacity:0;}to{transform:translateX(0);opacity:1;}}@keyframes slideOut{from{transform:translateX(0);opacity:1;}to{transform:translateX(100%);opacity:0;}}`;
    document.head.appendChild(style);
</script>
</body>
</html>
<?php ob_end_flush(); ?>