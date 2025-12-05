<?php
// api/playlists.php
header('Content-Type: application/json');
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        
        if ($action === 'get_user_playlists') {
            // Get user's playlists
            $query = "SELECT id, title, description, cover_image, created_at 
                     FROM playlists 
                     WHERE user_id = ? 
                     ORDER BY created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->execute([$user_id]);
            $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'playlists' => $playlists
            ]);
            exit();
        }
        
        if ($action === 'get_playlist_songs' && isset($_GET['id'])) {
            $playlist_id = intval($_GET['id']);
            
            // Verify ownership
            $check_query = "SELECT * FROM playlists WHERE id = ? AND user_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute([$playlist_id, $user_id]);
            
            if ($check_stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Playlist not found']);
                exit();
            }
            
            // Get songs
            $query = "SELECT s.*, a.name as artist_name 
                     FROM songs s 
                     LEFT JOIN artists a ON s.artist_id = a.id 
                     WHERE s.id IN (SELECT song_id FROM playlist_songs WHERE playlist_id = ?) 
                     AND s.is_active = 1 
                     ORDER BY s.title ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute([$playlist_id]);
            $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'songs' => $songs
            ]);
            exit();
        }
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['action'])) {
        echo json_encode(['success' => false, 'message' => 'No action specified']);
        exit();
    }
    
    $action = $data['action'];
    
    // Create playlist
    if ($action === 'create') {
        $title = sanitizeInput($data['title'] ?? '');
        $description = sanitizeInput($data['description'] ?? '');
        $song_id = isset($data['song_id']) ? intval($data['song_id']) : null;
        
        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Title is required']);
            exit();
        }
        
        try {
            $query = "INSERT INTO playlists (user_id, title, description, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute([$user_id, $title, $description])) {
                $playlist_id = $conn->lastInsertId();
                
                // Add song if provided
                if ($song_id) {
                    $add_query = "INSERT INTO playlist_songs (playlist_id, song_id) VALUES (?, ?)";
                    $add_stmt = $conn->prepare($add_query);
                    $add_stmt->execute([$playlist_id, $song_id]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Playlist created successfully',
                    'playlist_id' => $playlist_id
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create playlist']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Add song to playlist
    if ($action === 'add_song') {
        $playlist_id = isset($data['playlist_id']) ? intval($data['playlist_id']) : 0;
        $song_id = isset($data['song_id']) ? intval($data['song_id']) : 0;
        
        if (!$playlist_id || !$song_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit();
        }
        
        // Verify playlist ownership
        $check_query = "SELECT * FROM playlists WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->execute([$playlist_id, $user_id]);
        
        if ($check_stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Playlist not found']);
            exit();
        }
        
        // Check if song already in playlist
        $check_song_query = "SELECT * FROM playlist_songs WHERE playlist_id = ? AND song_id = ?";
        $check_song_stmt = $conn->prepare($check_song_query);
        $check_song_stmt->execute([$playlist_id, $song_id]);
        
        if ($check_song_stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Song already in playlist']);
            exit();
        }
        
        try {
            $query = "INSERT INTO playlist_songs (playlist_id, song_id) VALUES (?, ?)";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute([$playlist_id, $song_id])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Song added to playlist'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add song']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Remove song from playlist
    if ($action === 'remove_song') {
        $playlist_id = isset($data['playlist_id']) ? intval($data['playlist_id']) : 0;
        $song_id = isset($data['song_id']) ? intval($data['song_id']) : 0;
        
        if (!$playlist_id || !$song_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit();
        }
        
        // Verify playlist ownership
        $check_query = "SELECT * FROM playlists WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->execute([$playlist_id, $user_id]);
        
        if ($check_stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Playlist not found']);
            exit();
        }
        
        try {
            $query = "DELETE FROM playlist_songs WHERE playlist_id = ? AND song_id = ?";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute([$playlist_id, $song_id])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Song removed from playlist'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to remove song']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Delete playlist
    if ($action === 'delete') {
        $playlist_id = isset($data['playlist_id']) ? intval($data['playlist_id']) : 0;
        
        if (!$playlist_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid playlist ID']);
            exit();
        }
        
        // Verify ownership
        $check_query = "SELECT * FROM playlists WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->execute([$playlist_id, $user_id]);
        
        if ($check_stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Playlist not found']);
            exit();
        }
        
        try {
            $conn->beginTransaction();
            
            // Delete all songs from playlist
            $delete_songs = "DELETE FROM playlist_songs WHERE playlist_id = ?";
            $delete_songs_stmt = $conn->prepare($delete_songs);
            $delete_songs_stmt->execute([$playlist_id]);
            
            // Delete playlist
            $delete_playlist = "DELETE FROM playlists WHERE id = ? AND user_id = ?";
            $delete_playlist_stmt = $conn->prepare($delete_playlist);
            
            if ($delete_playlist_stmt->execute([$playlist_id, $user_id])) {
                $conn->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Playlist deleted successfully'
                ]);
            } else {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to delete playlist']);
            }
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>