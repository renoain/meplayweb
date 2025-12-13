document.addEventListener("DOMContentLoaded", function () {
  // Form validation for login
  const loginForm = document.querySelector(".auth-form");
  if (loginForm) {
    initLoginForm(loginForm);
  }

  // Register form validation
  const registerForm = document.querySelector('form[action*="register"]');
  if (registerForm) {
    initRegisterForm(registerForm);
  }

  // Logout functionality
  initLogoutButtons();

  // Session timeout warning
  initSessionTimeout();
});

function initLoginForm(form) {
  form.addEventListener("submit", function (e) {
    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value;

    if (!username) {
      e.preventDefault();
      showError("Username atau email harus diisi");
      return;
    }

    if (!password) {
      e.preventDefault();
      showError("Password harus diisi");
      return;
    }

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Masuk...';
    }
  });
}

function initRegisterForm(form) {
  // Password strength indicator
  const password = document.getElementById("password");
  if (password) {
    password.addEventListener("input", function () {
      const strength = checkPasswordStrength(this.value);
      updatePasswordStrength(strength);
    });
  }

  // Password confirmation
  const confirmPassword = document.getElementById("confirm_password");
  if (password && confirmPassword) {
    confirmPassword.addEventListener("input", validatePasswordMatch);
  }

  form.addEventListener("submit", function (e) {
    const password = document.getElementById("password");
    const confirmPassword = document.getElementById("confirm_password");

    if (
      password &&
      confirmPassword &&
      password.value !== confirmPassword.value
    ) {
      e.preventDefault();
      showError("Password dan konfirmasi password tidak cocok");
      return;
    }

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Mendaftar...';
    }
  });
}

function initLogoutButtons() {
  // Handle logout links
  document.querySelectorAll('a[href*="logout"]').forEach((link) => {
    link.addEventListener("click", function (e) {
      e.preventDefault();
      confirmLogout(this.href);
    });
  });

  // Handle logout buttons
  document.querySelectorAll(".logout-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      const logoutUrl = this.getAttribute("data-logout-url") || "logout.php";
      confirmLogout(logoutUrl);
    });
  });
}

function confirmLogout(logoutUrl) {
  Swal.fire({
    title: "Konfirmasi Logout",
    text: "Apakah Anda yakin ingin keluar?",
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#667eea",
    cancelButtonColor: "#6c757d",
    confirmButtonText: "Ya, Keluar",
    cancelButtonText: "Batal",
  }).then((result) => {
    if (result.isConfirmed) {
      // Show loading
      Swal.fire({
        title: "Logging out...",
        text: "Sedang memproses logout",
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      // Perform logout
      window.location.href = logoutUrl;
    }
  });
}

function initSessionTimeout() {
  // Warn user before session timeout (15 minutes before)
  const timeout = 45 * 60 * 1000; // 45 minutes
  let timeoutWarning;

  function resetTimeout() {
    clearTimeout(timeoutWarning);
    timeoutWarning = setTimeout(showTimeoutWarning, timeout);
  }

  function showTimeoutWarning() {
    Swal.fire({
      title: "Sesi Akan Berakhir",
      text: "Sesi Anda akan segera berakhir. Apakah Anda ingin memperpanjang sesi?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#667eea",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Ya, Tetap Login",
      cancelButtonText: "Logout",
      timer: 60000, // 1 minute auto logout
      timerProgressBar: true,
    }).then((result) => {
      if (result.isConfirmed) {
        // Extend session by making a request to keep alive
        fetch("api/keep-alive.php")
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              resetTimeout();
              Swal.fire({
                title: "Sesi Diperpanjang",
                text: "Sesi Anda telah diperpanjang",
                icon: "success",
                timer: 2000,
                showConfirmButton: false,
              });
            }
          })
          .catch(() => {
            // If keep-alive fails, redirect to login
            window.location.href = "logout.php";
          });
      } else {
        // Logout
        window.location.href = "logout.php";
      }
    });
  }

  // Reset timeout on user activity
  ["click", "keypress", "scroll", "mousemove"].forEach((event) => {
    document.addEventListener(event, resetTimeout, { passive: true });
  });

  resetTimeout();
}

function checkPasswordStrength(password) {
  let strength = 0;

  if (password.length >= 6) strength++;
  if (password.match(/[a-z]+/)) strength++;
  if (password.match(/[A-Z]+/)) strength++;
  if (password.match(/[0-9]+/)) strength++;
  if (password.match(/[!@#$%^&*(),.?":{}|<>]+/)) strength++;

  return strength;
}

function updatePasswordStrength(strength) {
  let strengthBar = document.querySelector(".password-strength");
  if (!strengthBar) {
    strengthBar = document.createElement("div");
    strengthBar.className = "password-strength";
    document.getElementById("password").parentNode.appendChild(strengthBar);
  }

  strengthBar.className = "password-strength";

  if (strength === 0) {
    return;
  } else if (strength <= 2) {
    strengthBar.classList.add("strength-weak");
  } else if (strength <= 3) {
    strengthBar.classList.add("strength-fair");
  } else if (strength <= 4) {
    strengthBar.classList.add("strength-good");
  } else {
    strengthBar.classList.add("strength-strong");
  }
}

function validatePasswordMatch() {
  const password = document.getElementById("password");
  const confirmPassword = document.getElementById("confirm_password");

  if (!password || !confirmPassword) return;

  if (password.value !== confirmPassword.value) {
    confirmPassword.setCustomValidity("Password tidak cocok");
  } else {
    confirmPassword.setCustomValidity("");
  }
}

function showError(message) {
  // Remove existing alerts
  const existingAlert = document.querySelector(".alert");
  if (existingAlert) {
    existingAlert.remove();
  }

  // Create new error alert
  const alertDiv = document.createElement("div");
  alertDiv.className = "alert alert-error";
  alertDiv.innerHTML = `
        <i class="fas fa-exclamation-circle"></i>
        ${message}
    `;

  // Insert after auth-header
  const authHeader = document.querySelector(".auth-header");
  if (authHeader) {
    authHeader.parentNode.insertBefore(alertDiv, authHeader.nextSibling);

    // Scroll to alert
    alertDiv.scrollIntoView({ behavior: "smooth", block: "center" });
  }
}

// Demo account filler
document.querySelectorAll(".demo-account").forEach((account) => {
  account.addEventListener("click", function () {
    const text = this.textContent;
    if (text.includes("Admin")) {
      document.getElementById("username").value = "admin";
      document.getElementById("password").value = "password";
    } else if (text.includes("User")) {
      document.getElementById("username").value = "user";
      document.getElementById("password").value = "password";
    }
  });
});
