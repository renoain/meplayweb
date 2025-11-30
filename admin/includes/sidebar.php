<?php
// admin/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="admin-sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-music"></i>
            <span><?php echo SITE_NAME; ?> Admin</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <ul class="sidebar-menu">
        <li class="menu-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <li class="menu-item <?php echo $current_page == 'songs.php' ? 'active' : ''; ?>">
            <a href="songs.php">
                <i class="fas fa-music"></i>
                <span>Songs</span>
            </a>
        </li>
        
        <li class="menu-item <?php echo $current_page == 'artists.php' ? 'active' : ''; ?>">
            <a href="artists.php">
                <i class="fas fa-user"></i>
                <span>Artis</span>
            </a>
        </li>
        
        <li class="menu-item <?php echo $current_page == 'albums.php' ? 'active' : ''; ?>">
            <a href="albums.php">
                <i class="fas fa-compact-disc"></i>
                <span>Album</span>
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <a href="../index.php" class="sidebar-link" target="_blank">
            <i class="fas fa-external-link-alt"></i>
            <span>Ke Website</span>
        </a>
        <a href="../logout.php" class="sidebar-link logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</nav>