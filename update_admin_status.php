<?php
require_once 'baby_capstone_connection.php';

try {
    // Update admin user status and last_login
    $stmt = $pdo->prepare("UPDATE admin_users SET status = 'active', last_login = NOW() WHERE email = 'admin@barangay.gov.ph'");
    $stmt->execute();
    
    echo "Admin user status updated successfully!\n";
} catch (PDOException $e) {
    echo "Error updating admin user: " . $e->getMessage() . "\n";
}
?>
