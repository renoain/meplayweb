// Theme Management
class ThemeManager {
  constructor() {
    this.currentTheme = this.getSavedTheme() || "light";
    this.init();
  }

  init() {
    this.applyTheme(this.currentTheme);
    this.bindEvents();
  }

  getSavedTheme() {
    return localStorage.getItem("meplay-theme");
  }

  saveTheme(theme) {
    localStorage.setItem("meplay-theme", theme);
  }

  applyTheme(theme) {
    const html = document.documentElement;

    // Handle auto theme
    if (theme === "auto") {
      theme = window.matchMedia("(prefers-color-scheme: dark)").matches
        ? "dark"
        : "light";
    }

    html.setAttribute("data-theme", theme);
    this.currentTheme = theme;
    this.saveTheme(theme);
    this.updateUI(theme);
  }

  updateUI(theme) {
    // Update theme toggle button
    const themeToggle = document.getElementById("themeToggle");
    if (themeToggle) {
      const icon = themeToggle.querySelector("i");
      if (theme === "dark") {
        icon.className = "fas fa-sun";
        themeToggle.title = "Toggle Light Mode";
      } else {
        icon.className = "fas fa-moon";
        themeToggle.title = "Toggle Dark Mode";
      }
    }

    // Update theme options in dropdown
    const themeOptions = document.querySelectorAll(".theme-option");
    themeOptions.forEach((option) => {
      option.classList.remove("active");
      if (option.dataset.theme === theme) {
        option.classList.add("active");
      }
    });
  }

  bindEvents() {
    // Theme toggle button
    const themeToggle = document.getElementById("themeToggle");
    if (themeToggle) {
      themeToggle.addEventListener("click", () => {
        this.toggleTheme();
      });
    }

    // Theme options in dropdown
    const themeOptions = document.querySelectorAll(".theme-option");
    themeOptions.forEach((option) => {
      option.addEventListener("click", (e) => {
        e.stopPropagation();
        const theme = option.dataset.theme;
        this.applyTheme(theme);
      });
    });

    // Listen for system theme changes
    const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
    mediaQuery.addEventListener("change", (e) => {
      if (this.getSavedTheme() === "auto") {
        this.applyTheme("auto");
      }
    });
  }

  toggleTheme() {
    const newTheme = this.currentTheme === "light" ? "dark" : "light";
    this.applyTheme(newTheme);
  }
}

// Initialize theme manager when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.themeManager = new ThemeManager();
});

// Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
  module.exports = ThemeManager;
}
