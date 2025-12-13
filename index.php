<?php
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
$user_id = $_SESSION['user_id'];

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

// Get recent albums
$query = "SELECT al.*, a.name as artist_name, 
          COUNT(s.id) as song_count
          FROM albums al
          LEFT JOIN artists a ON al.artist_id = a.id
          LEFT JOIN songs s ON s.album_id = al.id
          WHERE al.is_active = 1
          GROUP BY al.id
          ORDER BY al.created_at DESC
          LIMIT 6";
$recent_albums_stmt = $conn->prepare($query);
$recent_albums_stmt->execute();

// Get user's playlists for dropdown
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
    <link rel="stylesheet" href="assets/css/header-search.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body data-theme="<?php echo getCurrentTheme(); ?>">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">

            <!-- Recently Added Section -->
            <section class="section">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Recently Added</h2>
                </div>
                <div class="songs-grid">
                    <?php while($song = $recent_songs_stmt->fetch(PDO::FETCH_ASSOC)): 
                        $is_liked = in_array($song['id'], $liked_songs);
                    ?>
                    <div class="song-card" data-song-id="<?php echo $song['id']; ?>">
                        <div class="song-cover">
                            <img src="<?php echo getCoverPath($song['cover_image'], 'song'); ?>" 
                                 alt="<?php echo htmlspecialchars($song['title']); ?>"
                                 loading="lazy"
                                 onerror="this.src='assets/images/covers/default-cover.png'">
                            <div class="song-overlay">
                                <button class="play-btn" data-song-id="<?php echo $song['id']; ?>">
                                    <i class="fas fa-play"></i>
                                </button>
                            </div>
                        </div>
                        <div class="song-info">
                            <h3 title="<?php echo htmlspecialchars($song['title']); ?>">
                                <?php echo htmlspecialchars($song['title']); ?>
                            </h3>
                            <p title="<?php echo htmlspecialchars($song['artist_name']); ?>">
                                <?php echo htmlspecialchars($song['artist_name']); ?>
                            </p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </section>

            <!-- Popular Songs Section -->
            <section class="section">
                <div class="section-header">
                    <h2><i class="fas fa-fire"></i> Popular Songs</h2>
                </div>
                <div class="songs-list">
                    <?php $counter = 1; ?>
                    <?php while($song = $popular_songs_stmt->fetch(PDO::FETCH_ASSOC)): 
                        $is_liked = in_array($song['id'], $liked_songs);
                    ?>
                    <div class="song-item" data-song-id="<?php echo $song['id']; ?>" data-index="<?php echo $counter - 1; ?>">
                        <div class="song-number">
                            <span class="song-rank"><?php echo $counter++; ?></span>
                        </div>
                        <div class="song-cover-small">
                            <img src="<?php echo getCoverPath($song['cover_image'], 'song'); ?>" 
                                 alt="<?php echo htmlspecialchars($song['title']); ?>"
                                 loading="lazy"
                                 onerror="this.src='assets/images/covers/default-cover.png'">
                        </div>
                        <div class="song-info">
                            <h4><?php echo htmlspecialchars($song['title']); ?></h4>
                            <p><?php echo htmlspecialchars($song['artist_name']); ?></p>
                        </div>
                        <div class="song-meta">
                            <span class="play-count"><i class="fas fa-play"></i> <?php echo $song['play_count']; ?></span>
                            <span class="song-duration"><?php echo formatDuration($song['duration']); ?></span>
                        </div>
                        <div class="song-actions">
                            <button class="like-btn <?php echo $is_liked ? 'liked' : ''; ?>" 
                                    data-song-id="<?php echo $song['id']; ?>"
                                    title="<?php echo $is_liked ? 'Unlike' : 'Like'; ?>">
                                <i class="<?php echo $is_liked ? 'fas' : 'far'; ?> fa-heart"></i>
                            </button>
                            <button class="more-btn" data-song-id="<?php echo $song['id']; ?>">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div class="song-dropdown" id="dropdown-<?php echo $song['id']; ?>">
                                <button class="dropdown-item like-song <?php echo $is_liked ? 'liked text-danger' : ''; ?>" 
                                        data-song-id="<?php echo $song['id']; ?>">
                                    <i class="<?php echo $is_liked ? 'fas' : 'far'; ?> fa-heart"></i> 
                                    <?php echo $is_liked ? 'Unlike' : 'Like'; ?>
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
                    <?php endwhile; ?>
                </div>
            </section>

            <!-- Recent Albums Section -->
            <?php if($recent_albums_stmt->rowCount() > 0): ?>
            <section class="section">
                <div class="section-header">
                    <h2><i class="fas fa-compact-disc"></i> Recent Albums</h2>
                </div>
                <div class="albums-grid">
                    <?php while($album = $recent_albums_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <!-- PERBAIKAN LINK: ke album_detail.php -->
                    <a href="album_detail.php?id=<?php echo $album['id']; ?>" class="album-card" data-album-id="<?php echo $album['id']; ?>">
                        <div class="album-cover">
                            <img src="<?php echo getCoverPath($album['cover_image'], 'album'); ?>" 
                                 alt="<?php echo htmlspecialchars($album['title']); ?>"
                                 loading="lazy"
                                 onerror="this.src='assets/images/covers/default-cover.png'">
                            <div class="album-overlay">
                                <i class="fas fa-play"></i>
                            </div>
                        </div>
                        <div class="album-info">
                            <h3><?php echo htmlspecialchars($album['title']); ?></h3>
                            <p><?php echo htmlspecialchars($album['artist_name']); ?></p>
                            <?php if($album['song_count'] > 0): ?>
                            <span class="album-songs"><?php echo $album['song_count']; ?> songs</span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endwhile; ?>
                </div>
            </section>
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
                        <input type="text" id="playlistTitle" name="title" required 
                               placeholder="Enter playlist name">
                    </div>
                    <div class="form-group">
                        <label for="playlistDescription">Description (Optional)</label>
                        <textarea id="playlistDescription" name="description" 
                                  placeholder="Describe your playlist..." rows="3"></textarea>
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
    <script src="assets/js/home.js"></script>
    <script src="assets/js/header-search.js"></script>

</body>
</html>