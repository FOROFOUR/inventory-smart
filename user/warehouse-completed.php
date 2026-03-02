<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$conn = getDBConnection();
$user_name = $_SESSION['name'] ?? 'Warehouse Staff';

// ── Handle AJAX ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'];

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit();
    }

    if ($action === 'return') {
        $stmt = $conn->prepare("
            UPDATE pull_out_transactions
            SET status = 'RETURNED', returned_at = NOW()
            WHERE id = ? AND status = 'RELEASED'
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $desc = "Asset from pull-out request #$id marked as RETURNED by $user_name";
            $log = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'RETURN_PULLOUT', ?)");
            $log->bind_param("ss", $user_name, $desc);
            $log->execute();
            echo json_encode(['success' => true, 'message' => "Request #$id marked as Returned."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
    exit();
}

// ── Filters ────────────────────────────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$dateFilter  = $_GET['date'] ?? '';
$statusFilter = $_GET['status'] ?? 'ALL'; // ALL, RELEASED, RETURNED

// ── Stats ──────────────────────────────────────────────────────────────────
$releasedCount = 0; $returnedCount = 0;
$s1 = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RELEASED'");
$s1->execute(); $s1->bind_result($releasedCount); $s1->fetch(); $s1->close();
$s2 = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RETURNED'");
$s2->execute(); $s2->bind_result($returnedCount); $s2->fetch(); $s2->close();

// ── Fetch transactions ─────────────────────────────────────────────────────
$sql = "
    SELECT p.*, a.brand, a.model, a.serial_number, a.location, a.sub_location,
           c.name AS category_name, sc.name AS subcategory_name
    FROM pull_out_transactions p
    LEFT JOIN assets a ON p.asset_id = a.id
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
    WHERE p.status IN ('RELEASED', 'RETURNED')
";

$params = []; $types = '';

if ($statusFilter !== 'ALL') {
    $sql .= " AND p.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
if ($search !== '') {
    $sql .= " AND (a.brand LIKE ? OR a.model LIKE ? OR p.requested_by LIKE ? OR a.serial_number LIKE ? OR p.received_by LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
    $types .= 'sssss';
}
if ($dateFilter !== '') {
    $sql .= " AND DATE(p.released_at) = ?";
    $params[] = $dateFilter;
    $types .= 's';
}

$sql .= " ORDER BY p.released_at DESC, p.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Completed — Warehouse</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
  :root {
    --amber: #f39c12;
    --amber-light: #fef9ed;
    --bg: #f4f6f9;
    --white: #ffffff;
    --text: #2c3e50;
    --text-muted: #7f8c8d;
    --border: #e8ecf0;
    --shadow: 0 2px 12px rgba(0,0,0,.07);
    --radius: 12px;
    --green: #27ae60;
    --blue: #2980b9;
    --red: #e74c3c;
    --returned: #3498db;
  }

  .main-content {
    position: relative;
    left: 250px;
    width: calc(100% - 250px);
    min-height: 100vh;
    background: var(--bg);
    padding: 28px 32px;
    transition: all 0.3s ease;
  }
  .sidebar.close ~ .main-content { left: 88px; width: calc(100% - 88px); }

  /* ── PAGE HEADER ── */
  .page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
  }
  .page-header h1 { font-size: 1.5rem; font-weight: 700; color: var(--text); }
  .page-header span { font-size: .875rem; color: var(--text-muted); display: block; margin-top: 2px; }

  /* ── STAT CARDS ── */
  .stats-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 22px;
  }
  .stat-card {
    background: var(--white);
    border-radius: var(--radius);
    padding: 18px 20px;
    box-shadow: var(--shadow);
    display: flex; align-items: center; gap: 14px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all .2s;
  }
  .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.1); }
  .stat-card.active-filter { border-color: var(--amber); }

  .stat-icon {
    width: 48px; height: 48px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
  }
  .stat-released .stat-icon { background: #eafaf1; color: var(--green); }
  .stat-returned .stat-icon { background: #ebf5fb; color: var(--returned); }

  .stat-info .count { font-size: 1.6rem; font-weight: 700; line-height: 1; }
  .stat-released .stat-info .count { color: var(--green); }
  .stat-returned .stat-info .count { color: var(--returned); }
  .stat-info .label { font-size: .75rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: .4px; }

  /* ── FILTERS ── */
  .filters {
    background: var(--white);
    border-radius: var(--radius);
    padding: 16px 20px;
    box-shadow: var(--shadow);
    display: flex; gap: 12px; align-items: center;
    margin-bottom: 22px; flex-wrap: wrap;
  }
  .filters input, .filters select {
    border: 1.5px solid var(--border); border-radius: 8px;
    padding: 8px 14px; font-size: .84rem;
    font-family: 'Poppins', sans-serif; color: var(--text);
    outline: none; transition: border-color .2s; background: var(--white);
  }
  .filters input:focus, .filters select:focus { border-color: var(--amber); }
  .search-wrap { position: relative; flex: 1; min-width: 200px; }
  .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
  .search-wrap input { padding-left: 36px; width: 100%; }

  .btn {
    padding: 8px 20px; border-radius: 8px; border: none;
    font-size: .84rem; font-family: 'Poppins', sans-serif; font-weight: 500;
    cursor: pointer; transition: all .2s;
    display: inline-flex; align-items: center; gap: 6px;
  }
  .btn-amber  { background: var(--amber); color: #fff; }
  .btn-amber:hover  { background: #d68910; }
  .btn-outline { background: transparent; border: 1.5px solid var(--border); color: var(--text-muted); }
  .btn-outline:hover { border-color: var(--amber); color: var(--amber); }

  /* ── TABLE ── */
  .card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
  .card-header {
    padding: 18px 22px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
  }
  .card-header h3 { font-size: .95rem; font-weight: 600; }
  .card-header small { font-size: .78rem; color: var(--text-muted); }

  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: .82rem; }
  thead th {
    background: #fafbfc; padding: 11px 16px; text-align: left;
    color: var(--text-muted); font-weight: 600; font-size: .74rem;
    text-transform: uppercase; letter-spacing: .5px;
    border-bottom: 1px solid var(--border); white-space: nowrap;
  }
  tbody td {
    padding: 13px 16px; border-bottom: 1px solid #f0f3f6;
    color: var(--text); vertical-align: middle;
  }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover td { background: #fafbfc; }

  .asset-name { font-weight: 600; color: var(--text); }
  .asset-meta { font-size: .73rem; color: var(--text-muted); margin-top: 2px; }

  .badge {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: .71rem; font-weight: 600; text-transform: uppercase; letter-spacing: .4px;
  }
  .badge-released { background: #eafaf1; color: #1e8449; }
  .badge-returned { background: #ebf5fb; color: #1a5276; }

  .location-tag {
    background: #f0f3f6; color: var(--text-muted);
    padding: 2px 8px; border-radius: 4px; font-size: .72rem; display: inline-block;
  }

  .btn-return {
    background: #ebf5fb; color: #1a5276;
    border: 1.5px solid #aed6f1;
    padding: 5px 12px; border-radius: 6px;
    font-size: .76rem; font-weight: 600;
    cursor: pointer; transition: all .2s;
    font-family: 'Poppins', sans-serif;
    display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap;
  }
  .btn-return:hover { background: var(--returned); color: #fff; border-color: var(--returned); }

  .done-label {
    font-size: .76rem; color: #a9cce3; font-style: italic;
  }

  /* ── EMPTY STATE ── */
  .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
  .empty-state i { font-size: 3rem; color: #d5d8dc; display: block; margin-bottom: 12px; }
  .empty-state p { font-size: .9rem; font-weight: 500; }
  .empty-state small { font-size: .8rem; color: #bdc3c7; }

  /* ── MODAL ── */
  .modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 9998;
    align-items: center; justify-content: center;
    backdrop-filter: blur(3px);
  }
  .modal-overlay.active { display: flex; }
  .modal {
    background: var(--white); border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    width: 100%; max-width: 440px; padding: 32px;
    animation: modalIn .25s ease;
  }
  @keyframes modalIn {
    from { transform: scale(.92) translateY(20px); opacity: 0; }
    to   { transform: scale(1) translateY(0); opacity: 1; }
  }
  .modal-icon {
    width: 60px; height: 60px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; margin: 0 auto 16px;
    background: #ebf5fb; color: var(--returned);
  }
  .modal h3 { text-align: center; font-size: 1.1rem; font-weight: 700; margin-bottom: 8px; }
  .modal p  { text-align: center; font-size: .84rem; color: var(--text-muted); margin-bottom: 20px; line-height: 1.6; }
  .modal-detail { background: #f8f9fa; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; font-size: .82rem; }
  .modal-detail-row { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #e8ecf0; }
  .modal-detail-row:last-child { border-bottom: none; }
  .modal-detail-row .label { color: var(--text-muted); }
  .modal-detail-row .val   { font-weight: 600; color: var(--text); text-align: right; max-width: 60%; }
  .modal-actions { display: flex; gap: 10px; }
  .modal-actions .btn { flex: 1; justify-content: center; padding: 10px; }
  .btn-blue  { background: var(--returned); color: #fff; }
  .btn-blue:hover  { background: #1a5276; }
  .btn-gray  { background: #f0f3f6; color: var(--text-muted); }
  .btn-gray:hover  { background: #e0e4e8; }

  /* ── TOAST ── */
  .toast-container { position: fixed; bottom: 28px; right: 28px; display: flex; flex-direction: column; gap: 10px; z-index: 99999; }
  .toast {
    background: #2c3e50; color: #fff;
    padding: 14px 20px; border-radius: 10px;
    font-size: .84rem; font-weight: 500;
    display: flex; align-items: center; gap: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.2);
    animation: toastIn .3s ease; min-width: 280px;
  }
  .toast.success { background: var(--returned); }
  .toast.error   { background: var(--red); }
  @keyframes toastIn {
    from { transform: translateX(100px); opacity: 0; }
    to   { transform: translateX(0); opacity: 1; }
  }

  @media (max-width: 900px) {
    .stats-row { grid-template-columns: 1fr 1fr; }
  }
  @media (max-width: 700px) {
    .main-content { padding: 18px 14px; }
    .stats-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<?php include 'warehouse_sidebar.php'; ?>

<div class="main-content">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1><i class='bx bx-check-circle' style="color:var(--green); margin-right:6px;"></i>Completed</h1>
      <span>Released and returned pull-out transactions</span>
    </div>
  </div>

  <!-- Stats Row (clickable filters) -->
  <div class="stats-row">
    <div class="stat-card stat-released <?= $statusFilter === 'RELEASED' ? 'active-filter' : '' ?>"
         onclick="setStatusFilter('RELEASED')">
      <div class="stat-icon"><i class='bx bx-check-double'></i></div>
      <div class="stat-info">
        <div class="count"><?= $releasedCount ?></div>
        <div class="label">Released</div>
      </div>
    </div>
    <div class="stat-card stat-returned <?= $statusFilter === 'RETURNED' ? 'active-filter' : '' ?>"
         onclick="setStatusFilter('RETURNED')">
      <div class="stat-icon"><i class='bx bx-undo'></i></div>
      <div class="stat-info">
        <div class="count"><?= $returnedCount ?></div>
        <div class="label">Returned</div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="filters" id="filterForm">
    <input type="hidden" name="status" id="statusInput" value="<?= htmlspecialchars($statusFilter) ?>">
    <div class="search-wrap">
      <i class='bx bx-search'></i>
      <input type="text" name="search" placeholder="Search asset, requester, received by..."
             value="<?= htmlspecialchars($search) ?>">
    </div>
    <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>" title="Filter by release date">
    <select name="status" id="statusSelect" onchange="document.getElementById('statusInput').value = this.value">
      <option value="ALL"      <?= $statusFilter === 'ALL'      ? 'selected' : '' ?>>All Status</option>
      <option value="RELEASED" <?= $statusFilter === 'RELEASED' ? 'selected' : '' ?>>Released</option>
      <option value="RETURNED" <?= $statusFilter === 'RETURNED' ? 'selected' : '' ?>>Returned</option>
    </select>
    <button type="submit" class="btn btn-amber"><i class='bx bx-filter-alt'></i> Filter</button>
    <?php if ($search || $dateFilter || $statusFilter !== 'ALL'): ?>
      <a href="warehouse-completed.php" class="btn btn-outline"><i class='bx bx-x'></i> Clear</a>
    <?php endif; ?>
  </form>

  <!-- Table -->
  <div class="card">
    <div class="card-header">
      <h3><i class='bx bx-list-check' style="color:var(--green); margin-right:6px;"></i>Transaction History</h3>
      <small><?= $transactions->num_rows ?> record<?= $transactions->num_rows != 1 ? 's' : '' ?> found</small>
    </div>
    <div class="table-wrap">
      <?php if ($transactions->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Asset</th>
            <th>Requested By</th>
            <th>Received By</th>
            <th>Route</th>
            <th>Released At</th>
            <th>Returned At</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $transactions->fetch_assoc()):
            $assetName = trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? '')) ?: 'Unknown Asset';
            $status    = $row['status'];
          ?>
          <tr id="row-<?= $row['id'] ?>">
            <td style="font-weight:600; color:var(--text-muted);">#<?= $row['id'] ?></td>
            <td>
              <div class="asset-name"><?= htmlspecialchars($assetName) ?></div>
              <div class="asset-meta">
                <?php if ($row['serial_number']): ?>S/N: <?= htmlspecialchars($row['serial_number']) ?><?php endif; ?>
                <?php if ($row['category_name']): ?> · <?= htmlspecialchars($row['category_name']) ?><?php endif; ?>
              </div>
            </td>
            <td><?= htmlspecialchars($row['requested_by'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['received_by'] ?? '—') ?></td>
            <td>
              <?php if ($row['from_location']): ?>
                <span class="location-tag"><?= htmlspecialchars($row['from_location']) ?></span>
                <i class='bx bx-right-arrow-alt' style="color:var(--text-muted); vertical-align:middle;"></i>
              <?php endif; ?>
              <?php if ($row['to_location']): ?>
                <span class="location-tag"><?= htmlspecialchars($row['to_location']) ?></span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td style="font-size:.78rem; color:var(--text-muted); white-space:nowrap;">
              <?= $row['released_at'] ? date('M j, Y<br>g:i A', strtotime($row['released_at'])) : '—' ?>
            </td>
            <td style="font-size:.78rem; color:var(--text-muted); white-space:nowrap;">
              <?= $row['returned_at'] ? date('M j, Y<br>g:i A', strtotime($row['returned_at'])) : '—' ?>
            </td>
            <td>
              <span class="badge badge-<?= strtolower($status) ?>"><?= ucfirst(strtolower($status)) ?></span>
            </td>
            <td>
              <?php if ($status === 'RELEASED'): ?>
                <button class="btn-return"
                  onclick="openReturnModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($assetName)) ?>', '<?= htmlspecialchars(addslashes($row['received_by'] ?? '')) ?>')">
                  <i class='bx bx-undo'></i> Mark Returned
                </button>
              <?php else: ?>
                <span class="done-label"><i class='bx bx-check-circle'></i> Done</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="empty-state">
          <i class='bx bx-check-circle'></i>
          <p>No completed transactions yet</p>
          <small>Released and returned items will appear here.</small>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /main-content -->

<!-- ── RETURN MODAL ── -->
<div class="modal-overlay" id="returnModal">
  <div class="modal">
    <div class="modal-icon"><i class='bx bx-undo'></i></div>
    <h3>Mark as Returned</h3>
    <p>Confirm that this asset has been returned to the warehouse.<br>Status will change to <strong>RETURNED</strong>.</p>
    <div class="modal-detail" id="returnDetail"></div>
    <div class="modal-actions">
      <button class="btn btn-gray" onclick="closeModal()"><i class='bx bx-x'></i> Cancel</button>
      <button class="btn btn-blue" onclick="submitReturn()"><i class='bx bx-undo'></i> Confirm Return</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
let currentId = null;

function openReturnModal(id, asset, receivedBy) {
  currentId = id;
  document.getElementById('returnDetail').innerHTML = `
    <div class="modal-detail-row"><span class="label">Request #</span><span class="val">${id}</span></div>
    <div class="modal-detail-row"><span class="label">Asset</span><span class="val">${asset}</span></div>
    <div class="modal-detail-row"><span class="label">Previously Received By</span><span class="val">${receivedBy || '—'}</span></div>
  `;
  document.getElementById('returnModal').classList.add('active');
}

function closeModal() {
  document.getElementById('returnModal').classList.remove('active');
  currentId = null;
}

document.querySelector('.modal-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

function submitReturn() {
  if (!currentId) return;

  document.querySelectorAll('.modal .btn').forEach(b => b.disabled = true);

  const formData = new FormData();
  formData.append('action', 'return');
  formData.append('id', currentId);

  fetch('warehouse-completed.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      closeModal();
      if (data.success) {
        showToast(data.message, 'success');
        // Update the row: change badge + remove button
        const row = document.getElementById('row-' + currentId);
        if (row) {
          const badgeCell = row.querySelector('.badge');
          if (badgeCell) {
            badgeCell.className = 'badge badge-returned';
            badgeCell.textContent = 'Returned';
          }
          const actionCell = row.querySelector('.btn-return');
          if (actionCell) {
            actionCell.outerHTML = `<span class="done-label"><i class='bx bx-check-circle'></i> Done</span>`;
          }
          // Update returned_at cell (6th td, index 6)
          const tds = row.querySelectorAll('td');
          if (tds[6]) {
            const now = new Date();
            const options = { month: 'short', day: 'numeric', year: 'numeric' };
            tds[6].innerHTML = now.toLocaleDateString('en-US', options) + '<br>' +
              now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
          }
          // Update returned count
          const retCount = document.querySelector('.stat-returned .count');
          if (retCount) retCount.textContent = parseInt(retCount.textContent) + 1;
          const relCount = document.querySelector('.stat-released .count');
          if (relCount) relCount.textContent = Math.max(0, parseInt(relCount.textContent) - 1);
        }
      } else {
        showToast(data.message, 'error');
      }
    })
    .catch(() => showToast('Network error. Please try again.', 'error'))
    .finally(() => document.querySelectorAll('.modal .btn').forEach(b => b.disabled = false));
}

function setStatusFilter(status) {
  document.getElementById('statusSelect').value = status;
  document.getElementById('filterForm').submit();
}

function showToast(message, type = 'success') {
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<i class='bx ${type === 'success' ? 'bx-check-circle' : 'bx-error-circle'}'></i> ${message}`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.transition = 'opacity .4s, transform .4s';
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(100px)';
    setTimeout(() => toast.remove(), 400);
  }, 3500);
}
</script>

</body>
</html>