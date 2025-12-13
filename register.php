<?php
 require_once 'config/constants.php';
require_once 'config/auth.php';

$auth = new Auth();
$message = '';
$message_type = '';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    if ($auth->isAdmin()) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $register_result = $auth->register($username, $email, $password);
    
    if ($register_result === true) {
        $message = "Registration successful! You can now login.";
        $message_type = 'success';
        $_POST = array(); // Clear form
    } else {
        $message = $register_result;
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <i class="fas fa-music"></i>
                    <span><?php echo SITE_NAME; ?></span>
                </div>
                <h1>Create Account</h1>
                <p>Sign up to start listening</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type == 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        Username
                    </label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           placeholder="Choose a username (3-50 characters)"
                           pattern="[a-zA-Z0-9_]{3,50}"
                           title="Username must be 3-50 characters and contain only letters, numbers, and underscores"
                           autocomplete="username">
                    <small class="form-help">3-50 characters, letters, numbers, and underscores only</small>
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           placeholder="Enter your email"
                           autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password (min. 6 characters)"
                           minlength="6"
                           autocomplete="new-password">
                    <small class="form-help">Minimum 6 characters</small>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-lock"></i>
                        Confirm Password
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Confirm your password"
                           minlength="6"
                           autocomplete="new-password">
                    <small class="form-help" id="passwordMatchText"></small>
                </div>

                <button type="submit" class="btn btn-primary btn-full" id="registerBtn">
                    <i class="fas fa-user-plus"></i>
                    <span id="registerText">Create Account</span>
                    <div id="registerSpinner" class="spinner" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Sign in</a></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const registerForm = document.getElementById('registerForm');
            const registerBtn = document.getElementById('registerBtn');
            const registerText = document.getElementById('registerText');
            const registerSpinner = document.getElementById('registerSpinner');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordMatchText = document.getElementById('passwordMatchText');
            const strengthBar = document.getElementById('strengthBar');

            // Password strength checker
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                checkPasswordStrength(password);
                checkPasswordMatch();
            });

            // Password confirmation checker
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);

            // Form submission
            registerForm.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }

                // Show loading state
                registerText.style.display = 'none';
                registerSpinner.style.display = 'inline-block';
                registerBtn.disabled = true;
            });

            function validateForm() {
                const username = document.getElementById('username').value.trim();
                const email = document.getElementById('email').value.trim();
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                // Username validation
                if (!username) {
                    showError('Please enter a username');
                    return false;
                }

                if (!/^[a-zA-Z0-9_]{3,50}$/.test(username)) {
                    showError('Username must be 3-50 characters and contain only letters, numbers, and underscores');
                    return false;
                }

                // Email validation
                if (!email) {
                    showError('Please enter your email');
                    return false;
                }

                if (!isValidEmail(email)) {
                    showError('Please enter a valid email address');
                    return false;
                }

                // Password validation
                if (!password) {
                    showError('Please enter a password');
                    return false;
                }

                if (password.length < 6) {
                    showError('Password must be at least 6 characters long');
                    return false;
                }

                // Password confirmation
                if (password !== confirmPassword) {
                    showError('Passwords do not match');
                    return false;
                }

                return true;
            }

            function checkPasswordStrength(password) {
                let strength = 0;
                
                if (password.length >= 6) strength++;
                if (password.length >= 8) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;

                // Update strength bar
                strengthBar.className = 'strength-bar';
                if (password.length === 0) {
                    strengthBar.style.width = '0%';
                    strengthBar.className = 'strength-bar';
                } else if (strength <= 2) {
                    strengthBar.style.width = '25%';
                    strengthBar.className = 'strength-bar strength-weak';
                } else if (strength <= 3) {
                    strengthBar.style.width = '50%';
                    strengthBar.className = 'strength-bar strength-fair';
                } else if (strength <= 4) {
                    strengthBar.style.width = '75%';
                    strengthBar.className = 'strength-bar strength-good';
                } else {
                    strengthBar.style.width = '100%';
                    strengthBar.className = 'strength-bar strength-strong';
                }
            }

            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (confirmPassword.length === 0) {
                    passwordMatchText.textContent = '';
                    passwordMatchText.style.color = '#666';
                } else if (password === confirmPassword) {
                    passwordMatchText.textContent = '✓ Passwords match';
                    passwordMatchText.style.color = '#28a745';
                } else {
                    passwordMatchText.textContent = '✗ Passwords do not match';
                    passwordMatchText.style.color = '#dc3545';
                }
            }

            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }

            function showError(message) {
                // Remove existing alerts
                const existingAlert = document.querySelector('.alert');
                if (existingAlert) {
                    existingAlert.remove();
                }

                // Create new alert
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-error';
                alertDiv.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i>
                    ${message}
                `;

                // Insert after auth-header
                const authHeader = document.querySelector('.auth-header');
                authHeader.parentNode.insertBefore(alertDiv, authHeader.nextSibling);

                // Auto remove after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }

            // Clear errors when user starts typing
            const formInputs = registerForm.querySelectorAll('input');
            formInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const alert = document.querySelector('.alert');
                    if (alert && !alert.classList.contains('alert-success')) {
                        alert.remove();
                    }
                });
            });
        });
    </script>
</body>
</html>