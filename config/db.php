<?php

/*
=====================================
AUTO LOCALHOST / LIVE DB CONNECTION
=====================================
*/

if ($_SERVER['HTTP_HOST'] == "localhost" || $_SERVER['HTTP_HOST'] == "127.0.0.1") {

    /*
    =====================================
    LOCALHOST DATABASE
    =====================================
    */

    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "attendance";

} else {

    /*
    =====================================
    LIVE HOSTING DATABASE
    =====================================
    */

    $host = "localhost";
    $user = "u232955123_attendance";
    $pass = "Brandweave@24";
    $db   = "u232955123_attendance";
}


/*
=====================================
DATABASE CONNECTION
=====================================
*/

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


/*
=====================================
START SESSION
=====================================
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>