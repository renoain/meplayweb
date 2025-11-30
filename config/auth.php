<?php
// config/auth.php
require_once 'database.php';
require_once 'functions.php';

class Auth {
    private $conn;
    private $table_users = 'users';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function register($username, $email, $password) {
        try {
            // Validate input
            if (empty($username) || empty($email) || empty($password)) {
                return "Semua field harus diisi";
            }

            if (!isValidUsername($username)) {
                return "Username harus 3-50 karakter dan hanya boleh mengandung huruf, angka, dan underscore";
            }

            if (!isValidEmail($email)) {
                return "Format email tidak valid";
            }

            if (strlen($password) < 6) {
                return "Password harus minimal 6 karakter";
            }

            // Check if username or email already exists
            $query = "SELECT id FROM " . $this->table_users . " WHERE username = :username OR email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return "Username atau email sudah digunakan";
            }

            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO " . $this->table_users . " (username, email, password, created_at) VALUES (:username, :email, :password, NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);

            if ($stmt->execute()) {
                logActivity("User registered: $username ($email)");
                return true;
            } else {
                return "Gagal mendaftar. Silakan coba lagi.";
            }
        } catch(PDOException $exception) {
            error_log("Registration error: " . $exception->getMessage());
            return "Terjadi kesalahan sistem. Silakan coba lagi.";
        }
    }

    public function login($username_or_email, $password) {
        try {
            // Validate input
            if (empty($username_or_email) || empty($password)) {
                return "Username/email dan password harus diisi";
            }

            $query = "SELECT id, username, email, password, role, profile_picture, is_active FROM " . $this->table_users . " 
                     WHERE (username = :username_or_email OR email = :username_or_email)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username_or_email', $username_or_email);
            $stmt->execute();

            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if user is active
                if (!$user['is_active']) {
                    logActivity("Login attempt for deactivated account: $username_or_email");
                    return "Akun tidak aktif. Silakan hubungi administrator.";
                }
                
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['profile_picture'] = $user['profile_picture'];
                    $_SESSION['logged_in'] = true;
                    
                    // Update last login
                    $this->updateLastLogin($user['id']);
                    
                    logActivity("User logged in: " . $user['username']);
                    return true;
                }
            }
            
            logActivity("Failed login attempt: $username_or_email");
            return "Username/email atau password salah";
        } catch(PDOException $exception) {
            error_log("Login error: " . $exception->getMessage());
            return "Terjadi kesalahan sistem. Silakan coba lagi.";
        }
    }

    private function updateLastLogin($user_id) {
        try {
            $query = "UPDATE " . $this->table_users . " SET last_login = NOW() WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
        } catch(PDOException $exception) {
            error_log("Update last login error: " . $exception->getMessage());
        }
    }

    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }

    public function requireAdmin() {
        $this->requireAuth();
        
        if (!$this->isAdmin()) {
            header("Location: index.php");
            exit();
        }
    }

    public function logout() {
        if ($this->isLoggedIn()) {
            logActivity("User logged out: " . $_SESSION['username']);
        }
        
        session_destroy();
        header("Location: login.php");
        exit();
    }

    public function getUserById($id) {
        try {
            $query = "SELECT id, username, email, profile_picture, role, created_at, last_login FROM " . $this->table_users . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            error_log("Get user error: " . $exception->getMessage());
            return null;
        }
    }

    public function changePassword($user_id, $current_password, $new_password) {
        try {
            // Get current password
            $query = "SELECT password FROM " . $this->table_users . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($current_password, $user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $query = "UPDATE " . $this->table_users . " SET password = :password WHERE id = :id";
                    $stmt = $this->conn->prepare($query);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':id', $user_id);
                    
                    if ($stmt->execute()) {
                        logActivity("Password changed for user ID: $user_id");
                        return true;
                    }
                } else {
                    return "Password saat ini salah";
                }
            }
            return "Gagal mengubah password";
        } catch(PDOException $exception) {
            error_log("Change password error: " . $exception->getMessage());
            return "Terjadi kesalahan sistem";
        }
    }
}
?>