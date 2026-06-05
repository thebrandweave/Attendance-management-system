<?php
$lifetime = 60 * 60 * 24 * 30;
session_set_cookie_params($lifetime);
session_start();
include("../config/db.php");

date_default_timezone_set("Asia/Kolkata");

// 1. Process Missed Checkouts for historical logs safely before reading view data
$fixCheckout = $conn->query("
    SELECT * FROM attendance 
    WHERE check_in IS NOT NULL 
    AND (check_out IS NULL OR check_out = '') 
    AND date < CURDATE()
");

while ($att = $fixCheckout->fetch_assoc()) {
    $autoCheckoutTime = $att['date'] . " 17:30:00";
    $checkIn = strtotime($att['check_in']);
    $checkOut = strtotime($autoCheckoutTime);
    $totalSeconds = $checkOut - $checkIn;

    $lunchSeconds = 0;
    if (!empty($att['lunch_out']) && !empty($att['lunch_in'])) {
        $lunchSeconds = strtotime($att['lunch_in']) - strtotime($att['lunch_out']);
    }

    $workingHours = max(0, ($totalSeconds - $lunchSeconds) / 3600);
    $status = ($workingHours < 5) ? "Half Day" : "Present";

    $stmt = $conn->prepare("UPDATE attendance SET check_out = ?, total_hours = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sdsi", $autoCheckoutTime, $workingHours, $status, $att['id']);
    $stmt->execute();
    $stmt->close();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
    header("Location: ../index.php");
    exit();
}

$adminBranch = $_SESSION['user']['branch'];
$today = date("Y-m-d");

$stmt = $conn->prepare("SELECT * FROM users WHERE role='employee' AND branch=?");
$stmt->bind_param("s", $adminBranch);
$stmt->execute();
$employees = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    body { margin: 0; font-family: 'Poppins', sans-serif; background: #eef2f7; }
    .layout { display: flex; min-height: 100vh; }
    .sidebar { width: 250px; background: linear-gradient(180deg, #111827, #1f2937); color: white; padding: 20px; }
    .sidebar h2 { margin-bottom: 25px; font-size: 20px; text-align: center; }
    .sidebar a { display: block; padding: 12px 14px; margin: 8px 0; color: white; text-decoration: none; border-radius: 8px; transition: 0.3s; font-size: 14px; }
    .sidebar a:hover { background: rgba(255,255,255,0.1); transform: translateX(4px); }
    .sidebar .logout { background: #ef4444; }
    .sidebar .logout:hover { background: #dc2626; }
    .main { flex: 1; padding: 25px; }
    .header { background: white; padding: 18px; border-radius: 12px; font-weight: 600; margin-bottom: 20px; box-shadow: 0 3px 10px rgba(0,0,0,0.08); }
    .card { background: white; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
    .card h3 { margin-bottom: 15px; padding:7px; }
    table { width: 100%; border-collapse: collapse; overflow: hidden; border:1px solid black; }
    th { background: #667eea; color: white; padding: 12px; font-size: 13px; border:1px solid #333333; }
    td { padding: 12px; text-align: center; border: 1px solid #c4c4c4; font-size: 13px; }
    tr:hover { background: #f9fafb; }
    .status-present { color: green; font-weight: 600; }
    .status-late { color: orange; font-weight: 600; }
    .status-halfday { color: #d97706; font-weight: 600; }
    .status-absent { color: red; font-weight: 600; }
    .status-pending { color: gray; font-weight: 600; }
    .status-overtime { color: #7c3aed; font-weight: 600; }
    .filters { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; padding:7px; }
    .filters input, .filters select { padding:10px; border:1px solid #ddd; border-radius:8px; font-family:'Poppins',sans-serif; }
  </style>
</head>
<body>
<div class="layout">
  <div class="sidebar">
    <h2><?= htmlspecialchars($adminBranch) ?> Admin</h2>
    <a href="#">🏠 Dashboard</a>
    <a href="create_employee.php">👤 Create Employee</a>
    <a href="../api/checkin.php">🟢 Check In- Morning</a>
    <a href="../api/lunch.php">🍽️ Lunch Break</a>
    <a href="../api/checkout.php">🔴 Check Out- Evening</a>
    <?php
      $leaveCountQuery = $conn->query("SELECT COUNT(*) as total FROM leave_requests lr LEFT JOIN users u ON lr.employee_id = u.id WHERE u.branch='$adminBranch' AND lr.status='pending'");
      $leaveCount = $leaveCountQuery->fetch_assoc()['total'];
    ?>
    <a href="leave_requests.php">📩 Manage Leaves <?php if($leaveCount > 0) { ?><span style="background:#ef4444; color:white; padding:2px 8px; border-radius:50px; font-size:12px; margin-left:8px; font-weight:600;"><?= $leaveCount ?></span><?php } ?></a>
    <a href="add_leave.php">📅 Company Leaves</a>
    <a href="reports.php">📊 Reports</a>
    <a href="../auth/logout.php" class="logout">🚪 Logout</a>
  </div>

  <div class="main">
    <div class="header">Admin Dashboard - <?= htmlspecialchars($adminBranch) ?> Branch</div>
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
          $leaveCheck = $conn->prepare("SELECT id, title FROM company_leaves WHERE leave_date=?");
          $leaveCheck->bind_param("s", $today);
          $leaveCheck->execute();
          $leaveData = $leaveCheck->get_result()->fetch_assoc();
          $isCompanyLeave = !empty($leaveData);
          $leaveCheck->close();

          while ($emp = $employees->fetch_assoc()) {
              $empId = $emp['id'];
              $attStmt = $conn->prepare("SELECT * FROM attendance WHERE user_id=? AND date=?");
              $attStmt->bind_param("is", $empId, $today);
              $attStmt->execute();
              $todayAtt = $attStmt->get_result()->fetch_assoc();
              $attStmt->close();

              $currentHour = (int)date("H");
              $isSunday = (date("w") == 0 && strtolower($adminBranch) != "mudipu");
              
              // Evaluate baseline rules
              if (!$isSunday && !$isCompanyLeave && empty($todayAtt['check_in']) && $currentHour >= 16) {
                  $status = "Absent";
              } elseif ($isCompanyLeave) {
                  $status = "Company Leave";
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
function openEditModal(emp, attendance) {
    document.getElementById('editModal').style.display = 'flex';
    document.getElementById('editId').value = emp.id || '';
    document.getElementById('editName').value = emp.name || '';
    document.getElementById('editEmployeeId').value = emp.employee_id || '';
    document.getElementById('editStatus').value = attendance?.status || 'Pending';
    document.getElementById('editCheckIn').value = formatDateTime(attendance?.check_in);
    document.getElementById('editCheckOut').value = formatDateTime(attendance?.check_out);
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
    fetch(window.location.href)
    .then(response => response.text())
    .then(data => {
        let parser = new DOMParser();
        let newTable = parser.parseFromString(data, 'text/html').querySelector('#tableContainer').innerHTML;
        document.querySelector('#tableContainer').innerHTML = newTable;
    }).catch(error => console.log("Refresh Error:", error));
}
setInterval(loadAttendanceTable, 10000);
</script>
</body>
</html>