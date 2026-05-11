<?php
include("config/db.php");

$empId = "ADMIN001";
$password = "admin123";

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (name, employee_id, password, role) VALUES (?, ?, ?, 'admin')");
$name = "Admin";
$stmt->bind_param("sss", $name, $empId, $hash);
$stmt->execute();

echo "Admin created";
?>