// assets/js/playlists.js
class PlaylistsManager {
  constructor() {
    this.init();
  }

  init() {
    console.log("PlaylistsManager initializing...");
    this.bindPlaylistButtons();
    this.bindCreatePlaylistForm();
    this.bindPlaylistCardEvents();
    this.bindDeletePlaylist();
    this.bindModals();
  }

  bindPlaylistButtons() {
    document.addEventListener("click", (e) => {
      // Add to playlist buttons
      if (e.target.closest(".add-to-playlist")) {
        e.preventDefault();
        const button = e.target.closest(".add-to-playlist");
        const songId = button.getAttribute("data-song-id");
        const playlistId = button.getAttribute("data-playlist-id");
        this.addToPlaylist(songId, playlistId);
      }

      // Play button on playlist cards
      if (e.target.closest(".play-btn")) {
        e.preventDefault();
        const button = e.target.closest(".play-btn");
        const playlistCard = button.closest(".playlist-card");
        if (playlistCard) {
          const playlistId = playlistCard.getAttribute("data-playlist-id");
          this.playPlaylist(playlistId);
        }
      }
    });
  }

  bindPlaylistCardEvents() {
    // Play button event for playlist cards
    document.querySelectorAll(".playlist-card .play-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        const playlistCard = btn.closest(".playlist-card");
        const playlistId = playlistCard.getAttribute("data-playlist-id");
        this.playPlaylist(playlistId);
      });
    });

    // More button dropdown
    document.addEventListener("click", (e) => {
      const moreBtn = e.target.closest(".more-btn");
      if (moreBtn) {
        e.preventDefault();
        e.stopPropagation();
        this.togglePlaylistDropdown(moreBtn);
        return;
      }

      // Close dropdowns when clicking outside
      if (
        !e.target.closest(".playlist-actions") &&
        !e.target.closest(".more-btn")
      ) {
        this.closeAllPlaylistDropdowns();
      }
    });
  }

  bindCreatePlaylistForm() {
    const form = document.getElementById("createPlaylistForm");
    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        this.createPlaylist();
      });
    }
  }

  bindDeletePlaylist() {
    // Delete playlist buttons
    document.addEventListener("click", (e) => {
      const deleteBtn = e.target.closest(".delete-playlist-btn");
      if (deleteBtn) {
        e.preventDefault();
        e.stopPropagation();
        this.openDeletePlaylistModal(deleteBtn);
      }
    });

    // Delete playlist form submission
    const deleteForm = document.getElementById("deletePlaylistForm");
    if (deleteForm) {
      deleteForm.addEventListener("submit", (e) => {
        // Submit form normally (tidak perlu AJAX karena form sudah diatur)
        console.log("Deleting playlist...");
      });
    }
  }

  bindModals() {
    // Modal open buttons
    const createPlaylistBtn = document.getElementById("createPlaylistBtn");
    const emptyCreateBtn = document.getElementById("emptyCreatePlaylistBtn");

    if (createPlaylistBtn) {
      createPlaylistBtn.addEventListener("click", () =>
        this.showCreatePlaylistModal()
      );
    }

    if (emptyCreateBtn) {
      emptyCreateBtn.addEventListener("click", () =>
        this.showCreatePlaylistModal()
      );
    }

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

    // Prevent form submission on empty title
    const createForm = document.getElementById("createPlaylistForm");
    if (createForm) {
      createForm.addEventListener("submit", (e) => {
        const title = document.getElementById("playlistTitle").value.trim();
        if (!title) {
          e.preventDefault();
          this.showNotification("Please enter a playlist title", "error");
          document.getElementById("playlistTitle").focus();
        }
      });
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
        this.showNotification("Added to playlist");
      } else {
        this.showNotification(
          data.message || "Error adding to playlist",
          "error"
        );
      }
    } catch (error) {
      console.error("Playlist error:", error);
      this.showNotification("Error adding to playlist", "error");
    }
  }

  async createPlaylist() {
    const form = document.getElementById("createPlaylistForm");
    const formData = new FormData(form);
    const songId = document.getElementById("songIdForPlaylist").value;

    try {
      const response = await fetch("api/playlists.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "create",
          title: formData.get("title"),
          description: formData.get("description"),
          song_id: songId || null,
        }),
      });

      const data = await response.json();

      if (data.success) {
        this.showNotification("Playlist created successfully");
        this.closeAllModals();
        form.reset();

        // Redirect to new playlist
        if (data.playlist_id) {
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

  async playPlaylist(playlistId) {
    try {
      const response = await fetch(
        `api/playlists.php?action=get_playlist_songs&id=${playlistId}`
      );
      const data = await response.json();

      if (data.success && data.songs && data.songs.length > 0) {
        if (window.musicPlayer) {
          await window.musicPlayer.playPlaylist(playlistId);
          this.showNotification(
            `Playing ${data.songs.length} songs from playlist`
          );
        } else {
          this.showNotification("Player not initialized", "error");
        }
      } else {
        this.showNotification("No songs in this playlist", "error");
      }
    } catch (error) {
      console.error("Error playing playlist:", error);
      this.showNotification("Error playing playlist", "error");
    }
  }

  togglePlaylistDropdown(button) {
    const dropdown = button.nextElementSibling;
    const allDropdowns = document.querySelectorAll(
      ".playlist-actions .dropdown-menu"
    );

    allDropdowns.forEach((d) => {
      if (d !== dropdown) {
        d.classList.remove("show");
      }
    });

    dropdown.classList.toggle("show");
  }

  closeAllPlaylistDropdowns() {
    document
      .querySelectorAll(".playlist-actions .dropdown-menu")
      .forEach((dropdown) => {
        dropdown.classList.remove("show");
      });
  }

  openDeletePlaylistModal(button) {
    const playlistId = button.getAttribute("data-playlist-id");
    const playlistTitle = button.getAttribute("data-playlist-title");

    // Close dropdown
    const dropdown = button.closest(".dropdown-menu");
    if (dropdown) {
      dropdown.classList.remove("show");
    }

    // Open modal
    const modal = document.getElementById("deletePlaylistModal");
    const playlistNameElement = document.getElementById("deletePlaylistName");
    const playlistIdInput = document.getElementById("deletePlaylistId");

    if (modal && playlistNameElement && playlistIdInput) {
      playlistNameElement.textContent = `"${playlistTitle}"`;
      playlistIdInput.value = playlistId;
      modal.classList.add("show");
      document.body.style.overflow = "hidden";
    }
  }

  showCreatePlaylistModal() {
    const modal = document.getElementById("createPlaylistModal");
    if (modal) {
      modal.classList.add("show");
      document.body.style.overflow = "hidden";
    }
  }

  closeAllModals() {
    document.querySelectorAll(".modal").forEach((modal) => {
      modal.classList.remove("show");
    });
    document.body.style.overflow = "";
  }

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
          <i class="fas fa-${type === "success" ? "check" : "exclamation"}"></i>
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

  async getPlaylists() {
    try {
      const response = await fetch(
        "api/playlists.php?action=get_user_playlists"
      );
      const data = await response.json();
      return data.playlists || [];
    } catch (error) {
      console.error("Error getting playlists:", error);
      return [];
    }
  }
}

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  window.playlistsManager = new PlaylistsManager();

  // Auto-hide notifications
  setTimeout(() => {
    document.querySelectorAll(".notification.show").forEach((notification) => {
      notification.classList.remove("show");
      setTimeout(() => notification.remove(), 300);
    });
  }, 3000);
});
