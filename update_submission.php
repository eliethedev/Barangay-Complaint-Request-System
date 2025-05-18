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
        $type = trim($_POST['type']);
        $details = trim($_POST['details']);
        $form_type = $_POST['form_type'];
        
        // Validate required fields
        if (empty($id) || empty($type) || empty($details) || empty($form_type)) {
            throw new Exception("All required fields must be filled out.");
        }
        
        // Make sure the user only edits their own submissions
        $user_id = $_SESSION['user_id'];
        
        if ($form_type === 'complaint') {
            // First verify ownership
            $check = $pdo->prepare("SELECT id FROM complaints WHERE id = ? AND user_id = ?");
            $check->execute([$id, $user_id]);
            
            if (!$check->fetch()) {
                throw new Exception("You don't have permission to edit this complaint.");
            }
            
            // Update the complaint
            $stmt = $pdo->prepare("UPDATE complaints SET type = ?, details = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$type, $details, $id, $user_id]);
        } elseif ($form_type === 'request') {
            // First verify ownership
            $check = $pdo->prepare("SELECT id FROM requests WHERE id = ? AND user_id = ?");
            $check->execute([$id, $user_id]);
            
            if (!$check->fetch()) {
                throw new Exception("You don't have permission to edit this request.");
            }
            
            // Update the request
            $stmt = $pdo->prepare("UPDATE requests SET type = ?, details = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$type, $details, $id, $user_id]);
        } else {
            throw new Exception("Invalid submission type.");
        }
        
        $_SESSION['success'] = "Your submission has been updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: user_dashboard.php");
    exit;
}
?>
