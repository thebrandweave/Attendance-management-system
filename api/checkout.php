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
            window.location.href='checkout.php';
        </script>";

        exit();
    }

    $userId = $user['id'];

    // GET ATTENDANCE
    $stmt = $conn->prepare("
        SELECT * FROM attendance
        WHERE user_id = ?
        AND date = ?
    ");

    $stmt->bind_param("is", $userId, $today);

    $stmt->execute();

    $attendance = $stmt->get_result()->fetch_assoc();

    if (!$attendance) {

        echo "<script>
            alert('Please Check-In First ❌');
            window.location.href='checkout.php';
        </script>";

        exit();
    }

    /*
    ============================================
    LUNCH BREAK CHECK-OUT
    ============================================
    */

    if (empty($attendance['lunch_out'])) {

        $stmt = $conn->prepare("
            UPDATE attendance
            SET lunch_out = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "si",
            $currentTime,
            $attendance['id']
        );

        $stmt->execute();

        echo "<script>
            alert('Lunch Break Started 🍴');
            window.location.href='../admin/dashboard.php';
        </script>";

        exit();
    }

    /*
    ============================================
    FINAL OFFICE CHECK-OUT
    ============================================
    */

    if (
        !empty($attendance['lunch_in']) &&
        empty($attendance['check_out'])
    ) {

        // CALCULATE MORNING HOURS

        $morningSeconds =
            strtotime($attendance['lunch_out']) -
            strtotime($attendance['check_in']);

        // CALCULATE EVENING HOURS

        $eveningSeconds =
            strtotime($currentTime) -
            strtotime($attendance['lunch_in']);

        // TOTAL WORKING HOURS

        $totalSeconds = $morningSeconds + $eveningSeconds;

        $totalHours = round($totalSeconds / 3600, 2);

        /*
        ============================================
        AUTO STATUS UPDATE
        ============================================
        */

        if ($totalHours >= 8) {

            $status = "Present";

        } elseif ($totalHours >= 4) {

            $status = "Half Day";

        } else {

            $status = "Pending";
        }

        // UPDATE FINAL CHECKOUT

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
            $currentTime,
            $totalHours,
            $status,
            $attendance['id']
        );

        $stmt->execute();

        echo "<script>
            alert('Final Check-Out Successful ✅ Total Hours: $totalHours hrs');
            window.location.href='../admin/dashboard.php';
        </script>";

        exit();
    }

    /*
    ============================================
    LUNCH NOT RETURNED
    ============================================
    */

    if (
        !empty($attendance['lunch_out']) &&
        empty($attendance['lunch_in'])
    ) {

        echo "<script>
            alert('Please do Lunch Check-In First ❌');
            window.location.href='../admin/dashboard.php';
        </script>";

        exit();
    }

    /*
    ============================================
    ALREADY CHECKED OUT
    ============================================
    */

    echo "<script>
        alert('Already Checked-Out ❌');
        window.location.href='../admin/dashboard.php';
    </script>";

    exit();
}
?>

<!DOCTYPE html>
<html>
<head>

<title>QR Check Out</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<script src="https://unpkg.com/html5-qrcode"></script>

<style>

body {
    font-family: 'Poppins', sans-serif;
    background: #f3f4f6;
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

    <h2>Scan QR To Check-Out</h2>

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