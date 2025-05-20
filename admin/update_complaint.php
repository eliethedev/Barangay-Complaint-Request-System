<?php
require_once '../baby_capstone_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['id'], $data['status'])) {
        try {
            $stmt = $pdo->prepare("UPDATE complaints 
                                  SET status = ?, resolution_notes = ?, updated_at = NOW()
                                  WHERE id = ?");
            $success = $stmt->execute([$data['status'], $data['resolution_notes'], $data['id']]);
            
            echo json_encode(['success' => $success]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
