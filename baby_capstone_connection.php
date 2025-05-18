    <?php
    // Database connection
    $host = 'localhost'; // Change as needed
    $db = 'baby_capstone'; // Change as needed
    $user = 'root'; // Change as needed
    $pass = ''; // Change as needed
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (\PDOException $e) {
        echo "DB connection failed: " . $e->getMessage();
        exit();
    }
    ?>