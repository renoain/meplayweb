<?php
// login.php
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
    $username_or_email = sanitizeInput($_POST['username_or_email']);
    $password = $_POST['password'];
    
    $login_result = $auth->login($username_or_email, $password);
    
    if ($login_result === true) {
        // Login successful - redirect based on role
        if ($auth->isAdmin()) {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: index.php");
        }
        exit();
    } else {
        $message = $login_result;
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
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
                <h1>Welcome Back</h1>
                <p>Sign in to your account</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type == 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="username_or_email">
                        <i class="fas fa-user"></i>
                        Username or Email
                    </label>
                    <input type="text" id="username_or_email" name="username_or_email" required 
                           value="<?php echo isset($_POST['username_or_email']) ? htmlspecialchars($_POST['username_or_email']) : ''; ?>"
                           placeholder="Enter your username or email"
                           autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password"
                           autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary btn-full" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span id="loginText">Sign In</span>
                    <div id="loginSpinner" class="spinner" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </button>
            </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const loginText = document.getElementById('loginText');
            const loginSpinner = document.getElementById('loginSpinner');

            loginForm.addEventListener('submit', function(e) {
                const usernameOrEmail = document.getElementById('username_or_email').value.trim();
                const password = document.getElementById('password').value;

                if (!usernameOrEmail) {
                    e.preventDefault();
                    showError('Please enter your username or email');
                    return false;
                }

                if (!password) {
                    e.preventDefault();
                    showError('Please enter your password');
                    return false;
                }

                // Show loading state
                loginText.style.display = 'none';
                loginSpinner.style.display = 'inline-block';
                loginBtn.disabled = true;
            });

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

            // Auto-detect if input is email or username
            document.getElementById('username_or_email').addEventListener('blur', function() {
                const value = this.value.trim();
                if (value.includes('@')) {
                    this.type = 'email';
                } else {
                    this.type = 'text';
                }
            });

            // Clear error when user starts typing
            document.getElementById('username_or_email').addEventListener('input', clearErrors);
            document.getElementById('password').addEventListener('input', clearErrors);

            function clearErrors() {
                const alert = document.querySelector('.alert');
                if (alert) {
                    alert.remove();
                }
            }
        });
    </script>

    <style>
        .spinner {
            display: inline-block;
            margin-left: 8px;
        }
        
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .btn:disabled:hover {
            transform: none;
            box-shadow: none;
        }
    </style>
</body>
</html>