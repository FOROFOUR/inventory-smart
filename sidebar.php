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
$user = $result->fetch_assoc();

$userName = $user['name'];
$userRole = $user['role'];
$profilePic = !empty($user['profile_pic'])
    ? 'uploads/' . $user['profile_pic']
    : 'https://i.pravatar.cc/150?img=12';

$currentPage = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $currentPage;
    return ($currentPage === $page) ? 'active' : '';
}

// ─── NOTIFICATION COUNT ───────────────────────────────────────────────────────
// FIX: Tama na ang table at status value (UPPERCASE based sa pullout_api.php)
// Table: pull_out_transactions
// Status values: PENDING, RELEASED, RETURNED, CANCELLED
$pendingCount = 0;
$notifStmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM pull_out_transactions WHERE status = 'PENDING'"
);
if ($notifStmt && $notifStmt->execute()) {
    $notifRow     = $notifStmt->get_result()->fetch_assoc();
    $pendingCount = (int)($notifRow['cnt'] ?? 0);
}
// ─────────────────────────────────────────────────────────────────────────────
?>
<!-- NOW HTML CAN START -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

<style>
/* ── Notification badge ───────────────────────────────────────────── */

/* Make sure the <a> tag inside nav-link supports badge positioning */
.nav-link a {
    position: relative !important;
    display: flex !important;
    align-items: center !important;
    overflow: visible !important;   /* critical — hindi magttatago ng badge */
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
    animation: badgePop .3s cubic-bezier(.36,.07,.19,.97);
}

/* Badge — collapsed state: lumalabas sa ibabaw ng icon */
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
    60%  { transform: scale(1.3) rotate(3deg); opacity: 1; }
    100% { transform: scale(1) rotate(0deg); }
}

/* Tooltip — only visible on collapsed + hover */
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
    box-shadow: 0 4px 14px rgba(0,0,0,.3);
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
                <li class="nav-link <?php echo isActive('dashboard.php'); ?>">
                    <a href="dashboard.php">
                        <i class='bx bx-grid-alt icon'></i>
                        <span class="text nav-text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-link <?php echo isActive('inventory.php'); ?>">
                    <a href="inventory.php">
                        <i class='bx bx-package icon'></i>
                        <span class="text nav-text">Inventory</span>
                    </a>
                </li>

                <!-- ── Asset Transfer with PENDING notification badge ── -->
                <li class="nav-link <?php echo isActive('pullout.php'); ?>">
                    <a href="pullout.php">
                        <i class='bx bx-transfer-alt icon'></i>
                        <span class="text nav-text">Asset Transfer</span>
                        <?php if ($pendingCount > 0): ?>
                            <span class="badge"><?php echo $pendingCount > 99 ? '99+' : $pendingCount; ?></span>
                            <span class="badge-tooltip">
                                <?php echo $pendingCount; ?> pending transfer<?php echo $pendingCount > 1 ? 's' : ''; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-link <?php echo isActive('upload-assets.php'); ?>">
                    <a href="upload-assets.php">
                        <i class='bx bx-upload icon'></i>
                        <span class="text nav-text">Upload Assets</span>
                    </a>
                </li>

                <li class="nav-link <?php echo isActive('qr-management.php'); ?>">
                    <a href="qr-management.php">
                        <i class='bx bx-qr-scan icon'></i>
                        <span class="text nav-text">QR Management</span>
                    </a>
                </li>

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

toggle.addEventListener("click", () => {
    sidebar.classList.toggle("close");
});

sidebar.addEventListener("mouseenter", () => {
    clearTimeout(hoverTimeout);
    sidebar.classList.remove("close");
});

sidebar.addEventListener("mouseleave", () => {
    hoverTimeout = setTimeout(() => {
        sidebar.classList.add("close");
    }, 300);
});
</script>