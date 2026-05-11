<?php
include("../config/db.php");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
    header("Location: ../index.php");
    exit();
}

$id = $_POST['id'];

$name = $_POST['name'];

$employee_id = $_POST['employee_id'];

$status = $_POST['status'];

$check_in = !empty($_POST['check_in'])
    ? date("Y-m-d H:i:s", strtotime($_POST['check_in']))
    : null;

$check_out = !empty($_POST['check_out'])
    ? date("Y-m-d H:i:s", strtotime($_POST['check_out']))
    : null;

$today = date("Y-m-d");

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

$check->bind_param("is", $id, $today);

$check->execute();

$result = $check->get_result();

/* =========================
   UPDATE / INSERT ATTENDANCE
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

header("Location: dashboard.php");
exit();
?>