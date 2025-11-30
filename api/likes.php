<?php
// api/likes.php
require_once '../config/constants.php';
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_SESSION['user_id'];

error_log("Likes API called: User ID: $user_id, Method: " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    error_log("POST input: " . print_r($input, true));
    
    $song_id = isset($input['song_id']) ? intval($input['song_id']) : 0;
    $action = isset($input['action']) ? $input['action'] : '';
    
    error_log("Like API Called: song_id=$song_id, action=$action, user_id=$user_id");
    
    if (!$song_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid song ID']);
        exit();
    }
    
    try {
        if ($action === 'like') {
            // Check if already liked
            $check_query = "SELECT id FROM user_likes WHERE user_id = ? AND song_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute([$user_id, $song_id]);
            
            if ($check_stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Song already liked']);
                exit();
            }
            
            // Add like
            $insert_query = "INSERT INTO user_likes (user_id, song_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $result = $insert_stmt->execute([$user_id, $song_id]);
            
            if ($result) {
                // Update likes count
                $update_query = "UPDATE songs SET likes_count = likes_count + 1 WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->execute([$song_id]);
                
                // Get updated likes count
                $count_query = "SELECT likes_count FROM songs WHERE id = ?";
                $count_stmt = $conn->prepare($count_query);
                $count_stmt->execute([$song_id]);
                $likes_count = $count_stmt->fetchColumn();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Song liked successfully',
                    'is_liked' => true,
                    'new_count' => $likes_count
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to like song']);
            }
            
        } elseif ($action === 'unlike') {
            // Remove like
            $delete_query = "DELETE FROM user_likes WHERE user_id = ? AND song_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $result = $delete_stmt->execute([$user_id, $song_id]);
            
            if ($result) {
                // Update likes count
                $update_query = "UPDATE songs SET likes_count = GREATEST(0, likes_count - 1) WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->execute([$song_id]);
                
                // Get updated likes count
                $count_query = "SELECT likes_count FROM songs WHERE id = ?";
                $count_stmt = $conn->prepare($count_query);
                $count_stmt->execute([$song_id]);
                $likes_count = $count_stmt->fetchColumn();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Song unliked successfully',
                    'is_liked' => false,
                    'new_count' => $likes_count
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to unlike song']);
            }
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        }
        
    } catch (PDOException $e) {
        error_log("Database error in likes.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
} else {
    // GET request - check like status or get liked songs
    if (isset($_GET['song_id'])) {
        $song_id = intval($_GET['song_id']);
        
        error_log("Checking like status for song: $song_id, user: $user_id");
        
        $query = "SELECT id FROM user_likes WHERE user_id = ? AND song_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $song_id]);
        
        $is_liked = $stmt->fetch() !== false;
        
        error_log("Like status for song $song_id: " . ($is_liked ? 'liked' : 'not liked'));
        
        echo json_encode(['success' => true, 'is_liked' => $is_liked]);
        
    } elseif (isset($_GET['action']) && $_GET['action'] === 'get_liked') {
        // Get all liked songs for the user
        $query = "SELECT s.*, a.name as artist_name, al.title as album_title, g.name as genre_name 
                  FROM songs s 
                  LEFT JOIN artists a ON s.artist_id = a.id 
                  LEFT JOIN albums al ON s.album_id = al.id 
                  LEFT JOIN genres g ON s.genre_id = g.id 
                  WHERE s.id IN (SELECT song_id FROM user_likes WHERE user_id = ?) 
                  AND s.is_active = 1 
                  ORDER BY s.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id]);
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'songs' => $songs]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method or parameters']);
    }
}
?>