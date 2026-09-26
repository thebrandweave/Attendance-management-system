<?php
$lifetime = 60 * 60 * 24 * 30;
session_set_cookie_params($lifetime);
session_start();
include("../config/db.php");
require_once "../config/branch_helper.php";

ensureEmployeeSettingsColumns($conn);

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
    header("Location: ../index.php");
    exit();
}

$adminBranchId = $_SESSION['user']['branch_id'] ?? 0;
$adminBranch = $_SESSION['user']['branch'] ?? '';

// Fetch dynamic branch details from DB if available
$bStmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ? OR LOWER(branch_name) = LOWER(?)");
$bStmt->bind_param("is", $adminBranchId, $adminBranch);
$bStmt->execute();
$bRes = $bStmt->get_result()->fetch_assoc();
$adminBranchName = $bRes ? $bRes['branch_name'] : ucfirst($adminBranch);
$attTable = getBranchTableNameOnly($conn, $adminBranchName);

$isThirthahalliBranch = (
    strtolower(trim($adminBranchName)) === "thirthahalli" ||
    strtolower(trim($adminBranch)) === "thirthahalli"
);

$sundayIsWorking = (
    $isThirthahalliBranch ||
    strtolower(trim($adminBranchName)) === "mudipu" ||
    strtolower(trim($adminBranch)) === "mudipu"
);

if ($isThirthahalliBranch) {

    // Thirthahalli working hours
    $officeStartTime = "10:00:00";
    $officeEndTime   = "20:00:00";

    // Mark absent only later in the working day
    $absentMarkTime  = "18:00:00";

} else {

    // Existing branch timings
    $officeStartTime = "09:30:00";
    $officeEndTime   = "17:30:00";

    $absentMarkTime  = "16:00:00";
}

// 1. Process Missed Checkouts for historical logs safely before reading view data
$fixCheckout = $conn->query("
    SELECT * FROM `$attTable` 
    WHERE check_in IS NOT NULL 
    AND (check_out IS NULL OR check_out = '') 
    AND date < CURDATE()
");

while ($att = $fixCheckout->fetch_assoc()) {

    $autoCheckoutTime =
        $att['date'] . " " . $officeEndTime;

    $checkIn = strtotime($att['check_in']);
    $checkOut = strtotime($autoCheckoutTime);

    $totalSeconds = $checkOut - $checkIn;

    $lunchSeconds = 0;

    if (!empty($att['lunch_out']) && !empty($att['lunch_in'])) {
        $lunchSeconds =
            strtotime($att['lunch_in']) -
            strtotime($att['lunch_out']);
    }

    $workingHours =
        max(0, ($totalSeconds - $lunchSeconds) / 3600);

    $status = ($workingHours < 5)
        ? "Half Day"
        : "Present";

    $stmt = $conn->prepare("
        UPDATE `$attTable`
        SET check_out = ?,
            total_hours = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sdsi",
        $autoCheckoutTime,
        $workingHours,
        $status,
        $att['id']
    );

    $stmt->execute();
    $stmt->close();
}

$today = date("Y-m-d");

$stmt = $conn->prepare("SELECT * FROM users WHERE role='employee' AND (branch_id=? OR (branch_id IS NULL AND branch=?))");
$stmt->bind_param("is", $adminBranchId, $adminBranch);
$stmt->execute();
$employees = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { margin: 0; font-family: 'Poppins', sans-serif; background: #eef2f7; color: #111827; }
    .layout { display: flex; min-height: 100vh; }

    /* Mobile Header */
    .mobile-top-bar {
      display: none;
      background: #111827;
      color: white;
      padding: 14px 20px;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    }
    .mobile-top-bar .bar-title {
      font-size: 16px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .hamburger-btn {
      background: none;
      border: none;
      color: white;
      font-size: 24px;
      cursor: pointer;
      display: flex;
      align-items: center;
      padding: 4px;
    }

    /* Sidebar */
    .sidebar { width: 250px; background: linear-gradient(180deg, #111827, #1f2937); color: white; padding: 20px; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 1001; box-sizing: border-box; display: flex; flex-direction: column; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .sidebar-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; }
    .sidebar h2 { font-size: 20px; text-align: center; font-family: 'Poppins', sans-serif; font-weight: 600; margin: 0; flex: 1; }
    .sidebar-close-btn { display: none; background: none; border: none; color: #94a3b8; font-size: 24px; cursor: pointer; }
    .sidebar-nav { display: flex; flex-direction: column; gap: 4px; flex: 1; }
    .sidebar a { display: block; padding: 12px 14px; margin: 4px 0; color: white; text-decoration: none; border-radius: 8px; transition: 0.3s; font-size: 14px; font-family: 'Poppins', sans-serif; line-height: 1.5; }
    .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); transform: translateX(4px); font-weight: 600; }
    .sidebar .logout { background: #ef4444; margin-top: auto; text-align: center; }
    .sidebar .logout:hover { background: #dc2626; transform: none; }

    /* Overlay */
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
    .sidebar-overlay.active { display: block; opacity: 1; pointer-events: auto; }

    .main { flex: 1; margin-left: 250px; width: calc(100% - 250px); padding: 25px; min-width: 0; box-sizing: border-box; }
    .header { background: white; padding: 18px; border-radius: 12px; font-weight: 600; margin-bottom: 20px; box-shadow: 0 3px 10px rgba(0,0,0,0.08); }
    .card { background: white; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); padding: 15px; }
    .card h3 { margin-bottom: 15px; padding: 7px; }
    #tableContainer { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table { width: 100%; border-collapse: collapse; min-width: 800px; border: 1px solid black; }
    th { background: #667eea; color: white; padding: 12px; font-size: 13px; border: 1px solid #333333; white-space: nowrap; }
    td { padding: 12px; text-align: center; border: 1px solid #c4c4c4; font-size: 13px; white-space: nowrap; }
    tr:hover { background: #f9fafb; }
    .status-present { color: green; font-weight: 600; }
    .status-late { color: orange; font-weight: 600; }
    .status-halfday { color: #d97706; font-weight: 600; }
    .status-absent { color: red; font-weight: 600; }
    .status-pending { color: gray; font-weight: 600; }
    .status-overtime { color: #7c3aed; font-weight: 600; }
    .status-weeklyoff { color: #6b7280; font-weight: 600; }
    .status-companyleave { color: #0d9488; font-weight: 600; }
    .filters { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; padding: 7px; }
    .filters input, .filters select { padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 14px; }

    @media (max-width: 992px) {
      .layout { flex-direction: column; }
      .mobile-top-bar { display: flex; }
      .sidebar {
        transform: translateX(-100%);
        width: 270px;
        max-width: 85vw;
        box-shadow: 10px 0 25px rgba(0, 0, 0, 0.3);
      }
      .sidebar.active { transform: translateX(0); }
      .sidebar-close-btn { display: block; }
      .main { margin-left: 0; width: 100%; padding: 16px; }
      .filters input, .filters select { width: 100%; }
    }
  </style>
</head>
<body>

<!-- Mobile Header Bar -->
<div class="mobile-top-bar">
  <div class="bar-title">
    <i class="bi bi-shield-lock"></i> <?= htmlspecialchars($adminBranchName) ?> Admin
  </div>
  <button class="hamburger-btn" id="menuToggle" aria-label="Toggle navigation">
    <i class="bi bi-list"></i>
  </button>
</div>

<div class="layout">
  <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <h2><?= htmlspecialchars($adminBranchName) ?> Admin</h2>
      <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">&times;</button>
    </div>
    <div class="sidebar-nav">
      <a href="dashboard.php" class="active">🏠 Dashboard</a>
      <a href="create_employee.php">👤 Create Employee</a>
      <a href="../api/checkin.php">🟢 Check In- Morning</a>
      <a href="../api/lunch.php">🍽️ Lunch Break</a>
      <a href="../api/checkout.php">🔴 Check Out- Evening</a>
      <?php
        $leaveCountQuery = $conn->query("SELECT COUNT(*) as total FROM leave_requests lr LEFT JOIN users u ON lr.employee_id = u.id WHERE (u.branch_id='$adminBranchId' OR u.branch='$adminBranch') AND lr.status='pending'");
        $leaveCount = $leaveCountQuery ? $leaveCountQuery->fetch_assoc()['total'] : 0;
      ?>
      <a href="leave_requests.php">📩 Manage Leaves <?php if($leaveCount > 0) { ?><span style="background:#ef4444; color:white; padding:2px 8px; border-radius:50px; font-size:12px; margin-left:8px; font-weight:600;"><?= $leaveCount ?></span><?php } ?></a>
      <a href="add_leave.php">📅 Company Leaves</a>
      <a href="reports.php">📊 Reports</a>
      <a href="employee_settings.php">⚙️ Employee Settings</a>
    </div>
    <a href="../auth/logout.php" class="logout">🚪 Logout</a>
  </div>

  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <div class="main">
    <div class="header">Admin Dashboard - <?= htmlspecialchars($adminBranchName) ?> Branch</div>
    <div class="card">
      <h3>Employee Attendance</h3>
      <div class="filters">
        <input type="text" id="employeeFilter" placeholder="Search Employee ID" onkeyup="filterTable()">
        <select id="statusFilter" onchange="filterTable()">
          <option value="">All Status</option>
          <option value="Present">Present</option>
          <option value="Absent">Absent</option>
          <option value="Half Day">Half Day</option>
          <option value="Pending">Pending</option>
          <option value="Overtime">Overtime</option>
        </select>
      </div>

      <div id="tableContainer">
        <table id="employeeTable">
          <tr>
            <th>Name</th>
            <th>ID</th>
            <th>Date</th>
            <th>Status</th>
            <th>Check In</th>
            <th>Lunch Break</th>
            <th>Lunch Hours</th>
            <th>Check Out</th>
            <th>Hours</th>
            <th>Present</th>
            <th>Half</th>
            <th>Absent</th>
            <th>Action</th>
          </tr>
          <?php 
          $leaveCheck = $conn->prepare("SELECT id, title FROM company_leaves WHERE leave_date=? AND (branch_id=? OR branch=?)");
          $leaveCheck->bind_param("sis", $today, $adminBranchId, $adminBranch);
          $leaveCheck->execute();
          $leaveData = $leaveCheck->get_result()->fetch_assoc();
          $isCompanyLeave = !empty($leaveData);
          $leaveCheck->close();

          while ($emp = $employees->fetch_assoc()) {
              $empId = $emp['id'];
              $attStmt = $conn->prepare("SELECT * FROM `$attTable` WHERE user_id=? AND date=?");
              $attStmt->bind_param("is", $empId, $today);
              $attStmt->execute();
              $todayAtt = $attStmt->get_result()->fetch_assoc();
              $attStmt->close();

         $currentTime = date("H:i:s");
         $empCheckInDays = getEmployeeCheckInDaysArray($emp['check_in_days'] ?? '', $adminBranchName);
         $todayDayName = date("D");
         $isWorkingDayForEmp = in_array($todayDayName, $empCheckInDays, true);

// Evaluate baseline rules
if ($isCompanyLeave) {
    $status = "Company Leave";
} elseif (!$isWorkingDayForEmp) {
    $status = !empty($todayAtt['status']) ? $todayAtt['status'] : "Weekly Off";
} elseif (
    empty($todayAtt['check_in']) &&
    $currentTime >= $absentMarkTime
) {
    $status = "Absent";
} else {
    $status = $todayAtt['status'] ?? "Pending";
}

              // Compute presentation metrics safely
              $present = ($status == "Present" || $status == "Late" || $status == "Overtime") ? 1 : 0;
              $half = ($status == "Half Day") ? 1 : 0;
              $absent = ($status == "Absent") ? 1 : 0;

              // Read Display Parameters
              $checkInDisp = (!empty($todayAtt['check_in'])) ? date("h:i A", strtotime($todayAtt['check_in'])) : '-';
              $checkOutDisp = (!empty($todayAtt['check_out'])) ? date("h:i A", strtotime($todayAtt['check_out'])) : '-';
              
              $lunchHours = "-";
              if (!empty($todayAtt['lunch_out']) && !empty($todayAtt['lunch_in'])) {
                  $lOut = strtotime($todayAtt['lunch_out']);
                  $lIn = strtotime($todayAtt['lunch_in']);
                  if ($lIn > $lOut) {
                      $diff = $lIn - $lOut;
                      $lunchHours = floor($diff / 3600) . " hrs " . floor(($diff % 3600) / 60) . " mins";
                  }
              }

              $workingHours = "-";
              if (!empty($todayAtt['check_in']) && !empty($todayAtt['check_out'])) {
                  $tSec = strtotime($todayAtt['check_out']) - strtotime($todayAtt['check_in']);
                  $lSec = 0;
                  if (!empty($todayAtt['lunch_out']) && !empty($todayAtt['lunch_in'])) {
                      $lOut = strtotime($todayAtt['lunch_out']);
                      $lIn = strtotime($todayAtt['lunch_in']);
                      if ($lIn > $lOut) { $lSec = $lIn - $lOut; }
                  }
                  $wSec = $tSec - $lSec;
                  if ($wSec > 0) {
                      $workingHours = floor($wSec / 3600) . " hrs " . floor(($wSec % 3600) / 60) . " mins";
                  } else {
                      $workingHours = "0 hrs";
                  }
              }
          ?>
          <tr data-id="<?= strtolower($emp['employee_id']) ?>" data-status="<?= strtolower($status) ?>">
            <td><?= htmlspecialchars($emp['name']) ?></td>
            <td><?= htmlspecialchars($emp['employee_id']) ?></td>
            <td><?= htmlspecialchars($today) ?></td>
            <td style="border-right: 1px solid #7e7c7c;">
              <span class="status-<?= strtolower(str_replace(' ', '', $status)) ?>"><?= htmlspecialchars($status) ?></span>
            </td>
            <td style="background-color:#c5c2c0; color:black; border: 1px solid #7e7c7c;"><?= $checkInDisp ?></td>
            <td>
              <?= (!empty($todayAtt['lunch_out'])) ? date("h:i A", strtotime($todayAtt['lunch_out'])) : '-' ?> / 
              <?= (!empty($todayAtt['lunch_in'])) ? date("h:i A", strtotime($todayAtt['lunch_in'])) : '-' ?>
            </td>
            <td style="border-right: 1px solid #7e7c7c;"><?= htmlspecialchars($lunchHours) ?></td>
            <td style="background-color:#c5c2c0; color:black; border: 1px solid #7e7c7c;"><?= $checkOutDisp ?></td>
            <td style="color:green; font-weight:700;"><?= htmlspecialchars($workingHours) ?></td>
            <td><?= $present ?></td>
            <td><?= $half ?></td>
            <td><?= $absent ?></td>
            <td>
              <a href="delete_employee.php?id=<?= $emp['id'] ?>" onclick="return confirm('Are you sure?')" style="background:#ef4444; color:white; padding:8px 12px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:600;">Delete</a>
              <button onclick='openEditModal(<?= json_encode($emp) ?>, <?= json_encode($todayAtt) ?>)' style="background:#667eea; color:white; border:none; margin-left:10px; padding:8px 12px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600;"><i class="bi bi-pencil-square"></i> Edit</button>
            </td>
          </tr>
          <?php } ?>
        </table>
      </div>
    </div>
  </div>
</div>

<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); justify-content:center; align-items:center; z-index:999;">
  <div style="background:white; padding:25px; border-radius:12px; width:420px; max-height:90vh; overflow:auto;">
    <h3>Edit Employee Details</h3>
    <form method="POST" action="update_employee.php">
      <input type="hidden" name="branch" value="<?= htmlspecialchars($adminBranch) ?>">
      <input type="hidden" name="id" id="editId">
      <label>Name</label>
      <input type="text" name="name" id="editName" required style="width:100%;padding:12px;margin-top:8px;margin-bottom:15px;border:1px solid #ddd;border-radius:8px;">
      <label>Employee ID</label>
      <input type="text" name="employee_id" id="editEmployeeId" required style="width:100%;padding:12px;margin-top:8px;margin-bottom:15px;border:1px solid #ddd;border-radius:8px;">
      <label>Status</label>
      <select name="status" id="editStatus" style="width:100%;padding:12px;margin-top:8px;margin-bottom:15px;border:1px solid #ddd;border-radius:8px;">
          <option value="Present">Present</option>
          <option value="Late">Late</option>
          <option value="Half Day">Half Day</option>
          <option value="Pending">Pending</option>
          <option value="Absent">Absent</option>
          <option value="Overtime">Overtime</option>
      </select>
      <label>Check In</label>
      <input type="datetime-local" name="check_in" id="editCheckIn" style="width:100%;padding:12px;margin-top:8px;margin-bottom:15px;border:1px solid #ddd;border-radius:8px;">
      <label>Check Out</label>
      <input type="datetime-local" name="check_out" id="editCheckOut" style="width:100%;padding:12px;margin-top:8px;margin-bottom:15px;border:1px solid #ddd;border-radius:8px;">
      
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:15px; margin-bottom:15px;">
        <div style="font-weight:600; font-size:13.5px; color:#4338ca; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
          <i class="bi bi-sliders"></i> Employee Settings
        </div>
        <label style="font-size:12.5px; font-weight:500;">Daily Working Hours (hrs)</label>
        <input type="number" step="0.5" min="1" max="24" name="working_hours" id="editWorkingHours" style="width:100%;padding:10px;margin-top:5px;margin-bottom:10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
        <label style="font-size:12.5px; font-weight:500;">Monthly CL (days)</label>
        <input type="number" step="0.5" min="0" max="31" name="monthly_cl" id="editMonthlyCL" style="width:100%;padding:10px;margin-top:5px;margin-bottom:10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
        <label style="font-size:12.5px; font-weight:500;">Specific Check-in Days</label>
        <input type="hidden" name="check_in_days" id="editCheckInDaysInput" value="Mon,Tue,Wed,Thu,Fri,Sat">
        <div class="days-selector" id="editDaysSelector" style="display:flex;gap:5px;flex-wrap:wrap;margin-top:5px;">
          <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
            <div class="day-pill" data-day="<?= $d ?>" onclick="toggleEditModalDay(this)" style="padding:6px 10px;border-radius:6px;font-size:12px;font-weight:600;border:1.5px solid #ddd;background:#f9fafb;cursor:pointer;user-select:none;">
              <?= $d ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:6px;margin-top:6px;">
          <button type="button" class="btn-preset" onclick="setEditModalPreset('mon-sat')" style="padding:2px 7px;font-size:11px;border-radius:4px;border:1px dashed #999;background:white;cursor:pointer;">Mon - Sat</button>
          <button type="button" class="btn-preset" onclick="setEditModalPreset('mon-fri')" style="padding:2px 7px;font-size:11px;border-radius:4px;border:1px dashed #999;background:white;cursor:pointer;">Mon - Fri</button>
          <button type="button" class="btn-preset" onclick="setEditModalPreset('all')" style="padding:2px 7px;font-size:11px;border-radius:4px;border:1px dashed #999;background:white;cursor:pointer;">All Days</button>
        </div>
      </div>

      <div style="margin-top:20px; display:flex; gap:10px;">
        <button type="submit" style="flex:1; padding:12px; border:none; border-radius:8px; background:#667eea; color:white; font-weight:600; cursor:pointer;">Update</button>
        <button type="button" onclick="closeEditModal()" style="flex:1; padding:12px; border:none; border-radius:8px; background:#ef4444; color:white; font-weight:600; cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function formatDateTime(dateTime) {
    if (!dateTime) return "";
    let date = new Date(dateTime);
    let year = date.getFullYear();
    let month = String(date.getMonth() + 1).padStart(2, '0');
    let day = String(date.getDate()).padStart(2, '0');
    let hours = String(date.getHours()).padStart(2, '0');
    let minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}
function toggleEditModalDay(el) {
    el.classList.toggle('selected');
    if (el.classList.contains('selected')) {
        el.style.background = '#667eea';
        el.style.color = 'white';
        el.style.borderColor = '#667eea';
    } else {
        el.style.background = '#f9fafb';
        el.style.color = '#4b5563';
        el.style.borderColor = '#ddd';
    }
    updateEditModalDaysInput();
}
function updateEditModalDaysInput() {
    const selected = [];
    document.querySelectorAll('#editDaysSelector .day-pill.selected').forEach(pill => {
        selected.push(pill.getAttribute('data-day'));
    });
    document.getElementById('editCheckInDaysInput').value = selected.join(',');
}
function setEditModalPreset(preset) {
    document.querySelectorAll('#editDaysSelector .day-pill').forEach(pill => {
        const d = pill.getAttribute('data-day');
        let sel = false;
        if (preset === 'all') sel = true;
        else if (preset === 'mon-sat') sel = (d !== 'Sun');
        else if (preset === 'mon-fri') sel = (d !== 'Sat' && d !== 'Sun');

        pill.classList.toggle('selected', sel);
        if (sel) {
            pill.style.background = '#667eea';
            pill.style.color = 'white';
            pill.style.borderColor = '#667eea';
        } else {
            pill.style.background = '#f9fafb';
            pill.style.color = '#4b5563';
            pill.style.borderColor = '#ddd';
        }
    });
    updateEditModalDaysInput();
}
function openEditModal(emp, attendance) {
    document.getElementById('editModal').style.display = 'flex';
    document.getElementById('editId').value = emp.id || '';
    document.getElementById('editName').value = emp.name || '';
    document.getElementById('editEmployeeId').value = emp.employee_id || '';
    document.getElementById('editStatus').value = attendance?.status || 'Pending';
    document.getElementById('editCheckIn').value = formatDateTime(attendance?.check_in);
    document.getElementById('editCheckOut').value = formatDateTime(attendance?.check_out);
    document.getElementById('editWorkingHours').value = (emp.working_hours !== undefined && emp.working_hours !== null) ? parseFloat(emp.working_hours) : 8.0;
    document.getElementById('editMonthlyCL').value = (emp.monthly_cl !== undefined && emp.monthly_cl !== null) ? parseFloat(emp.monthly_cl) : 2.0;

    let dStr = emp.check_in_days || 'Mon,Tue,Wed,Thu,Fri,Sat';
    let dArr = dStr.split(',').map(s => s.trim());
    document.querySelectorAll('#editDaysSelector .day-pill').forEach(pill => {
        const d = pill.getAttribute('data-day');
        const sel = dArr.includes(d);
        pill.classList.toggle('selected', sel);
        if (sel) {
            pill.style.background = '#667eea';
            pill.style.color = 'white';
            pill.style.borderColor = '#667eea';
        } else {
            pill.style.background = '#f9fafb';
            pill.style.color = '#4b5563';
            pill.style.borderColor = '#ddd';
        }
    });
    updateEditModalDaysInput();
}
function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }
function filterTable() {
    const idFilter = document.getElementById("employeeFilter").value.toLowerCase();
    const statusFilter = document.getElementById("statusFilter").value.toLowerCase();
    const rows = document.querySelectorAll("#employeeTable tr[data-id]");
    rows.forEach(row => {
        const empId = row.getAttribute("data-id");
        const status = row.getAttribute("data-status");
        row.style.display = (empId.includes(idFilter) && (statusFilter === "" || status.includes(statusFilter))) ? "" : "none";
    });
}
function loadAttendanceTable() {
    const editModal = document.getElementById('editModal');
    if (editModal && editModal.style.display === 'flex') {
        return; // Do not interrupt editing
    }
    fetch(window.location.href)
    .then(response => response.text())
    .then(data => {
        let parser = new DOMParser();
        let parsedDoc = parser.parseFromString(data, 'text/html');
        let newTable = parsedDoc.querySelector('#tableContainer');
        if (newTable) {
            document.querySelector('#tableContainer').innerHTML = newTable.innerHTML;
        }
    }).catch(error => console.log("Refresh Error:", error));
}
setInterval(loadAttendanceTable, 10000);

// Mobile Sidebar Drawer
const menuToggle = document.getElementById('menuToggle');
const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
  sidebar.classList.add('active');
  sidebarOverlay.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeSidebar() {
  sidebar.classList.remove('active');
  sidebarOverlay.classList.remove('active');
  document.body.style.overflow = '';
}

if (menuToggle) menuToggle.addEventListener('click', openSidebar);
if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape' && sidebar.classList.contains('active')) {
    closeSidebar();
  }
});
</script>
</body>
</html>