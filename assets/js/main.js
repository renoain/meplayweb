class MePlayApp {
  constructor() {
    this.currentTheme = localStorage.getItem("meplay-theme") || "light";
    this.isSidebarCollapsed =
      localStorage.getItem("sidebar-collapsed") === "true";
    this.activeDropdown = null;
    this.activeSubmenu = null;
    this.searchTimeout = null;
    this.isModalOpen = false;

    this.init();
  }

  init() {
    console.log("MePlay App initializing...");

    // Initial setup
    this.applyTheme();
    this.setupEventListeners();
    this.initSongInteractions();
    this.setupGlobalFunctions();
    this.setupSidebar();

    console.log(" MePlay App ready");
  }

  setupGlobalFunctions() {
    // Global notification function
    window.showNotification = (message, type = "success") => {
      if (window.musicPlayer && window.musicPlayer.showNotification) {
        window.musicPlayer.showNotification(message, type);
      } else {
        // Fallback
        console.log(`${type}: ${message}`);
        this.showFallbackNotification(message, type);
      }
    };
  }

  showFallbackNotification(message, type = "success") {
    // Remove any existing notifications
    document.querySelectorAll(".notification").forEach((notification) => {
      notification.remove();
    });

    // Create new notification
    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
      <div class="notification-content">
        <i class="fas fa-${type === "success" ? "check" : "exclamation"}"></i>
        <span>${message}</span>
      </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => notification.classList.add("show"), 100);

    setTimeout(() => {
      notification.classList.remove("show");
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }

  setupEventListeners() {
    // Theme toggle
    const themeToggle = document.getElementById("themeToggle");
    if (themeToggle) {
      this.updateThemeIcon();
      themeToggle.addEventListener("click", () => this.toggleTheme());
    }

    // Sidebar toggle
    const sidebarToggle = document.getElementById("sidebarToggle");
    if (sidebarToggle) {
      sidebarToggle.addEventListener("click", () => this.toggleSidebar());
    }

    // Search
    this.initSearch();

    // Modals
    this.initModals();
  }

  setupSidebar() {
    const mainContent = document.querySelector(".main-content");
    const sidebar = document.getElementById("sidebar");

    if (this.isSidebarCollapsed) {
      mainContent?.classList.add("sidebar-collapsed");
      sidebar?.classList.add("collapsed");
    }
  }

  initSongInteractions() {
    // Play buttons on song covers
    document.addEventListener("click", (e) => {
      const playBtn = e.target.closest(".play-btn");
      if (playBtn) {
        e.preventDefault();
        e.stopPropagation();
        const songId = playBtn.getAttribute("data-song-id");
        if (songId) this.playSong(songId);
        return;
      }

      // Song card click
      const songCard = e.target.closest(".song-card");
      if (songCard && !e.target.closest(".song-overlay")) {
        const songId = songCard.getAttribute("data-song-id");
        if (songId) this.playSong(songId);
      }

      // Song item click (play on row click)
      const songItem = e.target.closest(".song-item");
      if (
        songItem &&
        !e.target.closest(".song-actions") &&
        !e.target.closest(".more-btn")
      ) {
        const songId = songItem.getAttribute("data-song-id");
        if (songId) this.playSong(songId);
      }
    });

    // More buttons dropdown
    document.addEventListener("click", (e) => {
      const moreBtn = e.target.closest(".more-btn");
      if (moreBtn) {
        e.preventDefault();
        e.stopPropagation();
        this.toggleSongDropdown(moreBtn);
        return;
      }

      // Close dropdowns when clicking outside
      if (
        !e.target.closest(".song-dropdown") &&
        !e.target.closest(".more-btn")
      ) {
        this.closeAllDropdowns();
      }
    });

    // Like buttons in dropdown
    document.addEventListener("click", (e) => {
      const likeBtn = e.target.closest(".like-song");
      if (likeBtn && likeBtn.classList.contains("like-song")) {
        e.preventDefault();
        e.stopPropagation();
        const songId = likeBtn.getAttribute("data-song-id");
        if (songId) {
          this.handleLikeButton(likeBtn, songId);
        }
        this.closeAllDropdowns();
      }
    });

    // Add to queue from dropdown
    document.addEventListener("click", (e) => {
      const addToQueueBtn = e.target.closest(".add-to-queue");
      if (addToQueueBtn) {
        e.preventDefault();
        e.stopPropagation();
        const songId = addToQueueBtn.getAttribute("data-song-id");
        if (songId && window.musicPlayer) {
          window.musicPlayer.addToQueueById(songId);
        }
        this.closeAllDropdowns();
      }
    });

    // Submenu triggers
    document.addEventListener("click", (e) => {
      const submenuTrigger = e.target.closest(".submenu-trigger");
      if (submenuTrigger) {
        e.preventDefault();
        e.stopPropagation();
        this.toggleSubmenu(submenuTrigger);
        return;
      }
    });

    // Add to playlist buttons in submenu
    document
      .querySelectorAll(".submenu .add-to-playlist:not(.disabled)")
      .forEach((button) => {
        button.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          this.handleAddToPlaylist(button);
        });
      });

    // Create playlist buttons
    document.querySelectorAll(".create-playlist").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handleCreatePlaylist(button);
      });
    });
  }

  async handleLikeButton(button, songId) {
    const isLiked = button.classList.contains("liked");
    const action = isLiked ? "unlike" : "like";

    try {
      const response = await fetch("api/likes.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          song_id: parseInt(songId),
          action: action,
        }),
      });

      const data = await response.json();

      if (data.success) {
        // Update dropdown button
        if (button.classList.contains("like-song")) {
          if (isLiked) {
            button.innerHTML = '<i class="far fa-heart"></i> Like';
            button.classList.remove("liked", "text-danger");
          } else {
            button.innerHTML = '<i class="fas fa-heart"></i> Unlike';
            button.classList.add("liked", "text-danger");
          }
        }

        // Also update standalone like button if exists
        const standaloneLikeBtn = document.querySelector(
          `.like-btn[data-song-id="${songId}"]`
        );
        if (standaloneLikeBtn) {
          if (isLiked) {
            standaloneLikeBtn.innerHTML = '<i class="far fa-heart"></i>';
            standaloneLikeBtn.classList.remove("liked");
          } else {
            standaloneLikeBtn.innerHTML = '<i class="fas fa-heart"></i>';
            standaloneLikeBtn.classList.add("liked");
          }
        }

        this.showNotification(
          isLiked ? "Removed from liked songs" : "Added to liked songs",
          "success"
        );
      } else {
        this.showNotification(data.message || "Error updating like", "error");
      }
    } catch (error) {
      console.error("Error:", error);
      this.showNotification("Error updating like", "error");
    }
  }

  async handleAddToPlaylist(button) {
    const songId = button.getAttribute("data-song-id");
    const playlistId = button.getAttribute("data-playlist-id");
    const playlistName = button.textContent.trim();
    const songItem = button.closest(".song-item");
    const songTitle = songItem
      ? songItem.querySelector(".song-info h4").textContent
      : "Song";

    try {
      const response = await fetch("api/playlists.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "add_song",
          playlist_id: parseInt(playlistId),
          song_id: parseInt(songId),
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification(`"${songTitle}" added to "${playlistName}"`);
      } else {
        this.showNotification(
          data.message || "Error adding to playlist",
          "error"
        );
      }
    } catch (error) {
      console.error("Add to playlist error:", error);
      this.showNotification("Error adding to playlist", "error");
    }
    this.closeSubmenu();
  }

  handleCreatePlaylist(button) {
    const songId = button.getAttribute("data-song-id");
    const modal = document.getElementById("createPlaylistModal");
    const songIdInput = document.getElementById("songIdForPlaylist");

    if (modal && songIdInput) {
      songIdInput.value = songId;
      this.showModal(modal);
    }
    this.closeAllDropdowns();
  }

  async playSong(songId) {
    if (!songId) return;

    if (window.musicPlayer) {
      await window.musicPlayer.playSongById(songId);
    } else {
      this.showNotification("Player not initialized", "error");
    }
  }

  //  THEME
  toggleTheme() {
    this.currentTheme = this.currentTheme === "light" ? "dark" : "light";
    this.applyTheme();
    this.updateThemeIcon();
  }

  applyTheme() {
    document.body.setAttribute("data-theme", this.currentTheme);
    localStorage.setItem("meplay-theme", this.currentTheme);
    this.updateThemeMeta();
  }

  updateThemeMeta() {
    let meta = document.querySelector('meta[name="theme-color"]');
    if (!meta) {
      meta = document.createElement("meta");
      meta.name = "theme-color";
      document.head.appendChild(meta);
    }
    meta.content = this.currentTheme === "dark" ? "#1a202c" : "#667eea";
  }

  updateThemeIcon() {
    const icon = document.querySelector("#themeToggle i");
    if (icon) {
      icon.className =
        this.currentTheme === "dark" ? "fas fa-sun" : "fas fa-moon";
    }
  }

  //  SIDEBAR
  toggleSidebar() {
    const mainContent = document.querySelector(".main-content");
    const sidebar = document.getElementById("sidebar");

    this.isSidebarCollapsed = !this.isSidebarCollapsed;
    mainContent?.classList.toggle("sidebar-collapsed", this.isSidebarCollapsed);
    sidebar?.classList.toggle("collapsed", this.isSidebarCollapsed);
    localStorage.setItem("sidebar-collapsed", this.isSidebarCollapsed);
  }

  //  SEARCH
  initSearch() {
    const searchInput = document.getElementById("search-input");
    if (!searchInput) return;

    searchInput.addEventListener("input", (e) => {
      const query = e.target.value.trim();
      if (query.length === 0) {
        this.hideSearchSuggestions();
        return;
      }

      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        if (query.length > 1) this.performSearch(query);
      }, 300);
    });

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
      }
    } catch (error) {
      console.error("Search error:", error);
      this.hideSearchSuggestions();
    }
  }

  showSearchSuggestions(results, query) {
    const container = document.getElementById("searchSuggestions");
    if (!container) return;

    if (results.length === 0) {
      container.innerHTML = `
        <div class="suggestion-item">
          <div class="suggestion-info">
            <p>No results for "${this.escapeHtml(query)}"</p>
          </div>
        </div>
      `;
    } else {
      container.innerHTML = results
        .map(
          (song) => `
          <a href="song.php?id=${song.id}" class="suggestion-item">
            <img src="${this.getSongCover(song.cover_image)}" 
                 alt="${this.escapeHtml(song.title)}"
                 onerror="this.src='assets/images/covers/default-cover.png'">
            <div class="suggestion-info">
              <h4>${this.escapeHtml(song.title)}</h4>
              <p>${this.escapeHtml(song.artist_name)}</p>
              <span class="suggestion-type">Song</span>
            </div>
          </a>
        `
        )
        .join("");
    }

    container.classList.add("show");
  }

  hideSearchSuggestions() {
    document.getElementById("searchSuggestions")?.classList.remove("show");
  }

  //  MODALS
  initModals() {
    // Create playlist buttons
    document.querySelectorAll(".create-playlist").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        const songId = button.getAttribute("data-song-id");
        this.showCreatePlaylistModal(songId);
      });
    });

    // Close modal buttons
    document.querySelectorAll(".close-modal").forEach((button) => {
      button.addEventListener("click", () => this.hideAllModals());
    });

    // Close modal when clicking outside
    document.addEventListener("click", (e) => {
      if (e.target.classList.contains("modal")) {
        this.hideAllModals();
      }
    });

    // Create playlist form
    const form = document.getElementById("createPlaylistForm");
    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        this.handleCreatePlaylist();
      });
    }
  }

  showCreatePlaylistModal(songId) {
    const modal = document.getElementById("createPlaylistModal");
    if (!modal) return;

    const songIdInput = document.getElementById("songIdForPlaylist");
    if (songIdInput) songIdInput.value = songId || "";

    modal.classList.add("show");
    document.body.style.overflow = "hidden";
    this.isModalOpen = true;
  }

  showModal(modal) {
    if (!modal) return;

    this.hideAllModals();
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
    this.isModalOpen = true;
  }

  hideAllModals() {
    document.querySelectorAll(".modal").forEach((modal) => {
      modal.classList.remove("show");
    });
    document.body.style.overflow = "";
    this.isModalOpen = false;
  }

  async handleCreatePlaylist() {
    const form = document.getElementById("createPlaylistForm");
    if (!form) return;

    const formData = new FormData(form);
    const title = formData.get("title").trim();
    const description = formData.get("description").trim();
    const songId = formData.get("song_id");

    if (!title) {
      this.showNotification("Playlist title is required", "error");
      return;
    }

    try {
      const response = await fetch("api/playlists.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "create",
          title: title,
          description: description,
          song_id: songId || null,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification(`Playlist "${title}" created successfully`);
        this.hideAllModals();
        form.reset();

        // Reload page after a delay
        setTimeout(() => {
          window.location.reload();
        }, 1500);
      } else {
        this.showNotification(
          data.message || "Error creating playlist",
          "error"
        );
      }
    } catch (error) {
      console.error("Create playlist error:", error);
      this.showNotification("Error creating playlist", "error");
    }
  }

  //  DROPDOWNS
  toggleSongDropdown(button) {
    this.closeAllDropdowns();

    const songId = button.getAttribute("data-song-id");
    let dropdown;

    if (songId) {
      dropdown = document.getElementById(`dropdown-${songId}`);
    }

    if (!dropdown) {
      dropdown =
        button.closest(".song-actions")?.querySelector(".song-dropdown") ||
        button.nextElementSibling;
    }

    if (!dropdown || !dropdown.classList.contains("song-dropdown")) return;

    const rect = button.getBoundingClientRect();
    dropdown.style.display = "block";
    dropdown.style.position = "fixed";
    dropdown.style.zIndex = "10000";

    // Position to the LEFT by default
    const dropdownWidth = dropdown.offsetWidth;
    const viewportWidth = window.innerWidth;

    let leftPosition = rect.left - dropdownWidth + 10;

    // If dropdown goes off screen on left, position to right
    if (leftPosition < 10) {
      leftPosition = rect.right - 10;
    }

    dropdown.style.top = `${rect.bottom + 5}px`;
    dropdown.style.left = `${leftPosition}px`;
    dropdown.classList.add("show");
    this.activeDropdown = dropdown;

    // Close when clicking outside
    const closeHandler = (e) => {
      if (!dropdown.contains(e.target) && !button.contains(e.target)) {
        dropdown.classList.remove("show");
        dropdown.style.display = "none";
        document.removeEventListener("click", closeHandler);
        this.activeDropdown = null;
      }
    };

    setTimeout(() => {
      document.addEventListener("click", closeHandler);
    }, 10);
  }

  toggleSubmenu(button) {
    const submenu = button
      .closest(".dropdown-submenu")
      ?.querySelector(".submenu");
    if (!submenu) return;

    if (this.activeSubmenu === submenu) {
      this.closeSubmenu();
    } else {
      this.closeSubmenu();
      this.activeSubmenu = submenu;

      // Position submenu
      const rect = button.getBoundingClientRect();
      const submenuWidth = submenu.offsetWidth;
      const viewportWidth = window.innerWidth;

      submenu.style.display = "block";
      submenu.style.position = "fixed";
      submenu.style.zIndex = "10001";

      // Position to the LEFT on desktop
      if (window.innerWidth > 768) {
        let leftPosition = rect.left - submenuWidth;

        // If submenu goes off screen on left, position to right
        if (leftPosition < 10) {
          leftPosition = rect.right + 10;
        }

        submenu.style.top = `${rect.top}px`;
        submenu.style.left = `${leftPosition}px`;
      } else {
        // On mobile, position below
        submenu.style.top = `${rect.bottom + 5}px`;
        submenu.style.left = `${rect.left}px`;
        submenu.style.maxHeight = "200px";
      }
    }
  }

  closeSubmenu() {
    if (this.activeSubmenu) {
      this.activeSubmenu.style.display = "none";
      this.activeSubmenu.style.maxHeight = "0";
      this.activeSubmenu = null;
    }
  }

  closeAllDropdowns() {
    if (this.activeDropdown) {
      this.activeDropdown.style.display = "none";
      this.activeDropdown.classList.remove("show");
      this.activeDropdown = null;
    }
    this.closeSubmenu();
  }

  //  UTILITIES
  getSongCover(coverImage) {
    if (coverImage && coverImage !== "default-cover.png") {
      return coverImage.startsWith("uploads/")
        ? coverImage
        : "uploads/covers/" + coverImage;
    }
    return "assets/images/covers/default-cover.png";
  }

  escapeHtml(unsafe) {
    if (!unsafe) return "";
    return unsafe
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
}

// Initialize app when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  window.mePlayApp = new MePlayApp();

  // Initialize music player
  if (typeof initializeMusicPlayer === "function") {
    setTimeout(() => {
      initializeMusicPlayer();
    }, 100);
  }
});
