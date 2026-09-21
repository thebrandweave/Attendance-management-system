<?php

session_start();

include("../config/db.php");
require_once "../config/branch_helper.php";

date_default_timezone_set("Asia/Kolkata");

if (
    !isset($_SESSION['user']) ||
    $_SESSION['user']['role'] !== 'admin'
) {
    http_response_code(403);
    exit("Unauthorized");
}

$adminBranch = $_SESSION['user']['branch'] ?? '';

$isThirthahalliBranch = (
    strtolower(trim($adminBranch)) === "thirthahalli"
);

if (!$isThirthahalliBranch) {
    http_response_code(403);
    exit("Overtime approval is only configured for Thirthahalli.");
}

$attTable = getBranchTableNameOnly(
    $conn,
    $adminBranch
);

$attendanceId = (int)($_POST['attendance_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (
    !$attendanceId ||
    !in_array($action, ['approve', 'reject'], true)
) {
    exit("Invalid request");
}

$stmt = $conn->prepare("
    SELECT *
    FROM `$attTable`
    WHERE id = ?
");

$stmt->bind_param("i", $attendanceId);
$stmt->execute();

$attendance = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attendance) {
    exit("Attendance not found");
}

if ($attendance['status'] !== 'Overtime Pending') {
    exit("No pending overtime request");
}

if ($action === 'approve') {

    $newStatus = "Overtime";

} else {

    $totalHours = (float)($attendance['total_hours'] ?? 0);

    if ($totalHours < 6.75) {

        $newStatus = "Half Day";

    } else {

        $checkInTime = !empty($attendance['check_in'])
            ? date("H:i:s", strtotime($attendance['check_in']))
            : null;

        if (
            $checkInTime &&
            $checkInTime > "10:00:00"
        ) {
            $newStatus = "Late";
        } else {
            $newStatus = "Present";
        }
    }
}

$stmt = $conn->prepare("
    UPDATE `$attTable`
    SET status = ?
    WHERE id = ?
");

$stmt->bind_param(
    "si",
    $newStatus,
    $attendanceId
);

$stmt->execute();
$stmt->close();

header("Location: ../admin/dashboard.php");
exit();