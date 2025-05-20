<?php
require_once '../baby_capstone_connection.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM complaints WHERE id = ?");
        $stmt->execute([$id]);
        $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($complaint) {
            echo json_encode($complaint);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Complaint not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'ID is required']);
}
?>
