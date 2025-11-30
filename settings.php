<?php
// settings.php
require_once 'config/constants.php';
require_once 'config/auth.php';
require_once 'config/functions.php';
require_once 'config/database.php';

$auth = new Auth();
$auth->requireAuth();

$user_id = $_SESSION['user_id'];
$database = new Database();
$conn = $database->getConnection();

$message = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitizeInput($_POST['full_name']);
        
        try {
            $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
            $stmt->execute([$full_name, $user_id]);
            
            // Update session
            $_SESSION['full_name'] = $full_name;
            
            // Handle Profile Picture
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['profile_picture']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_path = 'assets/uploads/users/' . $new_filename;
                    
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                        // Update DB
                        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        $stmt->execute([$new_filename, $user_id]);
                        $_SESSION['profile_picture'] = $new_filename;
                    }
                }
            }
            
            $message = "Profil berhasil diperbarui!";
            
        } catch(PDOException $e) {
            $error = "Gagal memperbarui profil: " . $e->getMessage();
        }
    }
    
    // Handle Password Change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = "Password baru tidak cocok!";
        } else {
            try {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($current_password, $user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $message = "Password berhasil diubah!";
                } else {
                    $error = "Password saat ini salah!";
                }
            } catch(PDOException $e) {
                $error = "Gagal mengubah password: " . $e->getMessage();
            }
        }
    }
}

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/player.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .settings-card {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <header class="main-header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2>Pengaturan</h2>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="themeToggle">
                        <i class="fas fa-moon"></i>
                    </button>
                    <!-- User Menu -->
                    <div class="user-menu-container">
                        <button class="user-menu-btn" id="userMenuBtn">
                            <img src="<?php echo getAvatarImage($_SESSION['profile_picture']); ?>" 
                                 alt="<?php echo htmlspecialchars($_SESSION['username']); ?>" class="user-avatar">
                            <span><?php echo htmlspecialchars($_SESSION['full_name'] ?: $_SESSION['username']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="user-dropdown" id="userDropdown">
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i> Profil Saya
                            </a>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i> Pengaturan
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item logout-btn">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content-area">
                <div class="settings-container">
                    <?php if ($message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Settings -->
                    <div class="settings-card">
                        <h3><i class="fas fa-user-edit"></i> Edit Profil</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small style="color: var(--text-secondary);">Username tidak dapat diubah.</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label>Nama Lengkap</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Foto Profil</label>
                                <input type="file" name="profile_picture" class="form-control" accept="image/*">
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">Simpan Perubahan</button>
                        </form>
                    </div>
                    
                    <!-- Password Settings -->
                    <div class="settings-card">
                        <h3><i class="fas fa-lock"></i> Ganti Password</h3>
                        <form method="POST">
                            <div class="form-group">
                                <label>Password Saat Ini</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Password Baru</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Konfirmasi Password Baru</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-primary">Ganti Password</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
        
        <?php include 'includes/player.php'; ?>
    </div>

    <script src="assets/js/player.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        const player = new MusicPlayer();
    </script>
</body>
</html>
