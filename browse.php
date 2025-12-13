<?php
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
$user_id = $_SESSION['user_id'];

// Get parameters
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$genre_id = isset($_GET['genre']) ? intval($_GET['genre']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Check if there's an anchor link (e.g., #song-123)
$anchor_id = 0;
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '#song-') !== false) {
    $referer = $_SERVER['HTTP_REFERER'];
    if (preg_match('/#song-(\d+)/', $referer, $matches)) {
        $anchor_id = intval($matches[1]);
    }
}

// Get all genres
$genres_query = "SELECT id, name, color FROM genres WHERE is_active = 1 ORDER BY name";
$genres_stmt = $conn->prepare($genres_query);
$genres_stmt->execute();
$genres = $genres_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get popular genres
$popular_genres_query = "SELECT g.id, g.name, g.color, COUNT(s.id) as song_count 
                         FROM genres g 
                         LEFT JOIN songs s ON g.id = s.genre_id AND s.is_active = 1 
                         WHERE g.is_active = 1 
                         GROUP BY g.id 
                         ORDER BY g.name ASC 
                         LIMIT 12";
$popular_genres_stmt = $conn->prepare($popular_genres_query);
$popular_genres_stmt->execute();
$popular_genres = $popular_genres_stmt->fetchAll(PDO::FETCH_ASSOC);

// Jika ada search query, tampilkan hasil pencarian
if (!empty($search_query)) {
    // Get user's playlists for dropdown
    $playlists_query = "SELECT id, title FROM playlists WHERE user_id = ? ORDER BY title ASC";
    $playlists_stmt = $conn->prepare($playlists_query);
    $playlists_stmt->execute([$user_id]);
    $user_playlists = $playlists_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get liked songs for this user
    $liked_query = "SELECT song_id FROM user_likes WHERE user_id = ?";
    $liked_stmt = $conn->prepare($liked_query);
    $liked_stmt->execute([$user_id]);
    $liked_songs = $liked_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Search for songs
    $search_sql = "SELECT s.*, a.name as artist_name, al.title as album_title, g.name as genre_name
                   FROM songs s 
                   LEFT JOIN artists a ON s.artist_id = a.id 
                   LEFT JOIN albums al ON s.album_id = al.id 
                   LEFT JOIN genres g ON s.genre_id = g.id
                   WHERE s.is_active = 1 
                   AND (s.title LIKE ? OR a.name LIKE ? OR al.title LIKE ?)
                   ORDER BY s.created_at DESC 
                   LIMIT $limit OFFSET $offset";
    
    $search_param = "%$search_query%";
    $search_stmt = $conn->prepare($search_sql);
    $search_stmt->execute([$search_param, $search_param, $search_param]);
    $search_results = $search_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total search count
    $search_count_sql = "SELECT COUNT(*) as total FROM songs s 
                         LEFT JOIN artists a ON s.artist_id = a.id 
                         LEFT JOIN albums al ON s.album_id = al.id 
                         WHERE s.is_active = 1 
                         AND (s.title LIKE ? OR a.name LIKE ? OR al.title LIKE ?)";
    $count_stmt = $conn->prepare($search_count_sql);
    $count_stmt->execute([$search_param, $search_param, $search_param]);
    $total_search_results = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_search_pages = ceil($total_search_results / $limit);
    
    // Search for albums
    $album_search_sql = "SELECT al.*, a.name as artist_name,
                         COUNT(s.id) as song_count
                         FROM albums al
                         LEFT JOIN artists a ON al.artist_id = a.id
                         LEFT JOIN songs s ON s.album_id = al.id
                         WHERE al.is_active = 1 
                         AND (al.title LIKE ? OR a.name LIKE ?)
                         GROUP BY al.id
                         ORDER BY al.created_at DESC
                         LIMIT 6";
    
    $album_stmt = $conn->prepare($album_search_sql);
    $album_stmt->execute([$search_param, $search_param]);
    $album_results = $album_stmt->fetchAll(PDO::FETCH_ASSOC);
}

//   get songs for that genre
if ($genre_id > 0) {
    // Get genre name
    $genre_name_query = "SELECT name FROM genres WHERE id = ?";
    $genre_name_stmt = $conn->prepare($genre_name_query);
    $genre_name_stmt->execute([$genre_id]);
    $genre_name = $genre_name_stmt->fetchColumn();
    
    // Get user's playlists for dropdown
    $playlists_query = "SELECT id, title FROM playlists WHERE user_id = ? ORDER BY title ASC";
    $playlists_stmt = $conn->prepare($playlists_query);
    $playlists_stmt->execute([$user_id]);
    $user_playlists = $playlists_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get liked songs
    $liked_query = "SELECT song_id FROM user_likes WHERE user_id = ?";
    $liked_stmt = $conn->prepare($liked_query);
    $liked_stmt->execute([$user_id]);
    $liked_songs = $liked_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get songs in this genre
    $genre_songs_query = "SELECT s.*, a.name as artist_name, al.title as album_title, g.name as genre_name
                         FROM songs s 
                         LEFT JOIN artists a ON s.artist_id = a.id 
                         LEFT JOIN albums al ON s.album_id = al.id 
                         LEFT JOIN genres g ON s.genre_id = g.id
                         WHERE s.genre_id = ? AND s.is_active = 1 
                         ORDER BY s.created_at DESC 
                         LIMIT $limit OFFSET $offset";
    $genre_songs_stmt = $conn->prepare($genre_songs_query);
    $genre_songs_stmt->execute([$genre_id]);
    $genre_songs = $genre_songs_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total songs count for this genre
    $genre_songs_count_query = "SELECT COUNT(*) as total FROM songs WHERE genre_id = ? AND is_active = 1";
    $genre_count_stmt = $conn->prepare($genre_songs_count_query);
    $genre_count_stmt->execute([$genre_id]);
    $total_genre_songs = $genre_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_genre_pages = ceil($total_genre_songs / $limit);
}

// Jika tidak ada search dan tidak ada genre filter, get featured albums
if (empty($search_query) && $genre_id == 0) {
    $featured_albums_query = "SELECT al.*, 
                              (SELECT COUNT(*) FROM songs WHERE album_id = al.id AND is_active = 1) as song_count,
                              (SELECT GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') 
                               FROM songs s 
                               LEFT JOIN artists a ON s.artist_id = a.id 
                               WHERE s.album_id = al.id) as artists
                              FROM albums al 
                              WHERE al.is_active = 1 
                              ORDER BY al.release_year DESC 
                              LIMIT 12";
    $featured_albums_stmt = $conn->prepare($featured_albums_query);
    $featured_albums_stmt->execute();
    $featured_albums = $featured_albums_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get genre name if filtering by genre
$genre_name = '';
if ($genre_id > 0) {
    foreach ($genres as $genre) {
        if ($genre['id'] == $genre_id) {
            $genre_name = $genre['name'];
            break;
        }
    }
}

// Function to get album cover images for 4-grid
function getAlbumCoverGrid($album_id, $conn) {
    $songs_query = "SELECT cover_image FROM songs WHERE album_id = ? AND cover_image IS NOT NULL AND cover_image != 'default-cover.png' LIMIT 4";
    $stmt = $conn->prepare($songs_query);
    $stmt->execute([$album_id]);
    $covers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $covers;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php 
        if (!empty($search_query)) {
            echo 'Search: "' . htmlspecialchars($search_query) . '" - ' . SITE_NAME;
        } elseif ($genre_id > 0) {
            echo htmlspecialchars($genre_name) . ' - Browse - ' . SITE_NAME;
        } else {
            echo 'Browse Music - ' . SITE_NAME;
        }
        ?>
    </title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/browse.css">
    <link rel="stylesheet" href="assets/css/playlist_detail.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/player.css">
    <link rel="stylesheet" href="assets/css/header-search.css">
    <style>
        /* FIX FOR LIKE BUTTON */
        .like-btn {
            background: transparent !important;
            border: none !important;
            padding: 8px !important;
            min-width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
        }
        
        .like-btn:hover {
            background-color: rgba(255, 255, 255, 0.1) !important;
        }
        
        .like-btn.liked {
            color: #ff3850 !important;
        }
        
        .like-btn i {
            font-size: 18px !important;
        }
        
        /* Highlight song with anchor */
        .song-item.highlighted {
            background-color: rgba(30, 215, 96, 0.1) !important;
            border-left: 4px solid #1ed760 !important;
        }
        
        .song-item.highlighted .song-info h4 {
            color: #1ed760 !important;
        }
    </style>
</head>
<body data-theme="<?php echo getCurrentTheme(); ?>">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="content">            
            <?php if (!empty($search_query)): ?>
                <!-- Search Results Section -->
                <section class="section">
                    <div class="section-header">
                        <h2>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h2>
                        <div class="results-count-badge">
                          </i> <?php echo number_format($total_search_results); ?> results
                        </div>
                    </div>
                    
                    <?php if (!empty($search_results)): ?>
                        <!-- Songs Results -->
                        <div class="subsection">
                            <h3 class="subsection-title">
                                </i> Songs (<?php echo count($search_results); ?>)
                            </h3>
                            <div class="songs-list">
                                <?php foreach ($search_results as $index => $song): 
                                    $is_liked = in_array($song['id'], $liked_songs);
                                    $is_highlighted = ($anchor_id == $song['id']);
                                ?>
                                <div class="song-item <?php echo $is_highlighted ? 'highlighted' : ''; ?>" 
                                     data-song-id="<?php echo $song['id']; ?>" 
                                     data-index="<?php echo $offset + $index; ?>"
                                     id="song-<?php echo $song['id']; ?>">
                                    <div class="song-cover">
                                        <img src="<?php echo getCoverPath($song['cover_image'], 'song'); ?>" 
                                             alt="<?php echo htmlspecialchars($song['title']); ?>">
                                        <button class="play-btn" data-song-id="<?php echo $song['id']; ?>">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="song-info">
                                        <h4><?php echo htmlspecialchars($song['title']); ?></h4>
                                        <p><?php echo htmlspecialchars($song['artist_name']); ?></p>
                                        <div class="song-meta">
                                            <?php if ($song['album_title']): ?>
                                            <span class="album"><?php echo htmlspecialchars($song['album_title']); ?></span>
                                            <?php endif; ?>
                                            <span class="genre"><?php echo htmlspecialchars($song['genre_name']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="song-duration"><?php echo formatDuration($song['duration']); ?></div>
                                    
                                    <div class="song-actions">
                                        <button class="like-btn <?php echo $is_liked ? 'liked' : ''; ?>" 
                                                data-song-id="<?php echo $song['id']; ?>">
                                            <i class="<?php echo $is_liked ? 'fas' : 'far'; ?> fa-heart"></i>
                                        </button>
                                        
                                        <button class="more-btn song-more-btn" data-index="<?php echo $offset + $index; ?>">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        
                                        <div class="song-dropdown" id="songDropdown-<?php echo $offset + $index; ?>">
                                            <button class="dropdown-item like-song <?php echo $is_liked ? 'liked' : ''; ?>" 
                                                    data-song-id="<?php echo $song['id']; ?>">
                                                <i class="<?php echo $is_liked ? 'fas' : 'far'; ?> fa-heart"></i> 
                                                <?php echo $is_liked ? 'Unlike' : 'Like'; ?>
                                            </button>
                                            
                                            <button class="dropdown-item add-to-queue" data-song-id="<?php echo $song['id']; ?>">
                                                <i class="fas fa-list"></i> Add to Queue
                                            </button>
                                            
                                            <div class="dropdown-submenu">
                                                <button class="dropdown-item submenu-trigger">
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
                        </div>
                        
                        <?php if ($total_search_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                            <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                            <?php endif; ?>
                            
                            <div class="page-numbers">
                                <?php for ($i = 1; $i <= $total_search_pages; $i++): ?>
                                    <?php if ($i == 1 || $i == $total_search_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>" 
                                           class="page-number <?php echo $i == $page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                        <span class="page-ellipsis">...</span>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            
                            <?php if ($page < $total_search_pages): ?>
                            <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>" class="page-link">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="no-content">
                            <i class="fas fa-search fa-3x"></i>
                            <h3>No songs found</h3>
                            <p>Try searching for something else or browse by genre.</p>
                            <a href="browse.php" class="btn-primary" style="margin-top: 1rem;">Browse All Genres</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($album_results)): ?>
                        <!-- Albums Results -->
                        <div class="subsection" style="margin-top: 3rem;">
                            <h3 class="subsection-title">
                         </i> Albums (<?php echo count($album_results); ?>)
                            </h3>
                            <div class="albums-grid">
                                <?php foreach ($album_results as $album): 
                                    $album_covers = getAlbumCoverGrid($album['id'], $conn);
                                ?>
                                <a href="album_detail.php?id=<?php echo $album['id']; ?>" class="album-card">
                                    <div class="album-cover">
                                        <?php if (!empty($album_covers) && count($album_covers) >= 4): ?>
                                        <div class="album-cover-grid">
                                            <?php for ($i = 0; $i < 4; $i++): ?>
                                            <div class="cover-cell">
                                                <img src="<?php echo getCoverPath($album_covers[$i], 'song'); ?>" 
                                                     alt="Album cover <?php echo $i + 1; ?>">
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                        <?php else: ?>
                                            <img src="<?php echo getCoverPath($album['cover_image'], 'album'); ?>" 
                                                 alt="<?php echo htmlspecialchars($album['title']); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="album-info">
                                        <h4><?php echo htmlspecialchars($album['title']); ?></h4>
                                        <p class="album-artists">
                                            <?php 
                                            if (!empty($album['artist_name'])) {
                                                echo htmlspecialchars($album['artist_name']);
                                            } else {
                                                echo 'Various Artists';
                                            }
                                            ?>
                                        </p>
                                        <div class="album-meta">
                                            <span class="song-count">
                                                <i class="fas fa-music"></i> <?php echo $album['song_count']; ?> songs
                                            </span>
                                            <?php if ($album['release_year']): ?>
                                            <span class="release-year">
                                                <?php echo $album['release_year']; ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                </section>
                
            <?php elseif ($genre_id > 0): ?>
                <!-- Genre Songs View -->
                <section class="section">
                    <div class="section-header">
                        <h2><?php echo htmlspecialchars($genre_name); ?> Songs</h2>
                        <a href="browse.php" class="view-all">
                            <i class="fas fa-arrow-left"></i> All Genres
                        </a>
                    </div>
                    
                    <?php if (!empty($genre_songs)): ?>
                        <div class="songs-list">
                            <?php foreach ($genre_songs as $index => $song): 
                                $is_liked = in_array($song['id'], $liked_songs);
                            ?>
                            <div class="song-item" data-song-id="<?php echo $song['id']; ?>" data-index="<?php echo $offset + $index; ?>">
                                <div class="song-cover">
                                    <img src="<?php echo getCoverPath($song['cover_image'], 'song'); ?>" 
                                         alt="<?php echo htmlspecialchars($song['title']); ?>">
                                    <button class="play-btn" data-song-id="<?php echo $song['id']; ?>">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </div>
                                
                                <div class="song-info">
                                    <h4><?php echo htmlspecialchars($song['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($song['artist_name']); ?></p>
                                    <div class="song-meta">
                                        <?php if ($song['album_title']): ?>
                                        <span class="album"><?php echo htmlspecialchars($song['album_title']); ?></span>
                                        <?php endif; ?>
                                        <span class="genre"><?php echo htmlspecialchars($song['genre_name']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="song-duration"><?php echo formatDuration($song['duration']); ?></div>
                                
                                <div class="song-actions">
                                    <button class="like-btn <?php echo $is_liked ? 'liked' : ''; ?>" 
                                            data-song-id="<?php echo $song['id']; ?>">
                                        <i class="<?php echo $is_liked ? 'fas' : 'far'; ?> fa-heart"></i>
                                    </button>
                                    
                                    <button class="more-btn song-more-btn" data-index="<?php echo $offset + $index; ?>">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    
                                    <div class="song-dropdown" id="songDropdown-<?php echo $offset + $index; ?>">
                                        <button class="dropdown-item like-song <?php echo $is_liked ? 'liked' : ''; ?>" 
                                                data-song-id="<?php echo $song['id']; ?>">
                                            <i class="<?php echo $is_liked ? 'fas' : 'far'; ?> fa-heart"></i> 
                                            <?php echo $is_liked ? 'Unlike' : 'Like'; ?>
                                        </button>
                                        
                                        <button class="dropdown-item add-to-queue" data-song-id="<?php echo $song['id']; ?>">
                                            <i class="fas fa-list"></i> Add to Queue
                                        </button>
                                        
                                        <div class="dropdown-submenu">
                                            <button class="dropdown-item submenu-trigger">
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
                        
                        <?php if ($total_genre_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                            <a href="?genre=<?php echo $genre_id; ?>&page=<?php echo $page - 1; ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                            <?php endif; ?>
                            
                            <div class="page-numbers">
                                <?php for ($i = 1; $i <= $total_genre_pages; $i++): ?>
                                    <?php if ($i == 1 || $i == $total_genre_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <a href="?genre=<?php echo $genre_id; ?>&page=<?php echo $i; ?>" 
                                           class="page-number <?php echo $i == $page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                        <span class="page-ellipsis">...</span>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            
                            <?php if ($page < $total_genre_pages): ?>
                            <a href="?genre=<?php echo $genre_id; ?>&page=<?php echo $page + 1; ?>" class="page-link">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="no-content">
                            <i class="fas fa-music fa-3x"></i>
                            <h3>No songs found</h3>
                            <p>There are no songs in this genre yet.</p>
                        </div>
                    <?php endif; ?>
                </section>
                
            <?php else: ?>
                <!-- Default Browse View (No search, no genre filter) -->
                
                <!-- Browse by Genre Section -->
                <section class="section">
                    <div class="section-header">
                        <h2>Browse by Genre</h2>
                    </div>
                    
                    <div class="genres-grid-simple">
                        <?php foreach ($genres as $genre): ?>
                        <a href="browse.php?genre=<?php echo $genre['id']; ?>" class="genre-card-simple" style="--genre-color: <?php echo $genre['color']; ?>">
                            <span class="genre-name"><?php echo htmlspecialchars($genre['name']); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                
                <!-- Featured Albums Section -->
                <?php if (!empty($featured_albums)): ?>
                <section class="section">
                    <div class="section-header">
                        <h2>Featured Albums</h2>
                    </div>
                    
                    <div class="albums-grid">
                        <?php foreach ($featured_albums as $album): 
                            $album_covers = getAlbumCoverGrid($album['id'], $conn);
                        ?>
                        <a href="album_detail.php?id=<?php echo $album['id']; ?>" class="album-card">
                            <div class="album-cover">
                                <?php if (!empty($album_covers) && count($album_covers) >= 4): ?>
                                <div class="album-cover-grid">
                                    <?php for ($i = 0; $i < 4; $i++): ?>
                                    <div class="cover-cell">
                                        <img src="<?php echo getCoverPath($album_covers[$i], 'song'); ?>" 
                                             alt="Album cover <?php echo $i + 1; ?>">
                                    </div>
                                    <?php endfor; ?>
                                </div>
                                <?php else: ?>
                                    <img src="<?php echo getCoverPath($album['cover_image'], 'album'); ?>" 
                                         alt="<?php echo htmlspecialchars($album['title']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="album-info">
                                <h4><?php echo htmlspecialchars($album['title']); ?></h4>
                                <p class="album-artists">
                                    <?php 
                                    if (!empty($album['artists'])) {
                                        $artists = explode(', ', $album['artists']);
                                        echo htmlspecialchars($artists[0] . (count($artists) > 1 ? ' & more' : ''));
                                    } else {
                                        echo 'Various Artists';
                                    }
                                    ?>
                                </p>
                                <div class="album-meta">
                                    <span class="song-count">
                                        <i class="fas fa-music"></i> <?php echo $album['song_count']; ?> songs
                                    </span>
                                    <?php if ($album['release_year']): ?>
                                    <span class="release-year">
                                        <?php echo $album['release_year']; ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
                
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
                        <label for="playlistTitle">Playlist Title *</label>
                        <input type="text" id="playlistTitle" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="playlistDescription">Description (Optional)</label>
                        <textarea id="playlistDescription" name="description" rows="3"></textarea>
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
    <script src="assets/js/browse.js"></script>
    <script src="assets/js/header-search.js"></script>
    <script src="assets/js/likes.js"></script>
    
    <script>
    // Scroll to highlighted song if anchor exists
    document.addEventListener('DOMContentLoaded', function() {
        const highlightedSong = document.querySelector('.song-item.highlighted');
        if (highlightedSong) {
            setTimeout(() => {
                highlightedSong.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 500);
        }
        
        // Handle like buttons
        document.querySelectorAll('.like-btn').forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const songId = this.getAttribute('data-song-id');
                const isLiked = this.classList.contains('liked');
                
                try {
                    const response = await fetch('api/likes.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            song_id: parseInt(songId),
                            action: isLiked ? 'unlike' : 'like'
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Update this button
                        const icon = this.querySelector('i');
                        if (data.is_liked) {
                            this.classList.add('liked');
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                        } else {
                            this.classList.remove('liked');
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                        }
                        
                        // Update dropdown item
                        const dropdownItem = document.querySelector(`.like-song[data-song-id="${songId}"]`);
                        if (dropdownItem) {
                            const dropdownIcon = dropdownItem.querySelector('i');
                            if (data.is_liked) {
                                dropdownItem.classList.add('liked');
                                dropdownIcon.classList.remove('far');
                                dropdownIcon.classList.add('fas');
                                dropdownItem.innerHTML = '<i class="fas fa-heart"></i> Unlike';
                            } else {
                                dropdownItem.classList.remove('liked');
                                dropdownIcon.classList.remove('fas');
                                dropdownIcon.classList.add('far');
                                dropdownItem.innerHTML = '<i class="far fa-heart"></i> Like';
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            });
        });
    });
    </script>
</body>
</html>