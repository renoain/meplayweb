class PlaylistDetailManager {
  constructor() {
    this.playlistId = null;
    this.activeDropdown = null;
    this.activeSubmenu = null;
    this.init();
  }

  init() {
    console.log(" PlaylistDetailManager initializing...");

    // Get playlist ID from URL or data attribute
    const playlistId = this.getPlaylistId();
    if (playlistId) {
      this.playlistId = playlistId;
    }

    this.setupEventListeners();
    this.setupModalHandlers();
    this.setupSearch();

    console.log(" PlaylistDetailManager ready");
  }

  getPlaylistId() {
    // Try to get playlist ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get("id");

    if (id) {
      return parseInt(id);
    }

    // Try to get from data attribute
    const playlistHeader = document.querySelector(".playlist-header");
    if (playlistHeader) {
      return playlistHeader.dataset.playlistId;
    }

    return null;
  }

  setupEventListeners() {
    // Play All button
    const playAllBtn = document.getElementById("playAllBtn");
    if (playAllBtn) {
      playAllBtn.addEventListener("click", () => this.playPlaylist());
    }

    // Play playlist button (alternative)
    const playPlaylistBtn = document.getElementById("playPlaylistBtn");
    if (playPlaylistBtn) {
      playPlaylistBtn.addEventListener("click", () => this.playPlaylist());
    }

    // Individual play buttons
    document.addEventListener("click", (e) => {
      const playBtn = e.target.closest(".play-btn");
      if (playBtn) {
        e.preventDefault();
        e.stopPropagation();
        const songId = playBtn.getAttribute("data-song-id");
        if (songId) this.playSong(songId);
        return;
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
        return;
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
        this.handleLikeButton(likeBtn);
        return;
      }
    });

    // Add to queue from dropdown
    document.addEventListener("click", (e) => {
      const addToQueueBtn = e.target.closest(".add-to-queue");
      if (addToQueueBtn) {
        e.preventDefault();
        e.stopPropagation();
        this.handleAddToQueue(addToQueueBtn);
        return;
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

    // Remove from playlist buttons
    document.querySelectorAll(".remove-from-playlist").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handleRemoveFromPlaylist(button);
      });
    });

    // Escape key to close dropdowns and modals
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        this.closeAllDropdowns();
        this.closeAllModals();
      }
    });
  }

  setupModalHandlers() {
    // Close modal buttons
    document.querySelectorAll(".close-modal").forEach((button) => {
      button.addEventListener("click", () => this.closeAllModals());
    });

    // Close modal when clicking outside
    document.addEventListener("click", (e) => {
      if (e.target.classList.contains("modal")) {
        this.closeAllModals();
      }
    });

    // Create playlist form
    const createForm = document.getElementById("createPlaylistForm");
    if (createForm) {
      createForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.handleCreatePlaylistForm();
      });
    }

    // Remove song form
    const removeForm = document.getElementById("removeSongForm");
    if (removeForm) {
      removeForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.handleRemoveSongForm();
      });
    }
  }

  setupSearch() {
    const searchInput = document.getElementById("searchSongsInput");
    if (searchInput) {
      let searchTimeout;

      searchInput.addEventListener("input", (e) => {
        const query = e.target.value.trim();

        clearTimeout(searchTimeout);

        if (query.length === 0) {
          this.clearSearchResults();
          return;
        }

        searchTimeout = setTimeout(() => {
          if (query.length > 1) {
            this.searchSongs(query);
          }
        }, 300);
      });
    }
  }

  //  PLAYBACK
  async playPlaylist() {
    if (!this.playlistId) return;

    if (window.musicPlayer) {
      await window.musicPlayer.playPlaylist(this.playlistId);
    } else {
      this.showNotification("Player not initialized", "error");
    }
  }

  async playSong(songId) {
    if (!songId) return;

    if (window.musicPlayer) {
      await window.musicPlayer.playSongById(songId);
    } else {
      this.showNotification("Player not initialized", "error");
    }
  }

  //  DROPDOWNS
  toggleSongDropdown(button) {
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

    if (
      this.activeDropdown === dropdown &&
      dropdown.style.display === "block"
    ) {
      dropdown.style.display = "none";
      dropdown.classList.remove("show");
      this.activeDropdown = null;
      this.closeSubmenu();
    } else {
      this.closeAllDropdowns();

      // Position dropdown
      const rect = button.getBoundingClientRect();
      dropdown.style.display = "block";
      dropdown.classList.add("show");
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

      this.activeDropdown = dropdown;
      this.closeSubmenu();
    }
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

  //  LIKES
  async handleLikeButton(button) {
    const songId = button.getAttribute("data-song-id");
    if (!songId) return;

    await this.toggleLike(songId, button);
    this.closeAllDropdowns();
  }

  async toggleLike(songId, button) {
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

  //  QUEUE
  async handleAddToQueue(button) {
    const songId = button.getAttribute("data-song-id");
    const songItem = button.closest(".song-item");
    const songTitle = songItem
      ? songItem.querySelector(".song-info h4").textContent
      : "Song";

    try {
      const response = await fetch(`api/songs.php?id=${songId}`);
      const data = await response.json();

      if (data.success && window.musicPlayer) {
        window.musicPlayer.addToQueue(data.song);
        this.showNotification(`"${songTitle}" added to queue`);
      }
    } catch (error) {
      console.error("Error adding to queue:", error);
      this.showNotification("Error adding to queue", "error");
    }
    this.closeAllDropdowns();
  }

  //  PLAYLIST OPERATIONS
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

  async handleRemoveFromPlaylist(button) {
    const songId = button.getAttribute("data-song-id");
    const playlistId = button.getAttribute("data-playlist-id");
    const songItem = button.closest(".song-item");
    const songTitle = songItem
      ? songItem.querySelector(".song-info h4").textContent
      : "Song";

    // Show confirmation modal
    const modal = document.getElementById("removeSongModal");
    const songIdInput = document.getElementById("removeSongId");

    if (modal && songIdInput) {
      songIdInput.value = songId;
      this.showModal(modal);
    }
  }

  //  SEARCH
  async searchSongs(query) {
    try {
      const response = await fetch(
        `api/search.php?q=${encodeURIComponent(query)}&limit=10`
      );
      const data = await response.json();

      if (data.success) {
        this.displaySearchResults(data.results);
      } else {
        this.clearSearchResults();
        this.showSearchMessage("No results found");
      }
    } catch (error) {
      console.error("Search error:", error);
      this.showSearchMessage("Error searching songs");
    }
  }

  displaySearchResults(results) {
    const container = document.getElementById("searchResults");
    if (!container) return;

    if (results.length === 0) {
      container.innerHTML = '<div class="search-message">No songs found</div>';
      return;
    }

    container.innerHTML = results
      .map(
        (song) => `
            <div class="search-result-item">
                <img src="${this.getSongCover(song.cover_image)}" 
                     alt="${this.escapeHtml(song.title)}"
                     onerror="this.src='assets/images/covers/default-cover.png'">
                <div class="search-result-info">
                    <h4>${this.escapeHtml(song.title)}</h4>
                    <p>${this.escapeHtml(song.artist_name)}</p>
                </div>
                <button class="add-song-btn" onclick="window.playlistDetailManager.addSongToPlaylist(${
                  song.id
                }, '${this.escapeHtml(song.title)}')">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>
        `
      )
      .join("");
  }

  clearSearchResults() {
    const container = document.getElementById("searchResults");
    if (container) {
      container.innerHTML = "";
    }
  }

  showSearchMessage(message) {
    const container = document.getElementById("searchResults");
    if (container) {
      container.innerHTML = `<div class="search-message">${message}</div>`;
    }
  }

  async addSongToPlaylist(songId, songTitle) {
    if (!this.playlistId) return;

    try {
      const response = await fetch("api/playlists.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "add_song",
          playlist_id: this.playlistId,
          song_id: songId,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification(`"${songTitle}" added to playlist`);
        // Reload page to show updated playlist
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        this.showNotification(data.message || "Error adding song", "error");
      }
    } catch (error) {
      console.error("Add song error:", error);
      this.showNotification("Error adding song", "error");
    }
  }

  //  MODALS
  showModal(modal) {
    if (!modal) return;

    this.closeAllModals();
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
  }

  closeAllModals() {
    document.querySelectorAll(".modal").forEach((modal) => {
      modal.classList.remove("show");
    });
    document.body.style.overflow = "";
  }

  showEditPlaylistModal() {
    const modal = document.getElementById("editPlaylistModal");
    this.showModal(modal);
  }

  showAddSongsModal() {
    const modal = document.getElementById("addSongsModal");
    this.showModal(modal);
  }

  async handleCreatePlaylistForm() {
    const form = document.getElementById("createPlaylistForm");
    const formData = new FormData(form);
    const songId = document.getElementById("songIdForPlaylist").value;
    const title = formData.get("title");

    if (!title.trim()) {
      this.showNotification("Please enter a playlist title", "error");
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
          title: title,
          description: formData.get("description"),
          song_id: songId ? parseInt(songId) : null,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification(`Playlist "${title}" created successfully`);
        this.closeAllModals();
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

  async handleRemoveSongForm() {
    const form = document.getElementById("removeSongForm");
    const formData = new FormData(form);
    const songId = formData.get("song_id");
    const playlistId = formData.get("playlist_id");

    try {
      const response = await fetch("api/playlists.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "remove_song",
          playlist_id: parseInt(playlistId),
          song_id: parseInt(songId),
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification("Song removed from playlist");
        this.closeAllModals();

        // Reload page to show updated playlist
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        this.showNotification(data.message || "Error removing song", "error");
      }
    } catch (error) {
      console.error("Remove song error:", error);
      this.showNotification("Error removing song", "error");
    }
  }

  //  UTILITY FUNCTIONS
  showNotification(message, type = "success") {
    if (window.musicPlayer && window.musicPlayer.showNotification) {
      window.musicPlayer.showNotification(message, type);
    } else {
      // Fallback notification
      console.log(`${type}: ${message}`);
      const notification = document.createElement("div");
      notification.className = `notification notification-${type} show`;
      notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${
                      type === "success" ? "check" : "exclamation"
                    }"></i>
                    <span>${message}</span>
                </div>
            `;

      document.body.appendChild(notification);

      setTimeout(() => {
        notification.classList.remove("show");
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    }
  }

  getSongCover(coverImage) {
    if (!coverImage || coverImage === "default-cover.png") {
      return "assets/images/covers/default-cover.png";
    }
    return coverImage.includes("uploads/")
      ? coverImage
      : `uploads/covers/${coverImage}`;
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

//  GLOBAL INITIALIZATION
document.addEventListener("DOMContentLoaded", () => {
  window.playlistDetailManager = new PlaylistDetailManager();
});
