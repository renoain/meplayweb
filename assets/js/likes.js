// assets/js/likes.js - CLEAN VERSION

class LikesManager {
  constructor() {
    this.isInitialized = false;
    this.init();
  }

  init() {
    if (this.isInitialized) return;

    console.log("🎵 LikesManager initialized");
    this.setupDirectEventBinding();
    this.checkAllLikeStatuses();
    this.isInitialized = true;
  }

  setupDirectEventBinding() {
    // Bind to existing elements
    this.bindToExistingElements();

    // Setup outside click handler
    document.addEventListener("click", (e) => {
      if (!e.target.closest(".song-actions")) {
        this.closeAllDropdowns();
      }
    });
  }

  bindToExistingElements() {
    // Like buttons
    document.querySelectorAll(".like-song").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handleLikeButton(button);
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

    // More buttons
    document.querySelectorAll(".more-btn").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.stopPropagation();
        this.toggleDropdown(button);
      });
    });

    // Other action buttons
    this.bindActionButtons();
  }

  bindActionButtons() {
    // Add to queue
    document.querySelectorAll(".add-to-queue").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handleAddToQueue(button);
        this.closeAllDropdowns();
      });
    });

    // Add to playlist
    document
      .querySelectorAll(".add-to-playlist:not(.disabled)")
      .forEach((button) => {
        button.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          this.handleAddToPlaylist(button);
          this.closeAllDropdowns();
        });
      });

    // Create playlist
    document.querySelectorAll(".create-playlist").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handleCreatePlaylist(button);
        this.closeAllDropdowns();
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
  }

  toggleDropdown(button) {
    const dropdown = button.nextElementSibling;
    const isOpen = dropdown.classList.contains("show");

    this.closeAllDropdowns();

    if (!isOpen) {
      dropdown.classList.add("show");
    }
  }

  closeAllDropdowns() {
    document.querySelectorAll(".dropdown-menu.show").forEach((dropdown) => {
      dropdown.classList.remove("show");
    });
  }

  async checkAllLikeStatuses() {
    const likeButtons = document.querySelectorAll(".like-song");

    for (let button of likeButtons) {
      const songId = button.getAttribute("data-song-id");
      if (songId) {
        await this.checkLikeStatus(songId);
      }
    }
  }

  async checkLikeStatus(songId) {
    try {
      const response = await fetch(`api/likes.php?song_id=${songId}`);
      const data = await response.json();

      if (data.success) {
        this.updateLikeButtons(songId, data.is_liked);
      }
    } catch (error) {
      console.error("Error checking like status:", error);
    }
  }

  updateLikeButtons(songId, isLiked) {
    document
      .querySelectorAll(`.like-song[data-song-id="${songId}"]`)
      .forEach((button) => {
        this.updateLikeButton(button, isLiked);
      });

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

  async handleLikeButton(button) {
    const songId = button.getAttribute("data-song-id");
    if (!songId) return;

    await this.toggleLike(songId, button);
    this.closeAllDropdowns();
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
        showNotification(
          isLiked ? "Removed from liked songs" : "Added to liked songs"
        );

        if (window.location.pathname.includes("likes.php") && isLiked) {
          setTimeout(() => {
            this.removeSongFromList(songId);
          }, 1000);
        }
      } else {
        showNotification(data.message || "Error updating like", "error");
      }
    } catch (error) {
      console.error("Error:", error);
      showNotification("Error updating like", "error");
    }
  }

  async handleAddToQueue(button) {
    const songId = button.getAttribute("data-song-id");
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

  async handleAddToPlaylist(button) {
    const songId = button.getAttribute("data-song-id");
    const playlistId = button.getAttribute("data-playlist-id");

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
        showNotification("Added to playlist");
      } else {
        showNotification(data.message || "Error adding to playlist", "error");
      }
    } catch (error) {
      console.error("Add to playlist error:", error);
      showNotification("Error adding to playlist", "error");
    }
  }

  handleCreatePlaylist(button) {
    const songId = button.getAttribute("data-song-id");
    const modal = document.getElementById("createPlaylistModal");
    const songIdInput = document.getElementById("songIdForPlaylist");

    if (modal && songIdInput) {
      songIdInput.value = songId;
      modal.classList.add("show");
    }
  }

  async handlePlayButton(button) {
    const songItem =
      button.closest(".song-item") || button.closest(".song-card");
    const songId = songItem.getAttribute("data-song-id");

    try {
      const response = await fetch(`api/songs.php?id=${songId}`);
      const data = await response.json();

      if (data.success && window.musicPlayer) {
        window.musicPlayer.playSong(data.song);
        showNotification("Now playing");
      }
    } catch (error) {
      console.error("Error playing song:", error);
      showNotification("Error playing song", "error");
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
}

// Global notification function
function showNotification(message, type = "success") {
  document.querySelectorAll(".notification").forEach((notification) => {
    notification.remove();
  });

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

// Initialize
document.addEventListener("DOMContentLoaded", () => {
  window.likesManager = new LikesManager();
});
