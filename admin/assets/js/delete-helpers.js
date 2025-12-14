// ============================================
// DELETE HELPERS JS - FOR ALL ADMIN PAGES
// ============================================

// Initialize delete modals for each page
document.addEventListener("DOMContentLoaded", function () {
  initializeDeleteButtons();
});

function initializeDeleteButtons() {
  // Find all delete buttons with data attributes
  const deleteButtons = document.querySelectorAll("[data-delete-type]");

  deleteButtons.forEach((button) => {
    // Remove existing click listeners
    const newButton = button.cloneNode(true);
    button.parentNode.replaceChild(newButton, button);

    // Add new click listener
    newButton.addEventListener("click", function (e) {
      e.preventDefault();

      const type = this.getAttribute("data-delete-type");
      const id = this.getAttribute("data-delete-id");
      const name = this.getAttribute("data-delete-name") || "";
      const additional = this.getAttribute("data-delete-additional") || "";
      const url = this.getAttribute("data-delete-url") || "";
      const requireConfirm =
        this.getAttribute("data-require-confirm") === "true";
      const warning = this.getAttribute("data-warning") || "";

      // Get confirmation text based on type
      let confirmationText = "konfirmasi";
      if (type === "artist") confirmationText = "DELETE artis";
      else if (type === "album") confirmationText = "DELETE album";
      else if (type === "song") confirmationText = " DELETE lagu";
      else if (type === "genre") confirmationText = "DELETE genre";

      DeleteModal.show({
        type: type,
        id: id,
        name: name,
        additionalInfo: additional,
        deleteUrl: url,
        requireConfirmation: requireConfirm,
        confirmationText: confirmationText,
        warning: warning,
      });
    });
  });
}

// Specific delete functions for each type
const DeleteHelpers = {
  // Delete song
  deleteSong: function (songId, songName) {
    DeleteModal.show({
      type: "song",
      id: songId,
      name: songName,
      deleteUrl: `songs.php?action=delete&id=${songId}`,
      requireConfirmation: true,
      confirmationText: "hapus lagu",
    });
  },

  // Delete artist
  deleteArtist: function (artistId, artistName, songsCount = 0) {
    let warning = "";
    if (songsCount > 0) {
      warning = `Artis ini memiliki ${songsCount} lagu. Menghapus artis akan menghapus semua lagu terkait.`;
    }

    DeleteModal.show({
      type: "artist",
      id: artistId,
      name: artistName,
      deleteUrl: `artists.php?action=delete&id=${artistId}`,
      requireConfirmation: true,
      confirmationText: "hapus artis",
      warning: warning,
    });
  },

  // Delete album
  deleteAlbum: function (albumId, albumTitle, songsCount = 0) {
    let warning = "";
    if (songsCount > 0) {
      warning = `Album ini memiliki ${songsCount} lagu. Menghapus album akan menghapus semua lagu terkait.`;
    }

    DeleteModal.show({
      type: "album",
      id: albumId,
      name: albumTitle,
      deleteUrl: `albums.php?action=delete&id=${albumId}`,
      requireConfirmation: true,
      confirmationText: "hapus album",
      warning: warning,
    });
  },

  // Delete genre
  deleteGenre: function (genreId, genreName, songsCount = 0) {
    let warning = "";
    if (songsCount > 0) {
      warning = `Genre ini digunakan oleh ${songsCount} lagu. Hapus atau ubah genre lagu terlebih dahulu.`;
    }

    DeleteModal.show({
      type: "genre",
      id: genreId,
      name: genreName,
      deleteUrl: `genres.php?action=delete&id=${genreId}`,
      requireConfirmation: songsCount === 0,
      confirmationText: "hapus genre",
      warning: warning,
    });
  },
};

// Make globally available
window.DeleteHelpers = DeleteHelpers;
