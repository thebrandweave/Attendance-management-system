<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

include("../config/db.php");

date_default_timezone_set("Asia/Kolkata");

$today = date("Y-m-d");

$currentTime = date("H:i");

/*
==================================================
RUN ONLY AFTER 9:00 PM
==================================================
*/

if ($currentTime >= "21:00") {

    /*
    ==================================================
    FIND EMPLOYEES
    WHO CHECKED IN
    BUT DID NOT CHECK OUT
    ==================================================
    */

    $query = $conn->query("
        SELECT *
        FROM attendance
        WHERE date = '$today'
        AND check_in IS NOT NULL
        AND (
            check_out IS NULL
            OR check_out = ''
        )
    ");

    while ($row = $query->fetch_assoc()) {

        $attendanceId = $row['id'];

        /*
        ==================================================
        AUTO CHECKOUT TIME = 5:30 PM
        ==================================================
        */

        $autoCheckoutTime =
            $today . " 17:30:00";

        /*
        ==================================================
        CALCULATE TOTAL HOURS
        ==================================================
        */

        $checkIn = strtotime($row['check_in']);

        $checkOut = strtotime($autoCheckoutTime);

        $totalSeconds = $checkOut - $checkIn;

        $totalHours = round(
            $totalSeconds / 3600,
            2
        );

        /*
        ==================================================
        UPDATE ATTENDANCE
        ==================================================
        */

        $status = "Present";

        $stmt = $conn->prepare("
            UPDATE attendance
            SET
                check_out = ?,
                total_hours = ?,
                status = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sdsi",
            $autoCheckoutTime,
            $totalHours,
            $status,
            $attendanceId
        );

        $stmt->execute();
    }

    echo "Auto Checkout Completed";
} else {

    echo "Auto Checkout runs only after 9 PM";
}
?>