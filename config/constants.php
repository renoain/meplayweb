<?php
 
 if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Site configuration
define('SITE_NAME', 'MePlay');
define('BASE_URL', 'http://localhost/meplay');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'meplay');
define('DB_USER', 'root');
define('DB_PASS', '');

// File upload paths
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

define('AUDIO_UPLOAD_PATH', UPLOAD_PATH . 'audio/');
define('COVER_UPLOAD_PATH', UPLOAD_PATH . 'covers/');
define('ARTIST_UPLOAD_PATH', UPLOAD_PATH . 'artists/');
define('USER_UPLOAD_PATH', UPLOAD_PATH . 'users/');

// Default images
define('DEFAULT_AVATAR', 'default-avatar.png');
define('DEFAULT_COVER', 'default-cover.png');

// Create upload directories if they don't exist
$upload_dirs = [
    UPLOAD_PATH,
    AUDIO_UPLOAD_PATH,
    COVER_UPLOAD_PATH,
    ARTIST_UPLOAD_PATH,
    USER_UPLOAD_PATH
];

foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Set default timezone
date_default_timezone_set('Asia/Jakarta');
?>