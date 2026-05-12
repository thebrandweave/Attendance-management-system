<?php
session_start();
include("../config/db.php");
date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit();
}

$statusMessage = "";
$statusClass = "";
?>
<!DOCTYPE html>
<html>
<head>
  <title>QR Check In</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

  <!-- QR SCANNER LIB -->
  <script src="https://unpkg.com/html5-qrcode"></script>
<style>
body {
  font-family: 'Poppins', Arial, sans-serif;
  background: linear-gradient(135deg, #eef2f7, #dbeafe);
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
}

/* CARD */
.card {
  width: 590px;
  background: rgba(255, 255, 255, 0.9);
  padding: 25px;
  border-radius: 18px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.15);
  text-align: center;
  backdrop-filter: blur(10px);
  animation: fadeIn 0.4s ease-in-out;
}

/* TITLE */
h2 {
  margin-bottom: 15px;
  font-size: 22px;
  color: #111827;
}

/* QR BOX */
#reader {
  width: 100%;
  border-radius: 12px;
  overflow: hidden;
  border: 2px dashed #6366f1;
  padding: 10px;
  background: #f9fafb;
}

/* STATUS TEXT */
.status-present {
  color: #16a34a;
  font-weight: bold;
  margin-top: 10px;
}

.status-late {
  color: #dc2626;
  font-weight: bold;
  margin-top: 10px;
}

.status-half {
  color: #f59e0b;
  font-weight: bold;
  margin-top: 10px;
}

/* BACK BUTTON */
.back-btn {
  display: inline-block;
  margin-top: 18px;
  padding: 10px 14px;
  background: linear-gradient(135deg, #111827, #374151);
  color: white;
  text-decoration: none;
  border-radius: 10px;
  font-size: 13px;
  transition: 0.3s;
}

.back-btn:hover {
  transform: scale(1.05);
  background: #000;
}

/* ANIMATION */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(15px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* MOBILE RESPONSIVE */
@media (max-width: 500px) {
  .card {
    width: 90%;
  }
}

 button {
      /* width: 100%; */
      margin-top: 15px;
      margin-bottom:15px;
      padding: 12px;
      background: #dc3545;
      color: white;
      border: none;
      border-radius: 8px;
    }
#reader {
  width: 95%;
  min-height: 420px;
  border-radius: 16px;
  overflow: hidden;
  border: 2px dashed #6366f1;
  padding: 10px;
  background: #f9fafb;
}

#reader video {
  width: 100% !important;
  height: 416px !important;
  object-fit: cover;
  border-radius: 12px;
}
</style>
</head>

<body>

<div class="card">
  <h2>Scan QR to Check In</h2>

  <!-- QR SCANNER -->
  <div id="reader"></div>

  <p id="msg"></p>

  <form method="POST" id="scanForm">
    <input type="hidden" name="token" id="token">
  </form>

  <a href="../admin/dashboard.php" class="back-btn">⬅ Go Back</a>

  <?php if ($statusMessage) { ?>
    <p class="<?= $statusClass; ?>"><?= $statusMessage; ?></p>
  <?php } ?>
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
            { facingMode: "environment" }, // back camera

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

<?php
/* =========================
   CHECK-IN LOGIC (SECURE)
========================= */
if (isset($_POST['token'])) {

$input = $_POST['token'];

parse_str(parse_url($input, PHP_URL_QUERY), $query);

$token = $query['token'] ?? $input;
$currentDateTime = new DateTime();

$today = $currentDateTime->format("Y-m-d");

$now = $currentDateTime->format("H:i:s");

$checkInTime = $currentDateTime->format("Y-m-d H:i:s");

  // SAFE USER FETCH
  $stmt = $conn->prepare("SELECT * FROM users WHERE qr_token = ?");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();

  if (!$user) {
    die("<script>alert('Invalid QR ❌');</script>");
  }

  $userId = $user['id'];

  // CHECK DUPLICATE ENTRY
  $stmt = $conn->prepare("
    SELECT id FROM attendance 
    WHERE user_id = ? AND date = ?
  ");
  $stmt->bind_param("is", $userId, $today);
  $stmt->execute();
  $check = $stmt->get_result();

  if ($check->num_rows > 0) {
    die("<script>alert('Already Checked In ❌'); window.location.href='../admin/dashboard.php';</script>");
  }

  // STATUS LOGIC
if ($now <= "09:30:00") {
    $status = "Present";
} elseif ($now <= "10:00:00") {
    $status = "Late";
} else {
    $status = "Half Day";
}              

  // INSERT ATTENDANCE
  $checkInTime = date("Y-m-d H:i:s");
  $stmt = $conn->prepare("
    INSERT INTO attendance (user_id, date, check_in, status)
  VALUES (?, ?, ?, ?)
  ");
  $stmt->bind_param("iss", $userId, $today,  $checkInTime, $status);
  $stmt->execute();

  echo "<script>alert('Check-in successful ($status)'); window.location.href='../admin/dashboard.php';</script>";
}
?>