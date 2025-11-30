<?php
// index.php
require_once 'config/constants.php';
require_once 'config/auth.php';
require_once 'config/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get recent songs, popular songs, etc.
$database = new Database();
$conn = $database->getConnection();

// Get recently added songs
$query = "SELECT s.*, a.name as artist_name, al.title as album_title, g.name as genre_name 
          FROM songs s 
          LEFT JOIN artists a ON s.artist_id = a.id 
          LEFT JOIN albums al ON s.album_id = al.id 
          LEFT JOIN genres g ON s.genre_id = g.id 
          WHERE s.is_active = 1 
          ORDER BY s.created_at DESC 
          LIMIT 10";
$recent_songs_stmt = $conn->prepare($query);
$recent_songs_stmt->execute();

// Get popular songs
$query = "SELECT s.*, a.name as artist_name, al.title as album_title, g.name as genre_name 
          FROM songs s 
          LEFT JOIN artists a ON s.artist_id = a.id 
          LEFT JOIN albums al ON s.album_id = al.id 
          LEFT JOIN genres g ON s.genre_id = g.id 
          WHERE s.is_active = 1 
          ORDER BY s.play_count DESC 
          LIMIT 10";
$popular_songs_stmt = $conn->prepare($query);
$popular_songs_stmt->execute();

// Get user's playlists for dropdown
$user_id = $_SESSION['user_id'];
$query = "SELECT id, title FROM playlists WHERE user_id = ?";
$playlists_stmt = $conn->prepare($query);
$playlists_stmt->execute([$user_id]);
$user_playlists = $playlists_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check liked status for songs
$liked_songs_query = "SELECT song_id FROM user_likes WHERE user_id = ?";
$liked_stmt = $conn->prepare($liked_songs_query);
$liked_stmt->execute([$user_id]);
$liked_songs = $liked_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/player.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body data-theme="<?php echo getCurrentTheme(); ?>">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <div class="welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                <p>Discover new music and enjoy your favorites</p>
            </div>

            <section class="section">
                <h2>Recently Added</h2>
                <div class="songs-grid">
                    <?php while($song = $recent_songs_stmt->fetch(PDO::FETCH_ASSOC)): 
                        $is_liked = in_array($song['id'], $liked_songs);
                    ?>
                    <div class="song-card" data-song-id="<?php echo $song['id']; ?>">
                        <div class="song-cover">
                            <img src="<?php echo getCoverPath($song['cover_image'], 'song'); ?>" alt="<?php echo htmlspecialchars($song['title']); ?>">
                            <button class="play-btn">
                                <i class="fas fa-play"></i>
                            </button>
                        </div>
                        <div class="song-info">
                            <h3><?php echo htmlspecialchars($song['title']); ?></h3>
                            <p><?php echo htmlspecialchars($song['artist_name']); ?></p>
                        </div>
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
                    <?php endwhile; ?>
                </div>
            </section>

            <section class="section">
                <h2>Popular Songs</h2>
                <div class="songs-list">
                    <?php while($song = $popular_songs_stmt->fetch(PDO::FETCH_ASSOC)): 
                        $is_liked = in_array($song['id'], $liked_songs);
                    ?>
                    <div class="song-item" data-song-id="<?php echo $song['id']; ?>">
                        <div class="song-number"><?php echo $song['play_count']; ?> plays</div>
                        <div class="song-info">
                            <h4><?php echo htmlspecialchars($song['title']); ?></h4>
                            <p><?php echo htmlspecialchars($song['artist_name']); ?></p>
                        </div>
                        <div class="song-duration"><?php echo formatDuration($song['duration']); ?></div>
                        <div class="song-actions">
                            <button class="more-btn">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu">
                                <button class="dropdown-item like-song <?php echo $is_liked ? 'liked' : ''; ?>" 
                                        data-song-id="<?php echo $song['id']; ?>">
                                    <i class="<?php echo $is_liked ? 'fas' : 'far'; ?> fa-heart"></i> 
                                    <?php echo $is_liked ? 'Unlike' : 'Like'; ?>
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
                    <?php endwhile; ?>
                </div>
            </section>
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
    <script src="assets/js/likes.js"></script>
</body>
</html>