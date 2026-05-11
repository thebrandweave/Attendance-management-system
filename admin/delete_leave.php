<?php
include("../config/db.php");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id'])) {

    $id = intval($_GET['id']);

    $conn->query("
        DELETE FROM leave_requests 
        WHERE id=$id
    ");

    header("Location: leave_requests.php");
    exit();
}
?>