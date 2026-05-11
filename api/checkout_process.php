<?php
include("../config/db.php");
session_start();

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user'])) {
  exit("Not logged in");
}

$empCode = $_POST['emp_id'];
$today = date("Y-m-d");

$user = $conn->query("SELECT * FROM users WHERE employee_id='$empCode'")->fetch_assoc();

if (!$user) {
  exit("Invalid ID ❌");
}

$userId = $user['id'];

$check = $conn->query("
  SELECT * FROM attendance 
  WHERE user_id=$userId AND date='$today'
")->fetch_assoc();

if (!$check) {
  exit("Check-in first ❌");
}

if ($check['check_out']) {
  exit("Already checked out ❌");
}

$conn->query("
  UPDATE attendance 
  SET check_out = NOW()
  WHERE user_id=$userId AND date='$today'
");

echo "Check-out successful ✅";