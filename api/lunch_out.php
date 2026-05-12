<?php

session_start();

include("../config/db.php");

date_default_timezone_set("Asia/Kolkata");

$id = $_GET['id'];

$today = date("Y-m-d");

$stmt = $conn->prepare("
UPDATE attendance
SET lunch_out = NOW()
WHERE user_id = ?
AND date = ?
");

$stmt->bind_param("is", $id, $today);

$stmt->execute();

header("Location: ../admin/dashboard.php");