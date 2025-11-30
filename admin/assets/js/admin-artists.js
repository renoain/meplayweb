// Artists specific JavaScript
document.addEventListener("DOMContentLoaded", function () {
  initializeArtistsForm();
  initializeArtistsUpload();
});

function initializeArtistsForm() {
  const artistForm = document.getElementById("artistForm");

  if (artistForm) {
    artistForm.addEventListener("submit", function (e) {
      const name = document.getElementById("name").value.trim();

      // Validate name
      if (!name) {
        e.preventDefault();
        showAlert("Nama artis wajib diisi!", "error");
        document.getElementById("name").focus();
        return false;
      }

      return true;
    });
  }
}

function initializeArtistsUpload() {
  const profilePictureUpload = document.getElementById("profilePictureUpload");
  const fileInput = document.getElementById("profile_picture");
  const fileInfo = document.querySelector(".file-info");

  if (!profilePictureUpload || !fileInput || !fileInfo) return;

  // Prevent multiple event listeners
  profilePictureUpload.removeEventListener("click", handleUploadClick);
  profilePictureUpload.removeEventListener("dragover", handleDragOver);
  profilePictureUpload.removeEventListener("dragleave", handleDragLeave);
  profilePictureUpload.removeEventListener("drop", handleDrop);
  fileInput.removeEventListener("change", handleFileChange);

  // Add event listeners
  profilePictureUpload.addEventListener("click", handleUploadClick);
  profilePictureUpload.addEventListener("dragover", handleDragOver);
  profilePictureUpload.addEventListener("dragleave", handleDragLeave);
  profilePictureUpload.addEventListener("drop", handleDrop);
  fileInput.addEventListener("change", handleFileChange);

  function handleUploadClick(e) {
    // Only trigger if clicking on the upload area, not the file input
    if (e.target !== fileInput && !e.target.closest('input[type="file"]')) {
      fileInput.click();
    }
  }

  function handleDragOver(e) {
    e.preventDefault();
    profilePictureUpload.classList.add("dragover");
  }

  function handleDragLeave() {
    profilePictureUpload.classList.remove("dragover");
  }

  function handleDrop(e) {
    e.preventDefault();
    profilePictureUpload.classList.remove("dragover");

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
      const existingPreview =
        profilePictureUpload.querySelector(".image-preview");
      if (existingPreview) {
        existingPreview.remove();
      }
    }
  }

  function previewImage(file) {
    const reader = new FileReader();
    reader.onload = function (e) {
      // Remove existing preview
      const existingPreview =
        profilePictureUpload.querySelector(".image-preview");
      if (existingPreview) {
        existingPreview.remove();
      }

      // Create new preview
      const preview = document.createElement("div");
      preview.className = "image-preview";
      preview.innerHTML = `
                <p style="margin: 15px 0 8px 0; font-weight: 600; color: var(--dark);">Preview:</p>
                <img src="${e.target.result}" alt="Preview" style="max-width: 150px; max-height: 150px; border-radius: 8px; border: 2px solid var(--border);">
            `;
      profilePictureUpload.appendChild(preview);
    };
    reader.readAsDataURL(file);
  }

  function isValidImageFile(filename) {
    const allowedExtensions = ["jpg", "jpeg", "png", "webp"];
    const extension = filename.toLowerCase().split(".").pop();
    return allowedExtensions.includes(extension);
  }
}

// Confirm delete function
function confirmDelete(artistId) {
  if (confirm("Apakah Anda yakin ingin menghapus artis ini?")) {
    window.location.href = "artists.php?action=delete&id=" + artistId;
  }
}
