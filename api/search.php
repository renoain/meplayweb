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
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Empty search query']);
    exit();
}

try {
    $search_query = "SELECT s.*, a.name as artist_name, al.title as album_title, g.name as genre_name 
                     FROM songs s 
                     LEFT JOIN artists a ON s.artist_id = a.id 
                     LEFT JOIN albums al ON s.album_id = al.id 
                     LEFT JOIN genres g ON s.genre_id = g.id 
                     WHERE s.is_active = 1 
                     AND (s.title LIKE ? OR a.name LIKE ? OR al.title LIKE ? OR g.name LIKE ?)
                     ORDER BY s.play_count DESC, s.created_at DESC 
                     LIMIT ?";
    
    $search_param = "%$query%";
    $stmt = $conn->prepare($search_query);
    $stmt->execute([$search_param, $search_param, $search_param, $search_param, $limit]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'results' => $results]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>