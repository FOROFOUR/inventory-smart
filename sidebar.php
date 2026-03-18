<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permissions_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: landing.php");
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, role, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$userName   = $user['name'];
$userRole   = $user['role'];
$profilePic = !empty($user['profile_pic'])
    ? 'uploads/' . $user['profile_pic']
    : 'https://i.pravatar.cc/150?img=12';

if (!isset($_SESSION['permissions'])) {
    loadPermissions($conn, $userId, $userRole);
}

$currentPage = basename($_SERVER['PHP_SELF']);
function isActive($page)
{
    global $currentPage;
    return ($currentPage === $page) ? 'active' : '';
}

// ── Badge counts ──────────────────────────────────────────────────────────────
$pendingCount  = 0;
$prepCount     = 0;
$releasedCount = 0;

if (hasPermission('asset_transfer')) {
    $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM pull_out_transactions WHERE status = 'PENDING'");
    if ($s && $s->execute()) $pendingCount = (int)($s->get_result()->fetch_assoc()['cnt'] ?? 0);

    $s2 = $conn->prepare("SELECT COUNT(*) AS cnt FROM pull_out_transactions WHERE status = 'CONFIRMED'");
    if ($s2 && $s2->execute()) $prepCount = (int)($s2->get_result()->fetch_assoc()['cnt'] ?? 0);

    $s3 = $conn->prepare("SELECT COUNT(*) AS cnt FROM pull_out_transactions WHERE status = 'RELEASED'");
    if ($s3 && $s3->execute()) $releasedCount = (int)($s3->get_result()->fetch_assoc()['cnt'] ?? 0);
}

// ── Recent notifications (last 10 activity logs) ──────────────────────────────
$notifRows = [];
$nq = $conn->query("SELECT user_name, action, description, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 10");
if ($nq) while ($nr = $nq->fetch_assoc()) $notifRows[] = $nr;

$totalBell = $pendingCount + $prepCount + $releasedCount;
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* ── BADGE ── */
    .nav-link a {
        position: relative !important;
        display: flex !important;
        align-items: center !important;
        overflow: visible !important;
    }

    .nav-link .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 999px;
        background: #e74c3c;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        margin-left: auto;
        flex-shrink: 0;
        z-index: 10;
        box-shadow: 0 2px 6px rgba(231, 76, 60, .5);
        animation: badgePop .3s cubic-bezier(.36, .07, .19, .97);
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
        font-family: 'Poppins', sans-serif;
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

    /* ── NOTIFICATION BELL ── */
    .notif-bell-wrap {
        position: fixed;
        top: 18px;
        right: 24px;
        z-index: 8888;
    }
.sidebar header .name {
    color: #2563eb !important;
    font-size: 18px !important;
    font-weight: 700 !important;
    letter-spacing: -.3px !important;
}
    .notif-bell-btn {
        background: #fff;
        border: none;
        cursor: pointer;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 12px rgba(0, 0, 0, .12);
        position: relative;
        transition: box-shadow .2s;
    }

    .notif-bell-btn:hover {
        box-shadow: 0 4px 18px rgba(0, 0, 0, .18);
    }

    .notif-bell-btn i {
        font-size: 1.35rem;
        color: #2c3e50;
    }

    .notif-bell-btn .bell-count {
        position: absolute;
        top: -3px;
        right: -3px;
        background: #e74c3c;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 5px rgba(231, 76, 60, .5);
        animation: badgePop .3s ease;
    }

    .notif-dropdown {
        display: none;
        position: absolute;
        top: 50px;
        right: 0;
        width: 340px;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, .15);
        overflow: hidden;
        animation: dropIn .2s ease;
    }

    .notif-dropdown.open {
        display: block;
    }

    @keyframes dropIn {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notif-header {
        padding: .9rem 1.2rem;
        border-bottom: 1px solid #f0f3f6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notif-header span {
        font-size: .9rem;
        font-weight: 700;
        color: #2c3e50;
        font-family: 'Poppins', sans-serif;
    }

    .notif-mark-read {
        font-size: .74rem;
        color: #f39c12;
        font-weight: 600;
        background: none;
        border: none;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
    }

    .notif-mark-read:hover {
        text-decoration: underline;
    }

    .notif-list {
        max-height: 340px;
        overflow-y: auto;
    }

    .notif-item {
        display: flex;
        gap: .75rem;
        align-items: flex-start;
        padding: .85rem 1.2rem;
        border-bottom: 1px solid #f8f9fa;
        transition: background .15s;
        cursor: default;
    }

    .notif-item:hover {
        background: #fafbfc;
    }

    .notif-item:last-child {
        border-bottom: none;
    }

    .notif-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .notif-icon.pending {
        background: #fef3cd;
        color: #f39c12;
    }

    .notif-icon.release {
        background: #d5f5e3;
        color: #27ae60;
    }

    .notif-icon.prep {
        background: #e8d5f5;
        color: #8e44ad;
    }

    .notif-icon.receive {
        background: #d6eaf8;
        color: #2980b9;
    }

    .notif-icon.default {
        background: #f0f3f6;
        color: #7f8c8d;
    }

    .notif-body {
        flex: 1;
        min-width: 0;
    }

    .notif-body .ntitle {
        font-size: .82rem;
        font-weight: 600;
        color: #2c3e50;
        font-family: 'Poppins', sans-serif;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .notif-body .ndesc {
        font-size: .76rem;
        color: #7f8c8d;
        margin-top: 2px;
        line-height: 1.4;
        font-family: 'Poppins', sans-serif;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .notif-body .ntime {
        font-size: .7rem;
        color: #bdc3c7;
        margin-top: 3px;
        font-family: 'Poppins', sans-serif;
    }

    .notif-empty {
        padding: 2.5rem;
        text-align: center;
        color: #bdc3c7;
    }

    .notif-empty i {
        font-size: 2.2rem;
        display: block;
        margin-bottom: .5rem;
    }

    .notif-empty span {
        font-size: .82rem;
        font-family: 'Poppins', sans-serif;
    }

    .notif-footer {
        padding: .75rem 1.2rem;
        border-top: 1px solid #f0f3f6;
        text-align: center;
    }

    .notif-footer a {
        font-size: .78rem;
        color: #f39c12;
        font-weight: 600;
        text-decoration: none;
        font-family: 'Poppins', sans-serif;
    }

    .notif-footer a:hover {
        text-decoration: underline;
    }

    /* summary pills inside dropdown */
    .notif-summary {
        display: flex;
        gap: .5rem;
        padding: .75rem 1.2rem;
        border-bottom: 1px solid #f0f3f6;
        flex-wrap: wrap;
    }

    .npill {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .25rem .65rem;
        border-radius: 20px;
        font-size: .74rem;
        font-weight: 600;
        font-family: 'Poppins', sans-serif;
    }

    .npill.pending {
        background: #fef3cd;
        color: #d68910;
    }

    .npill.prep {
        background: #e8d5f5;
        color: #7d3c98;
    }

    .npill.released {
        background: #d5f5e3;
        color: #1e8449;
    }
</style>

<!-- ── NOTIFICATION BELL ───────────────────────────────────────────────── -->
<div class="notif-bell-wrap">
    <button class="notif-bell-btn" id="notifBellBtn" onclick="toggleNotif(event)">
        <i class='bx bx-bell' id="bellIcon"></i>
        <?php if ($totalBell > 0): ?>
            <span class="bell-count" id="bellCount"><?= $totalBell > 99 ? '99+' : $totalBell ?></span>
        <?php else: ?>
            <span class="bell-count" id="bellCount" style="display:none;">0</span>
        <?php endif; ?>
    </button>

    <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-header">
            <span>🔔 Notifications</span>
            <button class="notif-mark-read" onclick="markAllRead()">Mark all read</button>
        </div>

        <?php if ($pendingCount > 0 || $prepCount > 0 || $releasedCount > 0): ?>
            <div class="notif-summary">
                <?php if ($pendingCount > 0): ?>
                    <span class="npill pending"><i class='bx bx-time-five'></i> <?= $pendingCount ?> Pending</span>
                <?php endif; ?>
                <?php if ($prepCount > 0): ?>
                    <span class="npill prep"><i class='bx bx-loader-circle'></i> <?= $prepCount ?> Preparing</span>
                <?php endif; ?>
                <?php if ($releasedCount > 0): ?>
                    <span class="npill released"><i class='bx bx-package'></i> <?= $releasedCount ?> For Receipt</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="notif-list" id="notifList">
            <?php if (empty($notifRows)): ?>
                <div class="notif-empty">
                    <i class='bx bx-bell-off'></i>
                    <span>No recent activity</span>
                </div>
            <?php else: ?>
                <?php foreach ($notifRows as $n):
                    $action = strtoupper($n['action'] ?? '');
                    $iconClass = 'default';
                    $iconName  = 'bx-bell';
                    if (str_contains($action, 'PENDING') || str_contains($action, 'REQUEST')) {
                        $iconClass = 'pending';
                        $iconName = 'bx-time-five';
                    } elseif (str_contains($action, 'RELEASE')) {
                        $iconClass = 'release';
                        $iconName = 'bx-paper-plane';
                    } elseif (str_contains($action, 'PREP') || str_contains($action, 'CONFIRM')) {
                        $iconClass = 'prep';
                        $iconName = 'bx-loader-circle';
                    } elseif (str_contains($action, 'RECEIV')) {
                        $iconClass = 'receive';
                        $iconName = 'bx-check-circle';
                    }
                    $timeAgo = '';
                    if ($n['created_at']) {
                        $diff = time() - strtotime($n['created_at']);
                        if ($diff < 60) $timeAgo = 'Just now';
                        elseif ($diff < 3600) $timeAgo = floor($diff / 60) . 'm ago';
                        elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . 'h ago';
                        else $timeAgo = date('M j', strtotime($n['created_at']));
                    }
                ?>
                    <div class="notif-item">
                        <div class="notif-icon <?= $iconClass ?>">
                            <i class='bx <?= $iconName ?>'></i>
                        </div>
                        <div class="notif-body">
                            <div class="ntitle"><?= htmlspecialchars($n['user_name'] ?? 'System') ?> · <?= htmlspecialchars($n['action'] ?? '') ?></div>
                            <div class="ndesc"><?= htmlspecialchars($n['description'] ?? '') ?></div>
                            <div class="ntime"><?= $timeAgo ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="notif-footer">
            <a href="reports.php">View full activity log →</a>
        </div>
    </div>
</div>

<!-- ── SIDEBAR ────────────────────────────────────────────────────────────── -->
<nav class="sidebar">
    <header>
        <div class="image-text">
            <span class="image">
                <i class="fas fa-feather-alt" style="font-size:26px;color:#2563eb;"></i>
            </span>
            <div class="text logo-text">
                <span class="name" style="color:#2563eb;font-weight:700;letter-spacing:-.3px;">IBIS</span>
                <span class="profession">Inventory System</span>
            </div>
        </div>
        <i class='bx bx-chevron-right toggle'></i>
    </header>

    <div class="menu-bar">
        <div class="menu">
            <div class="profile-snippet">
                <img src="<?= $profilePic ?>" alt="profile">
                <div class="profile-details">
                    <span class="user_name"><?= htmlspecialchars($userName) ?></span>
                    <span class="user_role"><?= htmlspecialchars($userRole) ?></span>
                </div>
            </div>

            <ul class="menu-links">

                <?php if (hasPermission('dashboard')): ?>
                    <li class="nav-link <?= isActive('dashboard.php') ?>">
                        <a href="dashboard.php">
                            <i class='bx bx-grid-alt icon'></i>
                            <span class="text nav-text">Dashboard</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('inventory_view')): ?>
                    <li class="nav-link <?= isActive('inventory.php') ?>">
                        <a href="inventory.php">
                            <i class='bx bx-package icon'></i>
                            <span class="text nav-text">Inventory</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('asset_transfer')): ?>
                    <li class="nav-link <?= isActive('pullout.php') ?>">
                        <a href="pullout.php" id="pulloutNavLink">
                            <i class='bx bx-transfer-alt icon'></i>
                            <span class="text nav-text">Asset Transfer</span>
                            <?php if ($pendingCount > 0): ?>
                                <span class="badge" id="sidebarBadge"><?= $pendingCount > 99 ? '99+' : $pendingCount ?></span>
                                <span class="badge-tooltip"><?= $pendingCount ?> pending transfer<?= $pendingCount > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li class="nav-link <?= isActive('admin-incoming.php') ?>">
                        <a href="admin-incoming.php" id="adminIncomingLink">
                            <i class='bx bx-box icon' style="font-size:1.05rem;padding-left:10px;"></i>
                            <span class="text nav-text" style="font-size:.88rem;opacity:.9;">↳ Orders</span>
                            <?php if ($pendingCount > 0): ?>
                                <span class="badge"><?= $pendingCount > 99 ? '99+' : $pendingCount ?></span>
                                <span class="badge-tooltip"><?= $pendingCount ?> pending</span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li class="nav-link <?= isActive('admin-preparing.php') ?>">
                        <a href="admin-preparing.php" id="adminPreparingLink">
                            <i class='bx bx-loader-circle icon' style="font-size:1.05rem;padding-left:10px;"></i>
                            <span class="text nav-text" style="font-size:.88rem;opacity:.9;">↳ Preparing</span>
                            <?php if ($prepCount > 0): ?>
                                <span class="badge" style="background:#8e44ad;box-shadow:0 2px 6px rgba(142,68,173,.5);"><?= $prepCount > 99 ? '99+' : $prepCount ?></span>
                                <span class="badge-tooltip"><?= $prepCount ?> being prepared</span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li class="nav-link <?= isActive('admin-receiving.php') ?>">
                        <a href="admin-receiving.php" id="adminReceivingLink">
                            <i class='bx bx-package icon' style="font-size:1.05rem;padding-left:10px;"></i>
                            <span class="text nav-text" style="font-size:.88rem;opacity:.9;">↳ Receiving</span>
                            <?php if ($releasedCount > 0): ?>
                                <span class="badge" style="background:#16a085;box-shadow:0 2px 6px rgba(22,160,133,.5);"><?= $releasedCount > 99 ? '99+' : $releasedCount ?></span>
                                <span class="badge-tooltip"><?= $releasedCount ?> awaiting receipt</span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('upload_assets')): ?>
                    <li class="nav-link <?= isActive('upload-assets.php') ?>">
                        <a href="upload-assets.php">
                            <i class='bx bx-upload icon'></i>
                            <span class="text nav-text">Upload Assets</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('qr_management')): ?>
                    <li class="nav-link <?= isActive('qr-management.php') ?>">
                        <a href="qr-management.php">
                            <i class='bx bx-qr-scan icon'></i>
                            <span class="text nav-text">QR Management</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('reports')): ?>
                    <li class="nav-link <?= isActive('reports.php') ?>">
                        <a href="reports.php">
                            <i class='bx bx-bar-chart-alt-2 icon'></i>
                            <span class="text nav-text">Reports</span>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-link <?= isActive('account-settings.php') ?>">
                    <a href="account-settings.php">
                        <i class='bx bx-cog icon'></i>
                        <span class="text nav-text">Account Settings</span>
                    </a>
                </li>

                <?php if (hasPermission('user_management')): ?>
                    <li class="nav-link <?= isActive('user-management.php') ?>">
                        <a href="user-management.php">
                            <i class='bx bx-group icon'></i>
                            <span class="text nav-text">User Management</span>
                        </a>
                    </li>
                <?php endif; ?>

            </ul>
        </div>

        <div class="bottom-content">
            <li class="">
                <a href="logout.php">
                    <i class='bx bx-log-out icon'></i>
                    <span class="text nav-text">Logout</span>
                </a>
            </li>
        </div>
    </div>
</nav>

<script>
    // ── SIDEBAR: Manual toggle only (no hover) ────────────────────────────────
    const sidebar = document.querySelector(".sidebar");
    const toggle = document.querySelector(".toggle");
    toggle.addEventListener("click", () => sidebar.classList.toggle("close"));

    // ── NOTIFICATION BELL ─────────────────────────────────────────────────────
    function toggleNotif(e) {
        e.stopPropagation();
        document.getElementById('notifDropdown').classList.toggle('open');
        // Animate bell
        const icon = document.getElementById('bellIcon');
        icon.style.transform = 'rotate(20deg)';
        setTimeout(() => icon.style.transform = '', 300);
    }
    document.addEventListener('click', e => {
        const wrap = document.getElementById('notifDropdown');
        if (wrap && !wrap.closest('.notif-bell-wrap').contains(e.target)) {
            wrap.classList.remove('open');
        }
    });

    function markAllRead() {
        const count = document.getElementById('bellCount');
        if (count) count.style.display = 'none';
    }

    // ── Real-time badge polling every 5s ──────────────────────────────────────
    <?php if (hasPermission('asset_transfer')): ?>
            (function pollBadges() {
                let lastPending = <?= $pendingCount ?>;
                let lastPrep = <?= $prepCount ?>;
                let lastReleased = <?= $releasedCount ?>;

                function renderBadge(linkEl, count, color, shadow, tooltipText) {
                    if (!linkEl) return;
                    linkEl.querySelectorAll('.badge, .badge-tooltip').forEach(el => el.remove());
                    if (count <= 0) return;
                    const badge = document.createElement('span');
                    badge.className = 'badge';
                    badge.textContent = count > 99 ? '99+' : count;
                    if (color) badge.style.background = color;
                    if (shadow) badge.style.boxShadow = shadow;
                    const tooltip = document.createElement('span');
                    tooltip.className = 'badge-tooltip';
                    tooltip.textContent = tooltipText;
                    linkEl.appendChild(badge);
                    linkEl.appendChild(tooltip);
                }

                function updateBellCount(pending, prep, released) {
                    const total = pending + prep + released;
                    const el = document.getElementById('bellCount');
                    if (!el) return;
                    el.textContent = total > 99 ? '99+' : total;
                    el.style.display = total > 0 ? 'flex' : 'none';

                    // Update summary pills
                    const summary = document.querySelector('.notif-summary');
                    if (summary) {
                        summary.innerHTML = '';
                        if (pending > 0) summary.innerHTML += `<span class="npill pending"><i class='bx bx-time-five'></i> ${pending} Pending</span>`;
                        if (prep > 0) summary.innerHTML += `<span class="npill prep"><i class='bx bx-loader-circle'></i> ${prep} Preparing</span>`;
                        if (released > 0) summary.innerHTML += `<span class="npill released"><i class='bx bx-package'></i> ${released} For Receipt</span>`;
                    }
                }

                async function poll() {
                    try {
                        const [r1, r2, r3] = await Promise.all([
                            fetch('pullout_api.php?action=get_pullouts&status=PENDING&search=').then(r => r.json()),
                            fetch('pullout_api.php?action=get_pullouts&status=CONFIRMED&search=').then(r => r.json()),
                            fetch('pullout_api.php?action=get_pullouts&status=RELEASED&search=').then(r => r.json()),
                        ]);
                        const pending = r1.success ? (r1.count ?? 0) : lastPending;
                        const prep = r2.success ? (r2.count ?? 0) : lastPrep;
                        const released = r3.success ? (r3.count ?? 0) : lastReleased;

                        if (pending !== lastPending) {
                            lastPending = pending;
                            renderBadge(document.getElementById('pulloutNavLink'), pending, null, null, `${pending} pending transfer${pending > 1 ? 's' : ''}`);
                            renderBadge(document.getElementById('adminIncomingLink'), pending, null, null, `${pending} pending`);
                        }
                        if (prep !== lastPrep) {
                            lastPrep = prep;
                            renderBadge(document.getElementById('adminPreparingLink'), prep, '#8e44ad', '0 2px 6px rgba(142,68,173,.5)', `${prep} being prepared`);
                        }
                        if (released !== lastReleased) {
                            lastReleased = released;
                            renderBadge(document.getElementById('adminReceivingLink'), released, '#16a085', '0 2px 6px rgba(22,160,133,.5)', `${released} awaiting receipt`);
                        }
                        updateBellCount(pending, prep, released);
                    } catch (e) {}
                }

                setInterval(poll, 5000);
            })();
    <?php endif; ?>
</script>