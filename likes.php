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

// Get liked songs with like status
$query = "SELECT s.*, 
          a.name as artist_name, 
          al.title as album_title, 
          g.name as genre_name,
          1 as is_liked  -- Karena ini halaman liked songs, semua lagu sudah di-like
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
    <style>
    /* Tambahan style untuk warna merah pada like button */
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
                    </div>
                    
                    <div class="song-duration"><?php echo formatDuration($song['duration']); ?></div>
                    
                    <div class="song-actions">
                        <button class="more-btn song-more-btn" data-index="<?php echo $index; ?>">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        
                        <div class="song-dropdown" id="songDropdown-<?php echo $index; ?>">
                            <!-- LIKE BUTTON dengan class "liked" dan warna merah -->
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
    <script>
    // ====== LIKES PAGE SPECIFIC JAVASCRIPT ======
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Likes page loaded');
        
        // ====== NOTIFICATION SYSTEM ======
        function showNotification(message, type = 'success') {
            // Remove any existing notifications
            const existingNotif = document.querySelector('.notification.show');
            if (existingNotif) {
                existingNotif.remove();
            }
            
            // Create new notification
            const notification = document.createElement('div');
            notification.className = `notification notification-${type} show`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Add to content area
            const content = document.querySelector('.content');
            if (content) {
                content.insertBefore(notification, content.firstChild);
                
                // Auto remove after 3 seconds
                setTimeout(() => {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }
        }
        
        // ====== DROPDOWN SYSTEM ======
        let activeDropdown = null;
        
        function closeDropdowns() {
            if (activeDropdown) {
                activeDropdown.style.display = 'none';
                activeDropdown = null;
            }
        }
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.more-btn') && 
                !e.target.closest('.song-dropdown')) {
                closeDropdowns();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDropdowns();
        });
        
        // ====== SONG DROPDOWNS ======
        document.querySelectorAll('.song-more-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                
                const index = this.getAttribute('data-index');
                const dropdown = document.getElementById('songDropdown-' + index);
                
                if (!dropdown) return;
                
                if (dropdown.style.display === 'block') {
                    dropdown.style.display = 'none';
                    activeDropdown = null;
                } else {
                    closeDropdowns();
                    dropdown.style.display = 'block';
                    
                    // POSITION DROPDOWN TO THE LEFT
                    const rect = this.getBoundingClientRect();
                    dropdown.style.position = 'fixed';
                    
                    // Calculate position for LEFT alignment
                    const dropdownWidth = dropdown.offsetWidth;
                    let leftPosition = rect.left - dropdownWidth - 10;
                    
                    // If dropdown would go off screen on left, position to right instead
                    if (leftPosition < 10) {
                        leftPosition = rect.right + 10;
                    }
                    
                    dropdown.style.top = (rect.bottom + 5) + 'px';
                    dropdown.style.left = leftPosition + 'px';
                    dropdown.style.zIndex = '1000';
                    
                    activeDropdown = dropdown;
                }
            });
        });
        
        // ====== LIKE SONG ======
        document.querySelectorAll('.like-song').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const songId = this.getAttribute('data-song-id');
                const isLiked = this.classList.contains('liked');
                const songItem = this.closest('.song-item');
                const songTitle = songItem ? songItem.querySelector('.song-info h4').textContent : 'Song';
                
                closeDropdowns();
                
                fetch('api/likes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        song_id: parseInt(songId),
                        action: 'unlike'  // Karena di halaman liked songs, action selalu unlike
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Unlike - remove from list with animation
                        if (songItem) {
                            songItem.style.transition = 'all 0.3s ease';
                            songItem.style.opacity = '0.5';
                            
                            setTimeout(() => {
                                songItem.style.opacity = '0';
                                songItem.style.height = '0';
                                songItem.style.padding = '0';
                                songItem.style.margin = '0';
                                songItem.style.overflow = 'hidden';
                                
                                setTimeout(() => {
                                    songItem.remove();
                                    
                                    // Update song count
                                    const remaining = document.querySelectorAll('.song-item').length;
                                    const countEl = document.querySelector('.header-info p');
                                    if (countEl) {
                                        countEl.textContent = remaining + ' liked songs';
                                    }
                                    
                                    // Show empty state if no songs left
                                    if (remaining === 0) {
                                        const songsList = document.querySelector('.songs-list');
                                        if (songsList) {
                                            songsList.innerHTML = `
                                                <div class="no-songs">
                                                    <div class="no-songs-content">
                                                        <i class="fas fa-heart fa-4x"></i>
                                                        <h2>No liked songs yet</h2>
                                                        <p>Start liking songs to see them here</p>
                                                        <a href="index.php" class="btn-primary">Discover Music</a>
                                                    </div>
                                                </div>
                                            `;
                                        }
                                    }
                                    
                                    showNotification(`Removed "${songTitle}" from liked songs`);
                                }, 300);
                            }, 100);
                        }
                    } else {
                        showNotification(data.message || 'Failed to unlike song', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Network error', 'error');
                });
                
                return false;
            });
        });
        
        // ====== ADD TO QUEUE ======
        document.querySelectorAll('.add-to-queue').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const songId = this.getAttribute('data-song-id');
                const songItem = this.closest('.song-item');
                const songTitle = songItem ? songItem.querySelector('.song-info h4').textContent : 'Song';
                
                closeDropdowns();
                
                // Add to queue via player if available
                if (window.musicPlayer) {
                    fetch(`api/songs.php?id=${songId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.musicPlayer.addToQueue(data.song);
                            showNotification(`"${songTitle}" added to queue`);
                        } else {
                            showNotification('Failed to get song data', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Network error', 'error');
                    });
                } else {
                    // Fallback to localStorage
                    let queue = JSON.parse(localStorage.getItem('meplay_queue') || '[]');
                    if (!queue.includes(songId)) {
                        queue.push(songId);
                        localStorage.setItem('meplay_queue', JSON.stringify(queue));
                        showNotification(`"${songTitle}" added to queue`);
                    } else {
                        showNotification(`"${songTitle}" already in queue`, 'error');
                    }
                }
                
                return false;
            });
        });
        
        // ====== ADD TO PLAYLIST ======
        document.querySelectorAll('.add-to-playlist:not(.disabled)').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const songId = this.getAttribute('data-song-id');
                const playlistId = this.getAttribute('data-playlist-id');
                const songItem = this.closest('.song-item');
                const songTitle = songItem ? songItem.querySelector('.song-info h4').textContent : 'Song';
                const playlistName = button.textContent.trim();
                
                closeDropdowns();
                
                fetch('api/playlists.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_song',
                        song_id: parseInt(songId),
                        playlist_id: parseInt(playlistId)
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(`"${songTitle}" added to "${playlistName}"`);
                    } else {
                        showNotification(data.message || 'Failed to add to playlist', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Network error', 'error');
                });
                
                return false;
            });
        });
        
        // ====== CREATE PLAYLIST ======
        document.querySelectorAll('.create-playlist').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const songId = this.getAttribute('data-song-id');
                
                closeDropdowns();
                document.getElementById('songIdForPlaylist').value = songId;
                document.getElementById('createPlaylistModal').classList.add('show');
                
                return false;
            });
        });
        
        // ====== PLAY BUTTONS ======
        document.querySelectorAll('.play-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                
                const songId = this.getAttribute('data-song-id');
                const songItem = this.closest('.song-item');
                const songTitle = songItem ? songItem.querySelector('.song-info h4').textContent : 'Song';
                
                if (window.musicPlayer) {
                    fetch(`api/songs.php?id=${songId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.musicPlayer.playSong(data.song);
                            showNotification(`Playing "${songTitle}"`);
                        } else {
                            showNotification('Failed to play song', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Network error', 'error');
                    });
                } else {
                    // Fallback
                    localStorage.setItem('meplay_now_playing', JSON.stringify({
                        id: songId,
                        title: songTitle
                    }));
                    showNotification(`Playing "${songTitle}"`);
                }
            });
        });
        
        // ====== PLAY ALL BUTTON ======
        const playAllBtn = document.getElementById('playAllLiked');
        if (playAllBtn) {
            playAllBtn.addEventListener('click', function() {
                const songIds = [];
                const songTitles = [];
                
                document.querySelectorAll('.song-item').forEach(item => {
                    const songId = item.getAttribute('data-song-id');
                    const songTitle = item.querySelector('.song-info h4').textContent;
                    if (songId) {
                        songIds.push(parseInt(songId));
                        songTitles.push(songTitle);
                    }
                });
                
                if (songIds.length > 0) {
                    if (window.musicPlayer) {
                        // Add all songs to player queue
                        songIds.forEach(songId => {
                            fetch(`api/songs.php?id=${songId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    window.musicPlayer.addToQueue(data.song);
                                }
                            });
                        });
                        
                        // Play first song
                        fetch(`api/songs.php?id=${songIds[0]}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                window.musicPlayer.playSong(data.song);
                                showNotification(`Playing ${songIds.length} liked songs`);
                            }
                        });
                    } else {
                        // Fallback to localStorage
                        localStorage.setItem('meplay_queue', JSON.stringify(songIds));
                        localStorage.setItem('meplay_current_playlist', JSON.stringify({
                            ids: songIds,
                            titles: songTitles,
                            name: 'Liked Songs'
                        }));
                        showNotification(`Added ${songIds.length} songs to queue`);
                    }
                }
            });
        }
        
        // ====== MODAL FUNCTIONS ======
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('show');
                });
            });
        });
        
        // ====== CREATE PLAYLIST FORM ======
        const createForm = document.getElementById('createPlaylistForm');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const songId = formData.get('song_id');
                const title = formData.get('title');
                
                fetch('api/playlists.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        title: title,
                        description: formData.get('description'),
                        song_id: songId ? parseInt(songId) : null
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(`Playlist "${title}" created successfully`);
                        document.getElementById('createPlaylistModal').classList.remove('show');
                        this.reset();
                    } else {
                        showNotification(data.message || 'Failed to create playlist', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Network error', 'error');
                });
            });
        }
        
        console.log('All event listeners set up successfully');
    });
    </script>
</body>
</html>