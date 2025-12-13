<?php
// api/search.php
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Empty search query']);
    exit();
}

try {
    $search_param = "%$query%";
    $results = [];
    
    // Search songs
    $song_query = "SELECT s.*, a.name as artist_name, al.title as album_title, 
                   'song' as type
                   FROM songs s 
                   LEFT JOIN artists a ON s.artist_id = a.id 
                   LEFT JOIN albums al ON s.album_id = al.id 
                   WHERE s.is_active = 1 
                   AND (s.title LIKE ? OR a.name LIKE ? OR al.title LIKE ?)
                   ORDER BY s.play_count DESC, s.created_at DESC 
                   LIMIT 6";
    
    $stmt = $conn->prepare($song_query);
    $stmt->execute([$search_param, $search_param, $search_param]);
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search artists
    $artist_query = "SELECT a.*, 'artist' as type,
                     COUNT(s.id) as song_count
                     FROM artists a
                     LEFT JOIN songs s ON s.artist_id = a.id
                     WHERE a.is_active = 1 
                     AND a.name LIKE ?
                     GROUP BY a.id
                     ORDER BY a.name
                     LIMIT 4";
    
    $stmt = $conn->prepare($artist_query);
    $stmt->execute([$search_param]);
    $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search albums
    $album_query = "SELECT al.*, a.name as artist_name, 'album' as type,
                    COUNT(s.id) as song_count
                    FROM albums al
                    LEFT JOIN artists a ON al.artist_id = a.id
                    LEFT JOIN songs s ON s.album_id = al.id
                    WHERE al.is_active = 1 
                    AND (al.title LIKE ? OR a.name LIKE ?)
                    GROUP BY al.id
                    ORDER BY al.created_at DESC
                    LIMIT 4";
    
    $stmt = $conn->prepare($album_query);
    $stmt->execute([$search_param, $search_param]);
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search playlists
    $playlist_query = "SELECT p.*, u.username as creator_name, 'playlist' as type,
                       COUNT(ps.song_id) as song_count
                       FROM playlists p
                       LEFT JOIN users u ON p.user_id = u.id
                       LEFT JOIN playlist_songs ps ON p.id = ps.playlist_id
                       WHERE p.is_public = 1 
                       AND p.title LIKE ?
                       GROUP BY p.id
                       ORDER BY p.created_at DESC
                       LIMIT 4";
    
    $stmt = $conn->prepare($playlist_query);
    $stmt->execute([$search_param]);
    $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine all results
    $results = array_merge(
        $songs,
        $artists,
        $albums,
        $playlists
    );
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'counts' => [
            'songs' => count($songs),
            'artists' => count($artists),
            'albums' => count($albums),
            'playlists' => count($playlists)
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>