document.addEventListener("DOMContentLoaded", function () {
  console.log("Profile page loaded");

  // Setup form validation
  setupFormValidation();

  // Setup avatar preview
  setupAvatarPreview();

  // Setup password strength
  setupPasswordStrength();

  // Setup modals
  setupModals();

  // Auto-hide alerts
  autoHideAlerts();

  // Setup real-time validation
  setupRealTimeValidation();
});

function setupFormValidation() {
  // Username form validation
  const usernameForm = document.getElementById("usernameForm");
  if (usernameForm) {
    usernameForm.addEventListener("submit", function (e) {
      const username = document.getElementById("username").value.trim();
      const errorElement = document.getElementById("usernameError");

      if (!validateUsername(username)) {
        e.preventDefault();
        showError(
          errorElement,
          "Username harus 3-50 karakter, hanya huruf, angka, dan underscore"
        );
      } else if (username === "<?php echo $username; ?>") {
        e.preventDefault();
        showError(errorElement, "Username sama dengan yang lama");
      } else {
        this.classList.add("loading");
      }
    });
  }

  // Email form validation
  const emailForm = document.getElementById("emailForm");
  if (emailForm) {
    emailForm.addEventListener("submit", function (e) {
      const email = document.getElementById("email").value.trim();
      const errorElement = document.getElementById("emailError");

      if (!validateEmail(email)) {
        e.preventDefault();
        showError(errorElement, "Format email tidak valid");
      } else if (email === "<?php echo $email; ?>") {
        e.preventDefault();
        showError(errorElement, "Email sama dengan yang lama");
      } else {
        this.classList.add("loading");
      }
    });
  }

  // Password form validation
  const passwordForm = document.getElementById("passwordForm");
  if (passwordForm) {
    passwordForm.addEventListener("submit", function (e) {
      const currentPassword = document.getElementById("current_password").value;
      const newPassword = document.getElementById("new_password").value;
      const confirmPassword = document.getElementById("confirm_password").value;

      let isValid = true;

      // Validate current password
      if (currentPassword.length < 1) {
        showError(
          document.getElementById("currentPasswordError"),
          "Password saat ini harus diisi"
        );
        isValid = false;
      } else {
        hideError(document.getElementById("currentPasswordError"));
      }

      // Validate new password
      if (newPassword.length < 6) {
        showError(
          document.getElementById("newPasswordError"),
          "Password baru minimal 6 karakter"
        );
        isValid = false;
      } else {
        hideError(document.getElementById("newPasswordError"));
      }

      // Validate confirm password
      if (newPassword !== confirmPassword) {
        showError(
          document.getElementById("confirmPasswordError"),
          "Password tidak cocok"
        );
        isValid = false;
      } else {
        hideError(document.getElementById("confirmPasswordError"));
      }

      if (!isValid) {
        e.preventDefault();
      } else {
        this.classList.add("loading");
      }
    });
  }
}

function validateUsername(username) {
  const regex = /^[a-zA-Z0-9_]{3,50}$/;
  return regex.test(username);
}

function validateEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}

function showError(element, message) {
  element.textContent = message;
  element.style.display = "block";
}

function hideError(element) {
  element.textContent = "";
  element.style.display = "none";
}

function setupAvatarPreview() {
  const avatarInput = document.getElementById("avatarInput");
  const profileAvatar = document.querySelector(".profile-avatar");
  const avatarForm = document.getElementById("avatarForm");

  if (avatarInput && profileAvatar) {
    avatarInput.addEventListener("change", function (e) {
      const file = e.target.files[0];
      if (!file) return;

      // Validate file
      const allowedTypes = [
        "image/jpeg",
        "image/jpg",
        "image/png",
        "image/webp",
        "image/gif",
      ];
      const maxSize = 5 * 1024 * 1024; // 5MB

      if (!allowedTypes.includes(file.type)) {
        showNotification(
          "Format file tidak didukung. Gunakan JPG, PNG, WebP, atau GIF",
          "error"
        );
        this.value = "";
        return;
      }

      if (file.size > maxSize) {
        showNotification("Ukuran file terlalu besar. Maksimal 5MB", "error");
        this.value = "";
        return;
      }

      // Preview image
      const reader = new FileReader();
      reader.onload = function (event) {
        profileAvatar.src = event.target.result;

        // Show loading
        avatarForm.classList.add("loading");

        // Auto submit after 1 second
        setTimeout(() => {
          avatarForm.submit();
        }, 1000);
      };
      reader.readAsDataURL(file);
    });
  }
}

function setupPasswordStrength() {
  const newPasswordInput = document.getElementById("new_password");

  if (newPasswordInput) {
    newPasswordInput.addEventListener("input", function () {
      const password = this.value;
      updatePasswordStrength(password);
    });
  }
}

function updatePasswordStrength(password) {
  const errorElement = document.getElementById("newPasswordError");

  if (password.length === 0) {
    hideError(errorElement);
    return;
  }

  let strength = 0;
  let message = "";

  // Length check
  if (password.length >= 6) strength += 25;
  if (password.length >= 8) strength += 25;

  // Character mix check
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;

  // Special characters check
  if (/\d/.test(password)) strength += 15;
  if (/[^a-zA-Z0-9]/.test(password)) strength += 10;

  // Determine strength
  if (strength < 50) {
    message = "Password lemah";
  } else if (strength < 75) {
    message = "Password cukup";
  } else if (strength < 90) {
    message = "Password kuat";
  } else {
    message = "Password sangat kuat";
  }

  showError(errorElement, message);
}

function setupModals() {
  // Delete account modal
  const deleteAccountBtn = document.querySelector(
    '[onclick="showDeleteAccountModal()"]'
  );
  const confirmDeleteInput = document.getElementById("confirm_delete");
  const deleteAccountBtnSubmit = document.getElementById("deleteAccountBtn");

  if (confirmDeleteInput && deleteAccountBtnSubmit) {
    confirmDeleteInput.addEventListener("input", function () {
      deleteAccountBtnSubmit.disabled = this.value !== "DELETE";
    });
  }

  // Clear history modal setup
  const clearHistoryBtn = document.querySelector(
    '[onclick="showClearHistoryModal()"]'
  );
}

function setupRealTimeValidation() {
  // Username real-time validation
  const usernameInput = document.getElementById("username");
  if (usernameInput) {
    usernameInput.addEventListener("input", function () {
      const username = this.value.trim();
      const errorElement = document.getElementById("usernameError");

      if (username.length > 0 && !validateUsername(username)) {
        showError(
          errorElement,
          "Username harus 3-50 karakter, hanya huruf, angka, dan underscore"
        );
      } else {
        hideError(errorElement);

        // Check if username is available (async)
        if (username.length >= 3 && username !== "<?php echo $username; ?>") {
          checkUsernameAvailability(username);
        }
      }
    });
  }

  // Email real-time validation
  const emailInput = document.getElementById("email");
  if (emailInput) {
    emailInput.addEventListener("input", function () {
      const email = this.value.trim();
      const errorElement = document.getElementById("emailError");

      if (email.length > 0 && !validateEmail(email)) {
        showError(errorElement, "Format email tidak valid");
      } else {
        hideError(errorElement);
      }
    });
  }
}

async function checkUsernameAvailability(username) {
  try {
    const response = await fetch(
      "api/profile.php?action=check_username&username=" +
        encodeURIComponent(username)
    );
    const data = await response.json();

    const errorElement = document.getElementById("usernameError");

    if (!data.available) {
      showError(errorElement, "Username sudah digunakan");
    } else {
      hideError(errorElement);
    }
  } catch (error) {
    console.error("Error checking username:", error);
  }
}

function togglePassword(inputId) {
  const input = document.getElementById(inputId);
  const button = input.parentNode.querySelector(".toggle-password");
  const icon = button.querySelector("i");

  if (input.type === "password") {
    input.type = "text";
    icon.classList.remove("fa-eye");
    icon.classList.add("fa-eye-slash");
  } else {
    input.type = "password";
    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");
  }
}

function showDeleteAccountModal() {
  document.getElementById("deleteAccountModal").style.display = "flex";
}

function closeDeleteAccountModal() {
  document.getElementById("deleteAccountModal").style.display = "none";
}

function showClearHistoryModal() {
  document.getElementById("clearHistoryModal").style.display = "flex";
}

function closeClearHistoryModal() {
  document.getElementById("clearHistoryModal").style.display = "none";
}

function clearHistory() {
  if (!confirm("Yakin ingin menghapus semua history pemutaran?")) {
    return;
  }

  // Show loading
  const clearHistoryBtn = document.querySelector(
    "#clearHistoryModal .btn-warning"
  );
  const originalText = clearHistoryBtn.innerHTML;
  clearHistoryBtn.innerHTML =
    '<i class="fas fa-spinner fa-spin"></i> Memproses...';
  clearHistoryBtn.disabled = true;

  // Simulate API call
  setTimeout(() => {
    showNotification("History berhasil dihapus", "success");
    closeClearHistoryModal();

    // Reset button
    clearHistoryBtn.innerHTML = originalText;
    clearHistoryBtn.disabled = false;

    // Refresh stats (simulated)
    const playsCount = document.querySelector(
      ".stat-item:nth-child(3) .stat-number"
    );
    if (playsCount) {
      playsCount.textContent = "0";
    }
  }, 1500);
}

function autoHideAlerts() {
  setTimeout(() => {
    const alerts = document.querySelectorAll(".alert");
    alerts.forEach((alert) => {
      alert.style.opacity = "0";
      alert.style.transform = "translateY(-10px)";
      setTimeout(() => alert.remove(), 300);
    });
  }, 5000);
}

function showNotification(message, type = "success") {
  // Remove existing notifications
  document.querySelectorAll(".custom-notification").forEach((n) => n.remove());

  // Create notification
  const notification = document.createElement("div");
  notification.className = `custom-notification notification-${type}`;
  notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === "success" ? "check" : "times"}"></i>
            <span>${message}</span>
        </div>
    `;

  // Add styles
  Object.assign(notification.style, {
    position: "fixed",
    top: "20px",
    right: "20px",
    padding: "1rem 1.5rem",
    borderRadius: "8px",
    backgroundColor: "white",
    boxShadow: "0 4px 15px rgba(0,0,0,0.1)",
    zIndex: "9999",
    transform: "translateX(120%)",
    transition: "transform 0.3s ease",
    maxWidth: "350px",
    minWidth: "250px",
    borderLeft: `4px solid ${type === "success" ? "#10b981" : "#ef4444"}`,
  });

  document.body.appendChild(notification);

  // Animate in
  setTimeout(() => {
    notification.style.transform = "translateX(0)";
  }, 10);

  // Auto remove
  setTimeout(() => {
    notification.style.transform = "translateX(120%)";
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Close modals when clicking outside
window.onclick = function (event) {
  const modals = document.querySelectorAll(".modal");
  modals.forEach((modal) => {
    if (event.target === modal) {
      modal.style.display = "none";
    }
  });
};

// Close modals with Escape key
document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    document.querySelectorAll(".modal").forEach((modal) => {
      modal.style.display = "none";
    });
  }
});
