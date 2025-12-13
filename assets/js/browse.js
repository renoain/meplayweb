document.addEventListener("DOMContentLoaded", function () {
  console.log("Search page loaded");

  //  NOTIFICATION SYSTEM
  function showNotification(message, type = "success") {
    // Remove any existing notifications
    document.querySelectorAll(".notification.show").forEach((notification) => {
      notification.remove();
    });

    // Create new notification
    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${
                  type === "success" ? "check" : "exclamation"
                }"></i>
                <span>${message}</span>
            </div>
        `;

    document.body.appendChild(notification);

    setTimeout(() => notification.classList.add("show"), 100);

    setTimeout(() => {
      notification.classList.remove("show");
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }

  //  DROPDOWN SYSTEM
  let activeDropdown = null;

  function closeDropdowns() {
    if (activeDropdown) {
      activeDropdown.style.display = "none";
      activeDropdown = null;
    }
  }

  document.addEventListener("click", function (e) {
    if (!e.target.closest(".more-btn") && !e.target.closest(".song-dropdown")) {
      closeDropdowns();
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeDropdowns();
  });

  //  SONG DROPDOWNS
  document.querySelectorAll(".song-more-btn").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.stopPropagation();

      const index = this.getAttribute("data-index");
      const dropdown = document.getElementById("songDropdown-" + index);

      if (!dropdown) return;

      if (dropdown.style.display === "block") {
        dropdown.style.display = "none";
        activeDropdown = null;
      } else {
        closeDropdowns();
        dropdown.style.display = "block";
        dropdown.style.position = "fixed";

        const rect = this.getBoundingClientRect();
        dropdown.style.top = rect.bottom + 5 + "px";
        dropdown.style.left = rect.right - dropdown.offsetWidth + "px";

        activeDropdown = dropdown;
      }
    });
  });

  //  LIKE SONG
  document.querySelectorAll(".like-song").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const songId = this.getAttribute("data-song-id");
      const isLiked = this.classList.contains("liked");
      const songItem = this.closest(".song-item");
      const songTitle = songItem
        ? songItem.querySelector(".song-info h4").textContent
        : "Song";

      closeDropdowns();

      fetch("api/likes.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          song_id: parseInt(songId),
          action: isLiked ? "unlike" : "like",
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Update button in dropdown
            if (isLiked) {
              this.classList.remove("liked");
              this.innerHTML = '<i class="far fa-heart"></i> Like';
            } else {
              this.classList.add("liked");
              this.innerHTML = '<i class="fas fa-heart"></i> Unlike';
            }

            // Update heart button
            const likeBtn = document.querySelector(
              `.like-btn[data-song-id="${songId}"]`
            );
            if (likeBtn) {
              const icon = likeBtn.querySelector("i");
              if (isLiked) {
                likeBtn.classList.remove("liked");
                icon.classList.remove("fas");
                icon.classList.add("far");
              } else {
                likeBtn.classList.add("liked");
                icon.classList.remove("far");
                icon.classList.add("fas");
              }
            }

            showNotification(
              isLiked
                ? `Removed "${songTitle}" from liked songs`
                : `Added "${songTitle}" to liked songs`,
              "success"
            );
          } else {
            showNotification(data.message || "Failed to update like", "error");
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          showNotification("Network error", "error");
        });

      return false;
    });
  });

  //  LIKE BUTTON CLICKS
  document.querySelectorAll(".like-btn").forEach((button) => {
    button.addEventListener("click", async function (e) {
      e.preventDefault();
      e.stopPropagation();

      const songId = this.getAttribute("data-song-id");
      const isLiked = this.classList.contains("liked");

      fetch("api/likes.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          song_id: parseInt(songId),
          action: isLiked ? "unlike" : "like",
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            const icon = this.querySelector("i");
            const newIsLiked = data.is_liked;

            if (newIsLiked) {
              this.classList.add("liked");
              icon.classList.remove("far");
              icon.classList.add("fas");
            } else {
              this.classList.remove("liked");
              icon.classList.remove("fas");
              icon.classList.add("far");
            }

            // Update dropdown item
            const dropdownItem = document.querySelector(
              `.like-song[data-song-id="${songId}"]`
            );
            if (dropdownItem) {
              const dropdownIcon = dropdownItem.querySelector("i");
              if (newIsLiked) {
                dropdownItem.classList.add("liked");
                dropdownIcon.classList.remove("far");
                dropdownIcon.classList.add("fas");
                dropdownItem.innerHTML = '<i class="fas fa-heart"></i> Unlike';
              } else {
                dropdownItem.classList.remove("liked");
                dropdownIcon.classList.remove("fas");
                dropdownIcon.classList.add("far");
                dropdownItem.innerHTML = '<i class="far fa-heart"></i> Like';
              }
            }

            showNotification(
              newIsLiked ? "Added to liked songs" : "Removed from liked songs",
              "success"
            );
          } else {
            showNotification(data.message || "Failed to update like", "error");
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          showNotification("Network error", "error");
        });
    });
  });

  //  PLAY BUTTONS
  document.querySelectorAll(".play-btn").forEach((btn) => {
    btn.addEventListener("click", async function (e) {
      e.stopPropagation();

      const songId = this.getAttribute("data-song-id");
      const songItem = this.closest(".song-item");
      const songTitle = songItem
        ? songItem.querySelector(".song-info h4").textContent
        : "Song";

      try {
        // Get song data
        const response = await fetch(`api/songs.php?id=${songId}`);
        const data = await response.json();

        if (data.success) {
          if (window.musicPlayer) {
            // Clear queue and add this song
            window.musicPlayer.clearQueue();
            window.musicPlayer.playSong(data.song, 0, true);

            showNotification(`Playing "${songTitle}"`);
          } else {
            // Fallback
            localStorage.setItem(
              "meplay_now_playing",
              JSON.stringify({
                id: songId,
                title: songTitle,
              })
            );
            showNotification(`Playing "${songTitle}"`);
          }
        } else {
          showNotification("Failed to play song", "error");
        }
      } catch (error) {
        console.error("Error:", error);
        showNotification("Network error", "error");
      }
    });
  });

  //  ADD TO QUEUE
  document.querySelectorAll(".add-to-queue").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const songId = this.getAttribute("data-song-id");
      const songItem = this.closest(".song-item");
      const songTitle = songItem
        ? songItem.querySelector(".song-info h4").textContent
        : "Song";

      closeDropdowns();

      // Add to queue via player if available
      if (window.musicPlayer) {
        fetch(`api/songs.php?id=${songId}`)
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              window.musicPlayer.addToQueue(data.song);
              showNotification(`"${songTitle}" added to queue`);
            } else {
              showNotification("Failed to get song data", "error");
            }
          })
          .catch((error) => {
            console.error("Error:", error);
            showNotification("Network error", "error");
          });
      } else {
        // Fallback to localStorage
        let queue = JSON.parse(localStorage.getItem("meplay_queue") || "[]");
        if (!queue.includes(songId)) {
          queue.push(songId);
          localStorage.setItem("meplay_queue", JSON.stringify(queue));
          showNotification(`"${songTitle}" added to queue`);
        } else {
          showNotification(`"${songTitle}" already in queue`, "error");
        }
      }

      return false;
    });
  });

  //  ADD TO PLAYLIST
  document
    .querySelectorAll(".add-to-playlist:not(.disabled)")
    .forEach((button) => {
      button.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        const songId = this.getAttribute("data-song-id");
        const playlistId = this.getAttribute("data-playlist-id");
        const songItem = this.closest(".song-item");
        const songTitle = songItem
          ? songItem.querySelector(".song-info h4").textContent
          : "Song";
        const playlistName = button.textContent.trim();

        closeDropdowns();

        fetch("api/playlists.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            action: "add_song",
            song_id: parseInt(songId),
            playlist_id: parseInt(playlistId),
          }),
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showNotification(`"${songTitle}" added to "${playlistName}"`);
            } else {
              showNotification(
                data.message || "Failed to add to playlist",
                "error"
              );
            }
          })
          .catch((error) => {
            console.error("Error:", error);
            showNotification("Network error", "error");
          });

        return false;
      });
    });

  //  CREATE PLAYLIST
  document.querySelectorAll(".create-playlist").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const songId = this.getAttribute("data-song-id");

      closeDropdowns();
      document.getElementById("songIdForPlaylist").value = songId;
      document.getElementById("createPlaylistModal").classList.add("show");

      return false;
    });
  });

  //  MODAL FUNCTIONS
  document.querySelectorAll(".close-modal").forEach((btn) => {
    btn.addEventListener("click", function () {
      document.querySelectorAll(".modal").forEach((modal) => {
        modal.classList.remove("show");
      });
    });
  });

  //  CREATE PLAYLIST FORM
  const createForm = document.getElementById("createPlaylistForm");
  if (createForm) {
    createForm.addEventListener("submit", function (e) {
      e.preventDefault();

      const formData = new FormData(this);
      const songId = formData.get("song_id");
      const title = formData.get("title");

      fetch("api/playlists.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "create",
          title: title,
          description: formData.get("description"),
          song_id: songId ? parseInt(songId) : null,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            showNotification(`Playlist "${title}" created successfully`);
            document
              .getElementById("createPlaylistModal")
              .classList.remove("show");
            this.reset();
          } else {
            showNotification(
              data.message || "Failed to create playlist",
              "error"
            );
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          showNotification("Network error", "error");
        });
    });
  }

  console.log("Search page initialized successfully");
});
