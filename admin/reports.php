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
   MONTH FILTER
========================= */

$month = $_GET['month'] ?? date("Y-m");

/* =========================
   OVERALL SUMMARY
========================= */

$summary = $conn->query("
    SELECT status, COUNT(*) as total
    FROM attendance
    WHERE date LIKE '$month%'
    GROUP BY status
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

$total = $present + $half + $absent;

$percent = $total
    ? round((($present + ($half * 0.5)) / $total) * 100, 2)
    : 0;

$score = $total
    ? round(($present / $total) * 100, 2)
    : 0;

/* =========================
   EMPLOYEE REPORT
========================= */

/* =========================
   EMPLOYEE REPORT
========================= */

$employees = $conn->query("
    SELECT
        users.id,
        users.name,
        users.employee_id,

        SUM(
            CASE
                WHEN attendance.status = 'Present'
                THEN 1
                ELSE 0
            END
        ) as present_count,

        SUM(
            CASE
                WHEN attendance.status = 'Absent'
                THEN 1
                ELSE 0
            END
        ) as absent_count,

        SUM(
            CASE
                WHEN attendance.status = 'Half Day'
                THEN 1
                ELSE 0
            END
        ) as halfday_count,

        SUM(
            CASE
                WHEN attendance.status = 'Late'
                THEN 1
                ELSE 0
            END
        ) as late_count,

        GROUP_CONCAT(
            DISTINCT CASE
                WHEN attendance.status = 'Absent'
                THEN DATE_FORMAT(attendance.date,'%d %b')
            END
            SEPARATOR ', '
        ) as absent_dates,

        GROUP_CONCAT(
            DISTINCT CASE
                WHEN attendance.status = 'Half Day'
                THEN DATE_FORMAT(attendance.date,'%d %b')
            END
            SEPARATOR ', '
        ) as halfday_dates,

        GROUP_CONCAT(
            DISTINCT CASE
                WHEN attendance.status = 'Late'
                THEN DATE_FORMAT(attendance.date,'%d %b')
            END
            SEPARATOR ', '
        ) as late_dates

    FROM users

    LEFT JOIN attendance
    ON users.id = attendance.user_id
    AND attendance.date LIKE '$month%'

    WHERE users.role = 'employee'

    GROUP BY users.id

    ORDER BY users.name ASC
");





$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = "attendance.date LIKE '$month%' 
AND users.role='employee'";

if (!empty($search)) {

    $where .= " AND users.employee_id LIKE '%$search%'";
}

if (!empty($status_filter)) {

    $where .= " AND attendance.status='$status_filter'";
}

$history = $conn->query("
    SELECT
        users.name,
        users.employee_id,
        attendance.date,

        (
            SELECT COUNT(*)
            FROM attendance a2
            WHERE a2.user_id = users.id
            AND a2.status = 'Present'
            AND a2.date LIKE '$month%'
        ) as present_count,

        (
            SELECT COUNT(*)
            FROM attendance a2
            WHERE a2.user_id = users.id
            AND a2.status = 'Absent'
            AND a2.date LIKE '$month%'
        ) as absent_count,

        (
            SELECT COUNT(*)
            FROM attendance a2
            WHERE a2.user_id = users.id
            AND a2.status = 'Half Day'
            AND a2.date LIKE '$month%'
        ) as halfday_count,

        (
            (
                SELECT COUNT(*)
                FROM attendance a2
                WHERE a2.user_id = users.id
                AND a2.status = 'Present'
                AND a2.date LIKE '$month%'
            )
            +
            (
                SELECT COUNT(*)
                FROM attendance a2
                WHERE a2.user_id = users.id
                AND a2.status = 'Absent'
                AND a2.date LIKE '$month%'
            )
            +
            (
                (
                    SELECT COUNT(*)
                    FROM attendance a2
                    WHERE a2.user_id = users.id
                    AND a2.status = 'Half Day'
                    AND a2.date LIKE '$month%'
                ) * 0.5
            )
        ) as total_days

    FROM attendance

    INNER JOIN users
    ON users.id = attendance.user_id

    WHERE attendance.date LIKE '$month%'
    AND users.role = 'employee'

    ORDER BY attendance.date DESC
");
?>

<!DOCTYPE html>
<html>

<head>

    <title>Reports Dashboard</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <style>

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #eef2f7;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* =========================
           SIDEBAR
        ========================= */

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

        /* =========================
           MAIN
        ========================= */

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

        /* =========================
           FILTER
        ========================= */

        .filter-box {
            background: white;
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        input {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-family: inherit;
        }

        button {
            padding: 10px 15px;
            border: none;
            background: #667eea;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
        }

        button:hover {
            opacity: 0.9;
        }

        /* =========================
           CARDS
        ========================= */

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            text-align: center;
        }

        .card h3 {
            margin: 0;
            font-size: 14px;
            color: #777;
        }

        .card p {
            font-size: 24px;
            font-weight: 600;
            margin-top: 10px;
        }

        .green {
            color: green;
        }

        .orange {
            color: orange;
        }

        .red {
            color: red;
        }

        .blue {
            color: #3b82f6;
        }

        /* =========================
           TABLE
        ========================= */

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }

        table th {
            background: #111827;
            color: white;
            padding: 14px;
            font-size: 14px;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: center;
            font-size: 14px;
        }

        table tr:hover {
            background: #f9fafb;
        }

        .table-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 12px;
            margin-top: 25px;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media(max-width: 900px) {

            .cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }
        }

        @media(max-width: 600px) {

            .cards {
                grid-template-columns: 1fr;
            }

            table {
                display: block;
                overflow-x: auto;
            }
        }

    </style>

</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->

    <div class="sidebar">

        <h2>Admin Panel</h2>

        <a href="dashboard.php">🏠 Dashboard</a>

        <a href="create_employee.php">👤 Create Employee</a>

        <a href="leave_requests.php">📩 Manage Leaves</a>

        <a href="../api/checkin.php">🟢 Check In</a>

        <a href="../api/checkout.php">🔴 Check Out</a>

        <a href="reports.php">📊 Reports</a>

        <a href="../auth/logout.php" class="logout">🚪 Logout</a>

    </div>

    <!-- MAIN -->

    <div class="main">

        <div class="header">
            📊 Attendance Reports Dashboard
        </div>

        <!-- FILTER -->

     <div class="filter-box">

    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">

        <!-- Month -->

        <input
            type="month"
            name="month"
            value="<?= $month ?>"
        >

        <!-- Search Employee ID -->

        <input
            type="text"
            name="search"
            placeholder="Search Employee ID"
            value="<?= $search ?>"
        >

        <!-- Status Filter -->

        <select name="status">

            <option value="">
                All Status
            </option>

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

        <!-- Buttons -->

        <button type="submit">
            Filter
        </button>

        <button
            type="button"
            onclick="window.print()"
        >
            Print
        </button>

    </form>

</div>

        <!-- SUMMARY CARDS

        <div class="cards">

            <div class="card">
                <h3>Present</h3>
                <p class="green"><?= $present ?></p>
            </div>

            <div class="card">
                <h3>Half Day</h3>
                <p class="orange"><?= $half ?></p>
            </div>

            <div class="card">
                <h3>Absent</h3>
                <p class="red"><?= $absent ?></p>
            </div>

            <div class="card">
                <h3>Late Count</h3>
                <p class="red"><?= $late ?></p>
            </div>

            <div class="card">
                <h3>Attendance %</h3>
                <p class="blue"><?= $percent ?>%</p>
            </div>

            <div class="card">
                <h3>Efficiency Score</h3>
                <p class="green"><?= $score ?>%</p>
            </div>

        </div> -->

      <!-- EMPLOYEE REPORT -->

<div class="table-title">
    Employee Attendance Report
</div>
<table>

<tr>

    <th>Name</th>

    <th>Employee ID</th>

    <th>Attendance Date</th>

    <th>Present</th>

    <th>Absent</th>

    <th>Half Days</th>

    <th>Total Days</th>

</tr>

<?php while($row = $history->fetch_assoc()): ?>

<tr>

    <td><?= $row['name'] ?></td>

    <td><?= $row['employee_id'] ?></td>

    <td>
        <?= date("d M Y", strtotime($row['date'])) ?>
    </td>

    <td class="green">
        <?= $row['present_count'] ?>
    </td>

    <td class="red">
        <?= $row['absent_count'] ?>
    </td>

    <td class="orange">
        <?= $row['halfday_count'] ?>
    </td>

    <td class="blue">
        <?= $row['total_days'] ?>
    </td>

</tr>

<?php endwhile; ?>

</table>
    </div>
</div>

</body>
</html>