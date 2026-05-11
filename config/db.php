


<?php
$host = "localhost"; // or your hosting MySQL host
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