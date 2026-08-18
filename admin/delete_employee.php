<?php

session_start();

include("../config/db.php");

/* =========================
   AUTH CHECK
========================= */

if (
    !isset($_SESSION['user']) ||
    $_SESSION['user']['role'] != "admin"
) {

    header("Location: ../index.php");
    exit();
}

/* =========================
   ADMIN BRANCH
========================= */

$adminBranchId = $_SESSION['user']['branch_id'] ?? $_SESSION['branch_id'] ?? 0;
$adminBranch = $_SESSION['user']['branch'] ?? $_SESSION['branch'] ?? '';

/* =========================
   DELETE EMPLOYEE
========================= */

if (isset($_GET['id'])) {

    $id = (int)$_GET['id'];

    /* =========================
       CHECK SAME BRANCH
    ========================= */

    $check = $conn->prepare("
        SELECT id
        FROM users
        WHERE id = ?
        AND role='employee'
        AND (branch_id = ? OR branch = ?)
    ");

    $check->bind_param(
        "iis",
        $id,
        $adminBranchId,
        $adminBranch
    );

    $check->execute();

    $result = $check->get_result();

    /* =========================
       UNAUTHORIZED ACCESS
    ========================= */

    if ($result->num_rows == 0) {

        die("Unauthorized Access");
    }

    require_once "../config/branch_helper.php";
    $attTable = getBranchTableNameOnly($conn, $adminBranch);

    /* =========================
       DELETE ATTENDANCE
    ========================= */

    $deleteAttendance = $conn->prepare("
        DELETE FROM `$attTable`
        WHERE user_id = ?
    ");

    $deleteAttendance->bind_param(
        "i",
        $id
    );

    $deleteAttendance->execute();

    $deleteAttendance->execute();

    /* =========================
       DELETE EMPLOYEE
    ========================= */

    $deleteUser = $conn->prepare("
        DELETE FROM users
        WHERE id = ?
        AND role='employee'
        AND branch = ?
    ");

    $deleteUser->bind_param(
        "is",
        $id,
        $adminBranch
    );

    $deleteUser->execute();

    /* =========================
       REDIRECT
    ========================= */

    header("Location: dashboard.php");

    exit();
}

?>