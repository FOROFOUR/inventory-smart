<?php
// PHP CODE FIRST - NO OUTPUT BEFORE THIS!
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: landing.php");
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, role, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();

$userName   = $user['name'];
$userRole   = $user['role'];
$profilePic = !empty($user['profile_pic'])
    ? 'uploads/' . $user['profile_pic']
    : 'https://i.pravatar.cc/150?img=12';

$currentPage = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $currentPage;
    return ($currentPage === $page) ? 'active' : '';
}

// ── Badge counts ──────────────────────────────────────────────────────────────

// PENDING — for Incoming Orders
$pendingCount = 0;
$notifStmt    = $conn->prepare("SELECT COUNT(*) AS cnt FROM pull_out_transactions WHERE status = 'PENDING'");
if ($notifStmt && $notifStmt->execute()) {
    $pendingCount = (int) ($notifStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
}

// CONFIRMED — for Preparing
$prepCount = 0;
$prepStmt  = $conn->prepare("SELECT COUNT(*) AS cnt FROM pull_out_transactions WHERE status = 'CONFIRMED'");
if ($prepStmt && $prepStmt->execute()) {
    $prepCount = (int) ($prepStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
}

// RELEASED — for Receiving
$releasedCount = 0;
$relStmt       = $conn->prepare("SELECT COUNT(*) AS cnt FROM pull_out_transactions WHERE status = 'RELEASED'");
if ($relStmt && $relStmt->execute()) {
    $releasedCount = (int) ($relStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
}
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="css/style.css">

<style>
/* ── Notification badge ──────────────────────────────────────────────────── */
.nav-link a {
    position: relative !important;
    display: flex !important;
    align-items: center !important;
    overflow: visible !important;
}

/* Badge — expanded state */
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
    box-shadow: 0 2px 6px rgba(231, 76, 60, 0.5);
    animation: badgePop .3s cubic-bezier(.36, .07, .19, .97);
}

/* Badge — collapsed state */
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
    0%   { transform: scale(0) rotate(-10deg); opacity: 0; }
    60%  { transform: scale(1.3) rotate(3deg);  opacity: 1; }
    100% { transform: scale(1)   rotate(0deg); }
}

/* Tooltip — collapsed + hover only */
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
    right: 100%; top: 50%;
    transform: translateY(-50%);
    border: 5px solid transparent;
    border-right-color: #1a1a2e;
}
.sidebar.close .nav-link:hover .badge-tooltip { display: block; }
</style>

<nav class="sidebar close">
    <header>
        <div class="image-text">
            <span class="image">
                <i class='bx bxs-box'></i>
            </span>
            <div class="text logo-text">
                <span class="name">Inventory</span>
                <span class="profession">System</span>
            </div>
        </div>
        <i class='bx bx-chevron-right toggle'></i>
    </header>

    <div class="menu-bar">
        <div class="menu">
            <div class="profile-snippet">
                <img src="<?php echo $profilePic; ?>" alt="profile">
                <div class="profile-details">
                    <span class="user_name"><?php echo $userName; ?></span>
                    <span class="user_role"><?php echo $userRole; ?></span>
                </div>
            </div>

            <ul class="menu-links">

                <!-- Dashboard -->
                <li class="nav-link <?php echo isActive('dashboard.php'); ?>">
                    <a href="dashboard.php">
                        <i class='bx bx-grid-alt icon'></i>
                        <span class="text nav-text">Dashboard</span>
                    </a>
                </li>

                <!-- Inventory -->
                <li class="nav-link <?php echo isActive('inventory.php'); ?>">
                    <a href="inventory.php">
                        <i class='bx bx-package icon'></i>
                        <span class="text nav-text">Inventory</span>
                    </a>
                </li>

                <!-- Asset Transfer (parent) -->
                <li class="nav-link <?php echo isActive('pullout.php'); ?>">
                    <a href="pullout.php" id="pulloutNavLink">
                        <i class='bx bx-transfer-alt icon'></i>
                        <span class="text nav-text">Asset Transfer</span>
                        <?php if ($pendingCount > 0): ?>
                            <span class="badge" id="sidebarBadge">
                                <?php echo $pendingCount > 99 ? '99+' : $pendingCount; ?>
                            </span>
                            <span class="badge-tooltip" id="sidebarTooltip">
                                <?php echo $pendingCount; ?> pending transfer<?php echo $pendingCount > 1 ? 's' : ''; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- ↳ Incoming Orders -->
                <li class="nav-link <?php echo isActive('admin-incoming.php'); ?>">
                    <a href="admin-incoming.php" id="adminIncomingLink">
                        <i class='bx bx-box icon' style="font-size:1.05rem; padding-left:10px;"></i>
                        <span class="text nav-text" style="font-size:.88rem; opacity:.9;">↳ Orders</span>
                        <?php if ($pendingCount > 0): ?>
                            <span class="badge">
                                <?php echo $pendingCount > 99 ? '99+' : $pendingCount; ?>
                            </span>
                            <span class="badge-tooltip">
                                <?php echo $pendingCount; ?> pending
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- ↳ Preparing -->
                <li class="nav-link <?php echo isActive('admin-preparing.php'); ?>">
                    <a href="admin-preparing.php" id="adminPreparingLink">
                        <i class='bx bx-loader-circle icon' style="font-size:1.05rem; padding-left:10px;"></i>
                        <span class="text nav-text" style="font-size:.88rem; opacity:.9;">↳ Preparing</span>
                        <?php if ($prepCount > 0): ?>
                            <span class="badge" style="background:#8e44ad; box-shadow:0 2px 6px rgba(142,68,173,.5);">
                                <?php echo $prepCount > 99 ? '99+' : $prepCount; ?>
                            </span>
                            <span class="badge-tooltip">
                                <?php echo $prepCount; ?> being prepared
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- ↳ Receiving -->
                <li class="nav-link <?php echo isActive('admin-receiving.php'); ?>">
                    <a href="admin-receiving.php" id="adminReceivingLink">
                        <i class='bx bx-package icon' style="font-size:1.05rem; padding-left:10px;"></i>
                        <span class="text nav-text" style="font-size:.88rem; opacity:.9;">↳ Receiving</span>
                        <?php if ($releasedCount > 0): ?>
                            <span class="badge" style="background:#16a085; box-shadow:0 2px 6px rgba(22,160,133,.5);">
                                <?php echo $releasedCount > 99 ? '99+' : $releasedCount; ?>
                            </span>
                            <span class="badge-tooltip">
                                <?php echo $releasedCount; ?> awaiting receipt
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- ↳ Completed
                <li class="nav-link <?php echo isActive('admin-completed.php'); ?>">
                    <a href="admin-completed.php">
                        <i class='bx bx-check-circle icon' style="font-size:1.05rem; padding-left:10px;"></i>
                        <span class="text nav-text" style="font-size:.88rem; opacity:.9;">↳ Completed</span>
                    </a>
                </li> -->

                <!-- Upload Assets -->
                <li class="nav-link <?php echo isActive('upload-assets.php'); ?>">
                    <a href="upload-assets.php">
                        <i class='bx bx-upload icon'></i>
                        <span class="text nav-text">Upload Assets</span>
                    </a>
                </li>

                <!-- QR Management -->
                <li class="nav-link <?php echo isActive('qr-management.php'); ?>">
                    <a href="qr-management.php">
                        <i class='bx bx-qr-scan icon'></i>
                        <span class="text nav-text">QR Management</span>
                    </a>
                </li>

                <!-- Reports -->
                <li class="nav-link <?php echo isActive('reports.php'); ?>">
                    <a href="reports.php">
                        <i class='bx bx-bar-chart-alt-2 icon'></i>
                        <span class="text nav-text">Reports</span>
                    </a>
                </li>

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
const sidebar = document.querySelector(".sidebar");
const toggle  = document.querySelector(".toggle");
let hoverTimeout;

toggle.addEventListener("click", () => sidebar.classList.toggle("close"));

sidebar.addEventListener("mouseenter", () => {
    clearTimeout(hoverTimeout);
    sidebar.classList.remove("close");
});
sidebar.addEventListener("mouseleave", () => {
    hoverTimeout = setTimeout(() => sidebar.classList.add("close"), 300);
});

// ── Real-time badge polling — every 15 seconds ────────────────────────────────
(function pollBadges() {
    let lastPending  = <?php echo $pendingCount; ?>;
    let lastPrep     = <?php echo $prepCount; ?>;  // ← dagdag
    let lastReleased = <?php echo $releasedCount; ?>;

    function renderBadge(linkEl, count, color, shadow, tooltipText) {
        if (!linkEl) return;
        linkEl.querySelectorAll('.badge, .badge-tooltip').forEach(el => el.remove());
        if (count <= 0) return;

        const label = count > 99 ? '99+' : count;

        const badge = document.createElement('span');
        badge.className   = 'badge';
        badge.textContent = label;
        if (color)  badge.style.background = color;
        if (shadow) badge.style.boxShadow  = shadow;

        const tooltip = document.createElement('span');
        tooltip.className   = 'badge-tooltip';
        tooltip.textContent = tooltipText;

        linkEl.appendChild(badge);
        linkEl.appendChild(tooltip);
    }

    async function updateBadges() {
        try {
            const res    = await fetch('pullout_api.php?action=get_pullouts&status=PENDING&search=');
            const result = await res.json();
            if (!result.success) return;

            const pending = result.count ?? 0;

            if (pending !== lastPending) {
                lastPending = pending;
                const pulloutLink  = document.getElementById('pulloutNavLink');
                const incomingLink = document.getElementById('adminIncomingLink');
                renderBadge(pulloutLink,  pending, null, null, `${pending} pending transfer${pending > 1 ? 's' : ''}`);
                renderBadge(incomingLink, pending, null, null, `${pending} pending`);
            }
        } catch(e) { /* silent fail */ }
    }

    async function updateReleasedBadge() {
        try {
            const res    = await fetch('pullout_api.php?action=get_pullouts&status=RELEASED&search=');
            const result = await res.json();
            if (!result.success) return;

            const released = result.count ?? 0;

            if (released !== lastReleased) {
                lastReleased = released;
                const receivingLink = document.getElementById('adminReceivingLink');
                renderBadge(
                    receivingLink, released,
                    '#16a085', '0 2px 6px rgba(22,160,133,.5)',
                    `${released} awaiting receipt`
                );
            }
        } catch(e) { /* silent fail */ }
    }

    async function updatePrepBadge() {
    try {
        const res    = await fetch('pullout_api.php?action=get_pullouts&status=CONFIRMED&search=');
        const result = await res.json();
        if (!result.success) return;

        const prep = result.count ?? 0;

        if (prep !== lastPrep) {
            lastPrep = prep;
            const preparingLink = document.getElementById('adminPreparingLink');
            renderBadge(
                preparingLink, prep,
                '#8e44ad', '0 2px 6px rgba(142,68,173,.5)',
                `${prep} being prepared`
            );
        }
    } catch(e) { /* silent fail */ }
}

    setInterval(() => { updateBadges(); updatePrepBadge(); updateReleasedBadge(); }, 3000);
})();
</script>