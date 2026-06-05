<?php
session_start();
include("../config/db.php");

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit();
}

$user = $_SESSION['user'];
$userId = (int)$user['id'];
$today = date("Y-m-d");

/* =======================
   TOTAL PRESENT DAYS
======================= */
$presentQuery = $conn->query("
  SELECT COUNT(*) AS total_present
  FROM attendance
  WHERE user_id = $userId
  AND DAYOFWEEK(date) != 1
  AND (
      status = 'Present'
      OR status = 'Late'
      OR status = 'Overtime'
  )
");

$presentData = $presentQuery->fetch_assoc();
$totalPresentDays = $presentData['total_present'] ?? 0;


$halfQuery = $conn->query("
  SELECT COUNT(*) AS total_half
  FROM attendance
  WHERE user_id = $userId
  AND DAYOFWEEK(date) != 1
  AND status = 'Half Day'
");

$halfData = $halfQuery->fetch_assoc();
$totalHalfDays = $halfData['total_half'] ?? 0;

$absentQuery = $conn->query("
  SELECT COUNT(*) AS total_absent
  FROM attendance
  WHERE user_id = $userId
  AND DAYOFWEEK(date) != 1
  AND status = 'Absent'
");

$absentData = $absentQuery->fetch_assoc();
$totalAbsentDays = $absentData['total_absent'] ?? 0;

/* =======================
   TODAY ATTENDANCE
======================= */
$attendance = $conn->query("
  SELECT * FROM attendance 
  WHERE user_id = $userId AND date = '$today'
")->fetch_assoc();
  

$todayWorked = "-";

if ($attendance && !empty($attendance['check_in'])) {

    $startTime = strtotime($attendance['check_in']);

    if (!empty($attendance['check_out'])) {
        $endTime = strtotime($attendance['check_out']);
    } else {
        $endTime = time(); // current time if still working
    }

    $totalSeconds = $endTime - $startTime;

    // subtract lunch time if taken
    $lunchSeconds = 0;

    if (
        !empty($attendance['lunch_out']) &&
        !empty($attendance['lunch_in'])
    ) {
        $lunchSeconds =
            strtotime($attendance['lunch_in']) -
            strtotime($attendance['lunch_out']);
    }

    $finalSeconds = $totalSeconds - $lunchSeconds;

    $hours = floor($finalSeconds / 3600);
    $minutes = floor(($finalSeconds % 3600) / 60);

    $todayWorked = $hours . "h " . $minutes . "m";
}



/* =======================
   HISTORY
======================= */
$allAttendance = $conn->query("
  SELECT 
    u.employee_id,
    a.date,
    a.check_in,
    a.check_out,
    a.status,
    a.lunch_out,
a.lunch_in,
    cl.title AS leave_title
  FROM attendance a
  INNER JOIN users u
      ON a.user_id = u.id

  LEFT JOIN company_leaves cl
      ON cl.leave_date = a.date

  WHERE a.user_id = $userId
  AND DAYOFWEEK(a.date) != 1

  ORDER BY a.date DESC
");

/* =======================
   LEAVES
======================= */
$leaves = $conn->query("
  SELECT * FROM leave_requests 
  WHERE employee_id = $userId
  ORDER BY date DESC
");

/* =======================
   COMPANY LEAVES
======================= */
$companyLeaves = $conn->query("
    SELECT *
    FROM company_leaves
    ORDER BY leave_date DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Dashboard</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: 'Poppins', sans-serif;
}

body {
  background: #eef2f7;
  color: #334155;
}

/* =========================
   LAYOUT
========================= */
.layout {
  display: flex;
  min-height: 100vh;
}

/* =========================
   MOBILE HEADER (HAMBURGER)
========================= */
.mobile-top-bar {
  display: none;
  background: #111827;
  color: white;
  padding: 15px 20px;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 1000;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.mobile-top-bar h2 {
  font-size: 16px;
  font-weight: 500;
}

.hamburger-btn {
  background: none;
  border: none;
  color: white;
  font-size: 24px;
  cursor: pointer;
  display: flex;
  align-items: center;
}

/* =========================
   SIDEBAR (DESKTOP DEFAULT)
========================= */
.sidebar {
  width: 260px;
  background: linear-gradient(180deg, #111827, #1f2937);
  color: white;
  padding: 24px;
  position: sticky;
  top: 0;
  height: 100vh;
  display: flex;
  flex-direction: column;
  transition: transform 0.3s ease;
  z-index: 999;
}

.sidebar h2 {
  text-align: center;
  margin-bottom: 30px;
  font-size: 20px;
  font-weight: 600;
}

.sidebar a {
  display: block;
  padding: 12px 16px;
  margin: 6px 0;
  color: #cbd5e1;
  text-decoration: none;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.3s ease;
}

.sidebar a:hover {
  background: rgba(255, 255, 255, 0.08);
  color: white;
  transform: translateX(4px);
}

.sidebar .logout {
  background: #ef4444;
  color: white;
  margin-top: auto;
  text-align: center;
}

.sidebar .logout:hover {
  background: #dc2626;
  transform: none;
}

/* Overlay for Mobile Navigation drawer */
.sidebar-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.5);
  z-index: 998;
}

/* =========================
   MAIN CONTENT
========================= */
.main {
  flex: 1;
  padding: 40px;
  /* max-width: 1200px; */
  margin: 0 auto;
  width: 100%;
}

h1 {
  margin-bottom: 6px;
  color: #111827;
  font-size: 28px;
  font-weight: 600;
}

.emp-id-text {
  margin-bottom: 24px;
  color: #64748b;
  font-size: 14px;
}

.present-badge {
  background: #16a34a;
  color: white;
  padding: 12px 20px;
  border-radius: 8px;
  display: inline-block;
  margin-bottom: 24px;
  font-size: 14px;
}

.card {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  margin-bottom: 24px;
}

.card h2 {
  margin-bottom: 20px;
  color: #111827;
  font-size: 18px;
  font-weight: 600;
  border-bottom: 2px solid #f1f5f9;
  padding-bottom: 10px;
}

.card p {
  margin-bottom: 12px;
  font-size: 14px;
  color: #475569;
}

/* =========================
   TABLE CONFIGURATION
========================= */
.table-wrapper {
  width: 100%;
}

table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

th {
  background: #f8fafc;
  color: #475569;
  padding: 14px 16px;
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  border-bottom: 2px solid #e2e8f0;
}

td {
  padding: 14px 16px;
  border-bottom: 1px solid #e2e8f0;
  font-size: 14px;
  color: #334155;
}

tr:hover {
  background: #f8fafc;
}

/* Badges */
.status-badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  color: white;
  display: inline-block;
}
.status-approved { background: #16a34a; }
.status-rejected { background: #ef4444; }
.status-pending { background: #f59e0b; }

.warning-box {
  background: #fffbeb;
  color: #b45309;
  padding: 16px;
  border-radius: 8px;
  margin-bottom: 20px;
  border-left: 4px solid #f59e0b;
  font-size: 14px;
  line-height: 1.5;
}

/* ==================================
   RESPONSIVE DESIGN SYSTEM
================================== */

@media (max-width: 992px) {
  .layout {
    flex-direction: column;
  }

  .mobile-top-bar {
    display: flex; /* Shows top navigation header bar */
  }

  /* Morphing sidebar into off-canvas side drawer */
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 280px;
    transform: translateX(-100%); /* Hides offscreen initially */
    box-shadow: 5px 0 15px rgba(0,0,0,0.2);
  }

  /* Triggered via JS toggle setup */
  .sidebar.active {
    transform: translateX(0);
  }

  .sidebar.active + .sidebar-overlay {
    display: block;
  }

  .sidebar .logout {
    margin-top: 40px; 
  }

  .main {
    padding: 20px;
  }
}

/* CRITICAL BREAKPOINT FOR TABLE ADJUSTMENTS */
@media (max-width: 768px) {
  h1 { font-size: 22px; }
  .card { padding: 16px; }

  /* Force table structures to behave like block layouts */
  table, thead, tbody, th, td, tr {
    display: block;
  }

  /* Hide traditional header lines visually */
  thead tr {
    position: absolute;
    top: -9999px;
    left: -9999px;
  }

  tr {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 16px;
    padding: 8px 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
  }

  tr:hover {
    background: #ffffff;
  }

  td {
    border: none;
    border-bottom: 1px dashed #f1f5f9;
    position: relative;
    padding-left: 45% !important; /* Forces whitespace segment for custom row targets */
    text-align: left;
    white-space: normal;
    min-height: 40px;
  }

  td:last-child {
    border-bottom: none;
  }

  /* Feed pseudo-headers inline leveraging data attributes */
  td::before {
    content: attr(data-label);
    position: absolute;
    left: 16px;
    width: 40%;
    padding-right: 10px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    font-size: 11px;
    white-space: nowrap;
  }
}
</style>
</head>

<body>

<div class="mobile-top-bar">
  <h2>Employee Panel</h2>
  <button class="hamburger-btn" id="menuToggle">☰</button>
</div>

<div class="layout">

  <div class="sidebar" id="sidebar">
    <h2>Employee Panel</h2>
    <a href="#">Dashboard</a>
    <a href="apply_leave.php">Apply Leave</a>
    <a href="../auth/logout.php" class="logout">Logout</a>
  </div>
  
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <div class="main">

    <h1>Welcome back, <?= htmlspecialchars($user['name']) ?></h1>
    <div class="emp-id-text">
      Employee ID: <strong><?= htmlspecialchars($user['employee_id']) ?></strong>
    </div>

    <div style="
display:flex;
gap:12px;
flex-wrap:wrap;
margin-bottom:24px;
">
<div class="present-badge" style="background:#0f766e;">
  Worked Today : <strong><?= $todayWorked ?></strong>
</div>


<div class="present-badge" style="background:#16a34a;">
  Present : <strong><?= $totalPresentDays ?></strong>
</div>

<div class="present-badge" style="background:#f59e0b;">
  Half Day : <strong><?= $totalHalfDays ?></strong>
</div>

<div class="present-badge" style="background:#dc2626;">
  Absent : <strong><?= $totalAbsentDays ?></strong>
</div>

</div>

    <div class="card">
      <h2>Today's Attendance</h2>
      <?php if ($attendance) { 
        $checkInTime = date("H:i:s", strtotime($attendance['check_in']));
        $isLateWarning = ($checkInTime >= "09:40:00" && $checkInTime <= "10:00:00");
        
        if ($isLateWarning) { ?>
          <div class="warning-box">
            <strong>Late Check-In Notice ⚠</strong><br>
            You checked in at <strong><?= date("h:i A", strtotime($attendance['check_in'])) ?></strong>. Your lunch break time has been reduced to <strong>10 minutes</strong> today.
          </div>
        <?php } ?>

        <p><strong>Status:</strong> <?= htmlspecialchars($attendance['status']); ?></p>
        <p><strong>Check In:</strong> <?= date("h:i A", strtotime($attendance['check_in'])) ?></p>
        <p><strong>Check Out:</strong> <?= $attendance['check_out'] ? date("h:i A", strtotime($attendance['check_out'])) : "Not yet" ?></p>
      <?php } else { ?>
        <p>No attendance marked today</p>
      <?php } ?>
    </div>

    <div class="card">
      <h2>Leave History</h2>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Reason</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $leaves->fetch_assoc()) { ?>
            <tr>
              <td data-label="Date"><?= htmlspecialchars($row['date']) ?></td>
              <td data-label="Type"><?= htmlspecialchars($row['type']) ?></td>
              <td data-label="Reason"><?= htmlspecialchars($row['reason']) ?></td>
              <td data-label="Status">
                <span class="status-badge status-<?= strtolower($row['status']) ?>">
                  <?= ucfirst(htmlspecialchars($row['status'])) ?>
                </span>
              </td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h2>Company Leave Announcements</h2>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Leave Date</th>
              <th>Title</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if($companyLeaves->num_rows > 0): ?>
              <?php while($leave = $companyLeaves->fetch_assoc()): 
                $isToday = ($leave['leave_date'] == $today);
                $isUpcoming = ($leave['leave_date'] > $today);
              ?>
              <tr>
                <td data-label="Leave Date"><?= date("d M Y", strtotime($leave['leave_date'])) ?></td>
                <td data-label="Title"><?= htmlspecialchars($leave['title']) ?></td>
                <td data-label="Status">
                  <?php if($isToday): ?>
                    <span class="status-badge" style="background:#7c3aed;">Today</span>
                  <?php elseif($isUpcoming): ?>
                    <span class="status-badge" style="background:#2563eb;">Upcoming</span>
                  <?php else: ?>
                    <span class="status-badge" style="background:#6b7280;">Completed</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="3" style="text-align: center; padding-left: 16px !important;">No company leaves available</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h2>Attendance History</h2>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Employee ID</th>
              <th>Date</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Total Hours</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($allAttendance && $allAttendance->num_rows > 0) { ?>
              <?php while ($row = $allAttendance->fetch_assoc()) { ?>
              <tr>
                <td data-label="Employee ID"><?= htmlspecialchars($row['employee_id']) ?></td>
                <td data-label="Date"><?= htmlspecialchars($row['date']) ?></td>
                <td data-label="Check In"><?= $row['check_in'] ? date("h:i A", strtotime($row['check_in'])) : '-' ?></td>
               <td data-label="Check Out">
<?= $row['check_out']
? date("h:i A", strtotime($row['check_out']))
: '-' ?>
</td>

<td
data-label="Total Hours"
style="
font-weight:600;
color:#16a34a;
">
<?php
if (!empty($row['check_in']) && !empty($row['check_out'])) {

    $checkIn = strtotime($row['check_in']);
    $checkOut = strtotime($row['check_out']);

    $totalSeconds = $checkOut - $checkIn;

    $lunchSeconds = 0;

    if (
        !empty($row['lunch_out']) &&
        !empty($row['lunch_in'])
    ) {
        $lunchSeconds =
            strtotime($row['lunch_in']) -
            strtotime($row['lunch_out']);
    }

    $finalSeconds = $totalSeconds - $lunchSeconds;

    $hours = floor($finalSeconds / 3600);
    $minutes = floor(($finalSeconds % 3600) / 60);

    echo $hours . "h " . $minutes . "m";
} else {
    echo "-";
}
?>
</td>

<td data-label="Status">
<?php
$status = $row['status'] ?? '';

if ($status == 'CL') {

    $leaveTitle = !empty($row['leave_title'])
        ? $row['leave_title']
        : 'Company Leave';
?>
    <span style="
    background:#7c3aed;
    color:white;
    padding:5px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:600;
    ">
        <?= htmlspecialchars($leaveTitle) ?>
    </span>

<?php
} else {

    $color = '#6b7280';

    if ($status == 'Present')
        $color = '#16a34a';

    elseif ($status == 'Absent')
        $color = '#dc2626';

    elseif ($status == 'Half Day')
        $color = '#f59e0b';

    elseif ($status == 'Late')
        $color = '#2563eb';

    elseif ($status == 'Overtime')
        $color = '#7c3aed';
?>

    <span style="
    background:<?= $color ?>;
    color:white;
    padding:5px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:600;
    ">
        <?= htmlspecialchars($status) ?>
    </span>

<?php } ?>

</td>
              </tr>
              <?php } ?>
            <?php } else { ?>
              <tr>
                <td colspan="5" style="text-align: center; padding-left: 16px !important;">No attendance records found</td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
  const menuToggle = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  function toggleMenu() {
    sidebar.classList.toggle('active');
  }

  menuToggle.addEventListener('click', toggleMenu);
  sidebarOverlay.addEventListener('click', toggleMenu);
</script>

</body>
</html>