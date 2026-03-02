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

    if ($action === 'release') {
        $released_by = $user_name;
        $stmt = $conn->prepare("
            UPDATE pull_out_transactions
            SET status = 'RELEASED', released_by = ?, released_at = NOW()
            WHERE id = ? AND status = 'CONFIRMED'
        ");
        $stmt->bind_param("si", $released_by, $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $desc = "Pull-out request #$id marked as RELEASED by $user_name";
            $log = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'RELEASE_PULLOUT', ?)");
            $log->bind_param("ss", $user_name, $desc);
            $log->execute();
            echo json_encode(['success' => true, 'message' => "Request #$id has been released."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed. Request may have already been processed.']);
        }

    } elseif ($action === 'revert') {
        // Send back to PENDING (e.g. warehouse made a mistake)
        $stmt = $conn->prepare("
            UPDATE pull_out_transactions
            SET status = 'PENDING'
            WHERE id = ? AND status = 'CONFIRMED'
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $desc = "Pull-out request #$id reverted to PENDING by $user_name";
            $log = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'REVERT_PULLOUT', ?)");
            $log->bind_param("ss", $user_name, $desc);
            $log->execute();
            echo json_encode(['success' => true, 'message' => "Request #$id reverted to Pending."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }
    exit();
}

// ── Fetch CONFIRMED requests ───────────────────────────────────────────────
$search     = trim($_GET['search'] ?? '');
$dateFilter = $_GET['date'] ?? '';

$sql = "
    SELECT p.*, a.brand, a.model, a.serial_number, a.location, a.sub_location,
           c.name AS category_name, sc.name AS subcategory_name
    FROM pull_out_transactions p
    LEFT JOIN assets a ON p.asset_id = a.id
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN sub_categories sc ON a.sub_category_id = sc.id
    WHERE p.status = 'CONFIRMED'
";

$params = []; $types = '';

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

$sql .= " ORDER BY p.created_at ASC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result();

// Count
$countStmt = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'CONFIRMED'");
$countStmt->execute();
$countStmt->bind_result($totalConfirmed);
$countStmt->fetch();
$countStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Preparing — Warehouse</title>
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
    --confirmed: #8e44ad;
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
  .sidebar.close ~ .main-content {
    left: 88px;
    width: calc(100% - 88px);
  }

  /* ── PAGE HEADER ── */
  .page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
  }
  .page-header h1 { font-size: 1.5rem; font-weight: 700; color: var(--text); }
  .page-header span { font-size: .875rem; color: var(--text-muted); display: block; margin-top: 2px; }

  .confirmed-badge {
    background: #f5eef8;
    color: var(--confirmed);
    border: 1.5px solid #d2b4de;
    padding: 6px 18px;
    border-radius: 20px;
    font-size: .82rem;
    font-weight: 600;
    display: flex; align-items: center; gap: 6px;
  }

  /* ── FILTERS ── */
  .filters {
    background: var(--white);
    border-radius: var(--radius);
    padding: 16px 20px;
    box-shadow: var(--shadow);
    display: flex; gap: 12px; align-items: center;
    margin-bottom: 22px; flex-wrap: wrap;
  }
  .filters input {
    border: 1.5px solid var(--border); border-radius: 8px;
    padding: 8px 14px; font-size: .84rem;
    font-family: 'Poppins', sans-serif; color: var(--text);
    outline: none; transition: border-color .2s;
  }
  .filters input:focus { border-color: var(--amber); }
  .search-wrap { position: relative; flex: 1; min-width: 200px; }
  .search-wrap i {
    position: absolute; left: 12px; top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted); pointer-events: none;
  }
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

  /* ── CARDS GRID ── */
  .cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 18px;
  }

  .request-card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
    border-top: 4px solid var(--confirmed);
  }
  .request-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 28px rgba(0,0,0,.11);
  }

  .card-top {
    padding: 18px 20px 14px;
  }

  .card-id-row {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 12px;
  }
  .card-id {
    font-size: .75rem; font-weight: 700;
    color: var(--confirmed); letter-spacing: .5px; text-transform: uppercase;
  }

  .urgency-badge {
    padding: 3px 10px; border-radius: 20px;
    font-size: .71rem; font-weight: 600;
  }
  .urgency-today  { background: #fdedec; color: #c0392b; }
  .urgency-soon   { background: #fef9ed; color: #d68910; }
  .urgency-normal { background: #eafaf1; color: #1e8449; }

  .asset-name {
    font-size: 1rem; font-weight: 700; color: var(--text); margin-bottom: 4px;
  }
  .asset-serial {
    font-size: .75rem; color: var(--text-muted);
  }

  .card-divider { border: none; border-top: 1px solid var(--border); margin: 14px 0; }

  .card-info-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 10px; padding: 0 20px 14px;
  }
  .info-item .info-label {
    font-size: .7rem; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px;
  }
  .info-item .info-val {
    font-size: .82rem; font-weight: 600; color: var(--text);
  }
  .info-item.full { grid-column: 1 / -1; }

  .location-arrow {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
  }
  .location-tag {
    background: #f0f3f6; color: var(--text-muted);
    padding: 2px 8px; border-radius: 4px; font-size: .72rem;
  }

  /* Checklist */
  .checklist {
    padding: 0 20px 14px;
  }
  .checklist-title {
    font-size: .74rem; font-weight: 600; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .4px; margin-bottom: 8px;
  }
  .checklist-item {
    display: flex; align-items: center; gap: 8px;
    font-size: .8rem; color: var(--text); margin-bottom: 5px;
    cursor: pointer; user-select: none;
  }
  .checklist-item input[type="checkbox"] {
    accent-color: var(--confirmed);
    width: 15px; height: 15px; cursor: pointer;
  }
  .checklist-item.checked { color: var(--text-muted); text-decoration: line-through; }

  /* Card Footer */
  .card-footer {
    padding: 14px 20px;
    border-top: 1px solid var(--border);
    background: #fafbfc;
    display: flex; gap: 8px;
  }
  .card-footer .btn { flex: 1; justify-content: center; font-size: .8rem; padding: 8px; }

  .btn-release { background: var(--green); color: #fff; }
  .btn-release:hover { background: #1e8449; }
  .btn-revert  { background: #f0f3f6; color: var(--text-muted); border: 1.5px solid var(--border); }
  .btn-revert:hover  { background: #e0e4e8; }

  /* ── EMPTY STATE ── */
  .empty-wrap {
    background: var(--white); border-radius: var(--radius);
    box-shadow: var(--shadow); padding: 60px 20px; text-align: center;
  }
  .empty-wrap i { font-size: 3.5rem; color: #d5d8dc; display: block; margin-bottom: 14px; }
  .empty-wrap p { font-size: .95rem; font-weight: 600; color: var(--text-muted); }
  .empty-wrap small { font-size: .8rem; color: #bdc3c7; }

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
    width: 100%; max-width: 460px; padding: 32px;
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
  }
  .release-icon { background: #eafaf1; color: var(--green); }
  .revert-icon  { background: #f0f3f6; color: var(--text-muted); }
  .modal h3 { text-align: center; font-size: 1.1rem; font-weight: 700; margin-bottom: 8px; }
  .modal p  { text-align: center; font-size: .84rem; color: var(--text-muted); margin-bottom: 20px; line-height: 1.6; }
  .modal-detail { background: #f8f9fa; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; font-size: .82rem; }
  .modal-detail-row { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #e8ecf0; }
  .modal-detail-row:last-child { border-bottom: none; }
  .modal-detail-row .label { color: var(--text-muted); }
  .modal-detail-row .val   { font-weight: 600; color: var(--text); text-align: right; max-width: 60%; }

  /* Received by field */
  .received-wrap { margin-bottom: 20px; }
  .received-wrap label { font-size: .82rem; font-weight: 600; color: var(--text); display: block; margin-bottom: 6px; }
  .received-wrap input {
    width: 100%; padding: 9px 14px;
    border: 1.5px solid var(--border); border-radius: 8px;
    font-family: 'Poppins', sans-serif; font-size: .83rem;
    outline: none; color: var(--text); transition: border-color .2s;
  }
  .received-wrap input:focus { border-color: var(--green); }

  .modal-actions { display: flex; gap: 10px; }
  .modal-actions .btn { flex: 1; justify-content: center; padding: 10px; }
  .btn-green  { background: var(--green); color: #fff; }
  .btn-green:hover  { background: #1e8449; }
  .btn-gray   { background: #f0f3f6; color: var(--text-muted); }
  .btn-gray:hover   { background: #e0e4e8; }

  /* ── TOAST ── */
  .toast-container {
    position: fixed; bottom: 28px; right: 28px;
    display: flex; flex-direction: column; gap: 10px; z-index: 99999;
  }
  .toast {
    background: #2c3e50; color: #fff;
    padding: 14px 20px; border-radius: 10px;
    font-size: .84rem; font-weight: 500;
    display: flex; align-items: center; gap: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.2);
    animation: toastIn .3s ease; min-width: 280px;
  }
  .toast.success { background: var(--green); }
  .toast.error   { background: var(--red); }
  @keyframes toastIn {
    from { transform: translateX(100px); opacity: 0; }
    to   { transform: translateX(0); opacity: 1; }
  }

  @media (max-width: 700px) {
    .main-content { padding: 18px 14px; }
    .cards-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<?php include 'warehouse_sidebar.php'; ?>

<div class="main-content">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1><i class='bx bx-loader-circle' style="color:var(--confirmed); margin-right:6px;"></i>Preparing</h1>
      <span>Confirmed requests being prepared for release</span>
    </div>
    <div class="confirmed-badge">
      <i class='bx bx-package'></i>
      <?= $totalConfirmed ?> Being Prepared
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="filters">
    <div class="search-wrap">
      <i class='bx bx-search'></i>
      <input type="text" name="search" placeholder="Search asset, requester, serial no..."
             value="<?= htmlspecialchars($search) ?>">
    </div>
    <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
    <button type="submit" class="btn btn-amber"><i class='bx bx-filter-alt'></i> Filter</button>
    <?php if ($search || $dateFilter): ?>
      <a href="warehouse-preparing.php" class="btn btn-outline"><i class='bx bx-x'></i> Clear</a>
    <?php endif; ?>
  </form>

  <!-- Cards Grid -->
  <?php if ($requests->num_rows > 0): ?>
  <div class="cards-grid" id="cardsGrid">
    <?php while ($row = $requests->fetch_assoc()):
      $assetName = trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? '')) ?: 'Unknown Asset';
      $urgency = 'normal'; $urgencyLabel = 'On Time';
      if ($row['date_needed']) {
        $daysLeft = (strtotime($row['date_needed']) - strtotime('today')) / 86400;
        if ($daysLeft <= 0)     { $urgency = 'today'; $urgencyLabel = 'Due Today!'; }
        elseif ($daysLeft <= 2) { $urgency = 'soon';  $urgencyLabel = 'Due Soon'; }
      }
    ?>
    <div class="request-card" id="card-<?= $row['id'] ?>">
      <div class="card-top">
        <div class="card-id-row">
          <span class="card-id"><i class='bx bx-transfer-alt'></i> Request #<?= $row['id'] ?></span>
          <?php if ($row['date_needed']): ?>
            <span class="urgency-badge urgency-<?= $urgency ?>"><?= $urgencyLabel ?></span>
          <?php endif; ?>
        </div>
        <div class="asset-name"><?= htmlspecialchars($assetName) ?></div>
        <?php if ($row['serial_number']): ?>
          <div class="asset-serial">S/N: <?= htmlspecialchars($row['serial_number']) ?></div>
        <?php endif; ?>
      </div>

      <hr class="card-divider">

      <div class="card-info-grid">
        <div class="info-item">
          <div class="info-label">Requested By</div>
          <div class="info-val"><?= htmlspecialchars($row['requested_by'] ?? '—') ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Date Needed</div>
          <div class="info-val"><?= $row['date_needed'] ? date('M j, Y', strtotime($row['date_needed'])) : '—' ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Quantity</div>
          <div class="info-val"><?= $row['quantity'] ?? 1 ?> pc<?= ($row['quantity'] ?? 1) > 1 ? 's' : '' ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Category</div>
          <div class="info-val"><?= htmlspecialchars($row['category_name'] ?? '—') ?></div>
        </div>
        <?php if ($row['from_location'] || $row['to_location']): ?>
        <div class="info-item full">
          <div class="info-label">Route</div>
          <div class="location-arrow">
            <?php if ($row['from_location']): ?>
              <span class="location-tag"><?= htmlspecialchars($row['from_location']) ?></span>
              <i class='bx bx-right-arrow-alt' style="color:var(--text-muted);"></i>
            <?php endif; ?>
            <?php if ($row['to_location']): ?>
              <span class="location-tag"><?= htmlspecialchars($row['to_location']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($row['purpose']): ?>
        <div class="info-item full">
          <div class="info-label">Purpose</div>
          <div class="info-val" style="font-weight:400; color:var(--text-muted); font-size:.8rem;">
            <?= htmlspecialchars($row['purpose']) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Preparation Checklist -->
      <div class="checklist">
        <div class="checklist-title"><i class='bx bx-check-square'></i> Preparation Checklist</div>
        <label class="checklist-item">
          <input type="checkbox" onchange="toggleCheck(this)">
          <span>Asset located and retrieved</span>
        </label>
        <label class="checklist-item">
          <input type="checkbox" onchange="toggleCheck(this)">
          <span>Condition checked and verified</span>
        </label>
        <label class="checklist-item">
          <input type="checkbox" onchange="toggleCheck(this)">
          <span>Packed and ready for handover</span>
        </label>
      </div>

      <div class="card-footer">
        <button class="btn btn-release"
          onclick="openReleaseModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($assetName)) ?>', '<?= htmlspecialchars(addslashes($row['requested_by'] ?? '')) ?>')">
          <i class='bx bx-check-double'></i> Mark as Released
        </button>
        <button class="btn btn-revert"
          onclick="openRevertModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($assetName)) ?>')">
          <i class='bx bx-undo'></i> Revert
        </button>
      </div>
    </div>
    <?php endwhile; ?>
  </div>

  <?php else: ?>
  <div class="empty-wrap">
    <i class='bx bx-package'></i>
    <p>No items being prepared</p>
    <small>Confirmed requests will appear here once acknowledged from Incoming Orders.</small>
  </div>
  <?php endif; ?>

</div><!-- /main-content -->

<!-- ── RELEASE MODAL ── -->
<div class="modal-overlay" id="releaseModal">
  <div class="modal">
    <div class="modal-icon release-icon"><i class='bx bx-check-double'></i></div>
    <h3>Mark as Released</h3>
    <p>Confirm that this asset has been handed over.<br>Status will change to <strong>RELEASED</strong>.</p>
    <div class="modal-detail" id="releaseDetail"></div>
    <div class="received-wrap">
      <label><i class='bx bx-user-check'></i> Received By</label>
      <input type="text" id="receivedBy" placeholder="Enter name of person receiving the asset...">
    </div>
    <div class="modal-actions">
      <button class="btn btn-gray" onclick="closeModal('releaseModal')"><i class='bx bx-x'></i> Cancel</button>
      <button class="btn btn-green" onclick="submitAction('release')"><i class='bx bx-check-double'></i> Confirm Release</button>
    </div>
  </div>
</div>

<!-- ── REVERT MODAL ── -->
<div class="modal-overlay" id="revertModal">
  <div class="modal">
    <div class="modal-icon revert-icon"><i class='bx bx-undo'></i></div>
    <h3>Revert to Pending?</h3>
    <p>This will send the request back to <strong>Incoming Orders</strong> with status <strong>PENDING</strong>.</p>
    <div class="modal-detail" id="revertDetail"></div>
    <div class="modal-actions">
      <button class="btn btn-gray" onclick="closeModal('revertModal')"><i class='bx bx-x'></i> Cancel</button>
      <button class="btn btn-amber" onclick="submitAction('revert')"><i class='bx bx-undo'></i> Yes, Revert</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
let currentId = null;
let currentAction = null;

function openReleaseModal(id, asset, requestedBy) {
  currentId = id; currentAction = 'release';
  document.getElementById('releaseDetail').innerHTML = `
    <div class="modal-detail-row"><span class="label">Request #</span><span class="val">${id}</span></div>
    <div class="modal-detail-row"><span class="label">Asset</span><span class="val">${asset}</span></div>
    <div class="modal-detail-row"><span class="label">Requested By</span><span class="val">${requestedBy}</span></div>
  `;
  document.getElementById('receivedBy').value = '';
  document.getElementById('releaseModal').classList.add('active');
  setTimeout(() => document.getElementById('receivedBy').focus(), 300);
}

function openRevertModal(id, asset) {
  currentId = id; currentAction = 'revert';
  document.getElementById('revertDetail').innerHTML = `
    <div class="modal-detail-row"><span class="label">Request #</span><span class="val">${id}</span></div>
    <div class="modal-detail-row"><span class="label">Asset</span><span class="val">${asset}</span></div>
  `;
  document.getElementById('revertModal').classList.add('active');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('active');
  currentId = null;
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', function(e) {
    if (e.target === this) closeModal(this.id);
  });
});

function submitAction(action) {
  if (!currentId) return;

  const formData = new FormData();
  formData.append('action', action);
  formData.append('id', currentId);

  if (action === 'release') {
    const received = document.getElementById('receivedBy').value.trim();
    if (received) formData.append('received_by', received);
  }

  document.querySelectorAll('.modal .btn').forEach(b => b.disabled = true);

  fetch('warehouse-preparing.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      const modalId = action === 'release' ? 'releaseModal' : 'revertModal';
      closeModal(modalId);
      if (data.success) {
        showToast(data.message, 'success');
        const card = document.getElementById('card-' + currentId);
        if (card) {
          card.style.transition = 'opacity .4s, transform .4s';
          card.style.opacity = '0';
          card.style.transform = 'scale(.95)';
          setTimeout(() => {
            card.remove();
            updateCount();
          }, 400);
        }
      } else {
        showToast(data.message, 'error');
      }
    })
    .catch(() => showToast('Network error. Please try again.', 'error'))
    .finally(() => document.querySelectorAll('.modal .btn').forEach(b => b.disabled = false));
}

function updateCount() {
  const cards = document.querySelectorAll('.request-card');
  const count = cards.length;
  const badge = document.querySelector('.confirmed-badge');
  if (badge) badge.innerHTML = `<i class='bx bx-package'></i> ${count} Being Prepared`;

  if (count === 0) {
    document.getElementById('cardsGrid').outerHTML = `
      <div class="empty-wrap">
        <i class='bx bx-package'></i>
        <p>No items being prepared</p>
        <small>Confirmed requests will appear here once acknowledged from Incoming Orders.</small>
      </div>`;
  }
}

function toggleCheck(checkbox) {
  const label = checkbox.closest('.checklist-item');
  label.classList.toggle('checked', checkbox.checked);
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