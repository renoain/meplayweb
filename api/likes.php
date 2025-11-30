<?php
// api/likes.php
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
    $song_id = isset($input['song_id']) ? intval($input['song_id']) : 0;
    $action = isset($input['action']) ? $input['action'] : '';
    
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
            $insert_stmt->execute([$user_id, $song_id]);
            
            // Update likes count
            $update_query = "UPDATE songs SET likes_count = likes_count + 1 WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->execute([$song_id]);
            
            // Get new count
            $count_query = "SELECT likes_count FROM songs WHERE id = ?";
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->execute([$song_id]);
            $new_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['likes_count'];
            
            echo json_encode([
                'success' => true, 
                'message' => 'Song liked', 
                'new_count' => $new_count,
                'is_liked' => true
            ]);
            
        } elseif ($action === 'unlike') {
            // Remove like
            $delete_query = "DELETE FROM user_likes WHERE user_id = ? AND song_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->execute([$user_id, $song_id]);
            
            // Update likes count
            $update_query = "UPDATE songs SET likes_count = GREATEST(0, likes_count - 1) WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->execute([$song_id]);
            
            // Get new count
            $count_query = "SELECT likes_count FROM songs WHERE id = ?";
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->execute([$song_id]);
            $new_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['likes_count'];
            
            echo json_encode([
                'success' => true, 
                'message' => 'Song unliked', 
                'new_count' => $new_count,
                'is_liked' => false
            ]);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
} else {
    // GET request - check like status or get liked songs
    if (isset($_GET['song_id'])) {
        $song_id = intval($_GET['song_id']);
        
        $query = "SELECT id FROM user_likes WHERE user_id = ? AND song_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $song_id]);
        
        echo json_encode(['is_liked' => $stmt->fetch() !== false]);
        
    } elseif (isset($_GET['action']) && $_GET['action'] === 'get_liked_songs') {
        // Get all liked songs for the user
        $query = "SELECT s.*, a.name as artist_name, al.title as album_title, g.name as genre_name 
                  FROM songs s 
                  LEFT JOIN artists a ON s.artist_id = a.id 
                  LEFT JOIN albums al ON s.album_id = al.id 
                  LEFT JOIN genres g ON s.genre_id = g.id 
                  WHERE s.id IN (SELECT song_id FROM user_likes WHERE user_id = ?) 
                  AND s.is_active = 1 
                  ORDER BY user_likes.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id]);
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'songs' => $songs]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
}
?>