<?php
session_start();
include("../config/db.php");
require_once "../config/branch_helper.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit();
    }
    header("Location: ../index.php");
    exit();
}

$idInput = $_REQUEST['ids'] ?? $_REQUEST['id'] ?? '';
$ids = [];

if (is_array($idInput)) {
    $ids = array_map('intval', $idInput);
} else {
    foreach (explode(',', (string)$idInput) as $p) {
        $v = intval(trim($p));
        if ($v > 0) $ids[] = $v;
    }
}
$ids = array_values(array_unique(array_filter($ids)));
$status = trim($_REQUEST['status'] ?? '');

if (!empty($ids) && !empty($status)) {
    $idList = implode(',', $ids);
    $statusSafe = $conn->real_escape_string($status);

    // 1. Update leave request status for all IDs
    $conn->query("UPDATE leave_requests SET status = '$statusSafe' WHERE id IN ($idList)");

    // 2. If approved, update or insert attendance record in employee's branch table
    if (strtolower($status) === 'approved' || strtolower($status) === 'approve') {
        $lrRes = $conn->query("
            SELECT lr.*, u.branch, u.branch_id 
            FROM leave_requests lr 
            JOIN users u ON lr.employee_id = u.id 
            WHERE lr.id IN ($idList)
        ");

        if ($lrRes) {
            while ($lrRow = $lrRes->fetch_assoc()) {
                $empId = (int)$lrRow['employee_id'];
                $leaveDate = $lrRow['date'];
                $userBranch = $lrRow['branch'] ?? 'gdedutech';
                $attTable = getBranchTableNameOnly($conn, $userBranch);

                $chk = $conn->query("SELECT id FROM `$attTable` WHERE user_id = $empId AND date = '$leaveDate'");
                if ($chk && $chk->num_rows > 0) {
                    $conn->query("UPDATE `$attTable` SET status = 'PL' WHERE user_id = $empId AND date = '$leaveDate'");
                } else {
                    $conn->query("INSERT INTO `$attTable` (user_id, date, status) VALUES ($empId, '$leaveDate', 'PL')");
                }
            }
        }
    }

    // 3. Dispatch consolidated email notification to the employee
    require_once __DIR__ . '/../config/mail_helper.php';
    sendLeaveApprovalNotification($conn, $ids, $status);
}

if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success', 
        'message' => 'Leave status updated successfully!',
        'count' => count($ids)
    ]);
    exit();
}

header("Location: ../admin/leave_requests.php");
exit();
?>