<?php
// PHP script to check and add missing fields to the requests table

require_once '../baby_capstone_connection.php';

// Expected fields for the requests table
$expected_fields = [
    'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
    'user_id' => 'INT',
    'type' => 'VARCHAR(100)',
    'name' => 'VARCHAR(255)',
    'phone' => 'VARCHAR(20)',
    'details' => 'TEXT',
    'attachments' => 'TEXT',
    'status' => 'VARCHAR(50) DEFAULT "Pending"',
    'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
    'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'purpose' => 'VARCHAR(255)',
    'validity' => 'VARCHAR(100)',
    'location' => 'VARCHAR(255)',
    'urgency' => 'VARCHAR(50)',
    'parties' => 'TEXT',
    'mediation_date' => 'DATE',
    'assistance_type' => 'VARCHAR(100)',
    'beneficiaries' => 'TEXT',
    'payment_status' => 'VARCHAR(50) DEFAULT "pending"',
    'payment_method' => 'VARCHAR(50)',
    'payment_amount' => 'DECIMAL(10,2)',
    'payment_reference' => 'VARCHAR(100)',
    'proof_of_payment' => 'VARCHAR(255)',
    'admin_notes' => 'TEXT'
];

// Get current fields
$existing_fields = [];
try {
    $result = $pdo->query("DESCRIBE requests");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $existing_fields[$row['Field']] = true;
    }
    
    echo "<h1>Request Table Schema Check</h1>";
    echo "<h2>Existing Fields</h2>";
    echo "<ul>";
    foreach (array_keys($existing_fields) as $field) {
        echo "<li>$field</li>";
    }
    echo "</ul>";
    
    // Check for missing fields
    $missing_fields = [];
    foreach ($expected_fields as $field => $definition) {
        if (!isset($existing_fields[$field])) {
            $missing_fields[$field] = $definition;
        }
    }
    
    if (empty($missing_fields)) {
        echo "<p style='color:green;'>All required fields exist in the requests table.</p>";
    } else {
        echo "<h2>Missing Fields</h2>";
        echo "<ul>";
        foreach ($missing_fields as $field => $definition) {
            echo "<li>$field ($definition)</li>";
        }
        echo "</ul>";
        
        // Add missing fields
        echo "<h2>Adding Missing Fields</h2>";
        
        foreach ($missing_fields as $field => $definition) {
            try {
                $sql = "ALTER TABLE requests ADD COLUMN $field $definition";
                $pdo->exec($sql);
                echo "<p style='color:green;'>Added field: $field</p>";
            } catch (PDOException $e) {
                echo "<p style='color:red;'>Error adding field $field: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Check for any test request
    $stmt = $pdo->query("SELECT COUNT(*) FROM requests");
    $count = $stmt->fetchColumn();
    
    echo "<h2>Request Count</h2>";
    echo "<p>There are $count requests in the database.</p>";
    
    if ($count === 0) {
        // Create a sample request for testing
        echo "<h2>Creating Sample Request</h2>";
        
        $sql = "INSERT INTO requests (user_id, type, name, phone, details, purpose, payment_status) 
                VALUES (1, 'Document Request', 'Test User', '09123456789', 'Test request for debugging', 'Testing', 'pending')";
        $pdo->exec($sql);
        
        $id = $pdo->lastInsertId();
        echo "<p style='color:green;'>Created sample request with ID: $id</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

// Add a link to test the get_request_details.php endpoint
echo "<h2>Test Links</h2>";
echo "<ul>";
echo "<li><a href='test_request.php' target='_blank'>Test Request Details Page</a></li>";
echo "<li><a href='test_update.php' target='_blank'>Test Request Update Page</a></li>";
echo "</ul>"; 