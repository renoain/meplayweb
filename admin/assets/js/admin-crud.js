// CRUD Management JavaScript
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add("show");
    document.body.style.overflow = "hidden";
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove("show");
    document.body.style.overflow = "";
  }
}

// Close modal when clicking outside
document.addEventListener("click", function (e) {
  if (e.target.classList.contains("modal")) {
    e.target.classList.remove("show");
    document.body.style.overflow = "";
  }
});

// Close modal with Escape key
document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    const modals = document.querySelectorAll(".modal.show");
    modals.forEach((modal) => {
      modal.classList.remove("show");
    });
    document.body.style.overflow = "";
  }
});

// Form validation
function validateForm(formId) {
  const form = document.getElementById(formId);
  const requiredFields = form.querySelectorAll("[required]");
  let isValid = true;

  requiredFields.forEach((field) => {
    if (!field.value.trim()) {
      showFieldError(field, "Field ini wajib diisi");
      isValid = false;
    } else {
      clearFieldError(field);
    }
  });

  return isValid;
}

function showFieldError(field, message) {
  clearFieldError(field);

  field.classList.add("error");

  const errorDiv = document.createElement("div");
  errorDiv.className = "field-error";
  errorDiv.style.cssText = `
        color: #ef4444;
        font-size: 0.8rem;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    `;
  errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;

  field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
  field.classList.remove("error");
  const existingError = field.parentNode.querySelector(".field-error");
  if (existingError) {
    existingError.remove();
  }
}

// Add CSS for error states
const crudErrorStyles = `
    .form-group input.error,
    .form-group select.error,
    .form-group textarea.error {
        border-color: #ef4444;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }
    
    .file-upload-area.error {
        border-color: #ef4444;
        background: rgba(239, 68, 68, 0.05);
    }
`;

const crudStyleSheet = document.createElement("style");
crudStyleSheet.textContent = crudErrorStyles;
document.head.appendChild(crudStyleSheet);
