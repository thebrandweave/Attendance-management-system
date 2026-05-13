<?php
session_start();
include("../config/db.php");

date_default_timezone_set("Asia/Kolkata");

/* =========================
   AUTH CHECK
========================= */

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
    header("Location: ../index.php");
    exit();
}

/* =========================
   FILTERS
========================= */

$month = $_GET['month'] ?? date("Y-m");
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$branch = $_SESSION['user']['branch'];

/* =========================
   TOTAL DAYS IN MONTH
========================= */

$totalDaysInMonth = cal_days_in_month(
    CAL_GREGORIAN,
    date('m', strtotime($month)),
    date('Y', strtotime($month))
);

/* =========================
   DASHBOARD SUMMARY
========================= */

$summary = $conn->query("
    SELECT
        attendance.status,
        COUNT(*) as total

    FROM attendance

    INNER JOIN users
    ON users.id = attendance.user_id

    WHERE attendance.date LIKE '$month%'
    AND users.branch = '$branch'

    GROUP BY attendance.status
");

$present = 0;
$half = 0;
$absent = 0;
$late = 0;

while ($row = $summary->fetch_assoc()) {

    if ($row['status'] == "Present") {
        $present = $row['total'];
    }

    elseif ($row['status'] == "Half Day") {
        $half = $row['total'];
    }

    elseif ($row['status'] == "Absent") {
        $absent = $row['total'];
    }

    elseif ($row['status'] == "Late") {
        $late = $row['total'];
    }
}

$totalAttendance = $present + $absent + $half;

$attendancePercent = $totalAttendance
    ? round((($present + ($half * 0.5)) / $totalAttendance) * 100, 2)
    : 0;

/* =========================
   EMPLOYEE SUMMARY REPORT
========================= */

$whereEmployee = "
users.role='employee'
AND users.branch='$branch'
";

if (!empty($search)) {
    $whereEmployee .= "
    AND (
        users.employee_id LIKE '%$search%'
        OR users.name LIKE '%$search%'
    )";
}

$employees = $conn->query("
    SELECT
        users.id,
        users.name,
        users.employee_id,

        SUM(
            CASE
                WHEN attendance.status='Present'
                    OR attendance.status='Overtime'
                THEN 1
                ELSE 0
            END
        ) as present_count,

        SUM(
            CASE
                WHEN attendance.status='Absent'
                
                THEN 1
                ELSE 0
            END
        ) as absent_count,

        SUM(
            CASE
                WHEN attendance.status='Half Day'
                THEN 1
                ELSE 0
            END
        ) as halfday_count,

        SUM(
            CASE
                WHEN attendance.status='Late'
                THEN 1
                ELSE 0
            END
        ) as late_count

    FROM users

    LEFT JOIN attendance
    ON users.id = attendance.user_id
    AND attendance.date LIKE '$month%'

    WHERE $whereEmployee

    GROUP BY users.id

    ORDER BY users.name ASC
");

/* =========================
   HISTORY SECTION
========================= */

$whereHistory = "
attendance.date LIKE '$month%'
AND users.role='employee'
AND users.branch='$branch'
";

if (!empty($search)) {

    $whereHistory .= "
    AND (
        users.employee_id LIKE '%$search%'
        OR users.name LIKE '%$search%'
    )";
}

if (!empty($status_filter)) {
    $whereHistory .= "
    AND attendance.status='$status_filter'";
}

$history = $conn->query("
    SELECT
        users.name,
        users.employee_id,
        attendance.date,
        attendance.status,
        attendance.check_in,
       attendance.lunch_out,
    attendance.lunch_in,
    attendance.check_out,
    attendance.total_hours

    FROM attendance

    INNER JOIN users
    ON users.id = attendance.user_id

    WHERE $whereHistory

    ORDER BY attendance.date DESC
");
?>

<!DOCTYPE html>
<html>

<head>

    <title>Premium Attendance Reports</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f7fb;
            color: #111827;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 260px;
            background: linear-gradient(180deg,#111827,#1f2937);
            color: white;
            padding: 25px;
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 20px;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 13px 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: 0.3s;
            font-size: 14px;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .logout {
            background: #ef4444;
        }

        /* MAIN */

        .main {
            flex: 1;
            padding: 25px;
        }

        .header {
            background: white;
            padding: 22px;
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }

        .header h1 {
            font-size: 26px;
        }

        .header p {
            color: #6b7280;
            margin-top: 5px;
        }

        /* FILTER */

        .filter-box {
            background: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }

        .filter-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        input,
        select {
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            font-family: inherit;
            min-width: 180px;
        }

        button {
            border: none;
            padding: 12px 18px;
            border-radius: 10px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
            transition: 0.3s;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
        }

        .btn-print {
            background: #111827;
            color: white;
        }

        .btn-reset {
            background: #ef4444;
            color: white;
            padding:10px;
            font-family: inherit;
            font-weight: 400;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        button:hover,
        .btn-reset:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        /* CARDS */

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 18px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            transition: 0.3s;
        }

        .card:hover {
            transform: translateY(-4px);
        }

        .card h3 {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .card p {
            font-size: 30px;
            font-weight: 700;
        }

        .green { color: #16a34a; }
        .red { color: #dc2626; }
        .orange { color: #f59e0b; }
        .blue { color: #2563eb; }

        /* TABLE */

        .table-wrapper {
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            margin-bottom: 30px;
        }

        .table-title {
            padding: 20px;
            font-size: 18px;
            font-weight: 600;
            border-bottom: 1px solid #eee;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #111827;
            color: white;
            padding: 14px;
            font-size: 14px;
            position: sticky;
            top: 0;
        }

        td {
            padding: 14px;
            border: 1px solid #eee;
            text-align: center;
            font-size: 14px;
        }

        tr:hover {
            background: #f9fafb;
        }

        /* BADGES */

        .badge {
            padding: 6px 10px;
            border-radius: 30px;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.present {
            background: #16a34a;
        }

        .badge.absent {
            background: #dc2626;
        }

        .badge.half {
            background: #f59e0b;
        }

        .badge.late {
            background: #2563eb;
        }

        /* PRINT */

        @media print {

            .sidebar,
            .filter-box,
            button,
            .btn-reset {
                display: none !important;
            }

            body {
                background: white;
            }

            .main {
                padding: 0;
            }

            .table-wrapper,
            .card,
            .header {
                box-shadow: none;
            }
        }

        /* MOBILE */

        @media(max-width:900px) {

            .layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
        }

        @media(max-width:700px) {

            table {
                display: block;
                overflow-x: auto;
            }

            .filter-form {
                flex-direction: column;
            }
        }

    </style>

</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->

    <div class="sidebar">

 <h2 style="text-align:center;">
  <?= htmlspecialchars($_SESSION['user']['branch']) ?> Admin 
</h2>

        <a href="dashboard.php">🏠 Dashboard</a>

        <a href="create_employee.php">👤 Create Employee</a>

      

        <a href="../api/checkin.php">🟢 Check In- Morning</a>
 <a href="../api/lunch.php">🍽️ Lunch Break</a>
        <a href="../api/checkout.php">🔴 Check Out- Evening</a>
          <a href="leave_requests.php">📩 Manage Leaves</a>

        <a href="reports.php">📊 Reports</a>

        <a href="../auth/logout.php" class="logout">🚪 Logout</a>

    </div>

    <!-- MAIN -->

    <div class="main">

        <!-- HEADER -->

        <div class="header">

            <h1> Attendance Reports</h1>

           

        </div>

        <!-- FILTER -->

        <div class="filter-box">

            <form method="GET" class="filter-form">

                <input
                    type="month"
                    name="month"
                    value="<?= $month ?>"
                >

                <input
                    type="text"
                    name="search"
                    placeholder="Search Employee Name / ID"
                    value="<?= $search ?>"
                >

                <select name="status">

                    <option value="">All Status</option>

                    <option
                        value="Present"
                        <?= $status_filter == 'Present' ? 'selected' : '' ?>
                    >
                        Present
                    </option>

                    <option
                        value="Absent"
                        <?= $status_filter == 'Absent' ? 'selected' : '' ?>
                    >
                        Absent
                    </option>

                    <option
                        value="Half Day"
                        <?= $status_filter == 'Half Day' ? 'selected' : '' ?>
                    >
                        Half Day
                    </option>

                    <option
                        value="Late"
                        <?= $status_filter == 'Late' ? 'selected' : '' ?>
                    >
                        Late
                    </option>

                </select>

                <button type="submit" class="btn-primary">
                    Filter
                </button>

               <button
    type="button"
    class="btn-print"
    onclick="printSummary()"
>
    Print Report
</button>

          

            </form>

        </div>

        <!-- CARDS -->

        <!-- <div class="cards">

            <div class="card">
                <h3>Total Present</h3>
                <p class="green"><?= $present ?></p>
            </div>

            <div class="card">
                <h3>Total Absent</h3>
                <p class="red"><?= $absent ?></p>
            </div>

            <div class="card">
                <h3>Half Days</h3>
                <p class="orange"><?= $half ?></p>
            </div>

            <div class="card">
                <h3>Late Count</h3>
                <p class="blue"><?= $late ?></p>
            </div>

            <div class="card">
                <h3>Attendance %</h3>
                <p class="blue"><?= $attendancePercent ?>%</p>
            </div>

        </div> -->

        <!-- EMPLOYEE SUMMARY -->

        <div class="table-wrapper" id="employeeSummary">

            <div class="table-title">
                👨‍💼 Employee Monthly Report
            </div>

            <table>

                <tr>

                    <th>Name</th>

                    <th>Employee ID</th>

                    <th>Present</th>

                    <th>Absent</th>

                    <th>Half Day</th>

                    <th>Late</th>

                    <th>Total Attendance</th>

                </tr>

                <?php while($emp = $employees->fetch_assoc()): ?>

                <?php

                $finalAttendance =
                    $emp['present_count']
                    +
                    ($emp['halfday_count'] * 0.5);

                ?>

                <tr>

                    <td>
                        <?= $emp['name'] ?>
                    </td>

                    <td>
                        <?= $emp['employee_id'] ?>
                    </td>

                    <td class="green">
                        <?= $emp['present_count'] ?? 0 ?>
                    </td>

                    <td class="red">
                        <?= $emp['absent_count'] ?? 0 ?>
                    </td>

                    <td class="orange">
                        <?= $emp['halfday_count'] ?? 0 ?>
                    </td>

                    <td class="blue">
                        <?= $emp['late_count'] ?? 0 ?>
                    </td>

                    <td style="font-weight:600;">

                        <?= $finalAttendance ?>

                        /

                        <?= $totalDaysInMonth ?>

                        Days

                    </td>

                </tr>

                <?php endwhile; ?>

            </table>

        </div>

        <!-- HISTORY -->

        <div class="table-wrapper">

            <div class="table-title">
                🕒 Recent Attendance History
            </div>

            <table>

                <tr>

                    <th>Date</th>

                    <th>Name</th>

                    <th>Employee ID</th>

                    <th>Status</th>

                <th>Check In</th>

<th>Lunch Out</th>

<th>Lunch In</th>

<th>Check Out</th>

<th>Total Hours</th>

                </tr>

                <?php while($row = $history->fetch_assoc()): ?>

                <tr>

                    <td>
                        <?= date("d M Y", strtotime($row['date'])) ?>
                    </td>

                    <td>
                        <?= $row['name'] ?>
                    </td>

                    <td>
                        <?= $row['employee_id'] ?>
                    </td>

                    <td>

                        <?php if($row['status'] == 'Present'): ?>

                            <span class="badge present">
                                Present
                            </span>

                        <?php elseif($row['status'] == 'Absent'): ?>

                            <span class="badge absent">
                                Absent
                            </span>

                        <?php elseif($row['status'] == 'Half Day'): ?>

                            <span class="badge half">
                                Half Day
                            </span>
<?php elseif($row['status'] == 'Overtime'): ?>

    <span class="badge present">
        Present
    </span>

<?php else: ?>

    <span class="badge late">
        Late
    </span>

<?php endif; ?>

                    </td>

                <td>
    <?= !empty($row['check_in'])
        ? date("h:i A", strtotime($row['check_in']))
        : '-' ?>
</td>

<td>
    <?= !empty($row['lunch_out'])
        ? date("h:i A", strtotime($row['lunch_out']))
        : '-' ?>
</td>

<td>
    <?= !empty($row['lunch_in'])
        ? date("h:i A", strtotime($row['lunch_in']))
        : '-' ?>
</td>

<td>
    <?= !empty($row['check_out'])
        ? date("h:i A", strtotime($row['check_out']))
        : '-' ?>
</td>

<td style="font-weight:600;color:#16a34a;">

<?php

/*
=========================================
TOTAL HOURS
Check In -> Check Out
=========================================
*/
$workingHours = "-";

if (
    !empty($row['check_in']) &&
    !empty($row['check_out'])
) {

    $checkIn  = strtotime($row['check_in']);
    $checkOut = strtotime($row['check_out']);

    $totalSeconds = $checkOut - $checkIn;

    /*
    =========================================
    LUNCH HOURS
    =========================================
    */

    $lunchSeconds = 0;

    if (
        !empty($row['lunch_out']) &&
        !empty($row['lunch_in'])
    ) {
        $lunchOut = strtotime($row['lunch_out']);
        $lunchIn  = strtotime($row['lunch_in']);

        $lunchSeconds = $lunchIn - $lunchOut;
    }

    /*
    =========================================
    FINAL WORKING TIME (IN SECONDS)
    =========================================
    */

    $finalSeconds = $totalSeconds - $lunchSeconds;

    if ($finalSeconds < 0) {
        $finalSeconds = 0;
    }

    // convert to HOURS + MINUTES (REAL 60 MIN FORMAT)
    $hours = floor($finalSeconds / 3600);
    $minutes = floor(($finalSeconds % 3600) / 60);

    $workingHours = $hours . "." . $minutes . " hrs";
}

echo $workingHours;
?>

</td>

                </tr>

                <?php endwhile; ?>

            </table>

        </div>

    </div>

</div>
<script>

function printSummary() {

    const printContent =
        document.getElementById("employeeSummary").innerHTML;

    const originalContent = document.body.innerHTML;

    document.body.innerHTML = `
        <html>
        <head>
            <title>Employee Monthly Summary</title>

            <style>

                body{
                    font-family:Poppins,sans-serif;
                    padding:20px;
                }

                table{
                    width:100%;
                    border-collapse:collapse;
                }

                th{
                    background:#111827;
                    color:white;
                    padding:12px;
                    border:1px solid #ddd;
                }

                td{
                    padding:12px;
                    border:1px solid #ddd;
                    text-align:center;
                }

                .table-title{
                    font-size:22px;
                    font-weight:600;
                    margin-bottom:20px;
                }

            </style>
        </head>

        <body>

            ${printContent}

        </body>
        </html>
    `;

    window.print();

    location.reload();
}
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
</body>
</html>