<?php
session_start();
require_once '../config.php';

$conn  = getDBConnection();
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    // Generic message for ALL failures — never hint which field is wrong
    $genericError = "Invalid username or password.";

    if (!empty($login) && !empty($password)) {

        $stmt = $conn->prepare("
            SELECT * FROM users
            WHERE (username = ? OR email = ?)
            LIMIT 1
        ");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $now  = date("Y-m-d H:i:s");

            if ($user['lock_until'] && $user['lock_until'] > $now) {
                // Locked — generic, no timing info
                $error = "Your account has been temporarily locked. Please try again later.";

            } elseif ($user['status'] !== 'ACTIVE') {
                // Inactive — same generic message
                $error = $genericError;

            } elseif (password_verify($password, $user['password'])) {
                // ✅ Success — reset counters
                $reset = $conn->prepare("UPDATE users SET failed_attempts = 0, lock_until = NULL WHERE id = ?");
                $reset->bind_param("i", $user['id']);
                $reset->execute();

                session_regenerate_id(true);
                $_SESSION['user_id']           = $user['id'];
                $_SESSION['role']              = $user['role'];
                $_SESSION['name']              = $user['name'];
                $_SESSION['profile_pic']       = $user['profile_pic'] ?? null;
                $_SESSION['warehouse_location'] = $user['warehouse_location'] ?? null;

                // Role-based redirect
                if ($user['role'] === 'ADMIN') {
                    header("Location: ../dashboard.php");
                } elseif ($user['role'] === 'WAREHOUSE') {
                    header("Location: ../user/warehouse_dashboard.php");
                } else {
                    // EMPLOYEE and any future roles
                    header("Location: ../dashboard.php");
                }
                exit();

            } else {
                // Wrong password — increment silently, never expose count
                $failedAttempts = $user['failed_attempts'] + 1;

                if ($failedAttempts >= 5) {
                    $lockUntil = date("Y-m-d H:i:s", strtotime("+15 minutes"));
                    $update    = $conn->prepare("UPDATE users SET failed_attempts = ?, lock_until = ? WHERE id = ?");
                    $update->bind_param("isi", $failedAttempts, $lockUntil, $user['id']);
                    $update->execute();
                    $error = "Your account has been temporarily locked. Please try again later.";
                } else {
                    $update = $conn->prepare("UPDATE users SET failed_attempts = ?, last_failed_attempt = NOW() WHERE id = ?");
                    $update->bind_param("ii", $failedAttempts, $user['id']);
                    $update->execute();
                    $error = $genericError;
                }
            }

        } else {
            // User not found — same generic, no enumeration hint
            $error = $genericError;
        }

    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — IBIS</title>
  <link rel="stylesheet" href="../css/auth.css?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    
</head>
<body>
    <div class="auth-container">

        <!-- ── Left panel ──────────────────────────────────── -->
        <div class="auth-left">
            <div class="auth-left-content">
                <a href="../landing.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
                <div class="brand-section">
                    <div class="logo">
                        <i class="fas fa-boxes-stacked"></i>
                        <span><strong>IBIS</strong></span>
                    </div>
                    <h1>Welcome Back</h1>
                    <p>Sign in to access the Imbak-Bantay Inventory System dashboard</p>
                </div>
                <div class="features-list">
                    <div class="feature-item">
                        <i class="fas fa-boxes"></i>
                        Real-time Inventory Tracking
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-exchange-alt"></i>
                        Asset Transfer Management
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-bar"></i>
                        Reports &amp; Analytics
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Right panel ─────────────────────────────────── -->
        <div class="auth-right">
            <div class="auth-form-wrapper">
                <div class="auth-header">
                    <h2>Sign In</h2>
                    <p>Enter your credentials to continue</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" class="auth-form" id="loginForm">

                    <div class="form-group">
                        <label>Username or Email</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input
                                type="text"
                                name="login"
                                placeholder="Enter username or email"
                                value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                required
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword()" tabindex="-1">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" name="remember">
                            <span>Remember me</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full" id="submitBtn">
                        <span>Sign In</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>

                </form>

                <div class="auth-footer">
                    <p class="demo-info">
                        <i class="fas fa-shield-alt"></i>
                        Secured by IBIS &mdash; Imbak-Bantay Inventory System
                    </p>
                </div>

            </div>
        </div>

    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon  = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Disable submit button to prevent double-click spam
        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>&nbsp; Signing in…';
        });
    </script>
</body>
</html>