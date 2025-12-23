 
<?php
// File: includes/admin-header.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<header class="admin-header">
    <div class="header-left">
        <h1>Dashboard</h1>
    </div>
    <div class="header-right">
        <!-- Quick Links Dropdown -->
        <div class="quick-links-dropdown">
            <button class="quick-links-btn" title="Quick Links">
                <i class="fas fa-bolt"></i>
                <span>Quick Links</span>
                <i class="fas fa-chevron-down"></i>
            </button>
            
            <div class="dropdown-menu">
                <a href="songs.php" class="dropdown-item <?php echo $current_page == 'songs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-music"></i>
                    <span>Songs</span>
                </a>
                <a href="artists.php" class="dropdown-item <?php echo $current_page == 'artists.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>Artis</span>
                </a>
                <a href="albums.php" class="dropdown-item <?php echo $current_page == 'albums.php' ? 'active' : ''; ?>">
                    <i class="fas fa-compact-disc"></i>
                    <span>Album</span>
                </a>
                <a href="genres.php" class="dropdown-item <?php echo $current_page == 'genres.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tag"></i>
                    <span>Genre</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="../index.php" class="dropdown-item">
                    <i class="fas fa-globe"></i>
                    <span>Website</span>
                </a>
                <a href="../logout.php" class="dropdown-item text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- User Profile -->
        <div class="user-menu">
            <img src="../uploads/users/<?php echo $_SESSION['profile_picture']; ?>" 
                 alt="Profile" class="user-avatar">
            <span><?php echo $_SESSION['username']; ?></span>
        </div>
    </div>
</header>
 