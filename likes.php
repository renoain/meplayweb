<?php
// likes.php
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

// Get liked songs - FIXED SQL QUERY
$query = "SELECT s.*, a.name as artist_name, al.title as album_title, g.name as genre_name 
          FROM songs s 
          LEFT JOIN artists a ON s.artist_id = a.id 
          LEFT JOIN albums al ON s.album_id = al.id 
          LEFT JOIN genres g ON s.genre_id = g.id 
          WHERE s.id IN (SELECT song_id FROM user_likes WHERE user_id = ?) 
          AND s.is_active = 1 
          ORDER BY s.created_at DESC"; // Changed from user_likes.created_at to s.created_at
$stmt = $conn->prepare($query);
$stmt->execute([$user_id]);
$liked_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's playlists for dropdown
$playlists_query = "SELECT id, title FROM playlists WHERE user_id = ?";
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
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/likes.css">
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
            <div class="songs-list">
                <?php foreach ($liked_songs as $song): ?>
                <div class="song-item" data-song-id="<?php echo $song['id']; ?>">
                    <div class="song-cover">
                        <img src="<?php echo getCoverPath($song['cover_image'], 'song'); ?>" 
                             alt="<?php echo htmlspecialchars($song['title']); ?>">
                        <button class="play-btn">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                    <div class="song-info">
                        <h4><?php echo htmlspecialchars($song['title']); ?></h4>
                        <p><?php echo htmlspecialchars($song['artist_name']); ?></p>
                        <div class="song-meta">
                            <span class="album"><?php echo htmlspecialchars($song['album_title'] ?? 'Single'); ?></span>
                            <span class="genre"><?php echo htmlspecialchars($song['genre_name'] ?? 'Unknown'); ?></span>
                        </div>
                    </div>
                    <div class="song-duration"><?php echo formatDuration($song['duration']); ?></div>
                    <div class="song-actions">
                        <button class="more-btn">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <button class="dropdown-item like-song liked" data-song-id="<?php echo $song['id']; ?>">
                                <i class="fas fa-heart"></i> Unlike
                            </button>
                            <button class="dropdown-item add-to-queue" data-song-id="<?php echo $song['id']; ?>">
                                <i class="fas fa-list"></i> Add to Queue
                            </button>
                            <div class="dropdown-submenu">
                                <button class="dropdown-item">
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
                        <label for="playlistTitle">Playlist Title</label>
                        <input type="text" id="playlistTitle" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="playlistDescription">Description (Optional)</label>
                        <textarea id="playlistDescription" name="description"></textarea>
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
    <script>
    // Play all liked songs
    document.getElementById('playAllLiked')?.addEventListener('click', function() {
        const songIds = Array.from(document.querySelectorAll('.song-item[data-song-id]'))
            .map(item => item.getAttribute('data-song-id'));
        
        if (songIds.length > 0 && window.musicPlayer) {
            // Clear current queue and add all liked songs
            window.musicPlayer.clearQueue();
            
            // Add first song and play
            playSongFromCard(songIds[0]);
            
            // Add remaining songs to queue
            songIds.slice(1).forEach(songId => {
                addSongToQueue(songId);
            });
            
            showNotification('Playing all liked songs');
        }
    });

    async function playSongFromCard(songId) {
        try {
            const response = await fetch(`api/songs.php?id=${songId}`);
            const data = await response.json();
            
            if (data.success && window.musicPlayer) {
                window.musicPlayer.playSong(data.song);
            }
        } catch (error) {
            console.error('Error playing song:', error);
            showNotification('Error playing song', 'error');
        }
    }

    async function addSongToQueue(songId) {
        try {
            const response = await fetch(`api/songs.php?id=${songId}`);
            const data = await response.json();
            
            if (data.success && window.musicPlayer) {
                window.musicPlayer.addToQueue(data.song);
            }
        } catch (error) {
            console.error('Error adding to queue:', error);
        }
    }
    </script>
</body>
</html>