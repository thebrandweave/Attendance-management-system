<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$lifetime = 60 * 60 * 24 * 30;

session_set_cookie_params($lifetime);

session_start();
include("../config/db.php");

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$toastMessage = "";
$toastColor = "";

if (isset($_POST['token'])) {
    $input = $_POST['token'];

    // Extract token safely
    if (filter_var($input, FILTER_VALIDATE_URL)) {
        parse_str(parse_url($input, PHP_URL_QUERY), $query);
        $token = $query['token'] ?? '';
    } else {
        $token = trim($input);
    }

    $today = date("Y-m-d");
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $currentTime = $now->format("Y-m-d H:i:s");
    $timeOnly = $now->format("H:i:s");

    // GET USER
    $stmt = $conn->prepare("SELECT * FROM users WHERE qr_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $toastMessage = "Access Denied: Invalid QR Code";
        $toastColor = "#ef4444"; // Red
    } else {
        $userId = $user['id'];

        // CHECK TODAY ATTENDANCE
        $stmt = $conn->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->bind_param("is", $userId, $today);
        $stmt->execute();
        $attendance = $stmt->get_result()->fetch_assoc();

      if (!$attendance) {

    // Determine Status
    if ($timeOnly <= "09:46:00") {

        $status = "Present";

    } elseif ($timeOnly <= "10:00:00") {

        $status = "Late";

    } else {

        $status = "Half Day";
    }

            $stmt = $conn->prepare("INSERT INTO attendance (user_id, date, check_in, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $userId, $today, $currentTime, $status);
            
            if ($stmt->execute()) {
                $toastMessage = " ✅ Check-In Successful. Status: $status";
                $toastColor = "#16a34a"; // Green
                // $redirect = "../admin/dashboard.php";
            }
        } else {
            $toastMessage = "Attendance already recorded for today.";
            $toastColor = "#f59e0b"; // Amber/Orange
            // $redirect = "../admin/dashboard.php";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
            margin: 0;
        }
        .card {
            width: 90%;
            max-width: 500px;
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            text-align: center;
        }
        #reader {
            width: 100%;
            border-radius: 15px;
            overflow: hidden;
            border: 2px solid #6366f1;
            background: #f9fafb;
        }
        .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #1f2937;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.3s;
        }
        .back-btn:hover { background: #000; }

        /* Toast Styling */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transform: translateX(150%);
            transition: transform 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 10000;
        }
        .toast.show { transform: translateX(0); }
    </style>
</head>
<body>

<div id="toast" class="toast"></div>

<div class="card">
  <h2 style="color: #1e293b;">Check-In 🕒</h2>
     <p style="color: #64748b; margin-bottom: 20px;">Welcome</p>
    <div id="reader"></div>
    
    <form method="POST" id="scanForm">
        <input type="hidden" name="token" id="token">
    </form>

    <a href="../admin/dashboard.php" class="back-btn">⬅ Go Back</a>
</div>

<script>
    const toast = document.getElementById("toast");

    function showToast(message, color = "#111827", redirect = null) {
        toast.innerText = message;
        toast.style.backgroundColor = color;
        toast.classList.add("show");

        setTimeout(() => {
            toast.classList.remove("show");
            if(redirect) {
                window.location.href = redirect;
            }
        }, 2000);
    }

    // Trigger toast if PHP set a message
    <?php if ($toastMessage): ?>
        showToast("<?php echo $toastMessage; ?>", "<?php echo $toastColor; ?>", "<?php echo $redirect ?? ''; ?>");
    <?php endif; ?>

    function onScanSuccess(decodedText) {
        // Stop scanning to prevent multiple submissions
        html5QrCode.stop().then(() => {
            document.getElementById("token").value = decodedText;
            document.getElementById("scanForm").submit();
        });
    }

    const html5QrCode = new Html5Qrcode("reader");
    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length) {
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                onScanSuccess
            );
        }
    }).catch(err => {
        showToast("Camera Error: " + err, "#ef4444");
    });
</script>

</body>
</html>