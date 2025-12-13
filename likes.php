<?php
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

// Get liked songs with like status
$query = "SELECT s.*, 
          a.name as artist_name, 
          al.title as album_title, 
          g.name as genre_name,
          1 as is_liked  
          FROM songs s 
          LEFT JOIN artists a ON s.artist_id = a.id 
          LEFT JOIN albums al ON s.album_id = al.id 
          LEFT JOIN genres g ON s.genre_id = g.id
          WHERE s.id IN (SELECT song_id FROM user_likes WHERE user_id = ?) 
          AND s.is_active = 1 
          ORDER BY s.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$user_id]);
$liked_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's playlists for dropdown
$playlists_query = "SELECT id, title FROM playlists WHERE user_id = ? ORDER BY title ASC";
$playlists_stmt = $conn->prepare($playlists_query);
$playlists_stmt->execute([$user_id]);
$user_playlists = $playlists_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liked Songs - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/likes.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/player.css">
    <link rel="stylesheet" href="assets/css/header-search.css">

    <style>
     .like-song.liked {
        color: var(--danger-color) !important;
    }
    
    .like-song.liked i {
        color: var(--danger-color) !important;
    }
    
    .like-song.liked:hover {
        background-color: rgba(239, 68, 68, 0.1) !important;
    }
    </style>
</head>
<body data-theme="<?php echo getCurrentTheme(); ?>">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <div class="page-header">
                <div class="header-content">
                    <div class="header-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="header-info">
                        <h1>Liked Songs</h1>
                        <p><?php echo count($liked_songs); ?> liked songs</p>
                    </div>
                </div>
                
                <?php if (count($liked_songs) > 0): ?>
                <div class="header-actions">
                    <button class="play-all-btn" id="playAllLiked">
                        <i class="fas fa-play"></i>
                        Play All
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if (count($liked_songs) > 0): ?>
            <div class="songs-list" id="likedSongsList">
                <?php foreach ($liked_songs as $index => $song): ?>
                <div class="song-item" data-song-id="<?php echo $song['id']; ?>">
                    <div class="song-cover">
                        <img src="<?php echo getCoverPath($song['cover_image'], 'song'); ?>" 
                             alt="<?php echo htmlspecialchars($song['title']); ?>"
                             onerror="this.src='assets/images/covers/default-cover.png'">
                        <button class="play-btn" data-song-id="<?php echo $song['id']; ?>">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                    
                    <div class="song-info">
                        <h4><?php echo htmlspecialchars($song['title']); ?></h4>
                        <p><?php echo htmlspecialchars($song['artist_name']); ?></p>
                    </div>
                    
                    <div class="song-duration"><?php echo formatDuration($song['duration']); ?></div>
                    
                    <div class="song-actions">
                        <button class="more-btn" data-song-id="<?php echo $song['id']; ?>">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        
                        <div class="song-dropdown" id="dropdown-<?php echo $song['id']; ?>">
                            <button class="dropdown-item like-song liked text-danger" 
                                data-song-id="<?php echo $song['id']; ?>">
                                <i class="fas fa-heart"></i> Unlike
                            </button>
                            <button class="dropdown-item add-to-queue" data-song-id="<?php echo $song['id']; ?>">
                                <i class="fas fa-list"></i> Add to Queue
                            </button>
                            <div class="dropdown-submenu">
                                <button class="dropdown-item submenu-trigger">
                                    <i class="fas fa-plus"></i> Add to Playlist
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                                <div class="submenu">
                                    <?php if ($user_playlists): ?>
                                        <?php foreach ($user_playlists as $playlist): ?>
                                            <button class="dropdown-item add-to-playlist" 
                                                    data-song-id="<?php echo $song['id']; ?>" 
                                                    data-playlist-id="<?php echo $playlist['id']; ?>">
                                                <?php echo htmlspecialchars($playlist['title']); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <button class="dropdown-item disabled">No playlists</button>
                                    <?php endif; ?>
                                    <button class="dropdown-item create-playlist" data-song-id="<?php echo $song['id']; ?>">
                                        <i class="fas fa-plus-circle"></i> Create New Playlist
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-songs">
                <div class="no-songs-content">
                    <i class="fas fa-heart fa-4x"></i>
                    <h2>No liked songs yet</h2>
                    <p>Start liking songs to see them here</p>
                    <a href="index.php" class="btn-primary">Discover Music</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/player.php'; ?>
    
    <!-- Create Playlist Modal -->
    <div id="createPlaylistModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Playlist</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createPlaylistForm">
                    <div class="form-group">
                        <label for="playlistTitle">Playlist Title *</label>
                        <input type="text" id="playlistTitle" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="playlistDescription">Description (Optional)</label>
                        <textarea id="playlistDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <input type="hidden" id="songIdForPlaylist" name="song_id">
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary close-modal">Cancel</button>
                        <button type="submit" class="btn-primary">Create Playlist</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/player.js"></script>
    <script src="assets/js/header-search.js"></script>
    <script src="assets/js/likes.js"></script>

</body>
</html>