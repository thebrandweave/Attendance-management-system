<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include("../config/db.php");

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

/* =====================================================
   ONLY PROCESS CHECKOUT WHEN FORM IS SUBMITTED
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {

    $input = trim($_POST['token']);

    if (filter_var($input, FILTER_VALIDATE_URL)) {
        parse_str(parse_url($input, PHP_URL_QUERY), $query);
        $token = $query['token'] ?? '';
    } else {
        $token = $input;
    }

    if (empty($token)) {
        die("<script>alert('Invalid QR Format'); window.location.href='checkout.php';</script>");
    }

    $today = date("Y-m-d");
    $currentTime = date("Y-m-d H:i:s");

    /* ================= USER ================= */
    $stmt = $conn->prepare("SELECT * FROM users WHERE qr_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        die("<script>alert('Invalid QR ❌'); window.location.href='checkout.php';</script>");
    }

    $userId = $user['id'];

    /* ================= ATTENDANCE ================= */
    $stmt = $conn->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $attendance = $stmt->get_result()->fetch_assoc();

    if (!$attendance) {
        die("<script>alert('Please Check-In First ❌'); window.location.href='checkin.php';</script>");
    }

    if (!empty($attendance['check_out'])) {
        die("<script>alert('Already Checked-Out ❌'); window.location.href='../admin/dashboard.php';</script>");
    }

    if (empty($attendance['check_in'])) {
        die("<script>alert('Invalid Check-In ❌'); window.location.href='../admin/dashboard.php';</script>");
    }

    /* ================= TIME ================= */
    $checkIn = strtotime($attendance['check_in']);
    $checkOut = strtotime($currentTime);

    if (!$checkIn || !$checkOut) {
        die("<script>alert('Time error ❌');</script>");
    }

    $totalSeconds = $checkOut - $checkIn;
    $totalHours = round($totalSeconds / 3600, 2);

    /* ================= STATUS ================= */
   if ($totalHours >= 8) {
    $status = "Present";
} elseif ($totalHours >= 6) {
    $status = "Half Day";
} elseif ($totalHours > 0) {
    $status = "Short Day";
} else {
    $status = "Absent";
}

    /* ================= UPDATE ================= */
    $stmt = $conn->prepare("
        UPDATE attendance 
        SET check_out = ?, total_hours = ?, status = ?
        WHERE id = ?
    ");

    $stmt->bind_param("sdsi", $currentTime, $totalHours, $status, $attendance['id']);
    $stmt->execute();

    echo "<script>
        alert('Check-Out Successful ✅ Hours: $totalHours');
        window.location.href='../admin/dashboard.php';
    </script>";
    exit();
}
?>

<!-- =====================================================
     HTML UI STARTS HERE (ONLY LOADS ON GET REQUEST)
===================================================== -->
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
    text-align: center;
}

#reader {
    width: 100%;
    min-height: 400px;
    border: 2px dashed #6366f1;
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
let qrScanner;

function onScanSuccess(decodedText) {
    console.log("QR:", decodedText);

    document.getElementById("token").value = decodedText.trim();

    setTimeout(() => {
        document.getElementById("scanForm").submit();
    }, 300);
}

/* ================= CAMERA ================= */
qrScanner = new Html5Qrcode("reader");

Html5Qrcode.getCameras()
.then(devices => {
    if (!devices || devices.length === 0) {
        alert("No camera found");
        return;
    }

    qrScanner.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: 250 },
        onScanSuccess
    );
})
.catch(err => {
    console.error(err);
    alert("Camera not available or permission denied");
});


</script>

</body>
</html>