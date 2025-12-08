// assets/js/likes.js - DIPERBAIKI untuk dropdown kiri

class LikesManager {
  constructor() {
    this.isInitialized = false;
    this.activeDropdown = null;
    this.init();
  }

  init() {
    if (this.isInitialized) return;

    console.log("🎵 LikesManager initialized");
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

    // Like buttons
    document.querySelectorAll(".like-song").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handleLikeButton(button);
      });
    });

    // Add to queue buttons
    document.querySelectorAll(".add-to-queue").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handleAddToQueue(button);
      });
    });

    // Add to playlist buttons
    document
      .querySelectorAll(".add-to-playlist:not(.disabled)")
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

    // Play buttons
    document.querySelectorAll(".play-btn").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handlePlayButton(button);
      });
    });

    // Player like button
    const playerLikeBtn = document.getElementById("nowPlayingLike");
    if (playerLikeBtn) {
      playerLikeBtn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handlePlayerLikeButton(playerLikeBtn);
      });
    }

    // Play All button
    const playAllBtn = document.getElementById("playAllLiked");
    if (playAllBtn) {
      playAllBtn.addEventListener("click", () => {
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
    const dropdown = document.getElementById(`dropdown-${songId}`);

    if (!dropdown) return;

    if (
      this.activeDropdown === dropdown &&
      dropdown.style.display === "block"
    ) {
      dropdown.style.display = "none";
      this.activeDropdown = null;
    } else {
      this.closeDropdowns();

      // Position dropdown to the LEFT
      const rect = button.getBoundingClientRect();
      dropdown.style.display = "block";
      dropdown.style.position = "fixed";
      dropdown.style.zIndex = "1000";

      // Calculate position - align to LEFT side of button
      dropdown.style.top = `${rect.bottom + 5}px`;

      // Position to the LEFT of the button
      const dropdownWidth = dropdown.offsetWidth;
      const leftPosition = rect.left - dropdownWidth;

      // If dropdown would go off screen on left, align to right instead
      if (leftPosition < 10) {
        dropdown.style.left = `${rect.right + 5}px`;
      } else {
        dropdown.style.left = `${leftPosition}px`;
      }

      this.activeDropdown = dropdown;
    }
  }

  closeDropdowns() {
    if (this.activeDropdown) {
      this.activeDropdown.style.display = "none";
      this.activeDropdown = null;
    }
  }

  closeModals() {
    document.querySelectorAll(".modal").forEach((modal) => {
      modal.classList.remove("show");
    });
  }

  async handleLikeButton(button) {
    const songId = button.getAttribute("data-song-id");
    if (!songId) return;

    await this.toggleLike(songId, button);
    this.closeDropdowns();
  }

  async handlePlayerLikeButton(button) {
    if (window.musicPlayer && window.musicPlayer.currentSong) {
      const songId = window.musicPlayer.currentSong.id;
      await this.toggleLike(songId, button);
    }
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
          isLiked ? "Removed from liked songs" : "Added to liked songs"
        );

        // If on likes page and unliking, remove from list
        if (window.location.pathname.includes("likes.php") && isLiked) {
          setTimeout(() => {
            this.removeSongFromList(songId);
          }, 1000);
        }
      } else {
        this.showNotification(data.message || "Error updating like", "error");
      }
    } catch (error) {
      console.error("Error:", error);
      this.showNotification("Error updating like", "error");
    }
  }

  updateLikeButtons(songId, isLiked) {
    // Update all like buttons for this song
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
    try {
      const response = await fetch(`api/songs.php?id=${songId}`);
      const data = await response.json();

      if (data.success && window.musicPlayer) {
        window.musicPlayer.addToQueue(data.song);
        this.showNotification("Added to queue");
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
        this.showNotification(`Added to "${playlistName}"`);
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
    this.closeDropdowns();
  }

  handleCreatePlaylist(button) {
    const songId = button.getAttribute("data-song-id");
    const modal = document.getElementById("createPlaylistModal");
    const songIdInput = document.getElementById("songIdForPlaylist");

    if (modal && songIdInput) {
      songIdInput.value = songId;
      modal.classList.add("show");
    }
    this.closeDropdowns();
  }

  async handlePlayButton(button) {
    const songId = button.getAttribute("data-song-id");

    try {
      const response = await fetch(`api/songs.php?id=${songId}`);
      const data = await response.json();

      if (data.success && window.musicPlayer) {
        window.musicPlayer.playSong(data.song);
        this.showNotification("Now playing");
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

    if (songIds.length > 0) {
      // Save to localStorage as current playlist
      localStorage.setItem(
        "meplay_current_playlist",
        JSON.stringify({
          ids: songIds,
          titles: songTitles,
          name: "Liked Songs",
        })
      );

      // Add all to queue
      songIds.forEach((id, index) => {
        this.addToLocalQueue(id, songTitles[index]);
      });

      this.showNotification(`Playing ${songIds.length} liked songs`);
    }
  }

  addToLocalQueue(songId, songTitle = "Song") {
    // Initialize queue in localStorage if not exists
    let queue = JSON.parse(localStorage.getItem("meplay_queue") || "[]");

    // Check if song already in queue
    if (!queue.includes(songId)) {
      queue.push(songId);
      localStorage.setItem("meplay_queue", JSON.stringify(queue));
      console.log("Added to local queue:", songId);
      return true;
    }
    return false;
  }

  async handleCreatePlaylistForm() {
    const form = document.getElementById("createPlaylistForm");
    const formData = new FormData(form);
    const songId = document.getElementById("songIdForPlaylist").value;
    const title = formData.get("title");

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

  removeSongFromList(songId) {
    const songElement = document.querySelector(
      `.song-item[data-song-id="${songId}"]`
    );
    if (songElement) {
      songElement.style.transition = "all 0.3s ease";
      songElement.style.opacity = "0";
      songElement.style.height = "0";
      songElement.style.padding = "0";
      songElement.style.margin = "0";
      songElement.style.border = "none";

      setTimeout(() => {
        songElement.remove();
        this.updateSongCount();

        if (document.querySelectorAll(".song-item").length === 0) {
          this.showEmptyState();
        }
      }, 300);
    }
  }

  updateSongCount() {
    const songCount = document.querySelectorAll(".song-item").length;
    const countElement = document.querySelector(".header-info p");
    if (countElement) {
      countElement.textContent = songCount + " liked songs";
    }
  }

  showEmptyState() {
    const songsList = document.querySelector(".songs-list");
    if (songsList) {
      songsList.innerHTML = `
                <div class="no-songs">
                    <div class="no-songs-content">
                        <i class="fas fa-heart fa-4x"></i>
                        <h2>No liked songs yet</h2>
                        <p>Start liking songs to see them here</p>
                        <a href="index.php" class="btn-primary">Discover Music</a>
                    </div>
                </div>
            `;
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
                <i class="fas fa-${
                  type === "success" ? "check" : "exclamation"
                }"></i>
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
  window.likesManager = new LikesManager();
});
