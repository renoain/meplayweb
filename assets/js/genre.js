class GenreManager {
  constructor() {
    this.genres = new Map();
    this.setupGenreNavigation();
    console.log("🎵 Genre Manager started");
  }

  setupGenreNavigation() {
    this.addGenreSectionToHome();

    this.setupGenreFilter();
  }

  addGenreSectionToHome() {
    const homeView = document.getElementById("homeView");
    if (!homeView) {
      console.error("❌ Home view not found");
      return;
    }

    // Add genres section after recently played
    const genreSection = `
            <section class="section-container">
                <div class="section-header">
                    <h2 class="section-title">Browse by Genre</h2>
                </div>
                <div class="genres-grid" id="genresGrid">
                    <div class="empty-state">
                        <p>Loading genres...</p>
                    </div>
                </div>
            </section>
        `;

    // Insert after recently played section
    const recentlyPlayed = homeView.querySelector(
      ".section-container:last-child"
    );
    if (recentlyPlayed) {
      recentlyPlayed.insertAdjacentHTML("afterend", genreSection);
      console.log("✅ Genre section added to home");
    } else {
      console.error("❌ Recently played section not found");
    }

    this.loadAndDisplayGenres();
  }

  loadAndDisplayGenres() {
    if (!window.mePlayApp || !window.mePlayApp.songs) {
      console.log("🔄 Waiting for songs to load...");
      setTimeout(() => this.loadAndDisplayGenres(), 500);
      return;
    }

    console.log("📊 Organizing songs by genre...");
    this.organizeSongsByGenre();
    this.renderGenreGrid();
  }

  organizeSongsByGenre() {
    this.genres.clear();

    window.mePlayApp.songs.forEach((song) => {
      const genre = song.genre || "Unknown";

      if (!this.genres.has(genre)) {
        this.genres.set(genre, {
          name: genre,
          songs: [],
          cover: song.cover_path || "assets/images/default-cover.jpg",
        });
      }

      this.genres.get(genre).songs.push(song);

      const genreData = this.genres.get(genre);
      if (
        song.cover_path &&
        song.cover_path !== "assets/images/default-cover.jpg"
      ) {
        genreData.cover = song.cover_path;
      }
    });

    console.log("🎵 Organized genres:", Array.from(this.genres.keys()));
  }

  renderGenreGrid() {
    const grid = document.getElementById("genresGrid");
    if (!grid) {
      console.error("❌ Genres grid not found");
      return;
    }

    if (this.genres.size === 0) {
      grid.innerHTML = `
                <div class="empty-state">
                    <p>No genres found</p>
                    <p>Songs need to have genre information</p>
                </div>
            `;
      return;
    }

    const genresArray = Array.from(this.genres.values()).sort(
      (a, b) => b.songs.length - a.songs.length
    );

    grid.innerHTML = genresArray
      .map(
        (genre) => `
            <div class="genre-card" data-genre="${genre.name}">
                <div class="genre-image">
                    <img src="${genre.cover}" 
                         alt="${genre.name}" 
                         class="genre-cover"
                         onerror="this.src='assets/images/default-cover.jpg'">
                    <div class="genre-overlay">
                        <div class="genre-info">
                            <h3 class="genre-name">${genre.name}</h3>
                            <p class="genre-stats">${genre.songs.length} songs</p>
                        </div>
                        <div class="play-button">
                            <i class="bi bi-play-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        `
      )
      .join("");

    this.setupGenreInteractions();
    console.log("✅ Genre grid rendered with", genresArray.length, "genres");
  }

  setupGenreInteractions() {
    const grid = document.getElementById("genresGrid");
    if (!grid) return;

    grid.querySelectorAll(".genre-card").forEach((card) => {
      card.addEventListener("click", (e) => {
        if (!e.target.closest(".play-button")) {
          const genre = card.dataset.genre;
          console.log("🎵 Showing genre detail:", genre);
          this.showGenreDetail(genre);
        }
      });
    });

    // Play button - play all genre songs
    grid.querySelectorAll(".play-button").forEach((button) => {
      button.addEventListener("click", (e) => {
        e.stopPropagation();
        const genreCard = e.target.closest(".genre-card");
        const genre = genreCard.dataset.genre;
        console.log("▶️ Playing genre:", genre);
        this.playGenre(genre);
      });
    });
  }

  showGenreDetail(genreName) {
    if (!window.mePlayApp) return;

    const genre = this.genres.get(genreName);
    if (!genre) {
      console.error("❌ Genre not found:", genreName);
      return;
    }

    console.log("📁 Creating genre detail view for:", genreName);
    // Create or update genre detail view
    this.createGenreDetailView(genre);
    window.mePlayApp.showView("genreDetail");
  }

  createGenreDetailView(genre) {
    let detailView = document.getElementById("genreDetailView");

    if (!detailView) {
      detailView = document.createElement("div");
      detailView.className = "view";
      detailView.id = "genreDetailView";
      detailView.innerHTML = this.getGenreDetailHTML(genre);
      document.querySelector(".content-area").appendChild(detailView);
      console.log("✅ Created new genre detail view");
    } else {
      detailView.innerHTML = this.getGenreDetailHTML(genre);
      console.log("✅ Updated existing genre detail view");
    }

    this.setupGenreDetailInteractions(genre);
  }

  getGenreDetailHTML(genre) {
    return `
            <div class="playlist-header">
                <div class="playlist-cover">
                    <img src="${genre.cover}" 
                         alt="${genre.name}" 
                         class="detail-playlist-cover"
                         onerror="this.src='assets/images/default-cover.jpg'">
                </div>
                <div class="playlist-info">
                    <span class="playlist-type">GENRE</span>
                    <h1 class="playlist-title">${genre.name}</h1>
                    <p class="playlist-stats">${genre.songs.length} songs</p>
                </div>
            </div>
            <div class="playlist-actions">
                <button class="btn-play-large" id="playGenreSongs">
                    <i class="bi bi-play-fill"></i>
                </button>
                <button class="btn-shuffle-genre" id="shuffleGenreSongs">
                    <i class="bi bi-shuffle"></i> Shuffle Play
                </button>
            </div>
            <div class="tracks-grid" id="genreSongsGrid">
                ${this.getGenreSongsGridHTML(genre.songs)}
            </div>
        `;
  }

  getGenreSongsGridHTML(songs) {
    if (!songs.length) {
      return '<div class="empty-state"><p>No songs in this genre</p></div>';
    }

    return songs
      .map((song, index) => {
        const isLiked = window.playlistManager?.isSongLiked(song.id);
        return `
                <div class="track-card" data-track-id="${song.id}">
                    <div class="track-image">
                        <img src="${
                          song.cover_path || "assets/images/default-cover.jpg"
                        }" 
                             alt="${song.title}" 
                             class="track-cover"
                             onerror="this.src='assets/images/default-cover.jpg'">
                        <div class="play-button">
                            <i class="bi bi-play-fill"></i>
                        </div>
                        <div class="track-dropdown">
                            <button class="track-dropdown-btn">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <div class="track-dropdown-content">
                                <button class="track-dropdown-item add-to-queue" data-song-id="${
                                  song.id
                                }">
                                    <i class="bi bi-plus-circle"></i> Add to Queue
                                </button>
                                <button class="track-dropdown-item toggle-like ${
                                  isLiked ? "liked" : ""
                                }" data-song-id="${song.id}">
                                    <i class="bi bi-heart${
                                      isLiked ? "-fill" : ""
                                    }"></i> 
                                    ${
                                      isLiked
                                        ? "Remove from Liked"
                                        : "Add to Liked"
                                    }
                                </button>
                                <div class="track-dropdown-divider"></div>
                                <button class="track-dropdown-item add-to-playlist" data-song-id="${
                                  song.id
                                }">
                                    <i class="bi bi-plus-square"></i> Add to Playlist
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="track-info">
                        <h4 class="track-title">${
                          song.title || "Unknown Title"
                        }</h4>
                        <p class="track-artist">${
                          song.artist || "Unknown Artist"
                        }</p>
                    </div>
                </div>
            `;
      })
      .join("");
  }

  setupGenreDetailInteractions(genre) {
    // Play genre songs
    document.getElementById("playGenreSongs")?.addEventListener("click", () => {
      console.log("▶️ Playing all songs in genre:", genre.name);
      this.playGenre(genre.name);
    });

    // Shuffle genre songs
    document
      .getElementById("shuffleGenreSongs")
      ?.addEventListener("click", () => {
        console.log("🔀 Shuffling songs in genre:", genre.name);
        this.shufflePlayGenre(genre.name);
      });

    // Setup track interactions
    const container = document.getElementById("genreSongsGrid");
    if (container && window.mePlayApp) {
      window.mePlayApp.setupTrackInteractions(container, genre.songs, "genre");
      console.log("✅ Setup track interactions for genre songs");
    }
  }

  playGenre(genreName) {
    const genre = this.genres.get(genreName);
    if (!genre || !genre.songs.length) {
      console.error("❌ No songs found for genre:", genreName);
      return;
    }

    if (window.mePlayApp) {
      window.mePlayApp.playTrack(genre.songs, 0, "genre");
      window.mePlayApp.showToast(
        `Playing ${genre.songs.length} ${genre.name} songs`,
        "success"
      );
    }
  }

  shufflePlayGenre(genreName) {
    const genre = this.genres.get(genreName);
    if (!genre || !genre.songs.length) return;

    const shuffledSongs = [...genre.songs].sort(() => Math.random() - 0.5);

    if (window.mePlayApp) {
      window.mePlayApp.playTrack(shuffledSongs, 0, "genre");
      window.mePlayApp.showToast(
        `Shuffling ${genre.songs.length} ${genre.name} songs`,
        "success"
      );
    }
  }

  // Add genre filter to search view
  setupGenreFilter() {
    const searchView = document.getElementById("searchView");
    if (searchView) {
      const searchTitle = searchView.querySelector(".search-title");
      if (searchTitle) {
        const filterHTML = `
                    <div class="genre-filter-container" id="genreFilterContainer" style="display: none;">
                        <div class="genre-filter">
                            <span>Filter by genre:</span>
                            <select id="genreFilterSelect">
                                <option value="">All Genres</option>
                            </select>
                        </div>
                    </div>
                `;
        searchTitle.insertAdjacentHTML("afterend", filterHTML);
        console.log("✅ Genre filter added to search");
      }
    }
  }

  showGenreFilter() {
    const container = document.getElementById("genreFilterContainer");
    const select = document.getElementById("genreFilterSelect");

    if (!container || !select) {
      console.error("❌ Genre filter elements not found");
      return;
    }

    // Populate genre options
    select.innerHTML = '<option value="">All Genres</option>';
    Array.from(this.genres.keys())
      .sort()
      .forEach((genre) => {
        select.innerHTML += `<option value="${genre}">${genre}</option>`;
      });

    container.style.display = "block";
    console.log("✅ Genre filter shown with", this.genres.size, "genres");

    select.replaceWith(select.cloneNode(true));
    const newSelect = document.getElementById("genreFilterSelect");

    // Add filter functionality
    newSelect.addEventListener("change", (e) => {
      console.log("🔍 Filtering search by genre:", e.target.value);
      this.filterSearchResults(e.target.value);
    });
  }

  filterSearchResults(genre) {
    const searchInput = document.getElementById("searchInput");
    if (!searchInput) return;

    const currentQuery = searchInput.value.trim();

    if (window.searchManager) {
      window.searchManager.performSearch(currentQuery, genre);
    } else {
      console.error("❌ Search manager not available");
    }
  }

  getSongsByGenre(genreName) {
    return this.genres.get(genreName)?.songs || [];
  }

  getAllGenres() {
    return Array.from(this.genres.keys()).sort();
  }

  getGenreStats() {
    const stats = [];
    this.genres.forEach((genre, name) => {
      stats.push({
        name: name,
        songCount: genre.songs.length,
        cover: genre.cover,
      });
    });
    return stats.sort((a, b) => b.songCount - a.songCount);
  }
}

// Initialize genre manager
document.addEventListener("DOMContentLoaded", () => {
  setTimeout(() => {
    window.genreManager = new GenreManager();
  }, 1500);
});
