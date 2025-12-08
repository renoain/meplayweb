<?php
// includes/sidebar.php

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define SITE_NAME if not defined
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'MePlay');
}

$current_page = basename($_SERVER['PHP_SELF']);

// Get user's playlists count for display
$playlists_count = 0;
$likes_count = 0;

if (isset($_SESSION['user_id'])) {
    // Use relative path that works from any directory
    $config_path = __DIR__ . '/../config/database.php';
    if (file_exists($config_path)) {
        require_once $config_path;
        
        try {
            $database = new Database();
            $conn = $database->getConnection();
            $user_id = $_SESSION['user_id'];
            
            // Get playlists count
            $playlists_query = "SELECT COUNT(*) as count FROM playlists WHERE user_id = ?";
            $playlists_stmt = $conn->prepare($playlists_query);
            $playlists_stmt->execute([$user_id]);
            $playlists_result = $playlists_stmt->fetch(PDO::FETCH_ASSOC);
            $playlists_count = $playlists_result ? $playlists_result['count'] : 0;
            
            // Get liked songs count
            $likes_query = "SELECT COUNT(*) as count FROM user_likes WHERE user_id = ?";
            $likes_stmt = $conn->prepare($likes_query);
            $likes_stmt->execute([$user_id]);
            $likes_result = $likes_stmt->fetch(PDO::FETCH_ASSOC);
            $likes_count = $likes_result ? $likes_result['count'] : 0;
            
        } catch (Exception $e) {
            // Silent fail - just use default counts
            error_log("Sidebar database error: " . $e->getMessage());
        }
    }
}
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fa-solid fa-music"></i>
            <span><?php echo SITE_NAME; ?></span>
        </div>
        <button class="sidebar-close" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <li class="nav-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Beranda</span>
                </a>
            </li>
            <li class="nav-item <?php echo $current_page == 'search.php' ? 'active' : ''; ?>">
                <a href="search.php" class="nav-link">
                    <i class="fas fa-search"></i>
                    <span>Cari</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-section">
            <div class="section-header">
                <button class="create-playlist-btn" id="sidebarCreatePlaylist" title="Create Playlist">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <ul>
                <li class="nav-item <?php echo $current_page == 'likes.php' ? 'active' : ''; ?>">
                    <a href="likes.php" class="nav-link">
                        <i class="fas fa-heart"></i>
                        <span>Liked Songs</span>
                        <?php if ($likes_count > 0): ?>
                        <span class="nav-count"><?php echo $likes_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item <?php echo $current_page == 'playlists.php' ? 'active' : ''; ?>">
                    <a href="playlists.php" class="nav-link">
                        <i class="fas fa-list"></i>
                        <span>Playlists</span>
                        <?php if ($playlists_count > 0): ?>
                        <span class="nav-count"><?php echo $playlists_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <a href="settings.php" class="nav-link">
            <i class="fas fa-cog"></i>
            <span>Pengaturan</span>
        </a>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <a href="admin/dashboard.php" class="nav-link">
            <i class="fas fa-shield-alt"></i>
            <span>Admin Panel</span>
        </a>
        <?php endif; ?>
        <a href="logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Create Playlist Modal for Sidebar -->
<div id="sidebarCreatePlaylistModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create New Playlist</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="sidebarCreatePlaylistForm">
                <div class="form-group">
                    <label for="sidebarPlaylistTitle">Playlist Title</label>
                    <input type="text" id="sidebarPlaylistTitle" name="title" required placeholder="My Awesome Playlist">
                </div>
                <div class="form-group">
                    <label for="sidebarPlaylistDescription">Description (Optional)</label>
                    <textarea id="sidebarPlaylistDescription" name="description" placeholder="Describe your playlist..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary close-modal">Cancel</button>
                    <button type="submit" class="btn-primary">Create Playlist</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Sidebar create playlist functionality
document.addEventListener('DOMContentLoaded', function() {
    const createPlaylistBtn = document.getElementById('sidebarCreatePlaylist');
    const modal = document.getElementById('sidebarCreatePlaylistModal');
    const form = document.getElementById('sidebarCreatePlaylistForm');
    const closeButtons = modal?.querySelectorAll('.close-modal');
    
    if (createPlaylistBtn && modal) {
        createPlaylistBtn.addEventListener('click', function() {
            modal.classList.add('show');
        });
        
        closeButtons?.forEach(button => {
            button.addEventListener('click', function() {
                modal.classList.remove('show');
            });
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        });
        
        // Handle form submission
        if (form) {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                const title = formData.get('title');
                
                if (!title.trim()) {
                    showNotification('Playlist title is required', 'error');
                    return;
                }
                
                try {
                    const response = await fetch('api/playlists.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'create',
                            title: title.trim(),
                            description: formData.get('description').trim()
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showNotification('Playlist created successfully');
                        modal.classList.remove('show');
                        form.reset();
                        
                        // Redirect to playlists page
                        setTimeout(() => {
                            window.location.href = 'playlists.php';
                        }, 1000);
                    } else {
                        showNotification(data.message || 'Error creating playlist', 'error');
                    }
                } catch (error) {
                    console.error('Create playlist error:', error);
                    showNotification('Error creating playlist', 'error');
                }
            });
        }
    }
});
</script>