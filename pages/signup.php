<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Smart Intern</title>
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
                    <h1>Join Smart Intern</h1>
                    <p>Create your account and start managing your inventory efficiently</p>
                </div>
               
            </div>
        </div>
        
        <div class="auth-right">
            <div class="auth-form-wrapper">
                <div class="auth-header">
                    <h2>Create Account</h2>
                    <p>Sign up to get started with Smart Intern</p>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>Sign up functionality coming soon with email verification.</span>
                </div>
                
                <form class="auth-form" id="signupForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input 
                                    type="text" 
                                    id="firstName" 
                                    name="firstName" 
                                    placeholder="John"
                                    required
                                >
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input 
                                    type="text" 
                                    id="lastName" 
                                    name="lastName" 
                                    placeholder="Doe"
                                    required
                                >
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                placeholder="john.doe@company.com"
                                required
                            >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-wrapper">
                            <i class="fas fa-at"></i>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                placeholder="Choose a username"
                                required
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
                                placeholder="Create a strong password"
                                required
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('password', 'toggleIcon1')">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        <small class="form-hint">At least 8 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="confirmPassword" 
                                name="confirmPassword" 
                                placeholder="Confirm your password"
                                required
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword', 'toggleIcon2')">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" name="terms" required>
                            <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">
                        <span>Create Account</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    
                    <div class="divider">
                        <span>or sign up with</span>
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
                        Already have an account? <a href="login.php">Sign in</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
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
        
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                showNotification('Passwords do not match', 'error');
                return;
            }
            
            if (password.length < 8) {
                showNotification('Password must be at least 8 characters', 'error');
                return;
            }
            
            showNotification('Sign up coming soon! Use login page with demo credentials.', 'info');
        });
        
        function showNotification(message, type = 'info') {
            const existing = document.querySelector('.notification');
            if (existing) existing.remove();
            
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }
    </script>
</body>
</html>