<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
    header("Location: ../index.php");
    exit();
}

$id = $_GET['id'];

$month = $_GET['month'] ?? date("Y-m");

/* =========================
   EMPLOYEE INFO
========================= */

$user = $conn->query("
    SELECT *
    FROM users
    WHERE id = '$id'
")->fetch_assoc();

/* =========================
   ATTENDANCE DETAILS
========================= */

$attendance = $conn->query("
    SELECT *
    FROM attendance
    WHERE user_id = '$id'
    AND date LIKE '$month%'
    ORDER BY date DESC
");

/* =========================
   SUMMARY
========================= */

$present = 0;
$absent = 0;
$half = 0;
$late = 0;

$temp = $conn->query("
    SELECT status, COUNT(*) as total
    FROM attendance
    WHERE user_id = '$id'
    AND date LIKE '$month%'
    GROUP BY status
");

while($r = $temp->fetch_assoc()){

    if($r['status']=="Present"){
        $present = $r['total'];
    }

    elseif($r['status']=="Absent"){
        $absent = $r['total'];
    }

    elseif($r['status']=="Half Day"){
        $half = $r['total'];
    }

    elseif($r['status']=="Late"){
        $late = $r['total'];
    }
}
?>

<!DOCTYPE html>
<html>

<head>

<title>Employee Report</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>

body{
    margin:0;
    font-family:'Poppins',sans-serif;
    background:#eef2f7;
}

.container{
    padding:25px;
}

.header{
    background:white;
    padding:20px;
    border-radius:12px;
    margin-bottom:20px;
    box-shadow:0 3px 10px rgba(0,0,0,0.08);
}

.cards{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-bottom:20px;
}

.card{
    background:white;
    padding:20px;
    border-radius:12px;
    text-align:center;
    box-shadow:0 3px 10px rgba(0,0,0,0.08);
}

.card h3{
    margin:0;
    font-size:14px;
    color:#777;
}

.card p{
    font-size:22px;
    font-weight:600;
}

.green{color:green;}
.red{color:red;}
.orange{color:orange;}
.blue{color:#3b82f6;}

table{
    width:100%;
    border-collapse:collapse;
    background:white;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 3px 10px rgba(0,0,0,0.08);
}

table th{
    background:#111827;
    color:white;
    padding:14px;
}

table td{
    padding:12px;
    border-bottom:1px solid #eee;
    text-align:center;
}

.status{
    padding:6px 12px;
    border-radius:20px;
    color:white;
    font-size:12px;
}

.present{
    background:green;
}

.absent{
    background:red;
}

.half{
    background:orange;
}

.late{
    background:#3b82f6;
}

.back{
    display:inline-block;
    margin-bottom:20px;
    background:#111827;
    color:white;
    text-decoration:none;
    padding:10px 16px;
    border-radius:8px;
}

</style>

</head>

<body>

<div class="container">

<a href="reports.php?month=<?= $month ?>" class="back">
← Back
</a>

<div class="header">

<h2>
<?= $user['name'] ?>
</h2>

<p>
Employee ID:
<b><?= $user['employee_id'] ?></b>
</p>

<p>
Month:
<b><?= $month ?></b>
</p>

</div>

<div class="cards">

<div class="card">
<h3>Present</h3>
<p class="green"><?= $present ?></p>
</div>

<div class="card">
<h3>Absent</h3>
<p class="red"><?= $absent ?></p>
</div>

<div class="card">
<h3>Half Day</h3>
<p class="orange"><?= $half ?></p>
</div>

<div class="card">
<h3>Late</h3>
<p class="blue"><?= $late ?></p>
</div>

</div>

<table>

<tr>

<th>Date</th>

<th>Status</th>

<th>Check In</th>

<th>Check Out</th>

<th>Remarks</th>

</tr>

<?php while($row = $attendance->fetch_assoc()): ?>

<tr>

<td>
<?= date("d M Y", strtotime($row['date'])) ?>
</td>

<td>

<?php if($row['status']=="Present"): ?>

<span class="status present">
Present
</span>

<?php elseif($row['status']=="Absent"): ?>

<span class="status absent">
Absent
</span>

<?php elseif($row['status']=="Half Day"): ?>

<span class="status half">
Half Day
</span>

<?php else: ?>

<span class="status late">
Late
</span>

<?php endif; ?>

</td>

<td>
<?= $row['check_in'] ? date("h:i A", strtotime($row['check_in'])) : "-" ?>
</td>

<td>
<?= $row['check_out'] ? date("h:i A", strtotime($row['check_out'])) : "-" ?>
</td>

<td>

<?php

if($row['status']=="Absent"){
    echo "Employee was absent";
}

elseif($row['status']=="Half Day"){
    echo "Worked half day";
}

elseif($row['status']=="Late"){
    echo "Employee arrived late";
}

else{
    echo "Full working day";
}

?>

</td>

</tr>

<?php endwhile; ?>

</table>

</div>

</body>
</html>