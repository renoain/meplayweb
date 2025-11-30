<?php
// search.php
require_once 'config/constants.php';
require_once 'config/auth.php';
require_once 'config/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$genre_id = isset($_GET['genre']) ? intval($_GET['genre']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get all genres for filter
$genres_query = "SELECT id, name, color FROM genres WHERE is_active = 1 ORDER BY name";
$genres_stmt = $conn->prepare($genres_query);
$genres_stmt->execute();
$genres = $genres_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build search query
$where_conditions = ["s.is_active = 1"];
$params = [];

if (!empty($search_query)) {
    $where_conditions[] = "(s.title LIKE ? OR a.name LIKE ? OR al.title LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($genre_id > 0) {
    $where_conditions[] = "s.genre_id = ?";
    $params[] = $genre_id;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) as total FROM songs s 
                LEFT JOIN artists a ON s.artist_id = a.id 
                LEFT JOIN albums al ON s.album_id = al.id 
                WHERE $where_clause";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_results = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_results / $limit);

// Get search results
$search_sql = "SELECT s.*, a.name as artist_name, al.title as album_title, g.name as genre_name 
               FROM songs s 
               LEFT JOIN artists a ON s.artist_id = a.id 
               LEFT JOIN albums al ON s.album_id = al.id 
               LEFT JOIN genres g ON s.genre_id = g.id 
               WHERE $where_clause 
               ORDER BY s.created_at DESC 
               LIMIT $limit OFFSET $offset";
               
$search_stmt = $conn->prepare($search_sql);
$search_stmt->execute($params);
$results = $search_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's playlists for dropdown
$user_id = $_SESSION['user_id'];
$playlists_query = "SELECT id, title FROM playlists WHERE user_id = ?";
$playlists_stmt = $conn->prepare($playlists_query);
$playlists_stmt->execute([$user_id]);
$user_playlists = $playlists_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
function getSongCover($cover_image) {
    if ($cover_image && file_exists('uploads/covers/' . $cover_image)) {
        return 'uploads/covers/' . $cover_image;
    }
    return 'assets/images/covers/default-cover.png';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/search.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/player.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body data-theme="<?php echo getCurrentTheme(); ?>">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">
            <div class="search-header">
                <h1>Search</h1>
                
                <?php if ($genres): ?>
                <div class="genre-filters">
                    <h3>Browse by Genre</h3>
                    <div class="genres-grid">
                        <a href="search.php" class="genre-card <?php echo $genre_id == 0 ? 'active' : ''; ?>">
                            <div class="genre-icon">
                                <i class="fas fa-music"></i>
                            </div>
                            <span class="genre-name">All Genres</span>
                        </a>
                        <?php foreach ($genres as $genre): ?>
                        <a href="search.php?genre=<?php echo $genre['id']; ?>" 
                           class="genre-card <?php echo $genre_id == $genre['id'] ? 'active' : ''; ?>"
                           style="--genre-color: <?php echo $genre['color']; ?>">
                            <div class="genre-icon">
                                <i class="fas fa-music"></i>
                            </div>
                            <span class="genre-name"><?php echo htmlspecialchars($genre['name']); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($search_query) || $genre_id > 0): ?>
            <div class="search-results">
                <div class="results-header">
                    <h2>
                        <?php if (!empty($search_query) && $genre_id > 0): ?>
                            Search results for "<?php echo htmlspecialchars($search_query); ?>" in 
                            <?php 
                            $genre_name = 'Unknown Genre';
                            foreach ($genres as $g) {
                                if ($g['id'] == $genre_id) {
                                    $genre_name = $g['name'];
                                    break;
                                }
                            }
                            echo htmlspecialchars($genre_name);
                            ?>
                        <?php elseif (!empty($search_query)): ?>
                            Search results for "<?php echo htmlspecialchars($search_query); ?>"
                        <?php elseif ($genre_id > 0): ?>
                            <?php 
                            $genre_name = 'Unknown Genre';
                            foreach ($genres as $g) {
                                if ($g['id'] == $genre_id) {
                                    $genre_name = $g['name'];
                                    break;
                                }
                            }
                            ?>
                            <?php echo htmlspecialchars($genre_name); ?> Songs
                        <?php endif; ?>
                    </h2>
                    <div class="results-count">
                        <?php echo number_format($total_results); ?> result<?php echo $total_results != 1 ? 's' : ''; ?>
                    </div>
                </div>

                <?php if ($results): ?>
                <div class="songs-list">
                    <?php foreach ($results as $song): ?>
                    <div class="song-item" data-song-id="<?php echo $song['id']; ?>">
                        <div class="song-cover">
                            <img src="<?php echo getSongCover($song['cover_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($song['title']); ?>">
                            <button class="play-btn">
                                <i class="fas fa-play"></i>
                            </button>
                        </div>
                        <div class="song-info">
                            <h4><?php echo htmlspecialchars($song['title']); ?></h4>
                            <p><?php echo htmlspecialchars($song['artist_name']); ?></p>
                            <div class="song-meta">
                                <span class="album"><?php echo htmlspecialchars($song['album_title'] ?? 'Single'); ?></span>
                                <span class="genre"><?php echo htmlspecialchars($song['genre_name'] ?? 'Unknown'); ?></span>
                            </div>
                        </div>
                        <div class="song-duration"><?php echo formatDuration($song['duration']); ?></div>
                        <div class="song-actions">
                            <button class="more-btn">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu">
                                <button class="dropdown-item like-song" data-song-id="<?php echo $song['id']; ?>">
                                    <i class="far fa-heart"></i> Like
                                </button>
                                <button class="dropdown-item add-to-queue" data-song-id="<?php echo $song['id']; ?>">
                                    <i class="fas fa-list"></i> Add to Queue
                                </button>
                                <div class="dropdown-submenu">
                                    <button class="dropdown-item">
                                        <i class="fas fa-plus"></i> Add to Playlist
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                    <div class="submenu">
                                        <?php if ($user_playlists): ?>
                                            <?php foreach ($user_playlists as $playlist): ?>
                                                <button class="dropdown-item add-to-playlist" 
                                                        data-song-id="<?php echo $song['id']; ?>" 
                                                        data-playlist-id="<?php echo $playlist['id']; ?>">
                                                    <?php echo htmlspecialchars($playlist['title']); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <button class="dropdown-item disabled">No playlists</button>
                                        <?php endif; ?>
                                        <button class="dropdown-item create-playlist" data-song-id="<?php echo $song['id']; ?>">
                                            <i class="fas fa-plus-circle"></i> Create New Playlist
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <?php endif; ?>
                    
                    <div class="page-numbers">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="page-number <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <span class="page-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-search fa-3x"></i>
                    <h3>No results found</h3>
                    <p>Try adjusting your search or filter to find what you're looking for.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/player.php'; ?>
    
    <!-- Create Playlist Modal -->
    <div id="createPlaylistModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Playlist</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createPlaylistForm">
                    <div class="form-group">
                        <label for="playlistTitle">Playlist Title</label>
                        <input type="text" id="playlistTitle" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="playlistDescription">Description (Optional)</label>
                        <textarea id="playlistDescription" name="description"></textarea>
                    </div>
                    <input type="hidden" id="songIdForPlaylist" name="song_id">
                    <div class="form-actions">
                        <button type="button" class="btn-secondary close-modal">Cancel</button>
                        <button type="submit" class="btn-primary">Create Playlist</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/player.js"></script>
    <script src="assets/js/likes.js"></script>
    <script src="assets/js/playlists.js"></script>
</body>
</html>

