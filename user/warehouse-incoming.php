<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$conn = getDBConnection();

$user_name = $_SESSION['name'] ?? 'Warehouse Staff';

// ── Handle AJAX status update ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action']; // 'confirm' or 'reject'

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit();
    }

    if ($action === 'confirm') {
        $newStatus = 'CONFIRMED';
        $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = ? WHERE id = ? AND status = 'PENDING'");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            // Log activity
            $desc = "Pull-out request #$id confirmed by " . $user_name;
            $log = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'CONFIRM_PULLOUT', ?)");
            $log->bind_param("ss", $user_name, $desc);
            $log->execute();

            echo json_encode(['success' => true, 'message' => "Request #$id confirmed successfully."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed. Request may have already been processed.']);
        }

    } elseif ($action === 'reject') {
        $reason = htmlspecialchars(trim($_POST['reason'] ?? ''));
        $newStatus = 'CANCELLED';
        $stmt = $conn->prepare("UPDATE pull_out_transactions SET status = ? WHERE id = ? AND status = 'PENDING'");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $desc = "Pull-out request #$id rejected by $user_name" . ($reason ? ". Reason: $reason" : ".");
            $log = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'REJECT_PULLOUT', ?)");
            $log->bind_param("ss", $user_name, $desc);
            $log->execute();

            echo json_encode(['success' => true, 'message' => "Request #$id rejected."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed. Request may have already been processed.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
    exit();
}

// ── Fetch PENDING requests ─────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$dateFilter = $_GET['date'] ?? '';

$sql = "
    SELECT p.*, a.brand, a.model, a.serial_number, a.location, a.sub_location,
           c.name AS category_name, sc.name AS subcategory_name
    FROM pull_out_transactions p
    LEFT JOIN assets a ON p.asset_id = a.id
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
    WHERE p.status = 'PENDING'
";

$params = [];
$types  = '';

if ($search !== '') {
    $sql .= " AND (a.brand LIKE ? OR a.model LIKE ? OR p.requested_by LIKE ? OR a.serial_number LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= 'ssss';
}

if ($dateFilter !== '') {
    $sql .= " AND DATE(p.created_at) = ?";
    $params[] = $dateFilter;
    $types .= 's';
}

$sql .= " ORDER BY p.created_at ASC"; // Oldest first — FIFO

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$requests = $stmt->get_result();

// ── Count ──────────────────────────────────────────────────────────────────
$countStmt = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'PENDING'");
$countStmt->execute();
$countStmt->bind_result($totalPending);
$countStmt->fetch();
$countStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Incoming Orders — Warehouse</title>
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
    --red: #e74c3c;
  }

  /* ── LAYOUT ── */
  .main-content {
    position: relative;
    left: 250px;
    width: calc(100% - 250px);
    min-height: 100vh;
    background: var(--bg);
    padding: 28px 32px;
    transition: all 0.3s ease;
  }
  .sidebar.close ~ .main-content {
    left: 88px;
    width: calc(100% - 88px);
  }

  /* ── PAGE HEADER ── */
  .page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
  }
  .page-header h1 { font-size: 1.5rem; font-weight: 700; color: var(--text); }
  .page-header span { font-size: .875rem; color: var(--text-muted); display: block; margin-top: 2px; }

  .pending-badge {
    background: #fef9ed;
    color: var(--amber);
    border: 1.5px solid #f5cba7;
    padding: 6px 18px;
    border-radius: 20px;
    font-size: .82rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  /* ── FILTERS ── */
  .filters {
    background: var(--white);
    border-radius: var(--radius);
    padding: 16px 20px;
    box-shadow: var(--shadow);
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 22px;
    flex-wrap: wrap;
  }
  .filters input, .filters input[type="date"] {
    border: 1.5px solid var(--border);
    border-radius: 8px;
    padding: 8px 14px;
    font-size: .84rem;
    font-family: 'Poppins', sans-serif;
    color: var(--text);
    outline: none;
    transition: border-color .2s;
  }
  .filters input:focus { border-color: var(--amber); }
  .search-wrap { position: relative; flex: 1; min-width: 200px; }
  .search-wrap i {
    position: absolute; left: 12px; top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted); font-size: 1rem;
    pointer-events: none;
  }
  .search-wrap input { padding-left: 36px; width: 100%; }

  .btn {
    padding: 8px 20px;
    border-radius: 8px;
    border: none;
    font-size: .84rem;
    font-family: 'Poppins', sans-serif;
    font-weight: 500;
    cursor: pointer;
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .btn-amber { background: var(--amber); color: #fff; }
  .btn-amber:hover { background: #d68910; }
  .btn-outline { background: transparent; border: 1.5px solid var(--border); color: var(--text-muted); }
  .btn-outline:hover { border-color: var(--amber); color: var(--amber); }

  /* ── TABLE CARD ── */
  .card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
  }
  .card-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
  }
  .card-header h3 { font-size: .95rem; font-weight: 600; }
  .card-header small { font-size: .78rem; color: var(--text-muted); }

  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: .82rem; }
  thead th {
    background: #fafbfc;
    padding: 11px 16px;
    text-align: left;
    color: var(--text-muted);
    font-weight: 600;
    font-size: .74rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
  }
  tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #f0f3f6;
    color: var(--text);
    vertical-align: middle;
  }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover td { background: #fafbfc; }

  .asset-name { font-weight: 600; color: var(--text); }
  .asset-meta { font-size: .73rem; color: var(--text-muted); margin-top: 2px; }

  .location-tag {
    background: #f0f3f6;
    color: var(--text-muted);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: .72rem;
    display: inline-block;
  }

  .urgency-badge {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .71rem;
    font-weight: 600;
    white-space: nowrap;
  }
  .urgency-today  { background: #fdedec; color: #c0392b; }
  .urgency-soon   { background: #fef9ed; color: #d68910; }
  .urgency-normal { background: #eafaf1; color: #1e8449; }

  /* Action Buttons */
  .action-wrap { display: flex; gap: 8px; align-items: center; }
  .btn-confirm {
    background: #eafaf1; color: #1e8449;
    border: 1.5px solid #a9dfbf;
    padding: 5px 14px; border-radius: 6px;
    font-size: .78rem; font-weight: 600;
    cursor: pointer; transition: all .2s;
    font-family: 'Poppins', sans-serif;
    display: inline-flex; align-items: center; gap: 5px;
  }
  .btn-confirm:hover { background: var(--green); color: #fff; border-color: var(--green); }

  .btn-reject {
    background: #fdedec; color: #c0392b;
    border: 1.5px solid #f1948a;
    padding: 5px 14px; border-radius: 6px;
    font-size: .78rem; font-weight: 600;
    cursor: pointer; transition: all .2s;
    font-family: 'Poppins', sans-serif;
    display: inline-flex; align-items: center; gap: 5px;
  }
  .btn-reject:hover { background: var(--red); color: #fff; border-color: var(--red); }

  /* ── EMPTY STATE ── */
  .empty-state {
    text-align: center; padding: 60px 20px; color: var(--text-muted);
  }
  .empty-state i { font-size: 3rem; color: #d5d8dc; display: block; margin-bottom: 12px; }
  .empty-state p { font-size: .9rem; font-weight: 500; }
  .empty-state small { font-size: .8rem; }

  /* ── MODAL ── */
  .modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9998;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(3px);
  }
  .modal-overlay.active { display: flex; }

  .modal {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    width: 100%;
    max-width: 480px;
    padding: 32px;
    animation: modalIn .25s ease;
    position: relative;
  }

  @keyframes modalIn {
    from { transform: scale(.92) translateY(20px); opacity: 0; }
    to   { transform: scale(1) translateY(0); opacity: 1; }
  }

  .modal-icon {
    width: 60px; height: 60px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 16px;
  }
  .modal-icon.confirm-icon { background: #eafaf1; color: var(--green); }
  .modal-icon.reject-icon  { background: #fdedec; color: var(--red); }

  .modal h3 { text-align: center; font-size: 1.1rem; font-weight: 700; margin-bottom: 8px; }
  .modal p  { text-align: center; font-size: .84rem; color: var(--text-muted); margin-bottom: 20px; line-height: 1.6; }

  .modal-detail {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 20px;
    font-size: .82rem;
  }
  .modal-detail-row {
    display: flex; justify-content: space-between;
    padding: 4px 0;
    border-bottom: 1px solid #e8ecf0;
  }
  .modal-detail-row:last-child { border-bottom: none; }
  .modal-detail-row .label { color: var(--text-muted); }
  .modal-detail-row .val   { font-weight: 600; color: var(--text); text-align: right; max-width: 60%; }

  .modal textarea {
    width: 100%; padding: 10px 14px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: 'Poppins', sans-serif;
    font-size: .83rem;
    resize: vertical; min-height: 80px;
    outline: none; color: var(--text);
    margin-bottom: 20px;
    transition: border-color .2s;
  }
  .modal textarea:focus { border-color: var(--red); }

  .modal-actions { display: flex; gap: 10px; }
  .modal-actions .btn { flex: 1; justify-content: center; padding: 10px; }
  .btn-green  { background: var(--green); color: #fff; }
  .btn-green:hover  { background: #1e8449; }
  .btn-danger { background: var(--red); color: #fff; }
  .btn-danger:hover { background: #c0392b; }
  .btn-gray   { background: #f0f3f6; color: var(--text-muted); }
  .btn-gray:hover   { background: #e0e4e8; }

  /* ── TOAST ── */
  .toast-container {
    position: fixed; bottom: 28px; right: 28px;
    display: flex; flex-direction: column; gap: 10px;
    z-index: 99999;
  }
  .toast {
    background: #2c3e50; color: #fff;
    padding: 14px 20px; border-radius: 10px;
    font-size: .84rem; font-weight: 500;
    display: flex; align-items: center; gap: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.2);
    animation: toastIn .3s ease;
    min-width: 280px;
  }
  .toast.success { background: var(--green); }
  .toast.error   { background: var(--red); }
  @keyframes toastIn {
    from { transform: translateX(100px); opacity: 0; }
    to   { transform: translateX(0); opacity: 1; }
  }

  /* ── RESPONSIVE ── */
  @media (max-width: 700px) {
    .main-content { padding: 18px 14px; }
    .filters { flex-direction: column; }
  }
</style>
</head>
<body>

<?php include 'warehouse_sidebar.php'; ?>

<div class="main-content">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1><i class='bx bx-box' style="color:var(--amber); margin-right:6px;"></i>Incoming Orders</h1>
      <span>Review and confirm pending pull-out requests</span>
    </div>
    <div class="pending-badge">
      <i class='bx bx-time-five'></i>
      <?= $totalPending ?> Pending Request<?= $totalPending != 1 ? 's' : '' ?>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="filters">
    <div class="search-wrap">
      <i class='bx bx-search'></i>
      <input type="text" name="search" placeholder="Search asset, requester, serial no..."
             value="<?= htmlspecialchars($search) ?>">
    </div>
    <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>" title="Filter by date submitted">
    <button type="submit" class="btn btn-amber"><i class='bx bx-filter-alt'></i> Filter</button>
    <?php if ($search || $dateFilter): ?>
      <a href="warehouse-incoming.php" class="btn btn-outline"><i class='bx bx-x'></i> Clear</a>
    <?php endif; ?>
  </form>

  <!-- Requests Table -->
  <div class="card">
    <div class="card-header">
      <h3><i class='bx bx-list-ul' style="color:var(--amber); margin-right:6px;"></i>Pending Pull-Out Requests</h3>
      <small>Sorted by: Oldest first (FIFO)</small>
    </div>
    <div class="table-wrap">
      <?php if ($requests->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Asset</th>
            <th>From → To</th>
            <th>Requested By</th>
            <th>Purpose</th>
            <th>Date Needed</th>
            <th>Submitted</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $requests->fetch_assoc()):
            // Urgency logic
            $urgency = 'normal'; $urgencyLabel = 'On Time';
            if ($row['date_needed']) {
              $daysLeft = (strtotime($row['date_needed']) - strtotime('today')) / 86400;
              if ($daysLeft <= 0)       { $urgency = 'today'; $urgencyLabel = 'Due Today!'; }
              elseif ($daysLeft <= 2)   { $urgency = 'soon';  $urgencyLabel = 'Due Soon'; }
            }
            $assetName = trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? '')) ?: 'Unknown Asset';
          ?>
          <tr id="row-<?= $row['id'] ?>">
            <td style="font-weight:600; color:var(--text-muted);">#<?= $row['id'] ?></td>
            <td>
              <div class="asset-name"><?= htmlspecialchars($assetName) ?></div>
              <div class="asset-meta">
                <?php if ($row['serial_number']): ?>S/N: <?= htmlspecialchars($row['serial_number']) ?> · <?php endif; ?>
                <?= htmlspecialchars($row['category_name'] ?? '') ?>
                <?php if ($row['subcategory_name']): ?> / <?= htmlspecialchars($row['subcategory_name']) ?><?php endif; ?>
              </div>
            </td>
            <td>
              <?php if ($row['from_location']): ?>
                <span class="location-tag"><?= htmlspecialchars($row['from_location']) ?></span>
                <i class='bx bx-right-arrow-alt' style="color:var(--text-muted); font-size:.9rem; vertical-align:middle;"></i>
              <?php endif; ?>
              <?php if ($row['to_location']): ?>
                <span class="location-tag"><?= htmlspecialchars($row['to_location']) ?></span>
              <?php else: ?>
                <span style="color:var(--text-muted); font-size:.8rem;">—</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['requested_by'] ?? '—') ?></td>
            <td style="max-width:160px; font-size:.8rem; color:var(--text-muted);">
              <?= htmlspecialchars($row['purpose'] ? (strlen($row['purpose']) > 60 ? substr($row['purpose'],0,60).'…' : $row['purpose']) : '—') ?>
            </td>
            <td>
              <?php if ($row['date_needed']): ?>
                <div><?= date('M j, Y', strtotime($row['date_needed'])) ?></div>
                <span class="urgency-badge urgency-<?= $urgency ?>"><?= $urgencyLabel ?></span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td style="font-size:.78rem; color:var(--text-muted);">
              <?= date('M j, Y<br>g:i A', strtotime($row['created_at'])) ?>
            </td>
            <td>
              <div class="action-wrap">
                
                <button class="btn-reject"
                  onclick="openRejectModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($assetName)) ?>')">
                  <i class='bx bx-x'></i> Reject
                </button>
                   <button class="btn-confirm"
                  onclick="openConfirmModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($assetName)) ?>', '<?= htmlspecialchars(addslashes($row['requested_by'] ?? '')) ?>', '<?= $row['date_needed'] ? date('M j, Y', strtotime($row['date_needed'])) : '—' ?>')">
                  <i class='bx bx-check'></i> Confirm
                </button>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="empty-state">
          <i class='bx bx-check-shield'></i>
          <p>All clear! No pending requests.</p>
          <small>New pull-out requests will appear here.</small>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /main-content -->

<!-- ── CONFIRM MODAL ── -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal">
    <div class="modal-icon confirm-icon"><i class='bx bx-check-shield'></i></div>
    <h3>Confirm Pull-Out Request</h3>
    <p>Are you sure you want to confirm this request?<br>Status will change to <strong>CONFIRMED</strong>.</p>
    <div class="modal-detail" id="confirmDetail"></div>
    <div class="modal-actions">
      <button class="btn btn-gray" onclick="closeModal('confirmModal')"><i class='bx bx-x'></i> Cancel</button>
      <button class="btn btn-green" id="confirmBtn" onclick="submitAction('confirm')"><i class='bx bx-check'></i> Yes, Confirm</button>
    </div>
  </div>
</div>

<!-- ── REJECT MODAL ── -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <div class="modal-icon reject-icon"><i class='bx bxs-error'></i></div>
    <h3>Reject Pull-Out Request</h3>
    <p>This request will be marked as <strong>CANCELLED</strong>.<br>Please provide a reason (optional).</p>
    <div class="modal-detail" id="rejectDetail"></div>
    <textarea id="rejectReason" placeholder="Enter reason for rejection (optional)..."></textarea>
    <div class="modal-actions">
      <button class="btn btn-gray" onclick="closeModal('rejectModal')"><i class='bx bx-x'></i> Cancel</button>
      <button class="btn btn-danger" onclick="submitAction('reject')"><i class='bx bxs-error'></i> Yes, Reject</button>
    </div>
  </div>
</div>

<!-- ── TOAST CONTAINER ── -->
<div class="toast-container" id="toastContainer"></div>

<script>
let currentId = null;
let currentAction = null;

function openConfirmModal(id, asset, requestedBy, dateNeeded) {
  currentId     = id;
  currentAction = 'confirm';
  document.getElementById('confirmDetail').innerHTML = `
    <div class="modal-detail-row"><span class="label">Request #</span><span class="val">${id}</span></div>
    <div class="modal-detail-row"><span class="label">Asset</span><span class="val">${asset}</span></div>
    <div class="modal-detail-row"><span class="label">Requested By</span><span class="val">${requestedBy}</span></div>
    <div class="modal-detail-row"><span class="label">Date Needed</span><span class="val">${dateNeeded}</span></div>
  `;
  document.getElementById('confirmModal').classList.add('active');
}

function openRejectModal(id, asset) {
  currentId     = id;
  currentAction = 'reject';
  document.getElementById('rejectDetail').innerHTML = `
    <div class="modal-detail-row"><span class="label">Request #</span><span class="val">${id}</span></div>
    <div class="modal-detail-row"><span class="label">Asset</span><span class="val">${asset}</span></div>
  `;
  document.getElementById('rejectReason').value = '';
  document.getElementById('rejectModal').classList.add('active');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('active');
  currentId = null;
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', function(e) {
    if (e.target === this) closeModal(this.id);
  });
});

function submitAction(action) {
  if (!currentId) return;

  const reason = action === 'reject'
    ? document.getElementById('rejectReason').value
    : '';

  // Disable buttons during request
  document.querySelectorAll('.modal .btn').forEach(b => b.disabled = true);

  const formData = new FormData();
  formData.append('action', action);
  formData.append('id', currentId);
  if (reason) formData.append('reason', reason);

  fetch('warehouse-incoming.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      closeModal(action === 'confirm' ? 'confirmModal' : 'rejectModal');
      if (data.success) {
        showToast(data.message, 'success');
        // Remove row with fade
        const row = document.getElementById('row-' + currentId);
        if (row) {
          row.style.transition = 'opacity .4s, transform .4s';
          row.style.opacity = '0';
          row.style.transform = 'translateX(-20px)';
          setTimeout(() => {
            row.remove();
            updatePendingCount();
          }, 400);
        }
      } else {
        showToast(data.message, 'error');
      }
    })
    .catch(() => showToast('Network error. Please try again.', 'error'))
    .finally(() => {
      document.querySelectorAll('.modal .btn').forEach(b => b.disabled = false);
    });
}

function updatePendingCount() {
  const rows = document.querySelectorAll('tbody tr');
  const count = rows.length;
  const badge = document.querySelector('.pending-badge');
  if (badge) badge.innerHTML = `<i class='bx bx-time-five'></i> ${count} Pending Request${count !== 1 ? 's' : ''}`;

  if (count === 0) {
    document.querySelector('tbody').innerHTML = `
      <tr><td colspan="8">
        <div class="empty-state">
          <i class='bx bx-check-shield'></i>
          <p>All clear! No pending requests.</p>
          <small>New pull-out requests will appear here.</small>
        </div>
      </td></tr>`;
  }
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