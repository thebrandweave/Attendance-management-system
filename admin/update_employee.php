<?php

session_start();

include("../config/db.php");

date_default_timezone_set("Asia/Kolkata");

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

$adminBranch = $_SESSION['user']['branch'];

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

/* =========================
   SECURITY CHECK
   ONLY SAME BRANCH
========================= */

$branchCheck = $conn->prepare("
    SELECT id
    FROM users
    WHERE id = ?
    AND role='employee'
    AND branch = ?
");

$branchCheck->bind_param(
    "is",
    $id,
    $adminBranch
);

$branchCheck->execute();

$branchResult = $branchCheck->get_result();

if ($branchResult->num_rows == 0) {

    die("Unauthorized Access");
}

/* =========================
   UPDATE USER
========================= */

$stmt = $conn->prepare("
    UPDATE users
    SET
        name = ?,
        employee_id = ?
    WHERE id = ?
");

$stmt->bind_param(
    "ssi",
    $name,
    $employee_id,
    $id
);

$stmt->execute();

/* =========================
   CHECK ATTENDANCE EXISTS
========================= */

$check = $conn->prepare("
    SELECT id
    FROM attendance
    WHERE user_id = ?
    AND date = ?
");

$check->bind_param(
    "is",
    $id,
    $today
);

$check->execute();

$result = $check->get_result();

/* =========================
   UPDATE ATTENDANCE
========================= */

if ($result->num_rows > 0) {

    $stmt = $conn->prepare("
        UPDATE attendance
        SET
            status = ?,
            check_in = ?,
            check_out = ?
        WHERE user_id = ?
        AND date = ?
    ");

    $stmt->bind_param(
        "sssis",
        $status,
        $check_in,
        $check_out,
        $id,
        $today
    );

    $stmt->execute();

} else {

    /* =========================
       INSERT ATTENDANCE
    ========================= */

    $stmt = $conn->prepare("
        INSERT INTO attendance
        (
            user_id,
            date,
            status,
            check_in,
            check_out
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "issss",
        $id,
        $today,
        $status,
        $check_in,
        $check_out
    );

    $stmt->execute();
}

/* =========================
   REDIRECT
========================= */

header("Location: dashboard.php");

exit();

?>