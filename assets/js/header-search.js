class HeaderSearch {
  constructor() {
    this.searchInput = document.getElementById("search-input");
    this.searchSuggestions = document.getElementById("searchSuggestions");
    this.searchTimeout = null;
    this.isSearching = false;
    this.minQueryLength = 2;
    this.lastQuery = "";

    if (this.searchInput) {
      this.init();
    }
  }

  init() {
    console.log(" Header Search initialized");
    this.setupEventListeners();

    // Auto-focus search if there's a query in URL
    const urlParams = new URLSearchParams(window.location.search);
    const searchQuery = urlParams.get("q");
    if (searchQuery && this.searchInput) {
      this.searchInput.value = searchQuery;
    }
  }

  setupEventListeners() {
    if (!this.searchInput) return;

    // Input event with debounce
    this.searchInput.addEventListener("input", (e) => {
      const query = e.target.value.trim();

      // Clear previous timeout
      clearTimeout(this.searchTimeout);

      if (query.length === 0) {
        this.hideSuggestions();
        return;
      }

      // Show loading state for queries >= min length
      if (query.length >= this.minQueryLength) {
        this.showLoading();
      }

      // Set new timeout for search
      this.searchTimeout = setTimeout(() => {
        if (query.length >= this.minQueryLength) {
          this.performSearch(query);
        } else if (query.length > 0) {
          this.showShortQuery();
        }
      }, 300);
    });

    this.searchInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        const query = this.searchInput.value.trim();
        if (query.length > 0) {
          console.log("Enter pressed, redirecting to browse.php");
          this.submitSearch(query);
          this.hideSuggestions();
        }
      }
    });

    // Focus event to show suggestions if there's a query
    this.searchInput.addEventListener("focus", () => {
      const query = this.searchInput.value.trim();
      if (query.length >= this.minQueryLength && query !== this.lastQuery) {
        this.performSearch(query);
      } else if (query.length > 0) {
        this.searchSuggestions.classList.add("show");
      }
    });

    // Close suggestions when clicking outside
    document.addEventListener("click", (e) => {
      if (!e.target.closest(".search-suggestions-container")) {
        this.hideSuggestions();
      }
    });

    // Escape key to close suggestions
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        this.hideSuggestions();
        this.searchInput.blur();
      }
    });
  }

  async performSearch(query) {
    if (this.isSearching || query === this.lastQuery) return;

    this.isSearching = true;
    this.lastQuery = query;
    console.log(" Searching for:", query);

    try {
      const response = await fetch(
        `api/search.php?q=${encodeURIComponent(query)}&limit=10`
      );
      const data = await response.json();
      console.log(" Search results:", data);

      if (data.success) {
        this.showSuggestions(data.results, query);
      } else {
        this.showNoResults(query);
      }
    } catch (error) {
      console.error(" Search error:", error);
      this.showError();
    } finally {
      this.isSearching = false;
    }
  }

  showSuggestions(results, query) {
    if (!this.searchSuggestions || !results || results.length === 0) {
      this.showNoResults(query);
      return;
    }

    let html = "";
    let resultCount = 0;
    const maxItems = 8;

    // Group results by type
    const songs = results
      .filter((r) => r.type === "song" || !r.type)
      .slice(0, 4);
    const artists = results.filter((r) => r.type === "artist").slice(0, 2);
    const albums = results.filter((r) => r.type === "album").slice(0, 2);
    const playlists = results.filter((r) => r.type === "playlist").slice(0, 2);

    // Add songs section
    if (songs.length > 0) {
      html += `<div class="suggestion-category">Songs</div>`;
      songs.forEach((song) => {
        html += this.renderSongItem(song);
        resultCount++;
      });
    }

    // Add artists section
    if (artists.length > 0 && resultCount < maxItems) {
      html += `<div class="suggestion-category">Artists</div>`;
      artists.forEach((artist) => {
        if (resultCount < maxItems) {
          html += this.renderArtistItem(artist);
          resultCount++;
        }
      });
    }

    // Add albums section
    if (albums.length > 0 && resultCount < maxItems) {
      html += `<div class="suggestion-category">Albums</div>`;
      albums.forEach((album) => {
        if (resultCount < maxItems) {
          html += this.renderAlbumItem(album);
          resultCount++;
        }
      });
    }

    // Add playlists section
    if (playlists.length > 0 && resultCount < maxItems) {
      html += `<div class="suggestion-category">Playlists</div>`;
      playlists.forEach((playlist) => {
        if (resultCount < maxItems) {
          html += this.renderPlaylistItem(playlist);
          resultCount++;
        }
      });
    }

    html += `
      <a href="browse.php?q=${encodeURIComponent(
        query
      )}" class="view-all-results">
        See all results for "${this.escapeHtml(query)}"
      </a>
    `;

    this.searchSuggestions.innerHTML = html;
    this.searchSuggestions.classList.add("show");

    // Update scroll position
    this.searchSuggestions.scrollTop = 0;
  }

  renderSongItem(song) {
    const coverPath = this.getCoverPath(song.cover_image);
    const duration = song.duration ? this.formatDuration(song.duration) : "";

    return `
      <a href="browse.php?q=${encodeURIComponent(song.title)}#song-${
      song.id
    }" class="suggestion-item" data-type="song" data-id="${song.id}">
        <img src="${coverPath}" 
             alt="${this.escapeHtml(song.title)}"
             onerror="this.src='assets/images/covers/default-cover.png'">
        <div class="suggestion-info">
          <h4>${this.escapeHtml(song.title)}</h4>
          <p>${this.escapeHtml(song.artist_name || "Unknown Artist")} • Song</p>
          ${duration ? `<span class="song-duration">${duration}</span>` : ""}
        </div>
        <i class="fas fa-play"></i>
      </a>
    `;
  }

  renderArtistItem(artist) {
    const avatarPath = artist.profile_image
      ? `uploads/artists/${artist.profile_image}`
      : null;

    return `
      <a href="artist.php?id=${
        artist.id
      }" class="suggestion-item" data-type="artist" data-id="${artist.id}">
        <div class="artist-avatar">
          ${
            avatarPath
              ? `<img src="${avatarPath}" alt="${this.escapeHtml(artist.name)}"
                 onerror="this.src='assets/images/avatars/default-artist.png'">`
              : `<i class="fas fa-user"></i>`
          }
        </div>
        <div class="suggestion-info">
          <h4>${this.escapeHtml(artist.name)}</h4>
          <p>Artist</p>
        </div>
        <i class="fas fa-user"></i>
      </a>
    `;
  }

  renderAlbumItem(album) {
    const coverPath = this.getCoverPath(album.cover_image);

    return `
      <a href="album_detail.php?id=${
        album.id
      }" class="suggestion-item" data-type="album" data-id="${album.id}">
        <img src="${coverPath}" 
             alt="${this.escapeHtml(album.title)}"
             onerror="this.src='assets/images/covers/default-cover.png'">
        <div class="suggestion-info">
          <h4>${this.escapeHtml(album.title)}</h4>
          <p>${this.escapeHtml(
            album.artist_name || "Unknown Artist"
          )} • Album</p>
          ${
            album.song_count
              ? `<span class="song-count">${album.song_count} songs</span>`
              : ""
          }
        </div>
        <i class="fas fa-play"></i>
      </a>
    `;
  }

  renderPlaylistItem(playlist) {
    return `
      <a href="playlist_detail.php?id=${
        playlist.id
      }" class="suggestion-item" data-type="playlist" data-id="${playlist.id}">
        <div class="playlist-icon">
          <i class="fas fa-list"></i>
        </div>
        <div class="suggestion-info">
          <h4>${this.escapeHtml(playlist.title)}</h4>
          <p>Playlist • ${this.escapeHtml(
            playlist.creator_name || "Unknown"
          )}</p>
          ${
            playlist.song_count
              ? `<span class="song-count">${playlist.song_count} songs</span>`
              : ""
          }
        </div>
        <i class="fas fa-play"></i>
      </a>
    `;
  }

  showLoading() {
    if (!this.searchSuggestions) return;

    this.searchSuggestions.innerHTML = `
      <div class="suggestion-item loading">
        <div class="suggestion-info">
          <p>Searching...</p>
        </div>
        <div class="spinner"></div>
      </div>
    `;
    this.searchSuggestions.classList.add("show");
  }

  showShortQuery() {
    if (!this.searchSuggestions) return;

    this.searchSuggestions.innerHTML = `
      <div class="suggestion-item no-results">
        <div class="suggestion-info">
          <p>Type at least ${this.minQueryLength} characters to search</p>
        </div>
      </div>
    `;
    this.searchSuggestions.classList.add("show");
  }

  showNoResults(query = "") {
    if (!this.searchSuggestions) return;

    this.searchSuggestions.innerHTML = `
      <div class="suggestion-item no-results">
        <div class="suggestion-info">
          <h4>No results found${
            query ? ` for "${this.escapeHtml(query)}"` : ""
          }</h4>
          <p>Try searching for something else</p>
        </div>
      </div>
    `;
    this.searchSuggestions.classList.add("show");
  }

  showError() {
    if (!this.searchSuggestions) return;

    this.searchSuggestions.innerHTML = `
      <div class="suggestion-item error">
        <div class="suggestion-info">
          <h4>Search error</h4>
          <p>Please try again in a moment</p>
        </div>
      </div>
    `;
    this.searchSuggestions.classList.add("show");
  }

  hideSuggestions() {
    if (this.searchSuggestions) {
      this.searchSuggestions.classList.remove("show");
      setTimeout(() => {
        if (!this.searchSuggestions.classList.contains("show")) {
          this.searchSuggestions.innerHTML = "";
        }
      }, 300);
    }
  }

  submitSearch(query) {
    console.log(" Submitting search to browse.php:", query);
    window.location.href = `browse.php?q=${encodeURIComponent(query)}`;
  }

  getCoverPath(coverImage) {
    if (!coverImage || coverImage === "default-cover.png") {
      return "assets/images/covers/default-cover.png";
    }

    if (coverImage.startsWith("http")) {
      return coverImage;
    }

    if (coverImage.startsWith("uploads/")) {
      return coverImage;
    }

    return `uploads/covers/${coverImage}`;
  }

  formatDuration(seconds) {
    if (!seconds) return "0:00";
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, "0")}`;
  }

  escapeHtml(unsafe) {
    if (!unsafe) return "";
    return unsafe
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
}

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  window.headerSearch = new HeaderSearch();
});
