<?php
// Enable error reporting
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

// Check if GD extension is available
$gd_available = extension_loaded('gd') && function_exists('imagecreatefromjpeg');

// Handle delete action FIRST (before form processing)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $result = deleteAlbum($conn, $_GET['id']);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'error';
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_album'])) {
        $result = addAlbum($conn);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
        
        // Clear form after successful add
        if ($result['success']) {
            $_POST = array();
        }
    } elseif (isset($_POST['edit_album'])) {
        $result = updateAlbum($conn);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
        
        // Redirect to clear form after successful edit
        if ($result['success']) {
            header("Location: albums.php?message=" . urlencode($result['message']) . "&type=success");
            exit();
        }
    }
}

// Get all albums with artist info
try {
    $query = "SELECT a.*, 
              ar.name as artist_name,
              COUNT(s.id) as songs_count
              FROM albums a 
              LEFT JOIN artists ar ON a.artist_id = ar.id 
              LEFT JOIN songs s ON a.id = s.album_id 
              GROUP BY a.id 
              ORDER BY a.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    $message = "Database error: " . $exception->getMessage();
    $message_type = 'error';
    $albums = [];
}

// Get artists for dropdown - only active artists
try {
    $artists = $conn->query("SELECT id, name FROM artists ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    $artists = [];
}

// Handle message from redirect
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'];
}

// Get album for editing - MUST be after form processing
$edit_album = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_album = getAlbumById($conn, $_GET['edit']);
    if (!$edit_album) {
        $message = "Album tidak ditemukan";
        $message_type = 'error';
    }
}

function getAlbumById($conn, $id) {
    try {
        $query = "SELECT a.*, ar.name as artist_name 
                  FROM albums a 
                  LEFT JOIN artists ar ON a.artist_id = ar.id 
                  WHERE a.id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $album = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Convert NULL to empty string for form values
        if ($album) {
            $album['release_year'] = $album['release_year'] ?? '';
        }
        
        return $album;
    } catch(PDOException $exception) {
        error_log("Error getting album: " . $exception->getMessage());
        return null;
    }
}

function validateArtist($conn, $artist_id) {
    if (empty($artist_id)) {
        return array('success' => false, 'message' => 'Artis wajib dipilih');
    }
    
    try {
        // Check if artist exists and is valid
        $query = "SELECT id, name FROM artists WHERE id = :artist_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":artist_id", $artist_id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return array('success' => false, 'message' => 'Artis tidak valid atau tidak ditemukan');
        }
        
        $artist = $stmt->fetch(PDO::FETCH_ASSOC);
        return array('success' => true, 'artist' => $artist);
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}

function addAlbum($conn) {
    global $gd_available;
    
    // Get form data
    $title = trim($_POST['title']);
    $artist_id = $_POST['artist_id'] ?: null;
    $release_year = $_POST['release_year'] ?: null;
    
    // Basic validation
    if (empty($title)) {
        return array('success' => false, 'message' => 'Judul album wajib diisi');
    }
    
    // Validate artist - NOW REQUIRED
    $artist_validation = validateArtist($conn, $artist_id);
    if (!$artist_validation['success']) {
        return $artist_validation;
    }
    
    // Validate release year if provided
    if (!empty($release_year)) {
        $current_year = date('Y');
        if ($release_year < 1900 || $release_year > $current_year) {
            return array('success' => false, 'message' => 'Tahun rilis harus antara 1900 dan ' . $current_year);
        }
    }
    
    try {
        // Check if album already exists for this artist
        $query = "SELECT id FROM albums WHERE title = :title AND artist_id = :artist_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":artist_id", $artist_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $artist_name = $artist_validation['artist']['name'];
            return array('success' => false, 'message' => 'Album "' . $title . '" sudah ada untuk artis ' . $artist_name);
        }
        
        // Handle cover image upload
        $cover_image = 'default-cover.png';
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $cover_file = $_FILES['cover_image'];
            
            if (!isAllowedImageFile($cover_file['name'])) {
                return array('success' => false, 'message' => 'Format file gambar tidak didukung. Gunakan JPG, PNG, atau WEBP');
            }
            
            $cover_image = uploadAlbumCover($cover_file, $gd_available);
            if (!$cover_image) {
                return array('success' => false, 'message' => 'Gagal mengupload cover album');
            }
        }
        
        // Insert album - artist_id is now REQUIRED
        $query = "INSERT INTO albums (title, artist_id, cover_image, release_year) 
                  VALUES (:title, :artist_id, :cover_image, :release_year)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":artist_id", $artist_id);
        $stmt->bindParam(":cover_image", $cover_image);
        
        // Bind release_year properly (can be NULL)
        if (empty($release_year)) {
            $stmt->bindValue(":release_year", null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(":release_year", $release_year);
        }
        
        if ($stmt->execute()) {
            $artist_name = $artist_validation['artist']['name'];
            return array('success' => true, 'message' => 'Album "' . $title . '" oleh ' . $artist_name . ' berhasil ditambahkan!');
        } else {
            // Delete uploaded file if database insert fails
            if ($cover_image && $cover_image != 'default-cover.png' && file_exists("../uploads/covers/" . $cover_image)) {
                unlink("../uploads/covers/" . $cover_image);
            }
            return array('success' => false, 'message' => 'Gagal menambahkan album ke database');
        }
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}

function updateAlbum($conn) {
    global $gd_available;
    
    $id = $_POST['id'];
    $title = trim($_POST['title']);
    $artist_id = $_POST['artist_id'] ?: null;
    $release_year = $_POST['release_year'] ?: null;
    
    if (empty($title)) {
        return array('success' => false, 'message' => 'Judul album wajib diisi');
    }
    
    // Validate artist - NOW REQUIRED
    $artist_validation = validateArtist($conn, $artist_id);
    if (!$artist_validation['success']) {
        return $artist_validation;
    }
    
    // Validate release year if provided
    if (!empty($release_year)) {
        $current_year = date('Y');
        if ($release_year < 1900 || $release_year > $current_year) {
            return array('success' => false, 'message' => 'Tahun rilis harus antara 1900 dan ' . $current_year);
        }
    }
    
    try {
        // Check if album exists
        $current_album = getAlbumById($conn, $id);
        if (!$current_album) {
            return array('success' => false, 'message' => 'Album tidak ditemukan');
        }
        
        // Check if album already exists for this artist (excluding current album)
        $query = "SELECT id FROM albums WHERE title = :title AND artist_id = :artist_id AND id != :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":artist_id", $artist_id);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $artist_name = $artist_validation['artist']['name'];
            return array('success' => false, 'message' => 'Album "' . $title . '" sudah ada untuk artis ' . $artist_name);
        }
        
        // Handle cover image
        $cover_image = $current_album['cover_image'];
        
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $cover_file = $_FILES['cover_image'];
            
            if (!isAllowedImageFile($cover_file['name'])) {
                return array('success' => false, 'message' => 'Format file gambar tidak didukung');
            }
            
            // Remove old image if exists
            if ($cover_image && $cover_image != 'default-cover.png' && file_exists("../uploads/covers/" . $cover_image)) {
                unlink("../uploads/covers/" . $cover_image);
            }
            
            $cover_image = uploadAlbumCover($cover_file, $gd_available);
            if (!$cover_image) {
                return array('success' => false, 'message' => 'Gagal mengupload cover album');
            }
        }
        
        // Remove image if checkbox is checked
        if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            if ($cover_image && $cover_image != 'default-cover.png' && file_exists("../uploads/covers/" . $cover_image)) {
                unlink("../uploads/covers/" . $cover_image);
            }
            $cover_image = 'default-cover.png'; // Set to default
        }
        
        // Update album - artist_id is now REQUIRED
        $query = "UPDATE albums SET title = :title, artist_id = :artist_id, cover_image = :cover_image, 
                  release_year = :release_year, updated_at = NOW() 
                  WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":artist_id", $artist_id);
        $stmt->bindParam(":cover_image", $cover_image);
        
        // Bind release_year properly (can be NULL)
        if (empty($release_year)) {
            $stmt->bindValue(":release_year", null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(":release_year", $release_year);
        }
        
        $stmt->bindParam(":id", $id);
        
        if ($stmt->execute()) {
            $artist_name = $artist_validation['artist']['name'];
            return array('success' => true, 'message' => 'Album "' . $title . '" oleh ' . $artist_name . ' berhasil diperbarui!');
        } else {
            return array('success' => false, 'message' => 'Gagal memperbarui album');
        }
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}

function deleteAlbum($conn, $id) {
    try {
        // Get album data first
        $album = getAlbumById($conn, $id);
        if (!$album) {
            return array('success' => false, 'message' => 'Album tidak ditemukan');
        }
        
        // Check if album has songs
        $query = "SELECT COUNT(*) as songs_count FROM songs WHERE album_id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $songs_count = $stmt->fetch(PDO::FETCH_ASSOC)['songs_count'];
        
        if ($songs_count > 0) {
            return array('success' => false, 'message' => 'Tidak dapat menghapus album yang memiliki lagu. Hapus lagu terlebih dahulu.');
        }
        
        // Delete album
        $query = "DELETE FROM albums WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        
        if ($stmt->execute()) {
            // Delete cover image if exists and not default
            if ($album['cover_image'] && $album['cover_image'] != 'default-cover.png' && 
                file_exists("../uploads/covers/" . $album['cover_image'])) {
                unlink("../uploads/covers/" . $album['cover_image']);
            }
            return array('success' => true, 'message' => 'Album berhasil dihapus');
        } else {
            return array('success' => false, 'message' => 'Gagal menghapus album');
        }
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}

function uploadAlbumCover($file, $gd_available = false) {
    $upload_dir = "../uploads/covers/";
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'album_' . uniqid() . '_' . time() . '.' . $file_extension;
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Only resize if GD is available
        if ($gd_available) {
            resizeImage($target_path, 500, 500);
        }
        return $filename;
    }
    return false;
}

function resizeImage($file_path, $max_width, $max_height) {
    if (!file_exists($file_path)) return false;
    
    // Check if GD functions are available
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
        return false;
    }
    
    list($width, $height, $type) = getimagesize($file_path);
    
    // If image is already smaller than max dimensions, no need to resize
    if ($width <= $max_width && $height <= $max_height) {
        return true;
    }
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = @imagecreatefromjpeg($file_path);
            break;
        case IMAGETYPE_PNG:
            $image = @imagecreatefrompng($file_path);
            break;
        case IMAGETYPE_WEBP:
            $image = @imagecreatefromwebp($file_path);
            break;
        default:
            return false;
    }
    
    if (!$image) return false;
    
    // Calculate new dimensions
    $ratio = $width / $height;
    if ($max_width / $max_height > $ratio) {
        $new_width = $max_height * $ratio;
        $new_height = $max_height;
    } else {
        $new_width = $max_width;
        $new_height = $max_width / $ratio;
    }
    
    // Create new image
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency for PNG and WEBP
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
        imagecolortransparent($new_image, imagecolorallocatealpha($new_image, 0, 0, 0, 127));
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
    }
    
    // Resize image
    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Save image
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($new_image, $file_path, 90);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($new_image, $file_path, 9);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($new_image, $file_path, 90);
            break;
    }
    
    // Free memory
    imagedestroy($image);
    imagedestroy($new_image);
    
    return $result;
}

function isAllowedImageFile($filename) {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($file_extension, $allowed_extensions);
}

// Helper function to get year value for form
function getYearValue($edit_album, $post_data) {
    // Priority 1: Form submission data (after validation error)
    if (isset($post_data['release_year']) && $post_data['release_year'] !== '') {
        return $post_data['release_year'];
    }
    
    // Priority 2: Editing existing album
    if ($edit_album && isset($edit_album['release_year']) && $edit_album['release_year'] !== '') {
        return $edit_album['release_year'];
    }
    
    // Default: empty string
    return '';
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Album - Admin <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/admin-main.css">
    <link rel="stylesheet" href="assets/css/admin-albums.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Kelola Album</h1>
                    <p>Kelola data album musik</p>
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
                <?php if (!$gd_available): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Peringatan:</strong> Ekstensi GD tidak tersedia. Upload gambar tetap bekerja, tetapi tidak akan di-resize otomatis.
                    </div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type == 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="albums-container">
                    <!-- Add/Edit Album Form -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-<?php echo $edit_album ? 'edit' : 'plus'; ?>"></i>
                                <?php echo $edit_album ? 'Edit Album' : 'Tambah Album Baru'; ?>
                            </h3>
                            <?php if ($edit_album): ?>
                                <a href="albums.php" class="btn btn-outline">
                                    <i class="fas fa-plus"></i> Tambah Baru
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-content">
                            <form method="POST" action="" enctype="multipart/form-data" class="album-form" id="albumForm">
                                <?php if ($edit_album): ?>
                                    <input type="hidden" name="id" value="<?php echo $edit_album['id']; ?>">
                                <?php endif; ?>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="title">Judul Album *</label>
                                        <input type="text" id="title" name="title" required
                                               value="<?php echo $edit_album ? htmlspecialchars($edit_album['title']) : (isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''); ?>"
                                               placeholder="Masukkan judul album">
                                    </div>

                                    <div class="form-group">
                                        <label for="artist_id">Artis *</label>
                                        <div class="searchable-select">
                                            <div class="select-search">
                                                <input type="text" class="search-input" placeholder="Ketik untuk mencari artis..." id="artistSearch" required>
                                                <i class="fas fa-search"></i>
                                            </div>
                                            <select id="artist_id" name="artist_id" class="select-dropdown" required>
                                                <option value="">Pilih Artis</option>
                                                <?php foreach ($artists as $artist): ?>
                                                    <option value="<?php echo $artist['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($artist['name']); ?>"
                                                        <?php echo ($edit_album && $edit_album['artist_id'] == $artist['id']) || (isset($_POST['artist_id']) && $_POST['artist_id'] == $artist['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($artist['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <small class="form-help">Wajib dipilih. Gunakan search untuk mencari artis</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="release_year">Tahun Rilis</label>
                                        <input type="number" id="release_year" name="release_year" 
                                               min="1900" max="<?php echo date('Y'); ?>" 
                                               value="<?php echo htmlspecialchars(getYearValue($edit_album, $_POST)); ?>"
                                               placeholder="YYYY">
                                        <small class="form-help">Opsional. Contoh: 2024</small>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="cover_image">Cover Album</label>
                                    <div class="file-upload" id="coverImageUpload">
                                        <div class="upload-icon">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                        </div>
                                        <p>Klik untuk upload cover atau drag & drop</p>
                                        <input type="file" id="cover_image" name="cover_image" 
                                               accept=".jpg,.jpeg,.png,.webp">
                                        <div class="file-info">Belum ada file yang dipilih</div>
                                        <small class="form-help">Format: JPG, PNG, WEBP (maks. 5MB)</small>
                                        <?php if (!$gd_available): ?>
                                            <small class="form-help warning">⚠️ Gambar tidak akan di-resize otomatis</small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($edit_album && $edit_album['cover_image'] && $edit_album['cover_image'] != 'default-cover.png'): ?>
                                        <div class="current-image">
                                            <p><strong>Cover Saat Ini:</strong></p>
                                            <img src="../uploads/covers/<?php echo $edit_album['cover_image']; ?>" 
                                                 alt="Current Cover" class="cover-preview">
                                            <div class="remove-image">
                                                <label>
                                                    <input type="checkbox" name="remove_image" value="1">
                                                    Hapus cover saat ini (gunakan default)
                                                </label>
                                            </div>
                                        </div>
                                    <?php elseif ($edit_album): ?>
                                        <div class="current-image">
                                            <p><strong>Cover Saat Ini:</strong> Default Cover</p>
                                            <img src="../uploads/covers/default-cover.png" 
                                                 alt="Default Cover" class="cover-preview">
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="form-actions">
                                    <?php if ($edit_album): ?>
                                        <button type="submit" name="edit_album" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Album
                                        </button>
                                        <a href="albums.php" class="btn btn-outline">Batal</a>
                                    <?php else: ?>
                                        <button type="submit" name="add_album" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Tambah Album
                                        </button>
                                        <button type="reset" class="btn btn-outline">Reset</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Albums List -->
                    <div class="albums-list">
                        <div class="card-header">
                            <h3><i class="fas fa-compact-disc"></i> Daftar Album</h3>
                            <div class="total-albums">
                                Total: <strong><?php echo count($albums); ?></strong> Album
                            </div>
                        </div>
                        <div class="card-content">
                            <?php if ($albums && count($albums) > 0): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Album</th>
                                                <th>Artis</th>
                                                <th>Lagu</th>
                                                <th>Tahun</th>
                                                <th>Tanggal</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($albums as $album): ?>
                                                <tr>
                                                    <td>
                                                        <div class="album-info">
                                                            <div class="album-cover">
                                                                <?php if ($album['cover_image'] && $album['cover_image'] != 'default-cover.png'): ?>
                                                                    <img src="../uploads/covers/<?php echo $album['cover_image']; ?>" 
                                                                         alt="<?php echo htmlspecialchars($album['title']); ?>"
                                                                         onerror="this.src='../uploads/covers/default-cover.png'">
                                                                <?php else: ?>
                                                                    <div class="no-cover">
                                                                        <i class="fas fa-compact-disc"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="album-details">
                                                                <h4><?php echo htmlspecialchars($album['title']); ?></h4>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($album['artist_name']): ?>
                                                            <span class="artist-name"><?php echo htmlspecialchars($album['artist_name']); ?></span>
                                                        <?php else: ?>
                                                            <span class="no-artist error">Tidak ada artis</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge"><?php echo $album['songs_count']; ?> lagu</span>
                                                    </td>
                                                    <td>
                                                        <?php echo $album['release_year'] ?: '-'; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('d M Y', strtotime($album['created_at'])); ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="albums.php?edit=<?php echo $album['id']; ?>" 
                                                               class="btn btn-sm btn-outline" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button onclick="confirmDelete(<?php echo $album['id']; ?>)" 
                                                                    class="btn btn-sm btn-danger" title="Hapus"
                                                                    <?php echo $album['songs_count'] > 0 ? 'disabled' : ''; ?>>
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
                                    <i class="fas fa-compact-disc fa-3x"></i>
                                    <h3>Belum Ada Album</h3>
                                    <p>Tambahkan album pertama Anda untuk memulai</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/admin-main.js"></script>
    <script src="assets/js/admin-albums.js"></script>
</body>
</html>