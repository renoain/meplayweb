<?php
// config/functions.php
require_once 'constants.php';

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username);
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function formatDuration($seconds) {
    if ($seconds < 1) return '0:00';
    
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return sprintf("%d:%02d", $minutes, $seconds);
}

function getCoverPath($cover_image, $type = 'song') {
    if (empty($cover_image) || $cover_image === 'default-cover.png') {
        return 'assets/images/covers/default-cover.png';
    }
    
    // Check if it's already a full path
    if (strpos($cover_image, 'uploads/') === 0) {
        return $cover_image;
    }
    
    // For song covers
    if ($type === 'song') {
        $path = 'uploads/covers/' . $cover_image;
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // For album covers
    if ($type === 'album') {
        $path = 'uploads/covers/' . $cover_image;
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // For artist images
    if ($type === 'artist') {
        $path = 'uploads/artists/' . $cover_image;
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return 'assets/images/covers/default-cover.png';
}

function uploadFile($file, $target_dir, $allowed_types = []) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ada',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh extension'
        ];
        return [
            'success' => false, 
            'message' => $error_messages[$file['error']] ?? 'Unknown upload error'
        ];
    }

    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File terlalu besar. Maksimal 10MB'];
    }

    // Get file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Generate unique filename
    $file_name = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $file_name;

    // Check allowed file types
    if (!empty($allowed_types) && !in_array($file_extension, $allowed_types)) {
        return [
            'success' => false, 
            'message' => 'Tipe file tidak diizinkan. Harus: ' . implode(', ', $allowed_types)
        ];
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return [
            'success' => true, 
            'file_name' => $file_name,
            'file_path' => $target_file
        ];
    } else {
        return ['success' => false, 'message' => 'Gagal mengupload file'];
    }
}

function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function logActivity($message, $user_id = null) {
    $log_file = dirname(__DIR__) . '/logs/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = getClientIP();
    $user_id = $user_id ?? ($_SESSION['user_id'] ?? 'guest');
    
    $log_message = "[$timestamp] [IP: $ip] [User: $user_id] $message" . PHP_EOL;
    
    // Create logs directory if it doesn't exist
    $log_dir = dirname($log_file);
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

function getCurrentTheme() {
    return isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
}
?>