// Sidebar functionality
class SidebarManager {
  constructor() {
    this.sidebar = document.getElementById("sidebar");
    this.sidebarToggle = document.getElementById("sidebarToggle");
    this.sidebarClose = document.getElementById("sidebarClose");
    this.sidebarOverlay = document.getElementById("sidebarOverlay");
    this.init();
  }

  init() {
    this.bindEvents();
    this.handleResize();
    this.loadLibraryStats();
  }

  bindEvents() {
    // Sidebar toggle
    if (this.sidebarToggle) {
      this.sidebarToggle.addEventListener("click", () => {
        this.toggleSidebar();
      });
    }

    // Sidebar close
    if (this.sidebarClose) {
      this.sidebarClose.addEventListener("click", () => {
        this.closeSidebar();
      });
    }

    // Overlay click
    if (this.sidebarOverlay) {
      this.sidebarOverlay.addEventListener("click", () => {
        this.closeSidebar();
      });
    }

    // Window resize
    window.addEventListener("resize", () => {
      this.handleResize();
    });

    // Create playlist button
    const createPlaylistBtn = document.getElementById("createPlaylistBtn");
    if (createPlaylistBtn) {
      createPlaylistBtn.addEventListener("click", (e) => {
        e.preventDefault();
        this.createPlaylist();
      });
    }

    // Navigation links
    this.setupNavigation();
  }

  setupNavigation() {
    const navLinks = document.querySelectorAll(".sidebar-nav a[href]");
    navLinks.forEach((link) => {
      link.addEventListener("click", (e) => {
        // Remove active class from all links
        navLinks.forEach((l) => l.classList.remove("active"));
        // Add active class to clicked link
        link.classList.add("active");
      });
    });
  }

  toggleSidebar() {
    if (window.innerWidth <= 768) {
      this.sidebar.classList.toggle("mobile-open");
      if (this.sidebarOverlay) {
        this.sidebarOverlay.classList.toggle("show");
      }
    } else {
      const mainContent = document.querySelector(".main-content");
      const isCollapsed = mainContent.classList.toggle("sidebar-collapsed");

      if (isCollapsed) {
        this.sidebar.style.transform = "translateX(-280px)";
      } else {
        this.sidebar.style.transform = "translateX(0)";
      }
    }
  }

  closeSidebar() {
    this.sidebar.classList.remove("mobile-open");
    if (this.sidebarOverlay) {
      this.sidebarOverlay.classList.remove("show");
    }
  }

  handleResize() {
    if (window.innerWidth > 768) {
      this.sidebar.style.transform = "translateX(0)";
      if (this.sidebarOverlay) {
        this.sidebarOverlay.classList.remove("show");
      }
    } else {
      this.sidebar.classList.remove("mobile-open");
    }
  }

  async loadLibraryStats() {
    try {
      const response = await fetch("api/library.php?action=stats");
      const data = await response.json();

      if (data.success) {
        this.updateLibraryStats(data.stats);
      }
    } catch (error) {
      console.error("Error loading library stats:", error);
    }
  }

  updateLibraryStats(stats) {
    const statItems = document.querySelectorAll(".library-stats .stat-item");

    statItems.forEach((item) => {
      const icon = item.querySelector("i");
      if (icon) {
        if (icon.classList.contains("fa-music")) {
          item.querySelector("span").textContent = `${stats.total_songs} lagu`;
        } else if (icon.classList.contains("fa-clock")) {
          item.querySelector(
            "span"
          ).textContent = `${stats.total_duration} menit`;
        }
      }
    });
  }

  createPlaylist() {
    const modal = document.createElement("div");
    modal.className = "modal";
    modal.innerHTML = `
      <div class="modal-content">
        <div class="modal-header">
          <h3>Buat Playlist Baru</h3>
          <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
          <form id="createPlaylistForm">
            <div class="form-group">
              <label for="playlistName">Nama Playlist</label>
              <input type="text" id="playlistName" required placeholder="Masukkan nama playlist">
            </div>
            <div class="form-group">
              <label for="playlistDescription">Deskripsi (opsional)</label>
              <textarea id="playlistDescription" rows="3" placeholder="Deskripsi playlist"></textarea>
            </div>
            <div class="form-actions">
              <button type="button" class="btn btn-outline cancel-btn">Batal</button>
              <button type="submit" class="btn btn-primary">Buat</button>
            </div>
          </form>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    // Add modal styles if not exists
    if (!document.querySelector("#modal-styles")) {
      const styles = document.createElement("style");
      styles.id = "modal-styles";
      styles.textContent = `
        .modal {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.5);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 10000;
        }
        .modal-content {
          background: var(--bg-primary);
          border-radius: 12px;
          width: 90%;
          max-width: 400px;
          box-shadow: var(--shadow-lg);
          border: 1px solid var(--border-color);
        }
        .modal-header {
          padding: 20px;
          border-bottom: 1px solid var(--border-color);
          display: flex;
          align-items: center;
          justify-content: space-between;
        }
        .modal-header h3 {
          margin: 0;
          font-size: 1.2rem;
          color: var(--text-primary);
        }
        .modal-close {
          background: none;
          border: none;
          font-size: 1.5rem;
          cursor: pointer;
          color: var(--text-secondary);
          padding: 0;
          width: 30px;
          height: 30px;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .modal-close:hover {
          background: var(--bg-tertiary);
          border-radius: 4px;
        }
        .modal-body {
          padding: 20px;
        }
        .form-group {
          margin-bottom: 15px;
        }
        .form-group label {
          display: block;
          margin-bottom: 5px;
          font-weight: 500;
          color: var(--text-primary);
        }
        .form-group input,
        .form-group textarea {
          width: 100%;
          padding: 10px;
          border: 1px solid var(--border-color);
          border-radius: 6px;
          background: var(--bg-secondary);
          color: var(--text-primary);
          font-family: inherit;
        }
        .form-group input:focus,
        .form-group textarea:focus {
          outline: none;
          border-color: var(--accent-color);
        }
        .form-actions {
          display: flex;
          gap: 10px;
          justify-content: flex-end;
          margin-top: 20px;
        }
        .btn {
          padding: 10px 20px;
          border: none;
          border-radius: 6px;
          cursor: pointer;
          font-weight: 500;
          transition: all 0.3s ease;
        }
        .btn-outline {
          background: transparent;
          border: 1px solid var(--border-color);
          color: var(--text-primary);
        }
        .btn-outline:hover {
          background: var(--bg-tertiary);
        }
        .btn-primary {
          background: var(--accent-color);
          color: white;
        }
        .btn-primary:hover {
          background: var(--accent-hover);
        }
      `;
      document.head.appendChild(styles);
    }

    // Close modal events
    const closeModal = () => modal.remove();

    modal.querySelector(".modal-close").addEventListener("click", closeModal);
    modal.querySelector(".cancel-btn").addEventListener("click", closeModal);
    modal.addEventListener("click", (e) => {
      if (e.target === modal) closeModal();
    });

    // Form submission
    modal.querySelector("form").addEventListener("submit", (e) => {
      e.preventDefault();
      const name = document.getElementById("playlistName").value;
      const description = document.getElementById("playlistDescription").value;
      this.submitPlaylist(name, description);
      closeModal();
    });

    // Focus on input
    setTimeout(() => {
      const nameInput = document.getElementById("playlistName");
      if (nameInput) nameInput.focus();
    }, 100);
  }

  async submitPlaylist(name, description) {
    try {
      const response = await fetch("api/playlists.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "create",
          title: name,
          description: description,
        }),
      });

      const result = await response.json();
      if (result.success) {
        showNotification("Playlist berhasil dibuat!", "success");
        // Refresh playlists list
        if (window.playlistManager) {
          window.playlistManager.loadUserPlaylists();
        }
      } else {
        throw new Error(result.message);
      }
    } catch (error) {
      console.error("Error creating playlist:", error);
      showNotification("Gagal membuat playlist: " + error.message, "error");
    }
  }
}

// Initialize sidebar when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.sidebarManager = new SidebarManager();
});
