<?php
 error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/constants.php';
require_once '../config/auth.php';

$auth = new Auth();
$auth->requireAdmin();

require_once '../config/database.php';
$database = new Database();
$conn = $database->getConnection();

$message = '';
$message_type = '';

// Handle delete action FIRST
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $result = deleteSong($conn, $_GET['id']);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'error';
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_song'])) {
        $result = addSong($conn);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
        
        // Clear form after successful add
        if ($result['success']) {
            $_POST = array();
        }
    } elseif (isset($_POST['edit_song'])) {
        $result = updateSong($conn);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
        
        // Redirect to clear form after successful edit
        if ($result['success']) {
            header("Location: songs.php?message=" . urlencode($result['message']) . "&type=success");
            exit();
        }
    }
}

// Get all songs with artist and album info
try {
    $query = "SELECT s.*, 
              a.name as artist_name,
              al.title as album_title,
              g.name as genre_name
              FROM songs s 
              LEFT JOIN artists a ON s.artist_id = a.id 
              LEFT JOIN albums al ON s.album_id = al.id 
              LEFT JOIN genres g ON s.genre_id = g.id 
              ORDER BY s.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    $message = "Database error: " . $exception->getMessage();
    $message_type = 'error';
    $songs = [];
}

// Get artists, albums, and genres for dropdowns
try {
    $artists = $conn->query("SELECT id, name FROM artists ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $albums = $conn->query("SELECT id, title FROM albums ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
    $genres = $conn->query("SELECT id, name FROM genres ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    $artists = [];
    $albums = [];
    $genres = [];
}

// Handle message from redirect
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'];
}

// Get song for editing - MUST be after form processing
$edit_song = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_song = getSongById($conn, $_GET['edit']);
    if (!$edit_song) {
        $message = "Lagu tidak ditemukan";
        $message_type = 'error';
    }
}

function getSongById($conn, $id) {
    try {
        $query = "SELECT s.*, 
                  a.name as artist_name,
                  al.title as album_title,
                  g.name as genre_name
                  FROM songs s 
                  LEFT JOIN artists a ON s.artist_id = a.id 
                  LEFT JOIN albums al ON s.album_id = al.id 
                  LEFT JOIN genres g ON s.genre_id = g.id 
                  WHERE s.id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Convert duration to minutes and seconds
        if ($song && $song['duration']) {
            $song['duration_minutes'] = floor($song['duration'] / 60);
            $song['duration_seconds'] = $song['duration'] % 60;
        }
        
        return $song;
    } catch(PDOException $exception) {
        error_log("Error getting song: " . $exception->getMessage());
        return null;
    }
}

function addSong($conn) {
    // Get form data
    $title = trim($_POST['title']);
    $artist_id = $_POST['artist_id'] ?: null;
    $album_id = $_POST['album_id'] ?: null;
    $genre_id = $_POST['genre_id'] ?: null;
    $duration_minutes = $_POST['duration_minutes'] ?: 0;
    $duration_seconds = $_POST['duration_seconds'] ?: 0;
    
    // Basic validation
    if (empty($title)) {
        return array('success' => false, 'message' => 'Judul lagu wajib diisi');
    }
    
    if (empty($artist_id)) {
        return array('success' => false, 'message' => 'Artis wajib dipilih');
    }
    
    // Validate duration
    $duration_minutes = intval($duration_minutes);
    $duration_seconds = intval($duration_seconds);
    
    if ($duration_minutes < 0 || $duration_seconds < 0 || $duration_seconds > 59) {
        return array('success' => false, 'message' => 'Format durasi tidak valid');
    }
    
    $duration = ($duration_minutes * 60) + $duration_seconds;
    
    if ($duration <= 0) {
        return array('success' => false, 'message' => 'Durasi lagu harus lebih dari 0 detik');
    }
    
    try {
        // Check if song already exists
        $query = "SELECT id FROM songs WHERE title = :title AND artist_id = :artist_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":artist_id", $artist_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return array('success' => false, 'message' => 'Lagu "' . $title . '" sudah ada untuk artis ini');
        }
        
        // Handle audio file upload
        $audio_file = null;
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            $audio = $_FILES['audio_file'];
            
            if (!isAllowedAudioFile($audio['name'])) {
                return array('success' => false, 'message' => 'Format file audio tidak didukung. Gunakan MP3, WAV, atau OGG');
            }
            
            $audio_file = uploadSongAudio($audio);
            if (!$audio_file) {
                return array('success' => false, 'message' => 'Gagal mengupload file audio');
            }
        } else {
            return array('success' => false, 'message' => 'File audio wajib diupload');
        }
        
        // Handle cover image upload (optional)
        $cover_image = null;
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $cover = $_FILES['cover_image'];
            
            if (!isAllowedImageFile($cover['name'])) {
                // Delete uploaded audio file if cover validation fails
                if ($audio_file && file_exists("../uploads/audio/" . $audio_file)) {
                    unlink("../uploads/audio/" . $audio_file);
                }
                return array('success' => false, 'message' => 'Format file cover tidak didukung');
            }
            
            $cover_image = uploadSongCover($cover);
            if (!$cover_image) {
                // Delete uploaded audio file if cover upload fails
                if ($audio_file && file_exists("../uploads/audio/" . $audio_file)) {
                    unlink("../uploads/audio/" . $audio_file);
                }
                return array('success' => false, 'message' => 'Gagal mengupload cover lagu');
            }
        }
        
        // Insert song
        $query = "INSERT INTO songs (title, artist_id, album_id, genre_id, audio_file, cover_image, duration) 
                  VALUES (:title, :artist_id, :album_id, :genre_id, :audio_file, :cover_image, :duration)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":artist_id", $artist_id);
        $stmt->bindParam(":album_id", $album_id);
        $stmt->bindParam(":genre_id", $genre_id);
        $stmt->bindParam(":audio_file", $audio_file);
        $stmt->bindParam(":cover_image", $cover_image);
        $stmt->bindParam(":duration", $duration);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Lagu "' . $title . '" berhasil ditambahkan!');
        } else {
            // Delete uploaded files if database insert fails
            if ($audio_file && file_exists("../uploads/audio/" . $audio_file)) {
                unlink("../uploads/audio/" . $audio_file);
            }
            if ($cover_image && file_exists("../uploads/covers/" . $cover_image)) {
                unlink("../uploads/covers/" . $cover_image);
            }
            return array('success' => false, 'message' => 'Gagal menambahkan lagu ke database');
        }
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}

function updateSong($conn) {
    $id = $_POST['id'];
    $title = trim($_POST['title']);
    $artist_id = $_POST['artist_id'] ?: null;
    $album_id = $_POST['album_id'] ?: null;
    $genre_id = $_POST['genre_id'] ?: null;
    $duration_minutes = $_POST['duration_minutes'] ?: 0;
    $duration_seconds = $_POST['duration_seconds'] ?: 0;
    
    if (empty($title)) {
        return array('success' => false, 'message' => 'Judul lagu wajib diisi');
    }
    
    if (empty($artist_id)) {
        return array('success' => false, 'message' => 'Artis wajib dipilih');
    }
    
    // Validate duration
    $duration_minutes = intval($duration_minutes);
    $duration_seconds = intval($duration_seconds);
    
    if ($duration_minutes < 0 || $duration_seconds < 0 || $duration_seconds > 59) {
        return array('success' => false, 'message' => 'Format durasi tidak valid');
    }
    
    $duration = ($duration_minutes * 60) + $duration_seconds;
    
    if ($duration <= 0) {
        return array('success' => false, 'message' => 'Durasi lagu harus lebih dari 0 detik');
    }
    
    try {
        // Check if song exists
        $current_song = getSongById($conn, $id);
        if (!$current_song) {
            return array('success' => false, 'message' => 'Lagu tidak ditemukan');
        }
        
        // Check if song already exists (excluding current song)
        $query = "SELECT id FROM songs WHERE title = :title AND artist_id = :artist_id AND id != :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":artist_id", $artist_id);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return array('success' => false, 'message' => 'Lagu "' . $title . '" sudah ada untuk artis ini');
        }
        
        // Handle audio file
        $audio_file = $current_song['audio_file'];
        
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            $audio = $_FILES['audio_file'];
            
            if (!isAllowedAudioFile($audio['name'])) {
                return array('success' => false, 'message' => 'Format file audio tidak didukung');
            }
            
            // Remove old audio file if exists
            if ($audio_file && file_exists("../uploads/audio/" . $audio_file)) {
                unlink("../uploads/audio/" . $audio_file);
            }
            
            $audio_file = uploadSongAudio($audio);
            if (!$audio_file) {
                return array('success' => false, 'message' => 'Gagal mengupload file audio');
            }
        }
        
        // Handle cover image
        $cover_image = $current_song['cover_image'];
        
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $cover = $_FILES['cover_image'];
            
            if (!isAllowedImageFile($cover['name'])) {
                return array('success' => false, 'message' => 'Format file cover tidak didukung');
            }
            
            // Remove old cover image if exists
            if ($cover_image && file_exists("../uploads/covers/" . $cover_image)) {
                unlink("../uploads/covers/" . $cover_image);
            }
            
            $cover_image = uploadSongCover($cover);
            if (!$cover_image) {
                return array('success' => false, 'message' => 'Gagal mengupload cover lagu');
            }
        }
        
        // Remove cover image if checkbox is checked
        if (isset($_POST['remove_cover']) && $_POST['remove_cover'] == '1') {
            if ($cover_image && file_exists("../uploads/covers/" . $cover_image)) {
                unlink("../uploads/covers/" . $cover_image);
            }
            $cover_image = null;
        }
        
        // Update song
        $query = "UPDATE songs SET title = :title, artist_id = :artist_id, album_id = :album_id, 
                  genre_id = :genre_id, audio_file = :audio_file, cover_image = :cover_image, 
                  duration = :duration, updated_at = NOW() WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":artist_id", $artist_id);
        $stmt->bindParam(":album_id", $album_id);
        $stmt->bindParam(":genre_id", $genre_id);
        $stmt->bindParam(":audio_file", $audio_file);
        $stmt->bindParam(":cover_image", $cover_image);
        $stmt->bindParam(":duration", $duration);
        $stmt->bindParam(":id", $id);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Lagu "' . $title . '" berhasil diperbarui!');
        } else {
            return array('success' => false, 'message' => 'Gagal memperbarui lagu');
        }
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}

function deleteSong($conn, $id) {
    try {
        // Get song data first
        $song = getSongById($conn, $id);
        if (!$song) {
            return array('success' => false, 'message' => 'Lagu tidak ditemukan');
        }
        
        // Delete song
        $query = "DELETE FROM songs WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        
        if ($stmt->execute()) {
            // Delete audio file if exists
            if ($song['audio_file'] && file_exists("../uploads/audio/" . $song['audio_file'])) {
                unlink("../uploads/audio/" . $song['audio_file']);
            }
            
            // Delete cover image if exists
            if ($song['cover_image'] && file_exists("../uploads/covers/" . $song['cover_image'])) {
                unlink("../uploads/covers/" . $song['cover_image']);
            }
            
            return array('success' => true, 'message' => 'Lagu berhasil dihapus');
        } else {
            return array('success' => false, 'message' => 'Gagal menghapus lagu');
        }
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}

function uploadSongAudio($file) {
    $upload_dir = "../uploads/audio/";
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'song_' . uniqid() . '_' . time() . '.' . $file_extension;
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $filename;
    }
    return false;
}

function uploadSongCover($file) {
    $upload_dir = "../uploads/covers/";
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'song_cover_' . uniqid() . '_' . time() . '.' . $file_extension;
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $filename;
    }
    return false;
}

function isAllowedAudioFile($filename) {
    $allowed_extensions = ['mp3', 'wav', 'ogg', 'm4a'];
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($file_extension, $allowed_extensions);
}

function isAllowedImageFile($filename) {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($file_extension, $allowed_extensions);
}

// Helper function to get duration values for form
function getDurationValue($edit_song, $post_data, $type = 'minutes') {
    // Priority 1: Form submission data (after validation error)
    if (isset($post_data['duration_' . $type]) && $post_data['duration_' . $type] !== '') {
        return $post_data['duration_' . $type];
    }
    
    // Priority 2: Editing existing song
    if ($edit_song && isset($edit_song['duration_' . $type])) {
        return $edit_song['duration_' . $type];
    }
    
    // Default: 0
    return 0;
}

// Helper function to get selected value
function getSelectedValue($field, $edit_song, $post_data, $value) {
    if ($edit_song && $edit_song[$field] == $value) {
        return 'selected';
    }
    if (isset($post_data[$field]) && $post_data[$field] == $value) {
        return 'selected';
    }
    return '';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Lagu - Admin <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/admin-main.css">
    <link rel="stylesheet" href="assets/css/admin-songs.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Kelola Lagu</h1>
                    <p>Kelola data lagu musik</p>
                </div>
                <div class="header-right">
                    <div class="user-menu">
                        <img src="../uploads/users/<?php echo $_SESSION['profile_picture']; ?>" 
                             alt="Profile" class="user-avatar">
                        <span><?php echo $_SESSION['username']; ?></span>
                    </div>
                </div>
            </header>

            <main class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type == 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="songs-container">
                    <!-- Add/Edit Song Form -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-<?php echo $edit_song ? 'edit' : ''; ?>"></i>
                                <?php echo $edit_song ? 'Edit Lagu' : 'Tambah Lagu Baru'; ?>
                            </h3>
                            <?php if ($edit_song): ?>
                                <a href="songs.php" class="btn btn-outline">
                                    <i class="fas fa-plus"></i> Tambah Baru
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-content">
                            <form method="POST" action="" enctype="multipart/form-data" class="song-form" id="songForm">
                                <?php if ($edit_song): ?>
                                    <input type="hidden" name="id" value="<?php echo $edit_song['id']; ?>">
                                <?php endif; ?>

                                <div class="form-grid">
                                    <!-- Title -->
                                    <div class="form-group">
                                        <label for="title">Judul Lagu </label>
                                        <input type="text" id="title" name="title" required
                                               value="<?php echo $edit_song ? htmlspecialchars($edit_song['title']) : (isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''); ?>"
                                               placeholder="Masukkan judul lagu">
                                    </div>

                                    <!-- Artist -->
                                    <div class="form-group">
                                        <label for="artist_id">Artis</label>
                                        <div class="searchable-select">
                                            <div class="select-search">
                                                <input type="text" class="search-input artist-search" 
                                                       placeholder="Ketik untuk mencari artis..." 
                                                       id="artistSearch"
                                                       autocomplete="off"
                                                       value="<?php 
                                                           if ($edit_song && $edit_song['artist_name']) {
                                                               echo htmlspecialchars($edit_song['artist_name']);
                                                           } elseif (isset($_POST['artist_id']) && $_POST['artist_id']) {
                                                               foreach ($artists as $artist) {
                                                                   if ($artist['id'] == $_POST['artist_id']) {
                                                                       echo htmlspecialchars($artist['name']);
                                                                       break;
                                                                   }
                                                               }
                                                           }
                                                       ?>"
                                                       required>
                                                <i class="fas fa-search"></i>
                                            </div>
                                            <select id="artist_id" name="artist_id" class="select-dropdown" required>
                                                <option value="">Pilih Artis</option>
                                                <?php foreach ($artists as $artist): ?>
                                                    <option value="<?php echo $artist['id']; ?>"
                                                        <?php echo getSelectedValue('artist_id', $edit_song, $_POST, $artist['id']); ?>>
                                                        <?php echo htmlspecialchars($artist['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                     </div>

                                    <!-- Album -->
                                    <div class="form-group">
                                        <label for="album_id">Album</label>
                                        <div class="searchable-select">
                                            <div class="select-search">
                                                <input type="text" class="search-input album-search" 
                                                       placeholder="Ketik untuk mencari album..." 
                                                       id="albumSearch"
                                                       autocomplete="off"
                                                       value="<?php 
                                                           if ($edit_song && $edit_song['album_title']) {
                                                               echo htmlspecialchars($edit_song['album_title']);
                                                           } elseif (isset($_POST['album_id']) && $_POST['album_id']) {
                                                               foreach ($albums as $album) {
                                                                   if ($album['id'] == $_POST['album_id']) {
                                                                       echo htmlspecialchars($album['title']);
                                                                       break;
                                                                   }
                                                               }
                                                           }
                                                       ?>">
                                                <i class="fas fa-search"></i>
                                            </div>
                                            <select id="album_id" name="album_id" class="select-dropdown">
                                                <option value="">Pilih Album (Opsional)</option>
                                                <?php foreach ($albums as $album): ?>
                                                    <option value="<?php echo $album['id']; ?>"
                                                        <?php echo getSelectedValue('album_id', $edit_song, $_POST, $album['id']); ?>>
                                                        <?php echo htmlspecialchars($album['title']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                     </div>

                                    <!-- Genre -->
                                    <div class="form-group">
                                        <label for="genre_id">Genre</label>
                                        <div class="searchable-select">
                                            <div class="select-search">
                                                <input type="text" class="search-input genre-search" 
                                                       placeholder="Ketik untuk mencari genre..." 
                                                       id="genreSearch"
                                                       autocomplete="off"
                                                       value="<?php 
                                                           if ($edit_song && $edit_song['genre_name']) {
                                                               echo htmlspecialchars($edit_song['genre_name']);
                                                           } elseif (isset($_POST['genre_id']) && $_POST['genre_id']) {
                                                               foreach ($genres as $genre) {
                                                                   if ($genre['id'] == $_POST['genre_id']) {
                                                                       echo htmlspecialchars($genre['name']);
                                                                       break;
                                                                   }
                                                               }
                                                           }
                                                       ?>">
                                                <i class="fas fa-search"></i>
                                            </div>
                                            <select id="genre_id" name="genre_id" class="select-dropdown">
                                                <option value="">Pilih Genre </option>
                                                <?php foreach ($genres as $genre): ?>
                                                    <option value="<?php echo $genre['id']; ?>"
                                                        <?php echo getSelectedValue('genre_id', $edit_song, $_POST, $genre['id']); ?>>
                                                        <?php echo htmlspecialchars($genre['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                     </div>

                                    <!-- Duration -->
                                    <div class="form-group">
                                        <label for="duration">Durasi</label>
                                        <div class="duration-inputs">
                                            <div class="duration-group">
                                                <input type="number" id="duration_minutes" name="duration_minutes" 
                                                       min="0" max="59" 
                                                       value="<?php echo htmlspecialchars(getDurationValue($edit_song, $_POST, 'minutes')); ?>"
                                                       placeholder="0" required>
                                                <label for="duration_minutes">Menit</label>
                                            </div>
                                            <div class="duration-group">
                                                <input type="number" id="duration_seconds" name="duration_seconds" 
                                                       min="0" max="59" 
                                                       value="<?php echo htmlspecialchars(getDurationValue($edit_song, $_POST, 'seconds')); ?>"
                                                       placeholder="0" required>
                                                <label for="duration_seconds">Detik</label>
                                            </div>
                                        </div>
                                     </div>
                                </div>

                                <!-- Audio File -->
                                <div class="form-group">
                                    <label for="audio_file">File Audio </label>
                                    <div class="file-upload" id="audioUpload">
                                        <div class="upload-icon">
                                            <i class="fas fa-file-audio"></i>
                                        </div>
                                        <p>Klik untuk upload audio atau drag & drop</p>
                                        <input type="file" id="audio_file" name="audio_file" 
                                               accept=".mp3,.wav,.ogg,.m4a" <?php echo !$edit_song ? 'required' : ''; ?>>
                                        <div class="file-info audio-file-info">Belum ada file yang dipilih</div>
                                        <small class="form-help">Format: MP3, WAV, OGG, M4A (maks. 20MB)</small>
                                    </div>
                                    
                                    <?php if ($edit_song && $edit_song['audio_file']): ?>
                                        <div class="current-file">
                                            <p><strong>File Audio Saat Ini:</strong> <?php echo htmlspecialchars($edit_song['audio_file']); ?></p>
                                            <audio controls class="audio-preview">
                                                <source src="../uploads/audio/<?php echo $edit_song['audio_file']; ?>" type="audio/mpeg">
                                                Browser Anda tidak mendukung pemutar audio.
                                            </audio>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Cover Image -->
                                <div class="form-group">
                                    <label for="cover_image">Cover Lagu</label>
                                    <div class="file-upload" id="coverImageUpload">
                                        <div class="upload-icon">
                                            <i class="fas fa-image"></i>
                                        </div>
                                        <p>Klik untuk upload cover atau drag & drop</p>
                                        <input type="file" id="cover_image" name="cover_image" 
                                               accept=".jpg,.jpeg,.png,.webp">
                                        <div class="file-info">Belum ada file yang dipilih</div>
                                        <small class="form-help">Format: JPG, PNG, WEBP (maks. 5MB)</small>
                                    </div>
                                    
                                    <?php if ($edit_song && $edit_song['cover_image']): ?>
                                        <div class="current-image">
                                            <p><strong>Cover Saat Ini:</strong></p>
                                            <img src="../uploads/covers/<?php echo $edit_song['cover_image']; ?>" 
                                                 alt="Current Cover" class="cover-preview">
                                            <div class="remove-image">
                                                <label>
                                                    <input type="checkbox" name="remove_cover" value="1">
                                                    Hapus cover saat ini
                                                </label>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="form-actions">
                                    <?php if ($edit_song): ?>
                                        <button type="submit" name="edit_song" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Lagu
                                        </button>
                                        <a href="songs.php" class="btn btn-outline">Batal</a>
                                    <?php else: ?>
                                        <button type="submit" name="add_song" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Tambah Lagu
                                        </button>
                                        <button type="reset" class="btn btn-outline">Reset</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Songs List -->
                    <div class="songs-list">
                        <div class="card-header">
                            <h3><i class="fas fa-music"></i> Daftar Lagu</h3>
                            <div class="total-songs">
                                Total: <strong><?php echo count($songs); ?></strong> Lagu
                            </div>
                        </div>
                        <div class="card-content">
                            <?php if ($songs && count($songs) > 0): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Lagu</th>
                                                <th>Artis</th>
                                                <th>Album</th>
                                                <th>Genre</th>
                                                <th>Duration</th>
                                                <th>Option</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($songs as $song): ?>
                                                <tr>
                                                    <td>
                                                        <div class="song-info">
                                                            <div class="song-cover">
                                                                <?php if ($song['cover_image']): ?>
                                                                    <img src="../uploads/covers/<?php echo $song['cover_image']; ?>" 
                                                                         alt="<?php echo htmlspecialchars($song['title']); ?>"
                                                                         onerror="this.src='../uploads/covers/default-cover.png'">
                                                                <?php else: ?>
                                                                    <div class="no-cover">
                                                                        <i class="fas fa-music"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="song-details">
                                                                <h4><?php echo htmlspecialchars($song['title']); ?></h4>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php echo $song['artist_name'] ? htmlspecialchars($song['artist_name']) : '-'; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $song['album_title'] ? htmlspecialchars($song['album_title']) : '-'; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $song['genre_name'] ? htmlspecialchars($song['genre_name']) : '-'; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                            $minutes = floor($song['duration'] / 60);
                                                            $seconds = $song['duration'] % 60;
                                                            echo sprintf('%d:%02d', $minutes, $seconds);
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="songs.php?edit=<?php echo $song['id']; ?>" 
                                                               class="btn btn-sm btn-outline" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button onclick="confirmDelete(<?php echo $song['id']; ?>)" 
                                                                    class="btn btn-sm btn-danger" title="Hapus">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-music fa-3x"></i>
                                    <h3>Belum Ada Lagu</h3>
                                    <p>Tambahkan lagu pertama Anda untuk memulai</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/admin-main.js"></script>
    <script src="assets/js/admin-songs.js"></script>
    <script>
    function confirmDelete(songId) {
        if (confirm('Apakah Anda yakin ingin menghapus lagu ini?')) {
            window.location.href = 'songs.php?action=delete&id=' + songId;
        }
    }
    </script>
</body>
</html>