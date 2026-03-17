<?php
ob_start();
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'sidebar.php';

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: landing.php");
    exit();
}

$userId = $_SESSION['user_id'];

// ── Helper: always re-fetch user from DB ──────────────────────────────────────
function fetchUser($conn, $userId) {
    $stmt = $conn->prepare("SELECT name, email, role, profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$user = fetchUser($conn, $userId);

$profilePic = !empty($user['profile_pic'])
    ? 'uploads/' . $user['profile_pic']
    : 'https://i.pravatar.cc/150?img=12';

$successMsg = '';
$errorMsg   = '';

// ── Handle Profile Picture Upload ─────────────────────────────────────────────
if (isset($_POST['update_picture'])) {
    if (!empty($_FILES['profile_pic']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext     = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $errorMsg = 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.';
        } elseif ($_FILES['profile_pic']['size'] > 5 * 1024 * 1024) {
            $errorMsg = 'File too large. Maximum 5MB allowed.';
        } else {
            $uploadDir   = __DIR__ . '/uploads/';
            $newFilename = 'profile_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $uploadDir . $newFilename)) {
                if (!empty($user['profile_pic'])) {
                    @unlink($uploadDir . $user['profile_pic']);
                }
                $upStmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                $upStmt->bind_param("si", $newFilename, $userId);
                $upStmt->execute();
                $successMsg = 'Profile picture updated successfully!';
            } else {
                $errorMsg = 'Failed to upload file. Check directory permissions.';
            }
        }
    } else {
        $errorMsg = 'No file selected.';
    }
    // Re-fetch after update
    $user = fetchUser($conn, $userId);
    $profilePic = !empty($user['profile_pic']) ? 'uploads/' . $user['profile_pic'] : 'https://i.pravatar.cc/150?img=12';
}

// ── Handle Account Info Update ────────────────────────────────────────────────
if (isset($_POST['update_info'])) {
    $newName  = trim($_POST['name']  ?? '');
    $newEmail = trim($_POST['email'] ?? '');

    if (empty($newName) || empty($newEmail)) {
        $errorMsg = 'Name and email are required.';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Invalid email address.';
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->bind_param("si", $newEmail, $userId);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $errorMsg = 'Email is already in use by another account.';
        } else {
            $upStmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $upStmt->bind_param("ssi", $newName, $newEmail, $userId);
            $upStmt->execute();
            $_SESSION['user_name'] = $newName;
            $successMsg = 'Account information updated successfully!';
        }
    }
    // Re-fetch after update so form values are current
    $user = fetchUser($conn, $userId);
}

// ── Handle Password Change ────────────────────────────────────────────────────
if (isset($_POST['change_password'])) {
    $currentPw = $_POST['current_password'] ?? '';
    $newPw     = $_POST['new_password']     ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    $pwStmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $pwStmt->bind_param("i", $userId);
    $pwStmt->execute();
    $pwRow = $pwStmt->get_result()->fetch_assoc();

    if (!password_verify($currentPw, $pwRow['password'])) {
        $errorMsg = 'Current password is incorrect.';
    } elseif (strlen($newPw) < 8) {
        $errorMsg = 'New password must be at least 8 characters.';
    } elseif ($newPw !== $confirmPw) {
        $errorMsg = 'New passwords do not match.';
    } else {
        $hash   = password_hash($newPw, PASSWORD_DEFAULT);
        $upStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upStmt->bind_param("si", $hash, $userId);
        $upStmt->execute();
        $successMsg = 'Password changed successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ═══════════════════════════════════════════════════
           BASE & LAYOUT
        ═══════════════════════════════════════════════════ */
        * { box-sizing: border-box; }

        /* ── Sidebar offset ── */
        .content {
            padding: 0;
            margin-left: 88px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            background: #f4f6fb;
        }

        .sidebar:not(.close) ~ .content {
            margin-left: 260px;
        }

        body {
            background: #f4f6fb;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        .main-content {
            padding: 32px 36px;
            min-height: 100vh;
            background: #f4f6fb;
            font-family: 'Poppins', sans-serif;
        }

        /* ═══════════════════════════════════════════════════
           PAGE HEADER
        ═══════════════════════════════════════════════════ */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
            padding-bottom: 22px;
            border-bottom: 1.5px solid #e8ecf3;
        }
        .page-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .page-header-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #4f8ef7, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            color: #fff;
            box-shadow: 0 4px 14px rgba(79,142,247,.35);
            flex-shrink: 0;
        }
        .page-header h1 {
            font-size: 1.45rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 3px;
            letter-spacing: -.3px;
        }
        .page-header p {
            font-size: .82rem;
            color: #94a3b8;
            margin: 0;
            font-weight: 400;
        }
        .breadcrumb {
            font-size: .78rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .breadcrumb span { color: #0f172a; font-weight: 500; }
        .breadcrumb i    { font-size: .7rem; }

        /* ═══════════════════════════════════════════════════
           ALERTS
        ═══════════════════════════════════════════════════ */
        .alert {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: .875rem;
            font-weight: 500;
            margin-bottom: 26px;
            animation: alertIn .35s cubic-bezier(.21,1.02,.73,1) both;
        }
        .alert i { font-size: 1.2rem; flex-shrink: 0; }
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            box-shadow: 0 2px 10px rgba(22,163,74,.1);
        }
        .alert-error {
            background: #fff1f2;
            color: #9f1239;
            border: 1px solid #fecdd3;
            box-shadow: 0 2px 10px rgba(244,63,94,.1);
        }
        @keyframes alertIn {
            from { opacity: 0; transform: translateY(-8px) scale(.98); }
            to   { opacity: 1; transform: translateY(0)    scale(1);   }
        }

        /* ═══════════════════════════════════════════════════
           GRID
        ═══════════════════════════════════════════════════ */
        .settings-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 26px;
            align-items: start;
        }

        /* ═══════════════════════════════════════════════════
           CARDS
        ═══════════════════════════════════════════════════ */
        .settings-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid #e8ecf3;
            box-shadow: 0 1px 4px rgba(15,23,42,.04), 0 4px 24px rgba(15,23,42,.06);
            overflow: hidden;
            transition: box-shadow .2s;
        }
        .settings-card:hover {
            box-shadow: 0 2px 8px rgba(15,23,42,.06), 0 8px 32px rgba(15,23,42,.09);
        }
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 14px;
            background: #fafbfe;
        }
        .header-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            color: #fff;
            flex-shrink: 0;
        }
        .card-header h3 {
            font-size: .975rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 2px;
            letter-spacing: -.1px;
        }
        .card-header p {
            font-size: .775rem;
            color: #94a3b8;
            margin: 0;
            font-weight: 400;
        }
        .card-body { padding: 26px 24px; }

        /* ═══════════════════════════════════════════════════
           AVATAR / PROFILE PIC SECTION
        ═══════════════════════════════════════════════════ */
        .avatar-wrapper {
            text-align: center;
            padding-bottom: 22px;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 22px;
        }
        .avatar-ring {
            position: relative;
            display: inline-block;
            margin-bottom: 14px;
        }
        .avatar-ring img {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 0 0 3px #e8ecf3, 0 8px 28px rgba(15,23,42,.14);
            display: block;
        }
        .avatar-edit-btn {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #4f8ef7, #2563eb);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            cursor: pointer;
            border: 2.5px solid #fff;
            font-size: .95rem;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 3px 10px rgba(37,99,235,.4);
        }
        .avatar-edit-btn:hover {
            transform: scale(1.12);
            box-shadow: 0 5px 16px rgba(37,99,235,.5);
        }
        .avatar-name {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 5px;
        }
        .avatar-role {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
            background: #eff6ff;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: .6px;
            border: 1px solid #bfdbfe;
        }

        /* ═══════════════════════════════════════════════════
           DROP ZONE
        ═══════════════════════════════════════════════════ */
        .drop-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 22px 16px;
            text-align: center;
            cursor: pointer;
            transition: all .25s;
            background: #f8fafc;
            display: block;
        }
        .drop-zone:hover,
        .drop-zone.drag-over {
            border-color: #4f8ef7;
            background: #eff6ff;
        }
        .drop-zone .dz-icon {
            font-size: 2rem;
            color: #cbd5e1;
            display: block;
            margin-bottom: 8px;
            transition: color .25s;
        }
        .drop-zone:hover .dz-icon { color: #4f8ef7; }
        .drop-zone .dz-title {
            font-size: .84rem;
            font-weight: 600;
            color: #475569;
            margin: 0 0 3px;
        }
        .drop-zone .dz-sub {
            font-size: .73rem;
            color: #94a3b8;
        }
        #picPreview {
            display: none;
            max-width: 100%;
            max-height: 140px;
            border-radius: 10px;
            margin-top: 12px;
            object-fit: cover;
            border: 2px solid #e8ecf3;
            box-shadow: 0 2px 12px rgba(15,23,42,.08);
        }

        /* ═══════════════════════════════════════════════════
           RIGHT COLUMN
        ═══════════════════════════════════════════════════ */
        .right-col {
            display: flex;
            flex-direction: column;
            gap: 26px;
        }

        /* ═══════════════════════════════════════════════════
           FORM ELEMENTS
        ═══════════════════════════════════════════════════ */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group:last-child { margin-bottom: 0; }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .75rem;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: .55px;
        }
        .form-group label i {
            font-size: .9rem;
            color: #94a3b8;
        }

        .input-wrap { position: relative; }
        .input-wrap .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.05rem;
            pointer-events: none;
            transition: color .2s;
            /* Prevent icon from being selectable / overflowing */
            z-index: 1;
        }
        .input-wrap:focus-within .input-icon { color: #4f8ef7; }

        .input-wrap input {
            width: 100%;
            padding: 11px 42px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: .88rem;
            font-family: 'Poppins', sans-serif;
            color: #0f172a;
            background: #f8fafc;
            transition: border-color .2s, box-shadow .2s, background .2s;
            outline: none;
            /* Prevent PHP error text spilling out */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .input-wrap input:focus {
            border-color: #4f8ef7;
            box-shadow: 0 0 0 3.5px rgba(79,142,247,.14);
            background: #fff;
        }
        .input-wrap input:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
            border-color: #e2e8f0;
        }
        .input-wrap input::placeholder { color: #cbd5e1; }

        .toggle-pw {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            font-size: 1.05rem;
            transition: color .2s;
            z-index: 2;
        }
        .toggle-pw:hover { color: #4f8ef7; }

        /* ═══════════════════════════════════════════════════
           PASSWORD STRENGTH
        ═══════════════════════════════════════════════════ */
        .pw-strength-bar {
            height: 5px;
            border-radius: 99px;
            margin-top: 10px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .pw-strength-fill {
            height: 100%;
            border-radius: 99px;
            width: 0;
            transition: width .35s ease, background .35s ease;
        }
        .pw-strength-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 6px;
        }
        .pw-strength-label {
            font-size: .72rem;
            font-weight: 600;
            color: #94a3b8;
            transition: color .3s;
        }
        .pw-strength-hint { font-size: .7rem; color: #cbd5e1; }

        /* ═══════════════════════════════════════════════════
           SECTION DIVIDER
        ═══════════════════════════════════════════════════ */
        .section-divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e2e8f0, transparent);
            margin: 8px 0 22px;
        }

        /* ═══════════════════════════════════════════════════
           BUTTONS
        ═══════════════════════════════════════════════════ */
        .btn-save {
            width: 100%;
            padding: 12px 20px;
            border: none;
            border-radius: 11px;
            font-size: .88rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform .18s, box-shadow .18s, filter .18s;
            letter-spacing: .1px;
        }
        .btn-save:active { transform: scale(.98); }

        .btn-primary {
            background: linear-gradient(135deg, #4f8ef7 0%, #2563eb 100%);
            color: #fff;
            box-shadow: 0 4px 14px rgba(37,99,235,.28);
            margin-top: 4px;
        }
        .btn-primary:hover {
            filter: brightness(1.07);
            box-shadow: 0 6px 22px rgba(37,99,235,.38);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #f87171 0%, #dc2626 100%);
            color: #fff;
            box-shadow: 0 4px 14px rgba(220,38,38,.25);
            margin-top: 4px;
        }
        .btn-danger:hover {
            filter: brightness(1.07);
            box-shadow: 0 6px 22px rgba(220,38,38,.38);
            transform: translateY(-1px);
        }

        /* ═══════════════════════════════════════════════════
           ICON BACKGROUNDS
        ═══════════════════════════════════════════════════ */
        .ic-blue  { background: linear-gradient(135deg, #4f8ef7, #2563eb); box-shadow: 0 3px 10px rgba(37,99,235,.3); }
        .ic-green { background: linear-gradient(135deg, #34d399, #059669); box-shadow: 0 3px 10px rgba(5,150,105,.3); }
        .ic-red   { background: linear-gradient(135deg, #f87171, #dc2626); box-shadow: 0 3px 10px rgba(220,38,38,.25); }

        /* ═══════════════════════════════════════════════════
           RESPONSIVE
        ═══════════════════════════════════════════════════ */
        @media (max-width: 1024px) {
            .settings-grid { grid-template-columns: 290px 1fr; }
        }
        @media (max-width: 860px) {
            .settings-grid { grid-template-columns: 1fr; }
            .main-content  { padding: 22px 20px; }
            .form-row      { grid-template-columns: 1fr; gap: 0; }
            .page-header   { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
    </style>
</head>
<body>

<div class="content">
<div class="main-content">

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon"><i class='bx bx-cog'></i></div>
            <div>
                <h1>Account Settings</h1>
                <p>Manage your profile, security, and personal information</p>
            </div>
        </div>
        <div class="breadcrumb">
            <i class='bx bx-home-alt'></i> Home
            <i class='bx bx-chevron-right'></i>
            <span>Account Settings</span>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><i class='bx bx-check-circle'></i><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><i class='bx bx-error-circle'></i><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>

    <div class="settings-grid">

        <!-- ── LEFT: Profile Picture ── -->
        <div class="settings-card">
            <div class="card-header">
                <div class="header-icon ic-blue"><i class='bx bx-user-circle'></i></div>
                <div>
                    <h3>Profile Picture</h3>
                    <p>Update your avatar</p>
                </div>
            </div>
            <div class="card-body">
                <div class="avatar-wrapper">
                    <div class="avatar-ring">
                        <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile" id="currentAvatar">
                        <label class="avatar-edit-btn" for="picInput" title="Change photo">
                            <i class='bx bx-camera'></i>
                        </label>
                    </div>
                    <div class="avatar-name"><?php echo htmlspecialchars($user['name'] ?? ''); ?></div>
                    <span class="avatar-role"><?php echo htmlspecialchars($user['role'] ?? ''); ?></span>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="file" id="picInput" name="profile_pic" accept="image/*" style="display:none;">
                    <label for="picInput" class="drop-zone" id="dropZone">
                        <i class='bx bx-cloud-upload dz-icon'></i>
                        <p class="dz-title">Click or drag &amp; drop a photo</p>
                        <span class="dz-sub">JPG, PNG, WEBP &middot; Max 5MB</span>
                        <img id="picPreview" src="" alt="Preview">
                    </label>
                    <button type="submit" name="update_picture" class="btn-save btn-primary" style="margin-top:16px;">
                        <i class='bx bx-upload'></i> Save Picture
                    </button>
                </form>
            </div>
        </div>

        <!-- ── RIGHT COLUMN ── -->
        <div class="right-col">

            <!-- Account Info -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="header-icon ic-green"><i class='bx bx-id-card'></i></div>
                    <div>
                        <h3>Account Information</h3>
                        <p>Update your name and email address</p>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class='bx bx-user'></i> Full Name</label>
                                <div class="input-wrap">
                                    <i class='bx bx-user input-icon'></i>
                                    <input type="text" name="name"
                                           value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                                           placeholder="Enter your name" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><i class='bx bx-envelope'></i> Email Address</label>
                                <div class="input-wrap">
                                    <i class='bx bx-envelope input-icon'></i>
                                    <input type="email" name="email"
                                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                           placeholder="Enter your email" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class='bx bx-badge-check'></i> Role</label>
                            <div class="input-wrap">
                                <i class='bx bx-badge-check input-icon'></i>
                                <input type="text" value="<?php echo htmlspecialchars($user['role'] ?? ''); ?>" disabled>
                            </div>
                        </div>
                        <div class="section-divider"></div>
                        <button type="submit" name="update_info" class="btn-save btn-primary">
                            <i class='bx bx-save'></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="header-icon ic-red"><i class='bx bx-lock-alt'></i></div>
                    <div>
                        <h3>Change Password</h3>
                        <p>Keep your account secure</p>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label><i class='bx bx-lock'></i> Current Password</label>
                            <div class="input-wrap">
                                <i class='bx bx-lock input-icon'></i>
                                <input type="password" name="current_password" id="currentPw"
                                       placeholder="Enter current password" required>
                                <i class='bx bx-hide toggle-pw' data-target="currentPw"></i>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class='bx bx-lock-open-alt'></i> New Password</label>
                                <div class="input-wrap">
                                    <i class='bx bx-lock-open-alt input-icon'></i>
                                    <input type="password" name="new_password" id="newPw"
                                           placeholder="Min. 8 characters" required>
                                    <i class='bx bx-hide toggle-pw' data-target="newPw"></i>
                                </div>
                                <div class="pw-strength-bar">
                                    <div class="pw-strength-fill" id="strengthFill"></div>
                                </div>
                                <div class="pw-strength-row">
                                    <div class="pw-strength-label" id="strengthLabel">Enter a password</div>
                                    <div class="pw-strength-hint">A–Z · 0–9 · symbols</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><i class='bx bx-lock-open-alt'></i> Confirm New Password</label>
                                <div class="input-wrap">
                                    <i class='bx bx-lock-open-alt input-icon'></i>
                                    <input type="password" name="confirm_password" id="confirmPw"
                                           placeholder="Repeat new password" required>
                                    <i class='bx bx-hide toggle-pw' data-target="confirmPw"></i>
                                </div>
                            </div>
                        </div>
                        <div class="section-divider"></div>
                        <button type="submit" name="change_password" class="btn-save btn-danger">
                            <i class='bx bx-shield-alt-2'></i> Change Password
                        </button>
                    </form>
                </div>
            </div>

        </div><!-- end right-col -->
    </div><!-- end settings-grid -->
</div><!-- end main-content -->
</div><!-- end .content -->

<script>
// ── Image preview ─────────────────────────────────────────────────────────────
const picInput   = document.getElementById('picInput');
const dropZone   = document.getElementById('dropZone');
const picPreview = document.getElementById('picPreview');

picInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            picPreview.src = e.target.result;
            picPreview.style.display = 'block';
        };
        reader.readAsDataURL(this.files[0]);
    }
});

['dragover', 'dragleave', 'drop'].forEach(ev => {
    dropZone.addEventListener(ev, e => {
        e.preventDefault();
        if (ev === 'dragover') dropZone.classList.add('drag-over');
        else dropZone.classList.remove('drag-over');
        if (ev === 'drop') {
            picInput.files = e.dataTransfer.files;
            picInput.dispatchEvent(new Event('change'));
        }
    });
});

// ── Toggle password visibility ────────────────────────────────────────────────
document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', function() {
        const input = document.getElementById(this.dataset.target);
        if (input.type === 'password') {
            input.type = 'text';
            this.classList.replace('bx-hide', 'bx-show');
        } else {
            input.type = 'password';
            this.classList.replace('bx-show', 'bx-hide');
        }
    });
});

// ── Password strength ─────────────────────────────────────────────────────────
const newPwInput    = document.getElementById('newPw');
const strengthFill  = document.getElementById('strengthFill');
const strengthLabel = document.getElementById('strengthLabel');

newPwInput.addEventListener('input', function() {
    const val = this.value;
    let score = 0;
    if (val.length >= 8)           score++;
    if (/[A-Z]/.test(val))         score++;
    if (/[0-9]/.test(val))         score++;
    if (/[^A-Za-z0-9]/.test(val))  score++;

    const levels = [
        { pct: '0%',   color: '#e0e5eb', text: 'Enter a password' },
        { pct: '25%',  color: '#e74c3c', text: 'Weak' },
        { pct: '50%',  color: '#f39c12', text: 'Fair' },
        { pct: '75%',  color: '#3498db', text: 'Good' },
        { pct: '100%', color: '#2ecc71', text: 'Strong' },
    ];
    const lv = val.length === 0 ? levels[0] : (levels[score] || levels[4]);
    strengthFill.style.width      = lv.pct;
    strengthFill.style.background = lv.color;
    strengthLabel.textContent     = lv.text;
    strengthLabel.style.color     = lv.color;
});

// ── Auto-hide alerts after 4s ─────────────────────────────────────────────────
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity .5s';
        el.style.opacity    = '0';
        setTimeout(() => el.remove(), 500);
    }, 4000);
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>