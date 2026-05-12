<?php
include("../config/db.php");

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
  header("Location: ../index.php");
  exit();
}

$today = date("Y-m-d");
$month = date("Y-m");

$employees = $conn->query("SELECT * FROM users WHERE role='employee'");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

  <style>
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

    /* ===== SIDEBAR (MATCHED EXACTLY) ===== */
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
      padding: 25px;
    }

    .header {
      background: white;
      padding: 18px;
      border-radius: 12px;
      font-weight: 600;
      margin-bottom: 20px;
      box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    }

    /* ===== CARD ===== */
    .card {
      background: white;
      padding: 10px;
      border-radius: 12px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    .card h3 {
      margin-bottom: 15px;
    }

    /* ===== TABLE ===== */
    table {
      width: 100%;
      border-collapse: collapse;
      border-radius: 10px;
      overflow: hidden;
    }

    th {
      background: #667eea;
      color: white;
      padding: 12px;
      font-size: 13px;
    }

    td {
      padding: 12px;
      text-align: center;
      border-bottom: 1px solid #eee;
      font-size: 13px;
    }

    tr:hover {
      background: #f9fafb;
    }

    /* ===== STATUS ===== */
    .status-present { color: green; font-weight: 600; }
    .status-late { color: orange; font-weight: 600; }
    .status-halfday { color: #d97706; font-weight: 600; }
    .status-absent { color: red; font-weight: 600; }
    
    .filters{
  display:flex;
  gap:10px;
  margin-bottom:20px;
  flex-wrap:wrap;
}

.filters input,
.filters select{
  padding:10px;
  border:1px solid #ddd;
  border-radius:8px;
  font-family:'Poppins',sans-serif;
}
.status-overtime {
    color: #7c3aed;
    font-weight: 600;
}
  </style>
</head>

<body>

<div class="layout">

  <!-- SIDEBAR (UPDATED STYLE MATCHED) -->
  <div class="sidebar">
    <h2>Admin Panel</h2>

    <a href="#">🏠 Dashboard</a>
    <a href="create_employee.php">👤 Create Employee</a>
      <a href="../api/checkin.php">🟢 Check In</a>
  <a href="../api/checkout.php">🔴 Check Out</a>
    <a href="leave_requests.php">📩 Manage Leaves</a>
    <a href="reports.php">📊 Reports</a>
    <a href="../auth/logout.php" class="logout">🚪 Logout</a>
  </div>

  <!-- MAIN -->
  <div class="main">

    <div class="header">
      Admin Dashboard - Attendance Management
    </div>

    <div class="card">
      <h3>Employee Attendance</h3>
 
      <div class="filters">

  <input 
    type="text"
    id="employeeFilter"
    placeholder="Search Employee ID"
    onkeyup="filterTable()"
  >

  <select id="statusFilter" onchange="filterTable()">
    <option value="">All Status</option>
    <option value="Present">Present</option>
    <option value="Absent">Absent</option>
    <option value="Half Day">Half Day</option>
  </select>

</div>

<table id="employeeTable">

   
          <tr>
     
          <th>Name</th>
          <th>ID</th>
          <th>Date</th>
          <th>Status</th>
          <th>Check In</th>
          <!-- <th>Lunch Out</th> -->
<th>Lunch Break</th>
<th>Lunch Hours</th>
          <th>Check Out</th>
          <th>Hours</th>
          <th>Present</th>
          <th>Half</th>
          <th>Absent</th>
          <th>Action</th>
        </tr>

<?php while ($emp = $employees->fetch_assoc()) {

  $empId = $emp['id'];

  $empCreatedDate = date("Y-m-d", strtotime($emp['created_at']));
  $isNewEmployee = ($empCreatedDate == $today);

  $todayAtt = $conn->query("
    SELECT * FROM attendance 
    WHERE user_id=$empId AND date='$today'
  ")->fetch_assoc();

if ($isNewEmployee) {

    $status = null;

} else {

    $status = $todayAtt['status'] ?? "Pending";

    /*
    ============================================
    OVERTIME STATUS
    IF WORKING HOURS > 7
    ============================================
    */

    if (
        !empty($todayAtt['check_in']) &&
        !empty($todayAtt['check_out'])
    ) {

        $totalSeconds =
            strtotime($todayAtt['check_out']) -
            strtotime($todayAtt['check_in']);

        $lunchSeconds = 0;

        if (
            !empty($todayAtt['lunch_out']) &&
            !empty($todayAtt['lunch_in'])
        ) {

            $lunchSeconds =
                strtotime($todayAtt['lunch_in']) -
                strtotime($todayAtt['lunch_out']);
        }

        $workingHours =
            ($totalSeconds - $lunchSeconds) / 3600;

        if ($workingHours > 7) {

            $status = "Overtime";

            $conn->query("
                UPDATE attendance
                SET status='Overtime'
                WHERE id='{$todayAtt['id']}'
            ");
        }
    }
}

  $present = 0;
  $half = 0;
  $absent = 0;

  if (!$isNewEmployee) {
    $monthly = $conn->query("
      SELECT * FROM attendance 
      WHERE user_id=$empId AND date LIKE '$month%'
    ");

    while ($m = $monthly->fetch_assoc()) {
      if ($m['status'] == "Present") $present++;
      elseif ($m['status'] == "Half Day") $half++;
      elseif ($m['status'] == "Pending") $absent++;
    }
  }

  $perDay = 500;
  $salary = ($present * $perDay) + ($half * ($perDay / 2));
/*
============================================
TOTAL HOURS
Check In -> Check Out
============================================
*/

$totalHours = "-";
$totalHoursValue = 0;

if (
    !empty($todayAtt['check_in']) &&
    !empty($todayAtt['check_out'])
) {

    $totalSeconds =
        strtotime($todayAtt['check_out']) -
        strtotime($todayAtt['check_in']);

    $totalHoursValue = $totalSeconds / 3600;

    $totalHours = round($totalHoursValue, 2);
}

/*
============================================
LUNCH HOURS
Lunch Out -> Lunch In
============================================
*/

$lunchHours = "-";
$lunchHoursValue = 0;

if (
    !empty($todayAtt['lunch_out']) &&
    !empty($todayAtt['lunch_in'])
) {

    $lunchSeconds =
        strtotime($todayAtt['lunch_in']) -
        strtotime($todayAtt['lunch_out']);

    $lunchHoursValue = $lunchSeconds / 3600;

    $lunchHours = round($lunchHoursValue, 2);
}

/*
============================================
FINAL WORKING HOURS
Total Hours - Lunch Hours
============================================
*/

$workingHours = "-";

if ($totalHours !== "-") {

    $finalHours = $totalHoursValue - $lunchHoursValue;

    $workingHours = round($finalHours, 2) . " hrs";
}
?>

<tr
  data-id="<?= strtolower($emp['employee_id']) ?>"
  data-status="<?= strtolower($status) ?>"
>
<td>
  <?= $emp['name'] ?>

  <i class="bi bi-pencil-square"
     onclick='openEditModal(
        <?= json_encode($emp) ?>,
        <?= json_encode($todayAtt) ?>
     )'
     style="
        margin-left:8px;
        color:#667eea;
        cursor:pointer;
        font-size:14px;
     ">
  </i>
</td>

  <td><?= $emp['employee_id'] ?></td>
  <td><?= $today ?></td>

  <td>
    <?php if ($isNewEmployee) { ?>
      <span style="color:gray;">Not Started</span>
    <?php } else { ?>
      <span class="status-<?= strtolower(str_replace(' ', '', $status)) ?>">
        <?= $status ?>
      </span>
    <?php } ?>
  </td>

  <td>
    <?= !empty($todayAtt['check_in'])
        ? date("d-m-Y h:i A", strtotime($todayAtt['check_in']))
        : '-' ?>
  </td>
<td>
<?= !empty($todayAtt['lunch_out'])
    ? date("h:i A", strtotime($todayAtt['lunch_out']))
    : '-' ?>
    <span>/</span>
    <?= !empty($todayAtt['lunch_in'])
    ? date("h:i A", strtotime($todayAtt['lunch_in']))
    : '-' ?>
</td>

<!-- <td>

</td> -->

<td><?= $lunchHours ?></td>
  <td>
    <?= !empty($todayAtt['check_out'])
        ? date("d-m-Y h:i A", strtotime($todayAtt['check_out']))
        : '-' ?>
  </td>

<td><?= $workingHours ?></td>
  <td><?= $present ?></td>
  <td><?= $half ?></td>
  <td><?= $absent ?></td>
    <td>
  <a href="delete_employee.php?id=<?= $emp['id'] ?>"
     onclick="return confirm('Are you sure you want to delete this employee?')"
     style="
        background:#ef4444;
        color:white;
        padding:8px 12px;
        border-radius:6px;
        text-decoration:none;
        font-size:12px;
        font-weight:600;
     ">
     Delete
  </a>
</td>
</tr>

<?php } ?>

      </table>
    </div>

  </div>
</div>
<!-- EDIT MODAL -->
<div id="editModal" style="
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.4);
    justify-content:center;
    align-items:center;
    z-index:999;
">

  <div style="
      background:white;
      padding:25px;
      border-radius:12px;
      width:420px;
      max-height:90vh;
      overflow:auto;
  ">

    <h3>Edit Employee Details</h3>

    <form method="POST" action="update_employee.php">

      <input type="hidden" name="id" id="editId">

      <!-- NAME -->
      <label>Name</label>
      <input type="text"
             name="name"
             id="editName"
             required
             style="width:100%;padding:12px;margin-top:8px;margin-bottom:15px;border:1px solid #ddd;border-radius:8px;">

      <!-- EMPLOYEE ID -->
      <label>Employee ID</label>
      <input type="text"
             name="employee_id"
             id="editEmployeeId"
             required
             style="width:100%;padding:12px;margin-top:8px;margin-bottom:15px;border:1px solid #ddd;border-radius:8px;">

      <!-- STATUS -->
      <label>Status</label>
      <select name="status"
              id="editStatus"
              style="width:100%;padding:12px;margin-top:8px;margin-bottom:15px;border:1px solid #ddd;border-radius:8px;">

          <option value="Present">Present</option>
          <option value="Late">Late</option>
          <option value="Half Day">Half Day</option>
          <option value="Pending">Pending</option>
          <option value="Overtime">Overtime</option>

      </select>

      <!-- CHECK IN -->
      <label>Check In</label>
      <input type="datetime-local"
             name="check_in"
             id="editCheckIn"
             style="width:100%;padding:12px;margin-top:8px;margin-bottom:15px;border:1px solid #ddd;border-radius:8px;">

      <!-- CHECK OUT -->
      <label>Check Out</label>
      <input type="datetime-local"
             name="check_out"
             id="editCheckOut"
             style="width:100%;padding:12px;margin-top:8px;margin-bottom:15px;border:1px solid #ddd;border-radius:8px;">

      <div style="
          margin-top:20px;
          display:flex;
          gap:10px;
      ">

        <button type="submit" style="
            flex:1;
            padding:12px;
            border:none;
            border-radius:8px;
            background:#667eea;
            color:white;
            font-weight:600;
            cursor:pointer;
        ">
          Update
        </button>

        <button type="button"
                onclick="closeEditModal()"
                style="
                    flex:1;
                    padding:12px;
                    border:none;
                    border-radius:8px;
                    background:#ef4444;
                    color:white;
                    font-weight:600;
                    cursor:pointer;
                ">
          Cancel
        </button>

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


    document.getElementById('editStatus').value =
        attendance?.status || 'Pending';

    document.getElementById('editCheckIn').value =
        formatDateTime(attendance?.check_in);

    document.getElementById('editCheckOut').value =
        formatDateTime(attendance?.check_out);
}

function closeEditModal() {

    document.getElementById('editModal').style.display = 'none';
}

function filterTable() {

    const idFilter = document
        .getElementById("employeeFilter")
        .value
        .toLowerCase();

    const statusFilter = document
        .getElementById("statusFilter")
        .value
        .toLowerCase();

    const rows = document.querySelectorAll("#employeeTable tr[data-id]");

    rows.forEach(row => {

        const empId = row.getAttribute("data-id");

        const status = row.getAttribute("data-status");

        const idMatch = empId.includes(idFilter);

        const statusMatch =
            statusFilter === "" ||
            status.includes(statusFilter);

        row.style.display =
            (idMatch && statusMatch)
            ? ""
            : "none";
    });
}

</script>
</body>
</html>