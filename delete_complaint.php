<?php
session_start();
require_once 'baby_capstone_connection.php';

$id = intval(trim($_POST['id']));

try {
    $delete = $pdo->prepare("DELETE FROM complaints WHERE id = ?");
    $delete->execute([$id]);

    if ($delete->rowCount() > 0) {
        header("Location: user_dashboard.php?status=complaint_deleted");
    } else {
        header("Location: user_dashboard.php?status=complaint_not_found");
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
$id = intval(trim($_POST['id']));

try {
    $delete = $pdo->prepare("DELETE FROM requests WHERE id = ?");
    $delete->execute([$id]);

    if ($delete->rowCount() > 0) {
        header("Location: user_dashboard.php?status=request_deleted");
    } else {
        header("Location: user_dashboard.php?status=request_not_found");
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>