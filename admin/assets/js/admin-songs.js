document.addEventListener("DOMContentLoaded", function () {
  console.log("Admin Songs JS Loaded");

  // FIX ALIGNMENT ON LOAD
  fixAllInputAlignment();

  // Initialize searchable selects
  initializeSearchableSelects();

  // Initialize other components
  initializeSongsForm();
  initializeSongsUpload();
  initializeDurationInputs();
});

// FIX ALIGNMENT FUNCTIONS

function fixAllInputAlignment() {
  console.log("Fixing input alignment...");

  // List semua input yang perlu di-fix
  const inputsToFix = [
    "title",
    "artistSearch",
    "albumSearch",
    "genreSearch",
    "duration_minutes",
    "duration_seconds",
  ];

  inputsToFix.forEach((id) => {
    const input = document.getElementById(id);
    if (input) {
      // Force left alignment untuk semua kecuali duration
      if (id.includes("duration")) {
        // Durasi tetap center untuk angka
        input.style.textAlign = "center";
      } else {
        // Semua lainnya left
        input.style.textAlign = "left";
        input.style.paddingLeft = "15px";
      }

      // Force width 100%
      input.style.width = "100%";
      input.style.boxSizing = "border-box";

      console.log(`Fixed alignment for: ${id}`);
    }
  });

  // Special force untuk search inputs
  forceSearchInputsAlignment();
}

function forceSearchInputsAlignment() {
  // Force dengan selector yang lebih kuat
  const style = document.createElement("style");
  style.textContent = `
    #artistSearch, 
    #albumSearch, 
    #genreSearch {
      text-align: left !important;
      padding-left: 15px !important;
    }
    
    .searchable-select input[type="text"] {
      text-align: left !important;
      padding-left: 15px !important;
    }
  `;
  document.head.appendChild(style);
}

// SEARCHABLE SELECT FUNCTIONS

function initializeSearchableSelects() {
  // Initialize each searchable select
  setupSearchableSelect("artistSearch", "artist_id");
  setupSearchableSelect("albumSearch", "album_id");
  setupSearchableSelect("genreSearch", "genre_id");
}

function setupSearchableSelect(searchInputId, selectId) {
  const searchInput = document.getElementById(searchInputId);
  const select = document.getElementById(selectId);

  if (!searchInput || !select) return;

  // Ensure alignment
  searchInput.style.textAlign = "left";
  searchInput.style.paddingLeft = "15px";

  // Show dropdown on focus
  searchInput.addEventListener("focus", function () {
    select.style.display = "block";
    select.style.width = this.offsetWidth + "px";
  });

  // Hide dropdown on blur
  searchInput.addEventListener("blur", function () {
    setTimeout(() => {
      select.style.display = "none";
    }, 200);
  });

  // Filter options on input
  searchInput.addEventListener("input", function () {
    const searchTerm = this.value.toLowerCase();
    const options = select.options;

    for (let i = 0; i < options.length; i++) {
      const option = options[i];
      const text = option.text.toLowerCase();

      if (text.includes(searchTerm) || option.value === "") {
        option.style.display = "";
      } else {
        option.style.display = "none";
      }
    }
  });

  // Update search input when option is selected
  select.addEventListener("change", function () {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption && selectedOption.value) {
      searchInput.value = selectedOption.text;
    }
  });

  // Allow clicking on options
  select.addEventListener("mousedown", function (e) {
    if (e.target.tagName === "OPTION") {
      this.value = e.target.value;
      this.dispatchEvent(new Event("change"));
    }
  });
}

// FORM VALIDATION

function initializeSongsForm() {
  const form = document.getElementById("songForm");
  if (!form) return;

  form.addEventListener("submit", function (e) {
    // Basic validation
    const title = document.getElementById("title").value.trim();
    const artistId = document.getElementById("artist_id").value;
    const durationMinutes = document.getElementById("duration_minutes").value;
    const durationSeconds = document.getElementById("duration_seconds").value;

    if (!title) {
      e.preventDefault();
      showAlert("Judul lagu wajib diisi", "error");
      return false;
    }

    if (!artistId) {
      e.preventDefault();
      showAlert("Artis wajib dipilih", "error");
      return false;
    }

    // Validate duration
    const minutes = parseInt(durationMinutes) || 0;
    const seconds = parseInt(durationSeconds) || 0;

    if (minutes < 0 || seconds < 0 || seconds > 59) {
      e.preventDefault();
      showAlert("Format durasi tidak valid. Detik harus antara 0-59", "error");
      return false;
    }

    if (minutes === 0 && seconds === 0) {
      e.preventDefault();
      showAlert("Durasi lagu harus lebih dari 0 detik", "error");
      return false;
    }

    return true;
  });
}

// FILE UPLOAD WITH AUTO DURATION

function initializeSongsUpload() {
  initializeAudioUpload();
  initializeCoverUpload();
}

function initializeAudioUpload() {
  const audioUpload = document.getElementById("audioUpload");
  const audioFile = document.getElementById("audio_file");
  const audioInfo = document.querySelector(".audio-file-info");

  if (!audioUpload || !audioFile || !audioInfo) {
    console.warn("Audio upload elements not found");
    return;
  }

  console.log("Initializing audio upload with auto-duration...");

  // Handle click on upload area
  audioUpload.addEventListener("click", function (e) {
    if (e.target !== audioFile) {
      audioFile.click();
    }
  });

  // Handle drag and drop
  audioUpload.addEventListener("dragover", function (e) {
    e.preventDefault();
    audioUpload.classList.add("dragover");
  });

  audioUpload.addEventListener("dragleave", function () {
    audioUpload.classList.remove("dragover");
  });

  audioUpload.addEventListener("drop", function (e) {
    e.preventDefault();
    audioUpload.classList.remove("dragover");

    if (e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];

      if (!isValidAudioFile(file.name)) {
        showAlert(
          "Format file audio tidak didukung. Gunakan MP3, WAV, OGG, atau M4A",
          "error"
        );
        return;
      }

      if (file.size > 20 * 1024 * 1024) {
        showAlert("Ukuran file audio terlalu besar. Maksimal 20MB", "error");
        return;
      }

      // Create a new DataTransfer object and add the file
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      audioFile.files = dataTransfer.files;

      updateAudioInfo();
      extractAndSetAudioDuration(file);
    }
  });

  // Handle file selection
  audioFile.addEventListener("change", function () {
    if (audioFile.files.length > 0) {
      const file = audioFile.files[0];

      if (!isValidAudioFile(file.name)) {
        showAlert(
          "Format file audio tidak didukung. Gunakan MP3, WAV, OGG, atau M4A",
          "error"
        );
        audioFile.value = "";
        updateAudioInfo();
        return;
      }

      if (file.size > 20 * 1024 * 1024) {
        showAlert("Ukuran file audio terlalu besar. Maksimal 20MB", "error");
        audioFile.value = "";
        updateAudioInfo();
        return;
      }

      updateAudioInfo();
      extractAndSetAudioDuration(file);
    } else {
      updateAudioInfo();
    }
  });

  function updateAudioInfo() {
    if (audioFile.files.length > 0) {
      const file = audioFile.files[0];
      const fileSize = (file.size / 1024 / 1024).toFixed(2);

      audioInfo.innerHTML = `
        <div class="audio-details">
          <strong>${file.name}</strong>
          <div class="audio-meta">
            <span>${fileSize} MB</span>
          </div>
        </div>
      `;
      audioInfo.style.color = "var(--success)";
    } else {
      audioInfo.innerHTML = "Belum ada file yang dipilih";
      audioInfo.style.color = "var(--gray)";

      // Remove audio preview
      const existingPreview = document.querySelector(".audio-preview");
      if (existingPreview) {
        existingPreview.remove();
      }
    }
  }

  function extractAndSetAudioDuration(file) {
    console.log("Extracting duration from audio file...");

    const audioURL = URL.createObjectURL(file);
    const audio = new Audio();

    // Show loading message
    const audioMeta = audioInfo.querySelector(".audio-meta");
    if (audioMeta) {
      audioMeta.innerHTML += `<span>•</span><span>Mengekstrak durasi...</span>`;
    }

    audio.src = audioURL;

    audio.addEventListener("loadedmetadata", function () {
      const duration = audio.duration; // in seconds
      console.log("Audio duration:", duration, "seconds");

      // Format duration for display
      const formattedDuration = formatTime(duration);

      // Update duration in info
      if (audioMeta) {
        audioMeta.innerHTML = `<span>${(file.size / 1024 / 1024).toFixed(
          2
        )} MB</span><span>•</span><span>${formattedDuration}</span>`;
      }

      // Auto-fill duration inputs
      const minutes = Math.floor(duration / 60);
      const seconds = Math.floor(duration % 60);

      document.getElementById("duration_minutes").value = minutes;
      document.getElementById("duration_seconds").value = seconds;

      console.log(`Auto-filled duration: ${minutes}m ${seconds}s`);

      // Create audio preview
      createAudioPreview(audioURL, file, formattedDuration);

      // Clean up URL object
      setTimeout(() => URL.revokeObjectURL(audioURL), 1000);
    });

    audio.addEventListener("error", function (e) {
      console.error("Error loading audio:", e);
      showAlert(
        "Gagal mengekstrak durasi dari file audio. Mohon isi durasi secara manual.",
        "error"
      );

      if (audioMeta) {
        audioMeta.innerHTML = `<span>${(file.size / 1024 / 1024).toFixed(
          2
        )} MB</span>`;
      }
    });

    // Set timeout in case metadata never loads
    setTimeout(() => {
      if (!audio.duration || audio.duration === Infinity) {
        console.warn("Audio metadata not loaded within timeout");
        showAlert(
          "Tidak dapat membaca durasi audio. Mohon isi durasi secara manual.",
          "warning"
        );
      }
    }, 5000);
  }

  function createAudioPreview(audioURL, file, duration) {
    // Remove existing preview
    const existingPreview = document.querySelector(".audio-preview");
    if (existingPreview) {
      existingPreview.remove();
    }

    // Create preview player
    const preview = document.createElement("div");
    preview.className = "audio-preview";
    preview.innerHTML = `
      <div style="margin: 15px 0 8px 0; font-weight: 600; color: var(--dark);">
        Preview Audio:
      </div>
      <audio controls style="width: 100%; max-width: 400px; margin: 10px 0;">
        <source src="${audioURL}" type="${file.type}">
        Browser Anda tidak mendukung pemutar audio.
      </audio>
      <div style="font-size: 0.8rem; color: var(--gray); margin-top: 5px;">
        Durasi: ${duration}
      </div>
    `;

    audioUpload.parentNode.insertBefore(preview, audioUpload.nextSibling);
  }

  function isValidAudioFile(filename) {
    const allowedExtensions = ["mp3", "wav", "ogg", "m4a", "flac", "aac"];
    const extension = filename.toLowerCase().split(".").pop();
    return allowedExtensions.includes(extension);
  }
}

function initializeCoverUpload() {
  const coverImageUpload = document.getElementById("coverImageUpload");
  const fileInput = document.getElementById("cover_image");
  const fileInfo = coverImageUpload
    ? coverImageUpload.querySelector(".file-info")
    : null;

  if (!coverImageUpload || !fileInput || !fileInfo) {
    console.warn("Cover upload elements not found");
    return;
  }

  console.log("Initializing cover upload...");

  // Handle click on upload area
  coverImageUpload.addEventListener("click", function (e) {
    if (e.target !== fileInput) {
      fileInput.click();
    }
  });

  // Handle drag and drop
  coverImageUpload.addEventListener("dragover", function (e) {
    e.preventDefault();
    coverImageUpload.classList.add("dragover");
  });

  coverImageUpload.addEventListener("dragleave", function () {
    coverImageUpload.classList.remove("dragover");
  });

  coverImageUpload.addEventListener("drop", function (e) {
    e.preventDefault();
    coverImageUpload.classList.remove("dragover");

    if (e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];

      if (!isValidImageFile(file.name)) {
        showAlert(
          "Format file tidak didukung. Gunakan JPG, PNG, atau WEBP",
          "error"
        );
        return;
      }

      if (file.size > 5 * 1024 * 1024) {
        showAlert("Ukuran file terlalu besar. Maksimal 5MB", "error");
        return;
      }

      // Create a new DataTransfer object and add the file
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      fileInput.files = dataTransfer.files;

      updateFileInfo();
      previewImage(file);
    }
  });

  // Handle file selection
  fileInput.addEventListener("change", function () {
    if (fileInput.files.length > 0) {
      const file = fileInput.files[0];

      if (!isValidImageFile(file.name)) {
        showAlert(
          "Format file tidak didukung. Gunakan JPG, PNG, atau WEBP",
          "error"
        );
        fileInput.value = "";
        updateFileInfo();
        return;
      }

      if (file.size > 5 * 1024 * 1024) {
        showAlert("Ukuran file terlalu besar. Maksimal 5MB", "error");
        fileInput.value = "";
        updateFileInfo();
        return;
      }

      updateFileInfo();
      previewImage(file);
    } else {
      updateFileInfo();
    }
  });

  function updateFileInfo() {
    if (fileInput.files.length > 0) {
      const file = fileInput.files[0];
      const fileSize = (file.size / 1024 / 1024).toFixed(2);
      fileInfo.textContent = `${file.name} (${fileSize} MB)`;
      fileInfo.style.color = "var(--success)";
    } else {
      fileInfo.textContent = "Belum ada file yang dipilih";
      fileInfo.style.color = "var(--gray)";

      // Remove preview if no file
      const existingPreview = coverImageUpload.querySelector(".image-preview");
      if (existingPreview) {
        existingPreview.remove();
      }
    }
  }

  function previewImage(file) {
    const reader = new FileReader();
    reader.onload = function (e) {
      // Remove existing preview
      const existingPreview = coverImageUpload.querySelector(".image-preview");
      if (existingPreview) {
        existingPreview.remove();
      }

      // Create new preview
      const preview = document.createElement("div");
      preview.className = "image-preview";
      preview.innerHTML = `
        <p style="margin: 0 0 8px 0; font-weight: 600; color: var(--dark);">
          Preview:
        </p>
        <img src="${e.target.result}" alt="Preview" style="max-width: 150px; max-height: 150px; border-radius: 8px; border: 2px solid var(--border);">
      `;
      coverImageUpload.appendChild(preview);
    };
    reader.readAsDataURL(file);
  }

  function isValidImageFile(filename) {
    const allowedExtensions = ["jpg", "jpeg", "png", "webp", "gif"];
    const extension = filename.toLowerCase().split(".").pop();
    return allowedExtensions.includes(extension);
  }
}

// DURATION INPUTS

function initializeDurationInputs() {
  const minutesInput = document.getElementById("duration_minutes");
  const secondsInput = document.getElementById("duration_seconds");

  if (minutesInput) {
    minutesInput.addEventListener("input", function () {
      let value = this.value.replace(/\D/g, "");
      if (value > 59) value = 59;
      this.value = value;
    });
  }

  if (secondsInput) {
    secondsInput.addEventListener("input", function () {
      let value = this.value.replace(/\D/g, "");
      if (value > 59) value = 59;
      this.value = value;
    });
  }

  // Add manual duration calculation helper
  addDurationCalculator();
}

function addDurationCalculator() {
  // Create duration calculator button if not exists
  const durationGroup = document.querySelector(".duration-inputs");
  if (!durationGroup) return;

  // Check if calculator already exists

  durationGroup.parentNode.insertBefore(calculator, durationGroup.nextSibling);

  // Add calculator functionality
  document
    .getElementById("calculateDuration")
    .addEventListener("click", function () {
      const minutes = prompt("Masukkan menit:", "0");
      const seconds = prompt("Masukkan detik:", "0");

      if (minutes !== null && seconds !== null) {
        const min = parseInt(minutes) || 0;
        const sec = parseInt(seconds) || 0;

        if (min < 0 || sec < 0 || sec > 59) {
          showAlert(
            "Format durasi tidak valid. Detik harus antara 0-59",
            "error"
          );
          return;
        }

        document.getElementById("duration_minutes").value = min;
        document.getElementById("duration_seconds").value = sec;

        showAlert(`Durasi diatur: ${min}m ${sec}s`, "success");
      }
    });
}

// HELPER FUNCTIONS

function formatTime(seconds) {
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = Math.floor(seconds % 60);
  return `${minutes}:${remainingSeconds.toString().padStart(2, "0")}`;
}

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

// DELETE CONFIRMATION

function confirmDelete(songId) {
  if (confirm("Apakah Anda yakin ingin menghapus lagu ini?")) {
    window.location.href = `songs.php?action=delete&id=${songId}`;
  }
}

// Make function globally available
window.confirmDelete = confirmDelete;
