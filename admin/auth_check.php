<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    // Not logged in as admin, redirect to login page
    $_SESSION['login_error'] = "You must be logged in as an administrator to access this page.";
    header("Location: ../index.php");
    exit();
}

// Check for session timeout
if (isset($_SESSION['admin_last_activity']) && isset($_SESSION['admin_expire_time'])) {
    $inactive_time = time() - $_SESSION['admin_last_activity'];
    
    if ($inactive_time > $_SESSION['admin_expire_time']) {
        // Session has expired, destroy session and redirect to login
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['login_error'] = "Your session has expired. Please log in again.";
        header("Location: ../index.php");
        exit();
    }
    
    // Update last activity time
    $_SESSION['admin_last_activity'] = time();
}
?>
