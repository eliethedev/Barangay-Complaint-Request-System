<?php
require_once '../baby_capstone_connection.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS sms_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('Bulk', 'Individual', 'Urgent') NOT NULL,
        recipients JSON NOT NULL,
        message TEXT NOT NULL,
        status ENUM('Pending', 'Sent', 'Failed', 'Scheduled') NOT NULL DEFAULT 'Pending',
        response TEXT,
        scheduled_time DATETIME,
        created_at DATETIME NOT NULL,
        updated_at DATETIME,
        INDEX idx_status (status),
        INDEX idx_type (type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "SMS notifications table created successfully";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
