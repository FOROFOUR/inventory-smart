<?php
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permissions_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: landing.php"); exit(); }
if ($_SESSION['role'] !== 'ADMIN') { header("Location: dashboard.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Location Management — IBIS</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="css/style.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
:root {
    --primary: #2d3436;
    --accent:  #0984e3;
    --green:   #00b894;
    --danger:  #d63031;
    --warning: #e17055;
    --bg:      #f0f2f5;
    --card:    #ffffff;
    --border:  #dfe6e9;
    --txt:     #2d3436;
    --muted:   #636e72;
    --shadow:  rgba(0,0,0,0.07);
}
body { font-family: 'Space Grotesk', sans-serif; background: var(--bg); color: var(--txt); }

/* ── LAYOUT ── */
.content { margin-left: 88px; padding: 2rem; transition: margin-left .3s; min-height: 100vh; }
.sidebar:not(.close) ~ .content { margin-left: 260px; }

/* ── PAGE HEADER ── */
.page-header {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    border-radius: 16px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    color: #fff;
    box-shadow: 0 8px 24px rgba(30,60,114,.25);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.page-header h1 { font-size: 1.9rem; font-weight: 700; display: flex; align-items: center; gap: .75rem; }
.page-header p  { opacity: .85; font-size: .93rem; margin-top: .2rem; }
.btn-add {
    display: flex; align-items: center; gap: .5rem;
    background: #fff; color: #1e3c72;
    border: none; border-radius: 10px;
    padding: .7rem 1.4rem;
    font-family: inherit; font-size: .9rem; font-weight: 700;
    cursor: pointer; white-space: nowrap;
    transition: all .2s;
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.btn-add:hover { background: #1e3c72; color: #fff; }

/* ── STATS ROW ── */
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.75rem;
}
.stat-card {
    background: var(--card);
    border-radius: 14px;
    padding: 1.4rem 1.6rem;
    box-shadow: 0 2px 10px var(--shadow);
    display: flex; align-items: center; gap: 1.1rem;
    position: relative; overflow: hidden;
}
.stat-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 3px;
}
.stat-card.blue::before   { background: linear-gradient(90deg,#0984e3,#74b9ff); }
.stat-card.green::before  { background: linear-gradient(90deg,#00b894,#55efc4); }
.stat-card.orange::before { background: linear-gradient(90deg,#e17055,#fdcb6e); }
.stat-icon {
    width: 50px; height: 50px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; flex-shrink: 0;
}
.stat-card.blue   .stat-icon { background: rgba(9,132,227,.12);  color: #0984e3; }
.stat-card.green  .stat-icon { background: rgba(0,184,148,.12);  color: #00b894; }
.stat-card.orange .stat-icon { background: rgba(225,112,85,.12); color: #e17055; }
.stat-val   { font-size: 1.9rem; font-weight: 700; line-height: 1; }
.stat-label { font-size: .8rem; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; font-weight: 600; margin-top: .2rem; }

/* ── TABLE CARD ── */
.table-card {
    background: var(--card);
    border-radius: 16px;
    box-shadow: 0 2px 10px var(--shadow);
    overflow: hidden;
}
.toolbar {
    display: flex; align-items: center; gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 2px solid var(--border);
    flex-wrap: wrap;
}
.toolbar h3 {
    font-size: .95rem; font-weight: 700; color: var(--txt);
    display: flex; align-items: center; gap: .5rem;
    text-transform: uppercase; letter-spacing: .5px; flex: 1;
}
.toolbar h3 i { color: var(--accent); }
.search-wrap {
    position: relative;
}
.search-wrap i {
    position: absolute; left: .75rem; top: 50%; transform: translateY(-50%);
    color: var(--muted); font-size: 1.1rem; pointer-events: none;
}
.search-wrap input {
    padding: .55rem .75rem .55rem 2.2rem;
    border: 1.5px solid var(--border);
    border-radius: 9px;
    font-family: inherit; font-size: .85rem;
    outline: none; width: 240px;
    transition: border-color .2s;
}
.search-wrap input:focus { border-color: var(--accent); }
.filter-select {
    padding: .55rem .9rem;
    border: 1.5px solid var(--border);
    border-radius: 9px;
    font-family: inherit; font-size: .85rem;
    outline: none; cursor: pointer;
    transition: border-color .2s;
}
.filter-select:focus { border-color: var(--accent); }

/* Table */
table { width: 100%; border-collapse: collapse; font-size: .88rem; }
thead { background: linear-gradient(135deg, #2d3436 0%, #34495e 100%); color: #fff; }
thead th { padding: .9rem 1.2rem; text-align: left; font-weight: 600; font-size: .77rem; text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #f8f9fa; }
tbody td { padding: .9rem 1.2rem; }

.loc-name { font-weight: 600; color: var(--txt); display: flex; align-items: center; gap: .5rem; }
.loc-name i { color: var(--muted); font-size: 1rem; }
.loc-id { font-size: .75rem; font-family: 'JetBrains Mono', monospace; color: var(--muted); background: #f0f2f5; padding: .15rem .5rem; border-radius: 5px; }
.loc-date { font-size: .8rem; color: var(--muted); }

.badge {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .25rem .7rem; border-radius: 6px;
    font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px;
    font-family: 'JetBrains Mono', monospace;
}
.badge-active   { background: #d4f8e8; color: #0a6640; }
.badge-inactive { background: #fde8e8; color: #8b1a1a; }

.actions { display: flex; gap: .5rem; align-items: center; }
.action-btn {
    width: 32px; height: 32px; border-radius: 8px; border: none;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 1rem; transition: all .2s;
}
.action-btn.edit     { background: rgba(9,132,227,.1);  color: #0984e3; }
.action-btn.edit:hover     { background: #0984e3; color: #fff; }
.action-btn.toggle   { background: rgba(0,184,148,.1);  color: #00b894; }
.action-btn.toggle:hover   { background: #00b894; color: #fff; }
.action-btn.deactivate { background: rgba(225,112,85,.1); color: #e17055; }
.action-btn.deactivate:hover { background: #e17055; color: #fff; }
.action-btn.del      { background: rgba(214,48,49,.1);  color: #d63031; }
.action-btn.del:hover      { background: #d63031; color: #fff; }

/* Empty state */
.empty-state { text-align: center; padding: 3.5rem 1rem; color: var(--muted); }
.empty-state i { font-size: 3rem; display: block; margin-bottom: .75rem; opacity: .35; }

/* Pagination */
.pagination {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.5rem;
    border-top: 2px solid var(--border);
    flex-wrap: wrap; gap: .75rem;
}
.page-info { font-size: .83rem; color: var(--muted); }
.page-btns { display: flex; gap: .4rem; }
.page-btn {
    min-width: 34px; height: 34px; border-radius: 8px;
    border: 1.5px solid var(--border);
    background: #fff; color: var(--txt);
    font-family: inherit; font-size: .83rem; font-weight: 600;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all .2s;
}
.page-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
.page-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }
.page-btn:disabled { opacity: .4; cursor: not-allowed; }

/* ── MODAL ── */
.overlay {
    position: fixed; inset: 0;
    background: rgba(15,23,42,.55);
    backdrop-filter: blur(4px);
    z-index: 1000; display: none;
    align-items: center; justify-content: center;
    padding: 1rem;
}
.overlay.active { display: flex; }
.modal {
    background: #fff; border-radius: 18px;
    padding: 2rem; width: 100%; max-width: 440px;
    box-shadow: 0 24px 64px rgba(15,23,42,.2);
    animation: slideUp .25s ease;
}
@keyframes slideUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
.modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
.modal-header h3 { font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
.modal-header h3 i { color: var(--accent); }
.btn-close { background: none; border: none; cursor: pointer; font-size: 1.4rem; color: var(--muted); display: flex; align-items: center; transition: color .15s; }
.btn-close:hover { color: var(--danger); }

.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; font-size: .83rem; font-weight: 600; color: var(--txt); margin-bottom: .45rem; }
.form-group input {
    width: 100%; padding: .65rem 1rem;
    border: 1.5px solid var(--border); border-radius: 10px;
    font-family: inherit; font-size: .9rem; outline: none;
    transition: border-color .2s;
}
.form-group input:focus { border-color: var(--accent); }
.hint { font-size: .78rem; color: var(--muted); margin-top: .35rem; display: flex; align-items: center; gap: .3rem; }

.modal-footer { display: flex; gap: .75rem; justify-content: flex-end; margin-top: 1.5rem; }
.btn-cancel {
    padding: .65rem 1.3rem; border-radius: 10px;
    border: 1.5px solid var(--border); background: #fff;
    font-family: inherit; font-size: .88rem; font-weight: 600; color: var(--muted);
    cursor: pointer; transition: all .2s;
}
.btn-cancel:hover { border-color: var(--danger); color: var(--danger); }
.btn-save {
    padding: .65rem 1.5rem; border-radius: 10px; border: none;
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: #fff; font-family: inherit; font-size: .88rem; font-weight: 700;
    cursor: pointer; transition: all .2s; display: flex; align-items: center; gap: .5rem;
}
.btn-save:hover { opacity: .9; transform: translateY(-1px); }

/* Delete confirm modal */
.del-icon { font-size: 3rem; color: var(--danger); text-align: center; display: block; margin-bottom: 1rem; }
.del-msg  { text-align: center; font-size: .95rem; color: var(--muted); line-height: 1.6; }
.del-name { font-weight: 700; color: var(--txt); }
.btn-del-confirm {
    padding: .65rem 1.5rem; border-radius: 10px; border: none;
    background: var(--danger); color: #fff;
    font-family: inherit; font-size: .88rem; font-weight: 700;
    cursor: pointer; transition: all .2s; display: flex; align-items: center; gap: .5rem;
}
.btn-del-confirm:hover { background: #b02020; transform: translateY(-1px); }

/* Toast */
#toast-wrap { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: .6rem; }
.toast {
    display: flex; align-items: center; gap: .75rem;
    background: #fff; padding: .85rem 1.25rem;
    border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.13);
    font-size: .88rem; font-weight: 600; min-width: 260px;
    border-left: 4px solid;
    animation: toastIn .3s ease;
}
@keyframes toastIn { from { opacity:0; transform:translateX(30px); } to { opacity:1; transform:translateX(0); } }
.toast.success { border-color: var(--green); }
.toast.success i { color: var(--green); }
.toast.error   { border-color: var(--danger); }
.toast.error   i { color: var(--danger); }

@media (max-width: 900px) {
    .stats-row { grid-template-columns: 1fr 1fr; }
    .search-wrap input { width: 180px; }
}
@media (max-width: 600px) {
    .content { margin-left: 0 !important; padding: 1rem; }
    .stats-row { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1><i class='bx bx-map-alt'></i> Location Management</h1>
            <p>Manage all warehouse and store locations in the system</p>
        </div>
        <button class="btn-add" onclick="openAddModal()">
            <i class='bx bx-plus'></i> Add Location
        </button>
    </div>

    <!-- Stats -->
    <div class="stats-row" id="statsRow">
        <div class="stat-card blue">
            <div class="stat-icon"><i class='bx bx-map-pin'></i></div>
            <div>
                <div class="stat-val" id="statTotal">—</div>
                <div class="stat-label">Total Locations</div>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class='bx bx-check-circle'></i></div>
            <div>
                <div class="stat-val" id="statActive">—</div>
                <div class="stat-label">Active</div>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class='bx bx-hide'></i></div>
            <div>
                <div class="stat-val" id="statInactive">—</div>
                <div class="stat-label">Inactive</div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-card">
        <div class="toolbar">
            <h3><i class='bx bx-list-ul'></i> All Locations</h3>
            <div class="search-wrap">
                <i class='bx bx-search'></i>
                <input type="text" id="searchInput" placeholder="Search locations…" oninput="applyFilters()">
            </div>
            <select class="filter-select" id="statusFilter" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>

        <div id="tableWrap">
            <div class="empty-state"><i class='bx bx-loader-alt bx-spin'></i><p>Loading…</p></div>
        </div>

        <div class="pagination" id="pagination" style="display:none;"></div>
    </div>

</div>

<!-- Add / Edit Modal -->
<div class="overlay" id="locModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class='bx bx-map-pin'></i> <span id="modalTitle">Add Location</span></h3>
            <button class="btn-close" onclick="closeModal('locModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="form-group">
            <label>Location Name <span style="color:var(--danger)">*</span></label>
            <input type="text" id="fieldName" placeholder="e.g. CBTL MOA" maxlength="100">
            <p class="hint"><i class='bx bx-info-circle'></i> Must match exactly with the location stored in assets.</p>
        </div>
        <input type="hidden" id="editId">
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('locModal')">Cancel</button>
            <button class="btn-save" onclick="saveLocation()">
                <i class='bx bx-save'></i> <span id="saveBtnLabel">Save</span>
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="overlay" id="delModal">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <h3 style="color:var(--danger);"><i class='bx bx-trash'></i> Delete Location</h3>
            <button class="btn-close" onclick="closeModal('delModal')"><i class='bx bx-x'></i></button>
        </div>
        <i class='bx bx-error-circle del-icon'></i>
        <p class="del-msg">Are you sure you want to permanently delete<br><span class="del-name" id="delName"></span>?<br><br><small>This cannot be undone. If any users are assigned here, deletion will be blocked.</small></p>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('delModal')">Cancel</button>
            <button class="btn-del-confirm" onclick="confirmDelete()">
                <i class='bx bx-trash'></i> Delete
            </button>
        </div>
    </div>
</div>

<div id="toast-wrap"></div>

<script>
const API = 'user_management_api.php';
let allLocations = [];
let filtered     = [];
let page         = 1;
const PER_PAGE   = 20;
let delTarget    = null;

// ── LOAD ─────────────────────────────────────────────────────────────────────
async function loadLocations() {
    try {
        const r = await fetch(`${API}?action=get_locations`);
        const d = await r.json();
        if (!d.success) return showToast(d.message || 'Failed to load', 'error');
        allLocations = d.data;
        updateStats();
        applyFilters();
    } catch(e) {
        document.getElementById('tableWrap').innerHTML =
            `<div class="empty-state"><i class='bx bx-error-circle'></i><p>Failed to load locations</p></div>`;
    }
}

function updateStats() {
    const active   = allLocations.filter(l => l.is_active == 1).length;
    const inactive = allLocations.length - active;
    document.getElementById('statTotal').textContent   = allLocations.length;
    document.getElementById('statActive').textContent  = active;
    document.getElementById('statInactive').textContent = inactive;
}

// ── FILTER & RENDER ──────────────────────────────────────────────────────────
function applyFilters() {
    const q      = document.getElementById('searchInput').value.trim().toLowerCase();
    const status = document.getElementById('statusFilter').value;
    filtered = allLocations.filter(l => {
        const matchQ = !q || l.name.toLowerCase().includes(q);
        const matchS = status === '' || String(l.is_active) === status;
        return matchQ && matchS;
    });
    page = 1;
    renderTable();
}

function renderTable() {
    const start  = (page - 1) * PER_PAGE;
    const slice  = filtered.slice(start, start + PER_PAGE);
    const wrap   = document.getElementById('tableWrap');

    if (!filtered.length) {
        wrap.innerHTML = `<div class="empty-state"><i class='bx bx-map-pin'></i><p>No locations found</p></div>`;
        document.getElementById('pagination').style.display = 'none';
        return;
    }

    const rows = slice.map((l, i) => {
        const num       = start + i + 1;
        const activeBadge = l.is_active == 1
            ? `<span class="badge badge-active"><i class='bx bx-check-circle'></i> Active</span>`
            : `<span class="badge badge-inactive"><i class='bx bx-hide'></i> Inactive</span>`;
        const toggleBtn = l.is_active == 1
            ? `<button class="action-btn deactivate" title="Deactivate" onclick="toggleLoc(${l.id}, 0)"><i class='bx bx-hide'></i></button>`
            : `<button class="action-btn toggle"     title="Activate"   onclick="toggleLoc(${l.id}, 1)"><i class='bx bx-show'></i></button>`;
        const date = l.created_at ? new Date(l.created_at).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' }) : '—';

        return `<tr>
            <td><span class="loc-id">#${l.id}</span></td>
            <td>
                <div class="loc-name">
                    <i class='bx bx-map-pin'></i>
                    ${esc(l.name)}
                </div>
            </td>
            <td>${activeBadge}</td>
            <td class="loc-date">${date}</td>
            <td>
                <div class="actions">
                    <button class="action-btn edit" title="Edit" onclick="openEditModal(${l.id})"><i class='bx bx-edit'></i></button>
                    ${toggleBtn}
                    <button class="action-btn del"  title="Delete" onclick="openDelModal(${l.id}, '${esc(l.name).replace(/'/g,"\\'")}')"><i class='bx bx-trash'></i></button>
                </div>
            </td>
        </tr>`;
    }).join('');

    wrap.innerHTML = `
    <table>
        <thead>
            <tr>
                <th style="width:70px;">ID</th>
                <th>Location Name</th>
                <th style="width:130px;">Status</th>
                <th style="width:140px;">Added</th>
                <th style="width:120px;">Actions</th>
            </tr>
        </thead>
        <tbody>${rows}</tbody>
    </table>`;

    renderPagination();
}

function renderPagination() {
    const total  = filtered.length;
    const pages  = Math.ceil(total / PER_PAGE);
    const pg     = document.getElementById('pagination');

    if (pages <= 1) { pg.style.display = 'none'; return; }
    pg.style.display = 'flex';

    const start = (page - 1) * PER_PAGE + 1;
    const end   = Math.min(page * PER_PAGE, total);

    let btns = '';
    // Prev
    btns += `<button class="page-btn" onclick="goPage(${page-1})" ${page===1?'disabled':''}>‹</button>`;
    // Pages
    for (let p = 1; p <= pages; p++) {
        if (p === 1 || p === pages || Math.abs(p - page) <= 1) {
            btns += `<button class="page-btn ${p===page?'active':''}" onclick="goPage(${p})">${p}</button>`;
        } else if (Math.abs(p - page) === 2) {
            btns += `<button class="page-btn" disabled>…</button>`;
        }
    }
    // Next
    btns += `<button class="page-btn" onclick="goPage(${page+1})" ${page===pages?'disabled':''}>›</button>`;

    pg.innerHTML = `
        <span class="page-info">Showing ${start}–${end} of ${total} locations</span>
        <div class="page-btns">${btns}</div>`;
}

function goPage(p) { page = p; renderTable(); window.scrollTo(0, 0); }

// ── ADD / EDIT ────────────────────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('modalTitle').textContent   = 'Add Location';
    document.getElementById('saveBtnLabel').textContent = 'Add Location';
    document.getElementById('fieldName').value = '';
    document.getElementById('editId').value    = '';
    openModal('locModal');
    setTimeout(() => document.getElementById('fieldName').focus(), 100);
}

function openEditModal(id) {
    const loc = allLocations.find(l => l.id == id);
    if (!loc) return;
    document.getElementById('modalTitle').textContent   = 'Edit Location';
    document.getElementById('saveBtnLabel').textContent = 'Save Changes';
    document.getElementById('fieldName').value          = loc.name;
    document.getElementById('editId').value             = loc.id;
    openModal('locModal');
    setTimeout(() => document.getElementById('fieldName').focus(), 100);
}

async function saveLocation() {
    const name  = document.getElementById('fieldName').value.trim();
    const editId = document.getElementById('editId').value;

    if (!name) { showToast('Location name is required.', 'error'); document.getElementById('fieldName').focus(); return; }

    const action  = editId ? 'edit_location' : 'add_location';
    const payload = editId ? { action, id: parseInt(editId), name } : { action, name };

    try {
        const r = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const d = await r.json();
        if (d.success) {
            showToast(d.message, 'success');
            closeModal('locModal');
            loadLocations();
        } else {
            showToast(d.message || 'Error', 'error');
        }
    } catch(e) { showToast('Request failed', 'error'); }
}

// ── TOGGLE ACTIVE ─────────────────────────────────────────────────────────────
async function toggleLoc(id, active) {
    try {
        const r = await fetch(API, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_location', id, active })
        });
        const d = await r.json();
        if (d.success) { showToast(d.message, 'success'); loadLocations(); }
        else showToast(d.message || 'Error', 'error');
    } catch(e) { showToast('Request failed', 'error'); }
}

// ── DELETE ────────────────────────────────────────────────────────────────────
function openDelModal(id, name) {
    delTarget = id;
    document.getElementById('delName').textContent = name;
    openModal('delModal');
}

async function confirmDelete() {
    if (!delTarget) return;
    try {
        const r = await fetch(API, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_location', id: delTarget })
        });
        const d = await r.json();
        if (d.success) {
            showToast(d.message, 'success');
            closeModal('delModal');
            loadLocations();
        } else {
            showToast(d.message, 'error');
        }
    } catch(e) { showToast('Request failed', 'error'); }
}

// ── UTILS ─────────────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function showToast(msg, type = 'success') {
    const wrap = document.getElementById('toast-wrap');
    const t    = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<i class='bx ${type==='success'?'bx-check-circle':'bx-error-circle'}'></i>${msg}`;
    wrap.appendChild(t);
    setTimeout(() => { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 3200);
}

// Backdrop close
document.querySelectorAll('.overlay').forEach(o =>
    o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); })
);
// Enter key submit
document.getElementById('fieldName').addEventListener('keydown', e => { if (e.key === 'Enter') saveLocation(); });

// Init
document.addEventListener('DOMContentLoaded', loadLocations);
</script>
</body>
</html>
<?php ob_end_flush(); ?>