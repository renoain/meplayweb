<?php
// api/playlists.php
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
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : '';
    
    try {
        if ($action === 'create') {
            $title = isset($input['title']) ? trim($input['title']) : '';
            $description = isset($input['description']) ? trim($input['description']) : '';
            $song_id = isset($input['song_id']) ? intval($input['song_id']) : null;
            
            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Playlist title is required']);
                exit();
            }
            
            // Create playlist
            $query = "INSERT INTO playlists (user_id, title, description) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->execute([$user_id, $title, $description]);
            $playlist_id = $conn->lastInsertId();
            
            // Add song to playlist if provided
            if ($song_id) {
                $add_query = "INSERT INTO playlist_songs (playlist_id, song_id) VALUES (?, ?)";
                $add_stmt = $conn->prepare($add_query);
                $add_stmt->execute([$playlist_id, $song_id]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Playlist created', 'playlist_id' => $playlist_id]);
            
        } elseif ($action === 'add_song') {
            $playlist_id = isset($input['playlist_id']) ? intval($input['playlist_id']) : 0;
            $song_id = isset($input['song_id']) ? intval($input['song_id']) : 0;
            
            if (!$playlist_id || !$song_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid playlist or song ID']);
                exit();
            }
            
            // Verify playlist ownership
            $verify_query = "SELECT id FROM playlists WHERE id = ? AND user_id = ?";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->execute([$playlist_id, $user_id]);
            
            if (!$verify_stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Playlist not found']);
                exit();
            }
            
            // Check if song already in playlist
            $check_query = "SELECT id FROM playlist_songs WHERE playlist_id = ? AND song_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute([$playlist_id, $song_id]);
            
            if ($check_stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Song already in playlist']);
                exit();
            }
            
            // Add song to playlist
            $insert_query = "INSERT INTO playlist_songs (playlist_id, song_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->execute([$playlist_id, $song_id]);
            
            echo json_encode(['success' => true, 'message' => 'Song added to playlist']);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
} else {
    // GET request
    if (isset($_GET['action']) && $_GET['action'] === 'get_user_playlists') {
        $query = "SELECT id, title, description, cover_image, created_at 
                  FROM playlists 
                  WHERE user_id = ? 
                  ORDER BY created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id]);
        $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'playlists' => $playlists]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
}
?>