<?php
require_once 'baby_capstone_connection.php';

// Admin user details
$name = "Administrator";
$email = "admin@gmail.com";
$password = "admin123"; // Plain text password
$role = "super_admin";
$status = "active";

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // First check if the admin_users table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if ($tableCheck->rowCount() == 0) {
        // Table doesn't exist, create it
        $pdo->exec("CREATE TABLE `admin_users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `email` varchar(100) NOT NULL,
            `password` varchar(255) NOT NULL,
            `role` enum('admin','super_admin') NOT NULL DEFAULT 'admin',
            `status` enum('active','inactive') NOT NULL DEFAULT 'active',
            `last_login` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        echo "Created admin_users table.<br>";
    }
    
    // Check if admin_logs table exists
    $logsTableCheck = $pdo->query("SHOW TABLES LIKE 'admin_logs'");
    if ($logsTableCheck->rowCount() == 0) {
        // Table doesn't exist, create it
        $pdo->exec("CREATE TABLE `admin_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `admin_id` int(11) NOT NULL,
            `action` varchar(100) NOT NULL,
            `details` text DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `admin_id` (`admin_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        echo "Created admin_logs table.<br>";
    }

    // Check if admin with this email already exists
    $check_stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
    $check_stmt->execute([$email]);
    
    if ($check_stmt->fetch()) {
        // Admin exists, update instead
        $stmt = $pdo->prepare("UPDATE admin_users SET name = ?, password = ?, role = ?, status = ?, updated_at = NOW() WHERE email = ?");
        $stmt->execute([$name, $hashed_password, $role, $status, $email]);
        echo "Admin user updated successfully!<br>";
    } else {
        // Admin doesn't exist, insert new record
        $stmt = $pdo->prepare("INSERT INTO admin_users (name, email, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$name, $email, $hashed_password, $role, $status]);
        echo "Admin user created successfully!<br>";
    }
    
    // Display the admin credentials for reference
    echo "<hr>";
    echo "<h3>Admin Credentials:</h3>";
    echo "<p><strong>Email:</strong> $email</p>";
    echo "<p><strong>Password:</strong> $password</p>";
    echo "<p><strong>Password Hash:</strong> $hashed_password</p>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
?>
