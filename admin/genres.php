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

// Handle delete action FIRST (before form processing)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $result = deleteGenre($conn, $_GET['id']);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'error';
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_genre'])) {
        $result = addGenre($conn);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
        
        // Clear form after successful add
        if ($result['success']) {
            $_POST = array(); // Clear POST data
        }
    } elseif (isset($_POST['edit_genre'])) {
        $result = updateGenre($conn);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
        
        // Redirect to clear form after successful edit
        if ($result['success']) {
            header("Location: genres.php?message=" . urlencode($result['message']) . "&type=success");
            exit();
        }
    }
}

// Get all genres with songs count
try {
    $query = "SELECT g.*, 
              COUNT(s.id) as songs_count
              FROM genres g 
              LEFT JOIN songs s ON g.id = s.genre_id 
              GROUP BY g.id 
              ORDER BY g.name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    $message = "Database error: " . $exception->getMessage();
    $message_type = 'error';
    $genres = [];
}

// Handle message from redirect
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'];
}

// Get genre for editing - MUST be after form processing
$edit_genre = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_genre = getGenreById($conn, $_GET['edit']);
    if (!$edit_genre) {
        $message = "Genre tidak ditemukan";
        $message_type = 'error';
    }
}

function getGenreById($conn, $id) {
    try {
        $query = "SELECT * FROM genres WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $exception) {
        error_log("Error getting genre: " . $exception->getMessage());
        return null;
    }
}

function addGenre($conn) {
    // Get form data
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $color = $_POST['color'] ?: '#667eea'; // Default color
    
    // Basic validation
    if (empty($name)) {
        return array('success' => false, 'message' => 'Nama genre wajib diisi');
    }
    
    // Validate color format
    if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
        return array('success' => false, 'message' => 'Format warna tidak valid. Gunakan format hex: #RRGGBB');
    }
    
    try {
        // Check if genre already exists (case insensitive)
        $query = "SELECT id FROM genres WHERE LOWER(name) = LOWER(:name)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return array('success' => false, 'message' => 'Genre dengan nama tersebut sudah ada');
        }
        
        // Insert genre
        $query = "INSERT INTO genres (name, description, color) VALUES (:name, :description, :color)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":color", $color);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Genre "' . $name . '" berhasil ditambahkan!');
        } else {
            return array('success' => false, 'message' => 'Gagal menambahkan genre ke database');
        }
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}

function updateGenre($conn) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $color = $_POST['color'] ?: '#667eea';
    
    if (empty($name)) {
        return array('success' => false, 'message' => 'Nama genre wajib diisi');
    }
    
    // Validate color format
    if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
        return array('success' => false, 'message' => 'Format warna tidak valid. Gunakan format hex: #RRGGBB');
    }
    
    try {
        // Check if genre exists
        $current_genre = getGenreById($conn, $id);
        if (!$current_genre) {
            return array('success' => false, 'message' => 'Genre tidak ditemukan');
        }
        
        // Check if genre already exists (excluding current genre, case insensitive)
        $query = "SELECT id FROM genres WHERE LOWER(name) = LOWER(:name) AND id != :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return array('success' => false, 'message' => 'Genre dengan nama tersebut sudah ada');
        }
        
        // Update genre
        $query = "UPDATE genres SET name = :name, description = :description, color = :color WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":color", $color);
        $stmt->bindParam(":id", $id);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Genre "' . $name . '" berhasil diperbarui!');
        } else {
            return array('success' => false, 'message' => 'Gagal memperbarui genre');
        }
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}

function deleteGenre($conn, $id) {
    try {
        // Get genre data first
        $genre = getGenreById($conn, $id);
        if (!$genre) {
            return array('success' => false, 'message' => 'Genre tidak ditemukan');
        }
        
        // Check if genre has songs
        $query = "SELECT COUNT(*) as songs_count FROM songs WHERE genre_id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $songs_count = $stmt->fetch(PDO::FETCH_ASSOC)['songs_count'];
        
        if ($songs_count > 0) {
            return array('success' => false, 'message' => 'Tidak dapat menghapus genre yang memiliki lagu. Hapus atau ubah genre lagu terlebih dahulu.');
        }
        
        // Delete genre
        $query = "DELETE FROM genres WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Genre berhasil dihapus');
        } else {
            return array('success' => false, 'message' => 'Gagal menghapus genre');
        }
        
    } catch(PDOException $exception) {
        return array('success' => false, 'message' => 'Database error: ' . $exception->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Genre - Admin <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/admin-main.css">
    <link rel="stylesheet" href="assets/css/admin-genres.css">
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
                    <h1>Kelola Genre</h1>
                    <p>Kelola genre musik</p>
            </header>

            <main class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <i class="fas fa-<?php echo $message_type == 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="genres-container">
                    <!-- Add/Edit Genre Form -->
                    <div class="form-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-<?php echo $edit_genre ? 'edit' : 'plus'; ?>"></i>
                                <?php echo $edit_genre ? 'Edit Genre' : 'Tambah Genre Baru'; ?>
                            </h3>
                            <?php if ($edit_genre): ?>
                                <a href="genres.php" class="btn btn-outline">
                                    <i class="fas fa-plus"></i> Tambah Baru
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-content">
                            <form method="POST" action="" class="genre-form" id="genreForm">
                                <?php if ($edit_genre): ?>
                                    <input type="hidden" name="id" value="<?php echo $edit_genre['id']; ?>">
                                <?php endif; ?>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="name">Nama Genre *</label>
                                        <input type="text" id="name" name="name" required
                                               value="<?php echo $edit_genre ? htmlspecialchars($edit_genre['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''); ?>"
                                               placeholder="Masukkan nama genre">
                                    </div>

                                    <div class="form-group">
                                        <label for="color">Warna</label>
                                        <div class="color-picker-container">
                                            <input type="color" id="color" name="color" 
                                                   value="<?php echo $edit_genre ? htmlspecialchars($edit_genre['color']) : (isset($_POST['color']) ? htmlspecialchars($_POST['color']) : '#667eea'); ?>"
                                                   class="color-input">
                                            <input type="text" id="color_hex" 
                                                   value="<?php echo $edit_genre ? htmlspecialchars($edit_genre['color']) : (isset($_POST['color']) ? htmlspecialchars($_POST['color']) : '#667eea'); ?>"
                                                   class="color-hex" placeholder="#667eea" maxlength="7">
                                        </div>
                                        <small class="form-help">Pilih warna untuk genre ini</small>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="description">Deskripsi</label>
                                    <textarea id="description" name="description" rows="4" 
                                              placeholder="Masukkan deskripsi genre..."><?php echo $edit_genre ? htmlspecialchars($edit_genre['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''); ?></textarea>
                                </div>

                                <div class="form-actions">
                                    <?php if ($edit_genre): ?>
                                        <button type="submit" name="edit_genre" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Genre
                                        </button>
                                        <a href="genres.php" class="btn btn-outline">Batal</a>
                                    <?php else: ?>
                                        <button type="submit" name="add_genre" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Tambah Genre
                                        </button>
                                        <button type="reset" class="btn btn-outline">Reset</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Genres List -->
                    <div class="genres-list">
                        <div class="card-header">
                            <h3><i class="fas fa-tags"></i> Daftar Genre</h3>
                            <div class="total-genres">
                                Total: <strong><?php echo count($genres); ?></strong> Genre
                            </div>
                        </div>
                        <div class="card-content">
                            <?php if ($genres && count($genres) > 0): ?>
                                <div class="genres-grid">
                                    <?php foreach ($genres as $genre): ?>
                                        <div class="genre-card" style="border-left: 4px solid <?php echo htmlspecialchars($genre['color']); ?>">
                                            <div class="genre-header">
                                                <div class="genre-color" style="background: <?php echo htmlspecialchars($genre['color']); ?>"></div>
                                                <h4><?php echo htmlspecialchars($genre['name']); ?></h4>
                                                <div class="genre-actions">
                                                    <a href="genres.php?edit=<?php echo $genre['id']; ?>" 
                                                       class="btn btn-sm btn-outline" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button onclick="confirmDelete(<?php echo $genre['id']; ?>)" 
                                                            class="btn btn-sm btn-danger" title="Hapus"
                                                            <?php echo $genre['songs_count'] > 0 ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="genre-body">
                                                <?php if ($genre['description']): ?>
                                                    <p class="genre-description"><?php echo htmlspecialchars($genre['description']); ?></p>
                                                <?php else: ?>
                                                    <p class="no-description">Tidak ada deskripsi</p>
                                                <?php endif; ?>
                                                <div class="genre-stats">
                                                    <span class="songs-count">
                                                        <i class="fas fa-music"></i>
                                                        <?php echo $genre['songs_count']; ?> lagu
                                                    </span>
                                                    <span class="created-date">
                                                        <i class="fas fa-calendar"></i>
                                                        <?php echo date('d M Y', strtotime($genre['created_at'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-tags fa-3x"></i>
                                    <h3>Belum Ada Genre</h3>
                                    <p>Tambahkan genre pertama Anda untuk memulai</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
    function confirmDelete(genreId) {
        if (confirm('Apakah Anda yakin ingin menghapus genre ini?')) {
            window.location.href = 'genres.php?action=delete&id=' + genreId;
        }
    }
    
    // Form validation
    document.getElementById('genreForm')?.addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        const color = document.getElementById('color_hex').value;
        
        if (!name) {
            e.preventDefault();
            alert('Nama genre wajib diisi!');
            return false;
        }
        
        // Validate color format
        const colorRegex = /^#[0-9A-F]{6}$/i;
        if (color && !colorRegex.test(color)) {
            e.preventDefault();
            alert('Format warna tidak valid. Gunakan format hex: #RRGGBB');
            return false;
        }
        
        return true;
    });

    // Color picker functionality
    document.addEventListener('DOMContentLoaded', function() {
        const colorInput = document.getElementById('color');
        const colorHex = document.getElementById('color_hex');
        
        if (colorInput && colorHex) {
            // Sync color picker with hex input
            colorInput.addEventListener('input', function() {
                colorHex.value = this.value;
            });
            
            // Sync hex input with color picker
            colorHex.addEventListener('input', function() {
                const value = this.value;
                if (value.startsWith('#') && value.length === 7) {
                    colorInput.value = value;
                }
            });
            
            // Validate hex input on blur
            colorHex.addEventListener('blur', function() {
                const value = this.value;
                const colorRegex = /^#[0-9A-F]{6}$/i;
                
                if (value && !colorRegex.test(value)) {
                    this.setCustomValidity('Format warna tidak valid. Gunakan: #RRGGBB');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    });

    // Auto-capitalize genre name
    document.getElementById('name')?.addEventListener('blur', function() {
        this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1);
    });
    </script>
</body>
</html>