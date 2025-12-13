<?php
// includes/header.php
?>
<style>
#search-input {
    flex: 1;
    border: none;
    background: transparent;
    color: #000000; /* GANTI: dari var(--text-primary) ke hitam */
    font-size: 0.9rem;
    outline: none;
    width: 100%;
}

#search-input::placeholder {
    color: var(--text-secondary);
}

.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background-color: var(--bg-primary);
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    margin-top: 8px;
    border: 1px solid var(--border-color);
}

.search-suggestions.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

/* Spotify-style suggestion items */
.suggestion-item {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    cursor: pointer;
    transition: background-color 0.2s;
    text-decoration: none;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border-color);
}

.suggestion-item:hover {
    background-color: var(--bg-hover);
}

.suggestion-item:last-child {
    border-bottom: none;
}

.suggestion-item img {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    margin-right: 12px;
    object-fit: cover;
}

.artist-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 12px;
    background-color: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.artist-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    margin-right: 0;
}

.playlist-icon {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    margin-right: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.suggestion-info {
    flex: 1;
    min-width: 0;
}

.suggestion-info h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.suggestion-info p {
    font-size: 12px;
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.suggestion-type {
    font-size: 11px;
    color: var(--accent-color);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* View all results button - GANTI WARNA BIRU */
.view-all-results {
    padding: 12px 16px;
    text-align: center;
    background-color: var(--bg-secondary);
    color: #1e90ff; /* GANTI: biru cerah */
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.2s, color 0.2s;
    border-top: 1px solid var(--border-color);
    text-decoration: none;
    display: block;
}

.view-all-results:hover {
    background-color: var(--bg-hover);
    color: #007bff; /* GANTI: biru lebih gelap saat hover */
}

/* Loading and error states */
.suggestion-item.loading,
.suggestion-item.no-results,
.suggestion-item.error {
    justify-content: space-between;
    align-items: center;
    color: var(--text-secondary);
}

.suggestion-item.loading .spinner {
    width: 16px;
    height: 16px;
    border: 2px solid var(--border-color);
    border-top-color: var(--accent-color);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Category headers */
.suggestion-category {
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-secondary);
    background-color: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
}

/* Scrollbar styling */
.search-suggestions::-webkit-scrollbar {
    width: 8px;
}

.search-suggestions::-webkit-scrollbar-track {
    background: transparent;
}

.search-suggestions::-webkit-scrollbar-thumb {
    background: var(--bg-secondary);
    border-radius: 4px;
}

.search-suggestions::-webkit-scrollbar-thumb:hover {
    background: var(--border-color);
}
</style>

<header class="header">
    <div class="header-left">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="search-suggestions-container">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" 
                       placeholder="What do you want to listen to?" 
                       id="search-input" 
                       value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
                       autocomplete="off"
                       aria-label="Search songs, artists, albums">
                <div class="search-suggestions" id="searchSuggestions"></div>
            </div>
        </div>
    </div>
    <div class="header-right">
        <button class="theme-toggle" id="themeToggle" title="Toggle theme">
            <i class="fas fa-moon"></i>
        </button>
        <div class="user-menu">
            <img src="<?php echo $_SESSION['profile_picture'] ? 'uploads/users/' . $_SESSION['profile_picture'] : 'assets/images/default-avatar.png'; ?>" 
                 alt="<?php echo $_SESSION['username']; ?>" 
                 class="user-avatar"
                 onerror="this.src='assets/images/default-avatar.png'">
            <span class="username"><?php echo $_SESSION['username']; ?></span>
            <div class="dropdown-menu">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <?php if (isAdmin()): ?>
                <a href="admin/dashboard.php"><i class="fas fa-shield-alt"></i> Admin Panel</a>
                <?php endif; ?>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</header>