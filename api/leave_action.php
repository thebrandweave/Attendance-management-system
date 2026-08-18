<?php
session_start();
include("../config/db.php");
require_once "../config/branch_helper.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit();
    }
    header("Location: ../index.php");
    exit();
}

$id = intval($_REQUEST['id'] ?? 0);
$status = trim($_REQUEST['status'] ?? '');

if ($id > 0 && !empty($status)) {
    // 1. Update leave request status
    $stmt = $conn->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();

    // 2. If approved, update or insert attendance record in employee's branch table
    if (strtolower($status) === 'approved' || strtolower($status) === 'approve') {
        $lrRes = $conn->query("
            SELECT lr.*, u.branch, u.branch_id 
            FROM leave_requests lr 
            JOIN users u ON lr.employee_id = u.id 
            WHERE lr.id = $id
        ")->fetch_assoc();

        if ($lrRes) {
            $empId = $lrRes['employee_id'];
            $leaveDate = $lrRes['date'];
            $userBranch = $lrRes['branch'] ?? 'gdedutech';
            $attTable = getBranchTableNameOnly($conn, $userBranch);

            $chk = $conn->query("SELECT id FROM `$attTable` WHERE user_id = $empId AND date = '$leaveDate'");
            if ($chk->num_rows > 0) {
                $conn->query("UPDATE `$attTable` SET status = 'PL' WHERE user_id = $empId AND date = '$leaveDate'");
            } else {
                $conn->query("INSERT INTO `$attTable` (user_id, date, status) VALUES ($empId, '$leaveDate', 'PL')");
            }
        }
    }
}

if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Leave status updated successfully!']);
    exit();
}

header("Location: ../admin/leave_requests.php");
exit();
?>