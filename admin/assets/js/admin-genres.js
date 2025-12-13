document.addEventListener("DOMContentLoaded", function () {
  console.log("Admin Genres JS Loaded");

  // Initialize form validation
  initializeGenresForm();

  // Initialize color picker
  initializeColorPicker();

  // Auto-capitalize genre name
  initializeAutoCapitalize();

  // Initialize delete confirmations
  initializeDeleteConfirmations();
});

// FORM VALIDATION

function initializeGenresForm() {
  const form = document.getElementById("genreForm");
  if (!form) return;

  form.addEventListener("submit", function (e) {
    // Basic validation
    const name = document.getElementById("name").value.trim();
    const colorHex = document.getElementById("color_hex").value.trim();

    if (!name) {
      e.preventDefault();
      showAlert("Nama genre wajib diisi", "error");
      document.getElementById("name").focus();
      return false;
    }

    // Validate color format
    if (colorHex && !isValidHexColor(colorHex)) {
      e.preventDefault();
      showAlert(
        "Format warna tidak valid. Gunakan format hex: #RRGGBB",
        "error"
      );
      document.getElementById("color_hex").focus();
      return false;
    }

    // Validate name length
    if (name.length > 50) {
      e.preventDefault();
      showAlert("Nama genre terlalu panjang. Maksimal 50 karakter", "error");
      return false;
    }

    // Validate description length
    const description = document.getElementById("description").value.trim();
    if (description.length > 500) {
      e.preventDefault();
      showAlert("Deskripsi terlalu panjang. Maksimal 500 karakter", "error");
      return false;
    }

    return true;
  });

  // Real-time validation for name
  const nameInput = document.getElementById("name");
  if (nameInput) {
    nameInput.addEventListener("input", function () {
      if (this.value.length > 50) {
        this.setCustomValidity("Maksimal 50 karakter");
        showAlert("Nama genre maksimal 50 karakter", "warning");
      } else {
        this.setCustomValidity("");
      }
    });
  }

  // Real-time validation for description
}

// COLOR PICKER

function initializeColorPicker() {
  const colorInput = document.getElementById("color");
  const colorHex = document.getElementById("color_hex");

  if (!colorInput || !colorHex) return;

  // Sync color picker with hex input
  colorInput.addEventListener("input", function () {
    colorHex.value = this.value.toUpperCase();
    updateColorPreview(this.value);
  });

  // Sync hex input with color picker
  colorHex.addEventListener("input", function () {
    const value = this.value;

    // Auto-add # if missing
    if (value && !value.startsWith("#")) {
      this.value = "#" + value;
    }

    // Convert to uppercase
    this.value = this.value.toUpperCase();

    // Validate and update
    if (isValidHexColor(this.value)) {
      colorInput.value = this.value;
      updateColorPreview(this.value);
    }
  });

  // Validate hex input on blur
  colorHex.addEventListener("blur", function () {
    const value = this.value;

    if (value && !isValidHexColor(value)) {
      this.setCustomValidity("Format warna tidak valid. Gunakan: #RRGGBB");
      this.style.borderColor = "var(--danger)";
      showAlert("Format warna tidak valid. Gunakan: #RRGGBB", "error");
    } else {
      this.setCustomValidity("");
      this.style.borderColor = "";
    }
  });

  // Create color preview if not exists
  createColorPreview();

  // Initial update
  updateColorPreview(colorInput.value);
}

function createColorPreview() {
  const colorGroup = document.querySelector(".color-picker-container");
  if (!colorGroup) return;

  // Check if preview already exists
  if (document.getElementById("colorPreview")) return;

  const preview = document.createElement("div");
  preview.id = "colorPreview";
  preview.className = "color-preview";
  preview.style.marginTop = "10px";
  preview.style.width = "100%";
  preview.style.height = "40px";
  preview.style.borderRadius = "8px";
  preview.style.border = "2px solid var(--border)";
  preview.style.transition = "background-color 0.3s ease";

  // Insert after color picker container
  colorGroup.parentNode.appendChild(preview);

  // Add preview label
  const previewLabel = document.createElement("div");
  previewLabel.className = "form-help";
  previewLabel.textContent = "Preview warna:";
  previewLabel.style.marginTop = "5px";
  previewLabel.style.marginBottom = "5px";
  preview.parentNode.insertBefore(previewLabel, preview);
}

function updateColorPreview(color) {
  const preview = document.getElementById("colorPreview");
  if (preview) {
    preview.style.backgroundColor = color;

    // Add color name if possible
    const colorName = getColorName(color);
    if (colorName) {
      preview.textContent = colorName;
      preview.style.color = getContrastColor(color);
      preview.style.display = "flex";
      preview.style.alignItems = "center";
      preview.style.justifyContent = "center";
      preview.style.fontSize = "0.8rem";
      preview.style.fontWeight = "600";
    } else {
      preview.textContent = "";
    }
  }
}

function isValidHexColor(color) {
  const hexRegex = /^#([0-9A-F]{3}){1,2}$/i;
  return hexRegex.test(color);
}

function getColorName(hex) {
  const colors = {
    "#FF0000": "Merah",
    "#00FF00": "Hijau",
    "#0000FF": "Biru",
    "#FFFF00": "Kuning",
    "#FF00FF": "Magenta",
    "#00FFFF": "Cyan",
    "#FFA500": "Oranye",
    "#800080": "Ungu",
    "#008000": "Hijau Gelap",
    "#000080": "Biru Laut",
    "#800000": "Merah Marun",
    "#808000": "Zaitun",
    "#008080": "Teal",
    "#C0C0C0": "Perak",
    "#808080": "Abu-abu",
    "#000000": "Hitam",
    "#FFFFFF": "Putih",
    "#667EEA": "Biru Ungu",
    "#764BA2": "Ungu",
    "#F093FB": "Pink",
    "#F5576C": "Merah Muda",
    "#4FACFE": "Biru Terang",
    "#00F2FE": "Biru Cyan",
    "#43E97B": "Hijau Terang",
    "#38F9D7": "Hijau Mint",
  };

  return colors[hex.toUpperCase()] || "";
}

function getContrastColor(hexcolor) {
  // Remove # if present
  hexcolor = hexcolor.replace("#", "");

  // Convert to RGB
  const r = parseInt(hexcolor.substr(0, 2), 16);
  const g = parseInt(hexcolor.substr(2, 2), 16);
  const b = parseInt(hexcolor.substr(4, 2), 16);

  // Calculate luminance
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

  // Return black or white depending on luminance
  return luminance > 0.5 ? "#000000" : "#FFFFFF";
}

// AUTO CAPITALIZE

function initializeAutoCapitalize() {
  const nameInput = document.getElementById("name");
  if (!nameInput) return;

  // Auto-capitalize on blur
  nameInput.addEventListener("blur", function () {
    if (this.value.trim()) {
      // Capitalize first letter of each word
      this.value = this.value
        .split(" ")
        .map(
          (word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
        )
        .join(" ");
    }
  });

  // Suggest genre names
  nameInput.addEventListener("input", function () {
    suggestGenreNames(this.value);
  });
}

function suggestGenreNames(input) {
  if (!input || input.length < 2) return;

  const commonGenres = [
    "Pop",
    "Rock",
    "Jazz",
    "Hip Hop",
    "R&B",
    "Electronic",
    "Classical",
    "Country",
    "Reggae",
    "Metal",
    "Blues",
    "Folk",
    "Disco",
    "Soul",
    "Funk",
    "Punk",
    "Alternative",
    "Indie",
    "K-Pop",
    "J-Pop",
    "Latin",
    "Reggaeton",
    "EDM",
    "Techno",
    "House",
    "Trance",
    "Dubstep",
    "Gospel",
    "Rap",
    "Trap",
    "Lo-Fi",
    "Ambient",
    "Soundtrack",
    "Instrumental",
    "Acoustic",
  ];

  const suggestions = commonGenres.filter((genre) =>
    genre.toLowerCase().includes(input.toLowerCase())
  );

  if (suggestions.length > 0 && !document.getElementById("genreSuggestions")) {
    createGenreSuggestions(suggestions);
  } else if (document.getElementById("genreSuggestions")) {
    updateGenreSuggestions(suggestions);
  }
}

function createGenreSuggestions(suggestions) {
  const container = document.createElement("div");
  container.id = "genreSuggestions";
  container.className = "genre-suggestions";
  container.style.cssText = `
    margin-top: 5px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: white;
    max-height: 200px;
    overflow-y: auto;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    z-index: 1000;
  `;

  suggestions.forEach((genre) => {
    const suggestion = document.createElement("div");
    suggestion.className = "genre-suggestion";
    suggestion.textContent = genre;
    suggestion.style.cssText = `
      padding: 10px 15px;
      cursor: pointer;
      transition: background 0.3s ease;
      border-bottom: 1px solid var(--border-light);
    `;

    suggestion.addEventListener("mouseover", function () {
      this.style.background = "var(--light)";
    });

    suggestion.addEventListener("mouseout", function () {
      this.style.background = "";
    });

    suggestion.addEventListener("click", function () {
      document.getElementById("name").value = genre;
      container.remove();
    });

    container.appendChild(suggestion);
  });

  const nameInput = document.getElementById("name");
  nameInput.parentNode.appendChild(container);

  // Close suggestions when clicking outside
  document.addEventListener("click", function (e) {
    if (!container.contains(e.target) && e.target !== nameInput) {
      container.remove();
    }
  });
}

function updateGenreSuggestions(suggestions) {
  const container = document.getElementById("genreSuggestions");
  if (!container) return;

  container.innerHTML = "";

  suggestions.forEach((genre) => {
    const suggestion = document.createElement("div");
    suggestion.className = "genre-suggestion";
    suggestion.textContent = genre;
    suggestion.style.cssText = `
      padding: 10px 15px;
      cursor: pointer;
      transition: background 0.3s ease;
      border-bottom: 1px solid var(--border-light);
    `;

    suggestion.addEventListener("mouseover", function () {
      this.style.background = "var(--light)";
    });

    suggestion.addEventListener("mouseout", function () {
      this.style.background = "";
    });

    suggestion.addEventListener("click", function () {
      document.getElementById("name").value = genre;
      container.remove();
    });

    container.appendChild(suggestion);
  });
}

// DELETE CONFIRMATIONS

function initializeDeleteConfirmations() {
  // This will be called by inline onclick events
}

function confirmDelete(genreId, genreName = null) {
  let message = "Apakah Anda yakin ingin menghapus genre ini?";

  if (genreName) {
    message = `Apakah Anda yakin ingin menghapus genre "${genreName}"?`;
  }

  if (confirm(message)) {
    window.location.href = `genres.php?action=delete&id=${genreId}`;
  }
}

// HELPER FUNCTIONS

function showAlert(message, type = "info") {
  // Remove existing alerts
  const existingAlerts = document.querySelectorAll(".temp-alert");
  existingAlerts.forEach((alert) => alert.remove());

  // Create alert element
  const alertDiv = document.createElement("div");
  alertDiv.className = `temp-alert alert alert-${type}`;
  alertDiv.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 18px;
    border-radius: 8px;
    z-index: 9999;
    animation: slideIn 0.3s ease;
    font-size: 0.9rem;
    max-width: 300px;
  `;

  const icon =
    type === "success"
      ? "check-circle"
      : type === "error"
      ? "exclamation-circle"
      : type === "warning"
      ? "exclamation-triangle"
      : "info-circle";

  alertDiv.innerHTML = `
    <i class="fas fa-${icon}" style="margin-right: 8px;"></i>
    ${message}
  `;

  // Add to body
  document.body.appendChild(alertDiv);

  // Add CSS animation if not exists
  if (!document.querySelector("#alert-animations")) {
    const style = document.createElement("style");
    style.id = "alert-animations";
    style.textContent = `
      @keyframes slideIn {
        from {
          transform: translateX(100%);
          opacity: 0;
        }
        to {
          transform: translateX(0);
          opacity: 1;
        }
      }
      @keyframes slideOut {
        from {
          transform: translateX(0);
          opacity: 1;
        }
        to {
          transform: translateX(100%);
          opacity: 0;
        }
      }
    `;
    document.head.appendChild(style);
  }

  // Remove after 5 seconds
  setTimeout(() => {
    alertDiv.style.animation = "slideOut 0.3s ease";
    setTimeout(() => {
      alertDiv.remove();
    }, 300);
  }, 5000);
}

// MAKE FUNCTIONS GLOBALLY AVAILABLE

window.confirmDelete = confirmDelete;
