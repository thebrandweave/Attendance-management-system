<?php
session_start();
include("../config/db.php");
require_once "../config/branch_helper.php";

date_default_timezone_set("Asia/Kolkata");

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
    header("Location: ../index.php");
    exit();
}

/* =========================
   ADMIN BRANCH
========================= */
$adminBranchId = $_SESSION['user']['branch_id'] ?? $_SESSION['branch_id'] ?? 0;
$adminBranch = $_SESSION['user']['branch'] ?? $_SESSION['branch'] ?? '';

$bStmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ? OR LOWER(branch_name) = LOWER(?)");
$bStmt->bind_param("is", $adminBranchId, $adminBranch);
$bStmt->execute();
$bRes = $bStmt->get_result()->fetch_assoc();
$adminBranchName = $bRes ? $bRes['branch_name'] : ucfirst($adminBranch);
$attTable = getBranchTableNameOnly($conn, $adminBranchName);

/* =========================
   FORM DATA
========================= */
$id = (int)$_POST['id'];
$name = trim($_POST['name']);
$employee_id = trim($_POST['employee_id']);
$status = trim($_POST['status']);

$check_in = !empty($_POST['check_in'])
    ? date("Y-m-d H:i:s", strtotime($_POST['check_in']))
    : null;

$check_out = !empty($_POST['check_out'])
    ? date("Y-m-d H:i:s", strtotime($_POST['check_out']))
    : null;

$today = date("Y-m-d");

// Calculate total hours if check_in and check_out exist
$total_hours = 0.0;
if (!empty($check_in) && !empty($check_out)) {
    $cIn = strtotime($check_in);
    $cOut = strtotime($check_out);
    if ($cOut > $cIn) {
        $total_hours = round(($cOut - $cIn) / 3600, 2);
    }
}

/* =========================
   SECURITY CHECK: SAME BRANCH
========================= */
$branchCheck = $conn->prepare("
    SELECT id
    FROM users
    WHERE id = ?
    AND role='employee'
    AND (branch_id = ? OR branch = ?)
");
$branchCheck->bind_param("iis", $id, $adminBranchId, $adminBranch);
$branchCheck->execute();
$branchResult = $branchCheck->get_result();

if ($branchResult->num_rows == 0) {
    die("Unauthorized Access");
}

/* =========================
   UPDATE USER
========================= */
if (!empty($status)) {
    $stmt = $conn->prepare("
        UPDATE users
        SET
            name = ?,
            employee_id = ?,
            status = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssi", $name, $employee_id, $status, $id);
} else {
    $stmt = $conn->prepare("
        UPDATE users
        SET
            name = ?,
            employee_id = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssi", $name, $employee_id, $id);
}
$stmt->execute();
$stmt->close();

/* =========================
   CHECK ATTENDANCE EXISTS IN BRANCH TABLE
========================= */
$check = $conn->prepare("
    SELECT id
    FROM `$attTable`
    WHERE user_id = ?
    AND date = ?
");
$check->bind_param("is", $id, $today);
$check->execute();
$result = $check->get_result();

/* =========================
   UPDATE / INSERT ATTENDANCE IN BRANCH TABLE
========================= */
if ($result->num_rows > 0) {
    $stmt = $conn->prepare("
        UPDATE `$attTable`
        SET
            status = ?,
            check_in = ?,
            check_out = ?,
            total_hours = ?
        WHERE user_id = ?
        AND date = ?
    ");
    $stmt->bind_param("sssdis", $status, $check_in, $check_out, $total_hours, $id, $today);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("
        INSERT INTO `$attTable`
        (user_id, date, status, check_in, check_out, total_hours)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issssd", $id, $today, $status, $check_in, $check_out, $total_hours);
    $stmt->execute();
    $stmt->close();
}

/* =========================
   REDIRECT
========================= */
header("Location: dashboard.php");
exit();
?>