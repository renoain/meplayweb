class HomeManager {
  constructor() {
    this.activeDropdown = null;
    this.activeSubmenu = null;
    this.init();
  }

  init() {
    console.log(" HomeManager initializing...");
    this.setupEventListeners();
    this.setupAlbumLinks();
    console.log(" HomeManager ready");
  }

  setupEventListeners() {
    // Play buttons
    document.querySelectorAll(".song-card .play-btn").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        const songId = button.getAttribute("data-song-id");
        if (songId) this.playSong(songId);
      });
    });

    // Song card click (play on card click)
    document.querySelectorAll(".song-card").forEach((card) => {
      card.addEventListener("click", (e) => {
        if (!e.target.closest(".song-overlay")) {
          const songId = card.getAttribute("data-song-id");
          if (songId) this.playSong(songId);
        }
      });
    });

    // Song item click (play on row click)
    document.querySelectorAll(".songs-list .song-item").forEach((item) => {
      item.addEventListener("click", (e) => {
        if (
          !e.target.closest(".song-actions") &&
          !e.target.closest(".more-btn") &&
          !e.target.closest(".like-btn")
        ) {
          const songId = item.getAttribute("data-song-id");
          if (songId) this.playSong(songId);
        }
      });
    });

    // More buttons dropdown
    document.querySelectorAll(".more-btn").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.toggleSongDropdown(button);
      });
    });

    // Like buttons (standalone)
    document.querySelectorAll(".like-btn").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handleStandaloneLikeButton(button);
      });
    });

    // Like buttons in dropdown
    document.querySelectorAll(".song-dropdown .like-song").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handleLikeButton(button);
      });
    });

    // Add to queue from dropdown
    document
      .querySelectorAll(".song-dropdown .add-to-queue")
      .forEach((button) => {
        button.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          this.handleAddToQueue(button);
        });
      });

    // Submenu triggers
    document.querySelectorAll(".submenu-trigger").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.toggleSubmenu(button);
      });
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

    // Close dropdowns when clicking outside
    document.addEventListener("click", (e) => {
      if (
        !e.target.closest(".song-dropdown") &&
        !e.target.closest(".more-btn")
      ) {
        this.closeAllDropdowns();
      }
    });

    // Escape key to close dropdowns
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        this.closeAllDropdowns();
      }
    });
  }

  setupAlbumLinks() {
    // Album links should work normally
    document.querySelectorAll(".album-card").forEach((card) => {
      card.addEventListener("click", (e) => {
        // Let the link work normally
        console.log("Album card clicked, navigating to:", card.href);
      });
    });
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

      // Position submenu to the LEFT on desktop
      if (window.innerWidth > 768) {
        const rect = button.getBoundingClientRect();
        submenu.style.display = "block";
        submenu.style.position = "fixed";
        submenu.style.zIndex = "10001";
        submenu.style.top = `${rect.top}px`;

        // Position to the LEFT of the dropdown
        let leftPosition = rect.left - submenu.offsetWidth;

        // If submenu goes off screen on left, position to right
        if (leftPosition < 10) {
          leftPosition = rect.right + 10;
        }

        submenu.style.left = `${leftPosition}px`;
      } else {
        // On mobile, just show it
        submenu.style.display = "block";
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

  //  PLAYBACK
  async playSong(songId) {
    if (!songId) return;

    if (window.musicPlayer) {
      await window.musicPlayer.playSongById(songId);
    } else {
      this.showNotification("Player not initialized", "error");
    }
  }

  //  LIKES
  async handleStandaloneLikeButton(button) {
    const songId = button.getAttribute("data-song-id");
    if (!songId) return;

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
        // Update standalone button
        if (isLiked) {
          button.innerHTML = '<i class="far fa-heart"></i>';
          button.classList.remove("liked");
        } else {
          button.innerHTML = '<i class="fas fa-heart"></i>';
          button.classList.add("liked");
        }

        // Update dropdown button if exists
        const dropdownLikeBtn = document.querySelector(
          `.like-song[data-song-id="${songId}"]`
        );
        if (dropdownLikeBtn) {
          if (isLiked) {
            dropdownLikeBtn.innerHTML = '<i class="far fa-heart"></i> Like';
            dropdownLikeBtn.classList.remove("liked", "text-danger");
          } else {
            dropdownLikeBtn.innerHTML = '<i class="fas fa-heart"></i> Unlike';
            dropdownLikeBtn.classList.add("liked", "text-danger");
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

        // Update standalone button if exists
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

  showModal(modal) {
    if (!modal) return;

    modal.classList.add("show");
    document.body.style.overflow = "hidden";
  }

  //  UTILITY FUNCTIONS
  showNotification(message, type = "success") {
    if (window.musicPlayer && window.musicPlayer.showNotification) {
      window.musicPlayer.showNotification(message, type);
    } else if (window.mePlayApp) {
      window.mePlayApp.showFallbackNotification(message, type);
    } else {
      // Fallback notification
      console.log(`${type}: ${message}`);
    }
  }
}

//  GLOBAL INITIALIZATION
document.addEventListener("DOMContentLoaded", () => {
  window.homeManager = new HomeManager();
});
