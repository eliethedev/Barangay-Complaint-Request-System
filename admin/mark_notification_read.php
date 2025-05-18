<?php
require_once '../baby_capstone_connection.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit;
}

$notification_id = intval($_GET['id']);

$sql = "UPDATE notifications SET read_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $notification_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
}
