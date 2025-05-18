<?php
session_start();
require_once 'baby_capstone_connection.php';

// Display all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection Test</h1>";

// Test database connection
try {
    $test = $pdo->query("SELECT 1");
    echo "<p style='color:green'>Database connection successful!</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>Database connection failed: " . $e->getMessage() . "</p>";
}

// Check if complaints table exists
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'complaints'")->fetchAll();
    if (count($tables) > 0) {
        echo "<p style='color:green'>Complaints table exists!</p>";
        
        // Show table structure
        echo "<h2>Complaints Table Structure:</h2>";
        $columns = $pdo->query("SHOW COLUMNS FROM complaints")->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($columns, true) . "</pre>";
    } else {
        echo "<p style='color:red'>Complaints table does not exist!</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>Error checking tables: " . $e->getMessage() . "</p>";
}

// Display session data
echo "<h2>Session Data:</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Display form submission data if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Data:</h2>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    echo "<h2>FILES Data:</h2>";
    echo "<pre>" . print_r($_FILES, true) . "</pre>";
}
?>
