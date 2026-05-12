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
?>

<!DOCTYPE html>
<html>
<head>
  <title>Employee Dashboard</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body { background: #eef2f7; }

    .layout { display: flex; min-height: 100vh; }

    .sidebar {
      width: 250px;
      background: linear-gradient(180deg, #111827, #1f2937);
      color: white;
      padding: 20px;
    }

    .sidebar h2 {
      text-align: center;
      margin-bottom: 25px;
      font-size: 20px;
    }

    .sidebar a {
      display: block;
      padding: 12px 14px;
      margin: 8px 0;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      font-size: 14px;
    }

    .sidebar a:hover { background: rgba(255,255,255,0.1); }

    .sidebar .logout { background: #ef4444; }

    .main { flex: 1; padding: 25px; }

    h1 { margin-bottom: 20px; }

    .actions { display: flex; gap: 10px; margin-bottom: 20px; }

    .btn {
      padding: 10px 14px;
      border-radius: 8px;
      text-decoration: none;
      color: white;
      font-size: 13px;
    }

    .btn-green { background: #28a745; }
    .btn-red { background: #dc3545; }

    .card {
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 3px 10px rgba(0,0,0,0.08);
      margin-bottom: 20px;
    }

   table {
  width: 100%;
  border-collapse: collapse;
  min-width: 650px;
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

    .status-badge{
  padding:6px 12px;
  border-radius:20px;
  font-size:12px;
  font-weight:600;
  color:white;
  display:inline-block;
}

.status-approved{
  background:#22c55e;
}

.status-rejected{
  background:#ef4444;
}

.status-pending{
  background:#f59e0b;
}

/* =========================
   RESPONSIVE DESIGN
========================= */

@media (max-width: 992px) {

  .layout {
    flex-direction: column;
  }

  .sidebar {
    width: 100%;
    padding: 15px;
  }

  .sidebar h2 {
    margin-bottom: 15px;
  }

  .sidebar a {
    font-size: 13px;
    padding: 10px;
  }

  .main {
    padding: 15px;
  }

  h1 {
    font-size: 24px;
  }

  .card {
    padding: 15px;
    overflow-x: auto;
  }

  table {
    min-width: 700px;
  }

}

@media (max-width: 768px) {

  body {
    font-size: 14px;
  }

  .sidebar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
    align-items: center;
  }

  .sidebar h2 {
    width: 100%;
    text-align: center;
    margin-bottom: 10px;
  }

  .sidebar a {
    flex: 1 1 calc(50% - 10px);
    text-align: center;
    margin: 0;
    font-size: 12px;
  }

  .main {
    padding: 12px;
  }

  h1 {
    font-size: 22px;
    line-height: 1.4;
  }

  .card h2 {
    font-size: 18px;
    margin-bottom: 12px;
  }

  .status-badge {
    font-size: 11px;
    padding: 5px 10px;
  }

  td,
  th {
    padding: 10px;
    font-size: 12px;
  }

}

@media (max-width: 480px) {

  .sidebar a {
    flex: 1 1 100%;
  }

  h1 {
    font-size: 20px;
  }

  .card {
    border-radius: 10px;
  }

  .card h2 {
    font-size: 16px;
  }

  td,
  th {
    font-size: 11px;
    padding: 8px;
  }

  p {
    font-size: 13px;
  }

}
  </style>
</head>

<body>

<div class="layout">

<!-- SIDEBAR -->
<div class="sidebar">
  <h2>Employee Panel</h2>

  <a href="#"> Dashboard</a>
  <a href="apply_leave.php"> Apply Leave</a>
  <!-- <a href="../api/checkin.php">🟢 Check In</a>
  <a href="../api/checkout.php">🔴 Check Out</a> -->
  <a href="../auth/logout.php" class="logout"> Logout</a>
</div>

<!-- MAIN -->
<div class="main">

<h1>
  Welcome back,
  <?= htmlspecialchars($user['name']) ?> 
</h1>

<p style="
    margin-top:-10px;
    margin-bottom:25px;
    color:#6b7280;
    font-size:14px;
">
  Employee ID:
  <b><?= htmlspecialchars($user['employee_id']) ?></b>
</p>
<!-- <div class="actions">
  <a href="../api/checkin.php" class="btn btn-green">🟢 Check In</a>
  <a href="../api/checkout.php" class="btn btn-red">🔴 Check Out</a>
</div> -->

<!-- TODAY -->
<div class="card">
  <h2>Today's Attendance</h2>

<?php if ($attendance) { ?>

<?php

$checkInTime = date(
    "H:i:s",
    strtotime($attendance['check_in'])
);

$isLateWarning =
    $checkInTime >= "09:40:00" &&
    $checkInTime <= "10:00:00";

?>

<?php if ($isLateWarning) { ?>

<div style="
    background:#fef3c7;
    color:#92400e;
    padding:14px;
    border-radius:12px;
    margin-bottom:15px;
    border-left:5px solid #f59e0b;
    font-size:14px;
">

    <b>Late Check-In Notice ⚠</b><br><br>

    You checked in at
    <b>
        <?= date("h:i A", strtotime($attendance['check_in'])) ?>
    </b>.

    Your lunch break time has been reduced to
    <b>10 minutes</b> today.

</div>

<?php } ?>

<p><b>Status:</b> <?= $attendance['status']; ?></p>

<p>
    <b>Check In:</b>
    <?= date("h:i A", strtotime($attendance['check_in'])) ?>
</p>

<p>
    <b>Check Out:</b>

    <?= $attendance['check_out']
        ? date("h:i A", strtotime($attendance['check_out']))
        : "Not yet"
    ?>
</p>

<?php } else { ?>
    <p>No attendance marked today</p>
  <?php } ?>
</div>

<!-- LEAVES -->
<div class="card">
<h2>Leave History</h2>

<table>
<tr>
  <th>Date</th>
  <th>Type</th>
  <th>Reason</th>
  <th>Status</th>
</tr>

<?php while ($row = $leaves->fetch_assoc()) { ?>
<tr>
  <td><?= $row['date'] ?></td>
  <td><?= $row['type'] ?></td>
  <td><?= $row['reason'] ?></td>
<td>
  <span class="status-badge status-<?= strtolower($row['status']) ?>">
    <?= ucfirst($row['status']) ?>
  </span>
</td></tr>
<?php } ?>

</table>
</div>

<!-- HISTORY -->
<div class="card">
  <h2>Attendance History</h2>

  <table>
    <tr>
      <th>Employee ID</th>
      <th>Date</th>
      <th>Check In</th>
      <th>Check Out</th>
      <th>Status</th>
    </tr>

    <?php if ($allAttendance && $allAttendance->num_rows > 0) { ?>

      <?php while ($row = $allAttendance->fetch_assoc()) { ?>
        <tr>
  <td><?= $row['employee_id'] ?></td>
  <td><?= $row['date'] ?></td>
  <td><?= $row['check_in'] ?? '-' ?></td>
  <td><?= $row['check_out'] ?? '-' ?></td>
  <td><?= $row['status'] ?? '-' ?></td>
</tr>
      <?php } ?>

    <?php } else { ?>

      <tr>
        <td colspan="4">No attendance records found</td>
      </tr>

    <?php } ?>

  </table>
</div>

</div>
</div>

</body>
</html>