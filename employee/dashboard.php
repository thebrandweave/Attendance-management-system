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

/* =======================
   TODAY ATTENDANCE
======================= */
$attendance = $conn->query("
  SELECT * FROM attendance 
  WHERE user_id = $userId AND date = '$today'
")->fetch_assoc();

/* =======================
   HISTORY
======================= */
$allAttendance = $conn->query("
  SELECT 
    u.employee_id,
    a.date,
    a.check_in,
    a.check_out,
    a.status
  FROM attendance a
  INNER JOIN users u ON a.user_id = u.id
  WHERE a.user_id = $userId
  AND DAYOFWEEK(a.date) != 1
  ORDER BY a.date DESC
");

/* =======================
   LEAVES (FIXED)
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
   SIDEBAR
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
}

.sidebar h2 {
  text-align: center;
  margin-bottom: 30px;
  font-size: 20px;
  font-weight: 600;
  letter-spacing: 0.5px;
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
  margin-top: auto; /* Pushes logout to bottom on desktop */
  text-align: center;
}

.sidebar .logout:hover {
  background: #dc2626;
  transform: none;
}

/* =========================
   MAIN CONTENT
========================= */
.main {
  flex: 1;
  padding: 40px;
  max-width: 1200px;
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

/* =========================
   CARDS & BADGES
========================= */
.present-badge {
  background: #16a34a;
  color: white;
  padding: 12px 20px;
  border-radius: 8px;
  display: inline-block;
  margin-bottom: 24px;
  font-size: 14px;
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
}

.card {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
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
   TABLES
========================= */
.table-wrapper {
  width: 100%;
  overflow-x: auto;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
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
  letter-spacing: 0.5px;
  border-bottom: 2px solid #e2e8f0;
}

td {
  padding: 14px 16px;
  border-bottom: 1px solid #e2e8f0;
  font-size: 14px;
  color: #334155;
  white-space: nowrap;
}

tr:last-child td {
  border-bottom: none;
}

tr:hover {
  background: #f8fafc;
}

/* =========================
   STATUS BADGES
========================= */
.status-badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  color: white;
  display: inline-block;
  text-align: center;
}

.status-approved { background: #16a34a; }
.status-rejected { background: #ef4444; }
.status-pending { background: #f59e0b; }

/* =========================
   NOTICES
========================= */
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
   RESPONSIVE MEDIA QUERIES (FIXED)
================================== */

/* Tablets and small laptops */
@media (max-width: 992px) {
  .layout {
    flex-direction: column;
  }

  .sidebar {
    width: 100%;
    height: auto;
    position: relative;
    padding: 20px;
    gap: 8px;
  }

  .sidebar h2 {
    margin-bottom: 15px;
  }

  .sidebar-menu {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    width: 100%;
  }

  .sidebar a {
    margin: 0;
    flex: 1 1 calc(33.33% - 8px);
    text-align: center;
    font-size: 13px;
    padding: 10px;
  }

  .sidebar .logout {
    margin-top: 0;
  }

  .main {
    padding: 24px;
  }
}

/* Mobile Screens */
@media (max-width: 600px) {
  .sidebar a {
    flex: 1 1 calc(50% - 8px);
  }
  
  .main {
    padding: 16px;
  }

  h1 {
    font-size: 22px;
  }

  .card {
    padding: 16px;
  }

  .card h2 {
    font-size: 16px;
  }

  th, td {
    padding: 10px 12px;
    font-size: 13px;
  }
}

/* Tiny Mobile Devices */
@media (max-width: 400px) {
  .sidebar a {
    flex: 1 1 100%;
  }
}
</style>
</head>

<body>

<div class="layout">

  <div class="sidebar">
    <h2>Employee Panel</h2>
    <div class="sidebar-menu">
      <a href="#">Dashboard</a>
      <a href="apply_leave.php">Apply Leave</a>
      <a href="../auth/logout.php" class="logout">Logout</a>
    </div>
  </div>

  <div class="main">

    <h1>Welcome back, <?= htmlspecialchars($user['name']) ?></h1>
    
    <div class="emp-id-text">
      Employee ID: <strong><?= htmlspecialchars($user['employee_id']) ?></strong>
    </div>

    <div class="present-badge">
      Total Present Days : <strong><?= $totalPresentDays ?></strong>
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
              <td><?= htmlspecialchars($row['date']) ?></td>
              <td><?= htmlspecialchars($row['type']) ?></td>
              <td><?= htmlspecialchars($row['reason']) ?></td>
              <td>
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
                <td><?= date("d M Y", strtotime($leave['leave_date'])) ?></td>
                <td><?= htmlspecialchars($leave['title']) ?></td>
                <td>
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
                <td colspan="3">No company leaves available</td>
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
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($allAttendance && $allAttendance->num_rows > 0) { ?>
              <?php while ($row = $allAttendance->fetch_assoc()) { ?>
              <tr>
                <td><?= htmlspecialchars($row['employee_id']) ?></td>
                <td><?= htmlspecialchars($row['date']) ?></td>
                <td><?= $row['check_in'] ? date("h:i A", strtotime($row['check_in'])) : '-' ?></td>
                <td><?= $row['check_out'] ? date("h:i A", strtotime($row['check_out'])) : '-' ?></td>
                <td><?= htmlspecialchars($row['status'] ?? '-') ?></td>
              </tr>
              <?php } ?>
            <?php } else { ?>
              <tr>
                <td colspan="5">No attendance records found</td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

</body>
</html>