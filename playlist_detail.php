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
    <style>
    /* Tambahan untuk Play All di playlist detail */
    #playPlaylist {
        background-color: var(--accent-color);
        color: white;
        border: none;
        padding: 0.75rem 2rem;
        border-radius: 2rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        transition: all 0.2s;
    }
    
    #playPlaylist:hover {
        background-color: var(--accent-hover);
        transform: scale(1.05);
    }
    
    /* Style untuk queue list */
    .queue-list {
        max-height: 400px;
        overflow-y: auto;
        margin-bottom: 1rem;
    }
    
    .queue-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        border-bottom: 1px solid var(--border-color);
        transition: background-color 0.2s;
    }
    
    .queue-item:hover {
        background-color: var(--bg-tertiary);
    }
    
    .queue-item.active {
        background-color: var(--accent-color-light);
        border-left: 3px solid var(--accent-color);
    }
    
    .queue-item-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 1;
    }
    
    .queue-item-info img {
        width: 40px;
        height: 40px;
        border-radius: 0.375rem;
        object-fit: cover;
    }
    
    .queue-item-info h4 {
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--text-primary);
    }
    
    .queue-item-info p {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-bottom: 0;
    }
    
    .queue-item-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .btn-icon {
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 0.25rem;
        transition: all 0.2s;
    }
    
    .btn-icon:hover {
        color: var(--text-primary);
        background-color: var(--bg-tertiary);
    }
    
    .queue-actions {
        display: flex;
        justify-content: flex-end;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
    }
    </style>
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
                        <button class="play-all-btn" id="playPlaylist" data-playlist-id="<?php echo $playlist_id; ?>">
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

  <!-- Script bagian Play All yang sudah diperbaiki -->
<script>
// ====== PLAYLIST DETAIL PAGE JAVASCRIPT ======
document.addEventListener('DOMContentLoaded', function() {
    console.log('Playlist detail page loaded');
    
    // ====== NOTIFICATION SYSTEM ======
    function showNotification(message, type = 'success') {
        // Remove any existing notifications
        document.querySelectorAll('.notification.show').forEach(notification => {
            notification.remove();
        });
        
        // Create new notification
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    // ====== PLAY ALL FUNCTIONALITY - FIXED ======
    const playAllBtn = document.getElementById('playPlaylist');
    if (playAllBtn) {
        playAllBtn.addEventListener('click', async function() {
            const playlistId = this.getAttribute('data-playlist-id');
            const playlistName = document.querySelector('.playlist-info-large h1').textContent;
            
            // Collect all song IDs from the page
            const songIds = [];
            document.querySelectorAll('.song-item').forEach(item => {
                const songId = item.getAttribute('data-song-id');
                if (songId) {
                    songIds.push(parseInt(songId));
                }
            });
            
            if (songIds.length === 0) {
                showNotification('No songs in playlist', 'error');
                return;
            }
            
            try {
                // Fetch first song data to play immediately
                const firstSongResponse = await fetch(`api/songs.php?id=${songIds[0]}`);
                const firstSongData = await firstSongResponse.json();
                
                if (!firstSongData.success) {
                    showNotification('Failed to load song data', 'error');
                    return;
                }
                
                // Clear existing queue
                if (window.musicPlayer) {
                    window.musicPlayer.clearQueue();
                    
                    // Play first song immediately
                    window.musicPlayer.playSong(firstSongData.song, 0, true);
                    
                    // Add remaining songs to queue
                    for (let i = 1; i < songIds.length; i++) {
                        try {
                            const songResponse = await fetch(`api/songs.php?id=${songIds[i]}`);
                            const songData = await songResponse.json();
                            
                            if (songData.success) {
                                window.musicPlayer.addToQueue(songData.song);
                            }
                        } catch (error) {
                            console.error('Error fetching song:', error);
                        }
                    }
                    
                    // Set playlist info for reference
                    window.musicPlayer.currentPlaylist = {
                        id: playlistId,
                        name: playlistName,
                        songIds: songIds
                    };
                    
                    showNotification(`Playing ${songIds.length} songs from "${playlistName}"`);
                    
                    // Auto-play first song
                    setTimeout(() => {
                        if (window.musicPlayer.audio) {
                            window.musicPlayer.play();
                        }
                    }, 500);
                    
                } else {
                    // Fallback: Save to localStorage
                    const songsData = [];
                    
                    // Try to get all songs data
                    for (let i = 0; i < Math.min(songIds.length, 10); i++) { // Limit to 10 songs for performance
                        try {
                            const songResponse = await fetch(`api/songs.php?id=${songIds[i]}`);
                            const songData = await songResponse.json();
                            
                            if (songData.success) {
                                songsData.push(songData.song);
                            }
                        } catch (error) {
                            console.error('Error fetching song:', error);
                        }
                    }
                    
                    if (songsData.length > 0) {
                        localStorage.setItem('meplay_queue', JSON.stringify(songIds));
                        localStorage.setItem('meplay_current_playlist', JSON.stringify({
                            ids: songIds,
                            songs: songsData,
                            name: playlistName
                        }));
                        
                        // Set first song as now playing
                        localStorage.setItem('meplay_now_playing', JSON.stringify(songsData[0]));
                        
                        showNotification(`Added ${songIds.length} songs from "${playlistName}" to queue`);
                        
                        // Reload page to trigger player update
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                }
            } catch (error) {
                console.error('Error playing playlist:', error);
                showNotification('Failed to play playlist', 'error');
            }
        });
    }
    
    // ====== INDIVIDUAL PLAY BUTTONS - IMPROVED ======
    document.querySelectorAll('.play-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.stopPropagation();
            
            const songId = this.getAttribute('data-song-id');
            const songItem = this.closest('.song-item');
            const songTitle = songItem ? songItem.querySelector('.song-info h4').textContent : 'Song';
            
            try {
                // Get song data
                const response = await fetch(`api/songs.php?id=${songId}`);
                const data = await response.json();
                
                if (data.success) {
                    if (window.musicPlayer) {
                        // Clear queue and add this song
                        window.musicPlayer.clearQueue();
                        window.musicPlayer.playSong(data.song, 0, true);
                        
                        // Get all other songs in playlist
                        const allSongIds = [];
                        document.querySelectorAll('.song-item').forEach(item => {
                            const id = item.getAttribute('data-song-id');
                            if (id && id !== songId) {
                                allSongIds.push(parseInt(id));
                            }
                        });
                        
                        // Add other songs to queue (async, don't wait)
                        setTimeout(async () => {
                            for (const id of allSongIds) {
                                try {
                                    const songResponse = await fetch(`api/songs.php?id=${id}`);
                                    const songData = await songResponse.json();
                                    
                                    if (songData.success) {
                                        window.musicPlayer.addToQueue(songData.song);
                                    }
                                } catch (error) {
                                    console.error('Error fetching song:', error);
                                }
                            }
                        }, 0);
                        
                        showNotification(`Playing "${songTitle}"`);
                    } else {
                        // Fallback
                        localStorage.setItem('meplay_now_playing', JSON.stringify({
                            id: songId,
                            title: songTitle
                        }));
                        showNotification(`Playing "${songTitle}"`);
                    }
                } else {
                    showNotification('Failed to play song', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error', 'error');
            }
        });
    });
    
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
            !e.target.closest('.song-dropdown') && 
            !e.target.closest('.playlist-dropdown')) {
            closeDropdowns();
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDropdowns();
    });
    
    // ====== PLAYLIST DROPDOWN ======
    const playlistBtn = document.getElementById('playlistMoreBtn');
    if (playlistBtn) {
        playlistBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            
            const dropdown = document.getElementById('playlistDropdown');
            if (dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
                activeDropdown = null;
            } else {
                closeDropdowns();
                dropdown.style.display = 'block';
                dropdown.style.position = 'absolute';
                dropdown.style.top = '45px';
                dropdown.style.right = '0';
                activeDropdown = dropdown;
            }
        });
    }
    
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
                dropdown.style.position = 'fixed';
                
                const rect = this.getBoundingClientRect();
                dropdown.style.top = (rect.bottom + 5) + 'px';
                dropdown.style.left = (rect.right - dropdown.offsetWidth) + 'px';
                
                activeDropdown = dropdown;
            }
        });
    });
    
    // ====== REMOVE SONG FROM PLAYLIST ======
    document.querySelectorAll('.remove-from-playlist').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const songId = this.getAttribute('data-song-id');
            const playlistId = this.getAttribute('data-playlist-id');
            const songItem = this.closest('.song-item');
            const songTitle = songItem ? songItem.querySelector('.song-info h4').textContent : 'Song';
            
            closeDropdowns();
            
            if (confirm(`Remove "${songTitle}" from playlist?`)) {
                // Show loading
                if (songItem) {
                    songItem.style.opacity = '0.5';
                    songItem.style.pointerEvents = 'none';
                }
                
                // API call
                fetch('api/playlists.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'remove_song',
                        song_id: parseInt(songId),
                        playlist_id: parseInt(playlistId)
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove from DOM with animation
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
                                    
                                    // Update song count
                                    const remaining = document.querySelectorAll('.song-item').length;
                                    const countEl = document.querySelector('.playlist-meta span:first-child');
                                    if (countEl) {
                                        countEl.textContent = remaining + ' ' + 
                                            (remaining === 1 ? 'song' : 'songs');
                                    }
                                    
                                    // Show notification
                                    showNotification(`"${songTitle}" removed from playlist`);
                                    
                                    // Update Play All button if no songs left
                                    if (remaining === 0) {
                                        const playAllBtn = document.getElementById('playPlaylist');
                                        if (playAllBtn) {
                                            playAllBtn.style.display = 'none';
                                        }
                                    }
                                    
                                    // Reload if no songs left
                                    if (remaining === 0) {
                                        setTimeout(() => location.reload(), 1000);
                                    }
                                }, 300);
                            }, 100);
                        }
                    } else {
                        showNotification(data.message || 'Failed to remove song', 'error');
                        if (songItem) {
                            songItem.style.opacity = '1';
                            songItem.style.pointerEvents = 'auto';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Network error', 'error');
                    if (songItem) {
                        songItem.style.opacity = '1';
                        songItem.style.pointerEvents = 'auto';
                    }
                });
            }
            
            return false;
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
                    action: isLiked ? 'unlike' : 'like'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (isLiked) {
                        this.classList.remove('liked');
                        this.innerHTML = '<i class="far fa-heart"></i> Like';
                        showNotification(`Removed "${songTitle}" from liked songs`);
                    } else {
                        this.classList.add('liked');
                        this.innerHTML = '<i class="fas fa-heart"></i> Unlike';
                        showNotification(`Added "${songTitle}" to liked songs`);
                    }
                } else {
                    showNotification(data.message || 'Failed to update like', 'error');
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
    
    // ====== MODAL FUNCTIONS ======
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.classList.remove('show');
            });
        });
    });
    
    // Edit Playlist
    const editBtn = document.getElementById('editPlaylistBtn');
    if (editBtn) {
        editBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            closeDropdowns();
            document.getElementById('editPlaylistModal').classList.add('show');
        });
    }
    
    // Delete Playlist
    const deleteBtn = document.getElementById('deletePlaylistBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            closeDropdowns();
            document.getElementById('deletePlaylistModal').classList.add('show');
        });
    }
    
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