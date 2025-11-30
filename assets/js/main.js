// assets/js/main.js

/**
 * MePlay - Main Application Class
 * Handles theme management, UI interactions, and global functionality
 */
class MePlayApp {
  constructor() {
    this.currentTheme = this.getStoredTheme();
    this.isSidebarCollapsed = false;
    this.activeDropdown = null;
    this.searchTimeout = null;

    this.init();
  }

  /**
   * Initialize the application
   */
  init() {
    this.applyTheme();
    this.initThemeToggle();
    this.initSidebar();
    this.initDropdowns();
    this.initModals();
    this.initSearch();
    this.initNavigation();
    this.bindGlobalEvents();

    console.log("MePlay App initialized");
  }

  /**
   * Theme Management
   */
  getStoredTheme() {
    const stored = localStorage.getItem("meplay-theme");
    if (stored) return stored;

    // Check system preference
    if (
      window.matchMedia &&
      window.matchMedia("(prefers-color-scheme: dark)").matches
    ) {
      return "dark";
    }

    return "light";
  }

  applyTheme() {
    document.body.setAttribute("data-theme", this.currentTheme);
    localStorage.setItem("meplay-theme", this.currentTheme);

    // Update theme color meta tag for mobile browsers
    this.updateThemeMeta();
  }

  updateThemeMeta() {
    let metaThemeColor = document.querySelector('meta[name="theme-color"]');
    if (!metaThemeColor) {
      metaThemeColor = document.createElement("meta");
      metaThemeColor.name = "theme-color";
      document.head.appendChild(metaThemeColor);
    }

    if (this.currentTheme === "dark") {
      metaThemeColor.content = "#1a202c";
    } else {
      metaThemeColor.content = "#667eea";
    }
  }

  initThemeToggle() {
    const themeToggle = document.getElementById("themeToggle");
    if (!themeToggle) return;

    this.updateThemeIcon();

    themeToggle.addEventListener("click", () => {
      this.toggleTheme();
    });

    // Listen for system theme changes
    if (window.matchMedia) {
      window
        .matchMedia("(prefers-color-scheme: dark)")
        .addEventListener("change", (e) => {
          if (!localStorage.getItem("meplay-theme")) {
            this.currentTheme = e.matches ? "dark" : "light";
            this.applyTheme();
            this.updateThemeIcon();
          }
        });
    }
  }

  toggleTheme() {
    this.currentTheme = this.currentTheme === "light" ? "dark" : "light";
    this.applyTheme();
    this.updateThemeIcon();

    // Dispatch custom event for other components
    document.dispatchEvent(
      new CustomEvent("themeChanged", {
        detail: { theme: this.currentTheme },
      })
    );
  }

  updateThemeIcon() {
    const themeIcon = document.querySelector("#themeToggle i");
    if (themeIcon) {
      themeIcon.className =
        this.currentTheme === "dark" ? "fas fa-sun" : "fas fa-moon";
    }
  }

  /**
   * Sidebar Management
   */
  initSidebar() {
    const sidebarToggle = document.getElementById("sidebarToggle");
    const mainContent = document.querySelector(".main-content");
    const sidebar = document.getElementById("sidebar");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    if (!sidebarToggle || !mainContent || !sidebar) return;

    // Load sidebar state
    this.isSidebarCollapsed =
      localStorage.getItem("sidebar-collapsed") === "true";
    if (this.isSidebarCollapsed) {
      mainContent.classList.add("sidebar-collapsed");
      sidebar.classList.add("collapsed");
    }

    sidebarToggle.addEventListener("click", () => {
      this.toggleSidebar();
    });

    // Mobile sidebar handling
    if (sidebarOverlay) {
      sidebarOverlay.addEventListener("click", () => {
        this.hideMobileSidebar();
      });
    }

    // Close sidebar when clicking on nav links (mobile)
    if (window.innerWidth <= 768) {
      document.querySelectorAll(".sidebar-nav a").forEach((link) => {
        link.addEventListener("click", () => {
          this.hideMobileSidebar();
        });
      });
    }
  }

  toggleSidebar() {
    const mainContent = document.querySelector(".main-content");
    const sidebar = document.getElementById("sidebar");

    this.isSidebarCollapsed = !this.isSidebarCollapsed;

    mainContent.classList.toggle("sidebar-collapsed", this.isSidebarCollapsed);
    sidebar.classList.toggle("collapsed", this.isSidebarCollapsed);

    localStorage.setItem("sidebar-collapsed", this.isSidebarCollapsed);
  }

  hideMobileSidebar() {
    const sidebar = document.getElementById("sidebar");
    const sidebarOverlay = document.getElementById("sidebarOverlay");

    if (sidebar) sidebar.classList.remove("show");
    if (sidebarOverlay) sidebarOverlay.classList.remove("show");
  }

  /**
   * Dropdown Management
   */
  initDropdowns() {
    // Close dropdowns when clicking outside
    document.addEventListener("click", (e) => {
      if (
        !e.target.closest(".song-actions") &&
        !e.target.closest(".user-menu") &&
        !e.target.closest(".dropdown-menu")
      ) {
        this.closeAllDropdowns();
      }
    });

    // Handle song action dropdowns
    document.querySelectorAll(".more-btn").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.stopPropagation();
        const dropdown = button.nextElementSibling;
        this.toggleDropdown(dropdown);
      });
    });

    // Handle user menu dropdown
    const userMenu = document.querySelector(".user-menu");
    if (userMenu) {
      userMenu.addEventListener("click", (e) => {
        e.stopPropagation();
        const dropdown = userMenu.querySelector(".dropdown-menu");
        this.toggleDropdown(dropdown);
      });
    }

    // Close dropdown when item is clicked
    document
      .querySelectorAll(".dropdown-item:not(.disabled)")
      .forEach((item) => {
        item.addEventListener("click", (e) => {
          e.stopPropagation();
          this.closeAllDropdowns();
        });
      });

    // Handle submenu hover
    document.querySelectorAll(".dropdown-submenu").forEach((submenu) => {
      submenu.addEventListener("mouseenter", () => {
        const submenuElement = submenu.querySelector(".submenu");
        if (submenuElement) {
          this.positionSubmenu(submenuElement);
        }
      });
    });
  }

  toggleDropdown(dropdown) {
    if (!dropdown) return;

    // Close all other dropdowns
    if (this.activeDropdown && this.activeDropdown !== dropdown) {
      this.hideDropdown(this.activeDropdown);
    }

    // Toggle current dropdown
    if (dropdown.classList.contains("active")) {
      this.hideDropdown(dropdown);
      this.activeDropdown = null;
    } else {
      this.showDropdown(dropdown);
      this.activeDropdown = dropdown;
    }
  }

  showDropdown(dropdown) {
    dropdown.classList.add("active");
    dropdown.style.opacity = "1";
    dropdown.style.visibility = "visible";
    dropdown.style.transform = "translateY(0)";
  }

  hideDropdown(dropdown) {
    dropdown.classList.remove("active");
    dropdown.style.opacity = "0";
    dropdown.style.visibility = "hidden";
    dropdown.style.transform = "translateY(-10px)";
  }

  closeAllDropdowns() {
    document.querySelectorAll(".dropdown-menu").forEach((dropdown) => {
      this.hideDropdown(dropdown);
    });
    this.activeDropdown = null;
  }

  positionSubmenu(submenu) {
    const rect = submenu.getBoundingClientRect();
    const viewportWidth = window.innerWidth;

    // If submenu would go off-screen, position it to the left instead
    if (rect.right > viewportWidth - 20) {
      submenu.style.left = "auto";
      submenu.style.right = "100%";
    } else {
      submenu.style.left = "100%";
      submenu.style.right = "auto";
    }
  }

  /**
   * Modal Management
   */
  initModals() {
    // Create Playlist Modal
    const createPlaylistModal = document.getElementById("createPlaylistModal");
    const closeModalButtons = document.querySelectorAll(".close-modal");
    const createPlaylistButtons = document.querySelectorAll(".create-playlist");

    createPlaylistButtons.forEach((button) => {
      button.addEventListener("click", () => {
        const songId = button.getAttribute("data-song-id");
        this.showCreatePlaylistModal(songId);
      });
    });

    closeModalButtons.forEach((button) => {
      button.addEventListener("click", () => {
        this.hideModal(createPlaylistModal);
      });
    });

    // Close modal when clicking outside
    window.addEventListener("click", (e) => {
      if (e.target === createPlaylistModal) {
        this.hideModal(createPlaylistModal);
      }
    });

    // Handle create playlist form submission
    const createPlaylistForm = document.getElementById("createPlaylistForm");
    if (createPlaylistForm) {
      createPlaylistForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.handleCreatePlaylist();
      });
    }
  }

  showCreatePlaylistModal(songId) {
    const modal = document.getElementById("createPlaylistModal");
    if (!modal) return;

    document.getElementById("songIdForPlaylist").value = songId || "";
    this.showModal(modal);
  }

  showModal(modal) {
    if (!modal) return;

    modal.classList.add("show");
    document.body.style.overflow = "hidden";

    // Focus on first input
    const firstInput = modal.querySelector("input, textarea, select");
    if (firstInput) {
      setTimeout(() => firstInput.focus(), 100);
    }
  }

  hideModal(modal) {
    if (!modal) return;

    modal.classList.remove("show");
    document.body.style.overflow = "";

    // Reset form
    const form = modal.querySelector("form");
    if (form) {
      form.reset();
    }
  }

  async handleCreatePlaylist() {
    const form = document.getElementById("createPlaylistForm");
    if (!form) return;

    const formData = new FormData(form);
    const title = formData.get("title");
    const description = formData.get("description");
    const songId = formData.get("song_id");

    if (!title.trim()) {
      showNotification("Playlist title is required", "error");
      return;
    }

    try {
      const response = await fetch("api/playlists.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "create",
          title: title.trim(),
          description: description.trim(),
          song_id: songId || null,
        }),
      });

      const data = await response.json();

      if (data.success) {
        showNotification("Playlist created successfully");
        this.hideModal(document.getElementById("createPlaylistModal"));

        // Refresh playlists dropdown if exists
        this.refreshPlaylistsDropdown();
      } else {
        showNotification(data.message || "Error creating playlist", "error");
      }
    } catch (error) {
      console.error("Create playlist error:", error);
      showNotification("Error creating playlist", "error");
    }
  }

  refreshPlaylistsDropdown() {
    // This would refresh the playlists dropdown in all song cards
    // Implementation depends on specific requirements
    document.dispatchEvent(new CustomEvent("playlistsUpdated"));
  }

  /**
   * Search Functionality
   */
  initSearch() {
    const searchInput = document.getElementById("search-input");
    if (!searchInput) return;

    // Clear existing timeout
    if (this.searchTimeout) {
      clearTimeout(this.searchTimeout);
    }

    searchInput.addEventListener("input", (e) => {
      const query = e.target.value.trim();

      if (query.length === 0) {
        this.hideSearchSuggestions();
        return;
      }

      // Debounce search
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        if (query.length > 1) {
          this.performSearch(query);
        }
      }, 300);
    });

    searchInput.addEventListener("focus", () => {
      const query = searchInput.value.trim();
      if (query.length > 1) {
        this.performSearch(query);
      }
    });

    // Handle Enter key for instant search
    searchInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        const query = searchInput.value.trim();
        if (query) {
          window.location.href = `search.php?q=${encodeURIComponent(query)}`;
        }
        this.hideSearchSuggestions();
      }
    });

    // Hide suggestions when clicking outside
    document.addEventListener("click", (e) => {
      if (!e.target.closest(".search-box")) {
        this.hideSearchSuggestions();
      }
    });
  }

  async performSearch(query) {
    try {
      const response = await fetch(
        `api/search.php?q=${encodeURIComponent(query)}&limit=5`
      );
      const data = await response.json();

      if (data.success) {
        this.showSearchSuggestions(data.results, query);
      } else {
        this.hideSearchSuggestions();
      }
    } catch (error) {
      console.error("Search error:", error);
      this.hideSearchSuggestions();
    }
  }

  showSearchSuggestions(results, query) {
    const searchSuggestions = document.getElementById("searchSuggestions");
    if (!searchSuggestions) return;

    if (results.length === 0) {
      searchSuggestions.innerHTML = `
                <div class="suggestion-item">
                    <div class="suggestion-info">
                        <p>No results found for "${this.escapeHtml(query)}"</p>
                    </div>
                </div>
                <a href="search.php?q=${encodeURIComponent(
                  query
                )}" class="suggestion-item view-all">
                    <div class="suggestion-info">
                        <p><strong>Search for "${this.escapeHtml(
                          query
                        )}"</strong></p>
                    </div>
                </a>
            `;
    } else {
      const suggestionsHTML = results
        .map(
          (song) => `
                <a href="song.php?id=${song.id}" class="suggestion-item">
                    <img src="${this.getSongCover(song.cover_image)}" 
                         alt="${this.escapeHtml(song.title)}">
                    <div class="suggestion-info">
                        <h4>${this.escapeHtml(song.title)}</h4>
                        <p>${this.escapeHtml(song.artist_name)}</p>
                        <span class="suggestion-type">Song</span>
                    </div>
                </a>
            `
        )
        .join("");

      // Add "View all results" link
      const viewAllHTML = `
                <a href="search.php?q=${encodeURIComponent(
                  query
                )}" class="suggestion-item view-all">
                    <div class="suggestion-info">
                        <p><strong>View all results for "${this.escapeHtml(
                          query
                        )}"</strong></p>
                    </div>
                </a>
            `;

      searchSuggestions.innerHTML = suggestionsHTML + viewAllHTML;
    }

    searchSuggestions.classList.add("show");
  }

  hideSearchSuggestions() {
    const searchSuggestions = document.getElementById("searchSuggestions");
    if (searchSuggestions) {
      searchSuggestions.classList.remove("show");
    }
  }

  getSongCover(cover_image) {
    if (cover_image && cover_image !== "default-cover.png") {
      return cover_image.startsWith("uploads/")
        ? cover_image
        : "uploads/covers/" + cover_image;
    }
    return "assets/images/covers/default-cover.png";
  }

  /**
   * Navigation
   */
  initNavigation() {
    // Handle navigation clicks for smooth transitions
    document.addEventListener("click", (e) => {
      const link = e.target.closest("a");
      if (
        link &&
        link.href &&
        !link.target &&
        link.href.startsWith(window.location.origin)
      ) {
        this.handleNavigation(link);
      }
    });

    // Add loading state to page transitions
    window.addEventListener("beforeunload", () => {
      this.showLoading();
    });
  }

  handleNavigation(link) {
    // Add any pre-navigation logic here
    // For example, save current state, show loading indicator, etc.

    // If it's a same-page anchor link, smooth scroll
    if (link.hash && link.pathname === window.location.pathname) {
      e.preventDefault();
      this.scrollToAnchor(link.hash);
    }
  }

  scrollToAnchor(anchor) {
    const target = document.querySelector(anchor);
    if (target) {
      target.scrollIntoView({ behavior: "smooth" });
    }
  }

  showLoading() {
    // You can add a loading indicator here if needed
    document.body.classList.add("page-transition");
  }

  /**
   * Global Event Handlers
   */
  bindGlobalEvents() {
    // Handle add to queue buttons
    document.addEventListener("click", (e) => {
      if (e.target.closest(".add-to-queue")) {
        e.preventDefault();
        const button = e.target.closest(".add-to-queue");
        const songId = button.getAttribute("data-song-id");
        this.addToQueue(songId);
      }

      // Like functionality is now handled by LikesManager
      // if (e.target.closest(".like-song")) {
      //   e.preventDefault();
      //   // Let LikesManager handle this
      // }

      if (e.target.closest(".add-to-playlist")) {
        e.preventDefault();
        const button = e.target.closest(".add-to-playlist");
        const songId = button.getAttribute("data-song-id");
        const playlistId = button.getAttribute("data-playlist-id");
        this.addToPlaylist(songId, playlistId);
      }
    });

    // Handle play buttons
    document.addEventListener("click", (e) => {
      if (e.target.closest(".play-btn")) {
        e.preventDefault();
        const button = e.target.closest(".play-btn");
        const songCard = button.closest("[data-song-id]");
        const songId = songCard.getAttribute("data-song-id");
        this.playSong(songId);
      }
    });

    // Handle song item clicks (for play on entire item click)
    document.addEventListener("click", (e) => {
      const songItem = e.target.closest(".song-item");
      if (
        songItem &&
        !e.target.closest(".song-actions") &&
        !e.target.closest(".play-btn") &&
        !e.target.closest(".like-song")
      ) {
        const songId = songItem.getAttribute("data-song-id");
        this.playSong(songId);
      }
    });

    // Handle window resize
    window.addEventListener("resize", () => {
      this.handleResize();
    });

    // Listen for player state changes
    document.addEventListener("playerStateChanged", (e) => {
      this.handlePlayerStateChange(e.detail);
    });

    // Listen for like updates from LikesManager
    document.addEventListener("likeUpdated", (e) => {
      this.handleLikeUpdate(e.detail);
    });
  }

  handleLikeUpdate(detail) {
    const { songId, isLiked } = detail;

    // Update all like buttons for this song
    this.updateAllLikeButtonsForSong(songId, isLiked);

    // Update player like button if this is the current song
    if (
      window.musicPlayer &&
      window.musicPlayer.currentSong &&
      window.musicPlayer.currentSong.id == songId
    ) {
      this.updatePlayerLikeButton(isLiked);
    }
  }

  updateAllLikeButtonsForSong(songId, isLiked) {
    // Update all like buttons for this song on the page
    document
      .querySelectorAll(`.like-song[data-song-id="${songId}"]`)
      .forEach((button) => {
        this.updateLikeButton(button, isLiked);
      });
  }

  updateLikeButton(button, isLiked) {
    if (isLiked) {
      button.innerHTML = '<i class="fas fa-heart"></i> Unlike';
      button.classList.add("liked");
    } else {
      button.innerHTML = '<i class="far fa-heart"></i> Like';
      button.classList.remove("liked");
    }
  }

  updatePlayerLikeButton(isLiked) {
    const playerLikeBtn = document.getElementById("nowPlayingLike");
    if (playerLikeBtn) {
      if (isLiked) {
        playerLikeBtn.innerHTML = '<i class="fas fa-heart"></i>';
        playerLikeBtn.classList.add("liked");
      } else {
        playerLikeBtn.innerHTML = '<i class="far fa-heart"></i>';
        playerLikeBtn.classList.remove("liked");
      }
    }
  }

  async addToQueue(songId) {
    try {
      const response = await fetch(`api/songs.php?id=${songId}`);
      const data = await response.json();

      if (data.success && window.musicPlayer) {
        window.musicPlayer.addToQueue(data.song);
        showNotification("Added to queue");
      }
    } catch (error) {
      console.error("Error adding to queue:", error);
      showNotification("Error adding to queue", "error");
    }
  }

  async playSong(songId) {
    try {
      const response = await fetch(`api/songs.php?id=${songId}`);
      const data = await response.json();

      if (data.success && window.musicPlayer) {
        window.musicPlayer.playSong(data.song);
      } else {
        showNotification("Error loading song", "error");
      }
    } catch (error) {
      console.error("Error playing song:", error);
      showNotification("Error playing song", "error");
    }
  }

  async addToPlaylist(songId, playlistId) {
    try {
      const response = await fetch("api/playlists.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "add_song",
          playlist_id: playlistId,
          song_id: songId,
        }),
      });

      const data = await response.json();

      if (data.success) {
        showNotification("Added to playlist");
      } else {
        showNotification(data.message || "Error adding to playlist", "error");
      }
    } catch (error) {
      console.error("Playlist error:", error);
      showNotification("Error adding to playlist", "error");
    }
  }

  handleResize() {
    // Close dropdowns on mobile when resizing
    if (window.innerWidth <= 768) {
      this.closeAllDropdowns();
    }

    // Reposition any open submenus
    document.querySelectorAll(".submenu.active").forEach((submenu) => {
      this.positionSubmenu(submenu);
    });
  }

  handlePlayerStateChange(state) {
    // Handle player state changes if needed
    // For example, update UI elements based on player state
  }

  /**
   * Utility Methods
   */
  escapeHtml(unsafe) {
    return unsafe
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  /**
   * Public Methods
   */
  refreshUI() {
    // Refresh any dynamic UI elements
    this.updateThemeIcon();
    this.closeAllDropdowns();
  }

  // Method to manually trigger search
  search(query) {
    const searchInput = document.getElementById("search-input");
    if (searchInput) {
      searchInput.value = query;
      searchInput.dispatchEvent(new Event("input", { bubbles: true }));
    }
  }
}

/**
 * Utility Functions
 */

// Show notification
function showNotification(message, type = "success") {
  // Remove existing notifications
  document.querySelectorAll(".notification").forEach((notification) => {
    notification.remove();
  });

  const notification = document.createElement("div");
  notification.className = `notification notification-${type}`;
  notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${
              type === "success" ? "check" : "exclamation"
            }-circle"></i>
            <span>${message}</span>
        </div>
    `;

  document.body.appendChild(notification);

  // Animate in
  setTimeout(() => {
    notification.classList.add("show");
  }, 100);

  // Auto remove after 3 seconds
  setTimeout(() => {
    notification.classList.remove("show");
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 300);
  }, 3000);
}

// Format duration
function formatDuration(seconds) {
  if (!seconds || isNaN(seconds)) return "0:00";

  seconds = Math.floor(seconds);
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;

  return `${minutes}:${remainingSeconds.toString().padStart(2, "0")}`;
}

// Debounce function
function debounce(func, wait, immediate) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      timeout = null;
      if (!immediate) func(...args);
    };
    const callNow = immediate && !timeout;
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
    if (callNow) func(...args);
  };
}

// Throttle function
function throttle(func, limit) {
  let inThrottle;
  return function (...args) {
    if (!inThrottle) {
      func.apply(this, args);
      inThrottle = true;
      setTimeout(() => (inThrottle = false), limit);
    }
  };
}

// Fungsi untuk menangani posisi dropdown agar tidak terpotong
function handleDropdownPositioning() {
  document.querySelectorAll(".song-actions").forEach((actions) => {
    const dropdown = actions.querySelector(".dropdown-menu");
    const submenus = actions.querySelectorAll(".submenu");

    if (dropdown) {
      // Cek posisi dropdown utama
      const rect = dropdown.getBoundingClientRect();
      const viewportWidth = window.innerWidth;

      // Jika dropdown akan terpotong di sisi kanan, posisikan ke kiri
      if (rect.right > viewportWidth - 20) {
        dropdown.classList.add("dropdown-left");
        dropdown.classList.remove("dropdown-right");
      } else {
        dropdown.classList.add("dropdown-right");
        dropdown.classList.remove("dropdown-left");
      }
    }

    // Handle submenu positioning
    submenus.forEach((submenu) => {
      const rect = submenu.getBoundingClientRect();
      const viewportWidth = window.innerWidth;

      // Jika submenu akan terpotong di sisi kanan, posisikan ke kiri
      if (rect.right > viewportWidth - 20) {
        submenu.classList.add("right-edge");
      } else {
        submenu.classList.remove("right-edge");
      }
    });
  });
}

/**
 * Initialize the application when DOM is loaded
 */
document.addEventListener("DOMContentLoaded", () => {
  // Create global app instance
  window.mePlayApp = new MePlayApp();

  // Initialize player if audio element exists
  const audioElement = document.getElementById("audioElement");
  if (audioElement && typeof MusicPlayer !== "undefined") {
    window.musicPlayer = new MusicPlayer();
  }

  console.log("MePlay fully initialized");
});

document.addEventListener("DOMContentLoaded", function () {
  handleDropdownPositioning();

  // Re-position dropdowns on window resize
  window.addEventListener("resize", handleDropdownPositioning);

  // Re-position dropdowns when they are shown
  document.querySelectorAll(".song-actions").forEach((actions) => {
    actions.addEventListener("mouseenter", handleDropdownPositioning);
  });
});

/**
 * Error handling for uncaught errors
 */
window.addEventListener("error", (e) => {
  console.error("Uncaught error:", e.error);
  showNotification("An unexpected error occurred", "error");
});

/**
 * Handle offline/online status
 */
window.addEventListener("online", () => {
  showNotification("Connection restored", "success");
});

window.addEventListener("offline", () => {
  showNotification("You are currently offline", "error");
});
