<?php
/*
=====================================
LOCALHOST DATABASE
=====================================

$host = "localhost";
$user = "root";
$pass = "";
$db = "attendance";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
*/


/*
=====================================
LIVE HOSTING DATABASE
=====================================
*/

$host = "localhost";
$user = "u232955123_attendance";
$pass = "Brandweave@24";
$db = "u232955123_attendance";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>