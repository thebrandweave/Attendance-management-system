<?php
session_start();
include("../config/db.php");

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

if (isset($_POST['token'])) {

    $input = $_POST['token'];

    parse_str(parse_url($input, PHP_URL_QUERY), $query);

    $token = $query['token'] ?? $input;

    $today = date("Y-m-d");

    $currentDateTime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

    $currentTime = $currentDateTime->format("Y-m-d H:i:s");

    $timeNow = $currentDateTime->format("H:i:s");

    // GET USER
    $stmt = $conn->prepare("
        SELECT * FROM users
        WHERE qr_token = ?
    ");

    $stmt->bind_param("s", $token);

    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {

        echo "<script>
            alert('Invalid QR ❌');
            window.location.href='checkin.php';
        </script>";

        exit();
    }

    $userId = $user['id'];

    // CHECK TODAY ATTENDANCE
    $stmt = $conn->prepare("
        SELECT * FROM attendance
        WHERE user_id = ?
        AND date = ?
    ");

    $stmt->bind_param("is", $userId, $today);

    $stmt->execute();

    $attendance = $stmt->get_result()->fetch_assoc();

    /*
    ============================================
    FIRST OFFICE CHECK-IN
    ============================================
    */

    if (!$attendance) {

        // STATUS LOGIC

        if ($timeNow <= "09:30:00") {

            $status = "Present";

        } elseif ($timeNow <= "10:00:00") {

            $status = "Late";

        } else {

            $status = "Half Day";
        }

        // INSERT ATTENDANCE

        $stmt = $conn->prepare("
            INSERT INTO attendance (
                user_id,
                date,
                check_in,
                status
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isss",
            $userId,
            $today,
            $currentTime,
            $status
        );

        $stmt->execute();

        echo "<script>
            alert('Office Check-In Successful ✅');
            window.location.href='../admin/dashboard.php';
        </script>";

        exit();
    }

    /*
    ============================================
    LUNCH CHECK-IN
    ============================================
    */

    if (
        !empty($attendance['lunch_out']) &&
        empty($attendance['lunch_in'])
    ) {

        $stmt = $conn->prepare("
            UPDATE attendance
            SET lunch_in = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "si",
            $currentTime,
            $attendance['id']
        );

        $stmt->execute();

        echo "<script>
            alert('Lunch Break Ended ✅');
            window.location.href='../admin/dashboard.php';
        </script>";

        exit();
    }

    /*
    ============================================
    ALREADY CHECKED IN
    ============================================
    */

    echo "<script>
        alert('Already Checked-In ❌');
        window.location.href='../admin/dashboard.php';
    </script>";

    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>QR Check In</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/html5-qrcode"></script>

<style>

body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #eef2f7, #dbeafe);
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

.card {
    width: 600px;
    background: white;
    padding: 25px;
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.12);
    text-align: center;
}

h2 {
    margin-bottom: 15px;
}

#reader {
    width: 100%;
    min-height: 420px;
    border-radius: 16px;
    overflow: hidden;
    border: 2px dashed #6366f1;
    padding: 10px;
    background: #f9fafb;
}

#reader video {
    width: 100% !important;
    height: 420px !important;
    object-fit: cover;
    border-radius: 12px;
}

.back-btn {
    display: inline-block;
    margin-top: 20px;
    padding: 12px 15px;
    background: #111827;
    color: white;
    border-radius: 10px;
    text-decoration: none;
}

.back-btn:hover {
    background: #000;
}

</style>
</head>

<body>

<div class="card">

    <h2>Scan QR To Check-In</h2>

    <div id="reader"></div>

    <form method="POST" id="scanForm">

        <input type="hidden" name="token" id="token">

    </form>

    <a href="../admin/dashboard.php" class="back-btn">
        ⬅ Go Back
    </a>

</div>

<script>

function onScanSuccess(decodedText) {

    document.getElementById("token").value = decodedText;

    document.getElementById("scanForm").submit();
}

const html5QrCode = new Html5Qrcode("reader");

Html5Qrcode.getCameras().then(devices => {

    if (devices && devices.length) {

        html5QrCode.start(

            { facingMode: "environment" },

            {
                fps: 15,

                qrbox: {
                    width: 280,
                    height: 280
                },

                aspectRatio: 1.777,

                disableFlip: false,

                videoConstraints: {
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                    focusMode: "continuous"
                }
            },

            onScanSuccess
        );
    }

}).catch(err => {

    console.log(err);
});

</script>

</body>
</html>