<?php
require_once '../baby_capstone_connection.php';

// Get the last notification ID from the request
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

// Get unread notifications
$sql = "SELECT n.*, u.name as user_name 
        FROM notifications n 
        LEFT JOIN users u ON n.user_id = u.id 
        WHERE n.read_at IS NULL 
        AND n.id > ? 
        ORDER BY n.created_at DESC 
        LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $last_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['id'],
        'type' => $row['type'],
        'message' => $row['message'],
        'created_at' => $row['created_at'],
        'user_name' => $row['user_name']
    ];
}

// Get total unread notifications count
$sql_count = "SELECT COUNT(*) as count FROM notifications WHERE read_at IS NULL";
$result_count = $conn->query($sql_count);
$count = $result_count->fetch_assoc()['count'];

header('Content-Type: application/json');
echo json_encode([
    'notifications' => $notifications,
    'count' => $count
]);
