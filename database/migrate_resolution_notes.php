<?php
// Include database connection
require_once '../../baby_capstone_connection.php';

try {
    // Add resolution_notes column to complaints table if it doesn't exist
    $pdo->exec("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS resolution_notes TEXT");
    
    // Add resolution_notes column to requests table if it doesn't exist
    $pdo->exec("ALTER TABLE requests ADD COLUMN IF NOT EXISTS resolution_notes TEXT");
    
    echo "Migration completed successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
