// assets/js/search.js

class SearchManager {
  constructor() {
    this.init();
  }

  init() {
    this.bindSearchEvents();
    this.bindGenreFilters();
  }

  bindSearchEvents() {
    const searchInput = document.querySelector(".search-input");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => {
        this.debounce(() => {
          this.performSearch(e.target.value);
        }, 300)();
      });
    }
  }

  bindGenreFilters() {
    // Genre filter cards are already links, no additional JS needed
  }

  async performSearch(query) {
    if (query.length < 2) {
      this.clearSearchResults();
      return;
    }

    try {
      const response = await fetch(
        `api/search.php?q=${encodeURIComponent(query)}&limit=10`
      );
      const data = await response.json();

      if (data.success) {
        this.displaySearchSuggestions(data.results);
      }
    } catch (error) {
      console.error("Search error:", error);
    }
  }

  displaySearchSuggestions(results) {
    // Implement search suggestions dropdown
    // This would show a dropdown with quick results
    console.log("Search results:", results);
  }

  clearSearchResults() {
    // Clear search suggestions
  }

  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }
}

// Initialize search manager
document.addEventListener("DOMContentLoaded", () => {
  window.searchManager = new SearchManager();
});
