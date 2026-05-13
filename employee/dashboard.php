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
  AND (status = 'Present' OR status = 'Late' OR status = 'Overtime')
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

*{
  margin:0;
  padding:0;
  box-sizing:border-box;
  font-family:'Poppins',sans-serif;
}

body{
  background:#eef2f7;
}

/* =========================
   LAYOUT
========================= */

.layout{
  display:flex;
  min-height:100vh;
}

/* =========================
   SIDEBAR
========================= */

.sidebar{
  width:250px;
  background:linear-gradient(180deg,#111827,#1f2937);
  color:white;
  padding:20px;
  position:sticky;
  top:0;
  height:100vh;
}

.sidebar h2{
  text-align:center;
  margin-bottom:25px;
  font-size:20px;
}

.sidebar a{
  display:block;
  padding:12px 14px;
  margin:8px 0;
  color:white;
  text-decoration:none;
  border-radius:10px;
  font-size:14px;
  transition:0.3s;
}

.sidebar a:hover{
  background:rgba(255,255,255,0.1);
  transform:translateX(3px);
}

.sidebar .logout{
  background:#ef4444;
}

/* =========================
   MAIN
========================= */

.main{
  flex:1;
  padding:25px;
  overflow:hidden;
}

h1{
  margin-bottom:15px;
  color:#111827;
  font-size:32px;
  line-height:1.3;
}

/* =========================
   CARDS
========================= */

.card{
  background:white;
  padding:20px;
  border-radius:16px;
  box-shadow:0 5px 20px rgba(0,0,0,0.06);
  margin-bottom:20px;
  overflow:hidden;
}

.card h2{
  margin-bottom:18px;
  color:#111827;
  font-size:22px;
}

/* =========================
   TABLE
========================= */

.table-wrapper{
  width:100%;
  overflow-x:auto;
}

table{
  width:100%;
  border-collapse:collapse;
  min-width:700px;
}

th{
  background:#667eea;
  color:white;
  padding:14px;
  font-size:13px;
  white-space:nowrap;
}

td{
  padding:12px;
  text-align:center;
  border-bottom:1px solid #eee;
  font-size:13px;
  white-space:nowrap;
}

tr:hover{
  background:#f9fafb;
}

/* =========================
   STATUS
========================= */

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
   BUTTONS
========================= */

.actions{
  display:flex;
  gap:10px;
  margin-bottom:20px;
  flex-wrap:wrap;
}

.btn{
  padding:10px 14px;
  border-radius:10px;
  text-decoration:none;
  color:white;
  font-size:13px;
  transition:0.3s;
}

.btn:hover{
  opacity:0.9;
}

.btn-green{
  background:#16a34a;
}

.btn-red{
  background:#dc2626;
}

/* =========================
   LATE WARNING
========================= */

.warning-box{
  background:#fef3c7;
  color:#92400e;
  padding:14px;
  border-radius:12px;
  margin-bottom:15px;
  border-left:5px solid #f59e0b;
  font-size:14px;
  line-height:1.6;
}

/* =========================
   RESPONSIVE
========================= */

@media(max-width:992px){

  .layout{
    flex-direction:column;
  }

  .sidebar{
    width:100%;
    height:auto;
    position:relative;
    display:flex;
    flex-wrap:wrap;
    justify-content:center;
    gap:10px;
  }

  .sidebar h2{
    width:100%;
    margin-bottom:10px;
  }

  .sidebar a{
    margin:0;
    text-align:center;
    flex:1 1 calc(50% - 10px);
    min-width:140px;
  }

  .main{
    padding:18px;
  }

  h1{
    font-size:26px;
  }

  .card{
    padding:16px;
  }
}

@media(max-width:768px){

  .sidebar{
    padding:15px;
  }

  .sidebar a{
    flex:1 1 100%;
    font-size:13px;
  }

  h1{
    font-size:24px;
  }

  .card h2{
    font-size:18px;
  }

  td{
    padding:23px;
    font-size:12px;
  }

  th{
    padding:23px;
    font-size:12px;
  }

  p{
    font-size:14px;
    line-height:1.6;
  }

  .status-badge{
    font-size:11px;
  }

  .warning-box{
    font-size:13px;
  }
}

@media(max-width:480px){

  .main{
    padding:12px;
  }

  h1{
    font-size:20px;
  }

  .card{
    padding:14px;
    border-radius:12px;
  }

  .card h2{
    font-size:16px;
  }

  td{
    padding:23px;
    font-size:12px;
  }

  th{
    font-size:23px;
    padding:8px;
  }

  p{
    font-size:13px;
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
<div style="
  background:#16a34a;
  color:white;
  padding:15px 20px;
  border-radius:12px;
  display:inline-block;
  margin-bottom:20px;
">

  
</div>
<p style="
    margin-top:-10px;
    margin-bottom:25px;
    color:#6b7280;
    font-size:14px;
">
  Employee ID:
  <b><?= htmlspecialchars($user['employee_id']) ?></b>
</p>
  <div style="font-size:14px;">Total Present Days <span> <?= $totalPresentDays ?></span></div>
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

<div class="card">
  <h2>Leave History</h2>

  <div class="table-wrapper">
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
      </td>
    </tr>

    <?php } ?>

  </table>
  </div>
</div>


<!-- HISTORY -->
<div class="card">

  <h2>Attendance History</h2>

  <div class="table-wrapper">

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

        <td>
          <?= $row['check_in']
              ? date("h:i A", strtotime($row['check_in']))
              : '-' ?>
        </td>

        <td>
          <?= $row['check_out']
              ? date("h:i A", strtotime($row['check_out']))
              : '-' ?>
        </td>

        <td><?= $row['status'] ?? '-' ?></td>
      </tr>

      <?php } ?>

    <?php } else { ?>

      <tr>
        <td colspan="5">
          No attendance records found
        </td>
      </tr>

    <?php } ?>

  </table>

  </div>

</div>
</div>

</body>
</html>