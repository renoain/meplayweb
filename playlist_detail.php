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

// Get playlist info with song count
$playlist_query = "SELECT p.*, 
                  COUNT(ps.song_id) as song_count
                  FROM playlists p 
                  LEFT JOIN playlist_songs ps ON p.id = ps.playlist_id 
                  WHERE p.id = ? AND p.user_id = ?
                  GROUP BY p.id";
$playlist_stmt = $conn->prepare($playlist_query);
$playlist_stmt->execute([$playlist_id, $user_id]);
$playlist = $playlist_stmt->fetch(PDO::FETCH_ASSOC);

if (!$playlist) {
    header("Location: playlists.php");
    exit();
}

// Get songs in playlist with like status
$songs_query = "SELECT s.*, 
                a.name as artist_name, 
                al.title as album_title, 
                g.name as genre_name,
                ps.added_at,
                IF(ul.song_id IS NOT NULL, 1, 0) as is_liked
                FROM playlist_songs ps
                INNER JOIN songs s ON ps.song_id = s.id
                LEFT JOIN artists a ON s.artist_id = a.id 
                LEFT JOIN albums al ON s.album_id = al.id 
                LEFT JOIN genres g ON s.genre_id = g.id
                LEFT JOIN user_likes ul ON s.id = ul.song_id AND ul.user_id = ?
                WHERE ps.playlist_id = ? AND s.is_active = 1 
                ORDER BY ps.added_at ASC";
$songs_stmt = $conn->prepare($songs_query);
$songs_stmt->execute([$user_id, $playlist_id]);
$playlist_songs = $songs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's playlists for dropdown (exclude current playlist)
$playlists_query = "SELECT id, title FROM playlists WHERE user_id = ? AND id != ? ORDER BY title ASC";
$playlists_stmt = $conn->prepare($playlists_query);
$playlists_stmt->execute([$user_id, $playlist_id]);
$user_playlists = $playlists_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get first 2 song covers for playlist cover
$covers = [];
if (count($playlist_songs) > 0) {
    $first_song_cover = getCoverPath($playlist_songs[0]['cover_image'], 'song');
    if (!empty($first_song_cover)) {
        $covers[] = $first_song_cover;
    }
    if (count($playlist_songs) > 1) {
        $second_song_cover = getCoverPath($playlist_songs[1]['cover_image'], 'song');
        if (!empty($second_song_cover)) {
            $covers[] = $second_song_cover;
        }
    }
}

$error_message = '';
$success_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_playlist'])) {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        
        if (empty($title)) {
            $error_message = "Playlist title is required";
        } else {
            $update_query = "UPDATE playlists SET title = ?, description = ? WHERE id = ? AND user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            
            if ($update_stmt->execute([$title, $description, $playlist_id, $user_id])) {
                $success_message = "Playlist updated successfully";
                $playlist_stmt->execute([$playlist_id, $user_id]);
                $playlist = $playlist_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error_message = "Failed to update playlist";
            }
        }
    } elseif (isset($_POST['delete_playlist'])) {
        $conn->beginTransaction();
        
        try {
            $delete_songs_query = "DELETE FROM playlist_songs WHERE playlist_id = ?";
            $delete_songs_stmt = $conn->prepare($delete_songs_query);
            $delete_songs_stmt->execute([$playlist_id]);
            
            $delete_query = "DELETE FROM playlists WHERE id = ? AND user_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            
            if ($delete_stmt->execute([$playlist_id, $user_id])) {
                $conn->commit();
                header("Location: playlists.php?success=Playlist+deleted+successfully");
                exit();
            } else {
                $error_message = "Failed to delete playlist";
                $conn->rollBack();
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = "Failed to delete playlist: " . $e->getMessage();
        }
    }
}

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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/playlist_detail.css">
    <link rel="stylesheet" href="assets/css/likes.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/player.css">
</head>
<body data-theme="<?php echo getCurrentTheme(); ?>">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <div class="back-button">
                <a href="playlists.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Playlists
                </a>
            </div>
            
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
            
            <div class="playlist-header">
                <div class="playlist-cover-large">
                    <?php if (!empty($covers)): ?>
                        <?php if (count($covers) >= 2): ?>
                            <div class="playlist-cover-grid-large">
                                <img src="<?php echo $covers[0]; ?>" alt="Cover 1">
                                <img src="<?php echo $covers[1]; ?>" alt="Cover 2">
                                <img src="<?php echo $covers[0]; ?>" alt="Cover 3">
                                <img src="<?php echo $covers[1]; ?>" alt="Cover 4">
                            </div>
                        <?php else: ?>
                            <img src="<?php echo $covers[0]; ?>" alt="<?php echo htmlspecialchars($playlist['title']); ?>">
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="default-cover-large">
                            <i class="fas fa-music"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="playlist-info-large">
                    <h1><?php echo htmlspecialchars($playlist['title']); ?></h1>
                    
                    <?php if ($playlist['description']): ?>
                    <p class="playlist-description">
                        <?php echo htmlspecialchars($playlist['description']); ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="playlist-meta">
                        <span><?php echo count($playlist_songs); ?> <?php echo count($playlist_songs) == 1 ? 'song' : 'songs'; ?></span>
                        <?php if (!empty($playlist['created_at'])): ?>
                        <span>• Created <?php echo date('F j, Y', strtotime($playlist['created_at'])); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="playlist-actions">
                        <?php if (count($playlist_songs) > 0): ?>
                        <button class="play-all-btn" id="playPlaylist">
                            <i class="fas fa-play"></i>
                            Play All
                        </button>
                        <?php endif; ?>
                        
                        <button class="more-btn" id="playlistMoreBtn">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                        
                        <div class="playlist-dropdown" id="playlistDropdown">
                            <button class="dropdown-item" id="editPlaylistBtn">
                                <i class="fas fa-edit"></i> Edit Playlist
                            </button>
                            <button class="dropdown-item text-danger" id="deletePlaylistBtn">
                                <i class="fas fa-trash"></i> Delete Playlist
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (count($playlist_songs) > 0): ?>
            <div class="songs-list">
                <?php foreach ($playlist_songs as $index => $song): ?>
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
                    </div>
                    
                    <div class="song-duration"><?php echo formatDuration($song['duration']); ?></div>
                    
                    <div class="song-actions">
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
                            
                            <button class="dropdown-item text-danger remove-from-playlist" 
                                    data-song-id="<?php echo $song['id']; ?>" 
                                    data-playlist-id="<?php echo $playlist_id; ?>">
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
                    <a href="index.php" class="btn-primary">Browse Songs</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modals -->
    <div id="editPlaylistModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Playlist</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="editTitle">Playlist Title *</label>
                        <input type="text" id="editTitle" name="title" 
                               value="<?php echo htmlspecialchars($playlist['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editDescription">Description (Optional)</label>
                        <textarea id="editDescription" name="description" rows="3"><?php echo htmlspecialchars($playlist['description']); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary close-modal">Cancel</button>
                        <button type="submit" name="update_playlist" class="btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="deletePlaylistModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Playlist</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete "<strong><?php echo htmlspecialchars($playlist['title']); ?></strong>"?</p>
                <p class="text-warning">This action cannot be undone.</p>
                <form method="POST">
                    <div class="form-actions">
                        <button type="button" class="btn-secondary close-modal">Cancel</button>
                        <button type="submit" name="delete_playlist" class="btn-danger">
                            <i class="fas fa-trash"></i> Delete Playlist
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

    <?php include 'includes/player.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/player.js"></script>
    <script src="assets/js/likes.js"></script>
    <script>
// SANGAT SIMPLE SOLUTION
let activeDropdown = null;

// 1. FUNCTION TO CLOSE DROPDOWN
function closeDropdown() {
    if (activeDropdown) {
        activeDropdown.style.display = 'none';
        activeDropdown = null;
    }
}

// 2. CLICK ANYWHERE TO CLOSE DROPDOWN
document.addEventListener('click', function(e) {
    // If click is NOT on a dropdown button and NOT inside active dropdown
    if (!e.target.closest('.more-btn') && !e.target.closest('.song-dropdown') && !e.target.closest('.playlist-dropdown')) {
        closeDropdown();
    }
});

// 3. ESCAPE KEY
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDropdown();
    }
});

// 4. PLAYLIST DROPDOWN
document.getElementById('playlistMoreBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    
    const dropdown = document.getElementById('playlistDropdown');
    
    // Toggle
    if (dropdown.style.display === 'block') {
        dropdown.style.display = 'none';
        activeDropdown = null;
    } else {
        closeDropdown(); // Close any open dropdown first
        dropdown.style.display = 'block';
        dropdown.style.position = 'absolute';
        dropdown.style.top = '45px';
        dropdown.style.right = '0';
        activeDropdown = dropdown;
    }
});

// 5. SONG DROPDOWNS - SIMPLE LOOP
document.querySelectorAll('.song-more-btn').forEach(function(button) {
    button.addEventListener('click', function(e) {
        e.stopPropagation();
        
        const index = this.getAttribute('data-index');
        const dropdown = document.getElementById('songDropdown-' + index);
        
        if (!dropdown) return;
        
        // Toggle
        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
            activeDropdown = null;
        } else {
            closeDropdown(); // Close any open dropdown first
            
            dropdown.style.display = 'block';
            dropdown.style.position = 'fixed';
            
            // Position it
            const rect = this.getBoundingClientRect();
            dropdown.style.top = (rect.bottom + 5) + 'px';
            dropdown.style.left = (rect.right - dropdown.offsetWidth) + 'px';
            
            activeDropdown = dropdown;
        }
    });
});

// 6. ACTION BUTTONS - SIMPLE AND DIRECT
// Remove from playlist - FIXED VERSION
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-from-playlist')) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = e.target.closest('.remove-from-playlist');
        const songId = button.getAttribute('data-song-id');
        const playlistId = button.getAttribute('data-playlist-id');
        
        // Close dropdown first
        closeDropdown();
        
        // Confirm
        if (confirm('Remove this song from playlist?')) {
            // SIMPLE AJAX REQUEST
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'ajax/remove_from_playlist.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Find and remove the song element
                            const songElements = document.querySelectorAll('.song-item');
                            songElements.forEach(function(songElement) {
                                if (songElement.getAttribute('data-song-id') === songId) {
                                    songElement.remove();
                                    
                                    // If no songs left, reload page
                                    if (document.querySelectorAll('.song-item').length === 0) {
                                        setTimeout(function() {
                                            location.reload();
                                        }, 1000);
                                    }
                                }
                            });
                        } else {
                            alert(response.message || 'Failed to remove song');
                        }
                    } catch (error) {
                        console.error('JSON parse error:', error);
                        alert('Error processing response');
                    }
                } else {
                    alert('Request failed: ' + xhr.status);
                }
            };
            
            xhr.onerror = function() {
                alert('Network error');
            };
            
            xhr.send('song_id=' + encodeURIComponent(songId) + '&playlist_id=' + encodeURIComponent(playlistId));
        }
    }
    
    // Like song
    if (e.target.closest('.like-song')) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = e.target.closest('.like-song');
        const songId = button.getAttribute('data-song-id');
        const isLiked = button.classList.contains('liked');
        
        closeDropdown();
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'ajax/toggle_like.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        if (isLiked) {
                            button.classList.remove('liked');
                            button.innerHTML = '<i class="far fa-heart"></i> Like';
                            alert('Removed from likes');
                        } else {
                            button.classList.add('liked');
                            button.innerHTML = '<i class="fas fa-heart"></i> Unlike';
                            alert('Added to likes');
                        }
                    } else {
                        alert(response.message || 'Failed to update like');
                    }
                } catch (error) {
                    console.error('JSON parse error:', error);
                    alert('Error processing response');
                }
            } else {
                alert('Request failed: ' + xhr.status);
            }
        };
        
        xhr.onerror = function() {
            alert('Network error');
        };
        
        xhr.send('song_id=' + encodeURIComponent(songId) + '&action=' + (isLiked ? 'unlike' : 'like'));
    }
    
    // Add to queue
    if (e.target.closest('.add-to-queue')) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = e.target.closest('.add-to-queue');
        const songId = button.getAttribute('data-song-id');
        
        closeDropdown();
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'ajax/add_to_queue.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('Added to queue');
                    } else {
                        alert(response.message || 'Failed to add to queue');
                    }
                } catch (error) {
                    console.error('JSON parse error:', error);
                    alert('Error processing response');
                }
            } else {
                alert('Request failed: ' + xhr.status);
            }
        };
        
        xhr.onerror = function() {
            alert('Network error');
        };
        
        xhr.send('song_id=' + encodeURIComponent(songId));
    }
    
    // Add to playlist
    if (e.target.closest('.add-to-playlist')) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = e.target.closest('.add-to-playlist');
        const songId = button.getAttribute('data-song-id');
        const playlistId = button.getAttribute('data-playlist-id');
        
        closeDropdown();
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'ajax/add_to_playlist.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('Added to playlist');
                    } else {
                        alert(response.message || 'Failed to add to playlist');
                    }
                } catch (error) {
                    console.error('JSON parse error:', error);
                    alert('Error processing response');
                }
            } else {
                alert('Request failed: ' + xhr.status);
            }
        };
        
        xhr.onerror = function() {
            alert('Network error');
        };
        
        xhr.send('song_id=' + encodeURIComponent(songId) + '&playlist_id=' + encodeURIComponent(playlistId));
    }
    
    // Create playlist
    if (e.target.closest('.create-playlist')) {
        e.preventDefault();
        e.stopPropagation();
        
        const button = e.target.closest('.create-playlist');
        const songId = button.getAttribute('data-song-id');
        
        closeDropdown();
        
        document.getElementById('songIdForPlaylist').value = songId;
        document.getElementById('createPlaylistModal').classList.add('show');
    }
});

// 7. MODAL FUNCTIONS
document.querySelectorAll('.close-modal').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.modal').forEach(function(modal) {
            modal.classList.remove('show');
        });
    });
});

// Edit playlist
document.getElementById('editPlaylistBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    closeDropdown();
    document.getElementById('editPlaylistModal').classList.add('show');
});

// Delete playlist
document.getElementById('deletePlaylistBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    closeDropdown();
    document.getElementById('deletePlaylistModal').classList.add('show');
});

// 8. PLAY FUNCTIONS
// Play all
if (document.getElementById('playPlaylist')) {
    document.getElementById('playPlaylist').addEventListener('click', function() {
        const songIds = [];
        document.querySelectorAll('.song-item').forEach(function(item) {
            songIds.push(item.getAttribute('data-song-id'));
        });
        
        if (songIds.length > 0) {
            console.log('Playing all songs:', songIds);
        }
    });
}

// Individual play buttons
document.querySelectorAll('.play-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const songId = this.closest('.song-item').getAttribute('data-song-id');
        console.log('Playing song:', songId);
    });
});

// 9. CREATE PLAYLIST FORM
if (document.getElementById('createPlaylistForm')) {
    document.getElementById('createPlaylistForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'ajax/create_playlist.php', true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('Playlist created successfully');
                        document.getElementById('createPlaylistModal').classList.remove('show');
                        e.target.reset();
                    } else {
                        alert(response.message || 'Failed to create playlist');
                    }
                } catch (error) {
                    console.error('JSON parse error:', error);
                    alert('Error processing response');
                }
            } else {
                alert('Request failed: ' + xhr.status);
            }
        };
        
        xhr.onerror = function() {
            alert('Network error');
        };
        
        xhr.send(new FormData(this));
    });
}
</script>
   
</body>
</html>