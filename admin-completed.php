<?php
ob_start();

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$conn      = getDBConnection();
$user_name = $_SESSION['name'] ?? 'Admin';

// ── AJAX ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'];

    if ($action === 'return') {
        $stmt = $conn->prepare("UPDATE pull_out_transactions SET status='RETURNED', returned_at=NOW() WHERE id=? AND status='RELEASED'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            // Balance reversal via pullout_api
            $apiUrl = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/pullout_api.php?action=update_status';
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['id'=>$id,'status'=>'RETURNED']),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: '.http_build_query($_COOKIE,'','; ')],CURLOPT_RETURNTRANSFER=>true]);
            curl_exec($ch); curl_close($ch);
            $desc = "Transfer #$id marked RETURNED by $user_name";
            $conn->query("INSERT INTO activity_logs (user_name, action, description) VALUES ('$user_name','RETURN_PULLOUT','$desc')");
            echo json_encode(['success'=>true,'message'=>"Transfer #$id marked as Returned."]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Already processed.']);
        }
    }
    exit();
}

// ── Fetch RELEASED + RETURNED ─────────────────────────────────────────────────
$search     = trim($_GET['search']   ?? '');
$dateFilter = trim($_GET['date']     ?? '');
$locFilter  = trim($_GET['location'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT p.*, a.brand, a.model, a.serial_number,
           c.name AS category_name, sc.name AS subcategory_name
    FROM pull_out_transactions p
    LEFT JOIN assets         a  ON p.asset_id        = a.id
    LEFT JOIN categories     c  ON a.category_id     = c.id
    LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
    WHERE p.status IN ('RELEASED','RETURNED')
";
$params=[]; $types='';
if ($search !== '') {
    $sql .= " AND (a.brand LIKE ? OR a.model LIKE ? OR p.requested_by LIKE ? OR p.released_by LIKE ? OR p.from_location LIKE ? OR p.to_location LIKE ?)";
    $l="%$search%"; $params=array_merge($params,[$l,$l,$l,$l,$l,$l]); $types.='ssssss';
}
if ($dateFilter !== '') { $sql .= " AND DATE(p.released_at) = ?"; $params[]=$dateFilter; $types.='s'; }
if ($locFilter  !== '') {
    $sql .= " AND (p.from_location LIKE ? OR p.to_location LIKE ?)";
    $ll=$locFilter.'%'; $params=array_merge($params,[$ll,$ll]); $types.='ss';
}
if ($statusFilter !== '') { $sql .= " AND p.status = ?"; $params[]=$statusFilter; $types.='s'; }
$sql .= " ORDER BY p.released_at DESC";

$stmt=$conn->prepare($sql);
if ($params) $stmt->bind_param($types,...$params);
$stmt->execute();
$rows=[];
$result=$stmt->get_result();
while ($r=$result->fetch_assoc()) $rows[]=$r;

$releasedCount = $conn->query("SELECT COUNT(*) FROM pull_out_transactions WHERE status='RELEASED'")->fetch_row()[0];
$returnedCount = $conn->query("SELECT COUNT(*) FROM pull_out_transactions WHERE status='RETURNED'")->fetch_row()[0];

$locRows=$conn->query("SELECT DISTINCT TRIM(SUBSTRING_INDEX(loc,' / ',1)) AS main_loc FROM (SELECT from_location AS loc FROM pull_out_transactions WHERE from_location IS NOT NULL AND from_location!='' AND status IN('RELEASED','RETURNED') UNION ALL SELECT to_location AS loc FROM pull_out_transactions WHERE to_location IS NOT NULL AND to_location!='' AND status IN('RELEASED','RETURNED')) x ORDER BY main_loc");
$locations=[];
while ($lr=$locRows->fetch_assoc()) { if ($lr['main_loc']) $locations[]=$lr['main_loc']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Completed Transfers — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
:root {
    --primary:#263dc7; --primary-dark:#2f538a;
    --green:#27ae60; --red:#e74c3c; --info:#3498db; --amber:#f39c12;
    --bg:#f4f6f9; --white:#fff; --text:#2c3e50; --muted:#7f8c8d;
    --border:#e8ecf0; --shadow:0 2px 12px rgba(0,0,0,.07); --radius:12px;
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Space Grotesk',sans-serif; }
body { background:var(--bg); color:var(--text); }
.content { margin-left:88px; padding:2rem; transition:margin-left .3s; }
.sidebar:not(.close) ~ .content { margin-left:260px; }

.page-header { background:linear-gradient(135deg,#27ae60 0%,#1e8449 100%); border-radius:16px; padding:2rem 2rem 1.5rem; color:white; margin-bottom:1.5rem; position:relative; overflow:hidden; }
.page-header::before { content:''; position:absolute; top:-50%; right:-5%; width:300px; height:300px; background:rgba(255,255,255,.08); border-radius:50%; }
.page-header h1 { font-size:1.6rem; font-weight:700; position:relative; z-index:1; display:flex; align-items:center; gap:.6rem; }
.page-header p  { font-size:.9rem; opacity:.85; margin-top:.25rem; position:relative; z-index:1; }

.stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.stat-card { background:var(--white); border-radius:var(--radius); padding:1.25rem 1.5rem; box-shadow:var(--shadow); display:flex; align-items:center; gap:1rem; cursor:pointer; transition:all .2s; border:2px solid transparent; }
.stat-card:hover { transform:translateY(-2px); }
.stat-card.active { border-color:var(--primary); }
.stat-icon { width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
.stat-icon.green { background:#d5f5e3; color:var(--green); }
.stat-icon.blue  { background:#d6eaf8; color:var(--info); }
.stat-value { font-size:1.6rem; font-weight:700; line-height:1; }
.stat-label { font-size:.75rem; color:var(--muted); margin-top:2px; text-transform:uppercase; letter-spacing:.5px; }

.filters { background:var(--white); border-radius:var(--radius); padding:1rem 1.25rem; box-shadow:var(--shadow); display:flex; gap:.75rem; flex-wrap:wrap; align-items:center; margin-bottom:1.25rem; }
.filters input, .filters select { border:1.5px solid var(--border); border-radius:8px; padding:.6rem 1rem; font-family:'Space Grotesk',sans-serif; font-size:.875rem; color:var(--text); outline:none; background:white; transition:border-color .2s; }
.filters input:focus, .filters select:focus { border-color:var(--green); }
.search-wrap { position:relative; flex:1; min-width:220px; }
.search-wrap input { padding-left:2.4rem; width:100%; }
.search-wrap i { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--muted); }

.btn { padding:.55rem 1.1rem; border:none; border-radius:8px; font-family:'Space Grotesk',sans-serif; font-size:.84rem; font-weight:600; cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:.4rem; }
.btn-info      { background:var(--info);  color:white; } .btn-info:hover      { background:#2980b9; }
.btn-secondary { background:#ecf0f1; color:var(--text); } .btn-secondary:hover { background:#d5dbdb; }
.btn-export    { background:#16a085; color:white; }       .btn-export:hover    { background:#138d75; }

.table-wrap { background:var(--white); border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
.table-scroll { overflow-x:auto; }
table { width:100%; border-collapse:collapse; font-size:.85rem; }
thead { background:linear-gradient(135deg,#2d3436 0%,#34495e 100%); color:white; }
thead th { padding:.9rem .85rem; text-align:left; font-size:.78rem; text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }
tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
tbody tr:hover { background:#f8fff8; }
tbody td { padding:.85rem .85rem; vertical-align:middle; }

.asset-cell .brand { font-weight:600; }
.asset-cell .model { font-size:.8rem; color:var(--muted); }
.route-cell .from  { font-size:.8rem; color:var(--muted); }
.route-cell .arrow { color:var(--green); font-size:.75rem; font-weight:700; }
.route-cell .to    { font-weight:600; }

.badge { display:inline-block; padding:.3rem .7rem; border-radius:6px; font-size:.74rem; font-weight:700; text-transform:uppercase; }
.badge-released { background:#d5f5e3; color:#1e8449; }
.badge-returned { background:#d6eaf8; color:#1a5276; }

.empty-state { text-align:center; padding:3.5rem; color:var(--muted); }
.empty-state i { font-size:3.5rem; opacity:.25; display:block; margin-bottom:.75rem; }

.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); backdrop-filter:blur(3px); z-index:9999; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal { background:var(--white); border-radius:16px; width:95%; max-width:420px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.25); animation:slideUp .25s ease; }
@keyframes slideUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
.modal-header { padding:1.25rem 1.5rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
.modal-header h3 { font-size:1.1rem; font-weight:700; display:flex; align-items:center; gap:.5rem; }
.modal-close { background:none; border:none; font-size:1.4rem; cursor:pointer; color:var(--muted); border-radius:6px; padding:.2rem .4rem; }
.modal-close:hover { background:var(--border); }
.modal-body { padding:1.5rem; color:var(--muted); line-height:1.6; }
.modal-footer { padding:1rem 1.5rem; border-top:1px solid var(--border); display:flex; gap:.75rem; justify-content:flex-end; }

.toast { position:fixed; top:20px; right:20px; padding:.9rem 1.4rem; border-radius:10px; color:white; font-weight:600; font-size:.875rem; z-index:10000; animation:toastIn .3s ease; box-shadow:0 4px 16px rgba(0,0,0,.2); }
.toast.success { background:var(--green); } .toast.error { background:var(--red); }
@keyframes toastIn { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:translateX(0); } }
</style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
<div class="content">

    <div class="page-header">
        <div>
            <h1><i class='bx bx-check-circle'></i> Completed Transfers</h1>
            <p>Released and returned asset transfers — all locations</p>
        </div>
    </div>

    <!-- Clickable stat cards to filter -->
    <div class="stats-row">
        <div class="stat-card <?php echo $statusFilter==='' ? 'active':'' ?>" onclick="filterStatus('')">
            <div class="stat-icon green"><i class='bx bx-check-double'></i></div>
            <div><div class="stat-value"><?php echo $releasedCount + $returnedCount; ?></div><div class="stat-label">Total</div></div>
        </div>
        <div class="stat-card <?php echo $statusFilter==='RELEASED' ? 'active':'' ?>" onclick="filterStatus('RELEASED')">
            <div class="stat-icon green"><i class='bx bx-check'></i></div>
            <div><div class="stat-value" id="statReleased"><?php echo $releasedCount; ?></div><div class="stat-label">Released</div></div>
        </div>
        <div class="stat-card <?php echo $statusFilter==='RETURNED' ? 'active':'' ?>" onclick="filterStatus('RETURNED')">
            <div class="stat-icon blue"><i class='bx bx-undo'></i></div>
            <div><div class="stat-value" id="statReturned"><?php echo $returnedCount; ?></div><div class="stat-label">Returned</div></div>
        </div>
    </div>

    <div class="filters">
        <div class="search-wrap">
            <i class='bx bx-search'></i>
            <input type="text" id="searchInput" placeholder="Search asset, requester, received by..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <input type="date" id="dateInput" value="<?php echo htmlspecialchars($dateFilter); ?>" title="Filter by release date">
        <select id="locSelect">
            <option value="">All Locations</option>
            <?php foreach ($locations as $loc): ?>
                <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $locFilter===$loc?'selected':''; ?>><?php echo htmlspecialchars($loc); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-export" onclick="exportCSV()"><i class='bx bx-download'></i> Export CSV</button>
    </div>

    <div class="table-wrap">
        <div class="table-scroll">
            <table id="mainTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Asset</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>From → To</th>
                        <th>Purpose</th>
                        <th>Requested By</th>
                        <th>Received By</th>
                        <th>Released At</th>
                        <th>Returned At</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                <tr><td colspan="12"><div class="empty-state"><i class='bx bx-check-circle'></i><p>No completed transfers</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($rows as $r):
                    $from = $r['from_location'] ?: '—';
                    $to   = $r['to_location']   ?: ($r['location_received'] ?: '—');
                    $purpose = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/','',$r['purpose']??'');
                    $purpose = preg_replace('/\s*\|?\s*From:\s*.+?→\s*To:\s*.+$/i','',$purpose);
                    $purpose = trim($purpose) ?: '—';
                    $receivedBy = preg_replace('/\s*\[(dest_asset_id|dest):\d+\]/','',$r['released_by']??'');
                ?>
                <tr id="row-<?php echo $r['id']; ?>">
                    <td><strong>#<?php echo $r['id']; ?></strong></td>
                    <td>
                        <div class="asset-cell">
                            <div class="brand"><?php echo htmlspecialchars($r['brand']??'—'); ?></div>
                            <?php if ($r['model']): ?><div class="model"><?php echo htmlspecialchars($r['model']); ?></div><?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($r['subcategory_name']??'—'); ?></td>
                    <td><strong><?php echo $r['quantity']; ?></strong></td>
                    <td>
                        <div class="route-cell">
                            <div class="from"><?php echo htmlspecialchars($from); ?></div>
                            <div class="arrow">↓</div>
                            <div class="to"><?php echo htmlspecialchars($to); ?></div>
                        </div>
                    </td>
                    <td style="max-width:130px; white-space:normal; font-size:.82rem;"><?php echo htmlspecialchars($purpose); ?></td>
                    <td><?php echo htmlspecialchars($r['requested_by']??'—'); ?></td>
                    <td><?php echo htmlspecialchars(trim($receivedBy))?:('—'); ?></td>
                    <td style="font-size:.8rem; color:var(--muted);"><?php echo $r['released_at'] ? date('M j, Y g:i A', strtotime($r['released_at'])) : '—'; ?></td>
                    <td style="font-size:.8rem; color:var(--muted);" id="returned-at-<?php echo $r['id']; ?>"><?php echo $r['returned_at'] ? date('M j, Y g:i A', strtotime($r['returned_at'])) : '—'; ?></td>
                    <td><span class="badge badge-<?php echo strtolower($r['status']); ?>" id="status-<?php echo $r['id']; ?>"><?php echo $r['status']; ?></span></td>
                    <td id="action-<?php echo $r['id']; ?>">
                        <?php if ($r['status'] === 'RELEASED'): ?>
                            <button class="btn btn-info" onclick="openReturn(<?php echo $r['id']; ?>)"><i class='bx bx-undo'></i> Return</button>
                        <?php else: ?>
                            <span style="font-size:.8rem; color:var(--muted);">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Return Confirm Modal -->
<div class="modal-overlay" id="returnModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class='bx bx-undo' style="color:var(--info)"></i> Mark as Returned</h3>
            <button class="modal-close" onclick="closeModal('returnModal')"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="returnId">
            <p>Asset will be returned to source location and inventory balance will be restored.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('returnModal')">Cancel</button>
            <button class="btn btn-info" onclick="submitReturn()"><i class='bx bx-undo'></i> Confirm Return</button>
        </div>
    </div>
</div>

<script>
function filterStatus(s) {
    const p = new URLSearchParams({ search: document.getElementById('searchInput').value, date: document.getElementById('dateInput').value, location: document.getElementById('locSelect').value, status: s });
    window.location.href = 'admin-completed.php?' + p.toString();
}
function applyFilters() {
    filterStatus('<?php echo htmlspecialchars($statusFilter); ?>');
}
document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key==='Enter') applyFilters(); });
document.getElementById('dateInput').addEventListener('change', applyFilters);
document.getElementById('locSelect').addEventListener('change', applyFilters);

function openReturn(id) { document.getElementById('returnId').value=id; document.getElementById('returnModal').classList.add('active'); }
async function submitReturn() {
    const id   = document.getElementById('returnId').value;
    const body = new FormData(); body.append('action','return'); body.append('id',id);
    const res  = await fetch('admin-completed.php',{method:'POST',body});
    const result = await res.json();
    showToast(result.message, result.success ? 'success' : 'error');
    if (result.success) {
        closeModal('returnModal');
        const badge = document.getElementById('status-'+id);
        if (badge) { badge.className='badge badge-returned'; badge.textContent='RETURNED'; }
        const act = document.getElementById('action-'+id);
        if (act) act.innerHTML='<span style="font-size:.8rem;color:var(--muted);">—</span>';
        const ra = document.getElementById('returned-at-'+id);
        if (ra) { const now=new Date(); ra.textContent=now.toLocaleString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}); }
        const sr = document.getElementById('statReleased');
        const sret = document.getElementById('statReturned');
        if (sr) sr.textContent = Math.max(0, parseInt(sr.textContent)-1);
        if (sret) sret.textContent = parseInt(sret.textContent)+1;
    }
}

function exportCSV() {
    const table = document.getElementById('mainTable');
    const rows  = [...table.querySelectorAll('tr')];
    const csv   = rows.map(row =>
        [...row.querySelectorAll('th,td')].slice(0,-1)
            .map(cell => `"${cell.innerText.replace(/"/g,'""').replace(/\n/g,' ')}"`)
            .join(',')
    ).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv],{type:'text/csv'}));
    a.download = `completed_transfers_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
}

function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target===m) m.classList.remove('active'); }));

function showToast(msg, type='success') {
    const t = document.createElement('div'); t.className=`toast ${type}`; t.textContent=msg;
    document.body.appendChild(t); setTimeout(()=>t.remove(),3000);
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>