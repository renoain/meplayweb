<?php
 require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'config/functions.php';

// Start session jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect ke login jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['user_email'];
$profile_picture = $_SESSION['profile_picture'] ?? 'default-avatar.png';
$role = $_SESSION['user_role'] ?? 'user';

 $success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_username'])) {
        // Update username
        $new_username = sanitizeInput($_POST['username']);
        
        // Validate username
        if (empty($new_username)) {
            $error = "Username tidak boleh kosong";
        } elseif (!isValidUsername($new_username)) {
            $error = "Username harus 3-50 karakter dan hanya boleh mengandung huruf, angka, dan underscore";
        } elseif ($new_username === $username) {
            $error = "Username sama dengan yang lama";
        } else {
            try {
                $database = new Database();
                $conn = $database->getConnection();
                
                // Check if username already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$new_username, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $error = "Username sudah digunakan";
                } else {
                    // Update username
                    $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                    
                    if ($stmt->execute([$new_username, $user_id])) {
                        // Update session
                        $_SESSION['username'] = $new_username;
                        $username = $new_username;
                        
                        $success = "Username berhasil diubah";
                        logActivity("User $user_id changed username to $new_username");
                    } else {
                        $error = "Gagal mengubah username";
                    }
                }
            } catch (PDOException $e) {
                error_log("Update username error: " . $e->getMessage());
                $error = "Terjadi kesalahan sistem";
            }
        }
    }
    
    elseif (isset($_POST['update_email'])) {
        // Update email
        $new_email = sanitizeInput($_POST['email']);
        
        // Validate email
        if (empty($new_email)) {
            $error = "Email tidak boleh kosong";
        } elseif (!isValidEmail($new_email)) {
            $error = "Format email tidak valid";
        } elseif ($new_email === $email) {
            $error = "Email sama dengan yang lama";
        } else {
            try {
                $database = new Database();
                $conn = $database->getConnection();
                
                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$new_email, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $error = "Email sudah digunakan";
                } else {
                    // Update email
                    $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                    
                    if ($stmt->execute([$new_email, $user_id])) {
                        // Update session
                        $_SESSION['user_email'] = $new_email;
                        $email = $new_email;
                        
                        $success = "Email berhasil diubah";
                        logActivity("User $user_id changed email to $new_email");
                    } else {
                        $error = "Gagal mengubah email";
                    }
                }
            } catch (PDOException $e) {
                error_log("Update email error: " . $e->getMessage());
                $error = "Terjadi kesalahan sistem";
            }
        }
    }
    
    elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "Semua field password harus diisi";
        } elseif (strlen($new_password) < 6) {
            $error = "Password baru harus minimal 6 karakter";
        } elseif ($new_password !== $confirm_password) {
            $error = "Password baru dan konfirmasi tidak cocok";
        } else {
            try {
                $database = new Database();
                $conn = $database->getConnection();
                
                // Get current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($current_password, $user['password'])) {
                    // Hash new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    
                    if ($stmt->execute([$hashed_password, $user_id])) {
                        $success = "Password berhasil diubah";
                        logActivity("User $user_id changed password");
                    } else {
                        $error = "Gagal mengubah password";
                    }
                } else {
                    $error = "Password saat ini salah";
                }
            } catch (PDOException $e) {
                error_log("Change password error: " . $e->getMessage());
                $error = "Terjadi kesalahan sistem";
            }
        }
    }
    
    elseif (isset($_POST['upload_avatar'])) {
        // Upload avatar
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            
            // Validasi file
            $allowed_types = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_types)) {
                $error = "Format file tidak didukung. Gunakan JPG, PNG, WebP, atau GIF";
            } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
                $error = "Ukuran file terlalu besar. Maksimal 5MB";
            } else {
                try {
                    $database = new Database();
                    $conn = $database->getConnection();
                    
                    // Generate nama file unik
                    $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = USER_UPLOAD_PATH . $new_filename;
                    
                    // Buat direktori jika belum ada
                    if (!file_exists(USER_UPLOAD_PATH)) {
                        mkdir(USER_UPLOAD_PATH, 0755, true);
                    }
                    
                    // Pindahkan file
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Dapatkan avatar lama
                        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $old_avatar = $stmt->fetchColumn();
                        
                        // Update database
                        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        
                        if ($stmt->execute([$new_filename, $user_id])) {
                            // Hapus avatar lama jika bukan default
                            if ($old_avatar && $old_avatar !== 'default-avatar.png') {
                                $old_file = USER_UPLOAD_PATH . $old_avatar;
                                if (file_exists($old_file)) {
                                    unlink($old_file);
                                }
                            }
                            
                            // Update session
                            $_SESSION['profile_picture'] = $new_filename;
                            $profile_picture = $new_filename;
                            
                            $success = "Foto profil berhasil diunggah";
                            logActivity("User $user_id uploaded new avatar");
                        } else {
                            $error = "Gagal menyimpan foto profil";
                        }
                    } else {
                        $error = "Gagal mengunggah file";
                    }
                } catch (PDOException $e) {
                    error_log("Avatar upload error: " . $e->getMessage());
                    $error = "Terjadi kesalahan sistem";
                }
            }
        } else {
            $error = "Silakan pilih file gambar";
        }
    }
}

// Get user stats
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Liked songs count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM liked_songs WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $liked_songs_count = $stmt->fetchColumn();
    
    // Playlists count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM playlists WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $playlists_count = $stmt->fetchColumn();
    
    // Recent plays count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM recently_played WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $plays_count = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Get user stats error: " . $e->getMessage());
    $liked_songs_count = 0;
    $playlists_count = 0;
    $plays_count = 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/profile.css">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
<body>
    <!-- Header -->
    <header class="profile-header">
        <div class="header-container">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Home
            </a>
            
            <div class="header-actions">
                <a href="logout.php" class="logout-btn" onclick="return confirm('Yakin ingin logout?')">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <main class="profile-main">
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-card">
                <div class="profile-avatar-section">
                   
                    
                    <div class="profile-info">
                        <h1 class="profile-name"><?php echo htmlspecialchars($username); ?></h1>
                        <p class="profile-email"><?php echo htmlspecialchars($email); ?></p>
                        <p class="profile-role">
                            <span class="role-badge <?php echo $role; ?>">
                                <i class="fas fa-<?php echo $role === 'admin' ? 'crown' : 'user'; ?>"></i>
                                <?php echo ucfirst($role); ?>
                            </span>
                        </p>
                    </div>
                </div>
                
               
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <!-- Profile Forms -->
            <div class="profile-sections">
                <!-- Update Username -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-user-edit"></i>
                        Ubah Username
                    </h2>
                    
                    <form method="POST" class="profile-form" id="usernameForm">
                        <div class="form-group">
                            <label for="username">
                                <i class="fas fa-user"></i>
                                Username Baru
                            </label>
                            <input type="text" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($username); ?>"
                                   class="form-control"
                                   placeholder="Masukkan username baru">
                            <div class="form-error" id="usernameError"></div>
                        </div>
                        
                        <button type="submit" name="update_username" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Simpan Perubahan
                        </button>
                    </form>
                </div>

                <!-- Update Email -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-envelope"></i>
                        Ubah Email
                    </h2>
                    
                    <form method="POST" class="profile-form" id="emailForm">
                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i>
                                Email Baru
                            </label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($email); ?>"
                                   class="form-control"
                                   placeholder="Masukkan email baru">
                            <div class="form-error" id="emailError"></div>
                        </div>
                        
                        <button type="submit" name="update_email" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Simpan Perubahan
                        </button>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-lock"></i>
                        Ubah Password
                    </h2>
                    
                    <form method="POST" class="profile-form" id="passwordForm">
                        <div class="form-group">
                            <label for="current_password">
                                <i class="fas fa-key"></i>
                                Password Saat Ini
                            </label>
                            <div class="password-input">
                                <input type="password" id="current_password" name="current_password" 
                                       class="form-control"
                                       placeholder="Masukkan password saat ini">
                                <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-error" id="currentPasswordError"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">
                                <i class="fas fa-key"></i>
                                Password Baru
                            </label>
                            <div class="password-input">
                                <input type="password" id="new_password" name="new_password" 
                                       class="form-control"
                                       placeholder="Masukkan password baru">
                                <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-error" id="newPasswordError"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-key"></i>
                                Konfirmasi Password
                            </label>
                            <div class="password-input">
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="form-control"
                                       placeholder="Konfirmasi password baru">
                                <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-error" id="confirmPasswordError"></div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i>
                            Ubah Password
                        </button>
                    </form>
                </div>

                <!-- Danger Zone -->
                <div class="profile-section danger-zone">
                    <h2 class="section-title">
                    </h2>
                    
                    <div class="danger-actions">
                        <button class="btn btn-danger" onclick="showDeleteAccountModal()">
                            <i class="fas fa-trash"></i>
                            Hapus Akun
                        </button>
                        
                        <button class="btn btn-warning" onclick="showClearHistoryModal()">
                            <i class="fas fa-history"></i>
                            Hapus History
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modals -->
    <div id="deleteAccountModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Hapus Akun</h3>
                <button class="close-modal" onclick="closeDeleteAccountModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus akun? Tindakan ini tidak dapat dibatalkan.</p>
                <p><strong>Semua data Anda akan dihapus permanen:</strong></p>
                <ul>
                    <li>Playlist yang dibuat</li>
                    <li>Lagu yang disukai</li>
                    <li>History pemutaran</li>
                    <li>Semua data akun</li>
                </ul>
                
                <form method="POST" action="api/profile.php" class="delete-form">
                    <input type="hidden" name="action" value="delete_account">
                    <div class="form-group">
                        <label for="confirm_delete">
                            <i class="fas fa-key"></i>
                            Ketik "DELETE" untuk konfirmasi
                        </label>
                        <input type="text" id="confirm_delete" name="confirm_delete" 
                               class="form-control" placeholder="DELETE">
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteAccountModal()">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-danger" id="deleteAccountBtn" disabled>
                            <i class="fas fa-trash"></i>
                            Hapus Akun Permanen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="clearHistoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Hapus History</h3>
                <button class="close-modal" onclick="closeClearHistoryModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus semua history pemutaran?</p>
                <p>Tindakan ini akan menghapus:</p>
                <ul>
                    <li>History lagu yang pernah diputar</li>
                    <li>Rekomendasi berdasarkan history</li>
                </ul>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeClearHistoryModal()">
                        Batal
                    </button>
                    <button type="button" class="btn btn-warning" onclick="clearHistory()">
                        <i class="fas fa-history"></i>
                        Hapus History
                    </button>
                </div>
            </div>
        </div>
    </div>

     <script src="assets/js/profile.js"></script>
</body>
</html>