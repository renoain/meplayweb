// ============================================
// MODAL DELETE JS - REUSABLE FOR ALL PAGES
// ============================================

class DeleteModal {
  constructor() {
    this.modal = null;
    this.callback = null;
    this.type = "";
    this.itemId = null;
    this.itemName = "";
    this.additionalInfo = "";
    this.requireConfirmation = false;
    this.confirmationText = "";
    this.deleteUrl = "";

    this.init();
  }

  init() {
    // Create modal HTML
    this.createModal();

    // Add event listeners
    document.addEventListener("click", this.handleOutsideClick.bind(this));
    document.addEventListener("keydown", this.handleEscapeKey.bind(this));
  }

  createModal() {
    const modalHTML = `
      <div class="modal-backdrop" id="deleteModal">
        <div class="modal-delete">
          <div class="modal-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3 id="modalTitle">Konfirmasi Hapus</h3>
          </div>
          <div class="modal-body">
            <p id="modalMessage">Apakah Anda yakin ingin menghapus item ini?</p>
            <div class="item-details" id="itemDetails" style="display: none;">
              <strong id="itemName"></strong>
              <span id="itemAdditional"></span>
            </div>
            <div class="warning-text" id="warningText" style="display: none;">
              <i class="fas fa-exclamation-circle"></i>
              <span id="warningMessage"></span>
            </div>
            <div class="confirmation-input" id="confirmationInput" style="display: none;">
              <label for="confirmText">Ketik "<span id="confirmTextLabel">konfirmasi</span>" untuk melanjutkan:</label>
              <input type="text" id="confirmText" placeholder="Ketik di sini...">
              <div class="form-help" id="confirmationHelp"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="modal-btn modal-btn-cancel" id="cancelBtn">
              <i class="fas fa-times"></i> Batal
            </button>
            <button class="modal-btn modal-btn-delete" id="deleteBtn">
              <i class="fas fa-trash"></i> Hapus
            </button>
          </div>
        </div>
      </div>
    `;

    // Add to body if not exists
    if (!document.getElementById("deleteModal")) {
      document.body.insertAdjacentHTML("beforeend", modalHTML);
    }

    this.modal = document.getElementById("deleteModal");
    this.bindEvents();
  }

  bindEvents() {
    const cancelBtn = document.getElementById("cancelBtn");
    const deleteBtn = document.getElementById("deleteBtn");
    const confirmInput = document.getElementById("confirmText");

    cancelBtn.addEventListener("click", () => this.hide());

    deleteBtn.addEventListener("click", () => {
      if (this.requireConfirmation) {
        const input = document.getElementById("confirmText").value;
        if (input === this.confirmationText) {
          this.executeDelete();
        } else {
          this.showConfirmationError("Teks tidak sesuai. Silakan coba lagi.");
        }
      } else {
        this.executeDelete();
      }
    });

    if (confirmInput) {
      confirmInput.addEventListener("input", (e) => {
        const input = e.target.value;
        const help = document.getElementById("confirmationHelp");

        if (input === this.confirmationText) {
          help.textContent = "✓ Teks sesuai";
          help.style.color = "var(--success)";
        } else if (input) {
          help.textContent = `Teks harus tepat: "${this.confirmationText}"`;
          help.style.color = "var(--danger)";
        } else {
          help.textContent = "";
        }
      });

      confirmInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter") {
          document.getElementById("deleteBtn").click();
        }
      });
    }
  }

  show(options = {}) {
    // Set options
    this.type = options.type || "item";
    this.itemId = options.id;
    this.itemName = options.name || "";
    this.additionalInfo = options.additionalInfo || "";
    this.requireConfirmation = options.requireConfirmation || false;
    this.confirmationText = options.confirmationText || "konfirmasi";
    this.deleteUrl = options.deleteUrl || "";
    this.callback = options.callback || null;
    this.warning = options.warning || "";

    // Update modal content
    this.updateModalContent();

    // Show modal
    this.modal.classList.add("show");
    document.body.style.overflow = "hidden";

    // Focus on appropriate element
    if (this.requireConfirmation) {
      setTimeout(() => {
        document.getElementById("confirmText").focus();
      }, 300);
    } else {
      document.getElementById("cancelBtn").focus();
    }
  }

  updateModalContent() {
    // Update title based on type
    const titles = {
      song: "Hapus Lagu",
      artist: "Hapus Artis",
      album: "Hapus Album",
      genre: "Hapus Genre",
      user: "Hapus User",
      playlist: "Hapus Playlist",
    };

    const title = titles[this.type] || "Konfirmasi Hapus";
    document.getElementById("modalTitle").textContent = title;

    // Update message
    const messages = {
      song: `Apakah Anda yakin ingin menghapus lagu "${this.itemName}"?`,
      artist: `Apakah Anda yakin ingin menghapus artis "${this.itemName}"?`,
      album: `Apakah Anda yakin ingin menghapus album "${this.itemName}"?`,
      genre: `Apakah Anda yakin ingin menghapus genre "${this.itemName}"?`,
      user: `Apakah Anda yakin ingin menghapus user "${this.itemName}"?`,
      playlist: `Apakah Anda yakin ingin menghapus playlist "${this.itemName}"?`,
    };

    const message =
      messages[this.type] ||
      `Apakah Anda yakin ingin menghapus ${this.type} "${this.itemName}"?`;
    document.getElementById("modalMessage").textContent = message;

    // Show item details if available
    const itemDetails = document.getElementById("itemDetails");
    if (this.itemName || this.additionalInfo) {
      itemDetails.style.display = "block";
      document.getElementById("itemName").textContent = this.itemName;

      if (this.additionalInfo) {
        document.getElementById("itemAdditional").textContent =
          this.additionalInfo;
      } else {
        document.getElementById("itemAdditional").textContent = "";
      }
    } else {
      itemDetails.style.display = "none";
    }

    // Show warning if exists
    const warningText = document.getElementById("warningText");
    if (this.warning) {
      warningText.style.display = "flex";
      document.getElementById("warningMessage").textContent = this.warning;
    } else {
      warningText.style.display = "none";
    }

    // Handle confirmation input
    const confirmationInput = document.getElementById("confirmationInput");
    if (this.requireConfirmation) {
      confirmationInput.style.display = "block";
      document.getElementById("confirmTextLabel").textContent =
        this.confirmationText;
      document.getElementById("confirmText").value = "";
      document.getElementById("confirmationHelp").textContent = "";
    } else {
      confirmationInput.style.display = "none";
    }

    // Update delete button text
    const deleteBtn = document.getElementById("deleteBtn");
    const buttonTexts = {
      song: "Hapus Lagu",
      artist: "Hapus Artis",
      album: "Hapus Album",
      genre: "Hapus Genre",
      user: "Hapus User",
      playlist: "Hapus Playlist",
    };

    deleteBtn.innerHTML = `<i class="fas fa-trash"></i> ${
      buttonTexts[this.type] || "Hapus"
    }`;
  }

  showConfirmationError(message) {
    const help = document.getElementById("confirmationHelp");
    help.textContent = message;
    help.style.color = "var(--danger)";

    // Shake animation
    const input = document.getElementById("confirmText");
    input.style.animation = "shake 0.5s";
    input.style.borderColor = "var(--danger)";

    setTimeout(() => {
      input.style.animation = "";
      input.focus();
    }, 500);
  }

  async executeDelete() {
    const deleteBtn = document.getElementById("deleteBtn");
    const originalText = deleteBtn.innerHTML;

    // Show loading
    deleteBtn.classList.add("loading");
    deleteBtn.disabled = true;

    try {
      if (this.callback) {
        // Use custom callback
        await this.callback(this.itemId);
      } else if (this.deleteUrl) {
        // Redirect to delete URL
        window.location.href = this.deleteUrl;
      } else {
        // Default delete action
        this.performDefaultDelete();
      }
    } catch (error) {
      console.error("Delete error:", error);
      this.showError("Terjadi kesalahan saat menghapus. Silakan coba lagi.");
    } finally {
      // Reset button
      deleteBtn.classList.remove("loading");
      deleteBtn.disabled = false;
      deleteBtn.innerHTML = originalText;
    }
  }

  performDefaultDelete() {
    // Implement default delete logic here
    // This would typically be an API call
    console.log(`Deleting ${this.type} with ID: ${this.itemId}`);

    // For now, just hide modal and show success
    this.hide();
    this.showSuccess(
      `${
        this.type.charAt(0).toUpperCase() + this.type.slice(1)
      } berhasil dihapus!`
    );
  }

  showSuccess(message) {
    // Show success alert (you can use your existing alert system)
    if (window.showAlert) {
      window.showAlert(message, "success");
    } else {
      alert(message);
    }
  }

  showError(message) {
    // Show error alert
    if (window.showAlert) {
      window.showAlert(message, "error");
    } else {
      alert(message);
    }
  }

  hide() {
    this.modal.classList.remove("show");
    document.body.style.overflow = "";

    // Reset form
    const confirmInput = document.getElementById("confirmText");
    if (confirmInput) {
      confirmInput.value = "";
    }

    // Reset states
    this.callback = null;
    this.itemId = null;
    this.itemName = "";
    this.additionalInfo = "";
    this.requireConfirmation = false;
    this.confirmationText = "";
  }

  handleOutsideClick(event) {
    if (event.target === this.modal) {
      this.hide();
    }
  }

  handleEscapeKey(event) {
    if (event.key === "Escape" && this.modal.classList.contains("show")) {
      this.hide();
    }
  }

  // Static method for easy use
  static show(options) {
    if (!DeleteModal.instance) {
      DeleteModal.instance = new DeleteModal();
    }
    DeleteModal.instance.show(options);
  }

  static hide() {
    if (DeleteModal.instance) {
      DeleteModal.instance.hide();
    }
  }
}

// Add shake animation
if (!document.querySelector("#modalAnimations")) {
  const style = document.createElement("style");
  style.id = "modalAnimations";
  style.textContent = `
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
  `;
  document.head.appendChild(style);
}

// Make it globally available
window.DeleteModal = DeleteModal;

// Shortcut function
window.confirmDelete = function (options) {
  DeleteModal.show(options);
};
