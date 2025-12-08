// assets/js/album_detail.js
document.addEventListener("DOMContentLoaded", function () {
  console.log("Album detail page loaded");

  // ====== NOTIFICATION SYSTEM ======
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

  // ====== PLAY ALL FUNCTIONALITY ======
  const playAllBtn = document.getElementById("playAlbumBtn");
  if (playAllBtn) {
    playAllBtn.addEventListener("click", async function () {
      const albumName = document.querySelector(
        ".playlist-info-large h1"
      ).textContent;

      // Collect all song IDs from the page
      const songIds = [];
      document.querySelectorAll(".song-item").forEach((item) => {
        const songId = item.getAttribute("data-song-id");
        if (songId) {
          songIds.push(parseInt(songId));
        }
      });

      if (songIds.length === 0) {
        showNotification("No songs in album", "error");
        return;
      }

      try {
        // Fetch first song data to play immediately
        const firstSongResponse = await fetch(`api/songs.php?id=${songIds[0]}`);
        const firstSongData = await firstSongResponse.json();

        if (!firstSongData.success) {
          showNotification("Failed to load song data", "error");
          return;
        }

        // Clear existing queue
        if (window.musicPlayer) {
          window.musicPlayer.clearQueue();

          // Play first song immediately
          window.musicPlayer.playSong(firstSongData.song, 0, true);

          // Add remaining songs to queue
          for (let i = 1; i < songIds.length; i++) {
            try {
              const songResponse = await fetch(
                `api/songs.php?id=${songIds[i]}`
              );
              const songData = await songResponse.json();

              if (songData.success) {
                window.musicPlayer.addToQueue(songData.song);
              }
            } catch (error) {
              console.error("Error fetching song:", error);
            }
          }

          // Set album info for reference
          window.musicPlayer.currentAlbum = {
            name: albumName,
            songIds: songIds,
          };

          showNotification(
            `Playing ${songIds.length} songs from "${albumName}"`
          );

          // Auto-play first song
          setTimeout(() => {
            if (window.musicPlayer.audio) {
              window.musicPlayer.play();
            }
          }, 500);
        } else {
          // Fallback: Save to localStorage
          const songsData = [];

          // Try to get all songs data
          for (let i = 0; i < Math.min(songIds.length, 10); i++) {
            // Limit to 10 songs for performance
            try {
              const songResponse = await fetch(
                `api/songs.php?id=${songIds[i]}`
              );
              const songData = await songResponse.json();

              if (songData.success) {
                songsData.push(songData.song);
              }
            } catch (error) {
              console.error("Error fetching song:", error);
            }
          }

          if (songsData.length > 0) {
            localStorage.setItem("meplay_queue", JSON.stringify(songIds));
            localStorage.setItem(
              "meplay_current_album",
              JSON.stringify({
                ids: songIds,
                songs: songsData,
                name: albumName,
              })
            );

            // Set first song as now playing
            localStorage.setItem(
              "meplay_now_playing",
              JSON.stringify(songsData[0])
            );

            showNotification(
              `Added ${songIds.length} songs from "${albumName}" to queue`
            );

            // Reload page to trigger player update
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          }
        }
      } catch (error) {
        console.error("Error playing album:", error);
        showNotification("Failed to play album", "error");
      }
    });
  }

  // ====== INDIVIDUAL PLAY BUTTONS ======
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

            // Get all other songs in album
            const allSongIds = [];
            document.querySelectorAll(".song-item").forEach((item) => {
              const id = item.getAttribute("data-song-id");
              if (id && id !== songId) {
                allSongIds.push(parseInt(id));
              }
            });

            // Add other songs to queue (async, don't wait)
            setTimeout(async () => {
              for (const id of allSongIds) {
                try {
                  const songResponse = await fetch(`api/songs.php?id=${id}`);
                  const songData = await songResponse.json();

                  if (songData.success) {
                    window.musicPlayer.addToQueue(songData.song);
                  }
                } catch (error) {
                  console.error("Error fetching song:", error);
                }
              }
            }, 0);

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

  // ====== DROPDOWN SYSTEM ======
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

  // ====== SONG DROPDOWNS ======
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

  // ====== LIKE SONG ======
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

  // ====== ADD TO QUEUE ======
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

  // ====== ADD TO PLAYLIST ======
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

  // ====== CREATE PLAYLIST ======
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

  // ====== MODAL FUNCTIONS ======
  document.querySelectorAll(".close-modal").forEach((btn) => {
    btn.addEventListener("click", function () {
      document.querySelectorAll(".modal").forEach((modal) => {
        modal.classList.remove("show");
      });
    });
  });

  // ====== CREATE PLAYLIST FORM ======
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

  // ====== LIKE BUTTON CLICKS ======
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

  console.log("All event listeners set up successfully");
});
