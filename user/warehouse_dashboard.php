<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$conn = getDBConnection();

$user_name = $_SESSION['name'] ?? 'Warehouse Staff';
$user_role = $_SESSION['role'] ?? 'EMPLOYEE';
$user_pic  = $_SESSION['profile_pic'] ?? null;

// ── Stats (Warehouse-relevant only) ────────────────────────────────────────
$stats = [];
$statuses = ['CONFIRMED', 'RELEASED', 'RETURNED', 'CANCELLED'];
foreach ($statuses as $s) {
    $q = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = ?");
    $q->bind_param("s", $s);
    $q->execute();
    $q->bind_result($stats[$s]);
    $q->fetch();
    $q->close();
}

$total = array_sum($stats);

// ── Today released ──────────────────────────────────────────────────────────
$q = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'RELEASED' AND DATE(released_at) = CURDATE()");
$q->execute(); $q->bind_result($today_released); $q->fetch(); $q->close();

// ── Items being prepared (CONFIRMED) ───────────────────────────────────────
$q = $conn->prepare("SELECT COUNT(*) FROM pull_out_transactions WHERE status = 'CONFIRMED'");
$q->execute(); $q->bind_result($preparing_count); $q->fetch(); $q->close();

// ── Recent Transactions (warehouse-relevant statuses only) ─────────────────
$recent = $conn->query("
    SELECT p.*, a.brand, a.model, a.serial_number
    FROM pull_out_transactions p
    LEFT JOIN assets a ON p.asset_id = a.id
    WHERE p.status IN ('CONFIRMED','RELEASED','RETURNED','CANCELLED')
    ORDER BY p.created_at DESC
    LIMIT 10
");

// ── Recent Activity Logs ───────────────────────────────────────────────────
$logs = $conn->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 6");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QC Warehouse — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<style>
  :root {
    --sidebar-width: 260px;
    --sidebar-collapsed: 70px;
    --amber: #f39c12;
    --amber-light: #fef9ed;
    --amber-dark: #d68910;
    --bg: #f4f6f9;
    --white: #ffffff;
    --text: #2c3e50;
    --text-muted: #7f8c8d;
    --border: #e8ecf0;
    --shadow: 0 2px 12px rgba(0,0,0,.07);
    --radius: 12px;
    --confirmed: #8e44ad;
    --released: #27ae60;
    --returned: #3498db;
    --cancelled: #e74c3c;
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Poppins', sans-serif;
    background: var(--bg);
    color: var(--text);
    display: flex;
    min-height: 100vh;
  }

  .main-content { position: relative; left: 250px; width: calc(100% - 250px); min-height: 100vh; background: var(--bg); padding: 28px 32px; transition: all 0.3s ease; }
  .sidebar.close ~ .main-content { left: 88px; width: calc(100% - 88px); }

  /* ── PAGE HEADER ── */
  .page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 28px;
  }
  .page-header h1 { font-size: 1.5rem; font-weight: 700; color: var(--text); }
  .page-header span { font-size: .875rem; color: var(--text-muted); display: block; margin-top: 2px; font-weight: 400; }

  .date-badge {
    background: var(--white); border: 1px solid var(--border);
    padding: 8px 16px; border-radius: 8px; font-size: .82rem;
    color: var(--text-muted); display: flex; align-items: center; gap: 7px;
  }
  .date-badge i { color: var(--amber); font-size: 1rem; }

  /* ── STAT CARDS ── */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 28px;
  }

  .stat-card {
    background: var(--white); border-radius: var(--radius);
    padding: 22px 20px; box-shadow: var(--shadow);
    display: flex; align-items: center; gap: 16px;
    transition: transform .2s, box-shadow .2s;
    position: relative; overflow: hidden;
    cursor: pointer; text-decoration: none; color: inherit;
  }
  .stat-card::before {
    content: ''; position: absolute; top: 0; left: 0;
    width: 4px; height: 100%; border-radius: 12px 0 0 12px;
  }
  .stat-card.confirmed::before { background: var(--confirmed); }
  .stat-card.released::before  { background: var(--released); }
  .stat-card.returned::before  { background: var(--returned); }
  .stat-card.cancelled::before { background: var(--cancelled); }
  .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.11); }

  .stat-icon {
    width: 52px; height: 52px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; flex-shrink: 0;
  }
  .stat-card.confirmed .stat-icon { background: #f5eef8; color: var(--confirmed); }
  .stat-card.released  .stat-icon { background: #eafaf1; color: var(--released); }
  .stat-card.returned  .stat-icon { background: #ebf5fb; color: var(--returned); }
  .stat-card.cancelled .stat-icon { background: #fdedec; color: var(--cancelled); }

  .stat-info { flex: 1; }
  .stat-info .count { font-size: 1.8rem; font-weight: 700; line-height: 1; margin-bottom: 4px; }
  .stat-card.confirmed .count { color: var(--confirmed); }
  .stat-card.released  .count { color: var(--released); }
  .stat-card.returned  .count { color: var(--returned); }
  .stat-card.cancelled .count { color: var(--cancelled); }
  .stat-info .label {
    font-size: .78rem; color: var(--text-muted); font-weight: 500;
    text-transform: uppercase; letter-spacing: .5px;
  }

  /* ── SUMMARY ROW ── */
  .summary-row {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 18px; margin-bottom: 28px;
  }
  .summary-card {
    background: var(--white); border-radius: var(--radius);
    padding: 18px 20px; box-shadow: var(--shadow);
    display: flex; align-items: center; gap: 14px;
  }
  .summary-card .icon-wrap {
    width: 46px; height: 46px; border-radius: 10px;
    background: var(--amber-light); color: var(--amber);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
  }
  .summary-card .info .val { font-size: 1.5rem; font-weight: 700; color: var(--text); }
  .summary-card .info .desc { font-size: .78rem; color: var(--text-muted); }

  /* ── CONTENT ROW ── */
  .content-row { display: grid; grid-template-columns: 1.6fr 1fr; gap: 22px; }

  /* ── TABLE CARD ── */
  .card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
  .card-header {
    padding: 18px 22px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
  }
  .card-header h3 { font-size: .95rem; font-weight: 600; }
  .card-header a { font-size: .78rem; color: var(--amber); text-decoration: none; font-weight: 500; }
  .card-header a:hover { text-decoration: underline; }

  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: .82rem; }
  thead th {
    background: #fafbfc; padding: 11px 16px; text-align: left;
    color: var(--text-muted); font-weight: 600; font-size: .75rem;
    text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid var(--border);
  }
  tbody td {
    padding: 12px 16px; border-bottom: 1px solid #f0f3f6;
    color: var(--text); vertical-align: middle;
  }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover td { background: #fafbfc; }

  .badge {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .4px;
  }
  .badge-confirmed { background: #f5eef8; color: #6c3483; }
  .badge-released  { background: #eafaf1; color: #1e8449; }
  .badge-returned  { background: #ebf5fb; color: #1a5276; }
  .badge-cancelled { background: #fdedec; color: #c0392b; }

  .asset-name { font-weight: 500; color: var(--text); }
  .asset-serial { font-size: .73rem; color: var(--text-muted); }

  /* ── ACTIVITY LOG ── */
  .activity-list { padding: 10px 0; }
  .activity-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 13px 22px; border-bottom: 1px solid #f0f3f6; transition: background .15s;
  }
  .activity-item:last-child { border-bottom: none; }
  .activity-item:hover { background: #fafbfc; }
  .activity-dot {
    width: 34px; height: 34px; border-radius: 50%;
    background: var(--amber-light); color: var(--amber);
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; flex-shrink: 0; margin-top: 1px;
  }
  .activity-text { flex: 1; }
  .activity-text .action { font-size: .82rem; font-weight: 600; color: var(--text); }
  .activity-text .desc   { font-size: .76rem; color: var(--text-muted); margin-top: 2px; line-height: 1.4; }
  .activity-text .time   { font-size: .72rem; color: #bdc3c7; margin-top: 3px; }

  /* ── EMPTY STATE ── */
  .empty-state {
    text-align: center; padding: 40px 20px;
    color: var(--text-muted); font-size: .85rem;
  }
  .empty-state i { font-size: 2rem; color: #d5d8dc; display: block; margin-bottom: 10px; }

  /* ── RESPONSIVE ── */
  @media (max-width: 1100px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .content-row { grid-template-columns: 1fr; }
  }
  @media (max-width: 700px) {
    .main-content { padding: 18px 14px; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .summary-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<?php include 'warehouse_sidebar.php'; ?>

<div class="main-content" id="mainContent">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1><i class='bx bxs-dashboard' style="color:var(--amber); margin-right:6px;"></i>Dashboard</h1>
      <span>Welcome back, <?= htmlspecialchars($user_name) ?>! Here's your warehouse overview.</span>
    </div>
    <div class="date-badge">
      <i class='bx bx-calendar'></i>
      <?= date('l, F j, Y') ?>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="stats-grid">
    <a class="stat-card confirmed" href="warehouse-preparing.php">
      <div class="stat-icon"><i class='bx bx-loader-circle'></i></div>
      <div class="stat-info">
        <div class="count"><?= $stats['CONFIRMED'] ?></div>
        <div class="label">Preparing</div>
      </div>
    </a>
    <a class="stat-card released" href="warehouse-completed.php">
      <div class="stat-icon"><i class='bx bx-check-circle'></i></div>
      <div class="stat-info">
        <div class="count"><?= $stats['RELEASED'] ?></div>
        <div class="label">Released</div>
      </div>
    </a>
    <a class="stat-card returned" href="warehouse-completed.php">
      <div class="stat-icon"><i class='bx bx-undo'></i></div>
      <div class="stat-info">
        <div class="count"><?= $stats['RETURNED'] ?></div>
        <div class="label">Returned</div>
      </div>
    </a>
    <a class="stat-card cancelled" href="warehouse-completed.php">
      <div class="stat-icon"><i class='bx bx-x-circle'></i></div>
      <div class="stat-info">
        <div class="count"><?= $stats['CANCELLED'] ?></div>
        <div class="label">Cancelled</div>
      </div>
    </a>
  </div>

  <!-- Summary Row -->
  <div class="summary-row">
    <div class="summary-card">
      <div class="icon-wrap"><i class='bx bxs-package'></i></div>
      <div class="info">
        <div class="val"><?= $total ?></div>
        <div class="desc">Total Transactions Handled (All Time)</div>
      </div>
    </div>
    <div class="summary-card">
      <div class="icon-wrap"><i class='bx bxs-truck'></i></div>
      <div class="info">
        <div class="val"><?= $today_released ?></div>
        <div class="desc">Assets Released Today</div>
      </div>
    </div>
  </div>

  <!-- Table + Activity Log -->
  <div class="content-row">

    <!-- Recent Transactions -->
    <div class="card">
      <div class="card-header">
        <h3><i class='bx bx-transfer' style="color:var(--amber); margin-right:6px;"></i>Recent Transactions</h3>
        <a href="warehouse-completed.php">View all →</a>
      </div>
      <div class="table-wrap">
        <?php if ($recent->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Asset</th>
              <th>Requested By</th>
              <th>Date Needed</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $recent->fetch_assoc()): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td>
                <div class="asset-name">
                  <?= htmlspecialchars(trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? ''))) ?: '—' ?>
                </div>
                <?php if ($row['serial_number']): ?>
                  <div class="asset-serial">S/N: <?= htmlspecialchars($row['serial_number']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($row['requested_by'] ?? '—') ?></td>
              <td><?= $row['date_needed'] ? date('M j, Y', strtotime($row['date_needed'])) : '—' ?></td>
              <td>
                <?php
                  $s = strtolower($row['status']);
                  $labels = ['confirmed'=>'Preparing','released'=>'Released','returned'=>'Returned','cancelled'=>'Cancelled'];
                ?>
                <span class="badge badge-<?= $s ?>"><?= $labels[$s] ?? $row['status'] ?></span>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="empty-state">
            <i class='bx bx-inbox'></i>
            No transactions yet.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Activity Log -->
    <div class="card">
      <div class="card-header">
        <h3><i class='bx bx-history' style="color:var(--amber); margin-right:6px;"></i>Recent Activity</h3>
      </div>
      <div class="activity-list">
        <?php if ($logs->num_rows > 0): ?>
          <?php while ($log = $logs->fetch_assoc()): ?>
          <div class="activity-item">
            <div class="activity-dot"><i class='bx bx-user'></i></div>
            <div class="activity-text">
              <div class="action"><?= htmlspecialchars($log['action']) ?></div>
              <div class="desc"><?= htmlspecialchars($log['description']) ?></div>
              <div class="time">
                <i class='bx bx-time-five' style="font-size:.7rem;"></i>
                <?= date('M j, Y g:i A', strtotime($log['created_at'])) ?>
                · <?= htmlspecialchars($log['user_name'] ?? 'Unknown') ?>
              </div>
            </div>
          </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="empty-state">
            <i class='bx bx-history'></i>
            No activity yet.
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /content-row -->

</div><!-- /main-content -->

<script>
  const sidebar = document.querySelector('.sidebar');
  const observer = new MutationObserver(() => {
    document.body.classList.toggle('sidebar-collapsed', sidebar?.classList.contains('close'));
  });
  if (sidebar) {
    observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    if (sidebar.classList.contains('close')) document.body.classList.add('sidebar-collapsed');
  }
</script>

</body>
</html>