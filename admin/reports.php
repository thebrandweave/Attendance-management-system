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
   FILTERS & CUSTOM DATE RANGE (21st to 20th)
========================= */

if (isset($_GET['month'])) {
    $month = $_GET['month'];
} else {
    $todayDay = date('d');

    if ($todayDay >= 21) {
        $month = date('Y-m', strtotime('+1 month'));
    } else {
        $month = date('Y-m');
    }
}
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$branchId = $_SESSION['user']['branch_id'] ?? $_SESSION['branch_id'] ?? 0;
$branch = $_SESSION['user']['branch'] ?? $_SESSION['branch'] ?? '';

$bStmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ? OR LOWER(branch_name) = LOWER(?)");
$bStmt->bind_param("is", $branchId, $branch);
$bStmt->execute();
$bRes = $bStmt->get_result()->fetch_assoc();
$branchName = $bRes ? $bRes['branch_name'] : ucfirst($branch);
require_once "../config/branch_helper.php";
$attTable = getBranchTableNameOnly($conn, $branchName);
$isMudipuBranch = (strtolower($branchName) === "mudipu" || strtolower($branch) === "mudipu");

// Calculate custom start and end dates for the 21st to 20th cycle
$startDate = date("Y-m-21", strtotime("-1 month", strtotime($month . "-01")));
$endDate   = date("Y-m-20", strtotime($month . "-01"));

/* =========================
   FETCH COMPANY LEAVES IN CUSTOM WINDOW
========================= */
$companyLeaves = [];
$companyLeaveTitles = [];

// Fetch company leaves that fall explicitly within our custom 21st to 20th range
$leaveQuery = $conn->prepare("
    SELECT leave_date, title 
    FROM company_leaves 
    WHERE (leave_date BETWEEN ? AND ?) AND (branch_id=? OR branch=?)
");
$leaveQuery->bind_param("ssis", $startDate, $endDate, $branchId, $branch);
$leaveQuery->execute();
$result = $leaveQuery->get_result();

while ($lRow = $result->fetch_assoc()) {
    $companyLeaves[] = $lRow['leave_date'];
    $companyLeaveTitles[$lRow['leave_date']] = $lRow['title']; 
}

/* =========================
   TOTAL DAYS IN THE CUSTOM WORK CYCLE
========================= */

$totalDaysInMonth = 0;
$dateLoopRunner = strtotime($startDate);

while ($dateLoopRunner <= strtotime($endDate)) {
    $currentDateCheck = date("Y-m-d", $dateLoopRunner);

    // SKIP SUNDAY
    if (!$isMudipuBranch && date("N", strtotime($currentDateCheck)) == 7) {
        $dateLoopRunner = strtotime("+1 day", $dateLoopRunner);
        continue;
    }

    // SKIP COMPANY LEAVES FROM TOTAL WORKING DAYS
    if (in_array($currentDateCheck, $companyLeaves)) {
        $dateLoopRunner = strtotime("+1 day", $dateLoopRunner);
        continue;
    }

    $totalDaysInMonth++;
    $dateLoopRunner = strtotime("+1 day", $dateLoopRunner);
}

/* =========================
   DASHBOARD SUMMARY
========================= */

$summary = $conn->prepare("
    SELECT
        status,
        COUNT(*) as total
    FROM `$attTable`
    WHERE date BETWEEN ? AND ?
    GROUP BY status
");
$summary->bind_param("ss", $startDate, $endDate);
$summary->execute();
$summaryResult = $summary->get_result();

$present = 0;
$half = 0;
$halfCredit = 0; // half-days that actually earn 0.5 attendance credit (excludes Half Day Absent)
$absent = 0;
$late = 0;
$cl_count = 0;
$pl_count = 0;

while ($row = $summaryResult->fetch_assoc()) {
    if ($row['status'] == "Present") {
        $present = $row['total'];
    } elseif ($row['status'] == "Half Day") {
        $half += $row['total'];
        $halfCredit += $row['total'];
    } elseif ($row['status'] == "Half Day PL") {
        $half += $row['total'];
        $halfCredit += $row['total'];
        $pl_count += $row['total'] * 0.5;
    } elseif ($row['status'] == "Half Day Absent") {
        $half += $row['total'];
        // No attendance credit for an uncovered half-day-absent.
    } elseif ($row['status'] == "Absent") {
        $absent = $row['total'];
    } elseif ($row['status'] == "Late") {
        $late = $row['total'];
    } elseif ($row['status'] == "CL") {
        $cl_count = $row['total'];
    } elseif ($row['status'] == "PL") {
        $pl_count += $row['total'];
    } elseif ($row['status'] == "Overtime") {
        $present += $row['total'];
    }
}

$totalAttendance = $present + $absent + $half;
$attendancePercent = $totalAttendance ? round((($present + ($halfCredit * 0.5)) / $totalAttendance) * 100, 2) : 0;

/*
=========================================
AUTO CHECKOUT FOR MISSED EMPLOYEES AT 9:00 PM
=========================================
*/
$currentDate = date("Y-m-d");
$currentTimeOnly = date("H:i:s");

if ($currentTimeOnly >= "21:00:00") {
    $pendingCheckout = $conn->query("
        SELECT *
        FROM `$attTable`
        WHERE date='$currentDate'
        AND check_in IS NOT NULL
        AND (check_out IS NULL OR check_out='')
    ");

    while ($att = $pendingCheckout->fetch_assoc()) {
        $attendanceId = $att['id'];
        $checkInTime = strtotime($att['check_in']);
        $autoCheckoutDateTime = $currentDate . " 17:30:00";
        $autoCheckoutTime = strtotime($autoCheckoutDateTime);

        $lunchSeconds = 0;
        if (!empty($att['lunch_out']) && !empty($att['lunch_in'])) {
            $lunchSeconds = strtotime($att['lunch_in']) - strtotime($att['lunch_out']);
        }

        $totalSeconds = $autoCheckoutTime - $checkInTime;
        $workingHours = ($totalSeconds - $lunchSeconds) / 3600;
        $status = "Present";

        $updateAuto = $conn->prepare("UPDATE `$attTable` SET check_out=?, total_hours=?, status=? WHERE id=?");
        $updateAuto->bind_param("sdsi", $autoCheckoutDateTime, $workingHours, $status, $attendanceId);
        $updateAuto->execute();
    }
}
/*
=========================================
FIX MISSED CHECKOUTS
If employee checked in but never checked out
for a previous day, auto-close at 5:30 PM
=========================================
*/

$fixCheckout = $conn->query("
    SELECT *
    FROM `$attTable`
    WHERE check_in IS NOT NULL
    AND (check_out IS NULL OR check_out = '')
    AND date < CURDATE()
");

while ($att = $fixCheckout->fetch_assoc()) {

    $autoCheckoutTime =
        $att['date'] . " 17:30:00";

    $checkIn = strtotime($att['check_in']);
    $checkOut = strtotime($autoCheckoutTime);

    $totalSeconds = $checkOut - $checkIn;

    $lunchSeconds = 0;

    if (
        !empty($att['lunch_out']) &&
        !empty($att['lunch_in'])
    ) {

        $lunchSeconds =
            strtotime($att['lunch_in']) -
            strtotime($att['lunch_out']);
    }

    $workingHours =
        max(0, ($totalSeconds - $lunchSeconds) / 3600);

    $status = ($workingHours < 4)
        ? "Half Day"
        : "Present";

    $stmt = $conn->prepare("
        UPDATE `$attTable`
        SET
            check_out = ?,
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
}

/*
=========================================
AUTO INSERT ABSENT OR COMPANY LEAVE (CL) FOR MISSING DAYS
=========================================
*/
$employeesAbsent = $conn->query("
    SELECT id, created_at
    FROM users
    WHERE role='employee' AND (branch_id='$branchId' OR branch='$branch')
");

while ($emp = $employeesAbsent->fetch_assoc()) {
    $empId = $emp['id'];
    $joinDate = date("Y-m-d", strtotime($emp['created_at']));
    $dateLoop = strtotime($startDate);

    while ($dateLoop <= strtotime($endDate)) {
        $loopDate = date("Y-m-d", $dateLoop);

        if ($loopDate >= $currentDate) {
            break;
        }
        if ($loopDate < $joinDate) {
            $dateLoop = strtotime("+1 day", $dateLoop);
            continue;
        }
        if (!$isMudipuBranch && date("N", strtotime($loopDate)) == 7) {
            $dateLoop = strtotime("+1 day", $dateLoop);
            continue;
        }

        $isCompanyLeave = in_array($loopDate, $companyLeaves);

        $check = $conn->query("SELECT id, status FROM `$attTable` WHERE user_id='$empId' AND date='$loopDate'");

        if ($isCompanyLeave) {
            if ($check->num_rows == 0) {
                $conn->query("INSERT INTO `$attTable` (user_id, date, status) VALUES ('$empId', '$loopDate', 'CL')");
            } else {
                $existing = $check->fetch_assoc();
                if (in_array($existing['status'], ['Absent', 'PL', 'Half Day Absent', 'Half Day PL', 'Half Day'])) {
                    $conn->query("UPDATE `$attTable` SET status='CL' WHERE id=" . $existing['id']);
                }
            }
        } else {
            if ($check->num_rows == 0) {
                $conn->query("INSERT INTO `$attTable` (user_id, date, status) VALUES ('$empId', '$loopDate', 'Absent')");
            }
        }

        $dateLoop = strtotime("+1 day", $dateLoop);
    }

    if (!$isMudipuBranch) {
        $cleanupSunday = $conn->prepare("
            DELETE FROM `$attTable`
            WHERE user_id = ?
            AND date BETWEEN ? AND ?
            AND DAYOFWEEK(date) = 1
            AND status IN ('Absent', 'PL', 'Half Day', 'Half Day PL', 'Half Day Absent')
        ");
        $cleanupSunday->bind_param("iss", $empId, $startDate, $endDate);
        $cleanupSunday->execute();
    }

    /*
    =========================================
    REVERT STALE 'CL' ROWS
    -----------------------------------------
    A row only ever gets SET to 'CL' above when its date is found in
    $companyLeaves (i.e. it exists in the company_leaves table for this
    cycle). But nothing previously reverted that decision if the company
    leave entry was later edited or deleted -- once a row became 'CL' it
    stayed 'CL' forever, even on a cycle where company_leaves is now
    completely empty. That made old/incorrect company-leave entries show
    up permanently in the "Company Leave" column.
    Fix: every time the report runs, find every 'CL' row for this
    employee in this cycle whose date is NOT (no longer) in
    $companyLeaves, and revert it to 'Absent' so the normal PL quota pass
    below can re-evaluate it like any other day (it will become PL or
    Absent depending on quota remaining, exactly as if no CL had ever
    touched it).
    =========================================
    */
    $existingCLRows = $conn->prepare("
        SELECT id, date
        FROM `$attTable`
        WHERE user_id = ?
        AND date BETWEEN ? AND ?
        AND status = 'CL'
    ");
    $existingCLRows->bind_param("iss", $empId, $startDate, $endDate);
    $existingCLRows->execute();
    $existingCLResult = $existingCLRows->get_result();

    while ($clRow = $existingCLResult->fetch_assoc()) {
        $clRowDate = date("Y-m-d", strtotime($clRow['date']));
        if (!in_array($clRowDate, $companyLeaves)) {
            // No longer a recognized company leave date -> revert to
            // Absent so the quota pass below treats it like any other
            // unaccounted-for day.
            $conn->query("UPDATE `$attTable` SET status='Absent' WHERE id=" . $clRow['id']);
        }
    }

    $quotaRows = $conn->prepare("
        SELECT id, date, status
        FROM `$attTable`
        WHERE user_id = ?
        AND date BETWEEN ? AND ?
        AND status IN ('Absent', 'PL', 'Half Day', 'Half Day PL', 'Half Day Absent')
        " . (!$isMudipuBranch ? "AND DAYOFWEEK(date) != 1" : "") . "
        ORDER BY date ASC
    ");
    $quotaRows->bind_param("iss", $empId, $startDate, $endDate);
    $quotaRows->execute();
    $quotaResult = $quotaRows->get_result();

    $poolRemaining = 2.0; // shared PL units available this cycle

    while ($qRow = $quotaResult->fetch_assoc()) {
        $isHalfDay = ($qRow['status'] == 'Half Day' || $qRow['status'] == 'Half Day PL' || $qRow['status'] == 'Half Day Absent');
        $unitCost = $isHalfDay ? 0.5 : 1.0;

        if ($poolRemaining >= $unitCost) {
            $newStatus = $isHalfDay ? 'Half Day PL' : 'PL';
            $poolRemaining -= $unitCost;
        } else {
            $newStatus = $isHalfDay ? 'Half Day Absent' : 'Absent';
        }

        if ($qRow['status'] != $newStatus) {
            $conn->query("UPDATE `$attTable` SET status='$newStatus' WHERE id=" . $qRow['id']);
        }
    }
}

/* =========================
   EMPLOYEE SUMMARY REPORT
========================= */
$whereEmployee = "users.role='employee' AND (users.branch_id='$branchId' OR users.branch='$branch')";
if (!empty($search)) {
    $whereEmployee .= " AND (users.employee_id LIKE '%$search%' OR users.name LIKE '%$search%')";
}

$employees = $conn->query("
    SELECT
        users.id,
        users.name,
        users.employee_id,
       SUM(CASE WHEN (
        attendance.status='Present'
        OR attendance.status='Overtime'
        OR attendance.status='Late'
    )
    AND attendance.date BETWEEN '$startDate' AND '$endDate'
    THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN (attendance.status='Absent') AND attendance.date BETWEEN '$startDate' AND '$endDate' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN (attendance.status='Half Day Absent') AND attendance.date BETWEEN '$startDate' AND '$endDate' THEN 1 ELSE 0 END) as halfday_absent_count,
        SUM(CASE WHEN (attendance.status='Half Day' OR attendance.status='Half Day PL' OR attendance.status='Half Day Absent') AND attendance.date BETWEEN '$startDate' AND '$endDate' THEN 1 ELSE 0 END) as halfday_count,
        SUM(CASE WHEN (attendance.status='Half Day' OR attendance.status='Half Day PL') AND attendance.date BETWEEN '$startDate' AND '$endDate' THEN 1 ELSE 0 END) as halfday_credit_count,
        SUM(CASE WHEN attendance.status='CL' AND attendance.date BETWEEN '$startDate' AND '$endDate' THEN 1 ELSE 0 END) as cl_count,
        SUM(CASE
            WHEN attendance.status='PL' AND attendance.date BETWEEN '$startDate' AND '$endDate' THEN 1
            WHEN attendance.status='Half Day PL' AND attendance.date BETWEEN '$startDate' AND '$endDate' THEN 0.5
            ELSE 0
        END) as pl_count
    FROM users
    LEFT JOIN `$attTable` attendance ON users.id = attendance.user_id AND attendance.date BETWEEN '$startDate' AND '$endDate' " . (!$isMudipuBranch ? "AND DAYOFWEEK(attendance.date) != 1" : "") . "
    WHERE $whereEmployee
    GROUP BY users.id
    ORDER BY users.name ASC
");

/* =========================
   HISTORY SECTION
========================= */
$whereHistory = "attendance.date BETWEEN '$startDate' AND '$endDate' AND users.role='employee' AND (users.branch_id='$branchId' OR users.branch='$branch')";
if (!$isMudipuBranch) {
    $whereHistory .= " AND DAYOFWEEK(attendance.date) != 1";
}
if (!empty($search)) {
    $whereHistory .= " AND (users.employee_id LIKE '%$search%' OR users.name LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $whereHistory .= " AND attendance.status='$status_filter'";
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
    FROM `$attTable` attendance
    INNER JOIN users ON users.id = attendance.user_id
    WHERE $whereHistory
    ORDER BY attendance.date DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Reports</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f4f7fb; color: #111827; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: linear-gradient(180deg,#111827,#1f2937); color: white; padding: 25px; position: sticky; top: 0; height: 100vh; }
        .sidebar h2 { text-align: center; margin-bottom: 30px; font-size: 20px; }
        .sidebar a { display: block; color: white; text-decoration: none; padding: 13px 15px; border-radius: 10px; margin-bottom: 10px; transition: 0.3s; font-size: 14px; }
        .sidebar a:hover { background: rgba(255,255,255,0.1); transform: translateX(5px); }
        .logout { background: #ef4444; }
        .main { flex: 1; padding: 25px; }
        .header { background: white; padding: 22px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .header h1 { font-size: 26px; }
        .header p { color: #6b7280; margin-top: 5px; }
        .filter-box { background: white; padding: 20px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .filter-form { display: flex; gap: 12px; flex-wrap: wrap; }
        input, select { padding: 12px; border-radius: 10px; border: 1px solid #d1d5db; font-family: inherit; min-width: 180px; }
        button { border: none; padding: 12px 18px; border-radius: 10px; cursor: pointer; font-family: inherit; font-weight: 500; transition: 0.3s; }
        .btn-primary { background: #4f46e5; color: white; }
        .btn-print { background: #111827; color: white; }
        button:hover { opacity: 0.9; transform: translateY(-2px); }
        .table-wrapper { background: white; border-radius: 18px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); margin-bottom: 30px; }
        .table-title { padding: 20px; font-size: 18px; font-weight: 600; border-bottom: 1px solid #eee; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #111827; color: white; padding: 14px; font-size: 14px; position: sticky; top: 0; }
        td { padding: 14px; border: 1px solid #eee; text-align: center; font-size: 14px; }
        tr:hover { background: #f9fafb; }
        .badge { padding: 6px 10px; border-radius: 30px; color: white; font-size: 12px; font-weight: 600; }
        .badge.present { background: #16a34a; }
        .badge.absent { background: #dc2626; }
        .badge.half { background: #f59e0b; }
        .badge.late { background: #2563eb; }
        .badge.cl { background: #7c3aed; }
        .badge.pl { background: #0e2725; }
        .badge.half-pl { background: #0ea5a8; }
        .badge.half-absent { background: #94644a; }
        .red{
            background: #fff44f;
        }
        @media print {
            .sidebar, .filter-box, button { display: none !important; }
            body { background: white; }
            .main { padding: 0; }
            .table-wrapper, .header { box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="layout">
    <div class="sidebar">
        <h2 style="text-align:center;"><?= htmlspecialchars($branchName) ?> Admin</h2>
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="create_employee.php">👤 Create Employee</a>
        <a href="../api/checkin.php">🟢 Check In- Morning</a>
        <a href="../api/lunch.php">🍽️ Lunch Break</a>
        <a href="../api/checkout.php">🔴 Check Out- Evening</a>
        <a href="leave_requests.php">📩 Manage Leaves</a>
        <a href="add_leave.php">📅 Company Leaves</a>
        <a href="reports.php">📊 Reports</a>
        <a href="../auth/logout.php" class="logout">🚪 Logout</a>
    </div>

    <div class="main">
        <div class="header">
            <h1>Attendance Reports</h1>
            <p>Month Dates: <b><?= date("d M Y", strtotime($startDate)) ?></b> - <b><?= date("d M Y", strtotime($endDate)) ?></b></p>
        </div>
<div class="filter-box">
    <form method="GET" class="filter-form">
        
        <select name="month">
            <?php
            // Generate a list of cycles (e.g., 3 months back, 3 months forward)
            for ($i = -4; $i <= 7; $i++) {
                // Determine baseline tracking target
                $targetTime = strtotime("$i month", strtotime(date("Y-m-01")));
                $valueAttr  = date("Y-m", $targetTime); // e.g., "2026-06"
                
                // Construct labels: For '2026-06', the cycle is May 21 - Jun 20
                $prevMonthLabel = date("M", strtotime("-1 month", $targetTime)); // "May"
                $currMonthLabel = date("M", $targetTime);                        // "Jun"
                $yearLabel      = date("Y", $targetTime);                        // "2026"
                
                $displayLabel = $prevMonthLabel . " - " . $currMonthLabel . " " . $yearLabel;
                
                // Maintain selected state on page reload
                $selected = ($valueAttr == $month) ? 'selected' : '';
                
                echo "<option value='{$valueAttr}' {$selected}>{$displayLabel}</option>";
            }
            ?>
        </select>

        <input 
            type="text" 
            name="search" 
            placeholder="Search Employee Name / ID" 
            value="<?= htmlspecialchars($search) ?>"
        >
                <!-- <input type="text" name="search" placeholder="Search Employee Name / ID" value="<?= $search ?>"> -->
                <select name="status">
                    <option value="">All Status</option>
                    <option value="Present" <?= $status_filter == 'Present' ? 'selected' : '' ?>>Present</option>
                    <option value="Absent" <?= $status_filter == 'Absent' ? 'selected' : '' ?>>Absent</option>
                    <option value="Half Day" <?= $status_filter == 'Half Day' ? 'selected' : '' ?>>Half Day</option>
                    <option value="Half Day PL" <?= $status_filter == 'Half Day PL' ? 'selected' : '' ?>>Half Day (Monthly CL)</option>
                    <option value="Half Day Absent" <?= $status_filter == 'Half Day Absent' ? 'selected' : '' ?>>Half Day (Absent)</option>
                    <option value="Late" <?= $status_filter == 'Late' ? 'selected' : '' ?>>Late</option>
                    <option value="CL" <?= $status_filter == 'CL' ? 'selected' : '' ?>>Company Leaves</option>
                    <option value="PL" <?= $status_filter == 'PL' ? 'selected' : '' ?>>Monthly CL</option>
                    <option value="Overtime" <?= $status_filter == 'Overtime' ? 'selected' : '' ?>>Overtime</option>
                </select>
                <button type="submit" class="btn-primary">Search</button>
                <button type="button" class="btn-print" onclick="printSummary()">Print Report</button>
            </form>
        </div>

        <div class="table-wrapper" id="employeeSummary">
            <div class="table-title">👨‍💼 Employee Monthly Report (21st - 20th)</div>
            <table>
                <tr>
                    <th>Name</th>
                    <th>Employee ID</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Half Day</th>
                    <!-- <th>Late</th> -->
                    <th>Company Leave</th>
                    <th>Monthly CL</th>
                    <th>Total Attendance</th>
                </tr>
                <?php while($emp = $employees->fetch_assoc()): 
                    // A 'Half Day Absent' row is still a half-day worked
                    // (the employee was physically present for half the
                    // day) — it's just that the leave half of that day
                    // fell outside the PL quota. So its 0.5 worked-half
                    // still counts toward Total Attendance, the same way
                    // halfday_credit_count's 0.5 does for Half Day / Half
                    // Day PL. The uncovered leave-half is what gets shown
                    // as 0.5 in the Absent column below.
                    $finalAttendance = $emp['present_count']
                        // + ($emp['halfday_credit_count'] * 0.5)
                        + ($emp['halfday_absent_count'] * 0.5)
                        + $emp['pl_count']
                        ;
                    // Absent display = pure Absent rows + 0.5 credit for each
                    // Half Day Absent row (the uncovered leave-half of that
                    // day, once the PL quota ran out).
                    $absentDisplay = $emp['absent_count'] + ($emp['halfday_absent_count'] * 0.5);
                    // Half Day display = only the "covered" half days
                    // (Half Day worked normally + Half Day PL). The
                    // quota-exhausted Half Day Absent rows have been moved
                    // into the Absent column above instead of living here.
                    $halfDayDisplay = $emp['halfday_count'] - $emp['halfday_absent_count'];
                ?>
                <tr>
                    <td><?= $emp['name'] ?></td>
                    <td><?= $emp['employee_id'] ?></td>
                    <td class="green"><?= $emp['present_count'] ?? 0 ?></td>
                    <td class="red"><?= $absentDisplay ?></td>
                    <td class="orange"><?= $halfDayDisplay ?></td>
           
                    <td style="color: #7c3aed; font-weight: 600;"><?= $emp['cl_count'] ?? 0 ?></td>
                    <td style="color: #0d9488; font-weight: 600;"><?= $emp['pl_count'] ?? 0 ?></td>
                    <td style="font-weight:600;"><?= $finalAttendance ?> / <?= $totalDaysInMonth ?> Days</td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div class="table-wrapper">
            <div class="table-title">🕒 Recent Attendance History</div>
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
                <?php while($row = $history->fetch_assoc()): 
                    $currentRowDate = date("Y-m-d", strtotime($row['date']));
                    $lightColors = ["#fef2f2", "#eff6ff", "#f0fdf4", "#fff7ed", "#faf5ff", "#fdf2f8", "#ecfeff", "#f9fafb"];
                    $colorIndex = abs(crc32($currentRowDate)) % count($lightColors);
                    $rowBgColor = $lightColors[$colorIndex];
                ?>
                <tr style="background: <?= $rowBgColor ?>;">
                    <td><?= date("d M Y", strtotime($row['date'])) ?></td>
                    <td><?= $row['name'] ?></td>
                    <td><?= $row['employee_id'] ?></td>
                    <td>
                        <?php if($row['status'] == 'Present'): ?>
                            <span class="badge present">Present</span>
                        <?php elseif($row['status'] == 'Absent'): ?>
                            <span class="badge absent">Absent</span>
                        <?php elseif($row['status'] == 'Half Day'): ?>
                            <span class="badge half">Half Day</span>
                        <?php elseif($row['status'] == 'Half Day PL'): ?>
                            <span class="badge half-pl">Half Day (CL)</span>
                        <?php elseif($row['status'] == 'Half Day Absent'): ?>
                            <span class="badge half-absent">Half Day (Absent)</span>
                        <?php elseif($row['status'] == 'CL'): ?>
                            <span class="badge cl"><?= htmlspecialchars($companyLeaveTitles[$currentRowDate] ?? 'Occasional Leave') ?></span>
                        <?php elseif($row['status'] == 'PL'): ?>
                            <span class="badge pl">Monthly CL</span>
                        <?php elseif($row['status'] == 'Overtime'): ?>
                            <span class="badge present">Present</span>
                        <?php else: ?>
                            <span class="badge late">Late</span>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($row['check_in']) ? date("h:i A", strtotime($row['check_in'])) : '-' ?></td>
                    <td><?= !empty($row['lunch_out']) ? date("h:i A", strtotime($row['lunch_out'])) : '-' ?></td>
                    <td><?= !empty($row['lunch_in']) ? date("h:i A", strtotime($row['lunch_in'])) : '-' ?></td>
                    <td><?= !empty($row['check_out']) ? date("h:i A", strtotime($row['check_out'])) : '-' ?></td>
                    <td style="font-weight:600;color:#16a34a;">
                        <?php
                        $workingHours = "-";
                        if (!empty($row['check_in']) && !empty($row['check_out'])) {
                            $checkIn  = strtotime($row['check_in']);
                            $checkOut = strtotime($row['check_out']);
                            $totalSeconds = $checkOut - $checkIn;

                            $lunchSeconds = 0;
                            if (!empty($row['lunch_out']) && !empty($row['lunch_in'])) {
                                $lunchSeconds = strtotime($row['lunch_in']) - strtotime($row['lunch_out']);
                            }

                            $finalSeconds = $totalSeconds - $lunchSeconds;
                            if ($finalSeconds < 0) $finalSeconds = 0;

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
    const printContent = document.getElementById("employeeSummary").innerHTML;
    document.body.innerHTML = `
        <html>
        <head>
            <title>Employee Monthly Summary</title>
            <style>
                body{ font-family:Poppins,sans-serif; padding:20px; }
                table{ width:100%; border-collapse:collapse; }
                th{ background:#111827; color:white; padding:12px; border:1px solid #ddd; }
                td{ padding:12px; border:1px solid #ddd; text-align:center; }
                .table-title{ font-size:22px; font-weight:600; margin-bottom:20px; }
            </style>
        </head>
        <body>${printContent}</body>
        </html>
    `;
    window.print();
    location.reload();
}
</script>
</body>
</html>