<?php
session_start();
include("../config/db.php");
date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
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
        font-family: 'Poppins', Arial, sans-serif;
      background: #f3f4f6;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .card {
      width: 380px;
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.1);
      text-align: center;
      
      
    }

    #reader {
      width: 100%;
      border-radius: 10px;
      overflow: hidden;
    }

    button {
      /* width: 100%; */
      margin-top: 15px;
      padding: 12px;
      background: #dc3545;
      color: white;
      border: none;
      border-radius: 8px;
    }

    .back-btn {
      display: inline-block;
      margin-top: 15px;
      padding: 8px 12px;
      background: #111827;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      font-size: 13px;
    }

    .back-btn:hover {
      background: #374151;
    }
  </style>
</head>

<body>

<div class="card">
  <h2>Scan QR to Check Out</h2>

  <!-- QR SCANNER -->
  <div id="reader"></div>

  <form method="POST" id="scanForm">
    <input type="hidden" name="token" id="token">
  </form>

  <a href="../admin/dashboard.php" class="back-btn">⬅ Go Back</a>
</div>

<script>
function onScanSuccess(decodedText) {
    document.getElementById("token").value = decodedText;
    document.getElementById("scanForm").submit();
}

let scanner = new Html5QrcodeScanner(
    "reader",
    { fps: 10, qrbox: 250 }
);

scanner.render(onScanSuccess);
</script>

</body>
</html>

<?php
/* =========================
   CHECK-OUT LOGIC (SECURE)
========================= */
if (isset($_POST['token'])) {

$input = $_POST['token'];

// extract token from URL if full link is scanned
if (strpos($input, 'token=') !== false) {
    parse_str(parse_url($input, PHP_URL_QUERY), $query);
    $token = $query['token'] ?? '';
} else {
    $token = $input;
}

$token = trim($token);
  $today = date("Y-m-d");

  // 🔐 GET USER BY QR TOKEN
  $stmt = $conn->prepare("SELECT * FROM users WHERE qr_token = ?");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();

  if (!$user) {
    die("<script>alert('Invalid QR ❌');</script>");
  }

  $userId = $user['id'];

  // 🔎 CHECK ATTENDANCE RECORD
  $stmt = $conn->prepare("
    SELECT * FROM attendance 
    WHERE user_id = ? AND date = ?
  ");
  $stmt->bind_param("is", $userId, $today);
  $stmt->execute();
  $attendance = $stmt->get_result()->fetch_assoc();

  if (!$attendance) {
    die("<script>alert('Check-in first ❌');</script>");
  }

  if ($attendance['check_out']) {
    die("<script>alert('Already Checked Out ❌');</script>");
  }

  // 🕒 UPDATE CHECKOUT
  $stmt = $conn->prepare("
    UPDATE attendance 
    SET check_out = NOW()
    WHERE user_id = ? AND date = ?
  ");
  $stmt->bind_param("is", $userId, $today);
  $stmt->execute();

  echo "<script>alert('Check-out successful ✅'); window.location.href='../admin/dashboard.php';</script>";
}
?>