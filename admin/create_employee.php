<?php 
include("../config/db.php");
require_once "../config/branch_helper.php";

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

ensureEmployeeSettingsColumns($conn);

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
  $branch_id = $_SESSION['user']['branch_id'] ?? $_SESSION['branch_id'] ?? 0;
  $branch = $_SESSION['user']['branch'] ?? $_SESSION['branch'] ?? '';

  $bStmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ? OR LOWER(branch_name) = LOWER(?)");
  $bStmt->bind_param("is", $branch_id, $branch);
  $bStmt->execute();
  $bRes = $bStmt->get_result()->fetch_assoc();
  $branchName = $bRes ? $bRes['branch_name'] : ucfirst($branch);


  $working_hours = isset($_POST['working_hours']) && $_POST['working_hours'] !== '' ? max(0, min(24, (float)$_POST['working_hours'])) : 8.00;
  $monthly_cl = isset($_POST['monthly_cl']) && $_POST['monthly_cl'] !== '' ? max(0, min(31, (float)$_POST['monthly_cl'])) : 2.00;
  $check_in_days = !empty($_POST['check_in_days']) ? (is_array($_POST['check_in_days']) ? implode(',', $_POST['check_in_days']) : trim($_POST['check_in_days'])) : 'Mon,Tue,Wed,Thu,Fri,Sat';

  $empId = "EMP" . rand(1000,9999);
  $plainPassword = "EMP@" . rand(1000,9999);
  $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
  $token = bin2hex(random_bytes(32));

  $stmt = $conn->prepare("
    INSERT INTO users (name, employee_id, password, role, qr_token, branch, branch_id, working_hours, monthly_cl, check_in_days)
    VALUES (?, ?, ?, 'employee', ?, ?, ?, ?, ?, ?)
  ");

  $stmt->bind_param("sssssidds", $name, $empId, $hashedPassword, $token, $branch, $branch_id, $working_hours, $monthly_cl, $check_in_days);
  $stmt->execute();

$_SESSION['success'] = [
  "name" => $name,
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { margin: 0; font-family: 'Poppins', sans-serif; background: #eef2f7; color: #111827; }
    .layout { display: flex; min-height: 100vh; }
    .sidebar { width: 250px; background: linear-gradient(180deg, #111827, #1f2937); color: white; padding: 20px; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 1000; box-sizing: border-box; }
    .sidebar h2 { margin-bottom: 25px; font-size: 20px; text-align: center; font-family: 'Poppins', sans-serif; font-weight: 600; }
    .sidebar a { display: block; padding: 12px 14px; margin: 8px 0; color: white; text-decoration: none; border-radius: 8px; transition: 0.3s; font-size: 14px; font-family: 'Poppins', sans-serif; line-height: 1.5; }
    .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); transform: translateX(4px); font-weight: 600; }
    .sidebar .logout { background: #ef4444; }
    .sidebar .logout:hover { background: #dc2626; transform: none; }

/* ===== MAIN ===== */
.main {
  flex: 1;
  margin-left: 250px;
  width: calc(100% - 250px);
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 30px;
  box-sizing: border-box;
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

/* DAY SELECTOR */
.days-selector { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
.day-pill { padding: 6px 11px; border-radius: 8px; font-size: 12px; font-weight: 600; border: 1.5px solid #d1d5db; background: #f9fafb; color: #4b5563; cursor: pointer; user-select: none; transition: 0.2s; display: inline-flex; align-items: center; }
.day-pill:hover { border-color: #667eea; color: #667eea; }
.day-pill.selected { background: #667eea; border-color: #667eea; color: white; box-shadow: 0 2px 6px rgba(102,126,234,0.3); }
.quick-presets { display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap; }
.btn-preset { padding: 3px 8px; font-size: 11px; border-radius: 5px; border: 1px dashed #9ca3af; background: white; color: #4b5563; cursor: pointer; font-family: inherit; transition: 0.2s; }
.btn-preset:hover { background: #e0e7ff; border-color: #667eea; color: #4338ca; }
</style>
<script>
async function downloadQR() {

    const qrContainer = document.getElementById("qrContainer");

    const qrImage = document.getElementById("qrImage");

    if (!qrContainer || !qrImage) return;

    try {

        // Wait until image fully loads
        if (!qrImage.complete) {

            await new Promise((resolve) => {

                qrImage.onload = resolve;
            });
        }

        const canvas = await html2canvas(qrContainer, {
            useCORS: true,
            scale: 3
        });

        const image = canvas.toDataURL("image/png");

        const link = document.createElement("a");

        const employeeId =
            "<?= $_SESSION['success']['id'] ?? 'employee' ?>";

        link.href = image;

        link.download = employeeId + "_QR.png";

        document.body.appendChild(link);

        link.click();

        document.body.removeChild(link);

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
   <h2 style="text-align:center;">
  <?= htmlspecialchars($branchName ?? $_SESSION['user']['branch']) ?> Admin 
</h2>

    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="create_employee.php" class="active">👤 Create Employee</a>
    <a href="employee_settings.php">⚙️ Employee Settings</a>
    <a href="../api/checkin.php">🟢 Check In- Morning</a>
    <a href="../api/lunch.php">🍽️ Lunch Break</a>
    <a href="../api/checkout.php">🔴 Check Out- Evening</a>
    <?php
      $adminBranchId = $_SESSION['user']['branch_id'] ?? 0;
      $adminBranch = $_SESSION['user']['branch'] ?? '';
      $leaveCountQuery = $conn->query("SELECT COUNT(*) as total FROM leave_requests lr LEFT JOIN users u ON lr.employee_id = u.id WHERE (u.branch_id='$adminBranchId' OR u.branch='$adminBranch') AND lr.status='pending'");
      $leaveCount = $leaveCountQuery ? $leaveCountQuery->fetch_assoc()['total'] : 0;
    ?>
    <a href="leave_requests.php">📩 Manage Leaves <?php if($leaveCount > 0) { ?><span style="background:#ef4444; color:white; padding:2px 8px; border-radius:50px; font-size:12px; margin-left:8px; font-weight:600;"><?= $leaveCount ?></span><?php } ?></a>
    <a href="add_leave.php">📅 Company Leaves</a>
    <a href="reports.php">📊 Reports</a>
    <a href="../auth/logout.php" class="logout">🚪 Logout</a>
  </div>

  <!-- MAIN -->
  <div class="main">

    <div class="card">

      <a href="dashboard.php" class="back-btn">⬅ Go Back</a>

      <h2>Create Employee</h2>

      <form method="POST">

        <div style="text-align:left; margin-top:12px;">
          <label style="font-size:12px; font-weight:600; color:#374151;">Employee Name</label>
          <input 
            name="name" 
            placeholder="Enter Employee Name" 
            required
            style="margin-top:5px;"
          >
        </div>

        <div style="text-align:left; margin-top:12px;">
          <label style="font-size:12px; font-weight:600; color:#374151;">Daily Working Hours</label>
          <input 
            type="number" 
            step="0.5" 
            min="1" 
            max="24" 
            name="working_hours" 
            value="8.0" 
            placeholder="e.g. 8.0" 
            required
            style="margin-top:5px;"
          >
        </div>

        <div style="text-align:left; margin-top:12px;">
          <label style="font-size:12px; font-weight:600; color:#374151;">Monthly CL (Casual Leave)</label>
          <input 
            type="number" 
            step="0.5" 
            min="0" 
            max="31" 
            name="monthly_cl" 
            value="2.0" 
            placeholder="e.g. 2.0" 
            required
            style="margin-top:5px;"
          >
        </div>

        <div style="text-align:left; margin-top:12px;">
          <label style="font-size:12px; font-weight:600; color:#374151;">Specific Check-in Days</label>
          <input type="hidden" name="check_in_days" id="createCheckInDaysInput" value="Mon,Tue,Wed,Thu,Fri,Sat">
          <div class="days-selector" id="createDaysSelector">
            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
              <div class="day-pill <?= $d !== 'Sun' ? 'selected' : '' ?>" data-day="<?= $d ?>" onclick="toggleCreateDay(this)">
                <?= $d ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="quick-presets">
            <button type="button" class="btn-preset" onclick="setCreatePreset('mon-sat')">Mon - Sat</button>
            <button type="button" class="btn-preset" onclick="setCreatePreset('mon-fri')">Mon - Fri</button>
            <button type="button" class="btn-preset" onclick="setCreatePreset('all')">All Days</button>
          </div>
        </div>

        <input 
          type="hidden" 
          name="token" 
          value="<?= $_SESSION['form_token'] ?>"
        >

        <button name="create" style="margin-top:20px;">
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
  <b>Password:</b> <?= $_SESSION['success']['pass'] ?>
</div>
<!-- QR CODE -->
<div style="margin-top:15px; text-align:center;">

  <p><b>Employee Check-in QR</b></p>

  <div id="qrContainer" style="
      background:white;
      padding:15px;
      border-radius:12px;
      display:inline-block;
      border:1px solid #ddd;
  ">

   <img 
  id="qrImage"
  crossorigin="anonymous"
  src="https://quickchart.io/qr?size=200&text=<?= urlencode($qrLink) ?>"
  alt="QR Code"
  style="
    display:block;
    margin:auto;
    width:200px;
    height:200px;
  "
>

    <div style="
        margin-top:12px;
        font-size:16px;
        font-weight:600;
        color:#111827;
    ">
      <?= $_SESSION['success']['name'] ?>
    </div>

    <div style="
        margin-top:5px;
        font-size:13px;
        color:#666;
    ">
      ID: <?= $_SESSION['success']['id'] ?>
    </div>

  </div>

</div>

        <!-- <button onclick="downloadQR()" type="button">
          ⬇ Download QR
        </button> -->

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
function checkAutoRedirect() {

    const now = new Date();
    const hours = now.getHours();
    const minutes = now.getMinutes();

    // 9:30 AM to 9:40 AM CHECK-IN (ONLY ONCE)
    if (hours === 9 && minutes >= 30 && minutes <= 40) {

        if (!localStorage.getItem("auto_checkin_done")) {
            localStorage.setItem("auto_checkin_done", "1");
            window.location.href = "../api/checkin.php";
        }
    }

    // 5:24 PM CHECK-OUT (ONLY ONCE)
    if (hours === 17 && minutes === 24) {

        if (!localStorage.getItem("auto_checkout_done")) {
            localStorage.setItem("auto_checkout_done", "1");
            window.location.href = "../api/checkout.php";
        }
    }
}
</script>

<?php unset($_SESSION['success']); ?>
<?php } ?>

<script>
function toggleCreateDay(el) {
  el.classList.toggle('selected');
  updateCreateDays();
}
function updateCreateDays() {
  const selected = [];
  document.querySelectorAll('#createDaysSelector .day-pill.selected').forEach(pill => {
    selected.push(pill.getAttribute('data-day'));
  });
  document.getElementById('createCheckInDaysInput').value = selected.join(',');
}
function setCreatePreset(preset) {
  const pills = document.querySelectorAll('#createDaysSelector .day-pill');
  pills.forEach(pill => {
    const d = pill.getAttribute('data-day');
    if (preset === 'all') {
      pill.classList.add('selected');
    } else if (preset === 'mon-sat') {
      pill.classList.toggle('selected', d !== 'Sun');
    } else if (preset === 'mon-fri') {
      pill.classList.toggle('selected', d !== 'Sat' && d !== 'Sun');
    }
  });
  updateCreateDays();
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</body>
</html>