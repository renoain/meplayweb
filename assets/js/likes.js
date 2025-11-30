// assets/js/likes.js

class LikesManager {
  constructor() {
    this.init();
  }

  init() {
    this.bindLikeButtons();
  }

  bindLikeButtons() {
    // Like buttons in song cards
    document.addEventListener("click", (e) => {
      if (e.target.closest(".like-song")) {
        e.preventDefault();
        const button = e.target.closest(".like-song");
        const songId = button.getAttribute("data-song-id");
        this.toggleLike(songId, button);
      }
    });
  }

  async toggleLike(songId, button) {
    try {
      const isLiked = button.classList.contains("liked");
      const action = isLiked ? "unlike" : "like";

      const response = await fetch("api/likes.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          song_id: songId,
          action: action,
        }),
      });

      const data = await response.json();

      if (data.success) {
        button.classList.toggle("liked");
        const icon = button.querySelector("i");
        icon.className = isLiked ? "far fa-heart" : "fas fa-heart";

        showNotification(
          isLiked ? "Removed from liked songs" : "Added to liked songs"
        );

        // Update like count if displayed
        this.updateLikeCount(songId, data.new_count);
      } else {
        showNotification(data.message || "Error updating like", "error");
      }
    } catch (error) {
      console.error("Like error:", error);
      showNotification("Error updating like", "error");
    }
  }

  updateLikeCount(songId, newCount) {
    // Update like count in UI if displayed
    const likeCountElements = document.querySelectorAll(
      `[data-song-id="${songId}"] .like-count`
    );
    likeCountElements.forEach((element) => {
      element.textContent = newCount;
    });
  }

  async checkLikeStatus(songId) {
    try {
      const response = await fetch(`api/likes.php?song_id=${songId}`);
      const data = await response.json();
      return data.is_liked;
    } catch (error) {
      console.error("Error checking like status:", error);
      return false;
    }
  }

  async getLikedSongs() {
    try {
      const response = await fetch("api/likes.php?action=get_liked");
      const data = await response.json();
      return data.songs || [];
    } catch (error) {
      console.error("Error getting liked songs:", error);
      return [];
    }
  }
}

// Initialize likes manager
document.addEventListener("DOMContentLoaded", () => {
  window.likesManager = new LikesManager();
});
