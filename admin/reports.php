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

// if (isset($_GET['month'])) {
//     $month = $_GET['month'];
// } else {
//     $todayDay = date('d');

//     if ($todayDay >= 21) {
//         $month = date('Y-m', strtotime('+1 month'));
//     } else {
//         $month = date('Y-m');
//     }
// }
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$branchId = $_SESSION['user']['branch_id'] ?? $_SESSION['branch_id'] ?? 0;
$branch = $_SESSION['user']['branch'] ?? $_SESSION['branch'] ?? '';

$bStmt = $conn->prepare("SELECT branch_name, standard_check_in, standard_check_out FROM branches WHERE id = ? OR LOWER(branch_name) = LOWER(?)");
$bStmt->bind_param("is", $branchId, $branch);
$bStmt->execute();
$bRes = $bStmt->get_result()->fetch_assoc();
$branchName = $bRes ? $bRes['branch_name'] : ucfirst($branch);
require_once "../config/branch_helper.php";
ensureEmployeeSettingsColumns($conn);
$attTable = getBranchTableNameOnly($conn, $branchName);
$isMudipuBranch = (strtolower($branchName) === "mudipu" || strtolower($branch) === "mudipu");

$isThirthahalliBranch = (
    strtolower(trim($branchName)) === "thirthahalli" ||
    strtolower(trim($branch)) === "thirthahalli"
);

$currentAdminUser = $_SESSION['user'] ?? [];
$currentAdminEmpId = strtoupper(trim($currentAdminUser['employee_id'] ?? ''));
$adminCanEditSetting = isset($currentAdminUser['can_edit_reports']) ? (int)$currentAdminUser['can_edit_reports'] : null;

// Only authorized Thirthahalli branch admin (e.g. ADMIN003) has edit capability in reports
$canEditReports = (
    $isThirthahalliBranch &&
    ($adminCanEditSetting === 1 || ($adminCanEditSetting === null && $currentAdminEmpId === 'ADMIN003'))
);

$sundayIsWorking = (
    $isMudipuBranch ||
    $isThirthahalliBranch
);


/*
=========================================
BRANCH OFFICE TIMINGS
=========================================
*/

$officeStartTime = !empty($bRes['standard_check_in']) ? $bRes['standard_check_in'] : ($isThirthahalliBranch ? "10:00:00" : "09:30:00");
$officeEndTime   = !empty($bRes['standard_check_out']) ? $bRes['standard_check_out'] : "20:00:00";


/*
=========================================
MONTH / ATTENDANCE CYCLE
Thirthahalli : 1st -> Last day of month
Other Branches: 21st -> 20th
=========================================
*/

if (isset($_GET['month']) && !empty($_GET['month'])) {

    $month = $_GET['month'];

} else {

    if ($isThirthahalliBranch) {

        // Thirthahalli follows normal calendar month
        $month = date('Y-m');

    } else {

        // Other branches follow 21st -> 20th cycle
        $todayDay = (int) date('d');

        if ($todayDay >= 21) {
            $month = date('Y-m', strtotime('+1 month'));
        } else {
            $month = date('Y-m');
        }
    }
}


/*
=========================================
START & END DATE
=========================================
*/

if ($isThirthahalliBranch) {

    // Example:
    // September 2026 = 01 Sep 2026 -> 30 Sep 2026

    $startDate = date(
        "Y-m-01",
        strtotime($month . "-01")
    );

    $endDate = date(
        "Y-m-t",
        strtotime($month . "-01")
    );

} else {

    // Existing 21st -> 20th cycle

    $startDate = date(
        "Y-m-21",
        strtotime("-1 month", strtotime($month . "-01"))
    );

    $endDate = date(
        "Y-m-20",
        strtotime($month . "-01")
    );
}

// Full month's days count of current residing month (e.g. 30 for Sep, 31 for Oct)
$currentMonthDaysCount = (int) date("t", strtotime($month . "-01"));

// Determine if today is on or after one day before month/cycle end (or if month has ended)
$todayDate = date('Y-m-d');
$oneDayBeforeCycleEnd = date('Y-m-d', strtotime('-1 day', strtotime($endDate)));
$calendarMonthEnd = date('Y-m-t', strtotime($month . '-01'));
$oneDayBeforeCalendarEnd = date('Y-m-d', strtotime('-1 day', strtotime($calendarMonthEnd)));

$isOneDayBeforeMonthEnd = ($todayDate >= $oneDayBeforeCycleEnd || $todayDate >= $oneDayBeforeCalendarEnd);

/* ========================================================
   HANDLE POST ACTIONS (EDIT/ADD/DELETE ATTENDANCE, UPDATE SETTINGS)
======================================================== */
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // Preserve filter parameters on redirect
    $redirectParams = ['month' => $month];
    if (!empty($search)) $redirectParams['search'] = $search;
    if (!empty($status_filter)) $redirectParams['status'] = $status_filter;
    $redirectUrl = "reports.php?" . http_build_query($redirectParams);

    // Permission check: Editing is only allowed for Thirthahalli branch
    if (!$canEditReports) {
        if ($isAjax) {
            echo json_encode(['status' => 'error', 'message' => 'Editing attendance is not permitted for your account.']);
            exit();
        }
        $_SESSION['flash_msg'] = ['type' => 'error', 'text' => 'Editing attendance is not permitted for your account.'];
        header("Location: " . $redirectUrl);
        exit();
    }

    if ($action === 'update_attendance') {
        $attId = (int)($_POST['attendance_id'] ?? 0);
        $statusVal = trim($_POST['status'] ?? 'Present');
        $checkInVal = !empty($_POST['check_in']) ? date("Y-m-d H:i:s", strtotime($_POST['check_in'])) : null;
        $lunchOutVal = !empty($_POST['lunch_out']) ? date("Y-m-d H:i:s", strtotime($_POST['lunch_out'])) : null;
        $lunchInVal = !empty($_POST['lunch_in']) ? date("Y-m-d H:i:s", strtotime($_POST['lunch_in'])) : null;
        $checkOutVal = !empty($_POST['check_out']) ? date("Y-m-d H:i:s", strtotime($_POST['check_out'])) : null;

        if (isset($_POST['total_hours']) && $_POST['total_hours'] !== '') {
            $totalHours = max(0, min(24, (float)$_POST['total_hours']));
        } else {
            $totalHours = 0.0;
            if ($checkInVal && $checkOutVal) {
                $cIn = strtotime($checkInVal);
                $cOut = strtotime($checkOutVal);
                $totSec = $cOut - $cIn;
                $lunchSec = 0;
                if ($lunchOutVal && $lunchInVal) {
                    $lunchSec = strtotime($lunchInVal) - strtotime($lunchOutVal);
                }
                $workSec = max(0, $totSec - $lunchSec);
                $totalHours = round($workSec / 3600, 2);
            }
        }

        // Verify authorized record belonging to this branch
        $verifyStmt = $conn->prepare("
            SELECT a.id, u.name 
            FROM `$attTable` a
            INNER JOIN users u ON a.user_id = u.id
            WHERE a.id = ? AND u.role = 'employee' AND (u.branch_id = ? OR LOWER(u.branch) = LOWER(?))
        ");
        $verifyStmt->bind_param("iis", $attId, $branchId, $branch);
        $verifyStmt->execute();
        $vRes = $verifyStmt->get_result();

        if ($vRes->num_rows === 0) {
            if ($isAjax) {
                echo json_encode(['status' => 'error', 'message' => 'Attendance record not found or unauthorized access.']);
                exit();
            }
            $_SESSION['flash_msg'] = ['type' => 'error', 'text' => 'Unauthorized or attendance record not found.'];
            header("Location: " . $redirectUrl);
            exit();
        }

        $vData = $vRes->fetch_assoc();
        $upStmt = $conn->prepare("
            UPDATE `$attTable`
            SET status = ?, check_in = ?, lunch_out = ?, lunch_in = ?, check_out = ?, total_hours = ?
            WHERE id = ?
        ");
        $upStmt->bind_param("sssssdi", $statusVal, $checkInVal, $lunchOutVal, $lunchInVal, $checkOutVal, $totalHours, $attId);
        $success = $upStmt->execute();
        $upStmt->close();

        if ($isAjax) {
            echo json_encode([
                'status' => $success ? 'success' : 'error',
                'message' => $success ? "Attendance for {$vData['name']} updated successfully!" : "Failed to update attendance."
            ]);
            exit();
        }

        $_SESSION['flash_msg'] = [
            'type' => $success ? 'success' : 'error',
            'text' => $success ? "Attendance for {$vData['name']} updated successfully!" : "Failed to update attendance."
        ];
        header("Location: " . $redirectUrl);
        exit();

    } elseif ($action === 'add_attendance') {
        $empId = (int)($_POST['user_id'] ?? 0);
        $attDate = !empty($_POST['date']) ? date("Y-m-d", strtotime($_POST['date'])) : date("Y-m-d");
        $statusVal = trim($_POST['status'] ?? 'Present');
        $checkInVal = !empty($_POST['check_in']) ? date("Y-m-d H:i:s", strtotime($_POST['check_in'])) : null;
        $lunchOutVal = !empty($_POST['lunch_out']) ? date("Y-m-d H:i:s", strtotime($_POST['lunch_out'])) : null;
        $lunchInVal = !empty($_POST['lunch_in']) ? date("Y-m-d H:i:s", strtotime($_POST['lunch_in'])) : null;
        $checkOutVal = !empty($_POST['check_out']) ? date("Y-m-d H:i:s", strtotime($_POST['check_out'])) : null;

        if (isset($_POST['total_hours']) && $_POST['total_hours'] !== '') {
            $totalHours = max(0, min(24, (float)$_POST['total_hours']));
        } else {
            $totalHours = 0.0;
            if ($checkInVal && $checkOutVal) {
                $cIn = strtotime($checkInVal);
                $cOut = strtotime($checkOutVal);
                $totSec = $cOut - $cIn;
                $lunchSec = 0;
                if ($lunchOutVal && $lunchInVal) {
                    $lunchSec = strtotime($lunchInVal) - strtotime($lunchOutVal);
                }
                $workSec = max(0, $totSec - $lunchSec);
                $totalHours = round($workSec / 3600, 2);
            }
        }

        // Verify employee belongs to admin's branch
        $verifyEmp = $conn->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'employee' AND (branch_id = ? OR LOWER(branch) = LOWER(?))");
        $verifyEmp->bind_param("iis", $empId, $branchId, $branch);
        $verifyEmp->execute();
        $eRes = $verifyEmp->get_result()->fetch_assoc();

        if (!$eRes) {
            if ($isAjax) {
                echo json_encode(['status' => 'error', 'message' => 'Employee not found in your branch.']);
                exit();
            }
            $_SESSION['flash_msg'] = ['type' => 'error', 'text' => 'Employee not found in your branch.'];
            header("Location: " . $redirectUrl);
            exit();
        }

        // Check if attendance record already exists for this date
        $checkExist = $conn->prepare("SELECT id FROM `$attTable` WHERE user_id = ? AND date = ?");
        $checkExist->bind_param("is", $empId, $attDate);
        $checkExist->execute();
        $existRes = $checkExist->get_result()->fetch_assoc();

        if ($existRes) {
            $upStmt = $conn->prepare("
                UPDATE `$attTable`
                SET status = ?, check_in = ?, lunch_out = ?, lunch_in = ?, check_out = ?, total_hours = ?
                WHERE id = ?
            ");
            $upStmt->bind_param("sssssdi", $statusVal, $checkInVal, $lunchOutVal, $lunchInVal, $checkOutVal, $totalHours, $existRes['id']);
            $success = $upStmt->execute();
            $upStmt->close();
            $msg = "Existing attendance record for " . htmlspecialchars($eRes['name']) . " on " . date('d M Y', strtotime($attDate)) . " updated!";
        } else {
            $insStmt = $conn->prepare("
                INSERT INTO `$attTable` (user_id, date, status, check_in, lunch_out, lunch_in, check_out, total_hours)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insStmt->bind_param("issssssd", $empId, $attDate, $statusVal, $checkInVal, $lunchOutVal, $lunchInVal, $checkOutVal, $totalHours);
            $success = $insStmt->execute();
            $insStmt->close();
            $msg = "Attendance logged for " . htmlspecialchars($eRes['name']) . " on " . date('d M Y', strtotime($attDate)) . "!";
        }

        if ($isAjax) {
            echo json_encode(['status' => $success ? 'success' : 'error', 'message' => $msg]);
            exit();
        }

        $_SESSION['flash_msg'] = [
            'type' => $success ? 'success' : 'error',
            'text' => $success ? $msg : 'Failed to save attendance record.'
        ];
        header("Location: " . $redirectUrl);
        exit();

    } elseif ($action === 'delete_attendance') {
        $attId = (int)($_POST['attendance_id'] ?? 0);

        // Verify authorization
        $verifyStmt = $conn->prepare("
            SELECT a.id, u.name, a.date 
            FROM `$attTable` a
            INNER JOIN users u ON a.user_id = u.id
            WHERE a.id = ? AND u.role = 'employee' AND (u.branch_id = ? OR LOWER(u.branch) = LOWER(?))
        ");
        $verifyStmt->bind_param("iis", $attId, $branchId, $branch);
        $verifyStmt->execute();
        $vRes = $verifyStmt->get_result();

        if ($vRes->num_rows === 0) {
            if ($isAjax) {
                echo json_encode(['status' => 'error', 'message' => 'Attendance record not found or unauthorized.']);
                exit();
            }
            $_SESSION['flash_msg'] = ['type' => 'error', 'text' => 'Unauthorized or record not found.'];
            header("Location: " . $redirectUrl);
            exit();
        }

        $vData = $vRes->fetch_assoc();
        $delStmt = $conn->prepare("DELETE FROM `$attTable` WHERE id = ?");
        $delStmt->bind_param("i", $attId);
        $success = $delStmt->execute();
        $delStmt->close();

        $delMsg = "Attendance record for {$vData['name']} on " . date('d M Y', strtotime($vData['date'])) . " removed.";
        if ($isAjax) {
            echo json_encode(['status' => $success ? 'success' : 'error', 'message' => $delMsg]);
            exit();
        }

        $_SESSION['flash_msg'] = [
            'type' => $success ? 'success' : 'error',
            'text' => $success ? $delMsg : 'Failed to delete record.'
        ];
        header("Location: " . $redirectUrl);
        exit();

    } elseif ($action === 'update_employee_settings') {
        $empId = (int)($_POST['id'] ?? 0);
        $workingHours = isset($_POST['working_hours']) ? max(0, min(24, (float)$_POST['working_hours'])) : 10.50;
        $monthlyCL = isset($_POST['monthly_cl']) ? max(0, min(31, (float)$_POST['monthly_cl'])) : 2.00;
        $checkInDays = !empty($_POST['check_in_days']) ? (is_array($_POST['check_in_days']) ? implode(',', $_POST['check_in_days']) : trim($_POST['check_in_days'])) : 'Mon,Tue,Wed,Thu,Fri,Sat';

        // Verify employee belongs to admin's branch
        $verifyEmp = $conn->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'employee' AND (branch_id = ? OR LOWER(branch) = LOWER(?))");
        $verifyEmp->bind_param("iis", $empId, $branchId, $branch);
        $verifyEmp->execute();
        $eRes = $verifyEmp->get_result()->fetch_assoc();

        if (!$eRes) {
            if ($isAjax) {
                echo json_encode(['status' => 'error', 'message' => 'Employee not found in your branch.']);
                exit();
            }
            $_SESSION['flash_msg'] = ['type' => 'error', 'text' => 'Employee not found in your branch.'];
            header("Location: " . $redirectUrl);
            exit();
        }

        $upStmt = $conn->prepare("UPDATE users SET working_hours = ?, monthly_cl = ?, check_in_days = ? WHERE id = ?");
        $upStmt->bind_param("ddsi", $workingHours, $monthlyCL, $checkInDays, $empId);
        $success = $upStmt->execute();
        $upStmt->close();

        $setMsg = "Settings updated for " . htmlspecialchars($eRes['name']) . "!";
        if ($isAjax) {
            echo json_encode(['status' => $success ? 'success' : 'error', 'message' => $setMsg]);
            exit();
        }

        $_SESSION['flash_msg'] = [
            'type' => $success ? 'success' : 'error',
            'text' => $success ? $setMsg : "Failed to update settings."
        ];
        header("Location: " . $redirectUrl);
        exit();
    }
}



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
   if (!$sundayIsWorking && date("N", strtotime($currentDateCheck)) == 7) {
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
    } elseif (
    $row['status'] == "Overtime" ||
    $row['status'] == "Overtime Pending"
) {
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

$autoCheckoutTriggerTime = $isThirthahalliBranch
    ? "20:30:00"
    : "21:00:00";

if ($currentTimeOnly >= $autoCheckoutTriggerTime) {

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

        // Branch-based checkout time
        $autoCheckoutDateTime =
            $currentDate . " " . $officeEndTime;

        $autoCheckoutTime =
            strtotime($autoCheckoutDateTime);

        $lunchSeconds = 0;

        if (
            !empty($att['lunch_out']) &&
            !empty($att['lunch_in'])
        ) {
            $lunchSeconds =
                strtotime($att['lunch_in']) -
                strtotime($att['lunch_out']);
        }

        $totalSeconds =
            $autoCheckoutTime - $checkInTime;

        $workingHours =
            max(0, ($totalSeconds - $lunchSeconds) / 3600);

        $status = "Present";

        $updateAuto = $conn->prepare("
            UPDATE `$attTable`
            SET check_out=?, total_hours=?, status=?
            WHERE id=?
        ");

        $updateAuto->bind_param(
            "sdsi",
            $autoCheckoutDateTime,
            $workingHours,
            $status,
            $attendanceId
        );

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
    $att['date'] . " " . $officeEndTime;

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
// $employeesAbsent = $conn->query("
//     SELECT id, created_at
//     FROM users
//     WHERE role='employee' AND (branch_id='$branchId' OR branch='$branch')
// ");

// while ($emp = $employeesAbsent->fetch_assoc()) {
//     $empId = $emp['id'];
//     $joinDate = date("Y-m-d", strtotime($emp['created_at']));
//     $dateLoop = strtotime($startDate);

//     while ($dateLoop <= strtotime($endDate)) {
//         $loopDate = date("Y-m-d", $dateLoop);

//         if ($loopDate >= $currentDate) {
//             break;
//         }
//         if ($loopDate < $joinDate) {
//             $dateLoop = strtotime("+1 day", $dateLoop);
//             continue;
//         }
//         if (!$sundayIsWorking && date("N", strtotime($loopDate)) == 7) {
//             $dateLoop = strtotime("+1 day", $dateLoop);
//             continue;
//         }

//         $isCompanyLeave = in_array($loopDate, $companyLeaves);

//         $check = $conn->query("SELECT id, status FROM `$attTable` WHERE user_id='$empId' AND date='$loopDate'");

//         if ($isCompanyLeave) {
//             if ($check->num_rows == 0) {
//                 $conn->query("INSERT INTO `$attTable` (user_id, date, status) VALUES ('$empId', '$loopDate', 'CL')");
//             } else {
//                 $existing = $check->fetch_assoc();
//                 if (in_array($existing['status'], ['Absent', 'PL', 'Half Day Absent', 'Half Day PL', 'Half Day'])) {
//                     $conn->query("UPDATE `$attTable` SET status='CL' WHERE id=" . $existing['id']);
//                 }
//             }
//         } else {
//             if ($check->num_rows == 0) {
//                 $conn->query("INSERT INTO `$attTable` (user_id, date, status) VALUES ('$empId', '$loopDate', 'Absent')");
//             }
//         }

//         $dateLoop = strtotime("+1 day", $dateLoop);
//     }

//     if (!$sundayIsWorking) {
//         $cleanupSunday = $conn->prepare("
//             DELETE FROM `$attTable`
//             WHERE user_id = ?
//             AND date BETWEEN ? AND ?
//             AND DAYOFWEEK(date) = 1
//             AND status IN ('Absent', 'PL', 'Half Day', 'Half Day PL', 'Half Day Absent')
//         ");
//         $cleanupSunday->bind_param("iss", $empId, $startDate, $endDate);
//         $cleanupSunday->execute();
//     }

//     /*
//     =========================================
//     REVERT STALE 'CL' ROWS
//     -----------------------------------------
//     A row only ever gets SET to 'CL' above when its date is found in
//     $companyLeaves (i.e. it exists in the company_leaves table for this
//     cycle). But nothing previously reverted that decision if the company
//     leave entry was later edited or deleted -- once a row became 'CL' it
//     stayed 'CL' forever, even on a cycle where company_leaves is now
//     completely empty. That made old/incorrect company-leave entries show
//     up permanently in the "Company Leave" column.
//     Fix: every time the report runs, find every 'CL' row for this
//     employee in this cycle whose date is NOT (no longer) in
//     $companyLeaves, and revert it to 'Absent' so the normal PL quota pass
//     below can re-evaluate it like any other day (it will become PL or
//     Absent depending on quota remaining, exactly as if no CL had ever
//     touched it).
//     =========================================
//     */
//     $existingCLRows = $conn->prepare("
//         SELECT id, date
//         FROM `$attTable`
//         WHERE user_id = ?
//         AND date BETWEEN ? AND ?
//         AND status = 'CL'
//     ");
//     $existingCLRows->bind_param("iss", $empId, $startDate, $endDate);
//     $existingCLRows->execute();
//     $existingCLResult = $existingCLRows->get_result();

//     while ($clRow = $existingCLResult->fetch_assoc()) {
//         $clRowDate = date("Y-m-d", strtotime($clRow['date']));
//         if (!in_array($clRowDate, $companyLeaves)) {
//             // No longer a recognized company leave date -> revert to
//             // Absent so the quota pass below treats it like any other
//             // unaccounted-for day.
//             $conn->query("UPDATE `$attTable` SET status='Absent' WHERE id=" . $clRow['id']);
//         }
//     }

//     $quotaRows = $conn->prepare("
//         SELECT id, date, status
//         FROM `$attTable`
//         WHERE user_id = ?
//         AND date BETWEEN ? AND ?
//         AND status IN ('Absent', 'PL', 'Half Day', 'Half Day PL', 'Half Day Absent')
//         " . (!$isMudipuBranch ? "AND DAYOFWEEK(date) != 1" : "") . "
//         ORDER BY date ASC
//     ");
//     $quotaRows->bind_param("iss", $empId, $startDate, $endDate);
//     $quotaRows->execute();
//     $quotaResult = $quotaRows->get_result();

//     $poolRemaining = 2.0; // shared PL units available this cycle

//     while ($qRow = $quotaResult->fetch_assoc()) {
//         $isHalfDay = ($qRow['status'] == 'Half Day' || $qRow['status'] == 'Half Day PL' || $qRow['status'] == 'Half Day Absent');
//         $unitCost = $isHalfDay ? 0.5 : 1.0;

//         if ($poolRemaining >= $unitCost) {
//             $newStatus = $isHalfDay ? 'Half Day PL' : 'PL';
//             $poolRemaining -= $unitCost;
//         } else {
//             $newStatus = $isHalfDay ? 'Half Day Absent' : 'Absent';
//         }

//         if ($qRow['status'] != $newStatus) {
//             $conn->query("UPDATE `$attTable` SET status='$newStatus' WHERE id=" . $qRow['id']);
//         }
//     }
// }

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
        users.working_hours,
        users.monthly_cl,
        users.check_in_days,
        users.working_days_per_month,
       SUM(CASE WHEN (
        attendance.status='Present'
        OR attendance.status='Overtime'
OR attendance.status='Overtime Pending'
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
    LEFT JOIN `$attTable` attendance ON users.id = attendance.user_id AND attendance.date BETWEEN '$startDate' AND '$endDate' " . (!$sundayIsWorking ? "AND DAYOFWEEK(attendance.date) != 1" : "") . "
    WHERE $whereEmployee
    GROUP BY users.id
    ORDER BY users.name ASC
");

/* =========================
   HISTORY SECTION
========================= */
$whereHistory = "attendance.date BETWEEN '$startDate' AND '$endDate' AND users.role='employee' AND (users.branch_id='$branchId' OR users.branch='$branch')";
if (!$sundayIsWorking) {
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
        attendance.id as attendance_id,
        users.id as user_id,
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
    ORDER BY attendance.date DESC, attendance.id DESC
");

// Fetch branch employees for Add Attendance dropdown (Thirthahalli only)
$branchEmployeesList = [];
if ($isThirthahalliBranch) {
    $beQuery = $conn->query("
        SELECT id, name, employee_id, working_hours, monthly_cl, check_in_days
        FROM users 
        WHERE role = 'employee' AND (branch_id = '$branchId' OR LOWER(branch) = LOWER('$branch'))
        ORDER BY name ASC
    ");
    if ($beQuery) {
        while ($be = $beQuery->fetch_assoc()) {
            $branchEmployeesList[] = $be;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { margin: 0; font-family: 'Poppins', sans-serif; background: #eef2f7; color: #111827; }
    .layout { display: flex; min-height: 100vh; }
    .sidebar { width: 250px; background: linear-gradient(180deg, #111827, #1f2937); color: white; padding: 20px; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 1000; box-sizing: border-box; }
    .sidebar h2 { margin-bottom: 25px; font-size: 20px; text-align: center; font-family: 'Poppins', sans-serif; font-weight: 600; }
    .sidebar a { display: block; padding: 12px 14px; margin: 8px 0; color: white; text-decoration: none; border-radius: 8px; transition: 0.3s; font-size: 14px; font-family: 'Poppins', sans-serif; line-height: 1.5; }
    .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); transform: translateX(4px); font-weight: 600; }
    .sidebar .logout { background: #ef4444; }
    .sidebar .logout:hover { background: #dc2626; transform: none; }
    .main { flex: 1; margin-left: 250px; width: calc(100% - 250px); padding: 25px; min-width: 0; overflow-x: auto; box-sizing: border-box; }
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
        .badge.overtime {
    background: #7c3aed;
}

.badge.overtime-pending {
    background: #d97706;
}
        .badge.cl { background: #7c3aed; }
        .badge.pl { background: #0e2725; }
        .badge.half-pl { background: #0ea5a8; }
        .badge.half-absent { background: #94644a; }
        .red{
            background: #fff44f;
        }

        /* Action Buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 6px 12px;
            font-size: 12.5px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-action.edit {
            background: #4f46e5;
            color: white;
        }
        .btn-action.edit:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }
        .btn-action.settings {
            background: #0d9488;
            color: white;
        }
        .btn-action.settings:hover {
            background: #0f766e;
            transform: translateY(-1px);
        }
        .btn-action.delete {
            background: #ef4444;
            color: white;
        }
        .btn-action.delete:hover {
            background: #dc2626;
        }

        /* Modals */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
            z-index: 2000;
            padding: 15px;
            box-sizing: border-box;
        }
        .modal-card {
            background: #ffffff;
            border-radius: 16px;
            width: 760px;
            max-width: 96vw;
            max-height: 92vh;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid #e2e8f0;
            padding: 24px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #94a3b8;
            cursor: pointer;
            line-height: 1;
            padding: 2px 6px;
            border-radius: 6px;
            transition: 0.2s;
        }
        .modal-close:hover {
            color: #ef4444;
        }
        .emp-badge-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .emp-badge-card .emp-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }
        .emp-badge-card .emp-sub {
            font-size: 12.5px;
            color: #64748b;
        }
        .form-grid-2 {
           
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 12px;
        }
        @media (max-width: 640px) {
            .form-grid-2 { grid-template-columns: 1fr; }
        }
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .field-group label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }
        .field-group input, .field-group select {
            width: 100%;
            height: 38px;
            padding: 6px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            font-family: inherit;
            box-sizing: border-box;
            background: #ffffff;
        }
        .field-group input:focus, .field-group select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }
        .btn-modal-cancel {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-modal-cancel:hover {
            background: #e2e8f0;
        }
        .btn-modal-save {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border: none;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 3px 8px rgba(99, 102, 241, 0.25);
            font-family: inherit;
        }
        .btn-modal-save:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        /* Days Selector for Settings */
        .days-selector { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 4px; margin-bottom: 8px; }
        .day-pill { padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; border: 1.5px solid #cbd5e1; background: #ffffff; color: #475569; cursor: pointer; user-select: none; transition: 0.2s; }
        .day-pill:hover { border-color: #6366f1; color: #6366f1; }
        .day-pill.selected { background: #6366f1; border-color: #6366f1; color: white; }
        .quick-presets { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
        .btn-preset { padding: 3px 8px; font-size: 11px; border-radius: 4px; border: 1px dashed #94a3af; background: white; color: #475569; cursor: pointer; font-family: inherit; }
        .btn-preset:hover { background: #e0e7ff; border-color: #6366f1; color: #4338ca; }

        @media print {
            .sidebar, .filter-box, button, .no-print { display: none !important; }
            body { background: white; }
            .main { padding: 0; margin-left: 0 !important; width: 100% !important; }
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
        <?php
          $adminBranchId = $_SESSION['user']['branch_id'] ?? $_SESSION['branch_id'] ?? 0;
          $adminBranch = $_SESSION['user']['branch'] ?? $_SESSION['branch'] ?? '';
          $leaveCountQuery = $conn->query("SELECT COUNT(*) as total FROM leave_requests lr LEFT JOIN users u ON lr.employee_id = u.id WHERE (u.branch_id='$adminBranchId' OR u.branch='$adminBranch') AND lr.status='pending'");
          $leaveCount = $leaveCountQuery ? $leaveCountQuery->fetch_assoc()['total'] : 0;
        ?>
        <a href="leave_requests.php">📩 Manage Leaves <?php if($leaveCount > 0) { ?><span style="background:#ef4444; color:white; padding:2px 8px; border-radius:50px; font-size:12px; margin-left:8px; font-weight:600;"><?= $leaveCount ?></span><?php } ?></a>
        <a href="add_leave.php">📅 Company Leaves</a>
        <a href="reports.php" class="active">📊 Reports</a>
        <a href="employee_settings.php">⚙️ Employee Settings</a>
        <a href="../auth/logout.php" class="logout">🚪 Logout</a>
    </div>

    <div class="main">
        <div class="header">
            <h1>Attendance Reports</h1>
            <p>Month Dates: <b><?= date("d M Y", strtotime($startDate)) ?></b> - <b><?= date("d M Y", strtotime($endDate)) ?></b></p>
        </div>

        <?php if (!empty($_SESSION['flash_msg'])): ?>
            <div class="no-print" style="background: <?= $_SESSION['flash_msg']['type'] === 'success' ? '#ecfdf5' : '#fef2f2' ?>; color: <?= $_SESSION['flash_msg']['type'] === 'success' ? '#065f46' : '#991b1b' ?>; border: 1px solid <?= $_SESSION['flash_msg']['type'] === 'success' ? '#a7f3d0' : '#fecaca' ?>; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; font-size: 14px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="bi <?= $_SESSION['flash_msg']['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>" style="font-size: 18px;"></i>
                    <span><?= htmlspecialchars($_SESSION['flash_msg']['text']) ?></span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: inherit; padding: 0 4px; line-height: 1;">&times;</button>
            </div>
            <?php unset($_SESSION['flash_msg']); ?>
        <?php endif; ?>

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
                // $prevMonthLabel = date("M", strtotime("-1 month", $targetTime)); 
                // $currMonthLabel = date("M", $targetTime);                        
                // $yearLabel      = date("Y", $targetTime);                        
                
                // $displayLabel = $prevMonthLabel . " - " . $currMonthLabel . " " . $yearLabel;

                if ($isThirthahalliBranch) {

    // Example: September 2026
    $displayLabel = date("F Y", $targetTime);

} else {

    // Example: Aug - Sep 2026
    $prevMonthLabel = date("M", strtotime("-1 month", $targetTime));
    $currMonthLabel = date("M", $targetTime);
    $yearLabel      = date("Y", $targetTime);

    $displayLabel =
        $prevMonthLabel . " - " .
        $currMonthLabel . " " .
        $yearLabel;
}
                
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
                    <option
    value="Overtime Pending"
    <?= $status_filter == 'Overtime Pending' ? 'selected' : '' ?>
>
    Overtime Pending
</option>

<option
    value="Overtime"
    <?= $status_filter == 'Overtime' ? 'selected' : '' ?>
>
    Overtime
</option>
                </select>
                <button type="submit" class="btn-primary">Search</button>
                <button type="button" class="btn-print" onclick="printSummary()">Print Report</button>
            </form>
        </div>

        <div class="table-wrapper" id="employeeSummary">
          <div class="table-title">
    👨‍💼 Employee Monthly Report
    <?= $isThirthahalliBranch ? '(1st - End of Month)' : '(21st - 20th)' ?>
</div>
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
                    <!-- <?php if ($isThirthahalliBranch): ?>
                        <th class="no-print" style="width:110px;">Action</th>
                    <?php endif; ?> -->
                </tr>
              <?php while($emp = $employees->fetch_assoc()):

    $presentCount = (float)($emp['present_count'] ?? 0);
    

    $fullAbsent = (float)($emp['absent_count'] ?? 0);

    $halfDayAbsent = (float)($emp['halfday_absent_count'] ?? 0);

    $halfDayCount = (float)($emp['halfday_count'] ?? 0);

    // Already consumed Monthly CL:
    // PL = 1
    // Half Day PL = 0.5
    $monthlyCLUsed = (float)($emp['pl_count'] ?? 0);

    // Maximum Monthly CL allowed (per-employee setting or default 2.0)
    $monthlyCLLimit = (isset($emp['monthly_cl']) && $emp['monthly_cl'] !== null)
        ? (float)$emp['monthly_cl']
        : 2.0;

    // Target working days per month (calculated dynamically from employee's specific check-in days in this work cycle)
    $empCheckInDays = getEmployeeCheckInDaysArray($emp['check_in_days'] ?? '', $branchName);
    $empWorkingDays = calculateEmployeeWorkingDaysInCycle($startDate, $endDate, $empCheckInDays, $companyLeaves);

    // First calculate actual absent leave units from attendance
    $absentUnits =
        $fullAbsent +
        ($halfDayAbsent * 0.5);

    /*
    |--------------------------------------------------------------------------
    | MONTHLY CL & ABSENT ADJUSTMENT
    |--------------------------------------------------------------------------
    |
    | Monthly CL cannot exceed the employee's monthly allowance ($monthlyCLLimit, default 2.0).
    | - If Monthly CL used > limit: cap Monthly CL at limit, and any excess
    |   leaves beyond the limit come under Absent.
    | - If Monthly CL used <= limit: remaining allowance covers any absent days
    |   up to the limit.
    |
    */
    if ($monthlyCLUsed > $monthlyCLLimit) {
        $excessCL = $monthlyCLUsed - $monthlyCLLimit;
        $monthlyCLDisplay = $monthlyCLLimit;
        $absentDisplay = $absentUnits + $excessCL;
    } else {
        $remainingCL = $monthlyCLLimit - $monthlyCLUsed;
        $clTakenFromLeave = min(
            $remainingCL,
            $absentUnits
        );
        $monthlyCLDisplay =
            $monthlyCLUsed +
            $clTakenFromLeave;
        $absentDisplay =
            $absentUnits -
            $clTakenFromLeave;
    }


    /*
    |--------------------------------------------------------------------------
    | HALF DAY DISPLAY
    |--------------------------------------------------------------------------
    */

    $halfDayDisplay = $halfDayCount;


    /*
    |--------------------------------------------------------------------------
    | TOTAL ATTENDANCE
    |--------------------------------------------------------------------------
    | One day before month end, add the 4 Sundays on to total attendance.
    */

    $rawAttendance =
        $presentCount +
        $monthlyCLDisplay +
        ($halfDayCount * 0.5);

    // Add 4 Sundays if at or past one day before month end (for active employees with attendance)
    $sundayCredit = ($isOneDayBeforeMonthEnd && ($rawAttendance > 0 || ($emp['cl_count'] ?? 0) > 0)) ? 4 : 0;

    $finalAttendance = $rawAttendance + $sundayCredit;
    if ($finalAttendance > $currentMonthDaysCount) {
        $finalAttendance = (float)$currentMonthDaysCount;
    }

?>
                <tr>
                    <td><?= $emp['name'] ?></td>
                    <td><?= $emp['employee_id'] ?></td>
                  <td class="green">
    <?= $presentCount ?>
</td>

<td class="red">
    <?= $absentDisplay == floor($absentDisplay)
        ? number_format($absentDisplay, 0)
        : number_format($absentDisplay, 1) ?>
</td>

<td class="orange">
    <?= $halfDayDisplay ?>
</td>

<td style="color:#7c3aed;font-weight:600;">
    <?= $emp['cl_count'] ?? 0 ?>
</td>

<td style="color:#0d9488;font-weight:600;">
    <?= $monthlyCLDisplay == floor($monthlyCLDisplay)
        ? number_format($monthlyCLDisplay, 0)
        : number_format($monthlyCLDisplay, 1) ?>
</td>

<td style="font-weight:600;">
    <?= $finalAttendance == floor($finalAttendance) ? number_format($finalAttendance, 0) : number_format($finalAttendance, 1) ?>
    / <?= $currentMonthDaysCount ?> Days
    <small style="display:block; color:#6b7280; font-size:11px; font-weight:normal;"><?= htmlspecialchars(formatCheckInDaysDisplay($emp['check_in_days'] ?? '', $branchName)) ?></small>
</td>
<?php if ($isThirthahalliBranch): ?>
<!-- <td class="no-print">
    <button type="button" class="btn-action settings" onclick='openEmpSettingsModal(<?= htmlspecialchars(json_encode([
        "id" => (int)$emp["id"],
        "name" => $emp["name"],
        "employee_id" => $emp["employee_id"],
        "working_hours" => $emp["working_hours"],
        "monthly_cl" => $emp["monthly_cl"],
        "check_in_days" => $emp["check_in_days"]
    ]), ENT_QUOTES, "UTF-8") ?>)' title="Edit Employee Settings">
        <i class="bi bi-gear-fill"></i> Settings
    </button>
</td> -->
<?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div class="table-wrapper">
            <div class="table-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <span>🕒 Recent Attendance History</span>
                <!-- <?php if ($isThirthahalliBranch): ?>
                <button type="button" class="btn-action edit no-print" onclick="openAddAttendanceModal()" style="font-size:13px; padding:8px 16px;">
                    <i class="bi bi-plus-circle"></i> Add Attendance Record
                </button>
                <?php endif; ?> -->
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
                    <?php if ($canEditReports): ?>
                    <th class="no-print" style="width:100px;">Action</th>
                    <?php endif; ?>
                </tr>
                <?php while($row = $history->fetch_assoc()): 
                    $currentRowDate = date("Y-m-d", strtotime($row['date']));
                    $lightColors = ["#fef2f2", "#eff6ff", "#f0fdf4", "#fff7ed", "#faf5ff", "#fdf2f8", "#ecfeff", "#f9fafb"];
                    $colorIndex = abs(crc32($currentRowDate)) % count($lightColors);
                    $rowBgColor = $lightColors[$colorIndex];
                ?>
                <tr style="background: <?= $rowBgColor ?>;">
                    <td><?= date("d M Y", strtotime($row['date'])) ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['employee_id']) ?></td>
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
                        <?php elseif($row['status'] == 'Overtime Pending'): ?>
                            <span class="badge overtime-pending">Overtime Pending</span>
                        <?php elseif($row['status'] == 'Overtime'): ?>
                            <span class="badge overtime">Overtime</span>
                        <?php else: ?>
                            <span class="badge late"><?= htmlspecialchars($row['status']) ?></span>
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
                        } elseif (!empty($row['total_hours']) && (float)$row['total_hours'] > 0) {
                            $workingHours = number_format((float)$row['total_hours'], 2) . " hrs";
                        }
                        echo $workingHours;
                        ?>
                    </td>
                    <?php if ($canEditReports): ?>
                    <td class="no-print">
                        <button type="button" class="btn-action edit" onclick='openEditAttendanceModal(<?= htmlspecialchars(json_encode([
                            "id" => (int)$row["attendance_id"],
                            "user_id" => (int)$row["user_id"],
                            "name" => $row["name"],
                            "employee_id" => $row["employee_id"],
                            "date" => $row["date"],
                            "status" => $row["status"],
                            "check_in" => $row["check_in"],
                            "lunch_out" => $row["lunch_out"],
                            "lunch_in" => $row["lunch_in"],
                            "check_out" => $row["check_out"],
                            "total_hours" => $row["total_hours"]
                        ]), ENT_QUOTES, "UTF-8") ?>)' title="Edit Attendance Record">
                            <i class="bi bi-pencil-square"></i> Edit
                        </button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</div>

<?php if ($canEditReports): ?>
<!-- =========================================
     MODAL: EDIT ATTENDANCE RECORD
========================================= -->
<div id="editAttendanceModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="bi bi-pencil-square" style="color:#4f46e5;"></i> Edit Attendance Record</h3>
            <button type="button" class="modal-close" onclick="closeEditAttendanceModal()">&times;</button>
        </div>

        <form method="POST" id="editAttendanceForm">
            <input type="hidden" name="action" value="update_attendance">
            <input type="hidden" name="attendance_id" id="editAttId">

            <div class="emp-badge-card">
                <div>
                    <div class="emp-name" id="editEmpName">-</div>
                    <div class="emp-sub">Employee ID: <b id="editEmpCode">-</b></div>
                </div>
                <div>
                    <span style="font-size:12px; color:#64748b; font-weight:600;">DATE:</span>
                    <span id="editAttDateDisplay" style="font-size:13px; font-weight:700; color:#1e293b; margin-left:4px;">-</span>
                </div>
            </div>

            <input type="hidden" id="editAttDate">
            <input type="hidden" name="check_in" id="editAttCheckIn">
            <input type="hidden" name="check_out" id="editAttCheckOut">
            <input type="hidden" name="lunch_out" id="editAttLunchOut">
            <input type="hidden" name="lunch_in" id="editAttLunchIn">

            <div class="form-grid-2">
                <div class="field-group">
                    <label>Attendance Status</label>
                    <select name="status" id="editAttStatus">
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Half Day">Half Day</option>
                        <option value="Half Day PL">Half Day (Monthly CL)</option>
                        <option value="Half Day Absent">Half Day (Absent)</option>
                        <option value="Late">Late</option>
                        <option value="CL">Company Leave (CL)</option>
                        <option value="PL">Monthly CL (PL)</option>
                        <option value="Overtime">Overtime</option>
                        <option value="Overtime Pending">Overtime Pending</option>
                    </select>
                </div>
                <div class="field-group">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <label>Total Working Hours (hrs)</label>
                        <span id="editHoursText" style="font-size:12px; font-weight:700; color:#16a34a;">-</span>
                    </div>
                    <input type="number" step="0.01" min="0" max="24" name="total_hours" id="editAttHours" placeholder="e.g. 10.50">
                </div>
            </div>

            <!-- 12-HOUR FORMAT TIME INPUTS -->
            <div class="form-grid-2">
                <div class="field-group">
                    <label><i class="bi bi-box-arrow-in-right" style="color:#16a34a;"></i> Morning Check In </label>
                    <div style="display:flex; align-items:center; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:2px 8px; height:38px;">
                        <select id="editInHour" onchange="calcEditHours()" style="border:none; background:transparent; font-weight:600; font-size:13.5px; outline:none; cursor:pointer; color:#1e293b;">
                            <option value="">--</option>
                            <?php for ($h = 1; $h <= 12; $h++): $hStr = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $hStr ?>"><?= $hStr ?></option>
                            <?php endfor; ?>
                        </select>
                        <span style="font-weight:700; color:#94a3b8; margin:0 3px;">:</span>
                        <select id="editInMin" onchange="calcEditHours()" style="border:none; background:transparent; font-weight:600; font-size:13.5px; outline:none; cursor:pointer; color:#1e293b;">
                            <?php for ($m = 0; $m < 60; $m++): $mStr = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $mStr ?>"><?= $mStr ?></option>
                            <?php endfor; ?>
                        </select>
                        <select id="editInPeriod" onchange="calcEditHours()" style="border:none; background:#eff6ff; color:#2563eb; font-weight:700; font-size:12px; border-radius:6px; padding:3px 7px; margin-left:auto; outline:none; cursor:pointer;">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                </div>

                <div class="field-group">
                    <label><i class="bi bi-box-arrow-right" style="color:#dc2626;"></i> Evening Check Out </label>
                    <div style="display:flex; align-items:center; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:2px 8px; height:38px;">
                        <select id="editOutHour" onchange="calcEditHours()" style="border:none; background:transparent; font-weight:600; font-size:13.5px; outline:none; cursor:pointer; color:#1e293b;">
                            <option value="">--</option>
                            <?php for ($h = 1; $h <= 12; $h++): $hStr = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $hStr ?>"><?= $hStr ?></option>
                            <?php endfor; ?>
                        </select>
                        <span style="font-weight:700; color:#94a3b8; margin:0 3px;">:</span>
                        <select id="editOutMin" onchange="calcEditHours()" style="border:none; background:transparent; font-weight:600; font-size:13.5px; outline:none; cursor:pointer; color:#1e293b;">
                            <?php for ($m = 0; $m < 60; $m++): $mStr = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $mStr ?>"><?= $mStr ?></option>
                            <?php endfor; ?>
                        </select>
                        <select id="editOutPeriod" onchange="calcEditHours()" style="border:none; background:#eff6ff; color:#2563eb; font-weight:700; font-size:12px; border-radius:6px; padding:3px 7px; margin-left:auto; outline:none; cursor:pointer;">
                            <option value="AM">AM</option>
                            <option value="PM" selected>PM</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="field-group">
                    <label><i class="bi bi-cup-hot" style="color:#d97706;"></i> Lunch Out </label>
                    <div style="display:flex; align-items:center; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:2px 8px; height:38px;">
                        <select id="editLOutHour" onchange="calcEditHours()" style="border:none; background:transparent; font-weight:600; font-size:13.5px; outline:none; cursor:pointer; color:#1e293b;">
                            <option value="">--</option>
                            <?php for ($h = 1; $h <= 12; $h++): $hStr = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $hStr ?>"><?= $hStr ?></option>
                            <?php endfor; ?>
                        </select>
                        <span style="font-weight:700; color:#94a3b8; margin:0 3px;">:</span>
                        <select id="editLOutMin" onchange="calcEditHours()" style="border:none; background:transparent; font-weight:600; font-size:13.5px; outline:none; cursor:pointer; color:#1e293b;">
                            <?php for ($m = 0; $m < 60; $m++): $mStr = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $mStr ?>"><?= $mStr ?></option>
                            <?php endfor; ?>
                        </select>
                        <select id="editLOutPeriod" onchange="calcEditHours()" style="border:none; background:#eff6ff; color:#2563eb; font-weight:700; font-size:12px; border-radius:6px; padding:3px 7px; margin-left:auto; outline:none; cursor:pointer;">
                            <option value="AM">AM</option>
                            <option value="PM" selected>PM</option>
                        </select>
                    </div>
                </div>

                <div class="field-group">
                    <label><i class="bi bi-cup-hot-fill" style="color:#d97706;"></i> Lunch In </label>
                    <div style="display:flex; align-items:center; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:2px 8px; height:38px;">
                        <select id="editLInHour" onchange="calcEditHours()" style="border:none; background:transparent; font-weight:600; font-size:13.5px; outline:none; cursor:pointer; color:#1e293b;">
                            <option value="">--</option>
                            <?php for ($h = 1; $h <= 12; $h++): $hStr = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $hStr ?>"><?= $hStr ?></option>
                            <?php endfor; ?>
                        </select>
                        <span style="font-weight:700; color:#94a3b8; margin:0 3px;">:</span>
                        <select id="editLInMin" onchange="calcEditHours()" style="border:none; background:transparent; font-weight:600; font-size:13.5px; outline:none; cursor:pointer; color:#1e293b;">
                            <?php for ($m = 0; $m < 60; $m++): $mStr = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $mStr ?>"><?= $mStr ?></option>
                            <?php endfor; ?>
                        </select>
                        <select id="editLInPeriod" onchange="calcEditHours()" style="border:none; background:#eff6ff; color:#2563eb; font-weight:700; font-size:12px; border-radius:6px; padding:3px 7px; margin-left:auto; outline:none; cursor:pointer;">
                            <option value="AM">AM</option>
                            <option value="PM" selected>PM</option>
                        </select>
                    </div>
                </div>
            </div>

            <p style="font-size:11.5px; color:#64748b; margin:6px 0 0 0;">
                <i class="bi bi-info-circle"></i> Times are in 12-hour format (AM/PM). Working hours auto-calculate from punch in/out minus lunch. Select "--" to clear any time.
            </p>

            <div class="modal-footer" style="justify-content:space-between;">
                <button type="button" class="btn-action delete" onclick="confirmDeleteAttendance()">
                    <i class="bi bi-trash"></i> Delete Record
                </button>
                <div style="display:flex; gap:10px;">
                    <button type="button" class="btn-modal-cancel" onclick="closeEditAttendanceModal()">Cancel</button>
                    <button type="submit" class="btn-modal-save">
                        <i class="bi bi-check2"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- =========================================
     MODAL: ADD ATTENDANCE RECORD
========================================= -->
<div id="addAttendanceModal" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="bi bi-plus-circle" style="color:#4f46e5;"></i> Add Attendance Record</h3>
            <button type="button" class="modal-close" onclick="closeAddAttendanceModal()">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="add_attendance">

            <div class="form-grid-2">
                <div class="field-group">
                    <label>Select Employee *</label>
                    <select name="user_id" id="addAttUserId" required>
                        <option value="">-- Choose Employee --</option>
                        <?php foreach ($branchEmployeesList as $bEmp): ?>
                            <option value="<?= $bEmp['id'] ?>"><?= htmlspecialchars($bEmp['name']) ?> (<?= htmlspecialchars($bEmp['employee_id']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-group">
                    <label>Attendance Date *</label>
                    <input type="date" name="date" id="addAttDate" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="field-group">
                    <label>Attendance Status *</label>
                    <select name="status" id="addAttStatus">
                        <option value="Present" selected>Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Half Day">Half Day</option>
                        <option value="Half Day PL">Half Day (Monthly CL)</option>
                        <option value="Half Day Absent">Half Day (Absent)</option>
                        <option value="Late">Late</option>
                        <option value="CL">Company Leave (CL)</option>
                        <option value="PL">Monthly CL (PL)</option>
                        <option value="Overtime">Overtime</option>
                        <option value="Overtime Pending">Overtime Pending</option>
                    </select>
                </div>
                <div class="field-group">
                    <label>Total Working Hours (decimal hrs)</label>
                    <input type="number" step="0.01" min="0" max="24" name="total_hours" id="addAttHours" placeholder="e.g. 10.50">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="field-group">
                    <label><i class="bi bi-box-arrow-in-right" style="color:#16a34a;"></i> Morning Check In</label>
                    <input type="datetime-local" name="check_in" id="addAttCheckIn" onchange="calcAddHours()">
                </div>
                <div class="field-group">
                    <label><i class="bi bi-box-arrow-right" style="color:#dc2626;"></i> Evening Check Out</label>
                    <input type="datetime-local" name="check_out" id="addAttCheckOut" onchange="calcAddHours()">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="field-group">
                    <label><i class="bi bi-cup-hot" style="color:#d97706;"></i> Lunch Out</label>
                    <input type="datetime-local" name="lunch_out" id="addAttLunchOut" onchange="calcAddHours()">
                </div>
                <div class="field-group">
                    <label><i class="bi bi-cup-hot-fill" style="color:#d97706;"></i> Lunch In</label>
                    <input type="datetime-local" name="lunch_in" id="addAttLunchIn" onchange="calcAddHours()">
                </div>
            </div>

            <p style="font-size:11.5px; color:#64748b; margin:6px 0 0 0;">
                <i class="bi bi-info-circle"></i> If a record already exists for the selected employee on this date, it will be updated.
            </p>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeAddAttendanceModal()">Cancel</button>
                <button type="submit" class="btn-modal-save">
                    <i class="bi bi-plus-circle"></i> Log Attendance
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================
     MODAL: EDIT EMPLOYEE SETTINGS
========================================= -->
<div id="empSettingsModal" class="modal-overlay">
    <div class="modal-card" style="width:620px;">
        <div class="modal-header">
            <h3><i class="bi bi-gear-fill" style="color:#0d9488;"></i> Employee Settings</h3>
            <button type="button" class="modal-close" onclick="closeEmpSettingsModal()">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="update_employee_settings">
            <input type="hidden" name="id" id="settingEmpId">

            <div class="emp-badge-card">
                <div>
                    <div class="emp-name" id="settingEmpName">-</div>
                    <div class="emp-sub">Employee ID: <b id="settingEmpCode">-</b></div>
                </div>
                <span style="font-size:12px; color:#0d9488; background:#ccfbf1; font-weight:600; padding:4px 10px; border-radius:20px;">Branch Employee</span>
            </div>

            <div class="form-grid-2">
                <div class="field-group">
                    <label>Daily Working Hours (hrs)</label>
                    <input type="number" step="0.5" min="1" max="24" name="working_hours" id="settingWorkingHours" required>
                </div>
                <div class="field-group">
                    <label>Monthly CL Allowance (days)</label>
                    <input type="number" step="0.5" min="0" max="31" name="monthly_cl" id="settingMonthlyCL" required>
                </div>
            </div>

            <div class="field-group" style="margin-top:4px;">
                <label>Specific Check-in Days</label>
                <input type="hidden" name="check_in_days" id="settingCheckInDaysInput" value="Mon,Tue,Wed,Thu,Fri,Sat">
                <div class="days-selector" id="settingDaysSelector">
                    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
                        <div class="day-pill" data-day="<?= $d ?>" onclick="toggleReportDayPill(this)"><?= $d ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="quick-presets">
                    <button type="button" class="btn-preset" onclick="setReportDaysPreset('mon-sat')">Mon - Sat</button>
                    <button type="button" class="btn-preset" onclick="setReportDaysPreset('mon-fri')">Mon - Fri</button>
                    <button type="button" class="btn-preset" onclick="setReportDaysPreset('all')">All Days</button>
                </div>
            </div>

            <p style="font-size:11.5px; color:#64748b; margin:6px 0 0 0;">
                <i class="bi bi-info-circle"></i> Updating Monthly CL allowance and working days immediately recalculates monthly attendance quotas in the report.
            </p>

            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeEmpSettingsModal()">Cancel</button>
                <button type="submit" class="btn-modal-save" style="background:linear-gradient(135deg, #0d9488, #0f766e);">
                    <i class="bi bi-check2"></i> Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteAttendanceForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_attendance">
    <input type="hidden" name="attendance_id" id="deleteAttId">
</form>
<?php endif; ?>

<script>
<?php if ($canEditReports): ?>
function parse12HrParts(dtStr) {
    if (!dtStr) return null;
    let m = String(dtStr).match(/(\d{4})-(\d{2})-(\d{2})[T ](\d{1,2}):(\d{2})/);
    let hr, min;
    if (m) {
        hr = parseInt(m[4], 10);
        min = parseInt(m[5], 10);
    } else {
        let d = new Date(dtStr);
        if (isNaN(d.getTime())) return null;
        hr = d.getHours();
        min = d.getMinutes();
    }
    let period = hr >= 12 ? 'PM' : 'AM';
    let h12 = hr % 12;
    if (h12 === 0) h12 = 12;
    return {
        hour: String(h12).padStart(2, '0'),
        minute: String(min).padStart(2, '0'),
        period: period
    };
}

function set12Hr(hourId, minId, periodId, dtStr, defaultPeriod) {
    defaultPeriod = defaultPeriod || 'AM';
    const parts = parse12HrParts(dtStr);
    const hElem = document.getElementById(hourId);
    const mElem = document.getElementById(minId);
    const pElem = document.getElementById(periodId);
    if (!hElem || !mElem || !pElem) return;
    if (parts) {
        hElem.value = parts.hour;
        mElem.value = parts.minute;
        pElem.value = parts.period;
    } else {
        hElem.value = "";
        mElem.value = "00";
        pElem.value = defaultPeriod;
    }
}

function build24HrDateTime(dateStr, hourId, minId, periodId) {
    const hElem = document.getElementById(hourId);
    const hVal = hElem ? hElem.value : '';
    if (!hVal || !dateStr) return '';
    const mVal = document.getElementById(minId) ? document.getElementById(minId).value : '00';
    const pVal = document.getElementById(periodId) ? document.getElementById(periodId).value : 'AM';
    let hr = parseInt(hVal, 10);
    if (pVal === 'PM' && hr < 12) hr += 12;
    if (pVal === 'AM' && hr === 12) hr = 0;
    const hrStr = String(hr).padStart(2, '0');
    const minStr = String(mVal).padStart(2, '0');
    return `${dateStr} ${hrStr}:${minStr}:00`;
}

function calcEditHours() {
    const attDate = document.getElementById('editAttDate') ? document.getElementById('editAttDate').value : '';
    const inDt = build24HrDateTime(attDate, 'editInHour', 'editInMin', 'editInPeriod');
    const outDt = build24HrDateTime(attDate, 'editOutHour', 'editOutMin', 'editOutPeriod');
    const lOutDt = build24HrDateTime(attDate, 'editLOutHour', 'editLOutMin', 'editLOutPeriod');
    const lInDt = build24HrDateTime(attDate, 'editLInHour', 'editLInMin', 'editLInPeriod');

    // Update hidden inputs for form submission
    if (document.getElementById('editAttCheckIn')) document.getElementById('editAttCheckIn').value = inDt;
    if (document.getElementById('editAttCheckOut')) document.getElementById('editAttCheckOut').value = outDt;
    if (document.getElementById('editAttLunchOut')) document.getElementById('editAttLunchOut').value = lOutDt;
    if (document.getElementById('editAttLunchIn')) document.getElementById('editAttLunchIn').value = lInDt;

    const hoursInput = document.getElementById('editAttHours');
    const hoursText = document.getElementById('editHoursText');

    if (!inDt || !outDt) {
        if (hoursText) hoursText.textContent = '-';
        return;
    }

    const tIn = new Date(inDt.replace(' ', 'T')).getTime();
    const tOut = new Date(outDt.replace(' ', 'T')).getTime();

    if (isNaN(tIn) || isNaN(tOut) || tOut <= tIn) {
        if (hoursInput) hoursInput.value = '0.00';
        if (hoursText) hoursText.textContent = '0 hrs 0 mins';
        return;
    }

    let lunchSec = 0;
    if (lOutDt && lInDt) {
        const tLOut = new Date(lOutDt.replace(' ', 'T')).getTime();
        const tLIn = new Date(lInDt.replace(' ', 'T')).getTime();
        if (!isNaN(tLOut) && !isNaN(tLIn) && tLIn > tLOut) {
            lunchSec = Math.floor((tLIn - tLOut) / 1000);
        }
    }

    const totalSec = Math.floor((tOut - tIn) / 1000);
    const workSec = Math.max(0, totalSec - lunchSec);

    const hrs = Math.floor(workSec / 3600);
    const mins = Math.floor((workSec % 3600) / 60);
    const decimalHours = (workSec / 3600).toFixed(2);

    if (hoursInput) hoursInput.value = decimalHours;
    if (hoursText) hoursText.textContent = `${hrs} hrs ${mins} mins`;
}

function calculateHoursFromInputs(inId, outId, lunchOutId, lunchInId, targetHoursId) {
    const inElem = document.getElementById(inId);
    const outElem = document.getElementById(outId);
    if (!inElem || !outElem) return;
    const inVal = inElem.value;
    const outVal = outElem.value;
    if (!inVal || !outVal) return;

    const tIn = new Date(inVal).getTime();
    const tOut = new Date(outVal).getTime();
    if (isNaN(tIn) || isNaN(tOut) || tOut <= tIn) {
        const tgt = document.getElementById(targetHoursId);
        if (tgt) tgt.value = "0.00";
        return;
    }

    let lunchMs = 0;
    const lOutVal = document.getElementById(lunchOutId) ? document.getElementById(lunchOutId).value : "";
    const lInVal = document.getElementById(lunchInId) ? document.getElementById(lunchInId).value : "";
    if (lOutVal && lInVal) {
        const tLOut = new Date(lOutVal).getTime();
        const tLIn = new Date(lInVal).getTime();
        if (!isNaN(tLOut) && !isNaN(tLIn) && tLIn > tLOut) {
            lunchMs = tLIn - tLOut;
        }
    }

    const workMs = Math.max(0, (tOut - tIn) - lunchMs);
    const hours = (workMs / (1000 * 60 * 60)).toFixed(2);
    const tgt = document.getElementById(targetHoursId);
    if (tgt) tgt.value = hours;
}

function calcAddHours() {
    calculateHoursFromInputs('addAttCheckIn', 'addAttCheckOut', 'addAttLunchOut', 'addAttLunchIn', 'addAttHours');
}

/* Edit Attendance Modal */
function openEditAttendanceModal(att) {
    document.getElementById('editAttId').value = att.id || '';
    document.getElementById('deleteAttId').value = att.id || '';
    document.getElementById('editEmpName').textContent = att.name || 'Employee';
    document.getElementById('editEmpCode').textContent = att.employee_id || '-';
    
    // Display formatted date
    let formattedDate = att.date || '-';
    if (att.date) {
        let dParts = att.date.split('-');
        if (dParts.length === 3) {
            let dObj = new Date(parseInt(dParts[0], 10), parseInt(dParts[1], 10) - 1, parseInt(dParts[2], 10));
            formattedDate = dObj.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }
    }
    document.getElementById('editAttDateDisplay').textContent = formattedDate;
    document.getElementById('editAttDate').value = att.date || '';
    document.getElementById('editAttStatus').value = att.status || 'Present';

    // Populate 12-hour selectors
    set12Hr('editInHour', 'editInMin', 'editInPeriod', att.check_in, 'AM');
    set12Hr('editOutHour', 'editOutMin', 'editOutPeriod', att.check_out, 'PM');
    set12Hr('editLOutHour', 'editLOutMin', 'editLOutPeriod', att.lunch_out, 'PM');
    set12Hr('editLInHour', 'editLInMin', 'editLInPeriod', att.lunch_in, 'PM');

    // Calculate hours from the 12-hour inputs
    calcEditHours();

    // If both check-in and check-out are not present, but total_hours already exists in record
    if ((!att.check_in || !att.check_out) && att.total_hours !== null && att.total_hours !== undefined && att.total_hours !== '') {
        const hVal = parseFloat(att.total_hours);
        document.getElementById('editAttHours').value = hVal.toFixed(2);
        const wholeH = Math.floor(hVal);
        const remM = Math.round((hVal - wholeH) * 60);
        document.getElementById('editHoursText').textContent = `${wholeH} hrs ${remM} mins`;
    }

    document.getElementById('editAttendanceModal').style.display = 'flex';
}

function closeEditAttendanceModal() {
    document.getElementById('editAttendanceModal').style.display = 'none';
}

function confirmDeleteAttendance() {
    if (confirm('Are you sure you want to permanently delete this attendance record?')) {
        document.getElementById('deleteAttendanceForm').submit();
    }
}

/* Add Attendance Modal */
function openAddAttendanceModal() {
    document.getElementById('addAttUserId').selectedIndex = 0;
    document.getElementById('addAttDate').value = '<?= date("Y-m-d") ?>';
    document.getElementById('addAttStatus').value = 'Present';
    document.getElementById('addAttCheckIn').value = '';
    document.getElementById('addAttCheckOut').value = '';
    document.getElementById('addAttLunchOut').value = '';
    document.getElementById('addAttLunchIn').value = '';
    document.getElementById('addAttHours').value = '';

    document.getElementById('addAttendanceModal').style.display = 'flex';
}

function closeAddAttendanceModal() {
    document.getElementById('addAttendanceModal').style.display = 'none';
}

/* Employee Settings Modal */
function openEmpSettingsModal(emp) {
    document.getElementById('settingEmpId').value = emp.id || '';
    document.getElementById('settingEmpName').textContent = emp.name || 'Employee';
    document.getElementById('settingEmpCode').textContent = emp.employee_id || '-';
    document.getElementById('settingWorkingHours').value = (emp.working_hours !== undefined && emp.working_hours !== null) ? parseFloat(emp.working_hours) : 10.5;
    document.getElementById('settingMonthlyCL').value = (emp.monthly_cl !== undefined && emp.monthly_cl !== null) ? parseFloat(emp.monthly_cl) : 2.0;

    const daysStr = emp.check_in_days || 'Mon,Tue,Wed,Thu,Fri,Sat';
    const daysArr = daysStr.split(',').map(s => s.trim());
    document.querySelectorAll('#settingDaysSelector .day-pill').forEach(pill => {
        const d = pill.getAttribute('data-day');
        pill.classList.toggle('selected', daysArr.includes(d));
    });
    updateReportDaysInput();

    document.getElementById('empSettingsModal').style.display = 'flex';
}

function closeEmpSettingsModal() {
    document.getElementById('empSettingsModal').style.display = 'none';
}

function toggleReportDayPill(el) {
    el.classList.toggle('selected');
    updateReportDaysInput();
}

function updateReportDaysInput() {
    const selected = [];
    document.querySelectorAll('#settingDaysSelector .day-pill.selected').forEach(pill => {
        selected.push(pill.getAttribute('data-day'));
    });
    document.getElementById('settingCheckInDaysInput').value = selected.join(',');
}

function setReportDaysPreset(preset) {
    document.querySelectorAll('#settingDaysSelector .day-pill').forEach(pill => {
        const d = pill.getAttribute('data-day');
        let sel = false;
        if (preset === 'all') sel = true;
        else if (preset === 'mon-sat') sel = (d !== 'Sun');
        else if (preset === 'mon-fri') sel = (d !== 'Sat' && d !== 'Sun');
        pill.classList.toggle('selected', sel);
    });
    updateReportDaysInput();
}

// Close modals when clicking outside card or pressing Escape
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditAttendanceModal();
        closeAddAttendanceModal();
        closeEmpSettingsModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const editHoursInput = document.getElementById('editAttHours');
    if (editHoursInput) {
        editHoursInput.addEventListener('input', function() {
            const val = parseFloat(this.value);
            const txt = document.getElementById('editHoursText');
            if (!isNaN(val) && val >= 0) {
                const h = Math.floor(val);
                const m = Math.round((val - h) * 60);
                if (txt) txt.textContent = `${h} hrs ${m} mins`;
            } else {
                if (txt) txt.textContent = '-';
            }
        });
    }

    const editForm = document.getElementById('editAttendanceForm');
    if (editForm) {
        editForm.addEventListener('submit', function() {
            calcEditHours();
        });
    }
});
<?php endif; ?>

/* Print Summary */
function printSummary() {
    const summaryContainer = document.getElementById("employeeSummary").cloneNode(true);
    summaryContainer.querySelectorAll('.no-print').forEach(el => el.remove());
    const printContent = summaryContainer.innerHTML;
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
                .no-print{ display:none !important; }
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