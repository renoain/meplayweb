<?php
// playlists.php
require_once 'config/constants.php';
require_once 'config/auth.php';
require_once 'config/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get user's playlists
$query = "SELECT * FROM playlists WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$user_id]);
$playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playlists - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/playlists.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/player.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body data-theme="<?php echo getCurrentTheme(); ?>">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <div class="page-header">
                <h1>My Playlists</h1>
                <button class="btn-primary" id="createPlaylistBtn">
                    <i class="fas fa-plus"></i>
                    Create Playlist
                </button>
            </div>

            <?php if (count($playlists) > 0): ?>
            <div class="playlists-grid">
                <?php foreach ($playlists as $playlist): ?>
                 <a href="playlist_detail.php?id=<?php echo $playlist['id']; ?>" class="playlist-card" data-playlist-id="<?php echo $playlist['id']; ?>">
                    <div class="playlist-cover">
                        <?php if ($playlist['cover_image']): ?>
                            <img src="uploads/playlists/<?php echo $playlist['cover_image']; ?>" alt="<?php echo htmlspecialchars($playlist['title']); ?>">
                        <?php else: ?>
                            <div class="default-cover">
                                <i class="fas fa-music"></i>
                            </div>
                        <?php endif; ?>
                        <button class="play-btn">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                    <div class="playlist-info">
                        <h3><?php echo htmlspecialchars($playlist['title']); ?></h3>
                        <?php if ($playlist['description']): ?>
                        <p><?php echo htmlspecialchars($playlist['description']); ?></p>
                        <?php endif; ?>
                        <div class="playlist-meta">
                            <span><?php echo date('M j, Y', strtotime($playlist['created_at'])); ?></span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-playlists">
                <div class="no-playlists-content">
                    <i class="fas fa-list fa-4x"></i>
                    <h2>No playlists yet</h2>
                    <p>Create your first playlist to get started</p>
                    <button class="btn-primary" id="emptyCreatePlaylistBtn">
                        <i class="fas fa-plus"></i>
                        Create Playlist
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/player.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/player.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const createPlaylistBtn = document.getElementById('createPlaylistBtn');
        const emptyCreateBtn = document.getElementById('emptyCreatePlaylistBtn');
        const sidebarCreateBtn = document.getElementById('sidebarCreatePlaylist');
        
        function openCreatePlaylistModal() {
            if (sidebarCreateBtn) {
                sidebarCreateBtn.click();
            }
        }
        
        if (createPlaylistBtn) {
            createPlaylistBtn.addEventListener('click', openCreatePlaylistModal);
        }
        
        if (emptyCreateBtn) {
            emptyCreateBtn.addEventListener('click', openCreatePlaylistModal);
        }
    });
    </script>
</body>
</html>