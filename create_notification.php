<?php
require_once 'baby_capstone_connection.php';

function createNotification($type, $message, $user_id = null) {
    global $pdo;
    $sql = "INSERT INTO notifications (type, message, user_id, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$type, $message, $user_id]);
}

// Example usage when a new complaint is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'])) {
    $type = $_POST['type'];
    $message = $_POST['message'];
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
    
    if (createNotification($type, $message, $user_id)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create notification']);
    }
}
