<?php
session_start();
include("../config/db.php");
require_once "../config/branch_helper.php";

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
   DELETE EMPLOYEE
========================= */
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    /* =========================
       SECURITY CHECK: SAME BRANCH
    ========================= */
    $check = $conn->prepare("
        SELECT id, branch
        FROM users
        WHERE id = ?
        AND role='employee'
        AND (branch_id = ? OR LOWER(branch) = LOWER(?))
    ");
    $check->bind_param("iis", $id, $adminBranchId, $adminBranch);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows == 0) {
        die("Unauthorized Access or Employee Not Found");
    }

    $empData = $result->fetch_assoc();
    $empBranchTable = getBranchTableNameOnly($conn, $empData['branch'] ?? $adminBranchName);

    /* =========================
       DELETE ATTENDANCE RECORDS
    ========================= */
    $delAtt = $conn->prepare("DELETE FROM `$empBranchTable` WHERE user_id = ?");
    $delAtt->bind_param("i", $id);
    $delAtt->execute();
    $delAtt->close();

    /* =========================
       DELETE LEAVE REQUESTS
    ========================= */
    $delLeaves = $conn->prepare("DELETE FROM leave_requests WHERE employee_id = ?");
    $delLeaves->bind_param("i", $id);
    $delLeaves->execute();
    $delLeaves->close();

    /* =========================
       DELETE EMPLOYEE USER
    ========================= */
    $deleteUser = $conn->prepare("DELETE FROM users WHERE id = ? AND role='employee'");
    $deleteUser->bind_param("i", $id);
    $deleteUser->execute();
    $deleteUser->close();

    /* =========================
       REDIRECT
    ========================= */
    header("Location: dashboard.php");
    exit();
}

header("Location: dashboard.php");
exit();
?>