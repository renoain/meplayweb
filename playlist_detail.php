<?php
// playlist_detail.php
require_once 'config/constants.php';
require_once 'config/auth.php';
require_once 'config/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: playlists.php");
    exit();
}

$playlist_id = intval($_GET['id']);
$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get playlist info
$playlist_query = "SELECT * FROM playlists WHERE id = ? AND user_id = ?";
$playlist_stmt = $conn->prepare($playlist_query);
$playlist_stmt->execute([$playlist_id, $user_id]);
$playlist = $playlist_stmt->fetch(PDO::FETCH_ASSOC);

if (!$playlist) {
    header("Location: playlists.php");
    exit();
}

// Get songs in playlist
$songs_query = "SELECT s.*, a.name as artist_name, al.title as album_title, g.name as genre_name 
                FROM songs s 
                LEFT JOIN artists a ON s.artist_id = a.id 
                LEFT JOIN albums al ON s.album_id = al.id 
                LEFT JOIN genres g ON s.genre_id = g.id 
                WHERE s.id IN (SELECT song_id FROM playlist_songs WHERE playlist_id = ?) 
                AND s.is_active = 1 
                ORDER BY playlist_songs.added_at DESC";
$songs_stmt = $conn->prepare($songs_query);
$songs_stmt->execute([$playlist_id]);
$playlist_songs = $songs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's playlists for dropdown
$playlists_query = "SELECT id, title FROM playlists WHERE user_id = ? AND id != ?";
$playlists_stmt = $conn->prepare($playlists_query);
$playlists_stmt->execute([$user_id, $playlist_id]);
$user_playlists = $playlists_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($playlist['title']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/playlist_detail.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/player.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body data-theme="<?php echo getCurrentTheme(); ?>">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <div class="playlist-header">
                <div class="playlist-cover-large">
                    <?php if ($playlist['cover_image']): ?>
                        <img src="uploads/playlists/<?php echo $playlist['cover_image']; ?>" alt="<?php echo htmlspecialchars($playlist['title']); ?>">
                    <?php else: ?>
                        <div class="default-cover-large">
                            <i class="fas fa-music"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="playlist-info-large">
                    <h1><?php echo htmlspecialchars($playlist['title']); ?></h1>
                    <?php if ($playlist['description']): ?>
                    <p class="playlist-description"><?php echo htmlspecialchars($playlist['description']); ?></p>
                    <?php endif; ?>
                    <div class="playlist-meta">
                        <span><?php echo count($playlist_songs); ?> songs</span>
                        <span>•</span>
                        <span>Created <?php echo date('F j, Y', strtotime($playlist['created_at'])); ?></span>
                    </div>
                    <div class="playlist-actions">
                        <button class="play-all-btn" id="playPlaylist">
                            <i class="fas fa-play"></i>
                            Play
                        </button>
                        <button class="more-btn" id="playlistMoreBtn">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                        <div class="dropdown-menu">
                            <button class="dropdown-item" id="editPlaylist">
                                <i class="fas fa-edit"></i> Edit Playlist
                            </button>
                            <button class="dropdown-item" id="deletePlaylist">
                                <i class="fas fa-trash"></i> Delete Playlist
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (count($playlist_songs) > 0): ?>
            <div class="songs-list">
                <?php foreach ($playlist_songs as $song): ?>
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
                            <button class="dropdown-item like-song" data-song-id="<?php echo $song['id']; ?>">
                                <i class="far fa-heart"></i> Like
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
                                        <?php foreach ($user_playlists as $user_playlist): ?>
                                            <button class="dropdown-item add-to-playlist" 
                                                    data-song-id="<?php echo $song['id']; ?>" 
                                                    data-playlist-id="<?php echo $user_playlist['id']; ?>">
                                                <?php echo htmlspecialchars($user_playlist['title']); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <button class="dropdown-item disabled">No other playlists</button>
                                    <?php endif; ?>
                                    <button class="dropdown-item create-playlist" data-song-id="<?php echo $song['id']; ?>">
                                        <i class="fas fa-plus-circle"></i> Create New Playlist
                                    </button>
                                </div>
                            </div>
                            <button class="dropdown-item remove-from-playlist" data-song-id="<?php echo $song['id']; ?>" data-playlist-id="<?php echo $playlist_id; ?>">
                                <i class="fas fa-times"></i> Remove from Playlist
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-songs">
                <div class="no-songs-content">
                    <i class="fas fa-music fa-4x"></i>
                    <h2>No songs in this playlist</h2>
                    <p>Add some songs to get started</p>
                    <a href="search.php" class="btn-primary">Browse Songs</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/player.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/player.js"></script>
    <script>
    // Play all playlist songs
    document.getElementById('playPlaylist')?.addEventListener('click', function() {
        const songIds = Array.from(document.querySelectorAll('.song-item[data-song-id]'))
            .map(item => item.getAttribute('data-song-id'));
        
        if (songIds.length > 0 && window.musicPlayer) {
            // Clear current queue and add all playlist songs
            window.musicPlayer.clearQueue();
            
            // Add first song and play
            playSongFromCard(songIds[0]);
            
            // Add remaining songs to queue
            songIds.slice(1).forEach(songId => {
                addSongToQueue(songId);
            });
            
            showNotification('Playing playlist');
        }
    });

    // Remove song from playlist
    document.querySelectorAll('.remove-from-playlist').forEach(button => {
        button.addEventListener('click', async function() {
            const songId = this.getAttribute('data-song-id');
            const playlistId = this.getAttribute('data-playlist-id');
            
            try {
                const response = await fetch('api/playlists.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'remove_song',
                        playlist_id: playlistId,
                        song_id: songId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Song removed from playlist');
                    // Remove the song item from UI
                    this.closest('.song-item').remove();
                    
                    // Update song count
                    const songCount = document.querySelectorAll('.song-item').length;
                    document.querySelector('.playlist-meta span:first-child').textContent = songCount + ' songs';
                } else {
                    showNotification(data.message || 'Error removing song', 'error');
                }
            } catch (error) {
                console.error('Remove from playlist error:', error);
                showNotification('Error removing song', 'error');
            }
        });
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