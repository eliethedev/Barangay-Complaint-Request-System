<?php
// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'baby_capstone_connection.php';

// Get the request ID from the URL parameter
$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($request_id <= 0) {
    die("Please provide a valid request ID");
}

// Fetch the proof of payment path for the given request ID
$stmt = $pdo->prepare("SELECT proof_of_payment FROM requests WHERE id = ?");
$stmt->execute([$request_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result || empty($result['proof_of_payment'])) {
    die("No proof of payment found for request ID: $request_id");
}

$proof_path = $result['proof_of_payment'];

// Output information about the proof of payment
echo "<h2>Proof of Payment for Request ID: $request_id</h2>";
echo "<p>File path in database: " . htmlspecialchars($proof_path) . "</p>";

// Check if the file exists
$absolute_path = __DIR__ . '/' . $proof_path;
$file_exists = file_exists($absolute_path);
echo "<p>Absolute path: " . htmlspecialchars($absolute_path) . "</p>";
echo "<p>File exists: " . ($file_exists ? 'Yes' : 'No') . "</p>";

if ($file_exists) {
    // Get file information
    $file_size = filesize($absolute_path);
    $file_type = mime_content_type($absolute_path);
    $file_perms = substr(sprintf('%o', fileperms($absolute_path)), -4);
    
    echo "<p>File size: " . $file_size . " bytes</p>";
    echo "<p>File type: " . $file_type . "</p>";
    echo "<p>File permissions: " . $file_perms . "</p>";
    
    // Display the image
    echo "<h3>Image Preview:</h3>";
    echo "<img src='" . htmlspecialchars($proof_path) . "' alt='Proof of Payment' style='max-width: 500px; border: 1px solid #ccc;'>";
    
    // Direct link to the image
    echo "<p><a href='" . htmlspecialchars($proof_path) . "' target='_blank'>View full size image</a></p>";
} else {
    // Check the directory permissions
    $dir_path = dirname($absolute_path);
    $dir_exists = is_dir($dir_path);
    $dir_writable = is_writable($dir_path);
    $dir_perms = substr(sprintf('%o', fileperms($dir_path)), -4);
    
    echo "<p>Directory path: " . htmlspecialchars($dir_path) . "</p>";
    echo "<p>Directory exists: " . ($dir_exists ? 'Yes' : 'No') . "</p>";
    echo "<p>Directory writable: " . ($dir_writable ? 'Yes' : 'No') . "</p>";
    echo "<p>Directory permissions: " . $dir_perms . "</p>";
    
    // List files in the directory
    if ($dir_exists) {
        echo "<h3>Files in directory:</h3>";
        echo "<ul>";
        foreach (scandir($dir_path) as $file) {
            if ($file != '.' && $file != '..') {
                echo "<li>" . htmlspecialchars($file) . "</li>";
            }
        }
        echo "</ul>";
    }
}
?> 