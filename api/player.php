<?php
// api/player.php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check authentication
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_song':
        getSong();
        break;
    case 'update_play_count':
        updatePlayCount();
        break;
    case 'add_recently_played':
        addRecentlyPlayed();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getSong() {
    global $db;
    
    $song_id = $_GET['id'] ?? $_POST['id'] ?? null;
    
    if (!$song_id) {
        echo json_encode(['success' => false, 'message' => 'Song ID is required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT s.*, 
                   a.name as artist_name,
                   al.cover_image as album_cover,
                   g.name as genre_name
            FROM songs s 
            LEFT JOIN artists a ON s.artist_id = a.id
            LEFT JOIN albums al ON s.album_id = al.id
            LEFT JOIN genres g ON s.genre_id = g.id
            WHERE s.id = ?
        ");
        $stmt->execute([$song_id]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($song) {
            // Ensure audio file path is correct
            if ($song['audio_file'] && !empty($song['audio_file'])) {
                echo json_encode([
                    'success' => true,
                    'song' => [
                        'id' => $song['id'],
                        'title' => $song['title'],
                        'artist_name' => $song['artist_name'] ?: 'Unknown Artist',
                        'cover_image' => $song['album_cover'] ?: 'default-cover.png',
                        'audio_file' => $song['audio_file'],
                        'duration' => $song['duration'] ?: 0
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Audio file not available']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Song not found']);
        }
    } catch (PDOException $e) {
        error_log("Get song error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function updatePlayCount() {
    global $db;
    
    $song_id = $_POST['song_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;
    }
}

function addRecentlyPlayed() {
    global $db;
    
    $song_id = $_POST['song_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$song_id || !$user_id) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }
    
    try {
        // Remove existing entry if exists
        $stmt = $db->prepare("DELETE FROM recently_played WHERE user_id = ? AND song_id = ?");
        $stmt->execute([$user_id, $song_id]);
        
        // Add new entry
        $stmt = $db->prepare("INSERT INTO recently_played (user_id, song_id, played_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $song_id]);
        
        // Keep only last 50 entries per user
        $stmt = $db->prepare("
            DELETE FROM recently_played 
            WHERE user_id = ? AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM recently_played 
                    WHERE user_id = ? 
                    ORDER BY played_at DESC 
                    LIMIT 50
                ) tmp
            )
        ");
        $stmt->execute([$user_id, $user_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Add recently played error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>