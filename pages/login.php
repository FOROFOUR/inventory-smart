<?php
session_start();
require_once '../config.php';

$conn = getDBConnection();
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $login = trim($_POST['login']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("
        SELECT * FROM users 
        WHERE username = ? 
        UNION 
        SELECT * FROM users 
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $login, $login);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();
        $now = date("Y-m-d H:i:s");

        if ($user['lock_until'] && $user['lock_until'] > $now) {
            $error = "Account locked until " . $user['lock_until'];
        }

        elseif ($user['status'] !== 'ACTIVE') {
            $error = "Account inactive.";
        }

        elseif (password_verify($password, $user['password'])) {

            $reset = $conn->prepare("
                UPDATE users 
                SET failed_attempts = 0, lock_until = NULL 
                WHERE id = ?
            ");
            $reset->bind_param("i", $user['id']);
            $reset->execute();

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            if ($user['role'] === 'ADMIN') {
                header("Location: ../dashboard.php");

            } else {
                header("Location: ../user/warehouse_dashboard.php");
            }
            exit();

        } else {

            $failedAttempts = $user['failed_attempts'] + 1;

            if ($failedAttempts >= 5) {

                $lockUntil = date("Y-m-d H:i:s", strtotime("+15 minutes"));

                $update = $conn->prepare("
                    UPDATE users 
                    SET failed_attempts = ?, lock_until = ? 
                    WHERE id = ?
                ");
                $update->bind_param("isi", $failedAttempts, $lockUntil, $user['id']);
                $update->execute();

                $error = "Too many attempts. Locked for 15 minutes.";
            } else {

                $update = $conn->prepare("
                    UPDATE users 
                    SET failed_attempts = ?, last_failed_attempt = NOW()
                    WHERE id = ?
                ");
                $update->bind_param("ii", $failedAttempts, $user['id']);
                $update->execute();

                $error = "Invalid credentials. Attempt $failedAttempts of 5.";
            }
        }

    } else {
        $error = "User not found.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Intern</title>
    <link rel="stylesheet" href="../css/auth.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-left">
            <div class="auth-left-content">
                <a href="../landing.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
                <div class="brand-section">
                    <div class="logo">
                        <i class="fas fa-cube"></i>
                        <span>Smart<strong>Intern</strong></span>
                    </div>
                    <h1>Welcome Back</h1>
                    <p>Sign in to access your inventory management dashboard</p>
                </div>
                <div class="features-list">
                    
                    
                  
                </div>
            </div>
        </div>
        
        <div class="auth-right">
            <div class="auth-form-wrapper">
                <div class="auth-header">
                    <h2>Sign In</h2>
                    <p>Enter your credentials to access your account</p>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="auth-form" id="loginForm">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input              
    type="text"
    name="login"
    placeholder="Enter username or email"
    required
                          autofocus
                            >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Enter your password"
                                required
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" name="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">
                        <span>Sign In</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    
                    <div class="divider">
                        <span>or continue with</span>
                    </div>
                    
                    <div class="social-login">
                        <button type="button" class="btn-social">
                            <i class="fab fa-google"></i>
                            Google
                        </button>
                        <button type="button" class="btn-social">
                            <i class="fab fa-microsoft"></i>
                            Microsoft
                        </button>
                    </div>
                    
                    <p class="signup-link">
                        Don't have an account? <a href="signup.php">Sign up</a>
                    </p>
                </form>
            
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                showNotification('Please fill in all fields', 'error');
            }
        });
        
        function showNotification(message, type = 'info') {
            const existing = document.querySelector('.notification');
            if (existing) existing.remove();
            
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>