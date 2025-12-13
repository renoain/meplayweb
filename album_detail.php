<?php
 require_once 'config/constants.php';
require_once 'config/auth.php';
require_once 'config/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: search.php");
    exit();
}

$album_id = intval($_GET['id']);
$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get album details
$album_query = "SELECT al.*, 
               (SELECT GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') 
                FROM songs s 
                LEFT JOIN artists a ON s.artist_id = a.id 
                WHERE s.album_id = al.id) as artists,
               (SELECT COUNT(*) FROM songs WHERE album_id = al.id AND is_active = 1) as song_count
               FROM albums al 
               WHERE al.id = ? AND al.is_active = 1";
$album_stmt = $conn->prepare($album_query);
$album_stmt->execute([$album_id]);
$album = $album_stmt->fetch(PDO::FETCH_ASSOC);

if (!$album) {
    header("Location: search.php");
    exit();
}

// Get album songs with like status
$songs_query = "SELECT s.*, a.name as artist_name, g.name as genre_name,
               IF(ul.song_id IS NOT NULL, 1, 0) as is_liked
               FROM songs s 
               LEFT JOIN artists a ON s.artist_id = a.id 
               LEFT JOIN genres g ON s.genre_id = g.id
               LEFT JOIN user_likes ul ON s.id = ul.song_id AND ul.user_id = ?
               WHERE s.album_id = ? AND s.is_active = 1 
               ORDER BY s.id ASC, s.title ASC";
$songs_stmt = $conn->prepare($songs_query);
$songs_stmt->execute([$user_id, $album_id]);
$songs = $songs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's playlists for dropdown
$playlists_query = "SELECT id, title FROM playlists WHERE user_id = ? ORDER BY title ASC";
$playlists_stmt = $conn->prepare($playlists_query);
$playlists_stmt->execute([$user_id]);
$user_playlists = $playlists_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get album cover images for grid
$album_covers_query = "SELECT cover_image FROM songs WHERE album_id = ? AND cover_image IS NOT NULL AND cover_image != 'default-cover.png' LIMIT 4";
$covers_stmt = $conn->prepare($album_covers_query);
$covers_stmt->execute([$album_id]);
$album_covers = $covers_stmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate total duration
$total_duration = 0;
foreach ($songs as $song) {
    $total_duration += $song['duration'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($album['title']); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/album_detail.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/player.css">
    <link rel="stylesheet" href="assets/css/header-search.css">

</head>
<body data-theme="<?php echo getCurrentTheme(); ?>">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <!-- Back Button -->
            <div class="back-button">
                <a href="search.php">
                    <i class="fas fa-arrow-left"></i> Back to Browse
                </a>
            </div>
            
            <!-- Album Header -->
            <div class="playlist-header">
                <div class="playlist-cover-large">
                    <?php if (!empty($album_covers) && count($album_covers) >= 4): ?>
                    <div class="playlist-cover-grid-large">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="cover-cell">
                            <img src="<?php echo getCoverPath($album_covers[$i], 'song'); ?>" 
                                 alt="Album cover <?php echo $i + 1; ?>">
                        </div>
                        <?php endfor; ?>
                    </div>
                    <?php elseif ($album['cover_image'] && $album['cover_image'] !== 'default-cover.png'): ?>
                        <img src="<?php echo getCoverPath($album['cover_image'], 'album'); ?>" 
                             alt="<?php echo htmlspecialchars($album['title']); ?>">
                    <?php else: ?>
                        <div class="default-cover-large">
                            <i class="fas fa-compact-disc"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="playlist-info-large">
                    <p class="playlist-type">ALBUM</p>
                    <h1><?php echo htmlspecialchars($album['title']); ?></h1>
                    
                    <?php if (!empty($album['description'])): ?>
                    <p class="playlist-description"><?php echo nl2br(htmlspecialchars($album['description'])); ?></p>
                    <?php endif; ?>
                    
                    <div class="playlist-meta">
                        <span class="meta-item">
                            <i class="fas fa-user"></i> 
                            <?php 
                            if (!empty($album['artists'])) {
                                $artists = explode(', ', $album['artists']);
                                echo htmlspecialchars($artists[0] . (count($artists) > 1 ? ' & more' : ''));
                            } else {
                                echo 'Various Artists';
                            }
                            ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-music"></i> <?php echo count($songs); ?> songs
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-clock"></i> <?php echo formatDuration($total_duration); ?>
                        </span>
                        <?php if ($album['release_year']): ?>
                        <span class="meta-item">
                            <i class="fas fa-calendar"></i> <?php echo $album['release_year']; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="playlist-actions">
                        <?php if (count($songs) > 0): ?>
                        <button class="play-all-btn" id="playAlbumBtn">
                            <i class="fas fa-play"></i> Play Album
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (count($songs) > 0): ?>
            <div class="songs-list">
                <?php foreach ($songs as $index => $song): ?>
                <div class="song-item" data-song-id="<?php echo $song['id']; ?>" data-index="<?php echo $index; ?>">
                    <div class="song-cover">
                        <img src="<?php echo getCoverPath($song['cover_image'], 'song'); ?>" 
                             alt="<?php echo htmlspecialchars($song['title']); ?>">
                        <button class="play-btn" data-song-id="<?php echo $song['id']; ?>">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                    
                    <div class="song-info">
                        <h4><?php echo htmlspecialchars($song['title']); ?></h4>
                        <p><?php echo htmlspecialchars($song['artist_name']); ?></p>
                        <div class="song-meta">
                            <span class="genre"><?php echo htmlspecialchars($song['genre_name']); ?></span>
                        </div>
                    </div>
                    
                    <div class="song-duration"><?php echo formatDuration($song['duration']); ?></div>
                    
                    <div class="song-actions">
                        <button class="like-btn <?php echo $song['is_liked'] ? 'liked' : ''; ?>" 
                                data-song-id="<?php echo $song['id']; ?>">
                            <i class="<?php echo $song['is_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                        
                        <button class="more-btn song-more-btn" data-index="<?php echo $index; ?>">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        
                        <div class="song-dropdown" id="songDropdown-<?php echo $index; ?>">
                            <button class="dropdown-item like-song <?php echo $song['is_liked'] ? 'liked' : ''; ?>" 
                                    data-song-id="<?php echo $song['id']; ?>">
                                <i class="<?php echo $song['is_liked'] ? 'fas' : 'far'; ?> fa-heart"></i> 
                                <?php echo $song['is_liked'] ? 'Unlike' : 'Like'; ?>
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
                    <i class="fas fa-music fa-4x"></i>
                    <h2>No songs in this album</h2>
                    <p>This album doesn't have any songs yet.</p>
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
    <script src="assets/js/likes.js"></script>
    <script src="assets/js/album_detail.js"></script>
    <script src="assets/js/header-search.js"></script>

</body>
</html>