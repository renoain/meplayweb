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

// Get playlist ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: playlists.php");
    exit();
}

$playlist_id = intval($_GET['id']);

// Get playlist details
$query = "SELECT p.*, 
          u.username as creator_name,
          COUNT(DISTINCT ps.song_id) as song_count
          FROM playlists p 
          LEFT JOIN users u ON p.user_id = u.id
          LEFT JOIN playlist_songs ps ON p.id = ps.playlist_id
          WHERE p.id = ? AND p.user_id = ?
          GROUP BY p.id";
$stmt = $conn->prepare($query);
$stmt->execute([$playlist_id, $user_id]);
$playlist = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$playlist) {
    header("Location: playlists.php");
    exit();
}

// Get songs in playlist
$query = "SELECT s.*, 
          a.name as artist_name, 
          al.title as album_title, 
          g.name as genre_name,
          (SELECT COUNT(*) FROM user_likes ul WHERE ul.song_id = s.id AND ul.user_id = ?) as is_liked
          FROM songs s 
          LEFT JOIN artists a ON s.artist_id = a.id 
          LEFT JOIN albums al ON s.album_id = al.id 
          LEFT JOIN genres g ON s.genre_id = g.id
          INNER JOIN playlist_songs ps ON s.id = ps.song_id
          WHERE ps.playlist_id = ? 
          AND s.is_active = 1 
          ORDER BY ps.added_at ASC";
$stmt = $conn->prepare($query);
$stmt->execute([$user_id, $playlist_id]);
$songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's playlists for dropdown
$query = "SELECT id, title FROM playlists WHERE user_id = ? AND id != ? ORDER BY title ASC";
$playlists_stmt = $conn->prepare($query);
$playlists_stmt->execute([$user_id, $playlist_id]);
$user_playlists = $playlists_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle remove song from playlist
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_song'])) {
    $song_id = intval($_POST['song_id']);
    
    $delete_query = "DELETE FROM playlist_songs WHERE playlist_id = ? AND song_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    
    if ($delete_stmt->execute([$playlist_id, $song_id])) {
        $success_message = "Song removed from playlist";
        // Redirect with success message
        header("Location: playlist_detail.php?id=" . $playlist_id . "&success=" . urlencode($success_message));
        exit();
    } else {
        $error_message = "Failed to remove song";
    }
}

// Get success message from URL
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($playlist['title']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/player.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/playlist_detail.css">
    <link rel="stylesheet" href="assets/css/header-search.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
</head>
<body data-theme="<?php echo getCurrentTheme(); ?>">
     <?php include 'includes/sidebar.php'; ?>
    
     <div class="main-content">
         <?php include 'includes/header.php'; ?>
        
         <div class="content">
            <!-- Notifications -->
            <?php if ($error_message): ?>
                <div class="notification notification-error show">
                    <div class="notification-content">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="notification notification-success show">
                    <div class="notification-content">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Playlist Header -->
            <div class="page-header playlist-header" data-playlist-id="<?php echo $playlist_id; ?>">
                <div class="header-content">
                    <div class="header-icon">
                        <?php if (!empty($songs)): ?>
                        <img src="<?php echo getCoverPath($songs[0]['cover_image'], 'song'); ?>" 
                             alt="<?php echo htmlspecialchars($playlist['title']); ?>"
                             onerror="this.src='assets/images/covers/default-cover.png'">
                        <?php else: ?>
                        <i class="fas fa-music"></i>
                        <?php endif; ?>
                    </div>
                    <div class="header-info">
                        <h1><?php echo htmlspecialchars($playlist['title']); ?></h1>
                        <p>
                            Playlist • <?php echo $playlist['song_count']; ?> songs • 
                            Created by <?php echo htmlspecialchars($playlist['creator_name']); ?>
                        </p>
                        <?php if ($playlist['description']): ?>
                        <p class="playlist-description"><?php echo htmlspecialchars($playlist['description']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="play-all-btn" id="playPlaylistBtn">
                        <i class="fas fa-play"></i>
                        Play All
                    </button>
                    <button class="btn-secondary" onclick="playlistDetailManager.showEditPlaylistModal()">
                        <i class="fas fa-edit"></i>
                        Edit
                    </button>
                </div>
            </div>

            <!-- Songs Section -->
                <?php if (count($songs) > 0): ?>
                <div class="songs-list">
                    <?php foreach ($songs as $index => $song): ?>
                    <div class="song-item" data-song-id="<?php echo $song['id']; ?>" data-index="<?php echo $index; ?>">
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
                            
                            <!-- Dropdown Menu -->
                            <div class="song-dropdown" id="dropdown-<?php echo $song['id']; ?>">
                                <button class="dropdown-item like-song <?php echo $song['is_liked'] ? 'liked text-danger' : ''; ?>" 
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
                                            <?php foreach ($user_playlists as $playlist_item): ?>
                                                <button class="dropdown-item add-to-playlist" 
                                                        data-song-id="<?php echo $song['id']; ?>" 
                                                        data-playlist-id="<?php echo $playlist_item['id']; ?>">
                                                    <?php echo htmlspecialchars($playlist_item['title']); ?>
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
                                
                                <button class="dropdown-item remove-from-playlist" 
                                        data-song-id="<?php echo $song['id']; ?>"
                                        data-playlist-id="<?php echo $playlist_id; ?>">
                                    <i class="fas fa-minus-circle"></i> Remove from Playlist
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
                        <p>Add songs to get started</p>
                        <button class="btn-primary" onclick="playlistDetailManager.showAddSongsModal()">
                            <i class="fas fa-plus"></i>
                            Add Songs
                        </button>
                    </div>
                </div>
                <?php endif; ?>
        </div>
    </div>

     <?php include 'includes/player.php'; ?>
    
    <!-- Modals -->
    
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
    
    <!-- Edit Playlist Modal -->
    <div id="editPlaylistModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Playlist</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editPlaylistForm" method="POST" action="api/playlists.php">
                    <div class="form-group">
                        <label for="editPlaylistTitle">Playlist Title *</label>
                        <input type="text" id="editPlaylistTitle" name="title" required 
                               value="<?php echo htmlspecialchars($playlist['title']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="editPlaylistDescription">Description</label>
                        <textarea id="editPlaylistDescription" name="description" rows="3"><?php echo htmlspecialchars($playlist['description'] ?? ''); ?></textarea>
                    </div>
                    <input type="hidden" name="playlist_id" value="<?php echo $playlist_id; ?>">
                    <input type="hidden" name="action" value="update">
                    <div class="form-actions">
                         <button type="submit" class="btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Songs Modal -->
    <div id="addSongsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Songs to Playlist</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="search-songs">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchSongsInput" placeholder="Search songs...">
                    </div>
                    <div id="searchResults" class="search-results">
                     </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary close-modal">x</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Remove Song Confirmation Modal -->
    <div id="removeSongModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Remove Song</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove this song from the playlist?</p>
                <form id="removeSongForm" method="POST">
                    <input type="hidden" name="song_id" id="removeSongId">
                    <input type="hidden" name="playlist_id" value="<?php echo $playlist_id; ?>">
                    <input type="hidden" name="remove_song" value="1">
                    <div class="form-actions">
                        <button type="submit" class="btn-danger">Remove</button>
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