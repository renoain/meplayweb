// assets/js/playlist_detail.js (UPDATE existing file)
class PlaylistDetailManager {
  constructor() {
    this.playlistId = null;
    this.activeDropdown = null;
    this.activeSubmenu = null;
    this.init();
  }

  init() {
    console.log("PlaylistDetailManager initializing...");

    this.playlistId = this.getPlaylistId();
    if (!this.playlistId) {
      console.error("Playlist ID not found!");
      return;
    }

    this.setupEventListeners();
    this.setupModalHandlers();
    this.setupFormHandlers();
    this.setupSearch();

    console.log(
      "PlaylistDetailManager ready for playlist ID:",
      this.playlistId
    );
  }

  getPlaylistId() {
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get("id");

    if (id) {
      return parseInt(id);
    }

    const playlistHeader = document.querySelector(".playlist-header");
    if (playlistHeader && playlistHeader.dataset.playlistId) {
      return parseInt(playlistHeader.dataset.playlistId);
    }

    return null;
  }

  setupEventListeners() {
    // Play All button
    const playAllBtn = document.getElementById("playPlaylistBtn");
    if (playAllBtn) {
      playAllBtn.addEventListener("click", (e) => {
        e.preventDefault();
        this.playPlaylist();
      });
    }

    // Edit Playlist button
    const editBtn = document.getElementById("editPlaylistBtn");
    if (editBtn) {
      editBtn.addEventListener("click", (e) => {
        e.preventDefault();
        this.showEditPlaylistModal();
      });
    }

    // Delete Playlist button
    const deleteBtn = document.getElementById("deletePlaylistBtn");
    if (deleteBtn) {
      deleteBtn.addEventListener("click", (e) => {
        e.preventDefault();
        this.showDeletePlaylistModal();
      });
    }

    // Add Songs button
    const addSongsBtn = document.getElementById("addSongsBtn");
    if (addSongsBtn) {
      addSongsBtn.addEventListener("click", (e) => {
        e.preventDefault();
        this.showAddSongsModal();
      });
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

    // Remove from playlist buttons
    document.addEventListener("click", (e) => {
      const removeBtn = e.target.closest(".remove-from-playlist");
      if (removeBtn) {
        e.preventDefault();
        e.stopPropagation();
        this.handleRemoveFromPlaylist(removeBtn);
        return;
      }
    });

    // Add to playlist buttons in submenu
    document.addEventListener("click", (e) => {
      const addBtn = e.target.closest(".add-to-playlist");
      if (addBtn && !addBtn.classList.contains("disabled")) {
        e.preventDefault();
        e.stopPropagation();
        this.handleAddToPlaylist(addBtn);
        return;
      }
    });

    // Create playlist buttons
    document.addEventListener("click", (e) => {
      const createBtn = e.target.closest(".create-playlist");
      if (createBtn) {
        e.preventDefault();
        e.stopPropagation();
        this.handleCreatePlaylist(createBtn);
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

    // Escape key to close dropdowns and modals
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        this.closeAllDropdowns();
        this.closeAllModals();
      }
    });

    // Auto-hide notifications
    setTimeout(() => {
      document
        .querySelectorAll(".notification.show")
        .forEach((notification) => {
          notification.classList.remove("show");
          setTimeout(() => notification.remove(), 300);
        });
    }, 3000);
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
  }

  setupFormHandlers() {
    // Edit playlist form - AJAX submission
    const editForm = document.getElementById("editPlaylistForm");
    if (editForm) {
      editForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.handleEditPlaylistForm();
      });
    }

    // Delete playlist form
    const deleteForm = document.getElementById("deletePlaylistForm");
    if (deleteForm) {
      deleteForm.addEventListener("submit", (e) => {
        // Let it submit normally, we'll show a loading state
        const submitBtn = deleteForm.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.innerHTML =
            '<i class="fas fa-spinner fa-spin"></i> Deleting...';
          submitBtn.disabled = true;
        }
      });
    }

    // Remove song form - AJAX submission
    const removeForm = document.getElementById("removeSongForm");
    if (removeForm) {
      removeForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.handleRemoveSongForm();
      });
    }

    // Create playlist form
    const createForm = document.getElementById("createPlaylistForm");
    if (createForm) {
      createForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.handleCreatePlaylistForm();
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

  // MODAL FUNCTIONS
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

  showDeletePlaylistModal() {
    const modal = document.getElementById("deletePlaylistModal");
    this.showModal(modal);
  }

  showAddSongsModal() {
    const modal = document.getElementById("addSongsModal");
    this.showModal(modal);
    setTimeout(() => {
      const searchInput = document.getElementById("searchSongsInput");
      if (searchInput) searchInput.focus();
    }, 100);
  }

  // PLAYBACK FUNCTIONS
  async playPlaylist() {
    if (!this.playlistId) {
      this.showNotification("Playlist ID not found", "error");
      return;
    }

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

  // FORM HANDLERS
  async handleEditPlaylistForm() {
    const form = document.getElementById("editPlaylistForm");
    const formData = new FormData(form);
    const title = formData.get("title");
    const description = formData.get("description");

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
          action: "update",
          playlist_id: this.playlistId,
          title: title,
          description: description,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification(data.message || "Playlist updated successfully");
        this.closeAllModals();

        if (data.redirect) {
          setTimeout(() => {
            window.location.href = data.redirect;
          }, 1000);
        } else {
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        }
      } else {
        this.showNotification(
          data.message || "Failed to update playlist",
          "error"
        );
      }
    } catch (error) {
      console.error("Edit playlist error:", error);
      this.showNotification("Error updating playlist", "error");
    }
  }

  async handleRemoveSongForm() {
    const form = document.getElementById("removeSongForm");
    const formData = new FormData(form);

    try {
      const response = await fetch("api/playlists.php", {
        method: "POST",
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification(data.message || "Song removed from playlist");
        this.closeAllModals();
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        this.showNotification(data.message || "Failed to remove song", "error");
      }
    } catch (error) {
      console.error("Remove song error:", error);
      this.showNotification("Error removing song", "error");
    }
  }

  // DROPDOWN FUNCTIONS
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

      const rect = button.getBoundingClientRect();
      dropdown.style.display = "block";
      dropdown.classList.add("show");
      dropdown.style.position = "fixed";
      dropdown.style.zIndex = "10000";

      const dropdownWidth = dropdown.offsetWidth;
      const viewportWidth = window.innerWidth;

      let leftPosition = rect.left - dropdownWidth + 10;

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

      const rect = button.getBoundingClientRect();
      const submenuWidth = submenu.offsetWidth;

      submenu.style.display = "block";
      submenu.style.position = "fixed";
      submenu.style.zIndex = "10001";

      if (window.innerWidth > 768) {
        let leftPosition = rect.left - submenuWidth;

        if (leftPosition < 10) {
          leftPosition = rect.right + 10;
        }

        submenu.style.top = `${rect.top}px`;
        submenu.style.left = `${leftPosition}px`;
      } else {
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

  // BUTTON HANDLERS
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

  handleRemoveFromPlaylist(button) {
    const songId = button.getAttribute("data-song-id");
    const playlistId =
      button.getAttribute("data-playlist-id") || this.playlistId;
    const modal = document.getElementById("removeSongModal");
    const songIdInput = document.getElementById("removeSongId");

    if (modal && songIdInput) {
      songIdInput.value = songId;
      const playlistIdInput = modal.querySelector('input[name="playlist_id"]');
      if (playlistIdInput) {
        playlistIdInput.value = playlistId;
      }
      this.showModal(modal);
    }
    this.closeAllDropdowns();
  }

  // SEARCH FUNCTIONS
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

    if (!results || results.length === 0) {
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
                <button class="add-song-btn" data-song-id="${
                  song.id
                }" data-song-title="${this.escapeHtml(song.title)}">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>
        `
      )
      .join("");

    container.querySelectorAll(".add-song-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        const songId = btn.getAttribute("data-song-id");
        const songTitle = btn.getAttribute("data-song-title");
        this.addSongToPlaylist(songId, songTitle);
      });
    });
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
        this.closeAllModals();
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
        body: formData,
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification(data.message || "Playlist created successfully");
        this.closeAllModals();
        form.reset();

        if (data.redirect) {
          setTimeout(() => {
            window.location.href = data.redirect;
          }, 1000);
        } else if (data.playlist_id) {
          window.location.href = `playlist_detail.php?id=${data.playlist_id}`;
        } else {
          window.location.reload();
        }
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

  // UTILITY FUNCTIONS
  showNotification(message, type = "success") {
    if (window.musicPlayer && window.musicPlayer.showNotification) {
      window.musicPlayer.showNotification(message, type);
    } else {
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

// GLOBAL INITIALIZATION
document.addEventListener("DOMContentLoaded", () => {
  window.playlistDetailManager = new PlaylistDetailManager();
});
