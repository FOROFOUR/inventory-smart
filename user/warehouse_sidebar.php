<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../landing.php");
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, role, profile_pic, warehouse_location FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$userName          = $user['name']               ?? 'Warehouse Staff';
$userRole          = $user['role']               ?? 'EMPLOYEE';
$warehouseLocation = $user['warehouse_location']  ?? '';
$profilePic        = !empty($user['profile_pic'])
    ? '../uploads/' . $user['profile_pic']
    : 'https://i.pravatar.cc/150?img=12';

$currentPage = basename($_SERVER['PHP_SELF']);
function isActive($page)
{
    global $currentPage;
    return ($currentPage === $page) ? 'active' : '';
}

$locLike = $warehouseLocation . '%';

// Preparing badge — CONFIRMED items FROM this warehouse
$confirmedCount = 0;
$confStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM pull_out_transactions WHERE status='CONFIRMED' AND from_location LIKE ?");
if ($confStmt && $confStmt->bind_param("s", $locLike) && $confStmt->execute())
    $confirmedCount = (int)($confStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

// Receiving badge — RELEASED items TO this warehouse
$releasedCount = 0;
$relStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM pull_out_transactions WHERE status='RELEASED' AND to_location LIKE ?");
if ($relStmt && $relStmt->bind_param("s", $locLike) && $relStmt->execute())
    $releasedCount = (int)($relStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

// Bell total
$bellTotal = $confirmedCount + $releasedCount;

// Bell preview items (latest 8)
$bellItems = [];
$bellStmt = $conn->prepare("
    SELECT pt.id, pt.status, pt.quantity, pt.from_location, pt.to_location,
           pt.created_at, a.brand, a.model
    FROM pull_out_transactions pt
    LEFT JOIN assets a ON a.id = pt.asset_id
    WHERE (pt.status='CONFIRMED' AND pt.from_location LIKE ?)
       OR (pt.status='RELEASED'  AND pt.to_location   LIKE ?)
    ORDER BY FIELD(pt.status,'RELEASED','CONFIRMED'), pt.created_at DESC
    LIMIT 8
");
if ($bellStmt && $bellStmt->bind_param("ss", $locLike, $locLike) && $bellStmt->execute())
    $bellItems = $bellStmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Poppins', sans-serif;
    }

    :root {
        --sidebar-color: #FFF;
        --primary-color: #f39c12;
        --primary-light: #fef9ed;
        --text-color: #707070;
        --tran-03: all 0.3s ease;
        --tran-05: all 0.3s ease;
    }

    body {
        min-height: 100vh;
        background: #f4f6f9;
        transition: var(--tran-05);
    }

    /* ── Sidebar ── */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        width: 250px;
        padding: 10px 14px;
        background: var(--sidebar-color);
        transition: var(--tran-05);
        z-index: 200;
        box-shadow: 4px 0 10px rgba(0, 0, 0, .05);
    }

    .sidebar.close {
        width: 88px;
    }

    .sidebar header {
        position: relative;
    }

    .sidebar .image-text {
        display: flex;
        align-items: center;
    }

    .sidebar header .image {
        min-width: 60px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sidebar header .image i {
        font-size: 32px;
        color: var(--primary-color);
    }

    .sidebar header .text {
        display: flex;
        flex-direction: column;
    }

    .name {
        margin-top: 2px;
        font-size: 18px;
        font-weight: 600;
        color: #333;
    }

    .profession {
        font-size: 13px;
        margin-top: -2px;
        color: var(--text-color);
    }

    .sidebar.close .text {
        opacity: 0;
    }

    .sidebar .toggle {
        position: absolute;
        top: 50%;
        right: -25px;
        transform: translateY(-50%) rotate(180deg);
        height: 25px;
        width: 25px;
        background: var(--primary-color);
        color: var(--sidebar-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        cursor: pointer;
        transition: var(--tran-05);
    }

    .sidebar.close .toggle {
        transform: translateY(-50%) rotate(0deg);
    }

    .sidebar .menu {
        margin-top: 20px;
    }

    .profile-snippet {
        display: flex;
        align-items: center;
        padding: 10px;
        background: var(--primary-light);
        border-radius: 8px;
        margin-bottom: 20px;
        overflow: hidden;
        white-space: nowrap;
    }

    .profile-snippet img {
        height: 40px;
        width: 40px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 12px;
    }

    .profile-details {
        display: flex;
        flex-direction: column;
    }

    .user_name {
        font-size: 14px;
        font-weight: 600;
        color: #333;
    }

    .user_role {
        font-size: 11px;
        color: var(--text-color);
    }

    .sidebar.close .profile-snippet {
        background: none;
        padding: 0;
        justify-content: center;
    }

    .sidebar.close .profile-details {
        display: none;
    }

    .sidebar.close .profile-snippet img {
        margin-right: 0;
    }

    .sidebar li {
        height: 50px;
        list-style: none;
        display: flex;
        align-items: center;
        margin-top: 10px;
    }

    .sidebar li .icon {
        min-width: 60px;
        border-radius: 6px;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .sidebar li .icon,
    .sidebar li .text {
        color: var(--text-color);
        transition: var(--tran-03);
    }

    .sidebar li a {
        list-style: none;
        height: 100%;
        background: transparent;
        display: flex;
        align-items: center;
        width: 100%;
        border-radius: 6px;
        text-decoration: none;
        transition: var(--tran-03);
        position: relative;
        overflow: visible;
    }

    .sidebar header .name {
        color: #2563eb !important;
        font-size: 18px !important;
        font-weight: 700 !important;
        letter-spacing: -.3px !important;
    }

    .sidebar li a:hover {
        background: var(--primary-color);
    }

    .sidebar li a:hover .icon,
    .sidebar li a:hover .text {
        color: var(--sidebar-color);
    }

    .sidebar li.active a {
        background: var(--primary-color);
    }

    .sidebar li.active .icon,
    .sidebar li.active .text {
        color: var(--sidebar-color);
    }

    .sidebar .menu-bar {
        height: calc(100% - 55px);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow-y: scroll;
    }

    .menu-bar::-webkit-scrollbar {
        display: none;
    }

    .sidebar.close .text {
        opacity: 0;
        pointer-events: none;
    }

    .main-content {
        position: relative;
        left: 250px;
        width: calc(100% - 250px);
        min-height: 100vh;
        background: #f4f6f9;
        transition: var(--tran-05);
        padding: 28px 32px;
    }

    .sidebar.close~.main-content {
        left: 88px;
        width: calc(100% - 88px);
    }

    /* ── Nav badges ── */
    .nav-link .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 999px;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        margin-left: auto;
        flex-shrink: 0;
        z-index: 10;
        animation: badgePop .3s cubic-bezier(.36, .07, .19, .97);
    }

    .nav-link .badge.badge-preparing {
        background: #e74c3c;
        box-shadow: 0 2px 6px rgba(231, 76, 60, .5);
    }

    .nav-link .badge.badge-teal {
        background: #16a085;
        box-shadow: 0 2px 6px rgba(22, 160, 133, .5);
    }

    .sidebar.close .nav-link .badge {
        position: absolute !important;
        top: 2px;
        right: 2px;
        margin-left: 0;
        min-width: 17px;
        height: 17px;
        font-size: 9px;
        padding: 0 4px;
    }

    @keyframes badgePop {
        0% {
            transform: scale(0) rotate(-10deg);
            opacity: 0;
        }

        60% {
            transform: scale(1.3) rotate(3deg);
            opacity: 1;
        }

        100% {
            transform: scale(1) rotate(0deg);
        }
    }

    .nav-link .badge-tooltip {
        display: none;
        position: absolute;
        left: calc(100% + 14px);
        top: 50%;
        transform: translateY(-50%);
        background: #1a1a2e;
        color: #fff;
        font-size: 11px;
        white-space: nowrap;
        padding: 5px 12px;
        border-radius: 6px;
        pointer-events: none;
        z-index: 9999;
        box-shadow: 0 4px 14px rgba(0, 0, 0, .3);
    }

    .nav-link .badge-tooltip::before {
        content: '';
        position: absolute;
        right: 100%;
        top: 50%;
        transform: translateY(-50%);
        border: 5px solid transparent;
        border-right-color: #1a1a2e;
    }

    .sidebar.close .nav-link:hover .badge-tooltip {
        display: block;
    }

    /* ══ GLOBAL BELL — fixed top-right ══ */
    #globalBellWrapper {
        position: fixed;
        top: 16px;
        right: 24px;
        z-index: 9999;
    }

    .bell-btn {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #fff;
        border: 1.5px solid #e2e8f0;
        box-shadow: 0 2px 10px rgba(15, 23, 42, .1);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: box-shadow .2s, background .2s;
        position: relative;
    }

    .bell-btn:hover {
        background: #fef9ed;
        box-shadow: 0 4px 16px rgba(15, 23, 42, .15);
    }

    .bell-btn i {
        font-size: 1.25rem;
        color: #334155;
    }

    .bell-btn .bell-count {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: #fff;
        font-size: .6rem;
        font-weight: 700;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        border-radius: 99px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
        animation: badgePop .3s cubic-bezier(.36, .07, .19, .97);
    }

    .bell-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        width: 340px;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 12px 40px rgba(15, 23, 42, .18);
        border: 1px solid #e8ecf3;
        overflow: hidden;
    }

    .bell-dropdown.open {
        display: block;
        animation: dropIn .2s ease;
    }

    @keyframes dropIn {
        from {
            opacity: 0;
            transform: translateY(-6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .bell-dropdown-hdr {
        padding: 13px 16px;
        border-bottom: 1px solid #f1f5f9;
        background: #fafbfe;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .bell-dropdown-hdr h4 {
        font-size: .84rem;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0;
    }

    .bell-dropdown-hdr h4 i {
        color: #f59e0b;
    }

    .bell-hdr-count {
        font-size: .7rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 99px;
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    .bell-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .bell-list::-webkit-scrollbar {
        width: 4px;
    }

    .bell-list::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 99px;
    }

    .bell-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 11px 16px;
        border-bottom: 1px solid #f8fafc;
        cursor: pointer;
        transition: background .15s;
    }

    .bell-item:last-child {
        border-bottom: none;
    }

    .bell-item:hover {
        background: #fffbeb;
    }

    .b-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
        margin-top: 5px;
    }

    .b-dot.preparing {
        background: #3b82f6;
    }

    .b-dot.receiving {
        background: #059669;
        animation: bPulse 1.5s infinite;
    }

    @keyframes bPulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(5, 150, 105, .4); }
        50%       { box-shadow: 0 0 0 5px rgba(5, 150, 105, 0); }
    }

    .bell-item-body {
        flex: 1;
        min-width: 0;
    }

    .bell-item-title {
        font-size: .79rem;
        font-weight: 600;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .bell-item-sub {
        font-size: .71rem;
        color: #64748b;
        margin-top: 2px;
        line-height: 1.4;
    }

    .bell-item-sub strong {
        color: #334155;
    }

    .bell-item-time {
        font-size: .67rem;
        color: #94a3b8;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .s-pill {
        display: inline-block;
        padding: 1px 7px;
        border-radius: 99px;
        font-size: .63rem;
        font-weight: 700;
    }

    .s-pill.confirmed {
        background: #dbeafe;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    .s-pill.released {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    .bell-empty {
        padding: 28px;
        text-align: center;
        color: #94a3b8;
        font-size: .82rem;
    }

    .bell-empty i {
        font-size: 2.2rem;
        display: block;
        margin-bottom: 8px;
        opacity: .25;
    }

    .bell-footer {
        padding: 10px 16px;
        border-top: 1px solid #f1f5f9;
        background: #fafbfe;
        display: flex;
        justify-content: space-between;
        gap: 8px;
    }

    .bell-footer a {
        font-size: .75rem;
        font-weight: 600;
        color: #2563eb;
        text-decoration: none;
        padding: 5px 10px;
        border-radius: 6px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        transition: background .15s;
    }

    .bell-footer a:hover {
        background: #dbeafe;
    }

    .bell-footer a.teal {
        color: #0d9488;
        background: #f0fdfa;
        border-color: #99f6e4;
    }

    .bell-footer a.teal:hover {
        background: #ccfbf1;
    }
</style>

<!-- ══ GLOBAL BELL ══ -->
<div id="globalBellWrapper">
    <button class="bell-btn" id="bellBtn" onclick="toggleBellDropdown()" title="Notifications">
        <i class='bx bx-bell<?= $bellTotal > 0 ? " bx-tada" : "" ?>' id="bellIcon"></i>
        <?php if ($bellTotal > 0): ?>
            <span class="bell-count" id="bellCount"><?= $bellTotal > 99 ? '99+' : $bellTotal ?></span>
        <?php endif; ?>
    </button>

    <div class="bell-dropdown" id="bellDropdown">
        <div class="bell-dropdown-hdr">
            <h4><i class='bx bx-bell'></i> Notifications</h4>
            <span class="bell-hdr-count" id="bellHdrCount"><?= $bellTotal ?> item<?= $bellTotal != 1 ? 's' : '' ?></span>
        </div>

        <div class="bell-list" id="bellList">
            <?php if (empty($bellItems)): ?>
                <div class="bell-empty">
                    <i class='bx bx-check-double'></i>
                    Nothing to act on right now
                </div>
            <?php else: ?>
                <?php foreach ($bellItems as $n):
                    $isR  = $n['status'] === 'RELEASED';
                    $item = trim(($n['brand'] ?? '') . ' ' . ($n['model'] ?? '')) ?: 'Asset #' . $n['id'];
                ?>
                    <div class="bell-item"
                        onclick="window.location='<?= $isR ? 'warehouse-receiving.php' : 'warehouse-preparing.php' ?>'">
                        <div class="b-dot <?= $isR ? 'receiving' : 'preparing' ?>"></div>
                        <div class="bell-item-body">
                            <div class="bell-item-title">
                                <span style="overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($item) ?></span>
                                <span class="s-pill <?= $isR ? 'released' : 'confirmed' ?>" style="flex-shrink:0;">
                                    <?= $isR ? '🚚 Incoming' : '📦 To Prepare' ?>
                                </span>
                            </div>
                            <div class="bell-item-sub">
                                <?php if ($isR): ?>
                                    From <strong><?= htmlspecialchars($n['from_location'] ?? '?') ?></strong>
                                    → <strong><?= htmlspecialchars($n['to_location'] ?? '') ?></strong>
                                    · Qty: <?= (int)$n['quantity'] ?>
                                <?php else: ?>
                                    Qty <?= (int)$n['quantity'] ?> pcs
                                    → to <strong><?= htmlspecialchars($n['to_location'] ?? '?') ?></strong>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="bell-item-time" data-ts="<?= htmlspecialchars($n['created_at'] ?? '') ?>">—</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($bellTotal > 0): ?>
            <div class="bell-footer">
                <?php if ($confirmedCount > 0): ?>
                    <a href="warehouse-preparing.php">📦 Preparing (<?= $confirmedCount ?>)</a>
                <?php endif; ?>
                <?php if ($releasedCount > 0): ?>
                    <a href="warehouse-receiving.php" class="teal">🚚 Receiving (<?= $releasedCount ?>)</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<!-- ══ END GLOBAL BELL ══ -->

<nav class="sidebar">
    <header>
        <div class="image-text">
            <span class="image">
                <i class="fas fa-feather-alt" style="font-size:26px;color:#2563eb;"></i>
            </span>
            <div class="text logo-text">
                <span class="name" style="color:#2563eb;font-weight:700;letter-spacing:-.3px;">IBIS</span>
                <span class="profession"><?= htmlspecialchars($warehouseLocation ?: 'Warehouse Portal') ?></span>
            </div>
        </div>
        <i class='bx bx-chevron-right toggle'></i>
    </header>

    <div class="menu-bar">
        <div class="menu">
            <div class="profile-snippet">
                <img src="<?= htmlspecialchars($profilePic) ?>" alt="profile">
                <div class="profile-details">
                    <span class="user_name"><?= htmlspecialchars($userName) ?></span>
                    <span class="user_role"><?= htmlspecialchars($userRole) ?></span>
                </div>
            </div>

            <ul class="menu-links">
                <li class="nav-link <?= isActive('warehouse_dashboard.php') ?>">
                    <a href="warehouse_dashboard.php">
                        <i class='bx bx-grid-alt icon'></i>
                        <span class="text nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-link <?= isActive('warehouse-preparing.php') ?>">
                    <a href="warehouse-preparing.php" id="preparingNavLink">
                        <i class='bx bx-loader-circle icon'></i>
                        <span class="text nav-text">Preparing</span>
                        <?php if ($confirmedCount > 0): ?>
                            <span class="badge badge-preparing"><?= $confirmedCount > 99 ? '99+' : $confirmedCount ?></span>
                            <span class="badge-tooltip"><?= $confirmedCount ?> item<?= $confirmedCount > 1 ? 's' : '' ?> to prepare</span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="nav-link <?= isActive('warehouse-release.php') ?>">
    <a href="warehouse-release.php">
        <i class='bx bx-paper-plane icon'></i>
        <span class="text nav-text">Release</span>
    </a>
</li>
                <li class="nav-link <?= isActive('warehouse-receiving.php') ?>">
                    <a href="warehouse-receiving.php" id="receivingNavLink">
                        <i class='bx bx-package icon'></i>
                        <span class="text nav-text">Receiving</span>
                        <?php if ($releasedCount > 0): ?>
                            <span class="badge badge-teal"><?= $releasedCount > 99 ? '99+' : $releasedCount ?></span>
                            <span class="badge-tooltip"><?= $releasedCount ?> item<?= $releasedCount > 1 ? 's' : '' ?> incoming</span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-link <?= isActive('warehouse-completed.php') ?>">
                    <a href="warehouse-completed.php">
                        <i class='bx bx-check-circle icon'></i>
                        <span class="text nav-text">Completed</span>
                    </a>
                </li>
                <li class="nav-link <?= isActive('warehouse-reports.php') ?>">
                    <a href="warehouse-reports.php">
                        <i class='bx bx-bar-chart-alt-2 icon'></i>
                        <span class="text nav-text">Reports</span>
                    </a>
                </li>
                <li class="nav-link <?= isActive('warehouse-account-settings.php') ?>">
                    <a href="warehouse-account-settings.php">
                        <i class='bx bx-cog icon'></i>
                        <span class="text nav-text">Account Settings</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="bottom-content">
            <li>
                <a href="../logout.php">
                    <i class='bx bx-log-out icon'></i>
                    <span class="text nav-text">Logout</span>
                </a>
            </li>
        </div>
    </div>
</nav>

<script>
    // ── Inject favicon ────────────────────────────────────────────────────────
    (function() {
        const favicon = document.createElement('link');
        favicon.rel   = 'icon';
        favicon.type  = 'image/x-icon';
        favicon.href  = '../favicon.ico';
        document.head.appendChild(favicon);
    })();

    // ── Sidebar toggle ────────────────────────────────────────────────────────
    const sidebar = document.querySelector(".sidebar");
    const toggle  = document.querySelector(".toggle");
    toggle.addEventListener("click", () => sidebar.classList.toggle("close"));

    // ── Bell toggle ───────────────────────────────────────────────────────────
    function toggleBellDropdown() {
        document.getElementById('bellDropdown').classList.toggle('open');
    }
    document.addEventListener('click', e => {
        const w = document.getElementById('globalBellWrapper');
        if (w && !w.contains(e.target))
            document.getElementById('bellDropdown')?.classList.remove('open');
    });

    // ── Relative timestamps ───────────────────────────────────────────────────
    function fmtAgo(d) {
        if (!d) return '—';
        const diff = Math.floor((Date.now() - new Date(d)) / 60000);
        if (diff < 1)    return 'just now';
        if (diff < 60)   return diff + 'm ago';
        if (diff < 1440) return Math.floor(diff / 60) + 'h ago';
        return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
    }
    document.querySelectorAll('[data-ts]').forEach(el => {
        el.textContent = fmtAgo(el.dataset.ts);
    });

    // ── Poll every 20 seconds ─────────────────────────────────────────────────
    (function pollNotifications() {
        let lastTotal     = <?= $bellTotal ?>;
        let lastConfirmed = <?= $confirmedCount ?>;
        let lastReleased  = <?= $releasedCount ?>;

        function escHtml(s) {
            return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function updateNavBadge(id, count, cls, tip) {
            const link = document.getElementById(id);
            if (!link) return;
            link.querySelectorAll('.badge, .badge-tooltip').forEach(el => el.remove());
            if (count > 0) {
                const b = document.createElement('span');
                b.className   = 'badge ' + cls;
                b.textContent = count > 99 ? '99+' : count;
                const t = document.createElement('span');
                t.className   = 'badge-tooltip';
                t.textContent = tip(count);
                link.appendChild(b);
                link.appendChild(t);
            }
        }

        function rebuildBellList(items) {
            const list = document.getElementById('bellList');
            if (!list) return;
            if (!items.length) {
                list.innerHTML = `<div class="bell-empty"><i class='bx bx-check-double'></i>Nothing to act on right now</div>`;
                return;
            }
            list.innerHTML = items.map(n => {
                const isR  = n.status === 'RELEASED';
                const item = ((n.brand || '') + ' ' + (n.model || '')).trim() || 'Asset #' + n.id;
                return `<div class="bell-item" onclick="window.location='${isR ? 'warehouse-receiving.php' : 'warehouse-preparing.php'}'">
                    <div class="b-dot ${isR ? 'receiving' : 'preparing'}"></div>
                    <div class="bell-item-body">
                        <div class="bell-item-title">
                            <span style="overflow:hidden;text-overflow:ellipsis;">${escHtml(item)}</span>
                            <span class="s-pill ${isR ? 'released' : 'confirmed'}" style="flex-shrink:0;">${isR ? '🚚 Incoming' : '📦 To Prepare'}</span>
                        </div>
                        <div class="bell-item-sub">
                            ${isR
                                ? `From <strong>${escHtml(n.from_location||'?')}</strong> → <strong>${escHtml(n.to_location||'')}</strong> · Qty: ${n.quantity}`
                                : `Qty ${n.quantity} pcs → to <strong>${escHtml(n.to_location||'?')}</strong>`
                            }
                        </div>
                    </div>
                    <div class="bell-item-time">${fmtAgo(n.created_at)}</div>
                </div>`;
            }).join('');
        }

        async function poll() {
            try {
                const res = await fetch('warehouse_dashboard_api.php?action=get_notifications');
                const d   = await res.json();
                if (!d.success) return;

                const total     = d.total     || 0;
                const preparing = d.preparing || 0;
                const receiving = d.receiving || 0;

                if (preparing !== lastConfirmed) {
                    lastConfirmed = preparing;
                    updateNavBadge('preparingNavLink', preparing, 'badge-preparing',
                        c => `${c} item${c > 1 ? 's' : ''} to prepare`);
                }
                if (receiving !== lastReleased) {
                    lastReleased = receiving;
                    updateNavBadge('receivingNavLink', receiving, 'badge-teal',
                        c => `${c} item${c > 1 ? 's' : ''} incoming`);
                }

                if (total !== lastTotal) {
                    lastTotal = total;
                    let countEl = document.getElementById('bellCount');
                    if (total > 0) {
                        if (!countEl) {
                            countEl = document.createElement('span');
                            countEl.className = 'bell-count';
                            countEl.id = 'bellCount';
                            document.getElementById('bellBtn').appendChild(countEl);
                        }
                        countEl.textContent = total > 99 ? '99+' : total;
                    } else {
                        countEl?.remove();
                    }
                    const icon = document.getElementById('bellIcon');
                    if (icon) icon.className = total > 0 ? 'bx bx-bell bx-tada' : 'bx bx-bell';
                    const hdr = document.getElementById('bellHdrCount');
                    if (hdr) hdr.textContent = total + ' item' + (total !== 1 ? 's' : '');
                    rebuildBellList(d.data || []);
                }
            } catch (e) { /* silent */ }
        }

        setInterval(poll, 20000);
    })();
</script>