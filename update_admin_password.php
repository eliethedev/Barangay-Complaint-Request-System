<?php
require_once 'baby_capstone_connection.php';

// The password we want to hash
$password = 'Admin@123';

// Generate the hash
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Update the admin user's password
    $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE email = 'admin@barangay.gov.ph'");
    $stmt->execute([$hashed_password]);
    
    echo "Admin password has been successfully updated!\n";
    echo "New password hash: " . $hashed_password . "\n";
} catch (PDOException $e) {
    echo "Error updating password: " . $e->getMessage() . "\n";
}
?>
