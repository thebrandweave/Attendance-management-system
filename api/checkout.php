<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include("../config/db.php");

date_default_timezone_set("Asia/Kolkata");

/* =====================================================
   AUTO CHECKOUT AFTER 9:00 PM
===================================================== */
$currentTimeOnly = date("H:i");

if ($currentTimeOnly >= "21:00") {
    $today = date("Y-m-d");
    $autoCheckoutQuery = $conn->query("
        SELECT * FROM attendance 
        WHERE date='$today' 
        AND check_in IS NOT NULL 
        AND (check_out IS NULL OR check_out='')
    ");

    while ($row = $autoCheckoutQuery->fetch_assoc()) {
        $attendanceId = $row['id'];
        $autoCheckoutTime = $today . " 17:30:00";

        $checkIn = strtotime($row['check_in']);
        $checkOut = strtotime($autoCheckoutTime);
        $totalSeconds = $checkOut - $checkIn;

        $lunchSeconds = 0;
        if (!empty($row['lunch_out']) && !empty($row['lunch_in'])) {
            $lunchOut = strtotime($row['lunch_out']);
            $lunchIn  = strtotime($row['lunch_in']);
            if ($lunchIn > $lunchOut) {
                $lunchSeconds = $lunchIn - $lunchOut;
            }
        }

        $workingSeconds = max(0, $totalSeconds - $lunchSeconds);
        $totalHours = round($workingSeconds / 3600, 2);
        $status = "Present";

        $updateStmt = $conn->prepare("UPDATE attendance SET check_out=?, total_hours=?, status=? WHERE id=?");
        $updateStmt->bind_param("sdsi", $autoCheckoutTime, $totalHours, $status, $attendanceId);
        $updateStmt->execute();
        $updateStmt->close();
    }
}

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$toastMessage = "";
$toastColor = "";

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

    $today = date("Y-m-d");
    $currentTime = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("SELECT * FROM users WHERE qr_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $toastMessage = "Invalid QR ❌";
        $toastColor = "#ef4444";
    } else {
        $userId = $user['id'];

        $stmt = $conn->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->bind_param("is", $userId, $today);
        $stmt->execute();
        $attendance = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$attendance) {
            $toastMessage = "Please Check-In First ❌";
            $toastColor = "#f59e0b";
        } elseif (
    isset($attendance['check_out']) &&
    trim((string)$attendance['check_out']) !== '' &&
    $attendance['check_out'] !== '0000-00-00 00:00:00'
) {
    $toastMessage = "Already Checked-Out ❌";
    $toastColor = "#f59e0b";
        } elseif (empty($attendance['check_in'])) {
            $toastMessage = "Invalid Check-In Record ❌";
            $toastColor = "#ef4444";
        } else {
            $checkIn = strtotime($attendance['check_in']);
            $checkOut = strtotime($currentTime);
            $totalSeconds = $checkOut - $checkIn;

            $lunchSeconds = 0;
            if (!empty($attendance['lunch_out']) && !empty($attendance['lunch_in'])) {
                $lunchOut = strtotime($attendance['lunch_out']);
                $lunchIn  = strtotime($attendance['lunch_in']);
                if ($lunchIn > $lunchOut) {
                    $lunchSeconds = $lunchIn - $lunchOut;
                }
            }

            $workingSeconds = max(0, $totalSeconds - $lunchSeconds);
            $totalHours = round($workingSeconds / 3600, 2);
            $checkoutTimeOnly = date("H:i", $checkOut);

            // Unified Status Resolution Logic
            if ($totalHours < 6.75) {
                $status = "Half Day";
            } elseif ($checkoutTimeOnly >= "17:35") {
                $status = "Overtime";
            } else {
                $status = "Present";
            }

            $stmt = $conn->prepare("UPDATE attendance SET check_out = ?, total_hours = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sdsi", $currentTime, $totalHours, $status, $attendance['id']);
            
            if ($stmt->execute()) {
                $toastMessage = "Check-Out Successful ✅ Hours: $totalHours";
                $toastColor = "#16a34a";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QR Check Out</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #f3f4f6, #e5e7eb); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { width: 90%; max-width: 500px; background: white; padding: 30px; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); text-align: center; }
        #reader { width: 100%; border-radius: 15px; overflow: hidden; border: 2px dashed #6366f1; background: #f9fafb; }
        .back-btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #111827; color: white; border-radius: 8px; text-decoration: none; transition: 0.3s; }
        .back-btn:hover { background: #000; }
        .toast { position: fixed; top: 20px; right: 20px; padding: 15px 25px; border-radius: 12px; color: white; font-weight: 500; box-shadow: 0 5px 15px rgba(0,0,0,0.2); transform: translateX(150%); transition: transform 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55); z-index: 10000; }
        .toast.show { transform: translateX(0); }
    </style>
</head>
<body>
    <div id="toast" class="toast"></div>
    <div class="card">
        <h2 style="color: #1e293b;">Check-Out 🕒</h2>
        <p style="color: #64748b; margin-bottom: 20px;">Scan QR to finish your day</p>
        <div id="reader"></div>
        <form method="POST" id="scanForm">
            <input type="hidden" name="token" id="token">
        </form>
        <a href="../admin/dashboard.php" class="back-btn">⬅ Go Back</a>
    </div>
    <script>
        const toast = document.getElementById("toast");
        function showToast(message, color = "#111827") {
            toast.innerText = message;
            toast.style.backgroundColor = color;
            toast.classList.add("show");
            setTimeout(() => { toast.classList.remove("show"); }, 3500);
        }
        <?php if ($toastMessage): ?>
            showToast("<?php echo $toastMessage; ?>", "<?php echo $toastColor; ?>");
        <?php endif; ?>

        function onScanSuccess(decodedText) {
            html5QrCode.stop().then(() => {
                toast.innerText = "Processing Check-out... ⏳";
                toast.style.backgroundColor = "#6366f1";
                toast.classList.add("show");
                document.getElementById("token").value = decodedText.trim();
                document.getElementById("scanForm").submit();
            });
        }
        let html5QrCode = new Html5Qrcode("reader");
        Html5Qrcode.getCameras().then(devices => {
            if (devices && devices.length) {
                html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 250 } }, onScanSuccess);
            }
        }).catch(err => { showToast("Camera Error ❌", "#ef4444"); });
    </script>
</body>
</html>