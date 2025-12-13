<?php
require_once '../config/constants.php';
require_once '../config/auth.php';

$auth = new Auth();
$auth->requireAdmin();

require_once '../config/database.php';
$database = new Database();
$conn = $database->getConnection();

try {
    // Total statistics
    $stats = array();
    
    // Total songs
    $query = "SELECT COUNT(*) as total FROM songs";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_songs'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total artists
    $query = "SELECT COUNT(*) as total FROM artists";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_artists'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total albums
    $query = "SELECT COUNT(*) as total FROM albums";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_albums'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total genres
    $query = "SELECT COUNT(*) as total FROM genres";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_genres'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Recent songs
    $query = "SELECT s.*, a.name as artist_name, al.title as album_title 
              FROM songs s 
              LEFT JOIN artists a ON s.artist_id = a.id 
              LEFT JOIN albums al ON s.album_id = al.id 
              ORDER BY s.created_at DESC 
              LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $recent_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent artists
    $query = "SELECT id, name, profile_picture, created_at FROM artists ORDER BY created_at DESC LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $recent_artists = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $exception) {
    $error = "Database error: " . $exception->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/admin-main.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Dashboard</h1>
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
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-music"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_songs']); ?></h3>
                            <p>Total Lagu</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_artists']); ?></h3>
                            <p>Total Artis</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-compact-disc"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_albums']); ?></h3>
                            <p>Total Album</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_genres']); ?></h3>
                            <p>Total Genre</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>  Quick Actions</h3>
                    </div>
                    <div class="card-content">
                        <div class="quick-actions">
                            <a href="songs.php" class="action-btn">
                                <i class="fas fa-plus"></i>
                                <span> Lagu</span>
                            </a>
                            <a href="artists.php" class="action-btn">
                                <i class="fas fa-user-plus"></i>
                                <span> Artis</span>
                            </a>
                            <a href="albums.php" class="action-btn">
                                <i class="fas fa-compact-disc"></i>
                                <span> Album</span>
                            </a>
                            <a href="genres.php" class="action-btn">
                                <i class="fas fa-tag"></i>
                                <span> Genre</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Content -->
                <div class="content-grid">
                    <!-- Recent Songs -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3><i class="fas fa-music"></i> Lagu Terbaru</h3>
                            <a href="songs.php" class="btn-link">Lihat Semua</a>
                        </div>
                        <div class="card-content">
                            <?php if ($recent_songs): ?>
                                <div class="recent-list">
                                    <?php foreach ($recent_songs as $song): ?>
                                        <div class="recent-item">
                                            <div class="item-info">
                                                <h4><?php echo htmlspecialchars($song['title']); ?></h4>
                                                <p><?php echo htmlspecialchars($song['artist_name']); ?></p>
                                            </div>
                                            <div class="item-meta">
                                                <span><?php echo date('d M Y', strtotime($song['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-music fa-2x"></i>
                                    <p>Belum ada lagu</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Artists -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3><i class="fas fa-user"></i> Artis Terbaru</h3>
                            <a href="artists.php" class="btn-link">Lihat Semua</a>
                        </div>
                        <div class="card-content">
                            <?php if ($recent_artists): ?>
                                <div class="recent-list">
                                    <?php foreach ($recent_artists as $artist): ?>
                                        <div class="recent-item">
                                            <div class="item-avatar">
                                                <?php if ($artist['profile_picture'] && $artist['profile_picture'] != 'default-artist.png'): ?>
                                                    <img src="../uploads/artists/<?php echo $artist['profile_picture']; ?>" 
                                                         alt="<?php echo htmlspecialchars($artist['name']); ?>">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="item-info">
                                                <h4><?php echo htmlspecialchars($artist['name']); ?></h4>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-user fa-2x"></i>
                                    <p>Belum ada artis</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="assets/js/admin-main.js"></script>
</body>
</html>