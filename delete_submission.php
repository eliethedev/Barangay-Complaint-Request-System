<?php
session_start();
require_once 'baby_capstone_connection.php';

// Security check - prevent unauthorized access
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// CSRF protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Security validation failed. Please try again.";
    header("Location: user_dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $category = $_POST['category'] ?? '';
        
        // Validate required fields
        if (empty($id) || empty($category)) {
            throw new Exception("Missing required information for deletion.");
        }
        
        // Make sure the user only deletes their own submissions
        $user_id = $_SESSION['user_id'];
        
        if ($category === 'complaint') {
            // First verify ownership
            $check = $pdo->prepare("SELECT id FROM complaints WHERE id = ? AND user_id = ?");
            $check->execute([$id, $user_id]);
            
            if (!$check->fetch()) {
                throw new Exception("You don't have permission to delete this complaint.");
            }
            
            // Delete the complaint
            $stmt = $pdo->prepare("DELETE FROM complaints WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            
            $_SESSION['success'] = "Complaint has been deleted successfully.";
        } elseif ($category === 'request') {
            // First verify ownership
            $check = $pdo->prepare("SELECT id FROM requests WHERE id = ? AND user_id = ?");
            $check->execute([$id, $user_id]);
            
            if (!$check->fetch()) {
                throw new Exception("You don't have permission to delete this request.");
            }
            
            // Delete the request
            $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            
            $_SESSION['success'] = "Request has been deleted successfully.";
        } else {
            throw new Exception("Invalid submission type.");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: user_dashboard.php");
    exit;
}
?>
