// assets/js/player.js - FIXED VERSION
class MusicPlayer {
  constructor() {
    this.audio = document.getElementById("audioElement");
    this.currentSong = null;
    this.queue = [];
    this.currentIndex = -1;
    this.isPlaying = false;
    this.isShuffle = false;
    this.repeatMode = "none";
    this.volume = 0.7;
    this.isDraggingProgress = false;
    this.isDraggingVolume = false;
    this.isPlayRequestPending = false; // Flag untuk menghindari race condition

    this.init();
  }

  init() {
    console.log("🎵 MusicPlayer initializing...");

    // Basic controls
    document
      .getElementById("playPauseBtn")
      .addEventListener("click", () => this.togglePlay());
    document
      .getElementById("prevBtn")
      .addEventListener("click", () => this.prev());
    document
      .getElementById("nextBtn")
      .addEventListener("click", () => this.next());
    document
      .getElementById("shuffleBtn")
      .addEventListener("click", () => this.toggleShuffle());
    document
      .getElementById("repeatBtn")
      .addEventListener("click", () => this.toggleRepeat());

    // Progress bar
    const progressBar = document.getElementById("progressBar");
    progressBar.addEventListener("click", (e) => this.seek(e));
    progressBar.addEventListener("mousedown", (e) => this.startDragProgress(e));

    // Volume bar
    const volumeBar = document.getElementById("volumeBar");
    volumeBar.addEventListener("click", (e) => this.setVolume(e));
    volumeBar.addEventListener("mousedown", (e) => this.startDragVolume(e));

    // Global mouse events for dragging
    document.addEventListener("mousemove", (e) => {
      if (this.isDraggingProgress) this.dragProgress(e);
      if (this.isDraggingVolume) this.dragVolume(e);
    });

    document.addEventListener("mouseup", () => {
      if (this.isDraggingProgress) {
        this.isDraggingProgress = false;
        document.getElementById("progressBar").classList.remove("dragging");
      }
      if (this.isDraggingVolume) {
        this.isDraggingVolume = false;
        document.getElementById("volumeBar").classList.remove("dragging");
      }
    });

    // Audio events
    this.audio.addEventListener("timeupdate", () => this.updateProgress());
    this.audio.addEventListener("ended", () => this.onSongEnd());
    this.audio.addEventListener("loadedmetadata", () =>
      this.onMetadataLoaded()
    );
    this.audio.addEventListener("error", (e) => this.onAudioError(e));

    // Like button
    const likeBtn = document.getElementById("nowPlayingLike");
    if (likeBtn) {
      likeBtn.addEventListener("click", () => this.toggleCurrentLike());
    }

    // Queue button
    const queueBtn = document.getElementById("queueBtn");
    if (queueBtn) {
      queueBtn.addEventListener("click", () => this.showQueue());
    }

    // Modal close buttons
    document.querySelectorAll(".close-modal").forEach((btn) => {
      btn.addEventListener("click", () => {
        this.hideQueue();
      });
    });

    // Clear queue button
    const clearQueueBtn = document.getElementById("clearQueue");
    if (clearQueueBtn) {
      clearQueueBtn.addEventListener("click", () => this.clearQueue());
    }

    // Close modal when clicking outside
    document.getElementById("queueModal").addEventListener("click", (e) => {
      if (e.target === document.getElementById("queueModal")) {
        this.hideQueue();
      }
    });

    // Close with Escape key
    document.addEventListener("keydown", (e) => {
      if (
        e.key === "Escape" &&
        document.getElementById("queueModal").classList.contains("show")
      ) {
        this.hideQueue();
      }
    });

    // Restore volume and update UI
    this.audio.volume = this.volume;
    this.updateVolumeUI();

    // Initialize from localStorage
    this.restoreState();

    // Setup global player instance
    window.musicPlayer = this;

    console.log("✅ MusicPlayer initialized successfully");
  }

  // VOLUME CONTROL
  startDragVolume(e) {
    e.preventDefault();
    this.isDraggingVolume = true;
    document.getElementById("volumeBar").classList.add("dragging");
    this.dragVolume(e);
  }

  dragVolume(e) {
    if (!this.isDraggingVolume) return;

    const volumeBar = document.getElementById("volumeBar");
    const rect = volumeBar.getBoundingClientRect();
    let percent = (e.clientX - rect.left) / rect.width;
    percent = Math.max(0, Math.min(1, percent));

    this.volume = percent;
    this.updateVolumeUI();
    this.saveState();
  }

  setVolume(e) {
    const volumeBar = document.getElementById("volumeBar");
    const rect = volumeBar.getBoundingClientRect();
    let percent = (e.clientX - rect.left) / rect.width;
    percent = Math.max(0, Math.min(1, percent));

    this.volume = percent;
    this.updateVolumeUI();
    this.saveState();
  }

  updateVolumeUI() {
    this.audio.volume = this.volume;

    const volumeProgress = document.getElementById("volumeProgress");
    const volumeHandle = document.getElementById("volumeHandle");

    if (volumeProgress) {
      volumeProgress.style.width = `${this.volume * 100}%`;
    }

    if (volumeHandle) {
      volumeHandle.style.left = `${this.volume * 100}%`;
    }

    // Update volume icon
    const volumeBtn = document.getElementById("volumeBtn");
    if (volumeBtn) {
      let iconClass = "fa-volume-up";
      if (this.volume === 0) {
        iconClass = "fa-volume-mute";
      } else if (this.volume < 0.3) {
        iconClass = "fa-volume-down";
      }

      volumeBtn.innerHTML = `<i class="fas ${iconClass}"></i>`;
      volumeBtn.title = `Volume: ${Math.round(this.volume * 100)}%`;
    }
  }

  // PROGRESS BAR METHODS
  startDragProgress(e) {
    e.preventDefault();
    this.isDraggingProgress = true;
    document.getElementById("progressBar").classList.add("dragging");
    this.dragProgress(e);
  }

  dragProgress(e) {
    if (!this.isDraggingProgress) return;

    const progressBar = document.getElementById("progressBar");
    const rect = progressBar.getBoundingClientRect();
    let percent = (e.clientX - rect.left) / rect.width;
    percent = Math.max(0, Math.min(1, percent));

    this.updateProgressUI(percent);

    if (this.audio.duration && !isNaN(this.audio.duration)) {
      this.audio.currentTime = percent * this.audio.duration;
    }
  }

  seek(e) {
    const progressBar = document.getElementById("progressBar");
    const rect = progressBar.getBoundingClientRect();
    let percent = (e.clientX - rect.left) / rect.width;
    percent = Math.max(0, Math.min(1, percent));

    if (this.audio.duration && !isNaN(this.audio.duration)) {
      this.audio.currentTime = percent * this.audio.duration;
      this.updateProgressUI(percent);
    }
  }

  updateProgressUI(percent) {
    const progress = document.getElementById("progress");
    const progressHandle = document.getElementById("progressHandle");

    if (progress) {
      progress.style.width = `${percent * 100}%`;
    }

    if (progressHandle) {
      progressHandle.style.left = `${percent * 100}%`;
    }

    if (this.audio.duration && !isNaN(this.audio.duration)) {
      const newTime = percent * this.audio.duration;
      document.getElementById("currentTime").textContent =
        this.formatTime(newTime);
    }
  }

  updateProgress() {
    if (this.isDraggingProgress) return;

    const currentTime = document.getElementById("currentTime");
    const totalTime = document.getElementById("totalTime");

    if (this.audio.duration && !isNaN(this.audio.duration)) {
      const percent = this.audio.currentTime / this.audio.duration;

      const progress = document.getElementById("progress");
      const progressHandle = document.getElementById("progressHandle");

      if (progress) {
        progress.style.width = `${percent * 100}%`;
      }

      if (progressHandle) {
        progressHandle.style.left = `${percent * 100}%`;
      }

      if (currentTime) {
        currentTime.textContent = this.formatTime(this.audio.currentTime);
      }

      if (totalTime) {
        totalTime.textContent = this.formatTime(this.audio.duration);
      }
    }
  }

  // QUEUE MODAL METHODS
  showQueue() {
    const queueModal = document.getElementById("queueModal");
    if (queueModal) {
      queueModal.classList.add("show");
      document.body.style.overflow = "hidden";
    }
  }

  hideQueue() {
    const queueModal = document.getElementById("queueModal");
    if (queueModal) {
      queueModal.classList.remove("show");
      document.body.style.overflow = "";
    }
  }

  updateQueueUI() {
    const queueList = document.getElementById("queueList");
    if (!queueList) return;

    if (this.queue.length === 0) {
      queueList.innerHTML = `
        <div class="queue-empty">
          <i class="fas fa-music"></i>
          <p>Your queue is empty</p>
          <p class="text-muted">Add songs from library, playlists, or search</p>
        </div>
      `;
      return;
    }

    queueList.innerHTML = this.queue
      .map(
        (song, index) => `
      <div class="queue-item ${
        index === this.currentIndex ? "active" : ""
      }" data-index="${index}">
        <div class="queue-item-info">
          <img src="${this.getCoverPath(song.cover_image)}" alt="${song.title}">
          <div>
            <h4>${song.title}</h4>
            <p>${song.artist_name || "Unknown Artist"}</p>
          </div>
        </div>
        <div class="queue-item-actions">
          <button class="btn-icon play-queue-item" data-index="${index}" title="Play">
            <i class="fas fa-play"></i>
          </button>
          <button class="btn-icon remove-queue-item" data-index="${index}" title="Remove">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>
    `
      )
      .join("");

    // Add event listeners
    queueList.querySelectorAll(".play-queue-item").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const index = parseInt(btn.dataset.index);
        this.playSong(this.queue[index], index);
        this.hideQueue();
      });
    });

    queueList.querySelectorAll(".remove-queue-item").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const index = parseInt(btn.dataset.index);
        this.removeFromQueue(index);
      });
    });

    queueList.querySelectorAll(".queue-item").forEach((item) => {
      item.addEventListener("click", (e) => {
        if (!e.target.closest(".btn-icon")) {
          const index = parseInt(item.dataset.index);
          this.playSong(this.queue[index], index);
          this.hideQueue();
        }
      });
    });
  }

  // PLAYBACK METHODS
  playSong(song, index = -1, addToQueue = false) {
    if (!song || !song.audio_file) {
      console.error("Invalid song data:", song);
      return;
    }

    // If same song is already playing, just toggle play
    if (this.currentSong && this.currentSong.id === song.id && this.audio.src) {
      this.togglePlay();
      return;
    }

    // Set current song
    this.currentSong = song;

    // Update UI
    this.updateNowPlaying();

    // Set audio source
    const audioPath = `uploads/audio/${song.audio_file}`;

    // Cancel any pending play request
    if (this.isPlayRequestPending) {
      this.isPlayRequestPending = false;
    }

    // Set source and load
    this.audio.src = audioPath;
    this.audio.load();

    // Set queue index
    if (index >= 0) {
      this.currentIndex = index;
    }

    // Add to queue if not already
    if (addToQueue && !this.queue.some((s) => s.id === song.id)) {
      this.queue.push(song);
      this.updateQueueUI();
    }

    // Update like button
    this.updateLikeButton(song.id);

    // Play the song
    this.play()
      .then(() => {
        this.incrementPlayCount(song.id);
        this.addToRecentlyPlayed(song.id);
        this.saveState();
      })
      .catch((error) => {
        console.error("Play failed:", error);
        if (error.name !== "AbortError") {
          this.showNotification("Failed to play audio", "error");
        }
      });
  }

  async play() {
    if (this.isPlayRequestPending) return;

    this.isPlayRequestPending = true;

    try {
      await this.audio.play();
      this.isPlaying = true;
      this.isPlayRequestPending = false;

      const playBtn = document.getElementById("playPauseBtn");
      if (playBtn) {
        playBtn.innerHTML = '<i class="fas fa-pause"></i>';
        playBtn.title = "Pause";
      }
    } catch (error) {
      this.isPlayRequestPending = false;

      // Only log if it's not an AbortError
      if (error.name !== "AbortError") {
        console.error("Play error:", error);
      }
    }
  }

  pause() {
    // Cancel any pending play request
    this.isPlayRequestPending = false;

    this.audio.pause();
    this.isPlaying = false;
    const playBtn = document.getElementById("playPauseBtn");
    if (playBtn) {
      playBtn.innerHTML = '<i class="fas fa-play"></i>';
      playBtn.title = "Play";
    }
  }

  togglePlay() {
    if (this.isPlaying) {
      this.pause();
    } else {
      this.play();
    }
  }

  next() {
    if (this.queue.length === 0) return;

    if (this.isShuffle) {
      this.playRandom();
    } else {
      this.currentIndex = (this.currentIndex + 1) % this.queue.length;
      this.playSong(this.queue[this.currentIndex], this.currentIndex);
    }
  }

  prev() {
    if (this.queue.length === 0) return;

    if (this.audio.currentTime > 3) {
      this.audio.currentTime = 0;
    } else {
      if (this.isShuffle) {
        this.playRandom();
      } else {
        this.currentIndex =
          this.currentIndex <= 0
            ? this.queue.length - 1
            : this.currentIndex - 1;
        this.playSong(this.queue[this.currentIndex], this.currentIndex);
      }
    }
  }

  playRandom() {
    if (this.queue.length <= 1) return;

    let newIndex;
    do {
      newIndex = Math.floor(Math.random() * this.queue.length);
    } while (newIndex === this.currentIndex && this.queue.length > 1);

    this.currentIndex = newIndex;
    this.playSong(this.queue[this.currentIndex], this.currentIndex);
  }

  toggleShuffle() {
    this.isShuffle = !this.isShuffle;
    const btn = document.getElementById("shuffleBtn");
    if (btn) {
      btn.classList.toggle("active", this.isShuffle);
      btn.title = this.isShuffle ? "Shuffle On" : "Shuffle Off";
    }
    this.saveState();
  }

  toggleRepeat() {
    const modes = ["none", "one", "all"];
    const btn = document.getElementById("repeatBtn");

    if (!btn) return;

    let currentIndex = modes.indexOf(this.repeatMode);
    this.repeatMode = modes[(currentIndex + 1) % modes.length];

    btn.className = "control-btn";
    btn.classList.add(`repeat-${this.repeatMode}`);

    const titles = {
      none: "No Repeat",
      one: "Repeat One",
      all: "Repeat All",
    };
    btn.title = titles[this.repeatMode];

    this.saveState();
  }

  onSongEnd() {
    if (this.repeatMode === "one") {
      this.audio.currentTime = 0;
      this.play();
    } else if (this.repeatMode === "all" || this.isShuffle) {
      this.next();
    } else if (this.currentIndex < this.queue.length - 1) {
      this.next();
    } else {
      this.pause();
    }
  }

  addToQueue(song, playNext = false) {
    if (!song) return;

    if (playNext) {
      this.queue.splice(this.currentIndex + 1, 0, song);
    } else {
      this.queue.push(song);
    }

    this.updateQueueUI();
    this.showNotification(`Added "${song.title}" to queue`);
    this.saveState();
  }

  removeFromQueue(index) {
    if (index >= 0 && index < this.queue.length) {
      const removed = this.queue.splice(index, 1)[0];

      if (index < this.currentIndex) {
        this.currentIndex--;
      } else if (index === this.currentIndex) {
        this.currentIndex = -1;
        this.currentSong = null;
        this.audio.src = "";
        this.pause();
        this.updateNowPlaying();
      }

      this.updateQueueUI();
      this.showNotification(`Removed "${removed.title}" from queue`);
      this.saveState();
    }
  }

  clearQueue() {
    this.queue = [];
    this.currentIndex = -1;

    if (!this.currentSong) {
      this.currentSong = null;
      this.audio.src = "";
      this.pause();
      this.updateNowPlaying();
    }

    this.updateQueueUI();
    this.showNotification("Queue cleared");
    this.saveState();
    this.hideQueue();
  }

  updateNowPlaying() {
    if (!this.currentSong) {
      document.getElementById("nowPlayingTitle").textContent = "Tidak ada lagu";
      document.getElementById("nowPlayingArtist").textContent =
        "Pilih lagu untuk diputar";
      document.getElementById("nowPlayingCover").src =
        "assets/images/covers/default-cover.png";
      return;
    }

    document.getElementById("nowPlayingTitle").textContent =
      this.currentSong.title;
    document.getElementById("nowPlayingArtist").textContent =
      this.currentSong.artist_name || "Unknown Artist";

    const coverImg = document.getElementById("nowPlayingCover");
    coverImg.src = this.getCoverPath(this.currentSong.cover_image);
  }

  async updateLikeButton(songId) {
    const likeBtn = document.getElementById("nowPlayingLike");
    if (!likeBtn) return;

    try {
      const response = await fetch(`api/likes.php?song_id=${songId}`);
      const data = await response.json();

      if (data.success) {
        if (data.is_liked) {
          likeBtn.innerHTML = '<i class="fas fa-heart"></i>';
          likeBtn.classList.add("liked");
          likeBtn.title = "Unlike";
        } else {
          likeBtn.innerHTML = '<i class="far fa-heart"></i>';
          likeBtn.classList.remove("liked");
          likeBtn.title = "Like";
        }
      }
    } catch (error) {
      console.error("Error checking like status:", error);
    }
  }

  toggleCurrentLike() {
    if (!this.currentSong) return;
    this.toggleLike(this.currentSong.id);
  }

  async toggleLike(songId) {
    const likeBtn = document.getElementById("nowPlayingLike");
    if (!likeBtn) return;

    const isLiked = likeBtn.classList.contains("liked");

    try {
      const response = await fetch("api/likes.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          song_id: songId,
          action: isLiked ? "unlike" : "like",
        }),
      });

      const data = await response.json();

      if (data.success) {
        if (isLiked) {
          likeBtn.innerHTML = '<i class="far fa-heart"></i>';
          likeBtn.classList.remove("liked");
          likeBtn.title = "Like";
          this.showNotification("Removed from liked songs");
        } else {
          likeBtn.innerHTML = '<i class="fas fa-heart"></i>';
          likeBtn.classList.add("liked");
          likeBtn.title = "Unlike";
          this.showNotification("Added to liked songs");
        }

        this.updateLikeButtonInPage(songId, !isLiked);
      }
    } catch (error) {
      console.error("Error toggling like:", error);
    }
  }

  updateLikeButtonInPage(songId, isLiked) {
    document
      .querySelectorAll(`.like-song[data-song-id="${songId}"]`)
      .forEach((button) => {
        if (isLiked) {
          button.classList.add("liked");
          button.innerHTML = '<i class="fas fa-heart"></i> Unlike';
        } else {
          button.classList.remove("liked");
          button.innerHTML = '<i class="far fa-heart"></i> Like';
        }
      });
  }

  getCoverPath(coverImage) {
    if (!coverImage || coverImage === "default-cover.png") {
      return "assets/images/covers/default-cover.png";
    }

    if (coverImage.includes("uploads/")) {
      return coverImage;
    }

    return `uploads/covers/${coverImage}`;
  }

  formatTime(seconds) {
    if (isNaN(seconds) || !isFinite(seconds)) return "0:00";

    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, "0")}`;
  }

  showNotification(message, type = "success") {
    const existingNotifs = document.querySelectorAll(".notification");
    existingNotifs.forEach((notif) => notif.remove());

    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
      <div class="notification-content">
        <i class="fas fa-${
          type === "success" ? "check-circle" : "exclamation-circle"
        }"></i>
        <span>${message}</span>
      </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => notification.classList.add("show"), 10);

    setTimeout(() => {
      notification.classList.remove("show");
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }

  async incrementPlayCount(songId) {
    try {
      await fetch("api/songs.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "increment_play_count",
          song_id: songId,
        }),
      });
    } catch (error) {
      console.error("Error incrementing play count:", error);
    }
  }

  async addToRecentlyPlayed(songId) {
    try {
      await fetch("api/player.php?action=add_recently_played", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `song_id=${songId}`,
      });
    } catch (error) {
      console.error("Error adding to recently played:", error);
    }
  }

  saveState() {
    const state = {
      queue: this.queue,
      currentIndex: this.currentIndex,
      currentSong: this.currentSong,
      volume: this.volume,
      isShuffle: this.isShuffle,
      repeatMode: this.repeatMode,
    };

    try {
      localStorage.setItem("musicPlayerState", JSON.stringify(state));
    } catch (error) {
      console.error("Error saving player state:", error);
    }
  }

  restoreState() {
    try {
      const saved = localStorage.getItem("musicPlayerState");
      if (saved) {
        const state = JSON.parse(saved);

        this.queue = state.queue || [];
        this.currentIndex = state.currentIndex || -1;
        this.currentSong = state.currentSong || null;
        this.volume = state.volume || 0.7;
        this.isShuffle = state.isShuffle || false;
        this.repeatMode = state.repeatMode || "none";

        this.updateQueueUI();
        this.updateNowPlaying();
        this.updateVolumeUI();

        const shuffleBtn = document.getElementById("shuffleBtn");
        if (shuffleBtn) {
          shuffleBtn.classList.toggle("active", this.isShuffle);
        }

        const repeatBtn = document.getElementById("repeatBtn");
        if (repeatBtn) {
          repeatBtn.className = "control-btn";
          repeatBtn.classList.add(`repeat-${this.repeatMode}`);
        }

        if (this.currentSong) {
          this.updateLikeButton(this.currentSong.id);
        }
      }
    } catch (error) {
      console.error("Error restoring player state:", error);
    }
  }

  onMetadataLoaded() {
    const totalTime = document.getElementById("totalTime");
    if (totalTime && this.audio.duration && !isNaN(this.audio.duration)) {
      totalTime.textContent = this.formatTime(this.audio.duration);
    }
  }

  onAudioError(e) {
    console.error("Audio error:", e);
    this.showNotification("Error loading audio file", "error");
  }
}

// Initialize player
document.addEventListener("DOMContentLoaded", () => {
  console.log("📄 DOM loaded, initializing MusicPlayer...");

  if (!document.getElementById("audioElement")) {
    console.error("❌ Audio element not found!");
    return;
  }

  try {
    new MusicPlayer();
    console.log("✅ MusicPlayer created successfully");
  } catch (error) {
    console.error("❌ Failed to create MusicPlayer:", error);
  }
});
