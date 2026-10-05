<?php 
include("../config/db.php");
require_once "../config/branch_helper.php";
require_once "../config/mail_helper.php";

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

$adminBranchId = (int)($_SESSION['user']['branch_id'] ?? $_SESSION['branch_id'] ?? 0);
$adminBranch = trim($_SESSION['user']['branch'] ?? $_SESSION['branch'] ?? '');

$bStmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ? OR LOWER(branch_name) = LOWER(?)");
$bStmt->bind_param("is", $adminBranchId, $adminBranch);
$bStmt->execute();
$bRes = $bStmt->get_result()->fetch_assoc();
$branchName = $bRes ? $bRes['branch_name'] : ($adminBranch !== '' ? ucfirst($adminBranch) : 'Main');

// Determine branch-specific default timings:
// Thirthahalli: 10:00 AM to 08:00 PM (10 hrs)
// Other branches: 09:30 AM to 05:30 PM (8 hrs)
$isThirthahalli = (stripos($branchName, 'thirthahalli') !== false || stripos($adminBranch, 'thirthahalli') !== false || $adminBranchId === 6);

if ($isThirthahalli) {
  $defaultStartHour = '10';
  $defaultStartMin = '00';
  $defaultStartPeriod = 'AM';
  $defaultShiftStart = '10:00:00';

  $defaultEndHour = '08';
  $defaultEndMin = '00';
  $defaultEndPeriod = 'PM';
  $defaultShiftEnd = '20:00:00';

  $defaultWorkingHours = 10.00;
} else {
  $defaultStartHour = '09';
  $defaultStartMin = '30';
  $defaultStartPeriod = 'AM';
  $defaultShiftStart = '09:30:00';

  $defaultEndHour = '05';
  $defaultEndMin = '30';
  $defaultEndPeriod = 'PM';
  $defaultShiftEnd = '17:30:00';

  $defaultWorkingHours = 8.00;
}

if (isset($_POST['create'])) {

  if (
    !isset($_POST['token']) ||
    !hash_equals($_SESSION['form_token'], $_POST['token'])
  ) {
    die("Invalid request ❌");
  }

  $name = trim($_POST['name'] ?? '');
  $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
  $branch_id = $adminBranchId;
  $branch = !empty($branchName) ? $branchName : $adminBranch;

  $shift_start = !empty($_POST['shift_start']) ? date("H:i:s", strtotime($_POST['shift_start'])) : $defaultShiftStart;
  $shift_end = !empty($_POST['shift_end']) ? date("H:i:s", strtotime($_POST['shift_end'])) : $defaultShiftEnd;
  $working_hours = isset($_POST['working_hours']) && $_POST['working_hours'] !== '' ? max(0, min(24, (float)$_POST['working_hours'])) : $defaultWorkingHours;
  $monthly_cl = isset($_POST['monthly_cl']) && $_POST['monthly_cl'] !== '' ? max(0, min(31, (float)$_POST['monthly_cl'])) : 2.00;
  $check_in_days = !empty($_POST['check_in_days']) ? (is_array($_POST['check_in_days']) ? implode(',', $_POST['check_in_days']) : trim($_POST['check_in_days'])) : 'Mon,Tue,Wed,Thu,Fri,Sat';

  $empId = "EMP" . rand(1000,9999);
  $plainPassword = "EMP@" . rand(1000,9999);
  $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
  $token = bin2hex(random_bytes(32));

  $stmt = $conn->prepare("
    INSERT INTO users (name, email, employee_id, password, role, qr_token, branch, branch_id, shift_start, shift_end, working_hours, monthly_cl, check_in_days)
    VALUES (?, ?, ?, ?, 'employee', ?, ?, ?, ?, ?, ?, ?, ?)
  ");

  $stmt->bind_param("ssssssissdds", $name, $email, $empId, $hashedPassword, $token, $branch, $branch_id, $shift_start, $shift_end, $working_hours, $monthly_cl, $check_in_days);
  $stmt->execute();

  // Send credentials, schedule & QR code to employee email
  $mailStatus = null;
  if (!empty($email)) {
    try {
      $mailStatus = sendNewEmployeeWelcomeEmail([
        'name'          => $name,
        'email'         => $email,
        'employee_id'   => $empId,
        'password'      => $plainPassword,
        'branch'        => $branch,
        'branch_name'   => $branchName,
        'shift_start'   => $shift_start,
        'shift_end'     => $shift_end,
        'working_hours' => $working_hours,
        'monthly_cl'    => $monthly_cl,
        'check_in_days' => $check_in_days,
        'qr_token'      => $token
      ]);
    } catch (\Throwable $e) {
      $mailStatus = ['success' => false, 'error' => $e->getMessage()];
    }
  }

  $_SESSION['success'] = [
    "name"       => $name,
    "email"      => $email,
    "id"         => $empId,
    "pass"       => $plainPassword,
    "qr"         => $token,
    "mail_sent"  => ($mailStatus && !empty($mailStatus['success'])),
    "mail_error" => ($mailStatus && empty($mailStatus['success'])) ? ($mailStatus['error'] ?? 'Could not deliver email') : null
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
  width: 100%;
  max-width: 100vh;
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
    <a href="employee_settings.php">⚙️ Employee Settings</a>
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
          <label style="font-size:12px; font-weight:600; color:#374151;">Email Address</label>
          <input 
            type="email"
            name="email" 
            placeholder="Enter Employee Email" 
            required
            style="margin-top:5px;"
          >
        </div>

        <div style="display:flex; gap:12px; margin-top:12px; text-align:left;">
          <div style="flex:1;">
            <label style="font-size:12px; font-weight:600; color:#374151;">Shift Start Time (12-hr)</label>
            <div style="display:flex; align-items:center; background:#f9fafb; border:1px solid #d1d5db; border-radius:8px; padding:2px 8px; margin-top:5px; height:42px;">
              <select id="createStartHour" onchange="syncCreateTimes()" style="border:none; background:transparent; font-weight:600; font-size:14px; outline:none; cursor:pointer;">
                <?php for ($h = 1; $h <= 12; $h++): $hStr = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                  <option value="<?= $hStr ?>" <?= $hStr === $defaultStartHour ? 'selected' : '' ?>><?= $hStr ?></option>
                <?php endfor; ?>
              </select>
              <span style="font-weight:700; color:#9ca3af; margin:0 2px;">:</span>
              <select id="createStartMin" onchange="syncCreateTimes()" style="border:none; background:transparent; font-weight:600; font-size:14px; outline:none; cursor:pointer;">
                <?php for ($m = 0; $m < 60; $m++): $mStr = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                  <option value="<?= $mStr ?>" <?= $mStr === $defaultStartMin ? 'selected' : '' ?>><?= $mStr ?></option>
                <?php endfor; ?>
              </select>
              <select id="createStartPeriod" onchange="syncCreateTimes()" style="border:none; background:#eff6ff; color:#1d4ed8; font-weight:700; font-size:13px; border-radius:6px; padding:3px 6px; margin-left:auto; outline:none; cursor:pointer;">
                <option value="AM" <?= $defaultStartPeriod === 'AM' ? 'selected' : '' ?>>AM</option>
                <option value="PM" <?= $defaultStartPeriod === 'PM' ? 'selected' : '' ?>>PM</option>
              </select>
            </div>
            <input type="hidden" name="shift_start" id="createShiftStart" value="<?= htmlspecialchars($defaultShiftStart) ?>">
          </div>
          <div style="flex:1;">
            <label style="font-size:12px; font-weight:600; color:#374151;">Shift End Time (12-hr)</label>
            <div style="display:flex; align-items:center; background:#f9fafb; border:1px solid #d1d5db; border-radius:8px; padding:2px 8px; margin-top:5px; height:42px;">
              <select id="createEndHour" onchange="syncCreateTimes()" style="border:none; background:transparent; font-weight:600; font-size:14px; outline:none; cursor:pointer;">
                <?php for ($h = 1; $h <= 12; $h++): $hStr = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                  <option value="<?= $hStr ?>" <?= $hStr === $defaultEndHour ? 'selected' : '' ?>><?= $hStr ?></option>
                <?php endfor; ?>
              </select>
              <span style="font-weight:700; color:#9ca3af; margin:0 2px;">:</span>
              <select id="createEndMin" onchange="syncCreateTimes()" style="border:none; background:transparent; font-weight:600; font-size:14px; outline:none; cursor:pointer;">
                <?php for ($m = 0; $m < 60; $m++): $mStr = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                  <option value="<?= $mStr ?>" <?= $mStr === $defaultEndMin ? 'selected' : '' ?>><?= $mStr ?></option>
                <?php endfor; ?>
              </select>
              <select id="createEndPeriod" onchange="syncCreateTimes()" style="border:none; background:#eff6ff; color:#1d4ed8; font-weight:700; font-size:13px; border-radius:6px; padding:3px 6px; margin-left:auto; outline:none; cursor:pointer;">
                <option value="AM" <?= $defaultEndPeriod === 'AM' ? 'selected' : '' ?>>AM</option>
                <option value="PM" <?= $defaultEndPeriod === 'PM' ? 'selected' : '' ?>>PM</option>
              </select>
            </div>
            <input type="hidden" name="shift_end" id="createShiftEnd" value="<?= htmlspecialchars($defaultShiftEnd) ?>">
          </div>
        </div>

        <div style="text-align:left; margin-top:12px;">
          <label style="font-size:12px; font-weight:600; color:#374151;">Daily Working Hours</label>
          <input 
            type="number" 
            step="0.5" 
            min="1" 
            max="24" 
            name="working_hours" 
            id="createWorkingHours"
            value="<?= htmlspecialchars($defaultWorkingHours) ?>" 
            placeholder="e.g. <?= htmlspecialchars($defaultWorkingHours) ?>" 
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

        <?php if (!empty($_SESSION['success']['mail_sent'])): ?>
         
        <?php elseif (!empty($_SESSION['success']['mail_error'])): ?>
          <div style="background:#fffbeb; color:#92400e; border:1px solid #fde68a; border-radius:8px; padding:10px 14px; margin-top:12px; font-size:12.5px; text-align:left; display:flex; align-items:center; gap:8px;">
            <i class="bi bi-exclamation-triangle-fill" style="font-size:16px; color:#f59e0b; flex-shrink:0;"></i>
            <div>Account created, but email delivery issue: <?= htmlspecialchars($_SESSION['success']['mail_error']) ?></div>
          </div>
        <?php endif; ?>

     <div class="info" style="line-height:1.6; margin-top:12px;">
       <div><b>Employee ID:</b> <?= htmlspecialchars($_SESSION['success']['id']) ?></div>
       <?php if (!empty($_SESSION['success']['email'])): ?>
         <div><b>Email:</b> <?= htmlspecialchars($_SESSION['success']['email']) ?></div>
       <?php endif; ?>
       <div><b>Password:</b> <?= htmlspecialchars($_SESSION['success']['pass']) ?></div>
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
function syncCreateTimes() {
  function partsTo24Hr(h, m, p) {
    let hour = parseInt(h, 10);
    const minute = String(parseInt(m, 10)).padStart(2, '0');
    if (p === 'PM' && hour < 12) hour += 12;
    if (p === 'AM' && hour === 12) hour = 0;
    return String(hour).padStart(2, '0') + ':' + minute + ':00';
  }
  const sH = document.getElementById('createStartHour').value;
  const sM = document.getElementById('createStartMin').value;
  const sP = document.getElementById('createStartPeriod').value;
  const s24 = partsTo24Hr(sH, sM, sP);
  document.getElementById('createShiftStart').value = s24;

  const eH = document.getElementById('createEndHour').value;
  const eM = document.getElementById('createEndMin').value;
  const eP = document.getElementById('createEndPeriod').value;
  const e24 = partsTo24Hr(eH, eM, eP);
  document.getElementById('createShiftEnd').value = e24;

  // Auto-calculate daily working hours
  const [h1, m1] = s24.split(':').map(Number);
  const [h2, m2] = e24.split(':').map(Number);
  let startMinutes = h1 * 60 + m1;
  let endMinutes = h2 * 60 + m2;
  if (endMinutes <= startMinutes) {
    endMinutes += 24 * 60;
  }
  const diffMinutes = endMinutes - startMinutes;
  const decimalHours = parseFloat((diffMinutes / 60).toFixed(1));
  const whInput = document.getElementById('createWorkingHours');
  if (whInput && decimalHours > 0) {
    whInput.value = decimalHours;
  }
}

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