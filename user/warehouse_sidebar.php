<?php
// PHP CODE FIRST - NO OUTPUT BEFORE THIS!
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../landing.php");
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, role, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$userName   = $user['name']    ?? 'Warehouse Staff';
$userRole   = $user['role']    ?? 'EMPLOYEE';
$profilePic = !empty($user['profile_pic'])
    ? '../uploads/' . $user['profile_pic']
    : 'https://i.pravatar.cc/150?img=12';

$currentPage = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $currentPage;
    return ($currentPage === $page) ? 'active' : '';
}

// ─── NOTIFICATION COUNT ───────────────────────────────────────────────────────
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
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

<style>
/* ── EXACT COPY of admin CSS + warehouse overrides ── */
* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

:root {
    --body-color: #ffffff;
    --sidebar-color: #FFF;
    --primary-color: #f39c12;        /* Warehouse amber instead of admin purple */
    --primary-light: #fef9ed;        /* Amber tint */
    --toggle-color: #DDD;
    --text-color: #707070;
    --tran-03: all 0.3s ease;
    --tran-04: all 0.3s ease;
    --tran-05: all 0.3s ease;
}

body {
    min-height: 100vh;
    background-color: #f4f6f9;
    transition: var(--tran-05);
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    width: 250px;
    padding: 10px 14px;
    background: var(--sidebar-color);
    transition: var(--tran-05);
    z-index: 100;
    box-shadow: 4px 0 10px rgba(0,0,0,0.05);
}

.sidebar.close { width: 88px; }

.sidebar header { position: relative; }

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

.sidebar.close .text { opacity: 0; }

.sidebar .toggle {
    position: absolute;
    top: 50%;
    right: -25px;
    transform: translateY(-50%) rotate(180deg);
    height: 25px;
    width: 25px;
    background-color: var(--primary-color);
    color: var(--sidebar-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    cursor: pointer;
    transition: var(--tran-05);
}

.sidebar.close .toggle { transform: translateY(-50%) rotate(0deg); }

.sidebar .menu { margin-top: 20px; }

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

.profile-details { display: flex; flex-direction: column; }
.user_name { font-size: 14px; font-weight: 600; color: #333; }
.user_role { font-size: 11px; color: var(--text-color); }

.sidebar.close .profile-snippet { background: none; padding: 0; justify-content: center; }
.sidebar.close .profile-details { display: none; }
.sidebar.close .profile-snippet img { margin-right: 0; }

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
    background-color: transparent;
    display: flex;
    align-items: center;
    width: 100%;
    border-radius: 6px;
    text-decoration: none;
    transition: var(--tran-03);
    position: relative;
    overflow: visible;
}

.sidebar li a:hover { background-color: var(--primary-color); }
.sidebar li a:hover .icon,
.sidebar li a:hover .text { color: var(--sidebar-color); }

.sidebar li.active a { background-color: var(--primary-color); }
.sidebar li.active .icon,
.sidebar li.active .text { color: var(--sidebar-color); }

.sidebar .menu-bar {
    height: calc(100% - 55px);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow-y: scroll;
}
.menu-bar::-webkit-scrollbar { display: none; }

.sidebar.close .text { opacity: 0; pointer-events: none; }

/* Content area */
.home {
    position: relative;
    left: 250px;
    height: 100vh;
    width: calc(100% - 250px);
    background-color: #f4f6f9;
    transition: var(--tran-05);
    padding: 20px;
}
.sidebar.close ~ .home {
    left: 88px;
    width: calc(100% - 88px);
}

/* ── Notification Badge ── */
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
    box-shadow: 0 2px 6px rgba(231,76,60,0.5);
    animation: badgePop .3s cubic-bezier(.36,.07,.19,.97);
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
    0%   { transform: scale(0) rotate(-10deg); opacity: 0; }
    60%  { transform: scale(1.3) rotate(3deg);  opacity: 1; }
    100% { transform: scale(1) rotate(0deg); }
}

/* Badge tooltip on collapsed hover */
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
    box-shadow: 0 4px 14px rgba(0,0,0,.3);
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

/* Main content margin sync */
.main-content {
    position: relative;
    left: 250px;
    width: calc(100% - 250px);
    min-height: 100vh;
    background-color: #f4f6f9;
    transition: var(--tran-05);
    padding: 28px 32px;
}
.sidebar.close ~ .main-content {
    left: 88px;
    width: calc(100% - 88px);
}
</style>

<nav class="sidebar close">
    <header>
        <div class="image-text">
            <span class="image">
                <i class='bx bxs-warehouse'></i>
            </span>
            <div class="text logo-text">
                <span class="name">Warehouse</span>
                <span class="profession">Portal</span>
            </div>
        </div>
        <i class='bx bx-chevron-right toggle'></i>
    </header>

    <div class="menu-bar">
        <div class="menu">
            <div class="profile-snippet">
                <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="profile">
                <div class="profile-details">
                    <span class="user_name"><?php echo htmlspecialchars($userName); ?></span>
                    <span class="user_role"><?php echo htmlspecialchars($userRole); ?></span>
                </div>
            </div>

            <ul class="menu-links">
                <li class="nav-link <?php echo isActive('warehouse_dashboard.php'); ?>">
                    <a href="warehouse_dashboard.php">
                        <i class='bx bx-grid-alt icon'></i>
                        <span class="text nav-text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-link <?php echo isActive('warehouse-incoming.php'); ?>">
                    <a href="warehouse-incoming.php">
                        <i class='bx bx-box icon'></i>
                        <span class="text nav-text">Incoming Orders</span>
                        <?php if ($pendingCount > 0): ?>
                            <span class="badge"><?php echo $pendingCount > 99 ? '99+' : $pendingCount; ?></span>
                            <span class="badge-tooltip">
                                <?php echo $pendingCount; ?> pending request<?php echo $pendingCount > 1 ? 's' : ''; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-link <?php echo isActive('warehouse-preparing.php'); ?>">
                    <a href="warehouse-preparing.php">
                        <i class='bx bx-loader-circle icon'></i>
                        <span class="text nav-text">Preparing</span>
                    </a>
                </li>

                <li class="nav-link <?php echo isActive('warehouse-completed.php'); ?>">
                    <a href="warehouse-completed.php">
                        <i class='bx bx-check-circle icon'></i>
                        <span class="text nav-text">Completed</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="bottom-content">
            <li class="">
                <a href="../logout.php">
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