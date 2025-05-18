<?php
// Include database connection and auth check
require_once '../baby_capstone_connection.php';
require_once 'auth_check.php';

// Set content type to JSON
header('Content-Type: application/json');
    
// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Resident ID is required']);
    exit;
}

// Sanitize input
$resident_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

try {
    // Prepare and execute query
    $stmt = $pdo->prepare("SELECT id, full_name, email, phone, address, gender, birthdate, age, profile_pic, status, created_at, is_voter, is_pwd FROM users WHERE id = ?");
    $stmt->execute([$resident_id]);
    
    // Fetch resident data
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resident) {
        http_response_code(404);
        echo json_encode(['error' => 'Resident not found']);
        exit;
    }
    
    // Return resident data as JSON
    echo json_encode($resident);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>
