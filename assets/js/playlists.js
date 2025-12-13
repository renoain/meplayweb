class PlaylistsManager {
  constructor() {
    this.init();
  }

  init() {
    this.bindPlaylistButtons();
    this.bindCreatePlaylistForm();
  }

  bindPlaylistButtons() {
    // Add to playlist buttons
    document.addEventListener("click", (e) => {
      if (e.target.closest(".add-to-playlist")) {
        e.preventDefault();
        const button = e.target.closest(".add-to-playlist");
        const songId = button.getAttribute("data-song-id");
        const playlistId = button.getAttribute("data-playlist-id");
        this.addToPlaylist(songId, playlistId);
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
        showNotification("Playlist created successfully");

        // Close modal
        const modal = document.getElementById("createPlaylistModal");
        modal.classList.remove("show");
        form.reset();

        // Refresh playlists if needed
        this.refreshPlaylists();
      } else {
        showNotification(data.message || "Error creating playlist", "error");
      }
    } catch (error) {
      console.error("Create playlist error:", error);
      showNotification("Error creating playlist", "error");
    }
  }

  async refreshPlaylists() {
    // This would refresh the playlists dropdown
    // Implementation depends on specific requirements
    console.log("Refreshing playlists...");
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

// Initialize playlists manager
document.addEventListener("DOMContentLoaded", () => {
  window.playlistsManager = new PlaylistsManager();
});
