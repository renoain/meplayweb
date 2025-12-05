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

// Handle create playlist form submission
$error_message = '';
$success_message = '';

// Handle playlist deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_playlist'])) {
        $playlist_id = intval($_POST['playlist_id']);
        
        // Verify ownership
        $check_query = "SELECT * FROM playlists WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->execute([$playlist_id, $user_id]);
        $playlist = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($playlist) {
            // First delete all songs from this playlist
            $delete_songs_query = "DELETE FROM playlist_songs WHERE playlist_id = ?";
            $delete_songs_stmt = $conn->prepare($delete_songs_query);
            $delete_songs_stmt->execute([$playlist_id]);
            
            // Then delete the playlist
            $delete_query = "DELETE FROM playlists WHERE id = ? AND user_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            
            if ($delete_stmt->execute([$playlist_id, $user_id])) {
                $success_message = "Playlist deleted successfully!";
                header("Location: playlists.php?success=" . urlencode($success_message));
                exit();
            } else {
                $error_message = "Failed to delete playlist";
            }
        } else {
            $error_message = "Playlist not found or access denied";
        }
    } elseif (isset($_POST['create_playlist'])) {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        
        if (empty($title)) {
            $error_message = "Playlist title is required";
        } else {
            $query = "INSERT INTO playlists (user_id, title, description, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute([$user_id, $title, $description])) {
                $playlist_id = $conn->lastInsertId();
                $success_message = "Playlist created successfully!";
                
                // Add song to playlist if provided
                if (isset($_POST['song_id']) && !empty($_POST['song_id'])) {
                    $song_id = intval($_POST['song_id']);
                    $add_query = "INSERT INTO playlist_songs (playlist_id, song_id) VALUES (?, ?)";
                    $add_stmt = $conn->prepare($add_query);
                    $add_stmt->execute([$playlist_id, $song_id]);
                }
                
                // Redirect to the new playlist
                header("Location: playlist_detail.php?id=" . $playlist_id . "&success=" . urlencode($success_message));
                exit();
            } else {
                $error_message = "Failed to create playlist. Please try again.";
            }
        }
    }
}

// Get success message from URL
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}

// Get user's playlists with song count and first 2 songs for cover
$query = "SELECT p.*, 
          COUNT(DISTINCT ps.song_id) as song_count,
          GROUP_CONCAT(DISTINCT s.cover_image ORDER BY ps.added_at LIMIT 2) as song_covers
          FROM playlists p 
          LEFT JOIN playlist_songs ps ON p.id = ps.playlist_id 
          LEFT JOIN songs s ON ps.song_id = s.id
          WHERE p.user_id = ? 
          GROUP BY p.id 
          ORDER BY p.created_at DESC";
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
            
            <div class="page-header">
                <h1>My Playlists</h1>
                <button class="btn-primary" id="createPlaylistBtn">
                    <i class="fas fa-plus"></i>
                    Create Playlist
                </button>
            </div>

            <?php if (count($playlists) > 0): ?>
            <div class="playlists-grid">
                <?php foreach ($playlists as $playlist): 
                    // Get song covers for playlist
                    $covers = $playlist['song_covers'] ? explode(',', $playlist['song_covers']) : [];
                    $has_covers = !empty($covers) && !empty($covers[0]);
                ?>
                <div class="playlist-card" data-playlist-id="<?php echo $playlist['id']; ?>">
                    <a href="playlist_detail.php?id=<?php echo $playlist['id']; ?>" class="playlist-link">
                        <div class="playlist-cover">
                            <?php if ($has_covers): ?>
                                <?php if (count($covers) >= 2): ?>
                                    <!-- Grid 2x2 untuk 2 atau lebih cover -->
                                    <div class="playlist-cover-grid">
                                        <img src="<?php echo getCoverPath($covers[0], 'song'); ?>" 
                                             alt="Cover 1">
                                        <img src="<?php echo getCoverPath($covers[1], 'song'); ?>" 
                                             alt="Cover 2">
                                        <img src="<?php echo getCoverPath($covers[0], 'song'); ?>" 
                                             alt="Cover 3">
                                        <img src="<?php echo getCoverPath($covers[1], 'song'); ?>" 
                                             alt="Cover 4">
                                    </div>
                                <?php else: ?>
                                    <!-- Single cover untuk 1 lagu -->
                                    <img src="<?php echo getCoverPath($covers[0], 'song'); ?>" 
                                         alt="<?php echo htmlspecialchars($playlist['title']); ?>">
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="default-cover">
                                    <i class="fas fa-music"></i>
                                </div>
                            <?php endif; ?>
                            <button class="play-btn" onclick="event.preventDefault(); playPlaylist(<?php echo $playlist['id']; ?>)">
                                <i class="fas fa-play"></i>
                            </button>
                        </div>
                        <div class="playlist-info">
                            <h3><?php echo htmlspecialchars($playlist['title']); ?></h3>
                            <?php if ($playlist['description']): ?>
                            <p><?php echo htmlspecialchars($playlist['description']); ?></p>
                            <?php endif; ?>
                            <div class="playlist-meta">
                                <span><?php echo $playlist['song_count']; ?> <?php echo $playlist['song_count'] == 1 ? 'song' : 'songs'; ?></span>
                            </div>
                        </div>
                    </a>
                    <div class="playlist-actions">
                        <button class="more-btn" onclick="event.stopPropagation(); togglePlaylistDropdown(this)">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="playlist_detail.php?id=<?php echo $playlist['id']; ?>" class="dropdown-item">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <a href="playlist_detail.php?id=<?php echo $playlist['id']; ?>" class="dropdown-item">
                                <i class="fas fa-edit"></i> Edit Playlist
                            </a>
                            <button class="dropdown-item delete-playlist-btn" 
                                    onclick="confirmDeletePlaylist(<?php echo $playlist['id']; ?>, '<?php echo htmlspecialchars(addslashes($playlist['title'])); ?>')">
                                <i class="fas fa-trash"></i> Delete Playlist
                            </button>
                        </div>
                    </div>
                </div>
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

    <!-- Create Playlist Modal -->
    <div id="createPlaylistModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Playlist</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createPlaylistForm" method="POST">
                    <div class="form-group">
                        <label for="playlistTitle">Playlist Title *</label>
                        <input type="text" id="playlistTitle" name="title" required 
                               placeholder="Enter playlist title">
                    </div>
                    <div class="form-group">
                        <label for="playlistDescription">Description (Optional)</label>
                        <textarea id="playlistDescription" name="description" rows="3" 
                                  placeholder="Add a description..."></textarea>
                    </div>
                    <input type="hidden" id="songIdForPlaylist" name="song_id" 
                           value="<?php echo isset($_GET['song_id']) ? intval($_GET['song_id']) : ''; ?>">
                    <div class="form-actions">
                        <button type="button" class="btn-secondary close-modal">Cancel</button>
                        <button type="submit" name="create_playlist" class="btn-primary">Create Playlist</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/player.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/player.js"></script>
    <script>
    function togglePlaylistDropdown(button) {
        const dropdown = button.nextElementSibling;
        const allDropdowns = document.querySelectorAll('.playlist-actions .dropdown-menu');
        
        allDropdowns.forEach(d => {
            if (d !== dropdown) {
                d.classList.remove('show');
            }
        });
        
        dropdown.classList.toggle('show');
    }
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.playlist-actions')) {
            document.querySelectorAll('.playlist-actions .dropdown-menu').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }
    });
    
    function confirmDeletePlaylist(playlistId, playlistTitle) {
        if (confirm(`Are you sure you want to delete "${playlistTitle}"?\n\nThis action cannot be undone.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const playlistIdInput = document.createElement('input');
            playlistIdInput.type = 'hidden';
            playlistIdInput.name = 'playlist_id';
            playlistIdInput.value = playlistId;
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'delete_playlist';
            actionInput.value = '1';
            
            form.appendChild(playlistIdInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    async function playPlaylist(playlistId) {
        try {
            const response = await fetch(`api/playlists.php?action=get_playlist_songs&id=${playlistId}`);
            const data = await response.json();
            
            if (data.success && data.songs.length > 0 && window.musicPlayer) {
                window.musicPlayer.clearQueue();
                
                for (let i = 0; i < data.songs.length; i++) {
                    const song = data.songs[i];
                    if (i === 0) {
                        window.musicPlayer.playSong(song);
                    } else {
                        window.musicPlayer.addToQueue(song);
                    }
                }
                
                showNotification('Playing playlist');
            } else {
                showNotification('No songs in this playlist', 'error');
            }
        } catch (error) {
            console.error('Error playing playlist:', error);
            showNotification('Error playing playlist', 'error');
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const createPlaylistModal = document.getElementById('createPlaylistModal');
        const createPlaylistBtn = document.getElementById('createPlaylistBtn');
        const emptyCreateBtn = document.getElementById('emptyCreatePlaylistBtn');
        const closeModalButtons = document.querySelectorAll('.close-modal');
        
        function openCreatePlaylistModal() {
            createPlaylistModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        if (createPlaylistBtn) {
            createPlaylistBtn.addEventListener('click', openCreatePlaylistModal);
        }
        
        if (emptyCreateBtn) {
            emptyCreateBtn.addEventListener('click', openCreatePlaylistModal);
        }
        
        closeModalButtons.forEach(button => {
            button.addEventListener('click', () => {
                createPlaylistModal.classList.remove('show');
                document.body.style.overflow = '';
            });
        });
        
        window.addEventListener('click', (e) => {
            if (e.target === createPlaylistModal) {
                createPlaylistModal.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
        
        const createPlaylistForm = document.getElementById('createPlaylistForm');
        if (createPlaylistForm) {
            createPlaylistForm.addEventListener('submit', function(e) {
                const title = document.getElementById('playlistTitle').value.trim();
                if (!title) {
                    e.preventDefault();
                    alert('Please enter a playlist title');
                    document.getElementById('playlistTitle').focus();
                }
            });
        }
        
        setTimeout(() => {
            document.querySelectorAll('.notification').forEach(notification => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            });
        }, 3000);
    });
    </script>
</body>
</html>