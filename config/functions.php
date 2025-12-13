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
    
    $path = '';
    switch ($type) {
        case 'song':
        case 'album':
            $path = 'uploads/covers/' . $cover_image;
            break;
        case 'artist':
            $path = 'uploads/artists/' . $cover_image;
            break;
        case 'playlist':
            $path = 'uploads/playlists/' . $cover_image;
            break;
    }
    
    if ($path && file_exists($path)) {
        return $path;
    }
    
    return 'assets/images/covers/default-cover.png';
}

// Function khusus untuk playlist cover
function getPlaylistCoverPath($cover_image) {
    return getCoverPath($cover_image, 'playlist');
}

// Upload playlist cover
function uploadPlaylistCover($file) {
    $target_dir = dirname(__DIR__) . '/uploads/playlists/';
    
    // Create directory if not exists
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
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

    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB.'];
    }

    // Get file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file type
    $allowed_types = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($file_extension, $allowed_types)) {
        return [
            'success' => false, 
            'message' => 'File type not allowed. Only JPG, PNG, WebP, GIF are allowed.'
        ];
    }
    
    // Generate unique filename
    $new_filename = 'playlist_' . uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return [
            'success' => true, 
            'file_name' => $new_filename,
            'file_path' => $target_file
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to upload image.'];
    }
}

// Delete playlist cover
function deletePlaylistCover($cover_image) {
    if ($cover_image && $cover_image !== 'default-cover.png') {
        $target_dir = dirname(__DIR__) . '/uploads/playlists/';
        $file_path = $target_dir . $cover_image;
        
        if (file_exists($file_path)) {
            return unlink($file_path);
        }
    }
    return true;
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

// Create playlist directory
function createPlaylistDirectory() {
    $playlist_dir = UPLOAD_PATH . 'playlists/';
    if (!file_exists($playlist_dir)) {
        mkdir($playlist_dir, 0755, true);
    }
    return $playlist_dir;
}

function getUserStats($user_id) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stats = [
            'liked_songs' => 0,
            'playlists' => 0,
            'total_plays' => 0,
            'followed_artists' => 0
        ];
        
        // Count liked songs
        $stmt = $conn->prepare("SELECT COUNT(*) FROM liked_songs WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats['liked_songs'] = $stmt->fetchColumn();
        
        // Count playlists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM playlists WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats['playlists'] = $stmt->fetchColumn();
        
        // Count total plays
        $stmt = $conn->prepare("SELECT COUNT(*) FROM recently_played WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats['total_plays'] = $stmt->fetchColumn();
        
        // Count followed artists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM artist_followers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats['followed_artists'] = $stmt->fetchColumn();
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Get user stats error: " . $e->getMessage());
        return [
            'liked_songs' => 0,
            'playlists' => 0,
            'total_plays' => 0,
            'followed_artists' => 0
        ];
    }
}

/**
 * Get user recent activity
 */
function getUserRecentActivity($user_id, $limit = 10) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $query = "SELECT 
                    rp.id,
                    rp.song_id,
                    rp.played_at,
                    s.title as song_title,
                    s.cover_image,
                    a.name as artist_name,
                    al.title as album_title
                 FROM recently_played rp
                 LEFT JOIN songs s ON rp.song_id = s.id
                 LEFT JOIN artists a ON s.artist_id = a.id
                 LEFT JOIN albums al ON s.album_id = al.id
                 WHERE rp.user_id = ?
                 ORDER BY rp.played_at DESC
                 LIMIT ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get user activity error: " . $e->getMessage());
        return [];
    }
}

/**
 * Validate username availability
 */
function isUsernameAvailable($username, $exclude_user_id = null) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($exclude_user_id) {
            $query = "SELECT COUNT(*) FROM users WHERE username = ? AND id != ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$username, $exclude_user_id]);
        } else {
            $query = "SELECT COUNT(*) FROM users WHERE username = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$username]);
        }
        
        return $stmt->fetchColumn() == 0;
    } catch (PDOException $e) {
        error_log("Check username error: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate email availability
 */
function isEmailAvailable($email, $exclude_user_id = null) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($exclude_user_id) {
            $query = "SELECT COUNT(*) FROM users WHERE email = ? AND id != ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$email, $exclude_user_id]);
        } else {
            $query = "SELECT COUNT(*) FROM users WHERE email = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$email]);
        }
        
        return $stmt->fetchColumn() == 0;
    } catch (PDOException $e) {
        error_log("Check email error: " . $e->getMessage());
        return false;
    }
}
?>