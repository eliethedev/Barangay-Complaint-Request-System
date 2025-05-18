<?php
require_once '../baby_capstone_connection.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM sms_notifications");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
