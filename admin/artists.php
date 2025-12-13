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

// Handle delete action FIRST
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $result = deleteArtist($conn, $_GET['id']);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'error';
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_artist'])) {
        $result = addArtist($conn);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
        
        // Clear form after successful add
        if ($result['success']) {
            $_POST = array();
        }
    } elseif (isset($_POST['edit_artist'])) {
        $result = updateArtist($conn);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
        
        // Redirect to clear form after successful edit
        if ($result['success']) {
            header("Location: artists.php?message=" . urlencode($result['message']) . "&type=success");
            exit();
        }
    }
}

// Get all artists with song count
try {
    $query = "SELECT a.*, 
              COUNT(s.id) as songs_count
              FROM artists a 
              LEFT JOIN songs s ON a.id = s.artist_id 
              GROUP BY a.id 
              ORDER BY a.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    $message = "Database error: " . $exception->getMessage();
    $message_type = 'error';
    $artists = [];
}

// Handle message from redirect
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'];
}

// Get artist for editing - MUST be after form processing
$edit_artist = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_artist = getArtistById($conn, $_GET['edit']);
    if (!$edit_artist) {
        $message = "Artis tidak ditemukan";
        $message_type = 'error';
    }
}

function getArtistById($conn, $id) {
    try {
        $query = "SELECT * FROM artists WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $exception) {
        error_log("Error getting artist: " . $exception->getMessage());
        return null;
    }
}

function addArtist($conn) {
    global $gd_available;
    
    // Get form data
    $name = trim($_POST['name']);
    
    // Basic validation
    if (empty($name)) {
        return array('success' => false, 'message' => 'Nama artis wajib diisi');
    }
    
    try {
        // Check if artist already exists
        $query = "SELECT id FROM artists WHERE name = :name";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return array('success' => false, 'message' => 'Artis "' . $name . '" sudah ada');
        }
        
        // Handle profile picture upload
        $profile_picture = 'default-artist.png'; // Default picture
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $photo = $_FILES['profile_picture'];
            
            if (!isAllowedImageFile($photo['name'])) {
                return array('success' => false, 'message' => 'Format file gambar tidak didukung. Gunakan JPG, PNG, atau WEBP');
            }
            
            $profile_picture = uploadArtistPhoto($photo, $gd_available);
            if (!$profile_picture) {
                return array('success' => false, 'message' => 'Gagal mengupload foto profil');
            }
        }
        
        // Insert artist - FIXED: menggunakan profile_picture bukan photo
        $query = "INSERT INTO artists (name, profile_picture) VALUES (:name, :profile_picture)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":profile_picture", $profile_picture);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Artis "' . $name . '" berhasil ditambahkan!');
        } else {
            // Delete uploaded file if database insert fails
            if ($profile_picture != 'default-artist.png' && file_exists("../uploads/artists/" . $profile_picture)) {
                unlink("../uploads/artists/" . $profile_picture);
            }
            return array('success' => false, 'message' => 'Gagal menambahkan artis ke database');
        }
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}

function updateArtist($conn) {
    global $gd_available;
    
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    
    if (empty($name)) {
        return array('success' => false, 'message' => 'Nama artis wajib diisi');
    }
    
    try {
        // Check if artist exists
        $current_artist = getArtistById($conn, $id);
        if (!$current_artist) {
            return array('success' => false, 'message' => 'Artis tidak ditemukan');
        }
        
        // Check if artist already exists (excluding current artist)
        $query = "SELECT id FROM artists WHERE name = :name AND id != :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return array('success' => false, 'message' => 'Artis "' . $name . '" sudah ada');
        }
        
        // Handle profile picture - FIXED: menggunakan profile_picture bukan photo
        $profile_picture = $current_artist['profile_picture'];
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $photo = $_FILES['profile_picture'];
            
            if (!isAllowedImageFile($photo['name'])) {
                return array('success' => false, 'message' => 'Format file gambar tidak didukung');
            }
            
            // Remove old photo if exists and not default
            if ($profile_picture && $profile_picture != 'default-artist.png' && file_exists("../uploads/artists/" . $profile_picture)) {
                unlink("../uploads/artists/" . $profile_picture);
            }
            
            $profile_picture = uploadArtistPhoto($photo, $gd_available);
            if (!$profile_picture) {
                return array('success' => false, 'message' => 'Gagal mengupload foto profil');
            }
        }
        
        // Remove photo if checkbox is checked
        if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
            if ($profile_picture && $profile_picture != 'default-artist.png' && file_exists("../uploads/artists/" . $profile_picture)) {
                unlink("../uploads/artists/" . $profile_picture);
            }
            $profile_picture = 'default-artist.png';
        }
        
        // Update artist - FIXED: menggunakan profile_picture bukan photo
        $query = "UPDATE artists SET name = :name, profile_picture = :profile_picture, updated_at = NOW() WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":profile_picture", $profile_picture);
        $stmt->bindParam(":id", $id);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Artis "' . $name . '" berhasil diperbarui!');
        } else {
            return array('success' => false, 'message' => 'Gagal memperbarui artis');
        }
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}

function deleteArtist($conn, $id) {
    try {
        // Get artist data first
        $artist = getArtistById($conn, $id);
        if (!$artist) {
            return array('success' => false, 'message' => 'Artis tidak ditemukan');
        }
        
        // Check if artist has songs
        $query = "SELECT COUNT(*) as songs_count FROM songs WHERE artist_id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $songs_count = $stmt->fetch(PDO::FETCH_ASSOC)['songs_count'];
        
        if ($songs_count > 0) {
            return array('success' => false, 'message' => 'Tidak dapat menghapus artis yang memiliki lagu. Hapus lagu terlebih dahulu.');
        }
        
        // Check if artist has albums
        $query = "SELECT COUNT(*) as albums_count FROM albums WHERE artist_id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $albums_count = $stmt->fetch(PDO::FETCH_ASSOC)['albums_count'];
        
        if ($albums_count > 0) {
            return array('success' => false, 'message' => 'Tidak dapat menghapus artis yang memiliki album. Hapus album terlebih dahulu.');
        }
        
        // Delete artist
        $query = "DELETE FROM artists WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        
        if ($stmt->execute()) {
            // Delete photo if exists and not default - FIXED: menggunakan profile_picture
            if ($artist['profile_picture'] && $artist['profile_picture'] != 'default-artist.png' && 
                file_exists("../uploads/artists/" . $artist['profile_picture'])) {
                unlink("../uploads/artists/" . $artist['profile_picture']);
            }
            return array('success' => true, 'message' => 'Artis berhasil dihapus');
        } else {
            return array('success' => false, 'message' => 'Gagal menghapus artis');
        }
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}

function uploadArtistPhoto($file, $gd_available = false) {
    $upload_dir = "../uploads/artists/";
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'artist_' . uniqid() . '_' . time() . '.' . $file_extension;
    $target_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Only resize if GD is available
        if ($gd_available) {
            resizeImage($target_path, 300, 300);
        }
        return $filename;
    }
    return false;
}

function isAllowedImageFile($filename) {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($file_extension, $allowed_extensions);
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Artis - Admin <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/admin-main.css">
    <link rel="stylesheet" href="assets/css/admin-artists.css">
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
                    <h1>Kelola Artis</h1>
                    <p>Kelola data artis musik</p>
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
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type == 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="artists-container">
                    <!-- Add/Edit Artist Form -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-<?php echo $edit_artist ? 'edit' : ' '; ?>"></i>
                                <?php echo $edit_artist ? 'Edit Artis' : 'Tambah Artis Baru'; ?>
                            </h3>
                            <?php if ($edit_artist): ?>
                                <a href="artists.php" class="btn btn-outline">
                                    <i class="fas fa-plus"></i> Tambah Baru
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-content">
                            <form method="POST" action="" enctype="multipart/form-data" class="artist-form" id="artistForm">
                                <?php if ($edit_artist): ?>
                                    <input type="hidden" name="id" value="<?php echo $edit_artist['id']; ?>">
                                <?php endif; ?>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="name">Nama Artis *</label>
                                        <input type="text" id="name" name="name" required
                                               value="<?php echo $edit_artist ? htmlspecialchars($edit_artist['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''); ?>"
                                               placeholder="Masukkan nama artis">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="profile_picture">Foto Profil</label>
                                    <div class="file-upload" id="profilePictureUpload">
                                        <div class="upload-icon">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                        </div>
                                        <p>Klik untuk upload foto atau drag & drop</p>
                                        <input type="file" id="profile_picture" name="profile_picture" 
                                               accept=".jpg,.jpeg,.png,.webp">
                                        <div class="file-info">Belum ada file yang dipilih</div>
                                        <small class="form-help">Format: JPG, PNG, WEBP (maks. 5MB)</small>
                                        <?php if (!$gd_available): ?>
                                         <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($edit_artist && $edit_artist['profile_picture'] && $edit_artist['profile_picture'] != 'default-artist.png'): ?>
                                        <div class="current-image">
                                            <p><strong>Foto Saat Ini:</strong></p>
                                            <img src="../uploads/artists/<?php echo $edit_artist['profile_picture']; ?>" 
                                                 alt="Current Photo" class="cover-preview">
                                            <div class="remove-image">
                                                <label>
                                                    <input type="checkbox" name="remove_photo" value="1">
                                                    Hapus foto saat ini (gunakan default)
                                                </label>
                                            </div>
                                        </div>
                                    <?php elseif ($edit_artist): ?>
                                        <div class="current-image">
                                            <p><strong>Foto Saat Ini:</strong> Default Photo</p>
                                            <img src="../uploads/artists/default-artist.png" 
                                                 alt="Default Photo" class="cover-preview">
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="form-actions">
                                    <?php if ($edit_artist): ?>
                                        <button type="submit" name="edit_artist" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Artis
                                        </button>
                                        <a href="artists.php" class="btn btn-outline">Batal</a>
                                    <?php else: ?>
                                        <button type="submit" name="add_artist" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Tambah Artis
                                        </button>
                                        <button type="reset" class="btn btn-outline">Reset</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Artists List -->
                    <div class="artists-list">
                        <div class="card-header">
                            <h3><i class="fas fa-users"></i> Daftar Artis</h3>
                            <div class="total-artists">
                                Total: <strong><?php echo count($artists); ?></strong> Artis
                            </div>
                        </div>
                        <div class="card-content">
                            <?php if ($artists && count($artists) > 0): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Artis</th>
                                                <th>Lagu</th>
                                                <th>Tanggal</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($artists as $artist): ?>
                                                <tr>
                                                    <td>
                                                        <div class="artist-info">
                                                            <div class="artist-photo">
                                                                <?php if ($artist['profile_picture'] && $artist['profile_picture'] != 'default-artist.png'): ?>
                                                                    <img src="../uploads/artists/<?php echo $artist['profile_picture']; ?>" 
                                                                         alt="<?php echo htmlspecialchars($artist['name']); ?>"
                                                                         onerror="this.src='../uploads/artists/default-artist.png'">
                                                                <?php else: ?>
                                                                    <div class="no-photo">
                                                                        <i class="fas fa-user"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="artist-details">
                                                                <h4><?php echo htmlspecialchars($artist['name']); ?></h4>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge"><?php echo $artist['songs_count']; ?> lagu</span>
                                                    </td>
                                                    <td>
                                                        <?php echo date('d M Y', strtotime($artist['created_at'])); ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="artists.php?edit=<?php echo $artist['id']; ?>" 
                                                               class="btn btn-sm btn-outline" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button onclick="confirmDelete(<?php echo $artist['id']; ?>)" 
                                                                    class="btn btn-sm btn-danger" title="Hapus"
                                                                    <?php echo ($artist['songs_count'] > 0) ? 'disabled' : ''; ?>>
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
                                    <i class="fas fa-users fa-3x"></i>
                                    <h3>Belum Ada Artis</h3>
                                    <p>Tambahkan artis pertama Anda untuk memulai</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/admin-main.js"></script>
    <script src="assets/js/admin-artists.js"></script>
</body>
</html>