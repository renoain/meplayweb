 <?php
// api/songs.php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : '';
    $song_id = isset($input['song_id']) ? intval($input['song_id']) : 0;
    
    if ($action === 'increment_play_count' && $song_id > 0) {
        try {
            $query = "UPDATE songs SET play_count = play_count + 1 WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$song_id]);
            
            // Add to recently played
            $user_id = $_SESSION['user_id'];
            $recent_query = "INSERT INTO recently_played (user_id, song_id) VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE played_at = CURRENT_TIMESTAMP";
            $recent_stmt = $conn->prepare($recent_query);
            $recent_stmt->execute([$user_id, $song_id]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    // GET request - get song details
    if (isset($_GET['id'])) {
        $song_id = intval($_GET['id']);
        
        try {
            $query = "SELECT s.*, a.name as artist_name, al.title as album_title, g.name as genre_name 
                      FROM songs s 
                      LEFT JOIN artists a ON s.artist_id = a.id 
                      LEFT JOIN albums al ON s.album_id = al.id 
                      LEFT JOIN genres g ON s.genre_id = g.id 
                      WHERE s.id = ? AND s.is_active = 1";
            $stmt = $conn->prepare($query);
            $stmt->execute([$song_id]);
            $song = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($song) {
                echo json_encode(['success' => true, 'song' => $song]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Song not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No song ID provided']);
    }
}
?>