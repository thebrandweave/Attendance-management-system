<?php
include("../config/db.php");
session_start();

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user'])) {
  exit("Not logged in");
}

$empCode = $_POST['emp_id'];
$today = date("Y-m-d");
$now = date("H:i:s");

// get user by employee_id
$user = $conn->query("SELECT * FROM users WHERE employee_id='$empCode'")->fetch_assoc();

if (!$user) {
  exit("Invalid ID ❌");
}

$userId = $user['id'];

// already checked?
$check = $conn->query("
  SELECT * FROM attendance 
  WHERE user_id=$userId AND date='$today'
");

if ($check->num_rows > 0) {
  exit("Already checked in ❌");
}

$now = date("H:i:s");

if ($now >= "09:30:00" && $now <= "09:40:00") {

    $status = "Present";

} elseif ($now > "09:40:00" && $now <= "10:00:00") {

    $status = "Late";

} else {

    $status = "Half Day";
}

$conn->query("
  INSERT INTO attendance (user_id, date, check_in, status)
  VALUES ($userId, '$today', NOW(), '$status')
");

echo "Check-in successful ($status) ✅";