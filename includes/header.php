<?php
// includes/header.php
?>
<header class="header">
    <div class="header-left">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search songs, artists, albums..." id="search-input" 
                   value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
            <div class="search-suggestions" id="searchSuggestions"></div>
        </div>
    </div>
    <div class="header-right">
        <button class="theme-toggle" id="themeToggle" title="Toggle theme">
            <i class="fas fa-moon"></i>
        </button>
        <div class="user-menu">
            <img src="<?php echo $_SESSION['profile_picture'] ? 'uploads/users/' . $_SESSION['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                 alt="<?php echo $_SESSION['username']; ?>" class="user-avatar">
            <span class="username"><?php echo $_SESSION['username']; ?></span>
            <div class="dropdown-menu">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="library.php"><i class="fas fa-heart"></i> My Library</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <?php if (isAdmin()): ?>
                <a href="admin/dashboard.php"><i class="fas fa-shield-alt"></i> Admin Panel</a>
                <?php endif; ?>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</header>