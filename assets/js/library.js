// assets/js/library.js
class LibraryManager {
  constructor() {
    this.currentTab = this.getCurrentTab();
    this.initializeEventListeners();
    this.checkLikeStatuses();
  }

  initializeEventListeners() {
    // Play all liked songs
    const playAllLiked = document.getElementById("playAllLiked");
    if (playAllLiked) {
      playAllLiked.addEventListener("click", () => this.playAllLikedSongs());
    }

    // Create playlist buttons
    const createPlaylistBtn = document.getElementById("createPlaylistBtn");
    const createFirstPlaylist = document.getElementById("createFirstPlaylist");
    const createPlaylistModal = document.getElementById("createPlaylistModal");
    const closePlaylistModal = document.getElementById("closePlaylistModal");
    const cancelPlaylist = document.getElementById("cancelPlaylist");
    const savePlaylist = document.getElementById("savePlaylist");

    if (createPlaylistBtn) {
      createPlaylistBtn.addEventListener("click", () =>
        this.showCreatePlaylistModal()
      );
    }

    if (createFirstPlaylist) {
      createFirstPlaylist.addEventListener("click", () =>
        this.showCreatePlaylistModal()
      );
    }

    if (closePlaylistModal) {
      closePlaylistModal.addEventListener("click", () =>
        this.hideCreatePlaylistModal()
      );
    }

    if (cancelPlaylist) {
      cancelPlaylist.addEventListener("click", () =>
        this.hideCreatePlaylistModal()
      );
    }

    if (savePlaylist) {
      savePlaylist.addEventListener("click", () => this.createPlaylist());
    }

    // Clear recent history
    const clearRecentHistory = document.getElementById("clearRecentHistory");
    if (clearRecentHistory) {
      clearRecentHistory.addEventListener("click", () =>
        this.clearRecentHistory()
      );
    }

    // Song item interactions
    this.initializeSongInteractions();

    // Playlist interactions
    this.initializePlaylistInteractions();

    // Search functionality
    const librarySearch = document.getElementById("librarySearch");
    if (librarySearch) {
      librarySearch.addEventListener("input", (e) =>
        this.handleSearch(e.target.value)
      );
    }
  }

  getCurrentTab() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get("tab") || "liked";
  }

  initializeSongInteractions() {
    // Play track buttons
    document.querySelectorAll(".play-track-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const songItem = e.target.closest(".song-item");
        this.playSongFromItem(songItem);
      });
    });

    // Song item clicks
    document.querySelectorAll(".song-item").forEach((item) => {
      item.addEventListener("click", (e) => {
        if (
          !e.target.closest(".action-btn") &&
          !e.target.closest(".play-track-btn")
        ) {
          this.playSongFromItem(item);
        }
      });
    });

    // Like buttons
    document.querySelectorAll(".like-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        this.toggleLike(e.target.closest(".like-btn"));
      });
    });
  }

  initializePlaylistInteractions() {
    // Play playlist buttons
    document.querySelectorAll(".playlist-card .play-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const playlistCard = e.target.closest(".playlist-card");
        this.playPlaylist(playlistCard);
      });
    });

    // Playlist card clicks
    document.querySelectorAll(".playlist-card").forEach((card) => {
      card.addEventListener("click", (e) => {
        if (
          !e.target.closest(".play-btn") &&
          !e.target.closest(".action-btn")
        ) {
          this.viewPlaylist(card);
        }
      });
    });
  }

  playSongFromItem(songItem) {
    const songId = songItem.dataset.songId;
    const songDataStr = songItem.dataset.songData;

    if (songDataStr && window.musicPlayer) {
      try {
        const songData = JSON.parse(songDataStr);
        window.musicPlayer.playSong(songData);
      } catch (error) {
        console.error("Error parsing song data:", error);
        this.playSongById(songId);
      }
    } else if (window.musicPlayer) {
      this.playSongById(songId);
    }
  }

  playSongById(songId) {
    fetch(`api/player.php?action=get_song&id=${songId}`)
      .then((response) => response.json())
      .then((data) => {
        if (data.success && data.song) {
          window.musicPlayer.playSong(data.song);
        } else {
          showNotification("Gagal memuat data lagu", "error");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showNotification("Error memuat lagu", "error");
      });
  }

  async playAllLikedSongs() {
    try {
      const response = await fetch(
        "api/likes.php?action=get_liked_songs&limit=100"
      );
      const data = await response.json();

      if (data.success && data.data.songs.length > 0) {
        window.musicPlayer.setQueue(data.data.songs);
        if (data.data.songs[0]) {
          window.musicPlayer.playSong(data.data.songs[0]);
        }
        showNotification(
          `Memutar ${data.data.songs.length} lagu favorit`,
          "success"
        );
      } else {
        showNotification("Tidak ada lagu favorit untuk diputar", "info");
      }
    } catch (error) {
      console.error("Error playing all liked songs:", error);
      showNotification("Error memutar lagu favorit", "error");
    }
  }

  toggleLike(likeBtn) {
    const songId = likeBtn.dataset.songId;
    const isLiked = likeBtn.classList.contains("liked");
    const action = isLiked ? "unlike" : "like";

    fetch("api/likes.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        song_id: songId,
        action: action,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          if (isLiked) {
            likeBtn.classList.remove("liked");
            likeBtn.querySelector("i").className = "far fa-heart";
            likeBtn.title = "Tambahkan ke Favorit";

            // Remove from list if in liked songs tab
            if (this.currentTab === "liked") {
              const songItem = likeBtn.closest(".song-item");
              if (songItem) {
                songItem.style.opacity = "0";
                setTimeout(() => {
                  songItem.remove();
                  this.updateEmptyState();
                }, 300);
              }
            }

            showNotification("Lagu dihapus dari favorit", "success");
          } else {
            likeBtn.classList.add("liked");
            likeBtn.querySelector("i").className = "fas fa-heart";
            likeBtn.title = "Hapus dari Favorit";
            showNotification("Lagu ditambahkan ke favorit", "success");
          }
        } else {
          showNotification(data.message || "Gagal mengupdate favorit", "error");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showNotification("Error mengupdate favorit", "error");
      });
  }

  checkLikeStatuses() {
    // Update like button states based on current user's likes
    document.querySelectorAll(".like-btn").forEach((btn) => {
      const songId = btn.dataset.songId;
      if (songId) {
        this.checkSongLikeStatus(songId, btn);
      }
    });
  }

  async checkSongLikeStatus(songId, likeBtn) {
    try {
      const response = await fetch(
        `api/likes.php?action=is_liked&song_id=${songId}`
      );
      const data = await response.json();

      if (data.success && data.is_liked) {
        likeBtn.classList.add("liked");
        likeBtn.querySelector("i").className = "fas fa-heart";
        likeBtn.title = "Hapus dari Favorit";
      }
    } catch (error) {
      console.error("Error checking like status:", error);
    }
  }

  showCreatePlaylistModal() {
    const modal = document.getElementById("createPlaylistModal");
    modal.classList.add("show");
  }

  hideCreatePlaylistModal() {
    const modal = document.getElementById("createPlaylistModal");
    modal.classList.remove("show");

    // Reset form
    document.getElementById("createPlaylistForm").reset();
  }

  createPlaylist() {
    const form = document.getElementById("createPlaylistForm");
    const formData = new FormData(form);

    const playlistData = {
      title: formData.get("title"),
      description: formData.get("description"),
      is_public: formData.get("is_public") === "1",
    };

    if (!playlistData.title.trim()) {
      showNotification("Judul playlist harus diisi", "error");
      return;
    }

    fetch("api/playlists.php?action=create", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(playlistData),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showNotification("Playlist berhasil dibuat", "success");
          this.hideCreatePlaylistModal();
          // Reload page to show new playlist
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          showNotification(data.message || "Gagal membuat playlist", "error");
        }
      })
      .catch((error) => {
        console.error("Error creating playlist:", error);
        showNotification("Error membuat playlist", "error");
      });
  }

  clearRecentHistory() {
    if (
      !confirm("Apakah Anda yakin ingin menghapus semua riwayat pemutaran?")
    ) {
      return;
    }

    fetch("api/player.php?action=clear_recent_history", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showNotification("Riwayat pemutaran berhasil dihapus", "success");
          // Remove all recent items from UI
          document.querySelectorAll(".song-item").forEach((item) => {
            item.remove();
          });
          this.updateEmptyState();
        } else {
          showNotification(data.message || "Gagal menghapus riwayat", "error");
        }
      })
      .catch((error) => {
        console.error("Error clearing recent history:", error);
        showNotification("Error menghapus riwayat", "error");
      });
  }

  playPlaylist(playlistCard) {
    const playlistId = playlistCard.dataset.playlistId;
    showNotification("Fitur memutar playlist akan segera tersedia", "info");
  }

  viewPlaylist(playlistCard) {
    const playlistId = playlistCard.dataset.playlistId;
    window.location.href = `playlist.php?id=${playlistId}`;
  }

  handleSearch(query) {
    const items = document.querySelectorAll(".list-item, .playlist-card");

    if (!query.trim()) {
      // Show all items if search is empty
      items.forEach((item) => {
        item.style.display = "";
      });
      return;
    }

    const searchTerm = query.toLowerCase();

    items.forEach((item) => {
      const text = item.textContent.toLowerCase();
      if (text.includes(searchTerm)) {
        item.style.display = "";
      } else {
        item.style.display = "none";
      }
    });
  }

  updateEmptyState() {
    // Check if any items remain and show/hide empty state
    const currentTab = this.currentTab;
    const items = document.querySelectorAll(".list-item, .playlist-card");
    const emptyStates = document.querySelectorAll(".empty-state");

    if (items.length === 0) {
      emptyStates.forEach((state) => {
        state.style.display = "block";
      });
    } else {
      emptyStates.forEach((state) => {
        state.style.display = "none";
      });
    }
  }
}

// Initialize library manager when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
  window.libraryManager = new LibraryManager();
  console.log("Library Manager initialized");
});
