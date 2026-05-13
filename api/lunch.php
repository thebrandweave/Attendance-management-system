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

    if (filter_var($input, FILTER_VALIDATE_URL)) {
        parse_str(parse_url($input, PHP_URL_QUERY), $query);
        $token = $query['token'] ?? '';
    } else {
        $token = trim($input);
    }

    $today = date("Y-m-d");
    $now = date("Y-m-d H:i:s");

    /* ================= USER ================= */
    $stmt = $conn->prepare("SELECT * FROM users WHERE qr_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        echo "<script>
            alert('Invalid QR ❌');
            window.location.href='lunch.php';
        </script>";
        exit();
    }

    $userId = $user['id'];

    /* ================= ATTENDANCE ================= */
    $stmt = $conn->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $attendance = $stmt->get_result()->fetch_assoc();

    if (!$attendance) {
        echo "<script>
            alert('Please Check-In First ❌');
            window.location.href='checkin.php';
        </script>";
        exit();
    }

    /* ================= CASE 1: START LUNCH ================= */
    if (empty($attendance['lunch_out'])) {

        $stmt = $conn->prepare("
            UPDATE attendance
            SET lunch_out = ?
            WHERE id = ?
        ");

        $stmt->bind_param("si", $now, $attendance['id']);
        $stmt->execute();

        echo "<script>
            alert('Lunch Started 🍴');
            window.location.href='../admin/dashboard.php';
        </script>";
        exit();
    }

    /* ================= CASE 2: END LUNCH ================= */
    if (!empty($attendance['lunch_out']) && empty($attendance['lunch_in'])) {

        $stmt = $conn->prepare("
            UPDATE attendance
            SET lunch_in = ?
            WHERE id = ?
        ");

        $stmt->bind_param("si", $now, $attendance['id']);
        $stmt->execute();

        echo "<script>
            alert('Lunch Ended ✅');
            window.location.href='../admin/dashboard.php';
        </script>";
        exit();
    }

    /* ================= CASE 3 ================= */
    echo "<script>
        alert('Lunch already completed ❌');
        window.location.href='../admin/dashboard.php';
    </script>";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>QR Lunch Break</title>

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

    <h2>Scan QR for Lunch Break 🍴</h2>

    <div id="reader"></div>

    <form method="POST" id="scanForm">
        <input type="hidden" name="token" id="token">
    </form>

    <a href="../admin/dashboard.php" class="back-btn">⬅ Go Back</a>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    let html5QrCode = new Html5Qrcode("reader");

    function onScanSuccess(decodedText) {
        document.getElementById("token").value = decodedText;
        document.getElementById("scanForm").submit();
    }

    function startCamera() {

        Html5Qrcode.getCameras().then(devices => {

            if (!devices || devices.length === 0) {
                alert("No camera found ❌");
                return;
            }

            let cameraId = devices[0].id;

            const backCamera = devices.find(d =>
                d.label && d.label.toLowerCase().includes("back")
            );

            if (backCamera) {
                cameraId = backCamera.id;
            }

            html5QrCode.start(
                cameraId,
                { fps: 10, qrbox: 250 },
                onScanSuccess
            ).catch(err => {
                console.error(err);
                alert("Camera blocked or not allowed ❌");
            });

        });
    }

    setTimeout(startCamera, 800);

});
</script>

</body>
</html>