<?php
session_start();
require_once '../baby_capstone_connection.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Basic authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    echo json_encode(['error' => 'Unauthorized', 'status' => 401]);
    exit();
}

// Get request ID from query parameter
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate request ID
if ($request_id <= 0) {
    echo json_encode(['error' => 'Invalid request ID', 'status' => 400]);
    exit();
}

try {
    // Simple direct query to fetch all request data
    $stmt = $pdo->prepare("
        SELECT * FROM requests WHERE id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if request exists
    if (!$request) {
        echo json_encode(['error' => 'Request not found', 'status' => 404, 'request_id' => $request_id]);
        exit();
    }

    // Get user details if needed
    $user_stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
    $user_stmt->execute([$request['user_id']]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Combine request and user data
    if ($user) {
        $request['full_name'] = $user['full_name'];
        $request['user_email'] = $user['email'];
        $request['user_phone'] = $user['phone'];
    } else {
        // If user not found, add default values to prevent JS errors
        $request['full_name'] = $request['name'] ?? 'Unknown';
        $request['user_email'] = 'N/A';
        $request['user_phone'] = $request['phone'] ?? 'N/A';
    }

    // Add debug info in development mode
    if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
        $request['_debug'] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'query' => 'SELECT * FROM requests WHERE id = ?',
            'params' => [$request_id]
        ];
    }
    
    // Return request data as JSON
    header('Content-Type: application/json');
    echo json_encode($request);
    
} catch (PDOException $e) {
    // Log the error for server records
    error_log('Database error in get_request_details.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'error' => 'Database error', 
        'status' => 500,
        'message' => defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE ? $e->getMessage() : 'An internal error occurred'
    ]);
} 