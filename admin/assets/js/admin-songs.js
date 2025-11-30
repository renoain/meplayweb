// Songs specific JavaScript
document.addEventListener("DOMContentLoaded", function () {
  initializeSongsForm();
  initializeSongsUpload();
  initializeSearchableSelects();
  initializeDurationInputs();
});

function initializeSongsForm() {
  const songForm = document.getElementById("songForm");

  if (songForm) {
    songForm.addEventListener("submit", function (e) {
      const title = document.getElementById("title").value.trim();
      const artistSelect = document.getElementById("artist_id");
      const artistSearch = document.getElementById("artistSearch");
      const durationMinutes = document.getElementById("duration_minutes");
      const durationSeconds = document.getElementById("duration_seconds");
      const audioFile = document.getElementById("audio_file");

      // Validate title
      if (!title) {
        e.preventDefault();
        showAlert("Judul lagu wajib diisi!", "error");
        document.getElementById("title").focus();
        return false;
      }

      // Validate artist - REQUIRED
      if (!artistSelect.value) {
        e.preventDefault();
        showAlert("Artis wajib dipilih!", "error");
        artistSearch.focus();
        return false;
      }

      // Validate duration
      const minutes = parseInt(durationMinutes.value) || 0;
      const seconds = parseInt(durationSeconds.value) || 0;

      if (minutes < 0 || seconds < 0 || seconds > 59) {
        e.preventDefault();
        showAlert(
          "Format durasi tidak valid! Detik harus antara 0-59.",
          "error"
        );
        durationSeconds.focus();
        return false;
      }

      const totalDuration = minutes * 60 + seconds;
      if (totalDuration <= 0) {
        e.preventDefault();
        showAlert("Durasi lagu harus lebih dari 0 detik!", "error");
        durationMinutes.focus();
        return false;
      }

      // Validate audio file for new songs
      if (!window.songEditMode && (!audioFile || !audioFile.files.length)) {
        e.preventDefault();
        showAlert("File audio wajib diupload!", "error");
        return false;
      }

      return true;
    });
  }

  // Set edit mode flag
  window.songEditMode = !!document.querySelector('input[name="edit_song"]');
}

function initializeSongsUpload() {
  initializeAudioUpload();
  initializeCoverUpload();
}

function initializeAudioUpload() {
  const audioUpload = document.getElementById("audioUpload");
  const audioFile = document.getElementById("audio_file");
  const audioInfo = document.querySelector(".audio-file-info");

  if (!audioUpload || !audioFile || !audioInfo) return;

  // Prevent multiple event listeners
  audioUpload.removeEventListener("click", handleAudioUploadClick);
  audioUpload.removeEventListener("dragover", handleAudioDragOver);
  audioUpload.removeEventListener("dragleave", handleAudioDragLeave);
  audioUpload.removeEventListener("drop", handleAudioDrop);
  audioFile.removeEventListener("change", handleAudioFileChange);

  // Add event listeners
  audioUpload.addEventListener("click", handleAudioUploadClick);
  audioUpload.addEventListener("dragover", handleAudioDragOver);
  audioUpload.addEventListener("dragleave", handleAudioDragLeave);
  audioUpload.addEventListener("drop", handleAudioDrop);
  audioFile.addEventListener("change", handleAudioFileChange);

  function handleAudioUploadClick(e) {
    if (e.target !== audioFile && !e.target.closest('input[type="file"]')) {
      audioFile.click();
    }
  }

  function handleAudioDragOver(e) {
    e.preventDefault();
    audioUpload.classList.add("dragover");
  }

  function handleAudioDragLeave() {
    audioUpload.classList.remove("dragover");
  }

  function handleAudioDrop(e) {
    e.preventDefault();
    audioUpload.classList.remove("dragover");

    if (e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];

      if (!isValidAudioFile(file.name)) {
        showAlert(
          "Format file audio tidak didukung! Gunakan MP3, WAV, atau OGG.",
          "error"
        );
        return;
      }

      if (file.size > 20 * 1024 * 1024) {
        showAlert("Ukuran file audio terlalu besar! Maksimal 20MB.", "error");
        return;
      }

      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      audioFile.files = dataTransfer.files;

      updateAudioInfo();
      loadAudioPreview(file);
    }
  }

  function handleAudioFileChange() {
    if (audioFile.files.length > 0) {
      const file = audioFile.files[0];

      if (!isValidAudioFile(file.name)) {
        showAlert(
          "Format file audio tidak didukung! Gunakan MP3, WAV, atau OGG.",
          "error"
        );
        audioFile.value = "";
        updateAudioInfo();
        return;
      }

      if (file.size > 20 * 1024 * 1024) {
        showAlert("Ukuran file audio terlalu besar! Maksimal 20MB.", "error");
        audioFile.value = "";
        updateAudioInfo();
        return;
      }

      updateAudioInfo();
      loadAudioPreview(file);
    } else {
      updateAudioInfo();
    }
  }

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

  function loadAudioPreview(file) {
    const audioURL = URL.createObjectURL(file);

    // Remove existing preview
    const existingPreview = document.querySelector(".audio-preview");
    if (existingPreview) {
      existingPreview.remove();
    }

    // Create audio element to get duration
    const audio = new Audio();
    audio.src = audioURL;

    audio.addEventListener("loadedmetadata", function () {
      const duration = formatTime(audio.duration);

      // Update duration in info
      const audioMeta = audioInfo.querySelector(".audio-meta");
      if (audioMeta) {
        audioMeta.innerHTML += `<span>•</span><span>${duration}</span>`;
      }

      // Auto-fill duration inputs
      const minutes = Math.floor(audio.duration / 60);
      const seconds = Math.floor(audio.duration % 60);

      document.getElementById("duration_minutes").value = minutes;
      document.getElementById("duration_seconds").value = seconds;

      // Create preview player
      const preview = document.createElement("div");
      preview.className = "audio-preview";
      preview.innerHTML = `
                <div style="margin: 15px 0 8px 0; font-weight: 600; color: var(--dark);">Preview Audio:</div>
                <audio controls style="width: 100%; max-width: 400px; margin: 10px 0;">
                    <source src="${audioURL}" type="${file.type}">
                    Browser Anda tidak mendukung pemutar audio.
                </audio>
                <div style="font-size: 0.8rem; color: var(--gray); margin-top: 5px;">
                    Durasi: ${duration}
                </div>
            `;

      audioUpload.parentNode.insertBefore(preview, audioUpload.nextSibling);
    });

    audio.addEventListener("error", function () {
      showAlert("Gagal memuat preview audio.", "error");
    });
  }

  function isValidAudioFile(filename) {
    const allowedExtensions = ["mp3", "wav", "ogg", "m4a"];
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

  if (!coverImageUpload || !fileInput || !fileInfo) return;

  // Prevent multiple event listeners
  coverImageUpload.removeEventListener("click", handleUploadClick);
  coverImageUpload.removeEventListener("dragover", handleDragOver);
  coverImageUpload.removeEventListener("dragleave", handleDragLeave);
  coverImageUpload.removeEventListener("drop", handleDrop);
  fileInput.removeEventListener("change", handleFileChange);

  // Add event listeners
  coverImageUpload.addEventListener("click", handleUploadClick);
  coverImageUpload.addEventListener("dragover", handleDragOver);
  coverImageUpload.addEventListener("dragleave", handleDragLeave);
  coverImageUpload.addEventListener("drop", handleDrop);
  fileInput.addEventListener("change", handleFileChange);

  function handleUploadClick(e) {
    if (e.target !== fileInput && !e.target.closest('input[type="file"]')) {
      fileInput.click();
    }
  }

  function handleDragOver(e) {
    e.preventDefault();
    coverImageUpload.classList.add("dragover");
  }

  function handleDragLeave() {
    coverImageUpload.classList.remove("dragover");
  }

  function handleDrop(e) {
    e.preventDefault();
    coverImageUpload.classList.remove("dragover");

    if (e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];

      if (!isValidImageFile(file.name)) {
        showAlert(
          "Format file tidak didukung! Gunakan JPG, PNG, atau WEBP.",
          "error"
        );
        return;
      }

      if (file.size > 5 * 1024 * 1024) {
        showAlert("Ukuran file terlalu besar! Maksimal 5MB.", "error");
        return;
      }

      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      fileInput.files = dataTransfer.files;

      updateFileInfo();
      previewImage(file);
    }
  }

  function handleFileChange() {
    if (fileInput.files.length > 0) {
      const file = fileInput.files[0];

      if (!isValidImageFile(file.name)) {
        showAlert(
          "Format file tidak didukung! Gunakan JPG, PNG, atau WEBP.",
          "error"
        );
        fileInput.value = "";
        updateFileInfo();
        return;
      }

      if (file.size > 5 * 1024 * 1024) {
        showAlert("Ukuran file terlalu besar! Maksimal 5MB.", "error");
        fileInput.value = "";
        updateFileInfo();
        return;
      }

      updateFileInfo();
      previewImage(file);
    } else {
      updateFileInfo();
    }
  }

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
                <p style="margin: 0 0 8px 0; font-weight: 600; color: var(--dark);">Preview:</p>
                <img src="${e.target.result}" alt="Preview" style="max-width: 150px; max-height: 150px; border-radius: 8px; border: 2px solid var(--border);">
            `;
      coverImageUpload.appendChild(preview);
    };
    reader.readAsDataURL(file);
  }

  function isValidImageFile(filename) {
    const allowedExtensions = ["jpg", "jpeg", "png", "webp"];
    const extension = filename.toLowerCase().split(".").pop();
    return allowedExtensions.includes(extension);
  }
}

function initializeSearchableSelects() {
  initializeSearchableSelect("artistSearch", "artist_id");
  initializeSearchableSelect("albumSearch", "album_id");
  initializeSearchableSelect("genreSearch", "genre_id");
}

function initializeSearchableSelect(searchId, selectId) {
  const searchInput = document.getElementById(searchId);
  const selectDropdown = document.getElementById(selectId);

  if (!searchInput || !selectDropdown) return;

  // Remove existing listeners
  searchInput.removeEventListener("focus", handleSearchFocus);
  searchInput.removeEventListener("blur", handleSearchBlur);
  searchInput.removeEventListener("input", handleSearchInput);
  selectDropdown.removeEventListener("change", handleSelectChange);

  // Add event listeners
  searchInput.addEventListener("focus", handleSearchFocus);
  searchInput.addEventListener("blur", handleSearchBlur);
  searchInput.addEventListener("input", handleSearchInput);
  selectDropdown.addEventListener("change", handleSelectChange);

  // Initialize search input value
  updateSearchInput();

  function handleSearchFocus() {
    selectDropdown.style.display = "block";
    selectDropdown.size = Math.min(selectDropdown.options.length, 6);

    // Show all options initially
    Array.from(selectDropdown.options).forEach((option) => {
      option.style.display = "";
    });
  }

  function handleSearchBlur() {
    setTimeout(() => {
      selectDropdown.style.display = "none";
      selectDropdown.size = 1;
    }, 200);
  }

  function handleSearchInput() {
    const searchTerm = this.value.toLowerCase();
    const options = Array.from(selectDropdown.options);

    options.forEach((option) => {
      const text = option.textContent.toLowerCase();
      if (text.includes(searchTerm) || option.value === "") {
        option.style.display = "";
      } else {
        option.style.display = "none";
      }
    });

    // Auto-select first visible option if search matches exactly
    const visibleOptions = options.filter(
      (opt) => opt.style.display !== "none" && opt.value !== ""
    );

    if (
      visibleOptions.length === 1 &&
      visibleOptions[0].textContent.toLowerCase() === searchTerm
    ) {
      selectDropdown.value = visibleOptions[0].value;
      updateSearchInput();
    }
  }

  function handleSelectChange() {
    updateSearchInput();
  }

  function updateSearchInput() {
    if (selectDropdown.value) {
      const selectedOption =
        selectDropdown.options[selectDropdown.selectedIndex];
      searchInput.value = selectedOption.textContent;
      searchInput.setCustomValidity("");
    } else {
      searchInput.value = "";
    }
  }
}

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
}

function formatTime(seconds) {
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = Math.floor(seconds % 60);
  return `${minutes}:${remainingSeconds.toString().padStart(2, "0")}`;
}

// Confirm delete function
function confirmDelete(songId) {
  if (confirm("Apakah Anda yakin ingin menghapus lagu ini?")) {
    window.location.href = "songs.php?action=delete&id=" + songId;
  }
}
