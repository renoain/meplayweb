class AlbumDetailManager {
  constructor() {
    this.isInitialized = false;
    this.activeDropdown = null;
    this.activeSubmenu = null;
    this.init();
  }

  init() {
    if (this.isInitialized) return;

    console.log(" AlbumDetailManager initialized");
    this.setupEventListeners();
    this.isInitialized = true;
  }

  setupEventListeners() {
    // More buttons untuk dropdown
    document.querySelectorAll(".more-btn").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.stopPropagation();
        this.toggleSongDropdown(button);
      });
    });

    // Close dropdowns when clicking outside
    document.addEventListener("click", (e) => {
      if (
        !e.target.closest(".more-btn") &&
        !e.target.closest(".song-dropdown")
      ) {
        this.closeDropdowns();
      }
    });

    // Escape key to close dropdowns
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") this.closeDropdowns();
    });

    // Like buttons in dropdown
    document.querySelectorAll(".song-dropdown .like-song").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handleLikeButton(button);
      });
    });

    // Add to queue buttons
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

    // Play buttons on song covers
    document.querySelectorAll(".song-cover .play-btn").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handlePlayButton(button);
      });
    });

    // Play Album button
    const playAlbumBtn = document.getElementById("playAlbumBtn");
    if (playAlbumBtn) {
      playAlbumBtn.addEventListener("click", () => {
        this.handlePlayAll();
      });
    }

    // Modal close buttons
    document.querySelectorAll(".close-modal").forEach((button) => {
      button.addEventListener("click", () => {
        this.closeModals();
      });
    });

    // Create playlist form
    const createForm = document.getElementById("createPlaylistForm");
    if (createForm) {
      createForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.handleCreatePlaylistForm();
      });
    }
  }

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
      this.closeDropdowns();

      // Position dropdown to the LEFT
      const rect = button.getBoundingClientRect();
      dropdown.style.display = "block";
      dropdown.classList.add("show");
      dropdown.style.position = "fixed";
      dropdown.style.zIndex = "10000";

      // Calculate position - align to LEFT side of button
      dropdown.style.top = `${rect.bottom + 5}px`;

      // Position to the LEFT of the button
      const dropdownWidth = dropdown.offsetWidth;
      let leftPosition = rect.left - dropdownWidth;

      // If dropdown would go off screen on left, align to right instead
      if (leftPosition < 10) {
        leftPosition = rect.right + 5;
      }

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
        submenu.style.right = `${window.innerWidth - rect.left + 5}px`;
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

  closeDropdowns() {
    if (this.activeDropdown) {
      this.activeDropdown.style.display = "none";
      this.activeDropdown.classList.remove("show");
      this.activeDropdown = null;
    }
    this.closeSubmenu();
  }

  closeModals() {
    document.querySelectorAll(".modal").forEach((modal) => {
      modal.classList.remove("show");
    });
    document.body.style.overflow = "";
  }

  async handleLikeButton(button) {
    const songId = button.getAttribute("data-song-id");
    if (!songId) return;

    await this.toggleLike(songId, button);
    this.closeDropdowns();
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
        this.updateLikeButtons(songId, !isLiked);
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

  updateLikeButtons(songId, isLiked) {
    // Update like buttons in dropdown
    document
      .querySelectorAll(`.like-song[data-song-id="${songId}"]`)
      .forEach((button) => {
        this.updateLikeButton(button, isLiked);
      });

    // Update player like button if this song is playing
    if (
      window.musicPlayer &&
      window.musicPlayer.currentSong &&
      window.musicPlayer.currentSong.id == songId
    ) {
      this.updatePlayerLikeButton(isLiked);
    }
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
    this.closeDropdowns();
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
      modal.classList.add("show");
      document.body.style.overflow = "hidden";
    }
    this.closeDropdowns();
  }

  async handlePlayButton(button) {
    const songId = button.getAttribute("data-song-id");
    const songItem = button.closest(".song-item");
    const songTitle = songItem
      ? songItem.querySelector(".song-info h4").textContent
      : "Song";

    try {
      const response = await fetch(`api/songs.php?id=${songId}`);
      const data = await response.json();

      if (data.success && window.musicPlayer) {
        window.musicPlayer.playSong(data.song);
        this.showNotification(`Playing "${songTitle}"`);
      }
    } catch (error) {
      console.error("Error playing song:", error);
      this.showNotification("Error playing song", "error");
    }
  }

  async handlePlayAll() {
    const songItems = document.querySelectorAll(".song-item");
    const songIds = [];
    const songTitles = [];

    songItems.forEach((item) => {
      const songId = item.getAttribute("data-song-id");
      const songTitle = item.querySelector(".song-info h4").textContent;
      if (songId) {
        songIds.push(parseInt(songId));
        songTitles.push(songTitle);
      }
    });

    if (songIds.length === 0) {
      this.showNotification("No songs in album", "error");
      return;
    }

    try {
      // Fetch first song data to play immediately
      const firstSongResponse = await fetch(`api/songs.php?id=${songIds[0]}`);
      const firstSongData = await firstSongResponse.json();

      if (firstSongData.success && window.musicPlayer) {
        // Clear queue and play first song
        window.musicPlayer.clearQueue();
        window.musicPlayer.playSong(firstSongData.song);

        // Add remaining songs to queue
        for (let i = 1; i < songIds.length; i++) {
          try {
            const songResponse = await fetch(`api/songs.php?id=${songIds[i]}`);
            const songData = await songResponse.json();
            if (songData.success) {
              window.musicPlayer.addToQueue(songData.song);
            }
          } catch (error) {
            console.error("Error adding song to queue:", error);
          }
        }

        this.showNotification(`Playing ${songIds.length} songs from album`);
      }
    } catch (error) {
      console.error("Error playing all songs:", error);
      this.showNotification("Error playing songs", "error");
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
        this.closeModals();
        form.reset();
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

  showNotification(message, type = "success") {
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
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.albumDetailManager = new AlbumDetailManager();
});
