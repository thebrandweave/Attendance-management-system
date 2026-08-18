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

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    if ($id > 0) {
        // 1. Fetch user to confirm role
        $stmt = $conn->prepare("SELECT id, name, branch FROM users WHERE id = ? AND role = 'employee'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $userBranch = $user['branch'] ?? '';

            // Disable foreign key checks temporarily for smooth cleanup across legacy & branch tables
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");

            // 2. Delete from legacy `attendance` table
            $delLegacy = $conn->prepare("DELETE FROM attendance WHERE user_id = ?");
            $delLegacy->bind_param("i", $id);
            $delLegacy->execute();
            $delLegacy->close();

            // 3. Delete from all `attendance_%` per-branch tables
            $tablesRes = $conn->query("SHOW TABLES LIKE 'attendance_%'");
            while ($row = $tablesRes->fetch_array()) {
                $tName = $row[0];
                $conn->query("DELETE FROM `$tName` WHERE user_id = $id");
            }

            // 4. Delete from `leave_requests` table
            $delLeave = $conn->prepare("DELETE FROM leave_requests WHERE employee_id = ?");
            $delLeave->bind_param("i", $id);
            $delLeave->execute();
            $delLeave->close();

            // 5. Delete user from `users` table
            $delUser = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'employee'");
            $delUser->bind_param("i", $id);
            $delUser->execute();
            $delUser->close();

            // Re-enable foreign key checks
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        }
    }
}

header("Location: dashboard.php");
exit();
?>