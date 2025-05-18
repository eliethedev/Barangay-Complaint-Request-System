<?php
// Database update script for complaints table
require_once 'baby_capstone_connection.php';

// Display all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Update Script</h1>";

// Check if complaints table exists
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'complaints'")->fetchAll();
    if (count($tables) > 0) {
        echo "<p>Complaints table exists! Checking structure...</p>";
    } else {
        echo "<p>Complaints table does not exist! Creating it now...</p>";
        
        // Create complaints table
        $pdo->exec("CREATE TABLE complaints (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(20),
            email VARCHAR(255),
            address TEXT,
            subject_type VARCHAR(255),
            subject VARCHAR(255),
            details TEXT NOT NULL,
            attachments TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            user_id INT,
            status VARCHAR(20) DEFAULT 'pending'
        )");
        
        echo "<p style='color:green'>Complaints table created successfully!</p>";
    }
    
    // Check if required columns exist
    $requiredColumns = [
        'id', 'type', 'name', 'phone', 'email', 'address', 
        'subject_type', 'subject', 'details', 'attachments', 
        'created_at', 'updated_at', 'user_id', 'status'
    ];
    
    $existingColumns = [];
    $columns = $pdo->query("SHOW COLUMNS FROM complaints")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        $existingColumns[] = $column['Field'];
    }
    
    echo "<p>Existing columns: " . implode(", ", $existingColumns) . "</p>";
    
    // Add missing columns
    $missingColumns = array_diff($requiredColumns, $existingColumns);
    if (!empty($missingColumns)) {
        echo "<p>Missing columns: " . implode(", ", $missingColumns) . "</p>";
        
        // Add each missing column
        foreach ($missingColumns as $column) {
            try {
                switch ($column) {
                    case 'email':
                        $pdo->exec("ALTER TABLE complaints ADD COLUMN email VARCHAR(255) AFTER phone");
                        break;
                    case 'subject_type':
                        $pdo->exec("ALTER TABLE complaints ADD COLUMN subject_type VARCHAR(255) AFTER address");
                        break;
                    case 'subject':
                        $pdo->exec("ALTER TABLE complaints ADD COLUMN subject VARCHAR(255) AFTER subject_type");
                        break;
                    case 'updated_at':
                        $pdo->exec("ALTER TABLE complaints ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
                        break;
                    default:
                        // Generic column addition
                        $pdo->exec("ALTER TABLE complaints ADD COLUMN $column VARCHAR(255)");
                }
                echo "<p style='color:green'>Added column: $column</p>";
            } catch (PDOException $e) {
                echo "<p style='color:red'>Error adding column $column: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        echo "<p style='color:green'>All required columns exist!</p>";
    }
    
    // Add indexes for better performance
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON complaints(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_status ON complaints(status)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_created_at ON complaints(created_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_subject_type ON complaints(subject_type)");
        echo "<p style='color:green'>Indexes created or already exist!</p>";
    } catch (PDOException $e) {
        echo "<p style='color:orange'>Note about indexes: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>Database update complete!</h2>";
    echo "<p>You can now <a href='submit_complaint.php'>return to the complaint form</a>.</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Database error: " . $e->getMessage() . "</p>";
}
?>
