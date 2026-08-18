<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/branch_helper.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'verify_admin':
        $empId = trim($_POST['admin_id'] ?? '');
        $pass  = $_POST['password'] ?? '';

        if (empty($empId) || empty($pass)) {
            echo json_encode(['status' => 'error', 'message' => 'Admin ID and password are required']);
            exit();
        }

        $stmt = $conn->prepare("SELECT * FROM users WHERE employee_id=? AND role='admin'");
        $stmt->bind_param("s", $empId);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            if (password_verify($pass, $user['password'])) {
                $_SESSION['create_branch_authorized'] = true;
                $_SESSION['verifier_admin_id'] = $user['id'];
                echo json_encode(['status' => 'success', 'message' => 'Admin authorization verified!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Incorrect admin password']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Admin ID not found']);
        }
        break;

    case 'create_branch_admin':
        // Check session authorization or existing logged in admin
        $isAuth = !empty($_SESSION['create_branch_authorized']) || (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin');
        if (!$isAuth) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please verify admin credentials first.']);
            exit();
        }

        $branchName = trim($_POST['branch_name'] ?? '');
        $branchCode = strtoupper(trim($_POST['branch_code'] ?? ''));
        $location   = trim($_POST['location'] ?? '');
        $adminName  = trim($_POST['admin_name'] ?? '');
        $adminId    = trim($_POST['admin_id'] ?? '');
        $adminPass  = $_POST['admin_password'] ?? '';

        if (empty($branchName) || empty($adminName) || empty($adminId) || empty($adminPass)) {
            echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled.']);
            exit();
        }

        if (empty($branchCode)) {
            $branchCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $branchName), 0, 3));
        }

        // Check if branch name already exists
        $bCheck = $conn->prepare("SELECT id FROM branches WHERE LOWER(branch_name) = LOWER(?)");
        $bCheck->bind_param("s", $branchName);
        $bCheck->execute();
        if ($bCheck->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => "Branch '$branchName' already exists."]);
            exit();
        }

        // Check if admin ID already exists
        $uCheck = $conn->prepare("SELECT id FROM users WHERE employee_id = ?");
        $uCheck->bind_param("s", $adminId);
        $uCheck->execute();
        if ($uCheck->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => "Admin ID '$adminId' already exists."]);
            exit();
        }

        // 1. Insert Branch
        $bStmt = $conn->prepare("INSERT INTO branches (branch_name, branch_code, location, status) VALUES (?, ?, ?, 'Active')");
        $bStmt->bind_param("sss", $branchName, $branchCode, $location);
        if (!$bStmt->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create branch record.']);
            exit();
        }

        $newBranchId = $bStmt->insert_id;
        
        // Create per-branch attendance table in MySQL
        getBranchAttendanceTable($conn, $branchName);
        $hashedPass = password_hash($adminPass, PASSWORD_DEFAULT);

        // 2. Insert Branch Admin User
        $uStmt = $conn->prepare("INSERT INTO users (name, employee_id, password, role, branch, branch_id, status, created_at) VALUES (?, ?, ?, 'admin', ?, ?, 'Active', NOW())");
        $uStmt->bind_param("ssssi", $adminName, $adminId, $hashedPass, $branchName, $newBranchId);

        if ($uStmt->execute()) {
            unset($_SESSION['create_branch_authorized']);
            echo json_encode(['status' => 'success', 'message' => "Branch '$branchName' and Admin '$adminName' created successfully!"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Branch created but failed to create admin user.']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action request']);
        break;
}
?>
