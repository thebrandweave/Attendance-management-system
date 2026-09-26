<?php
$lifetime = 60 * 60 * 24 * 30;
session_set_cookie_params($lifetime);
session_start();
include("../config/db.php");
require_once "../config/branch_helper.php";

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
    header("Location: ../index.php");
    exit();
}

// Ensure database schema columns exist
ensureEmployeeSettingsColumns($conn);

$adminBranchId = $_SESSION['user']['branch_id'] ?? 0;
$adminBranch = $_SESSION['user']['branch'] ?? '';

// Fetch dynamic branch details from DB if available
$bStmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ? OR LOWER(branch_name) = LOWER(?)");
$bStmt->bind_param("is", $adminBranchId, $adminBranch);
$bStmt->execute();
$bRes = $bStmt->get_result()->fetch_assoc();
$adminBranchName = $bRes ? $bRes['branch_name'] : ucfirst($adminBranch);

$validDaysList = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function sanitizeCheckInDays($input) {
    global $validDaysList;
    if (is_array($input)) {
        $clean = array_values(array_intersect($validDaysList, $input));
        return !empty($clean) ? implode(',', $clean) : 'Mon,Tue,Wed,Thu,Fri,Sat';
    }
    $parts = explode(',', (string)$input);
    $parts = array_values(array_filter(array_map('trim', $parts)));
    $clean = array_values(array_intersect($validDaysList, $parts));
    return !empty($clean) ? implode(',', $clean) : 'Mon,Tue,Wed,Thu,Fri,Sat';
}

/* ========================================================
   HANDLE POST ACTIONS (SINGLE & BULK UPDATE)
======================================================== */
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_single') {
        $empId = (int)($_POST['id'] ?? 0);
        $workingHours = isset($_POST['working_hours']) ? max(0, min(24, (float)$_POST['working_hours'])) : 8.00;
        $monthlyCL = isset($_POST['monthly_cl']) ? max(0, min(31, (float)$_POST['monthly_cl'])) : 2.00;
        $checkInDays = sanitizeCheckInDays($_POST['check_in_days'] ?? 'Mon,Tue,Wed,Thu,Fri,Sat');

        // Security check: employee must belong to admin's branch
        $checkStmt = $conn->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'employee' AND (branch_id = ? OR (branch_id IS NULL AND branch = ?))");
        $checkStmt->bind_param("iis", $empId, $adminBranchId, $adminBranch);
        $checkStmt->execute();
        $empData = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$empData) {
            if ($isAjax) {
                echo json_encode(['status' => 'error', 'message' => 'Employee not found or unauthorized access.']);
                exit();
            }
            $_SESSION['flash_error'] = "Employee not found or unauthorized access.";
            header("Location: employee_settings.php");
            exit();
        }

        $upStmt = $conn->prepare("UPDATE users SET working_hours = ?, monthly_cl = ?, check_in_days = ? WHERE id = ?");
        $upStmt->bind_param("ddsi", $workingHours, $monthlyCL, $checkInDays, $empId);
        $success = $upStmt->execute();
        $upStmt->close();

        if ($isAjax) {
            echo json_encode([
                'status' => $success ? 'success' : 'error',
                'message' => $success ? "Settings updated for " . htmlspecialchars($empData['name']) . "!" : "Database update failed.",
                'working_hours' => number_format($workingHours, 1),
                'monthly_cl' => number_format($monthlyCL, 1),
                'check_in_days' => $checkInDays,
                'check_in_days_display' => formatCheckInDaysDisplay($checkInDays, $adminBranchName)
            ]);
            exit();
        }

        $_SESSION['flash_success'] = "Settings updated successfully for " . htmlspecialchars($empData['name']) . "!";
        header("Location: employee_settings.php");
        exit();

    } elseif ($action === 'update_bulk') {
        $bulkHours = isset($_POST['bulk_working_hours']) ? max(0, min(24, (float)$_POST['bulk_working_hours'])) : 8.00;
        $bulkCL = isset($_POST['bulk_monthly_cl']) ? max(0, min(31, (float)$_POST['bulk_monthly_cl'])) : 2.00;
        $bulkDays = sanitizeCheckInDays($_POST['bulk_check_in_days'] ?? 'Mon,Tue,Wed,Thu,Fri,Sat');

        $bulkStmt = $conn->prepare("UPDATE users SET working_hours = ?, monthly_cl = ?, check_in_days = ? WHERE role = 'employee' AND (branch_id = ? OR (branch_id IS NULL AND branch = ?))");
        $bulkStmt->bind_param("ddsis", $bulkHours, $bulkCL, $bulkDays, $adminBranchId, $adminBranch);
        $bulkSuccess = $bulkStmt->execute();
        $bulkStmt->close();

        if ($isAjax) {
            echo json_encode([
                'status' => $bulkSuccess ? 'success' : 'error',
                'message' => $bulkSuccess ? "Applied settings to all employees in this branch!" : "Bulk update failed."
            ]);
            exit();
        }

        $_SESSION['flash_success'] = "Default settings applied to all employees successfully!";
        header("Location: employee_settings.php");
        exit();
    }
}

// Fetch all employees in this branch
$stmt = $conn->prepare("SELECT id, name, employee_id, working_hours, monthly_cl, check_in_days, status, created_at FROM users WHERE role='employee' AND (branch_id=? OR (branch_id IS NULL AND branch=?)) ORDER BY name ASC");
$stmt->bind_param("is", $adminBranchId, $adminBranch);
$stmt->execute();
$employeesList = $stmt->get_result();

$totalEmps = $employeesList->num_rows;

// Leave count for sidebar badge
$leaveCountQuery = $conn->query("SELECT COUNT(*) as total FROM leave_requests lr LEFT JOIN users u ON lr.employee_id = u.id WHERE (u.branch_id='$adminBranchId' OR u.branch='$adminBranch') AND lr.status='pending'");
$leaveCount = $leaveCountQuery ? $leaveCountQuery->fetch_assoc()['total'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Employee Settings - <?= htmlspecialchars($adminBranchName) ?> Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { margin: 0; font-family: 'Poppins', sans-serif; background: #eef2f7; color: #111827; }
    .layout { display: flex; min-height: 100vh; }

    /* SIDEBAR */
    .sidebar { width: 250px; background: linear-gradient(180deg, #111827, #1f2937); color: white; padding: 20px; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 1000; box-sizing: border-box; }
    .sidebar h2 { margin-bottom: 25px; font-size: 20px; text-align: center; font-family: 'Poppins', sans-serif; font-weight: 600; }
    .sidebar a { display: block; padding: 12px 14px; margin: 8px 0; color: white; text-decoration: none; border-radius: 8px; transition: 0.3s; font-size: 14px; font-family: 'Poppins', sans-serif; line-height: 1.5; }
    .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); transform: translateX(4px); font-weight: 600; }
    .sidebar .logout { background: #ef4444; }
    .sidebar .logout:hover { background: #dc2626; transform: none; }

    /* MAIN */
    .main { flex: 1; margin-left: 250px; width: calc(100% - 250px); padding: 25px; min-width: 0; box-sizing: border-box; }

    /* HEADER */
    .header { background: white; padding: 22px 25px; border-radius: 14px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
    .header-title h1 { font-size: 24px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px; }
    .header-title p { color: #6b7280; font-size: 13.5px; margin-top: 4px; }
    .btn-create { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 10px 18px; border-radius: 10px; font-weight: 600; font-size: 14px; text-decoration: none; transition: 0.3s; box-shadow: 0 4px 12px rgba(102,126,234,0.3); }
    .btn-create:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(102,126,234,0.4); }

    /* STATS GRID */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 25px; }
    .stat-card { background: white; border-radius: 14px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 16px; border-left: 4px solid #667eea; }
    .stat-card:nth-child(2) { border-left-color: #8b5cf6; }
    .stat-card:nth-child(3) { border-left-color: #0d9488; }
    .stat-card:nth-child(4) { border-left-color: #f59e0b; }
    .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: white; flex-shrink: 0; }
    .stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
    .stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
    .stat-icon.teal { background: linear-gradient(135deg, #0d9488, #0f766e); }
    .stat-icon.amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .stat-info h4 { font-size: 13px; color: #6b7280; font-weight: 500; margin-bottom: 4px; }
    .stat-info p { font-size: 20px; font-weight: 700; color: #111827; }

    /* BULK APPLY CARD */
    .bulk-card { background: white; border-radius: 14px; padding: 22px 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 25px; border-top: 3px solid #667eea; }
    .bulk-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .bulk-header h3 { font-size: 16px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 10px; }
    .bulk-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; align-items: flex-end; }
    .form-group label { display: block; font-size: 12.5px; font-weight: 600; color: #4b5563; margin-bottom: 6px; }
    .form-group input[type="number"], .form-group input[type="text"] { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; font-family: inherit; }
    .form-group input:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.2); }
    .btn-bulk-apply { padding: 11px 22px; background: #111827; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; height: 42px; font-family: inherit; font-size: 13.5px; }
    .btn-bulk-apply:hover { background: #374151; }

    /* DAY PILLS SELECTOR */
    .days-selector { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
    .day-pill { padding: 7px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600; border: 1.5px solid #d1d5db; background: #f9fafb; color: #4b5563; cursor: pointer; user-select: none; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px; }
    .day-pill:hover { border-color: #667eea; color: #667eea; }
    .day-pill.selected { background: #667eea; border-color: #667eea; color: white; box-shadow: 0 2px 6px rgba(102,126,234,0.3); }
    .quick-presets { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
    .btn-preset { padding: 3px 9px; font-size: 11px; border-radius: 5px; border: 1px dashed #9ca3af; background: white; color: #4b5563; cursor: pointer; font-family: inherit; transition: 0.2s; }
    .btn-preset:hover { background: #e0e7ff; border-color: #667eea; color: #4338ca; }

    /* TABLE CARD */
    .card { background: white; border-radius: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); padding: 22px; }
    .card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; }
    .card-header-flex h3 { font-size: 17px; font-weight: 600; color: #111827; }
    .search-box { position: relative; min-width: 260px; }
    .search-box input { width: 100%; padding: 10px 14px 10px 38px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13.5px; font-family: inherit; outline: none; }
    .search-box input:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.15); }
    .search-box i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #9ca3af; }

    /* TABLE */
    .table-responsive { width: 100%; overflow-x: auto; border-radius: 10px; border: 1px solid #e5e7eb; }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { background: #f8fafc; color: #475569; padding: 14px 16px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
    td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; vertical-align: middle; }
    tr:hover td { background: #f8fafc; }
    .emp-cell { display: flex; align-items: center; gap: 12px; }
    .emp-avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0; }
    .emp-name { font-weight: 600; color: #0f172a; }
    .emp-id-sub { font-size: 12px; color: #64748b; }

    /* BADGES */
    .badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 30px; font-size: 12.5px; font-weight: 600; }
    .badge-hours { background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; }
    .badge-cl { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; }
    .badge-days { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }

    /* ACTION BUTTONS */
    .btn-edit { background: #667eea; color: white; border: none; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; }
    .btn-edit:hover { background: #556cd6; transform: translateY(-1px); }

    /* MODAL */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(17,24,39,0.5); backdrop-filter: blur(2px); justify-content: center; align-items: center; z-index: 9999; }
    .modal-content { background: white; border-radius: 16px; width: 480px; max-width: 92%; box-shadow: 0 20px 40px rgba(0,0,0,0.15); animation: modalIn 0.25s ease-out; overflow: hidden; }
    @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
    .modal-header h3 { font-size: 17px; font-weight: 600; color: #111827; }
    .modal-close { background: none; border: none; font-size: 20px; color: #9ca3af; cursor: pointer; }
    .modal-close:hover { color: #111827; }
    .modal-body { padding: 24px; }
    .modal-emp-info { background: #f3f4f6; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
    .modal-emp-info .name { font-weight: 600; color: #111827; }
    .modal-emp-info .id { font-size: 13px; color: #6b7280; }
    .modal-actions { display: flex; gap: 12px; margin-top: 24px; }
    .btn-save { flex: 1; padding: 12px; border: none; border-radius: 8px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; font-weight: 600; cursor: pointer; font-size: 14px; font-family: inherit; transition: 0.2s; }
    .btn-save:hover { opacity: 0.95; transform: translateY(-1px); }
    .btn-cancel { flex: 1; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; background: white; color: #374151; font-weight: 600; cursor: pointer; font-size: 14px; font-family: inherit; transition: 0.2s; }
    .btn-cancel:hover { background: #f9fafb; }
    .form-hint { font-size: 11.5px; color: #6b7280; margin-top: 4px; }

    /* TOAST */
    .toast { position: fixed; top: 25px; right: 25px; background: #10b981; color: white; padding: 14px 20px; border-radius: 12px; font-size: 14px; font-weight: 600; box-shadow: 0 10px 25px rgba(0,0,0,0.15); transform: translateX(150%); opacity: 0; transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); z-index: 10000; display: flex; align-items: center; gap: 10px; }
    .toast.show { transform: translateX(0); opacity: 1; }
    .toast.error { background: #ef4444; }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main { margin-left: 0; width: 100%; padding: 15px; }
      .bulk-form-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<div class="layout">
  <!-- SIDEBAR -->
  <div class="sidebar">
    <h2><?= htmlspecialchars($adminBranchName) ?> Admin</h2>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="create_employee.php">👤 Create Employee</a>
    <a href="employee_settings.php" class="active">⚙️ Employee Settings</a>
    <a href="../api/checkin.php">🟢 Check In- Morning</a>
    <a href="../api/lunch.php">🍽️ Lunch Break</a>
    <a href="../api/checkout.php">🔴 Check Out- Evening</a>
    <a href="leave_requests.php">📩 Manage Leaves <?php if($leaveCount > 0) { ?><span style="background:#ef4444; color:white; padding:2px 8px; border-radius:50px; font-size:12px; margin-left:8px; font-weight:600;"><?= $leaveCount ?></span><?php } ?></a>
    <a href="add_leave.php">📅 Company Leaves</a>
    <a href="reports.php">📊 Reports</a>
    <a href="../auth/logout.php" class="logout">🚪 Logout</a>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main">
    <div class="header">
      <div class="header-title">
        <h1><i class="bi bi-gear-fill" style="color:#667eea;"></i> Employee Settings</h1>
        <p>Configure daily working hours, monthly casual leave (CL), and specific check-in days for each employee in <strong><?= htmlspecialchars($adminBranchName) ?></strong> branch.</p>
      </div>
      <div>
        <a href="create_employee.php" class="btn-create"><i class="bi bi-person-plus-fill"></i> Add New Employee</a>
      </div>
    </div>

    <!-- STATS SUMMARY -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
        <div class="stat-info">
          <h4>Total Employees</h4>
          <p><?= $totalEmps ?></p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple"><i class="bi bi-clock-history"></i></div>
        <div class="stat-info">
          <h4>Working Hours</h4>
          <p>Configurable</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon teal"><i class="bi bi-calendar-check-fill"></i></div>
        <div class="stat-info">
          <h4>Monthly CL</h4>
          <p>Per-Employee</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon amber"><i class="bi bi-calendar-week-fill"></i></div>
        <div class="stat-info">
          <h4>Check-in Days</h4>
          <p>Specific Days</p>
        </div>
      </div>
    </div>

    <!-- BULK APPLY CARD -->
    <div class="bulk-card">
      <div class="bulk-header">
        <h3><i class="bi bi-sliders"></i> Apply Default Settings to All Employees</h3>
        <span style="font-size:12.5px; color:#6b7280;">Apply standard hours, CL, and check-in days across all branch employees</span>
      </div>
      <form method="POST" action="employee_settings.php" onsubmit="return confirm('Apply these settings to ALL employees in <?= htmlspecialchars($adminBranchName) ?> branch?')">
        <input type="hidden" name="action" value="update_bulk">
        <input type="hidden" name="bulk_check_in_days" id="bulkCheckInDaysInput" value="Mon,Tue,Wed,Thu,Fri,Sat">
        <div class="bulk-form-grid">
          <div class="form-group">
            <label>Daily Working Hours (hrs)</label>
            <input type="number" name="bulk_working_hours" value="8.0" step="0.5" min="1" max="24" required>
          </div>
          <div class="form-group">
            <label>Monthly CL Allowance (days)</label>
            <input type="number" name="bulk_monthly_cl" value="2.0" step="0.5" min="0" max="31" required>
          </div>
          <div class="form-group" style="grid-column: span 2;">
            <label>Specific Check-in Days</label>
            <div class="days-selector" id="bulkDaysSelector">
              <?php foreach ($validDaysList as $day): ?>
                <div class="day-pill <?= in_array($day, ['Mon','Tue','Wed','Thu','Fri','Sat']) ? 'selected' : '' ?>" data-day="<?= $day ?>" onclick="toggleBulkDay(this)">
                  <?= $day ?>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="quick-presets">
              <span style="font-size:11.5px; color:#6b7280; margin-right:4px;">Presets:</span>
              <button type="button" class="btn-preset" onclick="setBulkPreset('mon-sat')">Mon - Sat (6 Days)</button>
              <button type="button" class="btn-preset" onclick="setBulkPreset('mon-fri')">Mon - Fri (5 Days)</button>
              <button type="button" class="btn-preset" onclick="setBulkPreset('all')">All Days (Mon - Sun)</button>
            </div>
          </div>
          <div>
            <button type="submit" class="btn-bulk-apply"><i class="bi bi-check2-all"></i> Apply to All</button>
          </div>
        </div>
      </form>
    </div>

    <!-- EMPLOYEES TABLE CARD -->
    <div class="card">
      <div class="card-header-flex">
        <h3>Employee Configuration List (<?= $totalEmps ?>)</h3>
        <div class="search-box">
          <i class="bi bi-search"></i>
          <input type="text" id="employeeSearchInput" placeholder="Search by name or employee ID..." onkeyup="filterEmployees()">
        </div>
      </div>

      <div class="table-responsive">
        <table id="employeeSettingsTable">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Employee ID</th>
              <th>Working Hours / Day</th>
              <th>Monthly CL</th>
              <th>Specific Check-in Days</th>
              <th style="text-align:center;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($totalEmps === 0): ?>
              <tr>
                <td colspan="6" style="text-align:center; padding:30px; color:#6b7280;">
                  No employees found in this branch yet. <a href="create_employee.php" style="color:#667eea; font-weight:600;">Create one now</a>.
                </td>
              </tr>
            <?php else: ?>
              <?php while ($emp = $employeesList->fetch_assoc()): 
                $initial = strtoupper(substr($emp['name'], 0, 1));
                $hours = isset($emp['working_hours']) ? (float)$emp['working_hours'] : 8.00;
                $cl = isset($emp['monthly_cl']) ? (float)$emp['monthly_cl'] : 2.00;
                $checkInDays = !empty($emp['check_in_days']) ? $emp['check_in_days'] : 'Mon,Tue,Wed,Thu,Fri,Sat';
                $daysDisplay = formatCheckInDaysDisplay($checkInDays, $adminBranchName);
              ?>
                <tr id="row-<?= $emp['id'] ?>" data-search="<?= strtolower(htmlspecialchars($emp['name'] . ' ' . $emp['employee_id'])) ?>">
                  <td>
                    <div class="emp-cell">
                      <div class="emp-avatar"><?= $initial ?></div>
                      <div>
                        <div class="emp-name"><?= htmlspecialchars($emp['name']) ?></div>
                        <div class="emp-id-sub"><?= htmlspecialchars($emp['employee_id']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td><strong><?= htmlspecialchars($emp['employee_id']) ?></strong></td>
                  <td>
                    <span class="badge badge-hours" id="display-hours-<?= $emp['id'] ?>">
                      <i class="bi bi-clock"></i> <?= number_format($hours, 1) ?> hrs/day
                    </span>
                  </td>
                  <td>
                    <span class="badge badge-cl" id="display-cl-<?= $emp['id'] ?>">
                      <i class="bi bi-calendar-check"></i> <?= number_format($cl, 1) ?> days
                    </span>
                  </td>
                  <td>
                    <span class="badge badge-days" id="display-days-<?= $emp['id'] ?>">
                      <i class="bi bi-calendar-week"></i> <?= htmlspecialchars($daysDisplay) ?>
                    </span>
                  </td>
                  <td style="text-align:center;">
                    <button type="button" class="btn-edit" onclick='openSettingsModal(<?= json_encode($emp) ?>)'>
                      <i class="bi bi-pencil-square"></i> Set Up
                    </button>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div id="settingsModal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h3><i class="bi bi-sliders"></i> Edit Employee Settings</h3>
      <button type="button" class="modal-close" onclick="closeSettingsModal()">&times;</button>
    </div>
    <form id="settingsForm" onsubmit="handleSettingsSubmit(event)">
      <input type="hidden" name="action" value="update_single">
      <input type="hidden" name="id" id="modalEmpId">
      <input type="hidden" name="check_in_days" id="modalCheckInDaysInput">

      <div class="modal-body">
        <div class="modal-emp-info">
          <div>
            <div class="name" id="modalEmpName">-</div>
            <div class="id" id="modalEmpCode">-</div>
          </div>
          <span style="font-size:12px; background:#e0e7ff; color:#3730a3; padding:4px 10px; border-radius:12px; font-weight:600;">Employee</span>
        </div>

        <div class="form-group" style="margin-bottom:18px;">
          <label><i class="bi bi-clock"></i> Daily Working Hours</label>
          <input type="number" name="working_hours" id="modalWorkingHours" step="0.5" min="1" max="24" required>
          <div class="form-hint">Standard daily shift duration (e.g., 8.0, 9.0 hours).</div>
        </div>

        <div class="form-group" style="margin-bottom:18px;">
          <label><i class="bi bi-calendar-check"></i> Monthly Casual Leaves (CL)</label>
          <input type="number" name="monthly_cl" id="modalMonthlyCL" step="0.5" min="0" max="31" required>
          <div class="form-hint">Allowed paid casual leaves per month (e.g., 2.0, 1.5 days).</div>
        </div>

        <div class="form-group" style="margin-bottom:18px;">
          <label><i class="bi bi-calendar-week"></i> Specific Check-in Days</label>
          <div class="days-selector" id="modalDaysSelector">
            <?php foreach ($validDaysList as $day): ?>
              <div class="day-pill" data-day="<?= $day ?>" onclick="toggleModalDay(this)">
                <?= $day ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="quick-presets">
            <span style="font-size:11.5px; color:#6b7280; margin-right:4px;">Presets:</span>
            <button type="button" class="btn-preset" onclick="setModalPreset('mon-sat')">Mon - Sat (6 Days)</button>
            <button type="button" class="btn-preset" onclick="setModalPreset('mon-fri')">Mon - Fri (5 Days)</button>
            <button type="button" class="btn-preset" onclick="setModalPreset('all')">All Days (Mon - Sun)</button>
          </div>
          <div class="form-hint">Select the specific days of the week on which this employee must check in.</div>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeSettingsModal()">Cancel</button>
          <button type="submit" class="btn-save" id="saveBtn"><i class="bi bi-check-lg"></i> Save Settings</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toastBox">
  <i class="bi bi-check-circle-fill" id="toastIcon"></i>
  <span id="toastText">Settings saved successfully!</span>
</div>

<script>
const allDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function showToast(message, isError = false) {
  const toast = document.getElementById("toastBox");
  const text = document.getElementById("toastText");
  const icon = document.getElementById("toastIcon");

  text.textContent = message;
  toast.className = isError ? "toast error show" : "toast show";
  icon.className = isError ? "bi bi-exclamation-circle-fill" : "bi bi-check-circle-fill";

  setTimeout(() => {
    toast.classList.remove("show");
  }, 3500);
}

function filterEmployees() {
  const query = document.getElementById("employeeSearchInput").value.toLowerCase().trim();
  const rows = document.querySelectorAll("#employeeSettingsTable tbody tr[data-search]");
  rows.forEach(row => {
    const text = row.getAttribute("data-search");
    row.style.display = (query === "" || text.includes(query)) ? "" : "none";
  });
}

// Bulk day toggling
function toggleBulkDay(el) {
  el.classList.toggle('selected');
  updateBulkInput();
}

function updateBulkInput() {
  const selected = [];
  document.querySelectorAll('#bulkDaysSelector .day-pill.selected').forEach(pill => {
    selected.push(pill.getAttribute('data-day'));
  });
  document.getElementById('bulkCheckInDaysInput').value = selected.join(',');
}

function setBulkPreset(preset) {
  const pills = document.querySelectorAll('#bulkDaysSelector .day-pill');
  pills.forEach(pill => {
    const d = pill.getAttribute('data-day');
    if (preset === 'all') {
      pill.classList.add('selected');
    } else if (preset === 'mon-sat') {
      pill.classList.toggle('selected', d !== 'Sun');
    } else if (preset === 'mon-fri') {
      pill.classList.toggle('selected', d !== 'Sat' && d !== 'Sun');
    }
  });
  updateBulkInput();
}

// Modal day toggling
function toggleModalDay(el) {
  el.classList.toggle('selected');
  updateModalDaysInput();
}

function updateModalDaysInput() {
  const selected = [];
  document.querySelectorAll('#modalDaysSelector .day-pill.selected').forEach(pill => {
    selected.push(pill.getAttribute('data-day'));
  });
  document.getElementById('modalCheckInDaysInput').value = selected.join(',');
}

function setModalPreset(preset) {
  const pills = document.querySelectorAll('#modalDaysSelector .day-pill');
  pills.forEach(pill => {
    const d = pill.getAttribute('data-day');
    if (preset === 'all') {
      pill.classList.add('selected');
    } else if (preset === 'mon-sat') {
      pill.classList.toggle('selected', d !== 'Sun');
    } else if (preset === 'mon-fri') {
      pill.classList.toggle('selected', d !== 'Sat' && d !== 'Sun');
    }
  });
  updateModalDaysInput();
}

function openSettingsModal(emp) {
  document.getElementById("modalEmpId").value = emp.id;
  document.getElementById("modalEmpName").textContent = emp.name || 'Employee';
  document.getElementById("modalEmpCode").textContent = 'ID: ' + (emp.employee_id || '-');
  document.getElementById("modalWorkingHours").value = emp.working_hours !== null && emp.working_hours !== undefined ? parseFloat(emp.working_hours) : 8.0;
  document.getElementById("modalMonthlyCL").value = emp.monthly_cl !== null && emp.monthly_cl !== undefined ? parseFloat(emp.monthly_cl) : 2.0;

  // Selected days
  let daysStr = emp.check_in_days || 'Mon,Tue,Wed,Thu,Fri,Sat';
  let daysArr = daysStr.split(',').map(s => s.trim());
  document.querySelectorAll('#modalDaysSelector .day-pill').forEach(pill => {
    const d = pill.getAttribute('data-day');
    pill.classList.toggle('selected', daysArr.includes(d));
  });
  updateModalDaysInput();

  document.getElementById("settingsModal").style.display = "flex";
}

function closeSettingsModal() {
  document.getElementById("settingsModal").style.display = "none";
}

window.addEventListener('click', function(e) {
  const modal = document.getElementById("settingsModal");
  if (e.target === modal) {
    closeSettingsModal();
  }
});

function handleSettingsSubmit(e) {
  e.preventDefault();
  const form = document.getElementById("settingsForm");
  const formData = new FormData(form);
  const saveBtn = document.getElementById("saveBtn");
  const empId = document.getElementById("modalEmpId").value;

  // Ensure at least 1 day selected
  const daysVal = document.getElementById('modalCheckInDaysInput').value.trim();
  if (!daysVal) {
    showToast("Please select at least one check-in day.", true);
    return;
  }

  saveBtn.disabled = true;
  saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';

  fetch("employee_settings.php", {
    method: "POST",
    headers: {
      "X-Requested-With": "XMLHttpRequest"
    },
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    saveBtn.disabled = false;
    saveBtn.innerHTML = '<i class="bi bi-check-lg"></i> Save Settings';

    if (data.status === 'success') {
      showToast(data.message, false);
      closeSettingsModal();

      // Update row in table immediately
      const hBadge = document.getElementById("display-hours-" + empId);
      const clBadge = document.getElementById("display-cl-" + empId);
      const dBadge = document.getElementById("display-days-" + empId);

      if (hBadge) hBadge.innerHTML = '<i class="bi bi-clock"></i> ' + data.working_hours + ' hrs/day';
      if (clBadge) clBadge.innerHTML = '<i class="bi bi-calendar-check"></i> ' + data.monthly_cl + ' days';
      if (dBadge) dBadge.innerHTML = '<i class="bi bi-calendar-week"></i> ' + data.check_in_days_display;

      const row = document.getElementById("row-" + empId);
      if (row) {
        const btn = row.querySelector(".btn-edit");
        if (btn) {
          const currentEmp = {
            id: empId,
            name: document.getElementById("modalEmpName").textContent,
            employee_id: document.getElementById("modalEmpCode").textContent.replace('ID: ', ''),
            working_hours: data.working_hours,
            monthly_cl: data.monthly_cl,
            check_in_days: data.check_in_days
          };
          btn.setAttribute("onclick", `openSettingsModal(${JSON.stringify(currentEmp)})`);
        }
      }
    } else {
      showToast(data.message || "Failed to update settings.", true);
    }
  })
  .catch(err => {
    saveBtn.disabled = false;
    saveBtn.innerHTML = '<i class="bi bi-check-lg"></i> Save Settings';
    console.error(err);
    showToast("An error occurred while saving.", true);
  });
}
</script>

<?php if (isset($_SESSION['flash_success'])): ?>
<script>
  window.addEventListener('DOMContentLoaded', () => {
    showToast(<?= json_encode($_SESSION['flash_success']) ?>, false);
  });
</script>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
<script>
  window.addEventListener('DOMContentLoaded', () => {
    showToast(<?= json_encode($_SESSION['flash_error']) ?>, true);
  });
</script>
<?php unset($_SESSION['flash_error']); endif; ?>

</body>
</html>
