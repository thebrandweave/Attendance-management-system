<?php 
include("../config/db.php");

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
  header("Location: ../index.php");
  exit();
}

if (!isset($_SESSION['form_token'])) {
  $_SESSION['form_token'] = bin2hex(random_bytes(16));
}

if (isset($_POST['create'])) {

  if (
    !isset($_POST['token']) ||
    !hash_equals($_SESSION['form_token'], $_POST['token'])
  ) {
    die("Invalid request ❌");
  }

  $name = $_POST['name'];

  $empId = "EMP" . rand(1000,9999);
  $plainPassword = "1234";
  $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
  $token = bin2hex(random_bytes(32));

  $stmt = $conn->prepare("
    INSERT INTO users (name, employee_id, password, role, qr_token)
    VALUES (?, ?, ?, 'employee', ?)
  ");

  $stmt->bind_param("ssss", $name, $empId, $hashedPassword, $token);
  $stmt->execute();

  $_SESSION['success'] = [
    "id" => $empId,
    "pass" => $plainPassword,
    "qr" => $token
  ];

  $_SESSION['form_token'] = bin2hex(random_bytes(32));

  header("Location: create_employee.php");
  exit();
}
?>

<!DOCTYPE html>
<html>
<head>

  <title>Create Employee</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>

/* ===== GLOBAL ===== */
body {
  margin: 0;
  font-family: 'Poppins', sans-serif;
  background: #eef2f7;
}

/* ===== LAYOUT ===== */
.layout {
  display: flex;
  min-height: 100vh;
}

/* ===== SIDEBAR ===== */
.sidebar {
  width: 250px;
  background: linear-gradient(180deg, #111827, #1f2937);
  color: white;
  padding: 20px;
}

.sidebar h2 {
  margin-bottom: 25px;
  font-size: 20px;
  text-align: center;
}

.sidebar a {
  display: block;
  padding: 12px 14px;
  margin: 8px 0;
  color: white;
  text-decoration: none;
  border-radius: 8px;
  transition: 0.3s;
  font-size: 14px;
}

.sidebar a:hover {
  background: rgba(255,255,255,0.1);
  transform: translateX(4px);
}

.sidebar .logout {
  background: #ef4444;
}

.sidebar .logout:hover {
  background: #dc2626;
}

/* ===== MAIN ===== */
.main {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 30px;
}

/* ===== CARD ===== */
.card {
  width: 400px;
  background: white;
  padding: 30px;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.1);
  text-align: center;
  animation: fadeIn 0.4s ease-in-out;
}

@keyframes fadeIn {
  from { 
    opacity: 0; 
    transform: translateY(10px); 
  }
  to { 
    opacity: 1; 
    transform: translateY(0); 
  }
}

/* ===== INPUT ===== */
input {
  width: 100%;
  padding: 12px;
  margin-top: 15px;
  border: 1px solid #ddd;
  border-radius: 10px;
  outline: none;
  transition: 0.3s;
  font-size: 14px;
  box-sizing: border-box;
}

input:focus {
  border-color: #667eea;
  box-shadow: 0 0 5px rgba(102,126,234,0.4);
}

/* ===== BUTTON ===== */
button {
  width: 100%;
  margin-top: 15px;
  padding: 12px;
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: 600;
  cursor: pointer;
  transition: 0.3s;
}

button:hover {
  transform: scale(1.03);
  box-shadow: 0 5px 15px rgba(102,126,234,0.4);
}

/* ===== BACK BUTTON ===== */
.back-btn {
  display: inline-block;
  margin-bottom: 15px;
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

/* ===== SUCCESS BOX ===== */
.success {
  margin-top: 15px;
  padding: 10px;
  background: #ecfdf5;
  color: #16a34a;
  border-radius: 10px;
  font-weight: 600;
  font-size: 14px;
}

.info {
  font-size: 13px;
  margin-top: 5px;
  color: #555;
}

/* ===== TOAST ===== */
.toast {
  position: fixed;
  top: 20px;
  right: 20px;
  background: #16a34a;
  color: white;
  padding: 7px 18px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  box-shadow: 0 10px 25px rgba(0,0,0,0.15);
  transform: translateX(120%);
  opacity: 0;
  transition: all 0.4s ease;
  z-index: 9999;
}

.toast.show {
  transform: translateX(0);
  opacity: 1;
}

.toast {
  display: flex;
  align-items: center;
  gap: 10px;
}

.toast-icon {
  font-size: 18px;
}

</style>
<script>

async function downloadQR() {

    const img = document.querySelector("img[alt='QR Code']");

    if (!img) return;

    try {

        const response = await fetch(img.src);

        const blob = await response.blob();

        const blobUrl = window.URL.createObjectURL(blob);

        const link = document.createElement("a");

        link.href = blobUrl;

        const employeeId = "<?= $_SESSION['success']['id'] ?? 'employee' ?>";

        link.download = employeeId + "_QR.png";

        document.body.appendChild(link);

        link.click();

        document.body.removeChild(link);

        window.URL.revokeObjectURL(blobUrl);

    } catch (error) {

        console.log(error);

    }

}

</script>
</head>

<body>

<div class="layout">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <h2>Admin Panel</h2>

    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="create_employee.php">👤 Create Employee</a>
    <a href="../api/checkin.php">🟢 Check In</a>
    <a href="../api/checkout.php">🔴 Check Out</a>
    <a href="leave_requests.php">📩 Manage Leaves</a>
    <a href="reports.php">📊 Reports</a>
    <a href="../auth/logout.php" class="logout">🚪 Logout</a>
  </div>

  <!-- MAIN -->
  <div class="main">

    <div class="card">

      <a href="dashboard.php" class="back-btn">⬅ Go Back</a>

      <h2>Create Employee</h2>

      <form method="POST">

        <input 
          name="name" 
          placeholder="Enter Employee Name" 
          required
        >

        <input 
          type="hidden" 
          name="token" 
          value="<?= $_SESSION['form_token'] ?>"
        >

        <button name="create">
          Create Employee
        </button>

      </form>

      <?php if (isset($_SESSION['success'])) { ?>

        <?php
        $qrLink = "https://thebrandweave.com/attendance/api/checkin.php?token=" . $_SESSION['success']['qr'];
        ?>

        <div class="success">
          Employee Created Successfully ✅
        </div>

        <div class="info">
          <b>ID:</b> <?= $_SESSION['success']['id'] ?><br>
          <b>Password:</b> <?= $_SESSION['success']['pass'] ?>
        </div>

        <!-- QR CODE -->
        <div style="margin-top:15px;">

          <p><b>Employee Check-in QR</b></p>

          <img 
            src="https://quickchart.io/qr?size=200&text=<?= urlencode($qrLink) ?>"
            alt="QR Code"
            style="border:1px solid #ddd; border-radius:10px;"
          >

        </div>

        <button onclick="downloadQR()" type="button">
          ⬇ Download QR
        </button>

      <?php } ?>

    </div>

  </div>

</div>

<?php if (isset($_SESSION['success'])) { ?>

<div class="toast" id="toast">
    <i class="fa-solid fa-circle-check toast-icon"></i>
    <span>QR Downloaded</span>
</div>

<script>

window.onload = function () {

    const toast = document.getElementById("toast");

    toast.classList.add("show");

    setTimeout(() => {
        toast.classList.remove("show");
    }, 3000);

    setTimeout(() => {
        downloadQR();
    }, 1500);

};

</script>

<?php unset($_SESSION['success']); ?>

<?php } ?>

</body>
</html>