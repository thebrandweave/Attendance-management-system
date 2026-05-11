<?php
include("../config/db.php");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id'])) {

    $id = intval($_GET['id']);

    // Delete attendance first
    $conn->query("DELETE FROM attendance WHERE user_id=$id");

    // Delete employee
    $conn->query("DELETE FROM users WHERE id=$id AND role='employee'");

    header("Location: dashboard.php");
    exit();
}
?>