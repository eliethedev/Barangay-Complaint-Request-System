<?php
session_start();
require_once '../baby_capstone_connection.php';

// Basic authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get limit parameter or use default
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$limit = min($limit, 50); // Cap at 50 to prevent excessive data loading

try {
    // Simple query to fetch requests
    $stmt = $pdo->prepare("
        SELECT id, user_id, type, name, status, created_at 
        FROM requests 
        ORDER BY id DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => count($requests),
        'requests' => $requests
    ]);
    
} catch (PDOException $e) {
    // Return error response
    echo json_encode([
        'error' => 'Database error',
        'message' => 'Failed to fetch requests list'
    ]);
} 