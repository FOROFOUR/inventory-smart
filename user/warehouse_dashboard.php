<?php
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../permissions_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: ../landing.php"); exit(); }

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT role, name, warehouse_location FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();

if (!$me || $me['role'] !== 'WAREHOUSE') {
    header("Location: ../dashboard.php"); exit();
}

$myLocation = $me['warehouse_location'] ?? '';
$myName     = $me['name'] ?? 'Warehouse';
$locLike    = $myLocation . '%';

$confStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM pull_out_transactions WHERE status='CONFIRMED' AND from_location LIKE ?");
$confStmt->bind_param("s", $locLike);
$confStmt->execute();
$preparingCount = (int)($confStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

$relStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM pull_out_transactions WHERE status='RELEASED' AND to_location LIKE ?");
$relStmt->bind_param("s", $locLike);
$relStmt->execute();
$releasedCount = (int)($relStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($myName) ?> — IBIS</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Poppins',sans-serif;background:#f4f6f9;min-height:100vh;}
.main-content{position:relative;left:250px;width:calc(100% - 250px);min-height:100vh;background:#f4f6f9;padding:28px 32px;transition:all .3s ease;}
.sidebar.close ~ .main-content{left:88px;width:calc(100% - 88px);}
.hero{background:linear-gradient(135deg,#1e3a8a 0%,#2563eb 60%,#4f8ef7 100%);border-radius:20px;padding:28px 32px;display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;box-shadow:0 8px 32px rgba(37,99,235,.25);overflow:hidden;position:relative;}
.hero::after{content:'';position:absolute;right:-30px;top:-30px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.06);}
.hero-left h1{font-size:1.4rem;font-weight:700;color:#fff;margin-bottom:4px;}
.hero-left p{font-size:.84rem;color:rgba(255,255,255,.75);display:flex;align-items:center;gap:6px;}
.hero-stats{display:flex;gap:24px;position:relative;z-index:1;}
.hstat{text-align:center;}
.hstat-val{font-size:1.8rem;font-weight:700;color:#fff;line-height:1;}
.hstat-lab{font-size:.68rem;color:rgba(255,255,255,.65);text-transform:uppercase;letter-spacing:.5px;margin-top:3px;}
.cards-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.scard{background:#fff;border-radius:16px;padding:18px 20px;border:1px solid #e8ecf3;box-shadow:0 1px 4px rgba(15,23,42,.04),0 4px 16px rgba(15,23,42,.05);display:flex;align-items:center;gap:14px;}
.scard-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;color:#fff;flex-shrink:0;}
.scard-label{font-size:.72rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px;}
.scard-val{font-size:1.6rem;font-weight:700;color:#0f172a;line-height:1.1;}
.quick-nav{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
.qcard{background:#fff;border-radius:16px;padding:20px 24px;border:1px solid #e8ecf3;box-shadow:0 1px 4px rgba(15,23,42,.04),0 4px 16px rgba(15,23,42,.05);display:flex;align-items:center;gap:16px;text-decoration:none;color:inherit;transition:transform .2s,box-shadow .2s;border-left:4px solid transparent;}
.qcard:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.1);}
.qcard.preparing{border-left-color:#f39c12;}
.qcard.receiving{border-left-color:#16a085;}
.qcard.completed{border-left-color:#27ae60;}
.qcard-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
.qcard.preparing .qcard-icon{background:#fef3cd;color:#f39c12;}
.qcard.receiving .qcard-icon{background:#d1f2eb;color:#16a085;}
.qcard.completed .qcard-icon{background:#d5f5e3;color:#27ae60;}
.qcard-label{font-size:.72rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px;}
.qcard-title{font-size:1rem;font-weight:700;color:#0f172a;}
.qcard-badge{margin-left:auto;font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:99px;flex-shrink:0;}
.badge-amber{background:#fef3cd;color:#92400e;border:1px solid #fde68a;}
.badge-teal{background:#d1f2eb;color:#0e6655;border:1px solid #a9dfce;}
.asset-card{background:#fff;border-radius:18px;border:1px solid #e8ecf3;box-shadow:0 1px 4px rgba(15,23,42,.04),0 4px 24px rgba(15,23,42,.06);overflow:hidden;}
.toolbar{padding:14px 22px;border-bottom:1px solid #f1f5f9;background:#fafbfe;display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.search-wrap{position:relative;flex:1;min-width:180px;}
.search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:1rem;pointer-events:none;}
.search-wrap input{width:100%;padding:8px 11px 8px 35px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:'Poppins',sans-serif;font-size:.83rem;color:#0f172a;background:#f8fafc;outline:none;transition:border-color .2s;}
.search-wrap input:focus{border-color:#4f8ef7;background:#fff;}
.fsel{padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-family:'Poppins',sans-serif;font-size:.83rem;color:#0f172a;background:#f8fafc;outline:none;cursor:pointer;}
.fsel:focus{border-color:#4f8ef7;}
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;}
table{width:100%;border-collapse:collapse;}
thead{background:linear-gradient(135deg,#1e293b,#334155);}
thead th{padding:12px 16px;text-align:left;color:#fff;font-size:.74rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .15s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:#f8faff;}
tbody td{padding:12px 16px;font-size:.84rem;color:#334155;vertical-align:middle;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px;}
.cond-new{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;}
.cond-used{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;}
.st-working{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;}
.st-checking{background:#fffbeb;color:#92400e;border:1px solid #fde68a;}
.st-broken{background:#fff1f2;color:#be123c;border:1px solid #fecdd3;}
.qty-badge{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:22px;padding:0 7px;border-radius:6px;background:#eff6ff;color:#2563eb;font-weight:700;font-size:.78rem;border:1px solid #bfdbfe;}
.empty-state{text-align:center;padding:60px 20px;color:#94a3b8;}
.empty-state i{font-size:3.5rem;display:block;margin-bottom:12px;opacity:.25;}
.loading-row{text-align:center;padding:40px;color:#94a3b8;}
.toast{position:fixed;top:20px;right:80px;z-index:99998;padding:12px 18px;border-radius:11px;font-family:'Poppins',sans-serif;font-size:.84rem;font-weight:500;display:flex;align-items:center;gap:9px;box-shadow:0 8px 24px rgba(15,23,42,.15);animation:slideIn .3s ease;max-width:340px;}
.toast-success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;}
.toast-error{background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;}
@keyframes slideIn{from{opacity:0;transform:translateX(20px);}to{opacity:1;transform:translateX(0);}}
@media(max-width:1024px){.main-content{left:88px;width:calc(100% - 88px);padding:22px 24px;}.sidebar.close ~ .main-content{left:88px;width:calc(100% - 88px);}.cards-row{grid-template-columns:repeat(2,1fr);}.quick-nav{grid-template-columns:repeat(2,1fr);}}
@media(max-width:768px){.main-content{left:0!important;width:100%!important;padding:16px 14px;}.hero{flex-direction:column;align-items:flex-start;gap:16px;padding:20px;border-radius:14px;margin-bottom:16px;}.hero-stats{gap:20px;width:100%;justify-content:flex-start;}.hero-left h1{font-size:1.2rem;}.hstat-val{font-size:1.6rem;}.cards-row{grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:16px;}.scard{padding:14px 16px;gap:12px;}.scard-icon{width:40px;height:40px;font-size:1.1rem;}.scard-val{font-size:1.4rem;}.quick-nav{grid-template-columns:1fr;gap:10px;margin-bottom:16px;}.qcard{padding:16px 18px;}.toolbar{padding:12px 14px;gap:8px;}.fsel{flex:1;min-width:0;}thead th:nth-child(4),tbody td:nth-child(4),thead th:nth-child(8),tbody td:nth-child(8){display:none;}}
@media(max-width:480px){.main-content{padding:12px 10px;}.hero{padding:16px;border-radius:12px;}.hero-left h1{font-size:1.05rem;}.hero-stats{gap:12px;}.hstat-val{font-size:1.3rem;}.hstat-lab{font-size:.6rem;}.cards-row{grid-template-columns:repeat(2,1fr);gap:8px;}.scard{padding:12px;gap:10px;border-radius:12px;}.scard-icon{width:36px;height:36px;font-size:1rem;border-radius:10px;}.scard-val{font-size:1.2rem;}.scard-label{font-size:.62rem;}.qcard{padding:14px 16px;gap:12px;}.qcard-icon{width:40px;height:40px;font-size:1.2rem;}.qcard-title{font-size:.9rem;}.qcard-label{font-size:.65rem;}.toolbar{flex-direction:column;align-items:stretch;}.search-wrap{min-width:0;}.fsel{width:100%;}thead th:nth-child(6),tbody td:nth-child(6){display:none;}tbody td,thead th{padding:9px 10px;font-size:.78rem;}.toast{right:10px;left:10px;max-width:none;top:auto;bottom:16px;}}
</style>
</head>
<body>

<?php include 'warehouse_sidebar.php'; ?>

<div class="main-content">

  <div class="hero">
    <div class="hero-left">
      <h1>📦 <?= htmlspecialchars($myLocation ?: 'Warehouse Dashboard') ?></h1>
      <p><i class='bx bx-time'></i> <span id="liveClock">—</span></p>
    </div>
    <div class="hero-stats">
      <div class="hstat"><div class="hstat-val" id="heroTotal">—</div><div class="hstat-lab">Total Items</div></div>
      <div class="hstat"><div class="hstat-val" id="heroPreparing"><?= $preparingCount ?></div><div class="hstat-lab">Preparing</div></div>
      <div class="hstat"><div class="hstat-val" id="heroReceiving"><?= $releasedCount ?></div><div class="hstat-lab">For Receipt</div></div>
    </div>
  </div>

  <div class="cards-row">
    <div class="scard">
      <div class="scard-icon" style="background:linear-gradient(135deg,#4f8ef7,#2563eb);"><i class='bx bx-package'></i></div>
      <div><div class="scard-label">Assets Here</div><div class="scard-val" id="cTotal">—</div></div>
    </div>
    <div class="scard">
      <div class="scard-icon" style="background:linear-gradient(135deg,#34d399,#059669);"><i class='bx bx-check-circle'></i></div>
      <div><div class="scard-label">Working</div><div class="scard-val" id="cWorking">—</div></div>
    </div>
    <div class="scard">
      <div class="scard-icon" style="background:linear-gradient(135deg,#fb923c,#c2410c);"><i class='bx bx-wrench'></i></div>
      <div><div class="scard-label">For Checking</div><div class="scard-val" id="cChecking">—</div></div>
    </div>
    <div class="scard">
      <div class="scard-icon" style="background:linear-gradient(135deg,#f87171,#dc2626);"><i class='bx bx-x-circle'></i></div>
      <div><div class="scard-label">Not Working</div><div class="scard-val" id="cBroken">—</div></div>
    </div>
  </div>

  <div class="quick-nav">
    <a href="warehouse-preparing.php" class="qcard preparing">
      <div class="qcard-icon"><i class='bx bx-loader-circle'></i></div>
      <div>
        <div class="qcard-label">Items leaving your warehouse</div>
        <div class="qcard-title">Preparing</div>
      </div>
      <?php if ($preparingCount > 0): ?>
        <span class="qcard-badge badge-amber"><?= $preparingCount ?> item<?= $preparingCount > 1 ? 's' : '' ?></span>
      <?php endif; ?>
    </a>
    <a href="warehouse-receiving.php" class="qcard receiving">
      <div class="qcard-icon"><i class='bx bx-package'></i></div>
      <div>
        <div class="qcard-label">Items coming to your warehouse</div>
        <div class="qcard-title">Receiving</div>
      </div>
      <?php if ($releasedCount > 0): ?>
        <span class="qcard-badge badge-teal"><?= $releasedCount ?> incoming</span>
      <?php endif; ?>
    </a>
    <a href="warehouse-completed.php" class="qcard completed">
      <div class="qcard-icon"><i class='bx bx-history'></i></div>
      <div>
        <div class="qcard-label">All released items</div>
        <div class="qcard-title">Release History</div>
      </div>
    </a>
  </div>

  <div class="asset-card">
    <div class="toolbar">
      <div class="search-wrap">
        <i class='bx bx-search'></i>
        <input type="text" id="searchInput" placeholder="Search assets…">
      </div>
      <select class="fsel" id="filterCat"><option value="">All Categories</option></select>
      <select class="fsel" id="filterStatus">
        <option value="">All Status</option>
        <option value="WORKING">Working</option>
        <option value="FOR CHECKING">For Checking</option>
        <option value="NOT WORKING">Not Working</option>
      </select>
      <select class="fsel" id="filterCond">
        <option value="">All Condition</option>
        <option value="NEW">New</option>
        <option value="USED">Used</option>
      </select>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Category</th><th>Brand / Model</th><th>Serial No.</th>
            <th>Qty</th><th>Condition</th><th>Status</th><th>Sub-location</th>
          </tr>
        </thead>
        <tbody id="assetBody">
          <tr><td colspan="8" class="loading-row"><i class='bx bx-loader-alt bx-spin'></i></td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
const MY_LOCATION = <?= json_encode($myLocation) ?>;
let allAssets = [];

function updateClock() {
    document.getElementById('liveClock').textContent =
        new Date().toLocaleString('en-PH', { weekday:'short', year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
}
updateClock(); setInterval(updateClock, 1000);

// ── Helper: sum beg_balance_count for a subset of assets ──────────────────────
function sumQty(arr) {
    return arr.reduce((sum, a) => sum + (parseInt(a.beg_balance_count) || 0), 0);
}

async function loadAssets() {
    try {
        const r = await fetch('warehouse_dashboard_api.php?action=get_assets');
        const d = await r.json();
        if (!d.success) {
            document.getElementById('assetBody').innerHTML =
                `<tr><td colspan="8"><div class="empty-state"><i class='bx bx-error-circle'></i><p>${esc(d.message||'Failed to load')}</p></div></td></tr>`;
            return;
        }
        allAssets = d.data;

        // Populate category filter
        const cats = [...new Set(allAssets.map(a => a.category_name).filter(Boolean))].sort();
        document.getElementById('filterCat').innerHTML =
            '<option value="">All Categories</option>' +
            cats.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');

        // ── KPI cards: SUM of beg_balance_count per status group ──
        const totalQty   = sumQty(allAssets);
        const workingQty = sumQty(allAssets.filter(a => a.status === 'WORKING'));
        const checkQty   = sumQty(allAssets.filter(a => a.status === 'FOR CHECKING'));
        const brokenQty  = sumQty(allAssets.filter(a => a.status === 'NOT WORKING'));

        document.getElementById('cTotal').textContent    = totalQty;
        document.getElementById('heroTotal').textContent  = totalQty;
        document.getElementById('cWorking').textContent   = workingQty;
        document.getElementById('cChecking').textContent  = checkQty;
        document.getElementById('cBroken').textContent    = brokenQty;

        renderAssets();
    } catch(e) { showToast('Connection error', 'error'); }
}

function renderAssets() {
    const q   = document.getElementById('searchInput').value.toLowerCase();
    const cat = document.getElementById('filterCat').value;
    const st  = document.getElementById('filterStatus').value;
    const cn  = document.getElementById('filterCond').value;

    const list = allAssets.filter(a =>
        (!q   || [a.brand,a.model,a.serial_number,a.category_name,a.sub_category_name].join(' ').toLowerCase().includes(q)) &&
        (!cat || a.category_name === cat) &&
        (!st  || a.status === st) &&
        (!cn  || a.condition === cn)
    );

    const tb = document.getElementById('assetBody');
    if (!list.length) {
        tb.innerHTML = `<tr><td colspan="8"><div class="empty-state"><i class='bx bx-package'></i><p>No assets found</p></div></td></tr>`;
        return;
    }
    const stClass = { 'WORKING':'st-working','FOR CHECKING':'st-checking','NOT WORKING':'st-broken' };
    tb.innerHTML = list.map(a => `<tr>
        <td style="color:#94a3b8;font-size:.75rem;">#${a.id}</td>
        <td><div style="font-weight:600;font-size:.82rem;color:#0f172a;">${esc(a.category_name||'—')}</div>
            <div style="font-size:.72rem;color:#94a3b8;">${esc(a.sub_category_name||'')}</div></td>
        <td><div style="font-weight:500;">${esc(a.brand||'—')}</div>
            <div style="font-size:.75rem;color:#94a3b8;">${esc(a.model||'')}</div></td>
        <td style="font-family:monospace;font-size:.78rem;color:#475569;">${esc(a.serial_number||'—')}</td>
        <td><span class="qty-badge">${parseInt(a.beg_balance_count)||1}</span></td>
        <td><span class="badge ${a.condition==='NEW'?'cond-new':'cond-used'}">${a.condition||'—'}</span></td>
        <td><span class="badge ${stClass[a.status]||'st-working'}">${a.status||'—'}</span></td>
        <td style="font-size:.8rem;color:#475569;">${esc(a.sub_location||'—')}</td>
    </tr>`).join('');
}

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function showToast(msg,type='success'){
    const t=document.createElement('div');
    t.className=`toast toast-${type}`;
    t.innerHTML=`<i class='bx ${type==='success'?'bx-check-circle':'bx-error-circle'}'></i> ${esc(msg)}`;
    document.body.appendChild(t);
    setTimeout(()=>{t.style.transition='opacity .4s';t.style.opacity='0';setTimeout(()=>t.remove(),400);},3200);
}

document.getElementById('searchInput').addEventListener('input', renderAssets);
document.getElementById('filterCat').addEventListener('change', renderAssets);
document.getElementById('filterStatus').addEventListener('change', renderAssets);
document.getElementById('filterCond').addEventListener('change', renderAssets);

loadAssets();
</script>
</body>
</html>
<?php ob_end_flush(); ?>