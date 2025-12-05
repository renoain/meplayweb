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
// ====== WAIT FOR DOM TO BE READY ======
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎵 DOM fully loaded, starting script...');
    
    // ====== TEST: CHECK ELEMENTS NOW ======
    console.log('=== ELEMENT CHECK ===');
    console.log('Remove buttons found:', document.querySelectorAll('.remove-from-playlist').length);
    console.log('Song items found:', document.querySelectorAll('.song-item').length);
    
    // If still 0, there might be another issue
    const removeButtons = document.querySelectorAll('.remove-from-playlist');
    const songItems = document.querySelectorAll('.song-item');
    
    if (removeButtons.length === 0) {
        console.error('❌ NO REMOVE BUTTONS FOUND! Checking HTML structure...');
        
        // Debug: Check what buttons actually exist
        console.log('All buttons with class "dropdown-item":', 
            document.querySelectorAll('.dropdown-item').length);
        
        // Check if dropdowns exist
        document.querySelectorAll('.song-dropdown').forEach((dropdown, i) => {
            console.log(`Dropdown ${i}:`, {
                id: dropdown.id,
                innerHTML: dropdown.innerHTML.substring(0, 100) + '...'
            });
        });
        
        // Try alternative selector
        console.log('Buttons with text "Remove":', 
            document.querySelectorAll('button:contains("Remove")').length);
    }
    
    // ====== SETUP EVENT LISTENERS ======
    console.log('=== SETTING UP EVENT LISTENERS ===');
    
    // Method 1: Direct event delegation (works even if buttons added later)
    document.addEventListener('click', function(e) {
        // Check if any parent element has the class
        let target = e.target;
        let removeBtn = null;
        
        // Traverse up to find remove button
        while (target && target !== document) {
            if (target.classList && target.classList.contains('remove-from-playlist')) {
                removeBtn = target;
                break;
            }
            target = target.parentElement;
        }
        
        if (removeBtn) {
            console.log('🔥 EVENT DELEGATION: Remove button clicked!');
            e.preventDefault();
            e.stopPropagation();
            
            const songId = removeBtn.getAttribute('data-song-id');
            const playlistId = removeBtn.getAttribute('data-playlist-id');
            const songItem = removeBtn.closest('.song-item');
            
            console.log('Data:', { songId, playlistId });
            console.log('Song item:', songItem);
            
            // Close dropdown
            const dropdown = removeBtn.closest('.song-dropdown');
            if (dropdown) {
                dropdown.style.display = 'none';
            }
            
            if (confirm('Remove this song from playlist?')) {
                // Call remove function
                removeSong(songId, playlistId, songItem);
            }
            
            return false;
        }
    });
    
    // Method 2: Also add direct listeners to existing buttons
    removeButtons.forEach((button, index) => {
        console.log(`Adding listener to button ${index}:`, {
            songId: button.getAttribute('data-song-id'),
            playlistId: button.getAttribute('data-playlist-id')
        });
        
        button.addEventListener('click', function(e) {
            console.log('🔥 DIRECT LISTENER: Remove button clicked!');
            e.preventDefault();
            e.stopPropagation();
            
            const songId = this.getAttribute('data-song-id');
            const playlistId = this.getAttribute('data-playlist-id');
            const songItem = this.closest('.song-item');
            
            if (confirm('Remove this song from playlist?')) {
                removeSong(songId, playlistId, songItem);
            }
            
            return false;
        });
    });
    
    // ====== REMOVE SONG FUNCTION ======
    function removeSong(songId, playlistId, songItem) {
        console.log('🔄 Removing song:', { songId, playlistId });
        
        if (!songId || !playlistId) {
            alert('❌ Invalid data');
            return;
        }
        
        // Visual feedback
        if (songItem) {
            songItem.style.opacity = '0.5';
            songItem.style.pointerEvents = 'none';
        }
        
        // API call
        fetch('api/playlists.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'remove_song',
                song_id: parseInt(songId),
                playlist_id: parseInt(playlistId)
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('API Response:', data);
            
            if (data.success) {
                // Animate removal
                if (songItem) {
                    songItem.style.transition = 'all 0.3s ease';
                    
                    setTimeout(() => {
                        songItem.style.opacity = '0';
                        songItem.style.height = '0';
                        songItem.style.padding = '0';
                        songItem.style.margin = '0';
                        songItem.style.overflow = 'hidden';
                        
                        setTimeout(() => {
                            songItem.remove();
                            
                            // Update count
                            const remaining = document.querySelectorAll('.song-item').length;
                            const countEl = document.querySelector('.playlist-meta span:first-child');
                            if (countEl) {
                                countEl.textContent = remaining + ' ' + 
                                    (remaining === 1 ? 'song' : 'songs');
                            }
                            
                            alert('✅ Song removed successfully!');
                            
                            // Reload if empty
                            if (remaining === 0) {
                                setTimeout(() => location.reload(), 1000);
                            }
                        }, 300);
                    }, 100);
                }
            } else {
                alert('❌ ' + (data.message || 'Failed to remove song'));
                if (songItem) {
                    songItem.style.opacity = '1';
                    songItem.style.pointerEvents = 'auto';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Network error');
            if (songItem) {
                songItem.style.opacity = '1';
                songItem.style.pointerEvents = 'auto';
            }
        });
    }
    
    // ====== TEST API ENDPOINT ======
    console.log('=== TESTING API ENDPOINT ===');
    
    // Quick test
    setTimeout(() => {
        fetch('api/playlists.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'remove_song',
                song_id: 1,
                playlist_id: 10
            })
        })
        .then(r => r.json())
        .then(data => {
            console.log('✅ API Test Response:', data);
        });
    }, 500);
    
    // ====== KEEP EXISTING DROPDOWN CODE ======
    let activeDropdown = null;
    
    function closeDropdown() {
        if (activeDropdown) {
            activeDropdown.style.display = 'none';
            activeDropdown = null;
        }
    }
    
    // Click outside to close dropdown
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.more-btn') && 
            !e.target.closest('.song-dropdown') && 
            !e.target.closest('.playlist-dropdown')) {
            closeDropdown();
        }
    });
    
    // Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDropdown();
    });
    
    // Song dropdown buttons
    document.querySelectorAll('.song-more-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            
            const index = this.getAttribute('data-index');
            const dropdown = document.getElementById('songDropdown-' + index);
            
            if (!dropdown) return;
            
            if (dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
                activeDropdown = null;
            } else {
                closeDropdown();
                dropdown.style.display = 'block';
                dropdown.style.position = 'fixed';
                
                const rect = this.getBoundingClientRect();
                dropdown.style.top = (rect.bottom + 5) + 'px';
                dropdown.style.left = (rect.right - dropdown.offsetWidth) + 'px';
                
                activeDropdown = dropdown;
            }
        });
    });
    
    // Playlist dropdown
    const playlistBtn = document.getElementById('playlistMoreBtn');
    if (playlistBtn) {
        playlistBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('playlistDropdown');
            
            if (dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
                activeDropdown = null;
            } else {
                closeDropdown();
                dropdown.style.display = 'block';
                dropdown.style.position = 'absolute';
                dropdown.style.top = '45px';
                dropdown.style.right = '0';
                activeDropdown = dropdown;
            }
        });
    }
    
    console.log('✅ Script setup complete!');
});

// If DOM is already loaded, trigger manually
if (document.readyState === 'loading') {
    console.log('📖 Document still loading...');
} else {
    console.log('⚡ Document already loaded, firing event manually');
    document.dispatchEvent(new Event('DOMContentLoaded'));
}
</script>
</body>
</html>