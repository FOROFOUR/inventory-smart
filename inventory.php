<?php
ob_start();
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #2d3436; --secondary: #00b894; --accent: #fdcb6e;
            --danger: #d63031; --warning: #e17055; --info: #74b9ff;
            --bg-main: #f8f9fa; --bg-card: #ffffff;
            --text-primary: #2d3436; --text-secondary: #636e72;
            --border: #dfe6e9; --shadow: rgba(0,0,0,0.08); --shadow-lg: rgba(0,0,0,0.12);
        }
        body { font-family: 'Space Grotesk', sans-serif; background: var(--bg-main); color: var(--text-primary); line-height: 1.6; }

        .content { margin-left: 88px; padding: 2rem; transition: margin-left 0.3s ease; }
        .sidebar:not(.close) ~ .content { margin-left: 260px; }

        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, #0984e3 100%);
            border-radius: 16px; padding: 2rem; margin-bottom: 2rem;
            color: white; box-shadow: 0 8px 24px var(--shadow-lg);
        }
        .page-header h1 { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 1rem; }
        .page-header h1 i { font-size: 2.5rem; }
        .page-header p { opacity: 0.9; font-size: 1rem; }

        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 1.75rem; }
        .kpi-card { background: var(--bg-card); border-radius: 14px; padding: 1.5rem; box-shadow: 0 2px 10px var(--shadow); display: flex; align-items: center; gap: 1.25rem; transition: all 0.3s; position: relative; overflow: hidden; }
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
        .kpi-value { font-size: 2rem; font-weight: 700; line-height: 1.1; color: var(--text-primary); }
        .kpi-label { font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0.2rem; font-weight: 500; }
        .kpi-sub   { font-size: 0.78rem; color: var(--text-secondary); margin-top: 0.3rem; }

        .controls-bar { background: var(--bg-card); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px var(--shadow); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
        .search-box { flex: 1; min-width: 200px; position: relative; }
        .search-box input { width: 100%; padding: 0.875rem 1rem 0.875rem 3rem; border: 2px solid var(--border); border-radius: 8px; font-family: 'Space Grotesk', sans-serif; font-size: 0.95rem; transition: all 0.3s; }
        .search-box input:focus { outline: none; border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(0,184,148,0.1); }
        .search-box i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 1.2rem; }
        .filter-group { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .btn { padding: 0.875rem 1.5rem; border: none; border-radius: 8px; font-family: 'Space Grotesk', sans-serif; font-size: 0.95rem; font-weight: 500; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.5rem; white-space: nowrap; }
        .btn-primary   { background: var(--secondary); color: white; }
        .btn-primary:hover { background: #00a881; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,184,148,0.3); }
        .btn-secondary { background: var(--bg-main); color: var(--text-primary); border: 2px solid var(--border); }
        .btn-secondary:hover { border-color: var(--primary); background: white; }
        .btn-danger    { background: var(--danger); color: white; }
        .btn-danger:hover { background: #c0392b; transform: translateY(-2px); }
        select.btn { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23636e72' d='M6 9L1 4h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; padding-right: 3rem; }

        .table-container { background: var(--bg-card); border-radius: 12px; box-shadow: 0 2px 8px var(--shadow); overflow: hidden; }
        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        thead { background: linear-gradient(135deg, #2d3436 0%, #34495e 100%); color: white; }
        thead th { padding: 1rem; text-align: left; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid var(--border); transition: all 0.2s; cursor: pointer; }
        tbody tr:hover { background: #f8f9fa; }
        tbody td { padding: 1rem; color: var(--text-primary); white-space: nowrap; }
        tbody td.actions-cell { cursor: default; }
        .badge { display: inline-block; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 500; font-family: 'JetBrains Mono', monospace; }
        .badge-new { background:#d4edda;color:#155724; } .badge-used { background:#fff3cd;color:#856404; }
        .badge-working { background:#d1ecf1;color:#0c5460; } .badge-not-working { background:#f8d7da;color:#721c24; }
        .badge-for-checking { background:#e2e3e5;color:#383d41; }
        .action-btn { padding: 0.5rem; background: none; border: none; cursor: pointer; color: var(--text-secondary); font-size: 1.2rem; transition: all 0.2s; border-radius: 6px; }
        .action-btn:hover { background: var(--bg-main); transform: scale(1.1); }
        .action-btn.edit:hover { color: var(--info); } .action-btn.pullout:hover { color: var(--secondary); }
        .location-cell { line-height: 1.4; }
        .location-main { font-weight: 500; color: var(--text-primary); }
        .location-sub  { font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.1rem; }
        mark { background: #fdcb6e; color: var(--primary); border-radius: 3px; padding: 0 2px; }
        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-secondary); }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.3; }
        .empty-state h3 { font-size: 1.25rem; margin-bottom: 0.5rem; }

        /* ── Modals ── */
        .modal-overlay { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:9999; animation:fadeIn .3s ease; }
        .modal-overlay.active { display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .modal { background: white; border-radius: 16px; max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp .3s ease; }
        .modal-header { background: linear-gradient(135deg, var(--primary) 0%, #0984e3 100%); color: white; padding: 1.5rem 2rem; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1; }
        .modal-header h2 { font-size: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; }
        .modal-close { background: rgba(255,255,255,0.2); border: none; color: white; font-size: 1.5rem; cursor: pointer; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all .2s; flex-shrink: 0; }
        .modal-close:hover { background: rgba(255,255,255,0.3); transform: rotate(90deg); }
        .modal-body { padding: 2rem; }
        .image-gallery { display: grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap: 1rem; margin-bottom: 2rem; }
        .image-item { position: relative; border-radius: 12px; overflow: hidden; aspect-ratio: 1; background: var(--bg-main); box-shadow: 0 4px 12px var(--shadow); cursor: pointer; transition: all .3s; }
        .image-item:hover { transform: translateY(-4px); box-shadow: 0 8px 24px var(--shadow-lg); }
        .image-item img { width: 100%; height: 100%; object-fit: cover; }
        .image-placeholder { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-secondary); font-size: 3rem; }
        .drive-fallback { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; gap:.5rem; color:#1a73e8; font-size:.8rem; font-weight:600; padding:1rem; text-align:center; }
        .drive-fallback i { font-size:2.5rem; }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .detail-item { background: var(--bg-main); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--secondary); }
        .detail-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); margin-bottom: 0.25rem; font-weight: 600; }
        .detail-value { font-size: 1rem; color: var(--text-primary); font-weight: 500; word-break: break-word; }
        .pullout-section { background: linear-gradient(135deg,#f8f9fa,#e9ecef); padding: 1.5rem; border-radius: 12px; border: 2px dashed var(--border); }
        .pullout-section h3 { margin-bottom: 1rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; }
        .pullout-form { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-group.full-width { grid-column: 1/-1; }
        .form-group label { font-size: 0.85rem; font-weight: 600; color: var(--text-primary); }
        .form-group label .required { color: var(--danger); margin-left: 2px; }
        .form-group input,.form-group textarea,.form-group select { padding: 0.75rem; border: 2px solid var(--border); border-radius: 8px; font-family: 'Space Grotesk',sans-serif; font-size: 0.9rem; transition: all .3s; width: 100%; }
        .form-group input:focus,.form-group textarea:focus,.form-group select:focus { outline: none; border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(0,184,148,0.1); }
        .form-group small { font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem; }
        .form-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid var(--border); flex-wrap: wrap; }
        .modal-actions { display: flex; gap: 1rem; justify-content: flex-end; padding: 1.5rem 2rem; border-top: 2px solid var(--border); background: var(--bg-main); border-radius: 0 0 16px 16px; flex-wrap: wrap; position: sticky; bottom: 0; }
        .edit-form { display: grid; grid-template-columns: repeat(auto-fit,minmax(250px,1fr)); gap: 1.5rem; }
        .password-lock-icon { text-align: center; margin-bottom: 1.5rem; }
        .password-lock-icon i { font-size: 3.5rem; color: var(--warning); }
        .password-error { color: var(--danger); font-size: 0.85rem; margin-top: 0.5rem; display: none; }
        .custom-select-wrapper { position: relative; }
        .custom-select-input { width: 100%; padding: 0.75rem; border: 2px solid var(--border); border-radius: 8px; font-family: 'Space Grotesk',sans-serif; font-size: 0.9rem; transition: all .3s; background: white; }
        .custom-select-input:focus { outline: none; border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(0,184,148,0.1); }
        .custom-dropdown { display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: white; border: 2px solid var(--secondary); border-radius: 8px; max-height: 220px; overflow-y: auto; z-index: 99999; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
        .dropdown-item { padding: 0.6rem 1rem; cursor: pointer; font-size: 0.9rem; color: var(--text-primary); transition: background .15s; }
        .dropdown-item:hover { background: rgba(0,184,148,0.1); color: var(--secondary); }
        .dropdown-item.selected-loc { background: rgba(0,184,148,0.08); color: var(--secondary); font-weight: 600; }
        .dropdown-item.no-match { color: var(--text-secondary); font-style: italic; cursor: default; }
        .dropdown-item.no-match:hover { background: none; color: var(--text-secondary); }

        /* ── Image Management Section ── */
        .img-mgmt-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--border); }
        .img-mgmt-title { margin-bottom: 1rem; color: var(--primary); display: flex; align-items: center; gap: .5rem; font-size: 1rem; font-weight: 700; }
        .edit-img-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: .75rem; margin-bottom: 1.25rem; min-height: 40px; }
        .edit-img-tile { position: relative; border-radius: 10px; overflow: hidden; aspect-ratio: 1; background: var(--bg-main); border: 2px solid var(--border); transition: all .2s; }
        .edit-img-tile:hover { border-color: var(--danger); box-shadow: 0 4px 12px rgba(214,48,49,.15); }
        .edit-img-tile img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .edit-img-tile .tile-icon { display: flex; align-items: center; justify-content: center; height: 100%; font-size: 2rem; color: #1a73e8; }
        .edit-img-tile .delete-img-btn { position: absolute; top: 4px; right: 4px; background: rgba(214,48,49,.88); border: none; color: white; border-radius: 6px; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; z-index: 2; opacity: 0; transition: opacity .2s; }
        .edit-img-tile:hover .delete-img-btn { opacity: 1; }
        .img-add-box { background: var(--bg-main); border: 2px dashed var(--border); border-radius: 10px; padding: 1.25rem; }
        .img-add-box-label { font-size: .82rem; font-weight: 700; color: var(--text-secondary); margin-bottom: .75rem; text-transform: uppercase; letter-spacing: .5px; }
        .img-add-row { display: flex; gap: .75rem; flex-wrap: wrap; align-items: flex-end; }
        .img-add-field { flex: 1; min-width: 160px; display: flex; flex-direction: column; gap: .35rem; }
        .img-add-field label { font-size: .8rem; font-weight: 600; color: var(--text-primary); }
        .img-add-field input[type="file"] { padding: .45rem .6rem; border: 2px solid var(--border); border-radius: 8px; font-size: .82rem; font-family: 'Space Grotesk', sans-serif; background: white; cursor: pointer; }
        .img-add-field input[type="text"] { padding: .6rem .75rem; border: 2px solid var(--border); border-radius: 8px; font-size: .85rem; font-family: 'Space Grotesk', sans-serif; transition: border-color .2s; }
        .img-add-field input[type="text"]:focus { outline: none; border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(0,184,148,.1); }
        .img-add-or { display: flex; align-items: center; padding: .4rem 0; font-size: .8rem; color: var(--text-secondary); font-weight: 700; flex-shrink: 0; }
        .img-loading { color: var(--text-secondary); font-size: .85rem; padding: .5rem; }
        .img-empty { color: var(--text-secondary); font-size: .85rem; padding: .5rem; font-style: italic; }

        @keyframes fadeIn  { from{opacity:0;}           to{opacity:1;} }
        @keyframes slideUp { from{opacity:0;transform:translateY(30px);} to{opacity:1;transform:translateY(0);} }
        @keyframes shake   { 0%,100%{transform:translateX(0);} 20%,60%{transform:translateX(-8px);} 40%,80%{transform:translateX(8px);} }
        .shake { animation: shake .4s ease; }

        @media(max-width:1200px){ .kpi-grid{grid-template-columns:repeat(2,1fr);} }
        @media(max-width:1024px){
            .content{ margin-left:88px; padding:1.5rem; }
            .sidebar:not(.close)~.content{ margin-left:88px; }
            thead th:nth-child(3), tbody td:nth-child(3){ display:none; }
        }
        @media(max-width:768px){
            .content{ margin-left:0; padding:1rem; padding-bottom:5rem; }
            .sidebar:not(.close)~.content{ margin-left:0; }
            .page-header{ padding:1.25rem; border-radius:12px; margin-bottom:1rem; }
            .page-header h1{ font-size:1.25rem; gap:.5rem; }
            .page-header h1 i{ font-size:1.5rem; }
            .kpi-grid{ grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:1rem; }
            .kpi-card{ padding:1rem; }
            .kpi-value{ font-size:1.5rem; }
            .controls-bar{ padding:1rem; margin-bottom:1rem; gap:.75rem; border-radius:10px; }
            .search-box{ min-width:100%; }
            .filter-group{ width:100%; gap:.5rem; }
            .filter-group select,.filter-group .custom-select-wrapper{ flex:1; min-width:0; }
            .btn{ padding:.75rem 1rem; font-size:.85rem; }
            .table-container{ border-radius:10px; }
            thead{ display:none; }
            tbody tr{ display:block; padding:1rem; border-bottom:2px solid var(--border); position:relative; }
            tbody tr:last-child{ border-bottom:none; }
            tbody td{ display:flex; justify-content:space-between; align-items:center; padding:.35rem 0; white-space:normal; font-size:.875rem; border:none; }
            tbody td::before{ content:attr(data-label); font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text-secondary); min-width:90px; flex-shrink:0; }
            tbody td.actions-cell{ justify-content:flex-end; padding-top:.5rem; margin-top:.25rem; border-top:1px solid var(--border); }
            tbody td.actions-cell::before{ display:none; }
            .modal-overlay.active{ align-items:flex-end; padding:0; }
            .modal{ border-radius:20px 20px 0 0; max-height:92vh; animation:slideUpMobile .35s ease; }
            .modal-header{ padding:1.25rem 1.5rem; border-radius:20px 20px 0 0; }
            .modal-header h2{ font-size:1.15rem; }
            .modal-body{ padding:1.25rem; }
            .modal-actions{ padding:1rem 1.25rem; border-radius:0; }
            .modal-actions .btn,.form-actions .btn{ flex:1; justify-content:center; }
            .detail-grid{ grid-template-columns:1fr 1fr; gap:.75rem; }
            .edit-form{ grid-template-columns:1fr; gap:1rem; }
            .edit-form .form-group.full-width{ grid-column:1; }
            .pullout-form{ grid-template-columns:1fr; gap:1rem; }
            .pullout-form .form-group.full-width{ grid-column:1; }
            .pullout-section{ padding:1rem; }
            .form-actions{ flex-direction:column-reverse; }
            .image-gallery{ grid-template-columns:repeat(2,1fr); gap:.75rem; }
            .edit-img-gallery{ grid-template-columns:repeat(3,1fr); gap:.5rem; }
            .img-add-row{ flex-direction:column; }
            .img-add-field{ min-width:100%; }
            .img-add-or{ justify-content:center; }
            .edit-img-tile .delete-img-btn{ opacity:1; }
        }
        @media(max-width:480px){
            .kpi-grid{ grid-template-columns:1fr 1fr; gap:.5rem; }
            .kpi-value{ font-size:1.3rem; }
            .detail-grid{ grid-template-columns:1fr; }
            .filter-group{ flex-direction:column; }
            .filter-group select,.filter-group .custom-select-wrapper{ width:100%; }
            .modal-actions{ flex-direction:column-reverse; }
            .modal-actions .btn{ width:100%; justify-content:center; }
            .edit-img-gallery{ grid-template-columns:repeat(3,1fr); }
        }
        @keyframes slideUpMobile{ from{opacity:0;transform:translateY(60px);} to{opacity:1;transform:translateY(0);} }
    </style>
</head>
<body>

<div class="content">
    <div class="page-header">
        <h1><i class='bx bx-package'></i> Inventory Management</h1>
        <p>Click on any row to view details • Manage and track all your assets</p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card blue">
            <div class="kpi-icon"><i class='bx bx-check-shield'></i></div>
            <div class="kpi-info">
                <div class="kpi-value" id="totalAssets">0</div>
                <div class="kpi-label">Working Assets</div>
                <div class="kpi-sub" id="kpiSubRecords">functional items</div>
            </div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-icon"><i class='bx bx-package'></i></div>
            <div class="kpi-info">
                <div class="kpi-value" id="activeItems">0</div>
                <div class="kpi-label">Total Assets</div>
                <div class="kpi-sub" id="kpiSubAssets">available units</div>
            </div>
        </div>
        <div class="kpi-card purple">
            <div class="kpi-icon"><i class='bx bx-transfer-alt'></i></div>
            <div class="kpi-info">
                <div class="kpi-value" id="pulledOut">0</div>
                <div class="kpi-label">In Transit</div>
                <div class="kpi-sub">pending transfers</div>
            </div>
        </div>
        <div class="kpi-card orange">
            <div class="kpi-icon"><i class='bx bx-time-five'></i></div>
            <div class="kpi-info">
                <div class="kpi-value" id="forChecking">0</div>
                <div class="kpi-label">For Checking</div>
                <div class="kpi-sub" id="kpiSubChecking">need attention</div>
            </div>
        </div>
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
            <div class="custom-select-wrapper" style="position:relative;min-width:160px;">
                <input type="text" id="locationSearchInput" class="btn btn-secondary"
                       style="min-width:160px;cursor:text;text-align:left;appearance:none;padding-right:2rem;width:100%;"
                       placeholder="All Locations" autocomplete="off"
                       oninput="filterLocPanel()" onfocus="showLocPanel()" onblur="hideLocPanel()">
                <i class='bx bx-map-pin' style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);color:var(--text-secondary);pointer-events:none;"></i>
                <div class="custom-dropdown" id="locPanelDropdown"></div>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-wrapper">
            <table id="inventoryTable">
                <thead>
                    <tr>
                        <th>Category / Type</th><th>Brand / Model</th><th>Serial No</th>
                        <th>Cond</th><th>Status</th><th>Location</th><th>Active</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="inventoryBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     View Modal
══════════════════════════════════════════════════════════════ -->
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

<!-- ══════════════════════════════════════════════════════════════
     Admin Password Modal
══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="adminPasswordModal">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <h2><i class='bx bx-lock-alt'></i> Admin Verification</h2>
            <button class="modal-close" onclick="closeModal('adminPasswordModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <div class="password-lock-icon"><i class='bx bx-shield-quarter'></i></div>
            <p style="text-align:center;color:var(--text-secondary);margin-bottom:1.5rem;font-size:.95rem;">Enter admin password to edit this asset.</p>
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

<!-- ══════════════════════════════════════════════════════════════
     Edit Modal  (with Image Management section)
══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h2><i class='bx bx-edit'></i> Edit Asset</h2>
            <button class="modal-close" onclick="closeModal('editModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">

            <!-- ── Image Management ──────────────────────────────────────── -->
            <div class="img-mgmt-section" style="margin-top:0;padding-top:0;border-top:none;margin-bottom:2rem;padding-bottom:1.5rem;border-bottom:2px solid var(--border);">
                <h4 class="img-mgmt-title"><i class='bx bx-image-alt'></i> Manage Photos</h4>

                <!-- Existing images gallery -->
                <div class="edit-img-gallery" id="editImgGallery">
                    <div class="img-loading"><i class='bx bx-loader-alt bx-spin'></i> Loading photos…</div>
                </div>

                <!-- Add new image -->
                <div class="img-add-box">
                    <div class="img-add-box-label"><i class='bx bx-plus-circle'></i> Add New Photo</div>
                    <div class="img-add-row">
                        <div class="img-add-field">
                            <label>Upload File <small style="font-weight:400;">(JPG / PNG / WEBP, max 5MB)</small></label>
                            <input type="file" id="newImgFile" accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>
                        <div class="img-add-or">OR</div>
                        <div class="img-add-field" style="flex:2;">
                            <label>Google Drive / Sheets URL</label>
                            <input type="text" id="newImgUrl" placeholder="https://drive.google.com/file/d/...">
                        </div>
                        <button type="button" class="btn btn-primary" onclick="addAssetImage()" style="flex-shrink:0;padding:.65rem 1.25rem;align-self:flex-end;">
                            <i class='bx bx-upload'></i> Upload
                        </button>
                    </div>
                </div>
            </div>
            <!-- ── end Image Management ──────────────────────────────────── -->

            <!-- Asset fields -->
            <form id="editForm" class="edit-form">
                <input type="hidden" name="asset_id" id="editAssetId">
                <div class="form-group"><label>Brand</label><input type="text" name="brand" id="editBrand" placeholder="e.g. Dell, HP, Acer"></div>
                <div class="form-group"><label>Model</label><input type="text" name="model" id="editModel" placeholder="e.g. Latitude 5520"></div>
                <div class="form-group"><label>Serial Number</label><input type="text" name="serial_number" id="editSerial" placeholder="Serial / Asset Tag"></div>
                <div class="form-group"><label>Condition</label><select name="condition" id="editCondition"><option value="NEW">New</option><option value="USED">Used</option></select></div>
                <div class="form-group"><label>Status</label><select name="status" id="editStatus"><option value="WORKING">Working</option><option value="NOT WORKING">Not Working</option><option value="FOR CHECKING">For Checking</option></select></div>
                <div class="form-group"><label>Location</label><input type="text" name="location" id="editLocation" placeholder="e.g. Warehouse A"></div>
                <div class="form-group"><label>Sub-Location</label><input type="text" name="sub_location" id="editSubLocation" placeholder="e.g. Shelf 3"></div>
                <div class="form-group full-width"><label>Description</label><textarea name="description" id="editDescription" rows="3" placeholder="Additional notes..."></textarea></div>
                <div class="form-group"><label>Beginning Balance</label><input type="number" name="beg_balance_count" id="editBegBalance" min="0" placeholder="e.g. 10"><small>Total stock at start.</small></div>
                <div class="form-group"><label>Active Count</label><input type="number" name="active_count" id="editActiveCount" min="0" placeholder="e.g. 8" oninput="previewBegBalance()"><small id="activeCountHint">Current available items. Adjusts Beginning Balance automatically.</small></div>
            </form>

        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal('editModal')"><i class='bx bx-x'></i> Cancel</button>
            <button class="btn btn-primary" onclick="submitEditAsset()"><i class='bx bx-save'></i> Save Changes</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     Transfer Modal
══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="pulloutModal">
    <div class="modal" style="max-width:780px;">
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
                            <input type="text" id="from_search" class="custom-select-input" readonly style="background:#f1f2f6;color:var(--text-secondary);cursor:not-allowed;">
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
                        <input type="text" name="from_sub_location" id="from_sub_location_value" readonly style="background:#f1f2f6;color:var(--text-secondary);cursor:not-allowed;">
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

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ── Location panel ────────────────────────────────────────────────────────────
function buildLocationMap(data) {
    const map = {};
    data.forEach(item => { const loc=(item.location||'').trim(); if(!loc)return; map[loc]=(map[loc]||0)+(parseInt(item.active_count)||0); });
    return map;
}
function buildLocPanelDropdown(term) {
    const dd=document.getElementById('locPanelDropdown');
    const map=buildLocationMap(inventoryData);
    const matches=LOCATIONS.filter(l=>!term||l.toLowerCase().includes(term.toLowerCase().trim()));
    dd.innerHTML='';
    const allItem=document.createElement('div');
    allItem.className='dropdown-item'+(!selectedLocation?' selected-loc':'');
    allItem.innerHTML='<em style="color:var(--text-secondary);">All Locations</em>';
    allItem.onmousedown=()=>{selectedLocation='';document.getElementById('locationSearchInput').value='';dd.style.display='none';loadInventory();};
    dd.appendChild(allItem);
    if(!matches.length){dd.innerHTML+='<div class="dropdown-item no-match">No results found</div>';}
    else{matches.forEach(loc=>{const item=document.createElement('div');const count=map[loc]!==undefined?map[loc]:0;item.className='dropdown-item'+(loc===selectedLocation?' selected-loc':'');item.innerHTML=`${loc}<span style="float:right;opacity:.45;font-size:.78rem;margin-left:.5rem;">${count>0?count+' active':''}</span>`;item.onmousedown=()=>selectLocPanel(loc);dd.appendChild(item);});}
}
function filterLocPanel(){buildLocPanelDropdown(document.getElementById('locationSearchInput').value);document.getElementById('locPanelDropdown').style.display='block';}
function showLocPanel(){buildLocPanelDropdown(document.getElementById('locationSearchInput').value);document.getElementById('locPanelDropdown').style.display='block';}
function hideLocPanel(){setTimeout(()=>{document.getElementById('locPanelDropdown').style.display='none';document.getElementById('locationSearchInput').value=selectedLocation||'';},200);}
function selectLocPanel(loc){selectedLocation=loc;document.getElementById('locationSearchInput').value=loc;document.getElementById('locPanelDropdown').style.display='none';loadInventory();}

// ── Inventory load & render ───────────────────────────────────────────────────
async function loadInventory() {
    try {
        const params=new URLSearchParams({action:'get_inventory',search:document.getElementById('searchInput').value,category:document.getElementById('categoryFilter').value,status:document.getElementById('statusFilter').value,location:selectedLocation});
        const response=await fetch(`inventory_api.php?${params}`);
        const result=await response.json();
        if(result.success){inventoryData=result.data;renderInventoryTable();updateStats();}
        else showNotification('Error loading inventory','error');
    }catch(error){showNotification('Failed to load inventory data','error');}
}

function highlightText(text,term){if(!term||!text)return text||'';const escaped=term.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');return String(text).replace(new RegExp(`(${escaped})`,'gi'),'<mark>$1</mark>');}

function renderInventoryTable() {
    const tbody=document.getElementById('inventoryBody');
    const searchTerm=document.getElementById('searchInput').value.trim();
    tbody.innerHTML='';
    if(!inventoryData.length){tbody.innerHTML=`<tr><td colspan="8" style="text-align:center;padding:3rem;white-space:normal;"><div class="empty-state"><i class='bx bx-package'></i><h3>No assets found${selectedLocation?` in "${selectedLocation}"`:''}}</h3><p>Try adjusting your filters</p></div></td></tr>`;return;}
    inventoryData.forEach(item=>{
        const activeCount=parseInt(item.active_count)||0;
        const h=(val)=>highlightText(val,searchTerm);
        const row=document.createElement('tr');
        row.setAttribute('data-id',item.id);
        row.onclick=function(e){if(!e.target.closest('.action-btn'))openViewModal(item.id);};
        row.innerHTML=`
            <td data-label="Category"><div class="location-cell"><div class="location-main">${h(item.category)||'N/A'}</div>${item.asset_type?`<div class="location-sub">${h(item.asset_type)}</div>`:''}</div></td>
            <td data-label="Brand / Model"><div class="location-cell"><div class="location-main">${h(item.brand)||'N/A'}</div>${item.model?`<div class="location-sub">${h(item.model)}</div>`:''}</div></td>
            <td data-label="Serial No"><code class="badge">${h(item.serial_number)||'N/A'}</code></td>
            <td data-label="Condition"><span class="badge badge-${(item.condition||'').toLowerCase()}">${item.condition||'N/A'}</span></td>
            <td data-label="Status"><span class="badge badge-${(item.status||'').toLowerCase().replace(' ','-')}">${item.status||'N/A'}</span></td>
            <td data-label="Location"><div class="location-cell"><div class="location-main">${h(item.location)||'N/A'}</div>${item.sub_location?`<div class="location-sub">${h(item.sub_location)}</div>`:''}</div></td>
            <td data-label="Active"><strong style="color:var(--secondary);font-size:1.1rem;">${activeCount}</strong></td>
            <td class="actions-cell">
                <button class="action-btn edit" onclick="event.stopPropagation();openAdminPasswordModal(${item.id})" title="Edit Asset"><i class='bx bx-edit'></i></button>
                <button class="action-btn pullout" onclick="event.stopPropagation();openPulloutModal(${item.id})" title="Transfer" ${activeCount<=0?'disabled style="opacity:.3;cursor:not-allowed;"':''}>
                    <i class='bx bx-transfer-alt'></i>
                </button>
            </td>`;
        tbody.appendChild(row);
    });
}

function updateStats() {
    const records  = inventoryData.length;
    const working  = inventoryData.filter(i=>i.status==='WORKING').length;
    const total    = inventoryData.reduce((s,i)=>s+(parseInt(i.active_count)||0),0);
    const transit  = inventoryData.reduce((s,i)=>s+(parseInt(i.for_pullout)||0),0);
    const checking = inventoryData.filter(i=>i.status==='FOR CHECKING').length;
    const notWork  = inventoryData.filter(i=>i.status==='NOT WORKING').length;
    document.getElementById('totalAssets').textContent   = working;
    document.getElementById('activeItems').textContent   = total;
    document.getElementById('pulledOut').textContent     = transit;
    document.getElementById('forChecking').textContent   = checking;
    document.getElementById('kpiSubRecords').textContent  = working === 1 ? '1 functional item' : `${working} of ${records} records`;
    document.getElementById('kpiSubAssets').textContent   = `${total} available units`;
    document.getElementById('kpiSubChecking').textContent = notWork > 0 ? `${notWork} not working` : 'need attention';
}

// ── View Modal ────────────────────────────────────────────────────────────────
async function openViewModal(id) {
    try {
        currentAssetId=id;
        const response=await fetch(`inventory_api.php?action=get_asset_details&asset_id=${id}`);
        const result=await response.json();
        if(!result.success){showNotification('Failed to load asset details','error');return;}
        const item=result.data;

        const gallery=document.getElementById('imageGallery');
        gallery.innerHTML='';
        if(item.images && item.images.length > 0){
            item.images.forEach(img => {
                const tile = document.createElement('div');
                tile.className = 'image-item';
                if(img.type === 'gdrive' && img.thumb){
                    tile.style.cssText = 'cursor:pointer;';
                    tile.onclick = () => window.open(img.url, '_blank');
                    tile.innerHTML = `<img src="${escHtml(img.thumb)}" alt="Asset Photo" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.innerHTML='<div class=\\'drive-fallback\\'><i class=\\'bx bxl-google\\'></i><span>View on<br>Google Drive</span></div>'">`;
                } else if(img.type === 'gdrive_folder' || (img.type === 'gdrive' && !img.thumb)){
                    tile.style.cssText = 'display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#e8f5e9,#f1f8e9);border:2px solid #a5d6a7;cursor:pointer;';
                    tile.onclick = () => window.open(img.url, '_blank');
                    tile.innerHTML = `<div style="text-align:center;padding:1rem;"><i class='bx bxl-google' style="font-size:2.5rem;color:#1a73e8;display:block;margin-bottom:.5rem;"></i><span style="font-size:.78rem;font-weight:600;color:#1a73e8;line-height:1.3;display:block;">View on<br>Google Drive</span></div>`;
                } else {
                    tile.innerHTML = `<img src="${escHtml(img.url)}" alt="Asset Image" onclick="window.open('${escHtml(img.url)}','_blank')" style="width:100%;height:100%;object-fit:cover;">`;
                }
                gallery.appendChild(tile);
            });
        } else {
            gallery.innerHTML = `<div class="image-item"><div class="image-placeholder"><i class='bx bx-image'></i></div></div>`;
        }

        document.getElementById('assetDetails').innerHTML=`
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
            <div class="detail-item"><div class="detail-label">QR Code</div><div class="detail-value"><code>${item.qr_code||'N/A'}</code></div></div>`;

        const availableQty=parseInt(item.active_count)||0;
        const pulloutBtn=document.getElementById('viewModalPulloutBtn');
        pulloutBtn.disabled=availableQty<=0;pulloutBtn.style.opacity=availableQty<=0?'0.5':'1';pulloutBtn.style.cursor=availableQty<=0?'not-allowed':'pointer';
        document.getElementById('viewModal').classList.add('active');
    }catch(error){showNotification('Failed to load asset details','error');}
}

function openEditFromView(){const id=currentAssetId;closeModal('viewModal');openAdminPasswordModal(id);}
function openPulloutFromView(){const id=currentAssetId;closeModal('viewModal');openPulloutModal(id);}

// ── Admin Password Modal ──────────────────────────────────────────────────────
function openAdminPasswordModal(id){pendingEditAssetId=id;document.getElementById('adminPasswordInput').value='';document.getElementById('passwordError').style.display='none';document.getElementById('adminPasswordModal').classList.add('active');setTimeout(()=>document.getElementById('adminPasswordInput').focus(),200);}

async function verifyAdminPassword(){
    const password=document.getElementById('adminPasswordInput').value;if(!password)return;
    try{
        const response=await fetch('inventory_api.php?action=verify_admin_password',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({password})});
        const result=await response.json();
        if(result.success){closeModal('adminPasswordModal');openEditModal(pendingEditAssetId);}
        else{document.getElementById('passwordError').style.display='block';const input=document.getElementById('adminPasswordInput');input.classList.add('shake');input.value='';setTimeout(()=>input.classList.remove('shake'),400);}
    }catch(error){showNotification('Verification failed. Try again.','error');}
}

// ── Edit Modal ────────────────────────────────────────────────────────────────
async function openEditModal(id){
    try{
        currentAssetId=id;
        const response=await fetch(`inventory_api.php?action=get_asset_details&asset_id=${id}`);
        const result=await response.json();if(!result.success){showNotification('Failed to load asset details','error');return;}
        const item=result.data;
        document.getElementById('editAssetId').value=item.id;
        document.getElementById('editBrand').value=item.brand||'';
        document.getElementById('editModel').value=item.model||'';
        document.getElementById('editSerial').value=item.serial_number||'';
        document.getElementById('editCondition').value=item.condition||'NEW';
        document.getElementById('editStatus').value=item.status||'WORKING';
        document.getElementById('editLocation').value=item.location||'';
        document.getElementById('editSubLocation').value=item.sub_location||'';
        document.getElementById('editDescription').value=item.description||'';
        document.getElementById('editBegBalance').value=item.beg_balance_count??'';
        document.getElementById('editActiveCount').value=item.active_count??'';
        _editReleasedCount=parseInt(item.for_pullout)||0;
        _editReturnedCount=parseInt(item.beg_balance_count)-parseInt(item.active_count)-_editReleasedCount||0;
        document.getElementById('activeCountHint').textContent='Current available items. Adjusts Beginning Balance automatically.';
        document.getElementById('activeCountHint').style.color='';
        // Reset image inputs
        document.getElementById('newImgFile').value='';
        document.getElementById('newImgUrl').value='';
        document.getElementById('editModal').classList.add('active');
        // Load image gallery for this asset
        loadEditImages(id);
    }catch(error){showNotification('Failed to open edit form','error');}
}

async function submitEditAsset(){
    const data=Object.fromEntries(new FormData(document.getElementById('editForm')).entries());
    try{
        const response=await fetch('inventory_api.php?action=update_asset',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
        const result=await response.json();
        if(result.success){showNotification('Asset updated successfully!','success');closeModal('editModal');loadInventory();}
        else showNotification(result.error||'Failed to update asset','error');
    }catch(error){showNotification('Failed to update asset','error');}
}

// ── Image Management ──────────────────────────────────────────────────────────
async function loadEditImages(assetId) {
    const gallery = document.getElementById('editImgGallery');
    gallery.innerHTML = '<div class="img-loading"><i class=\'bx bx-loader-alt bx-spin\'></i> Loading photos…</div>';
    try {
        const res    = await fetch(`inventory_api.php?action=get_asset_details&asset_id=${assetId}`);
        const result = await res.json();
        if (!result.success) {
            gallery.innerHTML = '<div class="img-loading" style="color:var(--danger);">Failed to load photos.</div>';
            return;
        }
        renderEditGallery(assetId, result.data.images || []);
    } catch(e) {
        gallery.innerHTML = '<div class="img-loading" style="color:var(--danger);">Error loading photos.</div>';
    }
}

function renderEditGallery(assetId, images) {
    const gallery = document.getElementById('editImgGallery');
    gallery.innerHTML = '';

    if (!images.length) {
        gallery.innerHTML = '<div class="img-empty">No photos yet. Add one below.</div>';
        return;
    }

    images.forEach(img => {
        const tile = document.createElement('div');
        tile.className = 'edit-img-tile';

        let inner = '';
        if (img.type === 'gdrive' && img.thumb) {
            inner = `<img src="${escHtml(img.thumb)}" alt="Photo"
                         onerror="this.parentElement.querySelector('.tile-icon').style.display='flex';this.style.display='none';">
                     <div class="tile-icon" style="display:none;"><i class='bx bxl-google'></i></div>`;
        } else if (img.type === 'image' && img.url) {
            inner = `<img src="${escHtml(img.url)}" alt="Photo">`;
        } else {
            inner = `<div class="tile-icon"><i class='bx bxl-google'></i></div>`;
        }

        const imageId = img.image_id || 0;
        tile.innerHTML = `
            ${inner}
            <button class="delete-img-btn" title="Delete photo"
                    onclick="deleteAssetImage(${assetId}, ${imageId})">
                <i class='bx bx-trash'></i>
            </button>`;
        gallery.appendChild(tile);
    });
}

async function deleteAssetImage(assetId, imageId) {
    if (!imageId) { showNotification('Cannot delete — image ID not found.', 'error'); return; }
    if (!confirm('Delete this photo? This cannot be undone.')) return;
    try {
        const res    = await fetch('inventory_api.php?action=delete_asset_image', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ asset_id: assetId, image_id: imageId })
        });
        const result = await res.json();
        if (result.success) { showNotification('Photo deleted.', 'success'); loadEditImages(assetId); }
        else showNotification(result.error || 'Delete failed.', 'error');
    } catch(e) { showNotification('Delete failed.', 'error'); }
}

async function addAssetImage() {
    const assetId   = parseInt(document.getElementById('editAssetId').value);
    const fileInput = document.getElementById('newImgFile');
    const urlInput  = document.getElementById('newImgUrl');

    if (!assetId) { showNotification('No asset selected.', 'error'); return; }

    const formData = new FormData();
    formData.append('asset_id', assetId);

    if (fileInput.files.length > 0) {
        formData.append('image', fileInput.files[0]);
    } else if (urlInput.value.trim()) {
        formData.append('drive_url', urlInput.value.trim());
    } else {
        showNotification('Please select a file or enter a Drive URL.', 'error');
        return;
    }

    try {
        const res    = await fetch('inventory_api.php?action=add_asset_image', { method:'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            showNotification('Photo added!', 'success');
            fileInput.value = '';
            urlInput.value  = '';
            loadEditImages(assetId);
        } else {
            showNotification(result.error || 'Upload failed.', 'error');
        }
    } catch(e) { showNotification('Upload failed.', 'error'); }
}

// ── Pullout / Transfer Modal ──────────────────────────────────────────────────
async function openPulloutModal(id){
    try{
        currentAssetId=id;
        const response=await fetch(`inventory_api.php?action=get_asset_details&asset_id=${id}`);
        const result=await response.json();if(!result.success){showNotification('Failed to load asset details','error');return;}
        const item=result.data;const availableQty=parseInt(item.active_count)||0;
        if(availableQty<=0){showNotification('No available items to transfer','error');return;}
        document.getElementById('modalAssetId').value=item.id;document.getElementById('available_qty').textContent=availableQty;document.getElementById('pullout_quantity').max=availableQty;document.getElementById('pullout_quantity').value=Math.min(1,availableQty);
        const today=new Date().toISOString().split('T')[0];document.querySelector('input[name="date_needed"]').min=today;
        const assetLoc=(item.location||'').trim();const assetSubLoc=(item.sub_location||'').trim();
        if(assetLoc){document.getElementById('from_search').value=assetLoc;document.getElementById('from_location_value').value=assetLoc;}
        if(assetSubLoc){document.getElementById('from_sub_location_value').value=assetSubLoc;}
        document.getElementById('pulloutModal').classList.add('active');
    }catch(error){showNotification('Failed to open transfer form','error');}
}

// ── Close Modal ───────────────────────────────────────────────────────────────
function closeModal(modalId){
    document.getElementById(modalId).classList.remove('active');
    if(modalId==='pulloutModal') resetTransferForm();
    if(modalId!=='adminPasswordModal') currentAssetId=null;
}

// ── Location + Dropdown data ──────────────────────────────────────────────────
const LOCATIONS=["QC WareHouse","Pulilan WareHouse","CFE","CFE 2","GSI","ENT Vertis","CBTL A30","CBTL FLZ","CBTL TRK","CBTL TR3","CBTL FVW","CBTL UT2","CBTL UTC","NN UTC","CBTL RNG","CBTL GB5","CBTL BHS","CBTL ANE","CBTL ATC","CBTL CLN","CBTL LN2","CBTL GFD","CBTL SMA","NN SMA","CBTL RAO","NN RAO","CBTL SFW","NN SFW","CBTL RPP","CBTL MID","CBTL MPF","CBTL GAL","CBTL KCT","CBTL RGS","CBTL MRQ","NN CLN","CBTL GL2","CBTL CIT","CBTL NUV","CBTL SPK","CBTL SRN","CBTL MAG","CBTL AB1","NN ABA","NN VER","CBTL VER","NN NUV","CBTL HPT","CBTL CAP","CBTL B11","CBTL CBL","CBTL AY2","CBTL AYT","CBTL RGC","CBTL RCY","CBTL SEA","CBTL CRO","CBTL CR2","CBTL SCP","CBTL GML","CBTL ABZ","CBTL LAN","NN EVO","CBTL SMC","CBTL RWL","CBTL STM","NN LCT","CBTL VRT","CBTL RGT","DSY HSS","DSY OPU","NN FIL","CBTL MIT","NN RML","NN EVI","CBTL MCK","CBTL SER","DSY OKA","CBTL MPL","CBTL MOA","NN OKA","NN UPM","CBTL UPM","CBTL NPK","CBTL PDM","CBTL MG2","CBTL MZA","CBTL JAZ","CBTL PQL","NN GWM","CBTL GWM","CBTL GWC","NN HSS","CBTL SGC","CBTL TOL","CBTL AUR","CBTL LPA","CBTL SMB","CBTL PSG","CBTL BWG","CBTL TNZ","CBTL FMM","CBTL SLN","NN LGC","CBTL LGC","CBTL RSJ","CBTL FIL","CBTL NDT","CBTL ACA","CBTL FSH","CBTL CEL","NN RWL","CBTL LAC","CBTL CRK","CBTL MAK","CBTL BTN","CBTL CAU","CBTL HAI","CBTL SSJ","CBTL SEO","CBTL SHW","CBTL MAX","CBTL SMX","NN KLT","NN 3CN","NN GFD","CBTL SLE","CBTL NEO","CBTL SOL","CBTL BIC","NN BIC","NN TOL","CBTL EWM","CBTL CL1","CBTL CL2","CBTL SMP","CBTL TEL","CBTL SMS","CBTL SMD","CBTL SMI","CBTL SMN","CBTL MEG","NN SSR","CBTL SSR","NN SGC","CBTL WML","CBTL ORT","CBTL ADO","CBTL MSB","NN OPU","NN GAL","CBTL SMG","CBTL SPP","CBTL FWI","CBTL ATR","CBTL BSD","CBTL SBD","CBTL CHM","CBTL EB1","CBTL EB2","CBTL LSP","CBTL TEG","CBTL CH2","CBTL AZL","CBTL LKK","CBTL KCC","CBTL KCZ","NN PDM","NN VIA","CBTL SMN-DS","CBTL AOA","CBTL BDO","CBTL BSM","CBTL BUR","CBTL DDP","CBTL DFO","CBTL DLS","CBTL ENB","CBTL KAT","CBTL LAP","CBTL MOR","CBTL OPD","CBTL SAL","CBTL SLX","CBTL SX2","CBTL WTC","CBTL TRG","CBTL UPN","CBTL UST","CBTL VRP","CBTL PHN","CBTL PHL","CBTL SMS-DS","CBTL WFG","CBTL SMM-DS","CBTL MEN","CBTL SSR-DS","CBTL SFW-DS","CBTL SBT-DS","CBTL SNV-DS","CBTL SSJ-DS","CBTL SRS-DS","CBTL STM-DS","CBTL TNZ-DS","CBTL SRO-DS","CBTL WT1","CBTL OLO-DS","CBTL SUC-DS","CBTL CAU-DS","CBTL SMT-DS","CBTL SPP-DS","CBTL DAE-DS","CBTL MAS-DS","CBTL ROX-DS","CBTL MLO-DS","CBTL CLC-DS","CBTL DVO-DS","CBTL MEG-DS","CBTL SEA-DS","CBTL CON-DS","CBTL SMC-DS","CBTL CLK-DS","CBTL TAR-DS","CBTL ILO-DS","CBTL BCD-DS","CBTL LAN-DS","CBTL SLZ-DS","CBTL BAG-DS","CBTL MKT-DS","CBTL MPO-DS","CBTL TUG-DS","CBTL CUB-DS","CBTL BFP-DS","CBTL BIC-DS","CBTL AUR-DS","CBTL MRK-DS","CBTL LOG-DS","CBTL SGC-DS","CBTL MSA-DS","CBTL BWG-DS","CBTL BTN-DS","CBTL TRC-DS","CBTL SBR2-DS","CBTL EST-DS","CBTL TAY-DS","CBTL SPB-DS","CBTL TEL-DS","CBTL BYD-DS","CBTL SEO-DS","CBTL SCP-DS","CBTL SFN-DS","CBTL LUC-DS","CBTL LUN-DS"];
const PURPOSES=["Testing","Installation","Setup","Defective","Service Unit","Buffer"];
const REQUESTERS=["Allen","Arbie","Arwin","Christian","Darwin","Don","Dyzel","Gerr","Gheo","Gilbert","Jackie","Jake","JB","JC","Jessica","Jubilee","Lea","Mar","Patrick","Princess","Ricky","Ron","Rai","Toto","Admin"];
const DROPDOWN_CONFIG={from:{data:LOCATIONS,searchId:'from_search',valueId:'from_location_value',dropdownId:'from_dropdown'},to:{data:LOCATIONS,searchId:'to_search',valueId:'to_location_value',dropdownId:'to_dropdown'},purpose:{data:PURPOSES,searchId:'purpose_search',valueId:'purpose_value',dropdownId:'purpose_dropdown'},requested_by:{data:REQUESTERS,searchId:'requested_by_search',valueId:'requested_by_value',dropdownId:'requested_by_dropdown'}};

function buildDropdown(key){const cfg=DROPDOWN_CONFIG[key];const dd=document.getElementById(cfg.dropdownId);const term=document.getElementById(cfg.searchId).value.toLowerCase().trim();const matches=cfg.data.filter(v=>v.toLowerCase().includes(term));dd.innerHTML='';if(!matches.length){dd.innerHTML='<div class="dropdown-item no-match">No results found</div>';}else{matches.forEach(val=>{const item=document.createElement('div');item.className='dropdown-item';item.textContent=val;item.onmousedown=()=>selectDropdownItem(key,val);dd.appendChild(item);});}}
function filterDropdown(key){buildDropdown(key);document.getElementById(DROPDOWN_CONFIG[key].valueId).value='';document.getElementById(DROPDOWN_CONFIG[key].dropdownId).style.display='block';}
function showDropdown(key){buildDropdown(key);document.getElementById(DROPDOWN_CONFIG[key].dropdownId).style.display='block';}
function hideDropdown(key){setTimeout(()=>{document.getElementById(DROPDOWN_CONFIG[key].dropdownId).style.display='none';if(!document.getElementById(DROPDOWN_CONFIG[key].valueId).value)document.getElementById(DROPDOWN_CONFIG[key].searchId).value='';},200);}
function selectDropdownItem(key,val){document.getElementById(DROPDOWN_CONFIG[key].searchId).value=val;document.getElementById(DROPDOWN_CONFIG[key].valueId).value=val;document.getElementById(DROPDOWN_CONFIG[key].dropdownId).style.display='none';}
function resetTransferForm(){document.getElementById('pulloutForm').reset();Object.keys(DROPDOWN_CONFIG).forEach(key=>{document.getElementById(DROPDOWN_CONFIG[key].searchId).value='';document.getElementById(DROPDOWN_CONFIG[key].valueId).value='';document.getElementById(DROPDOWN_CONFIG[key].dropdownId).style.display='none';});document.getElementById('from_sub_location_value').value='';}

async function submitPullout(){
    const form=document.getElementById('pulloutForm');const formData=new FormData(form);
    const data={asset_id:formData.get('asset_id'),quantity:parseInt(formData.get('quantity')),date_needed:formData.get('date_needed'),from_location:formData.get('from_location'),from_sub_location:(document.getElementById('from_sub_location_value').value||'').trim(),to_location:formData.get('to_location'),purpose:formData.get('purpose'),requested_by:formData.get('requested_by')};
    const availableQty=parseInt(document.getElementById('available_qty').textContent);
    if(data.quantity>availableQty){showNotification(`Cannot transfer ${data.quantity} items. Only ${availableQty} available.`,'error');return;}
    const missing=[];if(!data.quantity||data.quantity<1)missing.push('Quantity');if(!data.date_needed)missing.push('Date Needed');if(!data.from_location)missing.push('From (Source)');if(!data.to_location)missing.push('To (Recipient)');if(!data.purpose)missing.push('Purpose');if(!data.requested_by)missing.push('Requested By');
    if(missing.length>0){showNotification(`Please fill in: ${missing.join(', ')}`,'error');return;}
    if(data.from_location===data.to_location){showNotification('Source and recipient location cannot be the same.','error');return;}
    try{
        const response=await fetch('inventory_api.php?action=submit_pullout',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
        const result=await response.json();
        if(result.success){showNotification(`Transfer request submitted for ${data.quantity} item(s)!`,'success');closeModal('pulloutModal');loadInventory();}
        else showNotification(result.error||'Failed to submit transfer request','error');
    }catch(error){showNotification('Failed to submit transfer request','error');}
}

// ── Notifications ─────────────────────────────────────────────────────────────
function showNotification(message,type='info'){
    const n=document.createElement('div');
    n.style.cssText=`position:fixed;top:20px;right:20px;left:20px;padding:1rem 1.5rem;background:${type==='success'?'var(--secondary)':'var(--danger)'};color:white;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.2);z-index:10000;animation:slideIn .3s ease;font-weight:500;max-width:400px;margin:0 auto;`;
    if(window.innerWidth>600){n.style.left='auto';}
    n.textContent=message;document.body.appendChild(n);
    setTimeout(()=>{n.style.animation='slideOut .3s ease';setTimeout(()=>n.remove(),300);},4000);
}

async function loadCategories(){try{const response=await fetch('inventory_api.php?action=get_categories');const result=await response.json();if(result.success){const select=document.getElementById('categoryFilter');result.data.forEach(cat=>{const o=document.createElement('option');o.value=cat.id;o.textContent=cat.name;select.appendChild(o);});}}catch(error){console.error('Error loading categories:',error);}}

// ── Event listeners ───────────────────────────────────────────────────────────
document.getElementById('searchInput').addEventListener('input',function(){renderInventoryTable();clearTimeout(window._searchTimer);window._searchTimer=setTimeout(loadInventory,300);});
document.getElementById('categoryFilter').addEventListener('change',loadInventory);
document.getElementById('statusFilter').addEventListener('change',loadInventory);
document.querySelectorAll('.modal-overlay').forEach(overlay=>{overlay.addEventListener('click',function(e){if(e.target===this)closeModal(this.id);});});
document.addEventListener('DOMContentLoaded',function(){loadCategories();loadInventory();});

// ── Beginning Balance preview ─────────────────────────────────────────────────
let _editReleasedCount=0,_editReturnedCount=0;
function previewBegBalance(){const activeVal=parseInt(document.getElementById('editActiveCount').value);const hint=document.getElementById('activeCountHint');if(isNaN(activeVal)||activeVal<0){hint.textContent='Current available items. Adjusts Beginning Balance automatically.';hint.style.color='';return;}const newBeg=activeVal+_editReleasedCount-_editReturnedCount;if(newBeg<0){hint.innerHTML=`⚠️ Cannot set active to <strong>${activeVal}</strong> — would require a negative beginning balance.`;hint.style.color='var(--danger)';}else{hint.innerHTML=`Beginning Balance will be set to <strong>${newBeg}</strong> (active ${activeVal} + released ${_editReleasedCount} − returned ${_editReturnedCount}).`;hint.style.color='var(--secondary)';}}

// ── Keyframe animations ───────────────────────────────────────────────────────
const style=document.createElement('style');
style.textContent=`
    @keyframes slideIn  { from{transform:translateX(100%);opacity:0;} to{transform:translateX(0);opacity:1;} }
    @keyframes slideOut { from{transform:translateX(0);opacity:1;} to{transform:translateX(100%);opacity:0;} }
`;
document.head.appendChild(style);
</script>
</body>
</html>
<?php ob_end_flush(); ?>