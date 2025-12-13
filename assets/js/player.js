class MusicPlayer {
  constructor() {
    console.log(
      " Initializing MusicPlayer (Fixed Version with Persistent Volume)..."
    );

    // Singleton pattern
    if (window.musicPlayerInstance) {
      console.log(" Returning existing player instance");
      return window.musicPlayerInstance;
    }

    // Setup audio element
    this.audio = this.setupAudioElement();

    // Player state
    this.currentSong = null;
    this.queue = [];
    this.currentIndex = -1;
    this.isPlaying = false;
    this.isShuffle = false;
    this.repeatMode = "none";

    this.volume = 0.5; // Default 50%

    this.isDragging = false;
    this.isVolumeDragging = false;

    // Initialize
    this.init();

    // Store instance globally
    window.musicPlayerInstance = this;
    window.musicPlayer = this;

    console.log(" MusicPlayer initialized successfully with persistent volume");
    return this;
  }

  setupAudioElement() {
    let audio = document.getElementById("globalAudioElement");

    if (!audio) {
      audio = document.createElement("audio");
      audio.id = "globalAudioElement";
      audio.preload = "auto";
      audio.crossOrigin = "anonymous";
      audio.style.cssText = `
        position: fixed;
        opacity: 0;
        pointer-events: none;
        width: 1px;
        height: 1px;
      `;
      document.body.appendChild(audio);
      console.log("🔊 Created global audio element");
    }

    return audio;
  }

  init() {
    // Restore saved state FIRST (termasuk volume)
    this.restoreState();

    // Setup event listeners
    this.setupAudioEvents();

    // Bind UI controls
    this.bindControls();

    // Setup visibility handling
    this.setupVisibilityHandling();

    // Start auto-save
    this.startAutoSave();

    // Apply initial volume to UI
    this.updateVolumeUI();
  }

  setupAudioEvents() {
    // Clone audio element to remove old listeners
    const newAudio = this.audio.cloneNode();
    if (this.audio.parentNode) {
      this.audio.parentNode.replaceChild(newAudio, this.audio);
      this.audio = newAudio;
    }

    // Apply saved volume to audio element
    this.audio.volume = this.volume;

    // Audio event listeners
    this.audio.addEventListener("loadedmetadata", () =>
      this.onMetadataLoaded()
    );
    this.audio.addEventListener("timeupdate", () => this.updateProgress());
    this.audio.addEventListener("ended", () => this.onSongEnd());
    this.audio.addEventListener("error", (e) => this.onAudioError(e));

    this.audio.addEventListener("play", () => {
      this.isPlaying = true;
      this.updatePlayButton(true);
      this.saveState();
    });

    this.audio.addEventListener("pause", () => {
      this.isPlaying = false;
      this.updatePlayButton(false);
      this.saveState();
    });

    // Volume change listener
    this.audio.addEventListener("volumechange", () => {
      this.volume = this.audio.volume;
      this.saveState();
    });
  }

  bindControls() {
    // Player controls
    this.bindElement("playPauseBtn", "click", () => this.togglePlay());
    this.bindElement("prevBtn", "click", () => this.prev());
    this.bindElement("nextBtn", "click", () => this.next());
    this.bindElement("shuffleBtn", "click", () => this.toggleShuffle());
    this.bindElement("repeatBtn", "click", () => this.toggleRepeat());
    this.bindElement("queueBtn", "click", () => this.showQueue());
    this.bindElement("nowPlayingLike", "click", () => this.toggleCurrentLike());
    this.bindElement("clearQueue", "click", () => this.clearQueue());

    // Progress bar
    const progressBar = document.getElementById("progressBar");
    if (progressBar) {
      progressBar.addEventListener("click", (e) => this.seek(e));
      progressBar.addEventListener("mousedown", (e) => this.startDrag(e));
    }

    // Volume control - FIXED untuk memastikan volume tersimpan
    const volumeBar = document.getElementById("volumeBar");
    if (volumeBar) {
      volumeBar.addEventListener("click", (e) => this.setVolume(e));
      volumeBar.addEventListener("mousedown", (e) => this.startVolumeDrag(e));
    }

    // Volume button untuk mute/unmute
    const volumeBtn = document.getElementById("volumeBtn");
    if (volumeBtn) {
      volumeBtn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.toggleMute();
      });
    }

    // Global mouse events for dragging
    document.addEventListener("mousemove", (e) => {
      if (this.isDragging) this.dragProgress(e);
      if (this.isVolumeDragging) this.dragVolume(e);
    });

    document.addEventListener("mouseup", () => {
      if (this.isDragging || this.isVolumeDragging) {
        this.saveState(); // Save saat selesai drag
      }
      this.isDragging = false;
      this.isVolumeDragging = false;
    });

    // Close modal with escape key
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        this.closeQueueModal();
      }
    });
  }

  bindElement(id, event, handler) {
    const element = document.getElementById(id);
    if (element) {
      element.removeEventListener(event, handler); // Remove old listener
      element.addEventListener(event, handler);
    }
  }

  setupVisibilityHandling() {
    document.addEventListener("visibilitychange", () => {
      if (!document.hidden && this.isPlaying && this.audio.paused) {
        setTimeout(() => {
          if (this.currentSong && this.audio.src) {
            this.audio.play().catch((e) => {
              console.log("Auto-resume blocked:", e.name);
            });
          }
        }, 300);
      }
    });
  }

  startAutoSave() {
    setInterval(() => {
      if (this.currentSong) {
        this.saveState();
      }
    }, 3000);
  }

  //  VOLUME MANAGEMENT
  setVolume(e) {
    e.preventDefault();
    e.stopPropagation();

    const bar = document.getElementById("volumeBar");
    if (!bar) return;

    const rect = bar.getBoundingClientRect();
    let percent = (e.clientX - rect.left) / rect.width;
    percent = Math.max(0, Math.min(1, percent));

    this.volume = percent;
    this.audio.volume = this.volume;
    this.updateVolumeUI();
    this.saveState(); // Save immediately on volume change
  }

  startVolumeDrag(e) {
    e.preventDefault();
    e.stopPropagation();

    this.isVolumeDragging = true;
    this.dragVolume(e);
  }

  dragVolume(e) {
    if (!this.isVolumeDragging) return;

    const bar = document.getElementById("volumeBar");
    if (!bar) return;

    const rect = bar.getBoundingClientRect();
    let percent = (e.clientX - rect.left) / rect.width;
    percent = Math.max(0, Math.min(1, percent));

    this.volume = percent;
    this.audio.volume = this.volume;
    this.updateVolumeUI();
  }

  toggleMute() {
    if (this.audio.volume > 0) {
      // Store current volume before muting
      if (this.audio.volume > 0) {
        localStorage.setItem("lastVolume", this.audio.volume);
      }
      this.audio.volume = 0;
      this.volume = 0;
    } else {
      // Restore to last volume or default
      const lastVolume = parseFloat(localStorage.getItem("lastVolume")) || 0.5;
      this.audio.volume = lastVolume;
      this.volume = lastVolume;
    }
    this.updateVolumeUI();
    this.saveState();
  }

  updateVolumeUI() {
    const progress = document.getElementById("volumeProgress");
    const handle = document.getElementById("volumeHandle");
    const btn = document.getElementById("volumeBtn");

    if (progress) progress.style.width = `${this.volume * 100}%`;
    if (handle) handle.style.left = `${this.volume * 100}%`;

    if (btn) {
      let icon = "fa-volume-up";
      if (this.volume === 0) icon = "fa-volume-mute";
      else if (this.volume < 0.3) icon = "fa-volume-down";
      else if (this.volume < 0.6) icon = "fa-volume-down";
      btn.innerHTML = `<i class="fas ${icon}"></i>`;

      // Update tooltip
      const volumePercent = Math.round(this.volume * 100);
      btn.title = `Volume: ${volumePercent}%${
        this.volume === 0 ? " (Muted)" : ""
      }`;
    }
  }

  //  PLAYBACK CONTROL
  async playSong(song, index = -1, clearQueue = false) {
    if (!song || !song.audio_file) {
      console.error("Invalid song:", song);
      return false;
    }

    console.log("🎶 Playing:", song.title, "Volume:", this.volume);

    // If same song, toggle play/pause
    if (this.currentSong?.id === song.id && this.audio.src) {
      this.togglePlay();
      return true;
    }

    // Update state
    this.currentSong = song;
    if (index >= 0) this.currentIndex = index;

    // Clear queue if requested
    if (clearQueue) {
      this.queue = [];
      this.currentIndex = 0;
    }

    // Update UI
    this.updateNowPlaying();
    await this.updateLikeButton(song.id);

    // Set audio source
    const audioPath = `uploads/audio/${song.audio_file}`;
    this.audio.src = audioPath;

    // Apply current volume before playing
    this.audio.volume = this.volume;

    this.audio.load();

    // Play the song
    try {
      await this.audio.play();
      this.incrementPlayCount(song.id);
      this.saveState();
      this.showNotification(`Playing "${song.title}"`);
      return true;
    } catch (error) {
      console.error("Play failed:", error);
      this.showNotification("Failed to play song", "error");
      return false;
    }
  }

  async play() {
    if (!this.audio.src) {
      this.showNotification("No song selected", "error");
      return false;
    }

    try {
      await this.audio.play();
      this.isPlaying = true;
      this.updatePlayButton(true);
      this.saveState();
      return true;
    } catch (error) {
      console.error("Play error:", error);
      this.isPlaying = false;
      this.updatePlayButton(false);
      this.showNotification("Failed to play", "error");
      return false;
    }
  }

  pause() {
    this.audio.pause();
    this.isPlaying = false;
    this.updatePlayButton(false);
    this.saveState();
  }

  togglePlay() {
    if (this.isPlaying) {
      this.pause();
    } else {
      this.play();
    }
  }

  next() {
    if (this.queue.length === 0) {
      this.showNotification("Queue is empty", "info");
      return;
    }

    if (this.isShuffle) {
      this.playRandom();
    } else {
      this.currentIndex = (this.currentIndex + 1) % this.queue.length;
      this.playSong(this.queue[this.currentIndex], this.currentIndex);
    }
  }

  prev() {
    if (this.queue.length === 0) {
      this.showNotification("Queue is empty", "info");
      return;
    }

    if (this.audio.currentTime > 3) {
      this.audio.currentTime = 0;
      this.updateProgress();
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
      if (this.isShuffle) {
        btn.classList.add("active");
        btn.style.color = "var(--accent-color)";
      } else {
        btn.classList.remove("active");
        btn.style.color = "var(--text-secondary)";
      }
    }
    this.saveState();
    this.showNotification(
      this.isShuffle ? "Shuffle enabled" : "Shuffle disabled"
    );
  }

  toggleRepeat() {
    const modes = ["none", "one", "all"];
    const currentIndex = modes.indexOf(this.repeatMode);
    this.repeatMode = modes[(currentIndex + 1) % modes.length];

    const btn = document.getElementById("repeatBtn");
    if (btn) {
      btn.className = "control-btn";
      btn.classList.add(`repeat-${this.repeatMode}`);
    }

    const messages = {
      none: "Repeat disabled",
      one: "Repeat one song",
      all: "Repeat all",
    };

    this.showNotification(messages[this.repeatMode]);
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
      this.audio.currentTime = 0;
      this.updateProgress();
      this.showNotification("Playback finished");
    }
  }

  //  UI UPDATES
  updateNowPlaying() {
    const titleEl = document.getElementById("nowPlayingTitle");
    const artistEl = document.getElementById("nowPlayingArtist");
    const coverEl = document.getElementById("nowPlayingCover");

    if (!this.currentSong) {
      if (titleEl) titleEl.textContent = "No song playing";
      if (artistEl) artistEl.textContent = "Select a song to play";
      if (coverEl) coverEl.src = "assets/images/covers/default-cover.png";
      return;
    }

    if (titleEl) titleEl.textContent = this.currentSong.title || "Unknown";
    if (artistEl)
      artistEl.textContent = this.currentSong.artist_name || "Unknown Artist";
    if (coverEl) {
      coverEl.src = this.getCoverPath(this.currentSong.cover_image);
      coverEl.onerror = () => {
        coverEl.src = "assets/images/covers/default-cover.png";
      };
    }
  }

  updatePlayButton(isPlaying) {
    const btn = document.getElementById("playPauseBtn");
    if (!btn) return;

    if (isPlaying) {
      btn.innerHTML = '<i class="fas fa-pause"></i>';
      btn.title = "Pause";
    } else {
      btn.innerHTML = '<i class="fas fa-play"></i>';
      btn.title = "Play";
    }
  }

  updateProgress() {
    if (this.isDragging) return;

    if (this.audio.duration && !isNaN(this.audio.duration)) {
      const percent = (this.audio.currentTime / this.audio.duration) * 100;
      const progress = document.getElementById("progress");
      const handle = document.getElementById("progressHandle");
      const currentTime = document.getElementById("currentTime");
      const totalTime = document.getElementById("totalTime");

      if (progress) progress.style.width = `${percent}%`;
      if (handle) handle.style.left = `${percent}%`;
      if (currentTime)
        currentTime.textContent = this.formatTime(this.audio.currentTime);
      if (totalTime)
        totalTime.textContent = this.formatTime(this.audio.duration);
    }
  }

  //  STATE MANAGEMENT
  saveState() {
    const state = {
      queue: this.queue,
      currentIndex: this.currentIndex,
      currentSong: this.currentSong,
      volume: this.volume,
      isShuffle: this.isShuffle,
      repeatMode: this.repeatMode,
      isPlaying: this.isPlaying,
      currentTime: this.audio.currentTime,
      audioSrc: this.audio.src,
      timestamp: Date.now(),
    };

    try {
      localStorage.setItem("musicPlayerState", JSON.stringify(state));
      localStorage.setItem("musicPlayerVolume", this.volume.toString());
    } catch (error) {
      console.error("Save state error:", error);
    }
  }

  restoreState() {
    try {
      const savedVolume = localStorage.getItem("musicPlayerVolume");
      if (savedVolume !== null) {
        this.volume = parseFloat(savedVolume);
        this.volume = Math.max(0, Math.min(1, this.volume));
        console.log("🔊 Restored volume from localStorage:", this.volume);
      }

      const saved = localStorage.getItem("musicPlayerState");
      if (saved) {
        const state = JSON.parse(saved);

        // Restore basic state
        this.queue = state.queue || [];
        this.currentIndex = state.currentIndex || -1;
        this.currentSong = state.currentSong || null;

        if (savedVolume === null && state.volume !== undefined) {
          this.volume = state.volume;
        }

        this.isShuffle = state.isShuffle || false;
        this.repeatMode = state.repeatMode || "none";
        this.isPlaying = state.isPlaying || false;

        // Update UI
        this.updateNowPlaying();
        this.updateVolumeUI();
        this.updatePlayButton(this.isPlaying);

        // Update control buttons
        const shuffleBtn = document.getElementById("shuffleBtn");
        if (shuffleBtn) {
          if (this.isShuffle) {
            shuffleBtn.classList.add("active");
            shuffleBtn.style.color = "var(--accent-color)";
          }
        }

        const repeatBtn = document.getElementById("repeatBtn");
        if (repeatBtn) {
          repeatBtn.className = "control-btn";
          repeatBtn.classList.add(`repeat-${this.repeatMode}`);
        }

        // Restore audio if still valid
        if (state.audioSrc && this.currentSong) {
          const timeDiff = Date.now() - (state.timestamp || 0);
          if (timeDiff < 10 * 60 * 1000) {
            // 10 minutes
            this.audio.src = state.audioSrc;
            this.audio.volume = this.volume; // Terapkan volume yang sudah di-restore
            this.audio.load();

            // Restore position after metadata loads
            this.audio.addEventListener(
              "loadedmetadata",
              () => {
                if (state.currentTime && this.audio.duration) {
                  const savedTime = Math.min(
                    state.currentTime,
                    this.audio.duration - 1
                  );
                  this.audio.currentTime = savedTime;
                  this.updateProgress();
                }
              },
              { once: true }
            );
          }
        }

        // Auto resume if was playing
        if (this.isPlaying && this.currentSong && this.audio.src) {
          setTimeout(() => {
            this.audio.play().catch((e) => {
              console.log("Auto-resume blocked:", e.name);
              this.isPlaying = false;
              this.updatePlayButton(false);
            });
          }, 500);
        }
      }
    } catch (error) {
      console.error("Restore state error:", error);
      // Jika error, set volume ke default yang lebih rendah
      this.volume = 0.5;
      this.updateVolumeUI();
    }
  }

  //  QUEUE MANAGEMENT
  showQueue() {
    const modal = document.getElementById("queueModal");
    if (!modal) return;

    modal.classList.add("show");
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
    this.updateQueueUI();

    // Add close event listener
    const closeModal = () => {
      modal.classList.remove("show");
      modal.style.display = "none";
      document.body.style.overflow = "";
      document.removeEventListener("click", closeModalOutside);
    };

    const closeModalOutside = (e) => {
      if (e.target === modal) {
        closeModal();
      }
    };

    // Close button
    const closeBtn = modal.querySelector(".close-modal");
    if (closeBtn) {
      closeBtn.onclick = closeModal;
    }

    // Close when clicking outside
    setTimeout(() => {
      document.addEventListener("click", closeModalOutside);
    }, 10);
  }

  closeQueueModal() {
    const modal = document.getElementById("queueModal");
    if (modal) {
      modal.classList.remove("show");
      modal.style.display = "none";
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
          <p>Queue is empty</p>
          <p class="text-muted">Add songs to queue from songs list</p>
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
            <img src="${this.getCoverPath(song.cover_image)}" alt="${
          song.title
        }" 
                 onerror="this.src='assets/images/covers/default-cover.png'">
            <div>
              <h4>${song.title || "Unknown Song"}</h4>
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
        this.closeQueueModal();
      });
    });

    queueList.querySelectorAll(".remove-queue-item").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const index = parseInt(btn.dataset.index);
        this.removeFromQueue(index);
      });
    });
  }

  addToQueue(song) {
    if (!song) return false;

    // Check if song already in queue
    const exists = this.queue.some((s) => s.id === song.id);
    if (!exists) {
      this.queue.push(song);
      this.updateQueueUI();
      this.saveState();
      return true;
    }
    return false;
  }

  clearQueue() {
    this.queue = [];
    this.currentIndex = -1;

    // If current song was from queue, stop it
    if (!this.audio.src.includes("uploads/audio/")) {
      this.currentSong = null;
      this.audio.src = "";
      this.updateNowPlaying();
      this.updatePlayButton(false);
    }

    this.updateQueueUI();
    this.saveState();
    this.showNotification("Queue cleared");
    this.closeQueueModal();
  }

  removeFromQueue(index) {
    if (index < 0 || index >= this.queue.length) return;

    const removedSong = this.queue[index];

    // Handle different cases
    if (index === this.currentIndex) {
      // Removing current playing song
      if (this.queue.length === 1) {
        // Only song in queue
        this.clearQueue();
      } else {
        // Remove and play next
        this.queue.splice(index, 1);
        if (this.currentIndex >= this.queue.length) {
          this.currentIndex = this.queue.length - 1;
        }
        if (this.isPlaying && this.queue.length > 0) {
          this.playSong(this.queue[this.currentIndex], this.currentIndex);
        }
      }
    } else if (index < this.currentIndex) {
      // Removing song before current
      this.queue.splice(index, 1);
      this.currentIndex--;
    } else {
      // Removing song after current
      this.queue.splice(index, 1);
    }

    this.updateQueueUI();
    this.saveState();
    this.showNotification(`Removed "${removedSong.title}" from queue`);
  }

  //  PROGRESS CONTROLS
  startDrag(e) {
    e.preventDefault();
    this.isDragging = true;
    this.dragProgress(e);
  }

  dragProgress(e) {
    if (!this.isDragging) return;

    const bar = document.getElementById("progressBar");
    if (!bar) return;

    const rect = bar.getBoundingClientRect();
    let percent = (e.clientX - rect.left) / rect.width;
    percent = Math.max(0, Math.min(1, percent));

    const progress = document.getElementById("progress");
    const handle = document.getElementById("progressHandle");

    if (progress) progress.style.width = `${percent * 100}%`;
    if (handle) handle.style.left = `${percent * 100}%`;

    if (this.audio.duration) {
      this.audio.currentTime = percent * this.audio.duration;
    }

    const currentTime = document.getElementById("currentTime");
    if (currentTime && this.audio.duration) {
      currentTime.textContent = this.formatTime(percent * this.audio.duration);
    }
  }

  seek(e) {
    const bar = document.getElementById("progressBar");
    if (!bar) return;

    const rect = bar.getBoundingClientRect();
    let percent = (e.clientX - rect.left) / rect.width;
    percent = Math.max(0, Math.min(1, percent));

    if (this.audio.duration) {
      this.audio.currentTime = percent * this.audio.duration;
    }
  }

  //  UTILITY METHODS
  getCoverPath(coverImage) {
    if (!coverImage || coverImage === "default-cover.png") {
      return "assets/images/covers/default-cover.png";
    }
    return coverImage.includes("uploads/")
      ? coverImage
      : `uploads/covers/${coverImage}`;
  }

  formatTime(seconds) {
    if (isNaN(seconds)) return "0:00";
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, "0")}`;
  }

  async updateLikeButton(songId) {
    const btn = document.getElementById("nowPlayingLike");
    if (!btn) return;

    try {
      const response = await fetch(`api/likes.php?song_id=${songId}`);
      const data = await response.json();

      if (data.success) {
        if (data.is_liked) {
          btn.innerHTML = '<i class="fas fa-heart"></i>';
          btn.classList.add("liked");
          btn.style.color = "var(--danger-color)";
        } else {
          btn.innerHTML = '<i class="far fa-heart"></i>';
          btn.classList.remove("liked");
          btn.style.color = "var(--text-secondary)";
        }
      }
    } catch (error) {
      console.error("Like status error:", error);
    }
  }

  async toggleCurrentLike() {
    if (!this.currentSong) {
      this.showNotification("No song playing", "error");
      return;
    }

    const btn = document.getElementById("nowPlayingLike");
    if (!btn) return;

    const isLiked = btn.classList.contains("liked");

    try {
      const response = await fetch("api/likes.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          song_id: this.currentSong.id,
          action: isLiked ? "unlike" : "like",
        }),
      });

      const data = await response.json();

      if (data.success) {
        if (isLiked) {
          btn.innerHTML = '<i class="far fa-heart"></i>';
          btn.classList.remove("liked");
          btn.style.color = "var(--text-secondary)";
          this.showNotification("Removed from liked songs");
        } else {
          btn.innerHTML = '<i class="fas fa-heart"></i>';
          btn.classList.add("liked");
          btn.style.color = "var(--danger-color)";
          this.showNotification("Added to liked songs");
        }
      } else {
        this.showNotification(data.message || "Error updating like", "error");
      }
    } catch (error) {
      console.error("Toggle like error:", error);
      this.showNotification("Error updating like", "error");
    }
  }

  async incrementPlayCount(songId) {
    try {
      await fetch("api/songs.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "increment_play_count",
          song_id: songId,
        }),
      });
    } catch (error) {
      console.error("Play count error:", error);
    }
  }

  //  API METHODS
  async playSongById(songId) {
    try {
      const response = await fetch(`api/songs.php?id=${songId}`);
      const data = await response.json();

      if (data.success) {
        await this.playSong(data.song);
      } else {
        this.showNotification("Failed to load song", "error");
      }
    } catch (error) {
      console.error("Play song error:", error);
      this.showNotification("Error playing song", "error");
    }
  }

  async addToQueueById(songId) {
    try {
      const response = await fetch(`api/songs.php?id=${songId}`);
      const data = await response.json();

      if (data.success) {
        if (this.addToQueue(data.song)) {
          this.showNotification("Added to queue");
        } else {
          this.showNotification("Already in queue", "info");
        }
      } else {
        this.showNotification("Failed to add to queue", "error");
      }
    } catch (error) {
      console.error("Add to queue error:", error);
      this.showNotification("Error adding to queue", "error");
    }
  }

  async playPlaylist(playlistId) {
    try {
      const response = await fetch(
        `api/playlists.php?action=get_playlist_songs&id=${playlistId}`
      );
      const data = await response.json();

      if (data.success && data.songs && data.songs.length > 0) {
        // Clear current queue
        this.clearQueue();

        // Add all songs to queue
        data.songs.forEach((song) => {
          this.queue.push(song);
        });

        // Play first song
        if (this.queue.length > 0) {
          this.currentIndex = 0;
          await this.playSong(this.queue[0], 0);
          this.updateQueueUI();
          this.showNotification(
            `Playing ${data.songs.length} songs from playlist`
          );
        }
      } else {
        this.showNotification("No songs in this playlist", "error");
      }
    } catch (error) {
      console.error("Play playlist error:", error);
      this.showNotification("Error playing playlist", "error");
    }
  }

  async playAlbum(albumId) {
    try {
      const response = await fetch(
        `api/albums.php?action=get_album_songs&id=${albumId}`
      );
      const data = await response.json();

      if (data.success && data.songs && data.songs.length > 0) {
        // Clear current queue
        this.clearQueue();

        // Add all songs to queue
        data.songs.forEach((song) => {
          this.queue.push(song);
        });

        // Play first song
        if (this.queue.length > 0) {
          this.currentIndex = 0;
          await this.playSong(this.queue[0], 0);
          this.updateQueueUI();
          this.showNotification(
            `Playing ${data.songs.length} songs from album`
          );
        }
      } else {
        this.showNotification("No songs in this album", "error");
      }
    } catch (error) {
      console.error("Play album error:", error);
      this.showNotification("Error playing album", "error");
    }
  }

  //  EVENT HANDLERS
  onMetadataLoaded() {
    const totalTime = document.getElementById("totalTime");
    if (totalTime && this.audio.duration) {
      totalTime.textContent = this.formatTime(this.audio.duration);
    }
  }

  onAudioError(e) {
    console.error("Audio error:", e);
    this.showNotification("Error playing audio", "error");
    this.isPlaying = false;
    this.updatePlayButton(false);
  }

  //  NOTIFICATION SYSTEM
  showNotification(message, type = "success") {
    // Remove existing notifications
    const existing = document.querySelectorAll(".notification");
    existing.forEach((n, i) => {
      setTimeout(() => {
        n.classList.remove("show");
        setTimeout(() => n.remove(), 300);
      }, i * 100);
    });

    // Create notification element
    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
      <div class="notification-content">
        <i class="fas fa-${
          type === "success" ? "check" : type === "error" ? "times" : "info"
        }"></i>
        <span>${message}</span>
      </div>
    `;

    const container =
      document.getElementById("notificationContainer") || document.body;
    container.appendChild(notification);

    // Show animation
    setTimeout(() => notification.classList.add("show"), 10);

    // Auto remove after 3 seconds
    setTimeout(() => {
      notification.classList.remove("show");
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }
}

// ====
// GLOBAL INITIALIZATION
// ====

function initializeMusicPlayer() {
  console.log(" Initializing MusicPlayer...");

  // Check if player already exists
  if (window.musicPlayerInstance) {
    console.log(" Using existing player instance");
    return window.musicPlayerInstance;
  }

  // Create new instance
  try {
    const player = new MusicPlayer();
    console.log(" Player created successfully with persistent volume");

    // Debug: Check initial volume
    console.log("🔊 Initial volume set to:", player.volume * 100 + "%");

    return player;
  } catch (error) {
    console.error(" Player initialization failed:", error);
    return null;
  }
}

// Global functions
window.showNotification = function (message, type = "success") {
  if (window.musicPlayerInstance) {
    window.musicPlayerInstance.showNotification(message, type);
  } else {
    console.log(`${type}: ${message}`);
  }
};

// Global play functions
window.playSong = function (song) {
  if (window.musicPlayerInstance) {
    window.musicPlayerInstance.playSong(song);
  }
};

window.addToQueue = function (song) {
  if (window.musicPlayerInstance) {
    window.musicPlayerInstance.addToQueue(song);
  }
};

// Initialize when DOM is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    setTimeout(initializeMusicPlayer, 100);
  });
} else {
  setTimeout(initializeMusicPlayer, 100);
}

// Export untuk penggunaan modul (jika diperlukan)
if (typeof module !== "undefined" && module.exports) {
  module.exports = { MusicPlayer, initializeMusicPlayer };
}
