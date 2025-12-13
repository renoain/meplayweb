document.addEventListener("DOMContentLoaded", function () {
  initializeSearchableDropdowns();
  initializeFileUploads();
});

function initializeSearchableDropdowns() {
  const searchableSelects = document.querySelectorAll(".searchable-select");

  searchableSelects.forEach((container) => {
    const searchInput = container.querySelector(".search-input");
    const selectDropdown = container.querySelector(".select-dropdown");

    if (searchInput && selectDropdown) {
      // Show dropdown on focus
      searchInput.addEventListener("focus", function () {
        selectDropdown.style.display = "block";
        selectDropdown.size = Math.min(selectDropdown.options.length, 6);

        // Show all options initially
        Array.from(selectDropdown.options).forEach((option) => {
          option.style.display = "";
        });
      });

      // Hide dropdown on blur (with delay to allow click)
      searchInput.addEventListener("blur", function () {
        setTimeout(() => {
          selectDropdown.style.display = "none";
          selectDropdown.size = 1;
        }, 200);
      });

      // Filter options based on search
      searchInput.addEventListener("input", function () {
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
          updateSearchInput(searchInput, selectDropdown);
        }
      });

      // Update search input when selection changes
      selectDropdown.addEventListener("change", function () {
        updateSearchInput(searchInput, selectDropdown);
      });

      // Initialize search input value
      updateSearchInput(searchInput, selectDropdown);

      // Keyboard navigation
      searchInput.addEventListener("keydown", function (e) {
        if (e.key === "ArrowDown") {
          e.preventDefault();
          selectDropdown.focus();
        } else if (e.key === "Enter") {
          e.preventDefault();
          const visibleOptions = Array.from(selectDropdown.options).filter(
            (opt) => opt.style.display !== "none" && opt.value !== ""
          );
          if (visibleOptions.length > 0) {
            selectDropdown.value = visibleOptions[0].value;
            updateSearchInput(searchInput, selectDropdown);
            selectDropdown.dispatchEvent(new Event("change"));
          }
        }
      });
    }
  });
}

function updateSearchInput(searchInput, selectDropdown) {
  if (selectDropdown.value) {
    const selectedOption = selectDropdown.options[selectDropdown.selectedIndex];
    searchInput.value = selectedOption.textContent;
    searchInput.setCustomValidity("");
  } else {
    searchInput.value = "";
  }
}

function initializeFileUploads() {
  const fileInputs = document.querySelectorAll('input[type="file"]');

  fileInputs.forEach((input) => {
    const container = input.closest(".file-upload");
    if (container) {
      const label = container.querySelector("label");
      const icon = container.querySelector(".upload-icon");
      const fileInfo = container.querySelector(".file-info");

      // Click event
      container.addEventListener("click", function (e) {
        if (e.target !== input) {
          input.click();
        }
      });

      // Drag and drop
      container.addEventListener("dragover", function (e) {
        e.preventDefault();
        container.classList.add("dragover");
      });

      container.addEventListener("dragleave", function () {
        container.classList.remove("dragover");
      });

      container.addEventListener("drop", function (e) {
        e.preventDefault();
        container.classList.remove("dragover");
        input.files = e.dataTransfer.files;
        updateFileInfo(input, fileInfo);
      });

      // Change event
      input.addEventListener("change", function () {
        updateFileInfo(input, fileInfo);
      });
    }
  });
}

function updateFileInfo(input, fileInfo) {
  if (input.files.length > 0) {
    const file = input.files[0];
    const fileSize = (file.size / 1024 / 1024).toFixed(2);
    fileInfo.textContent = `${file.name} (${fileSize} MB)`;
    fileInfo.style.color = "var(--success)";
  } else {
    fileInfo.textContent = "Belum ada file yang dipilih";
    fileInfo.style.color = "var(--gray)";
  }
}

// Form validation helper
function validateForm(form) {
  const requiredFields = form.querySelectorAll("[required]");
  let isValid = true;

  requiredFields.forEach((field) => {
    if (!field.value.trim()) {
      field.style.borderColor = "var(--danger)";
      isValid = false;
    } else {
      field.style.borderColor = "";
    }
  });

  return isValid;
}

// Confirm delete
function confirmDelete(
  message = "Apakah Anda yakin ingin menghapus data ini?"
) {
  return confirm(message);
}

// Show loading state
function setLoadingState(button, isLoading) {
  if (isLoading) {
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
  } else {
    button.disabled = false;
    button.innerHTML = button.getAttribute("data-original-text");
  }
}
