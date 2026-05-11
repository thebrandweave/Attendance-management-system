<?php
include("../config/db.php");
date_default_timezone_set("Asia/Kolkata");

$time = date("H:i:s");
$today = date("Y-m-d");

if ($time >= "14:00:00") {

  $conn->query("
    UPDATE attendance 
    SET status = 'Absent'
    WHERE date = '$today'
    AND check_in IS NULL
  ");

}
?>