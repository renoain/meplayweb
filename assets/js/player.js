// assets/js/player.js

class MusicPlayer {
  constructor() {
    if (MusicPlayer.instance) {
      return MusicPlayer.instance;
    }
    MusicPlayer.instance = this;

    this.audio = document.getElementById("audioElement");
    this.isPlaying = false;
    this.currentSong = null;
    this.queue = [];
    this.currentIndex = -1;
    this.volume = 0.7;
    this.isShuffled = false;
    this.repeatMode = "none";
    this.isSeeking = false;
    this.isVolumeDragging = false;

    if (this.audio) {
      this.init();
    }

    return this;
  }

  init() {
    this.setupEventListeners();
    this.loadPlayerState();
    this.updateDisplay();
    this.setVolume(this.volume);
    this.restorePlaybackState();
  }

  setupEventListeners() {
    if (!this.audio) return;

    // Play/Pause
    const playPauseBtn = document.getElementById("playPauseBtn");
    if (playPauseBtn) {
      playPauseBtn.addEventListener("click", () => this.togglePlay());
    }

    // Previous/Next
    const prevBtn = document.getElementById("prevBtn");
    const nextBtn = document.getElementById("nextBtn");
    if (prevBtn) prevBtn.addEventListener("click", () => this.previous());
    if (nextBtn) nextBtn.addEventListener("click", () => this.next());

    // Progress bar
    const progressBar = document.getElementById("progressBar");
    if (progressBar) {
      progressBar.addEventListener("click", (e) => this.seek(e));
      progressBar.addEventListener("mousedown", (e) => this.startSeeking(e));
    }

    // Volume control
    const volumeBar = document.getElementById("volumeBar");
    if (volumeBar) {
      volumeBar.addEventListener("click", (e) => this.setVolumeFromClick(e));
      volumeBar.addEventListener("mousedown", (e) => this.startVolumeDrag(e));
    }

    // Global mouse events for dragging
    document.addEventListener("mousemove", (e) => {
      if (this.isSeeking) {
        this.dragSeeking(e);
      }
      if (this.isVolumeDragging) {
        this.dragVolume(e);
      }
    });

    document.addEventListener("mouseup", () => {
      this.stopSeeking();
      this.stopVolumeDrag();
    });

    // Volume button
    const volumeBtn = document.getElementById("volumeBtn");
    if (volumeBtn) {
      volumeBtn.addEventListener("click", () => this.toggleMute());
    }

    // Repeat button
    const repeatBtn = document.getElementById("repeatBtn");
    if (repeatBtn) {
      repeatBtn.addEventListener("click", () => this.toggleRepeat());
    }

    // Shuffle button
    const shuffleBtn = document.getElementById("shuffleBtn");
    if (shuffleBtn) {
      shuffleBtn.addEventListener("click", () => this.toggleShuffle());
    }

    // Queue button
    const queueBtn = document.getElementById("queueBtn");
    if (queueBtn) {
      queueBtn.addEventListener("click", () => this.showQueue());
    }

    // Like button
    const likeBtn = document.getElementById("nowPlayingLike");
    if (likeBtn) {
      likeBtn.addEventListener("click", () => this.toggleLike());
    }

    // Audio events
    this.audio.addEventListener("loadedmetadata", () =>
      this.onLoadedMetadata()
    );
    this.audio.addEventListener("timeupdate", () => this.onTimeUpdate());
    this.audio.addEventListener("ended", () => this.onEnded());
    this.audio.addEventListener("volumechange", () => this.onVolumeChange());
    this.audio.addEventListener("error", (e) => this.onError(e));
    this.audio.addEventListener("canplay", () => this.onCanPlay());

    // Queue modal
    const clearQueueBtn = document.getElementById("clearQueue");
    if (clearQueueBtn) {
      clearQueueBtn.addEventListener("click", () => this.clearQueue());
    }

    // Close queue modal
    const closeModalBtn = document.querySelector("#queueModal .close-modal");
    if (closeModalBtn) {
      closeModalBtn.addEventListener("click", () => {
        document.getElementById("queueModal")?.classList.remove("show");
      });
    }

    window.addEventListener("click", (e) => {
      const modal = document.getElementById("queueModal");
      if (e.target === modal) {
        modal.classList.remove("show");
      }
    });

    // Save state regularly and before page unload
    setInterval(() => {
      this.savePlayerState();
    }, 1000);

    window.addEventListener("beforeunload", () => {
      this.savePlayerState();
    });

    // Listen for like updates from LikesManager
    document.addEventListener("likeUpdated", (e) => {
      this.handleLikeUpdate(e.detail);
    });
  }

  handleLikeUpdate(detail) {
    const { songId, isLiked } = detail;

    // Update player like button if this is the current song
    if (this.currentSong && this.currentSong.id == songId) {
      this.updatePlayerLikeButton(isLiked);
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

  async toggleLike() {
    if (!this.currentSong) return;

    const likeButton = document.getElementById("nowPlayingLike");
    const isLiked = likeButton.classList.contains("liked");

    try {
      const response = await fetch("api/likes.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          song_id: this.currentSong.id,
          action: isLiked ? "unlike" : "like",
        }),
      });

      const data = await response.json();

      if (data.success) {
        // Update player button
        if (isLiked) {
          likeButton.innerHTML = '<i class="far fa-heart"></i>';
          likeButton.classList.remove("liked");
        } else {
          likeButton.innerHTML = '<i class="fas fa-heart"></i>';
          likeButton.classList.add("liked");
        }

        // Update all other like buttons for this song
        if (window.likesManager) {
          window.likesManager.updateLikeButtons(this.currentSong.id, !isLiked);
        }

        showNotification(
          isLiked ? "Removed from liked songs" : "Added to liked songs"
        );
      } else {
        showNotification(data.message || "Error updating like", "error");
      }
    } catch (error) {
      console.error("Like error:", error);
      showNotification("Error updating like", "error");
    }
  }

  async playSong(song) {
    if (!song || !this.audio) return;

    try {
      this.currentSong = song;
      const audioPath = song.audio_file.startsWith("uploads/audio/")
        ? song.audio_file
        : `uploads/audio/${song.audio_file}`;

      if (this.audio.src !== audioPath) {
        this.audio.src = audioPath;
        await new Promise((resolve) => {
          this.audio.addEventListener("loadedmetadata", resolve, {
            once: true,
          });
        });
      }

      this.updateNowPlaying();
      this.savePlayerState();

      await this.audio.play();
      this.isPlaying = true;
      this.updatePlayButton();
      this.incrementPlayCount(song.id);
    } catch (error) {
      console.error("Play failed:", error);
      showNotification("Error playing song", "error");

      if (this.queue.length > 1) {
        setTimeout(() => this.next(), 2000);
      }
    }
  }

  updateNowPlaying() {
    if (!this.currentSong) {
      const titleEl = document.getElementById("nowPlayingTitle");
      const artistEl = document.getElementById("nowPlayingArtist");
      const coverEl = document.getElementById("nowPlayingCover");

      if (titleEl) titleEl.textContent = "Tidak ada lagu";
      if (artistEl) artistEl.textContent = "Pilih lagu untuk diputar";
      if (coverEl) coverEl.src = "assets/images/covers/default-cover.png";
      return;
    }

    const titleEl = document.getElementById("nowPlayingTitle");
    const artistEl = document.getElementById("nowPlayingArtist");
    const coverEl = document.getElementById("nowPlayingCover");

    if (titleEl) titleEl.textContent = this.currentSong.title;
    if (artistEl) artistEl.textContent = this.currentSong.artist_name;

    if (coverEl) {
      const cover = this.currentSong.cover_image
        ? this.currentSong.cover_image.startsWith("uploads/covers/")
          ? this.currentSong.cover_image
          : "uploads/covers/" + this.currentSong.cover_image
        : "assets/images/covers/default-cover.png";
      coverEl.src = cover;
    }

    this.checkLikeStatus();
  }

  async checkLikeStatus() {
    if (!this.currentSong) return;

    try {
      const response = await fetch(
        `api/likes.php?song_id=${this.currentSong.id}`
      );
      const data = await response.json();

      if (data.success) {
        this.updatePlayerLikeButton(data.is_liked);
      }
    } catch (error) {
      console.error("Error checking like status:", error);
    }
  }

  // ... (rest of the existing player methods remain the same)
  togglePlay() {
    if (!this.currentSong || !this.audio) {
      if (this.queue.length > 0) {
        this.currentIndex = 0;
        this.playSong(this.queue[0]);
      }
      return;
    }

    if (this.isPlaying) {
      this.audio.pause();
    } else {
      this.audio.play().catch((error) => {
        console.error("Play error:", error);
        showNotification("Error playing song", "error");
      });
    }
    this.isPlaying = !this.isPlaying;
    this.updatePlayButton();
    this.savePlayerState();
  }

  previous() {
    if (this.queue.length === 0) return;

    if (this.audio.currentTime > 3) {
      this.audio.currentTime = 0;
      return;
    }

    this.currentIndex =
      this.currentIndex > 0 ? this.currentIndex - 1 : this.queue.length - 1;
    this.playSong(this.queue[this.currentIndex]);
  }

  next() {
    if (this.queue.length === 0) return;

    if (this.repeatMode === "one") {
      this.audio.currentTime = 0;
      this.audio.play();
      return;
    }

    let nextIndex;

    if (this.repeatMode === "all") {
      nextIndex = (this.currentIndex + 1) % this.queue.length;
    } else {
      nextIndex = this.currentIndex + 1;
    }

    if (this.isShuffled && this.queue.length > 1) {
      let newIndex;
      do {
        newIndex = Math.floor(Math.random() * this.queue.length);
      } while (newIndex === this.currentIndex && this.queue.length > 1);
      nextIndex = newIndex;
    }

    if (nextIndex >= this.queue.length) {
      if (this.repeatMode === "all") {
        nextIndex = 0;
      } else {
        this.stop();
        return;
      }
    }

    this.currentIndex = nextIndex;
    this.playSong(this.queue[this.currentIndex]);
  }

  addToQueue(song) {
    const existingIndex = this.queue.findIndex((s) => s.id === song.id);
    if (existingIndex !== -1) {
      this.queue.splice(existingIndex, 1);
      if (this.currentIndex > existingIndex) {
        this.currentIndex--;
      }
    }

    this.queue.push(song);

    if (this.queue.length === 1 && !this.currentSong) {
      this.currentIndex = 0;
      this.playSong(song);
    }

    this.updateQueueDisplay();
    this.savePlayerState();
  }

  removeFromQueue(index) {
    if (index < 0 || index >= this.queue.length) return;

    this.queue.splice(index, 1);
    if (index <= this.currentIndex) {
      this.currentIndex--;
    }

    if (index === this.currentIndex) {
      if (this.queue.length > 0) {
        this.currentIndex = Math.min(this.currentIndex, this.queue.length - 1);
        this.playSong(this.queue[this.currentIndex]);
      } else {
        this.currentSong = null;
        this.currentIndex = -1;
        if (this.audio) {
          this.audio.src = "";
        }
        this.updateNowPlaying();
        this.updatePlayButton();
      }
    }

    this.updateQueueDisplay();
    this.savePlayerState();
  }

  clearQueue() {
    const wasPlaying = this.isPlaying;
    this.queue = [];

    if (this.currentIndex !== -1) {
      this.currentSong = null;
      this.currentIndex = -1;
      if (this.audio) {
        this.audio.src = "";
      }
      this.isPlaying = false;
      this.updateNowPlaying();
      this.updatePlayButton();
    }

    this.updateQueueDisplay();
    this.savePlayerState();

    if (wasPlaying && this.audio) {
      this.audio.pause();
    }
  }

  showQueue() {
    const modal = document.getElementById("queueModal");
    if (modal) {
      this.updateQueueDisplay();
      modal.classList.add("show");
    }
  }

  updateQueueDisplay() {
    const queueList = document.getElementById("queueList");
    if (!queueList) return;

    if (this.queue.length === 0) {
      queueList.innerHTML = '<div class="no-queue">No songs in queue</div>';
      return;
    }

    queueList.innerHTML = this.queue
      .map((song, index) => {
        const isCurrent = index === this.currentIndex;
        return `
                    <div class="queue-item ${
                      isCurrent ? "active" : ""
                    }" data-index="${index}">
                        <div class="queue-item-info">
                            <h4>${song.title}</h4>
                            <p>${song.artist_name}</p>
                        </div>
                        <div class="queue-item-duration">${this.formatTime(
                          song.duration
                        )}</div>
                        <button class="queue-remove" data-index="${index}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
      })
      .join("");

    document.querySelectorAll(".queue-remove").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.stopPropagation();
        const index = parseInt(button.getAttribute("data-index"));
        this.removeFromQueue(index);
      });
    });

    document.querySelectorAll(".queue-item").forEach((item) => {
      item.addEventListener("click", (e) => {
        if (!e.target.closest(".queue-remove")) {
          const index = parseInt(item.getAttribute("data-index"));
          this.currentIndex = index;
          this.playSong(this.queue[index]);
        }
      });
    });
  }

  updatePlayButton() {
    const button = document.getElementById("playPauseBtn");
    if (!button) return;

    const icon = button.querySelector("i");
    if (icon) {
      icon.className = this.isPlaying ? "fas fa-pause" : "fas fa-play";
    }
  }

  onLoadedMetadata() {
    const totalTime = document.getElementById("totalTime");
    if (totalTime) {
      totalTime.textContent = this.formatTime(this.audio.duration);
    }
  }

  onTimeUpdate() {
    if (this.isSeeking || !this.audio) return;

    const progress = (this.audio.currentTime / this.audio.duration) * 100 || 0;
    const progressElement = document.getElementById("progress");
    const progressHandle = document.getElementById("progressHandle");

    if (progressElement) {
      progressElement.style.width = progress + "%";
    }

    if (progressHandle) {
      progressHandle.style.left = progress + "%";
    }

    const currentTime = document.getElementById("currentTime");
    if (currentTime) {
      currentTime.textContent = this.formatTime(this.audio.currentTime);
    }
  }

  stop() {
    this.isPlaying = false;
    this.audio.pause();
    this.audio.currentTime = 0;
    this.updatePlayButton();
    this.savePlayerState();
  }

  onEnded() {
    if (this.repeatMode === "one") {
      this.audio.currentTime = 0;
      this.audio.play();
    } else {
      this.next();
    }
  }

  onVolumeChange() {
    if (!this.audio) return;

    const volume = this.audio.muted ? 0 : this.audio.volume;
    const volumeProgress = document.getElementById("volumeProgress");
    const volumeHandle = document.getElementById("volumeHandle");

    if (volumeProgress) {
      volumeProgress.style.width = volume * 100 + "%";
    }

    if (volumeHandle) {
      volumeHandle.style.left = volume * 100 + "%";
    }

    const volumeIcon = document.getElementById("volumeBtn")?.querySelector("i");
    if (volumeIcon) {
      if (volume === 0) {
        volumeIcon.className = "fas fa-volume-mute";
      } else if (volume < 0.5) {
        volumeIcon.className = "fas fa-volume-down";
      } else {
        volumeIcon.className = "fas fa-volume-up";
      }
    }
  }

  onError(e) {
    console.error("Audio error:", e);
    showNotification("Error playing audio file", "error");

    if (this.queue.length > 0) {
      setTimeout(() => this.next(), 1000);
    }
  }

  seek(e) {
    const progressBar = document.getElementById("progressBar");
    if (!progressBar || !this.audio) return;

    const rect = progressBar.getBoundingClientRect();
    const percent = Math.max(
      0,
      Math.min(1, (e.clientX - rect.left) / rect.width)
    );
    this.audio.currentTime = percent * this.audio.duration;
  }

  setVolume(volume) {
    this.volume = Math.max(0, Math.min(1, volume));
    if (this.audio) {
      this.audio.volume = this.volume;
      this.audio.muted = false;
    }
    this.onVolumeChange();
    this.savePlayerState();
  }

  setVolumeFromClick(e) {
    const volumeBar = document.getElementById("volumeBar");
    if (!volumeBar) return;

    const rect = volumeBar.getBoundingClientRect();
    const percent = Math.max(
      0,
      Math.min(1, (e.clientX - rect.left) / rect.width)
    );
    this.setVolume(percent);
  }

  toggleMute() {
    if (this.audio) {
      this.audio.muted = !this.audio.muted;
      this.onVolumeChange();
    }
  }

  toggleRepeat() {
    const button = document.getElementById("repeatBtn");
    const modes = ["none", "one", "all"];
    const currentIndex = modes.indexOf(this.repeatMode);
    this.repeatMode = modes[(currentIndex + 1) % modes.length];

    if (button) {
      button.className = `control-btn repeat-${this.repeatMode}`;
    }
    this.savePlayerState();
  }

  toggleShuffle() {
    this.isShuffled = !this.isShuffled;
    const button = document.getElementById("shuffleBtn");
    if (button) {
      button.classList.toggle("active", this.isShuffled);
    }
    this.savePlayerState();
  }

  formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return "0:00";

    seconds = Math.floor(seconds);
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;

    return `${minutes}:${remainingSeconds.toString().padStart(2, "0")}`;
  }

  incrementPlayCount(songId) {
    fetch("api/songs.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        action: "increment_play_count",
        song_id: songId,
      }),
    }).catch((error) => console.error("Play count error:", error));
  }

  updateDisplay() {
    const currentTime = document.getElementById("currentTime");
    const totalTime = document.getElementById("totalTime");

    if (currentTime) currentTime.textContent = "0:00";
    if (totalTime) totalTime.textContent = "0:00";

    const progress = document.getElementById("progress");
    const progressHandle = document.getElementById("progressHandle");
    if (progress) progress.style.width = "0%";
    if (progressHandle) progressHandle.style.left = "0%";

    const volumeProgress = document.getElementById("volumeProgress");
    const volumeHandle = document.getElementById("volumeHandle");
    if (volumeProgress) volumeProgress.style.width = this.volume * 100 + "%";
    if (volumeHandle) volumeHandle.style.left = this.volume * 100 + "%";
  }

  savePlayerState() {
    const state = {
      currentSong: this.currentSong,
      queue: this.queue,
      currentIndex: this.currentIndex,
      volume: this.volume,
      isShuffled: this.isShuffled,
      repeatMode: this.repeatMode,
      currentTime: this.audio ? this.audio.currentTime : 0,
      isPlaying: this.isPlaying,
      audioSrc: this.audio ? this.audio.src : "",
    };
    localStorage.setItem("musicPlayerState", JSON.stringify(state));
  }

  loadPlayerState() {
    try {
      const saved = localStorage.getItem("musicPlayerState");
      if (saved) {
        const state = JSON.parse(saved);
        this.currentSong = state.currentSong;
        this.queue = state.queue || [];
        this.currentIndex = state.currentIndex || -1;
        this.setVolume(state.volume || 0.7);
        this.isShuffled = state.isShuffled || false;
        this.repeatMode = state.repeatMode || "none";

        if (this.currentSong && this.audio) {
          this.updateNowPlaying();

          if (
            state.audioSrc &&
            this.audio.src === state.audioSrc &&
            state.currentTime
          ) {
            this.audio.currentTime = state.currentTime;
          }

          this.isPlaying = state.isPlaying || false;
          this.updatePlayButton();
        }
      }
    } catch (error) {
      console.error("Error loading player state:", error);
    }
  }

  restorePlaybackState() {
    const savedState = localStorage.getItem("musicPlayerState");
    if (savedState) {
      const state = JSON.parse(savedState);
      if (state.currentSong) {
        if (state.currentTime && this.audio.readyState > 0) {
          this.audio.currentTime = state.currentTime;
        }

        if (state.isPlaying && this.audio.src) {
          setTimeout(() => {
            this.audio.play().catch((error) => {
              console.log("Auto-play prevented:", error);
            });
          }, 500);
        }
      }
    }
  }

  onCanPlay() {
    const savedState = localStorage.getItem("musicPlayerState");
    if (savedState) {
      const state = JSON.parse(savedState);
      if (state.isPlaying && this.audio.src === state.audioSrc) {
        this.audio.play().catch((error) => {
          console.log("Auto-play on canplay prevented:", error);
        });
      }
    }
  }

  startSeeking(e) {
    this.isSeeking = true;
    this.seek(e);
  }

  dragSeeking(e) {
    if (this.isSeeking) {
      this.seek(e);
    }
  }

  stopSeeking() {
    this.isSeeking = false;
  }

  startVolumeDrag(e) {
    this.isVolumeDragging = true;
    this.setVolumeFromClick(e);
  }

  dragVolume(e) {
    if (this.isVolumeDragging) {
      this.setVolumeFromClick(e);
    }
  }

  stopVolumeDrag() {
    this.isVolumeDragging = false;
  }
}

// Global player instance
let musicPlayer = null;

// Initialize player when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  const audioElement = document.getElementById("audioElement");
  if (audioElement) {
    musicPlayer = new MusicPlayer();
  }
});
