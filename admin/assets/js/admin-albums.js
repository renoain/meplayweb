// Albums specific JavaScript
document.addEventListener("DOMContentLoaded", function () {
  initializeAlbumsForm();
  initializeAlbumsUpload();
  initializeSearchableSelect();
  initializeYearInput();
});

function initializeAlbumsForm() {
  const albumForm = document.getElementById("albumForm");

  if (albumForm) {
    albumForm.addEventListener("submit", function (e) {
      const title = document.getElementById("title").value.trim();
      const artistSelect = document.getElementById("artist_id");
      const artistSearch = document.getElementById("artistSearch");
      const releaseYear = document.getElementById("release_year");

      // Validate title
      if (!title) {
        e.preventDefault();
        showAlert("Judul album wajib diisi!", "error");
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

      // Validate year if provided
      if (releaseYear.value) {
        const year = parseInt(releaseYear.value);
        const currentYear = new Date().getFullYear();

        if (year < 1900 || year > currentYear) {
          e.preventDefault();
          showAlert(
            "Tahun rilis harus antara 1900 dan " + currentYear,
            "error"
          );
          releaseYear.focus();
          return false;
        }
      }

      return true;
    });
  }
}

function initializeAlbumsUpload() {
  const coverImageUpload = document.getElementById("coverImageUpload");
  const fileInput = document.getElementById("cover_image");
  const fileInfo = document.querySelector(".file-info");

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
    // Only trigger if clicking on the upload area, not the file input
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

      // Validate file type
      if (!isValidImageFile(file.name)) {
        showAlert(
          "Format file tidak didukung! Gunakan JPG, PNG, atau WEBP.",
          "error"
        );
        return;
      }

      // Validate file size (5MB max)
      if (file.size > 5 * 1024 * 1024) {
        showAlert("Ukuran file terlalu besar! Maksimal 5MB.", "error");
        return;
      }

      // Set the file
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

      // Validate file type
      if (!isValidImageFile(file.name)) {
        showAlert(
          "Format file tidak didukung! Gunakan JPG, PNG, atau WEBP.",
          "error"
        );
        fileInput.value = "";
        updateFileInfo();
        return;
      }

      // Validate file size (5MB max)
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

function initializeSearchableSelect() {
  const searchInput = document.getElementById("artistSearch");
  const selectDropdown = document.getElementById("artist_id");

  if (!searchInput || !selectDropdown) return;

  // Remove existing listeners
  searchInput.removeEventListener("focus", handleSearchFocus);
  searchInput.removeEventListener("blur", handleSearchBlur);
  searchInput.removeEventListener("input", handleSearchInput);
  selectDropdown.removeEventListener("change", handleSelectChange);
  selectDropdown.removeEventListener("focus", handleSelectFocus);
  selectDropdown.removeEventListener("blur", handleSelectBlur);

  // Add event listeners
  searchInput.addEventListener("focus", handleSearchFocus);
  searchInput.addEventListener("blur", handleSearchBlur);
  searchInput.addEventListener("input", handleSearchInput);
  selectDropdown.addEventListener("change", handleSelectChange);
  selectDropdown.addEventListener("focus", handleSelectFocus);
  selectDropdown.addEventListener("blur", handleSelectBlur);

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
    let hasVisibleOptions = false;

    options.forEach((option) => {
      const text = option.textContent.toLowerCase();
      if (text.includes(searchTerm) || option.value === "") {
        option.style.display = "";
        hasVisibleOptions = true;
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

  function handleSelectFocus() {
    selectDropdown.style.display = "block";
    selectDropdown.size = Math.min(selectDropdown.options.length, 6);
  }

  function handleSelectBlur() {
    setTimeout(() => {
      selectDropdown.style.display = "none";
      selectDropdown.size = 1;
    }, 200);
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

function initializeYearInput() {
  const yearInput = document.getElementById("release_year");

  if (!yearInput) return;

  yearInput.removeEventListener("input", handleYearInput);
  yearInput.addEventListener("input", handleYearInput);

  function handleYearInput() {
    let year = this.value.replace(/\D/g, "");

    // Limit to 4 digits
    if (year.length > 4) {
      year = year.substring(0, 4);
    }

    this.value = year;

    // Validate range in real-time
    if (year.length === 4) {
      const yearNum = parseInt(year);
      const currentYear = new Date().getFullYear();

      if (yearNum < 1900) {
        this.setCustomValidity("Tahun tidak boleh kurang dari 1900");
      } else if (yearNum > currentYear) {
        this.setCustomValidity("Tahun tidak boleh lebih dari " + currentYear);
      } else {
        this.setCustomValidity("");
      }
    } else {
      this.setCustomValidity("");
    }
  }
}

// Confirm delete function
function confirmDelete(albumId) {
  if (confirm("Apakah Anda yakin ingin menghapus album ini?")) {
    window.location.href = "albums.php?action=delete&id=" + albumId;
  }
}
