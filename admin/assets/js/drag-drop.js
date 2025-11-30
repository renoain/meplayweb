// Drag and Drop functionality
class DragDropUpload {
  constructor(uploadArea, fileInput) {
    this.uploadArea = uploadArea;
    this.fileInput = fileInput;
    this.fileInfo = uploadArea.querySelector(".file-info");
    this.init();
  }

  init() {
    // Click to select file
    this.uploadArea.addEventListener("click", () => {
      this.fileInput.click();
    });

    // Drag and drop events
    this.uploadArea.addEventListener("dragover", (e) => {
      e.preventDefault();
      this.uploadArea.classList.add("dragover");
    });

    this.uploadArea.addEventListener("dragleave", (e) => {
      e.preventDefault();
      this.uploadArea.classList.remove("dragover");
    });

    this.uploadArea.addEventListener("drop", (e) => {
      e.preventDefault();
      this.uploadArea.classList.remove("dragover");

      const files = e.dataTransfer.files;
      if (files.length > 0) {
        this.fileInput.files = files;
        this.handleFileSelect(files[0]);
      }
    });

    // File input change
    this.fileInput.addEventListener("change", (e) => {
      if (e.target.files.length > 0) {
        this.handleFileSelect(e.target.files[0]);
      }
    });
  }

  handleFileSelect(file) {
    this.displayFileInfo(file);

    // If it's an image, show preview
    if (this.fileInput.accept.includes("image")) {
      this.showImagePreview(file);
    }

    // Validate file
    this.validateFile(file);
  }

  displayFileInfo(file) {
    const fileSize = this.formatFileSize(file.size);

    this.fileInfo.innerHTML = `
            <div class="file-info-item">
                <span class="file-name">${file.name}</span>
                <span class="file-size">${fileSize}</span>
            </div>
            <div class="file-progress">
                <div class="file-progress-bar" style="width: 100%"></div>
            </div>
        `;

    this.fileInfo.style.display = "block";
  }

  showImagePreview(file) {
    const previewContainer = this.uploadArea.querySelector(".image-preview");
    if (!previewContainer) return;

    const reader = new FileReader();
    reader.onload = (e) => {
      previewContainer.innerHTML = `<img src="${e.target.result}" class="preview-image" alt="Preview">`;
    };
    reader.readAsDataURL(file);
  }

  validateFile(file) {
    const acceptTypes = this.fileInput.accept
      .split(",")
      .map((type) => type.trim());
    const fileExtension = "." + file.name.split(".").pop().toLowerCase();

    let isValid = false;
    for (const type of acceptTypes) {
      if (type.startsWith(".")) {
        if (fileExtension === type) {
          isValid = true;
          break;
        }
      } else {
        // Handle MIME types if needed
        if (file.type.startsWith(type.replace("/*", ""))) {
          isValid = true;
          break;
        }
      }
    }

    if (!isValid) {
      this.showError("Format file tidak didukung");
      this.fileInput.value = "";
      this.fileInfo.style.display = "none";
      return false;
    }

    // Check file size (50MB for audio, 5MB for images)
    const maxSize = this.fileInput.accept.includes("audio")
      ? 50 * 1024 * 1024
      : 5 * 1024 * 1024;
    if (file.size > maxSize) {
      this.showError("Ukuran file terlalu besar");
      this.fileInput.value = "";
      this.fileInfo.style.display = "none";
      return false;
    }

    return true;
  }

  showError(message) {
    const errorDiv = document.createElement("div");
    errorDiv.className = "file-error";
    errorDiv.style.cssText = `
            color: #ef4444;
            font-size: 0.8rem;
            margin-top: 8px;
            padding: 5px;
            background: #fee2e2;
            border-radius: 4px;
            text-align: center;
        `;
    errorDiv.textContent = message;

    this.fileInfo.innerHTML = "";
    this.fileInfo.appendChild(errorDiv);
    this.fileInfo.style.display = "block";

    // Remove error after 5 seconds
    setTimeout(() => {
      errorDiv.remove();
      this.fileInfo.style.display = "none";
    }, 5000);
  }

  formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  }

  reset() {
    this.fileInput.value = "";
    this.fileInfo.style.display = "none";
    this.uploadArea.classList.remove("dragover");

    const previewContainer = this.uploadArea.querySelector(".image-preview");
    if (previewContainer) {
      previewContainer.innerHTML = "";
    }
  }
}

// Initialize drag and drop for all upload areas
document.addEventListener("DOMContentLoaded", function () {
  const audioUploadArea = document.getElementById("audioUploadArea");
  const audioFileInput = document.getElementById("audio_file");
  const coverUploadArea = document.getElementById("coverUploadArea");
  const coverFileInput = document.getElementById("cover_image");

  if (audioUploadArea && audioFileInput) {
    new DragDropUpload(audioUploadArea, audioFileInput);
  }

  if (coverUploadArea && coverFileInput) {
    new DragDropUpload(coverUploadArea, coverFileInput);
  }
});
