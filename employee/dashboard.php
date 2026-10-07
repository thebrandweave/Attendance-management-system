<?php
session_start();
include("../config/db.php");
require_once "../config/branch_helper.php";

ensureEmployeeSettingsColumns($conn);

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit();
}

$user = $_SESSION['user'];
$userId = (int)$user['id'];

// Refresh user record from DB for live settings
$uStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->bind_param("i", $userId);
$uStmt->execute();
$freshUser = $uStmt->get_result()->fetch_assoc();
if ($freshUser) {
    $user = $freshUser;
    $_SESSION['user'] = array_merge($_SESSION['user'], $freshUser);
}
$uStmt->close();

/* =======================
   QR CODE SETUP & UPLOAD HANDLER
======================= */
// Ensure employee has a valid qr_token in DB
if (empty($user['qr_token'])) {
    $newToken = bin2hex(random_bytes(32));
    $conn->query("UPDATE users SET qr_token = '$newToken' WHERE id = $userId");
    $user['qr_token'] = $newToken;
    $_SESSION['user']['qr_token'] = $newToken;
}

// Handle QR Code Upload (AJAX or form POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (isset($_POST['upload_qr_action']) || isset($_FILES['qr_image_file']))) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (!isset($_FILES['qr_image_file']) || $_FILES['qr_image_file']['error'] !== UPLOAD_ERR_OK) {
        $errMsg = "Please select a valid image file to upload.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit();
        }
        $_SESSION['flash_error'] = $errMsg;
        header("Location: dashboard.php");
        exit();
    }

    $file = $_FILES['qr_image_file'];
    $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts)) {
        $errMsg = "Invalid file type. Allowed formats: PNG, JPG, JPEG, WEBP, SVG.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit();
        }
        $_SESSION['flash_error'] = $errMsg;
        header("Location: dashboard.php");
        exit();
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        $errMsg = "File is too large. Maximum size is 5MB.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit();
        }
        $_SESSION['flash_error'] = $errMsg;
        header("Location: dashboard.php");
        exit();
    }

    $targetDir = __DIR__ . '/../uploads/qr_codes/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $cleanEmpCode = preg_replace('/[^a-zA-Z0-9_-]/', '', $user['employee_id'] ?? ('user_' . $userId));
    $fileName = $cleanEmpCode . '_qr_' . time() . '.' . $ext;
    $targetPath = $targetDir . $fileName;
    $dbRelativePath = 'uploads/qr_codes/' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Remove old custom file if it exists
        if (!empty($user['qr_code_image'])) {
            $oldFilePath = __DIR__ . '/../' . ltrim($user['qr_code_image'], '/');
            if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                @unlink($oldFilePath);
            }
        }

        $upStmt = $conn->prepare("UPDATE users SET qr_code_image = ? WHERE id = ?");
        $upStmt->bind_param("si", $dbRelativePath, $userId);
        $upStmt->execute();
        $upStmt->close();

        $user['qr_code_image'] = $dbRelativePath;
        $_SESSION['user']['qr_code_image'] = $dbRelativePath;

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'QR Code uploaded successfully! ✅',
                'qr_url' => '../' . $dbRelativePath
            ]);
            exit();
        }
        $_SESSION['flash_success'] = "QR Code uploaded successfully! ✅";
        header("Location: dashboard.php");
        exit();
    } else {
        $errMsg = "Failed to save uploaded file. Please check server permissions.";
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit();
        }
        $_SESSION['flash_error'] = $errMsg;
        header("Location: dashboard.php");
        exit();
    }
}

// Compute QR image source
$qrCheckinLink = "https://thebrandweave.com/attendance/api/checkin.php?token=" . urlencode($user['qr_token'] ?? '');
$customQrPath = !empty($user['qr_code_image']) ? $user['qr_code_image'] : null;
$hasCustomQr = false;
$qrDisplaySrc = "";

if ($customQrPath && file_exists(__DIR__ . '/../' . ltrim($customQrPath, '/'))) {
    $qrDisplaySrc = '../' . ltrim($customQrPath, '/');
    $hasCustomQr = true;
} else {
    // Standard Auto-generated Check-in QR for future new employees & existing ones
    $qrDisplaySrc = "https://quickchart.io/qr?size=220&text=" . urlencode($qrCheckinLink);
}

$userBranchId = (int)($user['branch_id'] ?? 0);
$userBranchStr = trim($user['branch'] ?? '');

// Fetch dynamic branch configuration from branches table
$bStmt = $conn->prepare("SELECT * FROM branches WHERE id = ? OR LOWER(branch_name) = LOWER(?) LIMIT 1");
$bStmt->bind_param("is", $userBranchId, $userBranchStr);
$bStmt->execute();
$branchData = $bStmt->get_result()->fetch_assoc();
$bStmt->close();

$branchName = !empty($branchData['branch_name']) ? $branchData['branch_name'] : (!empty($userBranchStr) ? $userBranchStr : 'gdedutech');
$userBranch = strtolower(trim($branchName));
$attTable = getBranchTableNameOnly($conn, $userBranch);
$today = date("Y-m-d");

$isThirthahalliBranch = (
    strtolower(trim($branchName)) === "thirthahalli" ||
    $userBranchId === 6 ||
    stripos($branchName, 'thirthahalli') !== false
);

$isMudipuBranch = (
    strtolower(trim($branchName)) === "mudipu" ||
    $userBranchId === 2 ||
    stripos($branchName, 'mudipu') !== false
);

// Determine Branch-level standard shift timings & defaults
if ($isThirthahalliBranch) {
    $branchStdStart = !empty($branchData['standard_check_in']) ? $branchData['standard_check_in'] : '10:00:00';
    $branchStdEnd   = !empty($branchData['standard_check_out']) ? $branchData['standard_check_out'] : '20:00:00';
    $branchStdHours = !empty($branchData['full_day_hours']) ? (float)$branchData['full_day_hours'] : 10.0;
} elseif ($isMudipuBranch) {
    $branchStdStart = !empty($branchData['standard_check_in']) ? $branchData['standard_check_in'] : '09:30:00';
    $branchStdEnd   = !empty($branchData['standard_check_out']) ? $branchData['standard_check_out'] : '17:30:00';
    $branchStdHours = !empty($branchData['full_day_hours']) ? (float)$branchData['full_day_hours'] : 8.0;
} else {
    // GD Edu Tech / Main
    $branchStdStart = !empty($branchData['standard_check_in']) ? $branchData['standard_check_in'] : '09:30:00';
    $branchStdEnd   = !empty($branchData['standard_check_out']) ? $branchData['standard_check_out'] : '17:30:00';
    $branchStdHours = !empty($branchData['full_day_hours']) ? (float)$branchData['full_day_hours'] : 8.0;
}

// Effective shift timings:
// Priority 1: Employee-specific settings in users table (if set and customized)
// Priority 2: Branch standard settings
$officeStartTime = !empty($user['shift_start']) ? $user['shift_start'] : $branchStdStart;
$officeEndTime   = !empty($user['shift_end']) ? $user['shift_end'] : $branchStdEnd;

// Effective employee settings:
$empWorkingHours = !empty($user['working_hours']) ? (float)$user['working_hours'] : $branchStdHours;
$empCheckInDays = getEmployeeCheckInDaysArray($user['check_in_days'] ?? '', $branchName);
$sundayIsWorking = in_array('Sun', $empCheckInDays, true);

$sundayDateFilter = $sundayIsWorking
    ? ""
    : "AND DAYOFWEEK(date) != 1";

$sundayHistoryFilter = $sundayIsWorking
    ? ""
    : "AND DAYOFWEEK(a.date) != 1";

/* =======================
   MONTH FILTER
   Thirthahalli: 1st -> end of month
   Other branches: 21st -> 20th
======================= */
if (!empty($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    $selectedMonth = $_GET['month'];
} else {
    if ($isThirthahalliBranch) {
        $selectedMonth = date('Y-m');
    } else {
        $selectedMonth = ((int)date('d') >= 21)
            ? date('Y-m', strtotime('+1 month'))
            : date('Y-m');
    }
}

if ($isThirthahalliBranch) {
    $monthStartDate = date('Y-m-01', strtotime($selectedMonth . '-01'));
    $monthEndDate   = date('Y-m-t', strtotime($selectedMonth . '-01'));
    $selectedMonthLabel = date('F Y', strtotime($selectedMonth . '-01'));
} else {
    $monthStartDate = date('Y-m-21', strtotime('-1 month', strtotime($selectedMonth . '-01')));
    $monthEndDate   = date('Y-m-20', strtotime($selectedMonth . '-01'));
    $selectedMonthLabel = date('d M Y', strtotime($monthStartDate)) . ' - ' . date('d M Y', strtotime($monthEndDate));
}

/* =======================
   MONTHLY STATUS SUMMARY
====================== */
$monthlySummaryStmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS total_present,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) AS total_late,
        SUM(CASE WHEN status IN ('Half Day','Half Day PL','Half Day Absent') THEN 1 ELSE 0 END) AS total_half,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS total_absent,
        SUM(CASE WHEN status = 'PL' THEN 1 WHEN status = 'Half Day PL' THEN 0.5 ELSE 0 END) AS total_pl,
        SUM(CASE WHEN status = 'CL' THEN 1 ELSE 0 END) AS total_cl,
        SUM(CASE WHEN status = 'Overtime' THEN 1 ELSE 0 END) AS total_overtime,
        SUM(CASE WHEN status = 'Overtime Pending' THEN 1 ELSE 0 END) AS total_overtime_pending
    FROM `$attTable`
    WHERE user_id = ?
      AND date BETWEEN ? AND ?
      " . ($sundayIsWorking ? "" : "AND DAYOFWEEK(date) != 1") . "
");

$monthlySummaryStmt->bind_param('iss', $userId, $monthStartDate, $monthEndDate);
$monthlySummaryStmt->execute();
$monthlySummary = $monthlySummaryStmt->get_result()->fetch_assoc() ?: [];
$monthlySummaryStmt->close();

$totalPresentDays   = (float)($monthlySummary['total_present'] ?? 0);
$totalLateDays      = (float)($monthlySummary['total_late'] ?? 0);
$totalHalfDays      = (float)($monthlySummary['total_half'] ?? 0);
$totalAbsentDays    = (float)($monthlySummary['total_absent'] ?? 0);
$totalPLDays        = (float)($monthlySummary['total_pl'] ?? 0);
$totalCLDays        = (float)($monthlySummary['total_cl'] ?? 0);
$totalOvertimeDays  = (float)($monthlySummary['total_overtime'] ?? 0);
$totalOTPendingDays = (float)($monthlySummary['total_overtime_pending'] ?? 0);

// Cap Monthly CL at limit (employee settings -> branch monthly_leaves -> default 2.0) and transfer excess to Absent
$empMonthlyCLLimit = (isset($user['monthly_cl']) && $user['monthly_cl'] !== null)
    ? (float)$user['monthly_cl']
    : (!empty($branchData['monthly_leaves']) ? (float)$branchData['monthly_leaves'] : 2.0);

if ($totalPLDays > $empMonthlyCLLimit) {
    $excessPL = $totalPLDays - $empMonthlyCLLimit;
    $totalAbsentDays += $excessPL;
    $totalPLDays = $empMonthlyCLLimit;
} else {
    $remainingCL = $empMonthlyCLLimit - $totalPLDays;
    $clCovered = min($remainingCL, $totalAbsentDays);
    $totalPLDays += $clCovered;
    $totalAbsentDays -= $clCovered;
}

/* =======================
   TODAY ATTENDANCE
======================= */
$attRes = $conn->query("
  SELECT * FROM `$attTable` 
  WHERE user_id = $userId AND date = '$today'
");
$attendance = ($attRes && $attRes->num_rows > 0) ? $attRes->fetch_assoc() : null;

$todayWorked = "-";

if ($attendance && !empty($attendance['check_in'])) {
    $startTime = strtotime($attendance['check_in']);

    if (!empty($attendance['check_out'])) {
        $endTime = strtotime($attendance['check_out']);
    } else {
        $endTime = time(); // current time if still working
    }

    $totalSeconds = max(0, $endTime - $startTime);

    // subtract lunch time if taken
    $lunchSeconds = 0;
    if (!empty($attendance['lunch_out']) && !empty($attendance['lunch_in'])) {
        $lunchSeconds = max(0, strtotime($attendance['lunch_in']) - strtotime($attendance['lunch_out']));
    }

    $finalSeconds = max(0, $totalSeconds - $lunchSeconds);
    $hours = floor($finalSeconds / 3600);
    $minutes = floor(($finalSeconds % 3600) / 60);

    $todayWorked = $hours . "h " . $minutes . "m";
}

/* =======================
   HISTORY
======================= */
$allAttendance = $conn->query("
  SELECT 
    u.employee_id,
    a.date,
    a.check_in,
    a.check_out,
    a.status,
    a.lunch_out,
    a.lunch_in,
    cl.title AS leave_title
  FROM `$attTable` a
  INNER JOIN users u
      ON a.user_id = u.id
  LEFT JOIN company_leaves cl
      ON cl.leave_date = a.date AND (cl.branch_id = u.branch_id OR cl.branch = u.branch)
  WHERE a.user_id = $userId
    AND a.date BETWEEN '$monthStartDate' AND '$monthEndDate'
    $sundayHistoryFilter
  ORDER BY a.date DESC
");

/* =======================
   LEAVES
======================= */
$leaves = $conn->query("
  SELECT * FROM leave_requests 
  WHERE employee_id = $userId
    AND date BETWEEN '$monthStartDate' AND '$monthEndDate'
  ORDER BY date DESC
");

/* =======================
   COMPANY LEAVES
======================= */
$empBranchId = (int)($user['branch_id'] ?? 0);
$empBranchStr = $conn->real_escape_string($user['branch'] ?? '');

$companyLeaves = $conn->query("
    SELECT *
    FROM company_leaves
    WHERE (branch_id = $empBranchId OR branch = '$empBranchStr')
      AND leave_date BETWEEN '$monthStartDate' AND '$monthEndDate'
    ORDER BY leave_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Employee Dashboard - <?= htmlspecialchars($user['name']) ?></title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background: #eef2f7;
      color: #334155;
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
    }

    /* =========================
       LAYOUT
    ========================= */
    .layout {
      display: flex;
      min-height: 100vh;
    }

    /* =========================
       MOBILE TOP BAR (STICKY)
    ========================= */
    .mobile-top-bar {
      display: none;
      background: #111827;
      color: white;
      padding: 14px 20px;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
    }

    .mobile-top-bar .bar-title {
      font-size: 16px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .hamburger-btn {
      background: none;
      border: none;
      color: white;
      font-size: 24px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 6px;
      border-radius: 6px;
      transition: background 0.2s;
    }

    .hamburger-btn:hover, .hamburger-btn:focus {
      background: rgba(255, 255, 255, 0.1);
      outline: none;
    }

    /* =========================
       SIDEBAR
    ========================= */
    .sidebar {
      width: 260px;
      background: linear-gradient(180deg, #111827, #1f2937);
      color: white;
      padding: 24px;
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      display: flex;
      flex-direction: column;
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 1001;
      overflow-y: auto;
      box-sizing: border-box;
    }

    .sidebar-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 25px;
      padding-bottom: 12px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-header h2 {
      font-size: 19px;
      font-weight: 600;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 0;
    }

    .sidebar-close-btn {
      display: none;
      background: none;
      border: none;
      color: #94a3b8;
      font-size: 26px;
      cursor: pointer;
      line-height: 1;
      padding: 4px;
      border-radius: 6px;
    }

    .sidebar-close-btn:hover {
      color: white;
      background: rgba(255, 255, 255, 0.1);
    }

    .sidebar-nav {
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;
    }

    .sidebar a {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      color: #cbd5e1;
      text-decoration: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.25s ease;
    }

    .sidebar a:hover {
      background: rgba(255, 255, 255, 0.08);
      color: white;
      transform: translateX(4px);
    }

    .sidebar a.active {
      background: #4f46e5;
      color: white;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
    }

    .sidebar .logout {
      background: #ef4444;
      color: white;
      margin-top: auto;
      justify-content: center;
      font-weight: 600;
    }

    .sidebar .logout:hover {
      background: #dc2626;
      transform: none;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    /* Overlay for Mobile Navigation drawer */
    .sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(2px);
      z-index: 1000;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
      display: block;
      opacity: 1;
      pointer-events: auto;
    }

    /* =========================
       MAIN CONTENT
    ========================= */
    .main {
      flex: 1;
      margin-left: 260px;
      width: calc(100% - 260px);
      padding: 35px 40px;
      box-sizing: border-box;
      min-width: 0;
    }

    /* =========================
       WELCOME BANNER WITH QR CODE
    ========================= */
    .welcome-banner-card {
      background: white;
      border-radius: 16px;
      padding: 24px 28px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
      border: 1px solid #eef2f6;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 24px;
      flex-wrap: wrap;
    }

    .welcome-banner-main {
      flex: 1;
      min-width: 260px;
    }

    .welcome-role-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #eff6ff;
      color: #3b82f6;
      font-size: 12px;
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 20px;
      margin-bottom: 8px;
    }

    .welcome-banner-main h1 {
      margin-bottom: 6px;
      color: #111827;
      font-size: 22px;
      font-weight: 700;
      letter-spacing: -0.3px;
    }

    .emp-id-text {
      color: #64748b;
      font-size: 13.5px;
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .emp-badge-tag {
      background: #e2e8f0;
      color: #334155;
      padding: 3px 9px;
      border-radius: 6px;
      font-size: 12.5px;
      font-weight: 600;
    }

    /* QR CARD WIDGET */
    .welcome-qr-card {
      display: flex;
      align-items: center;
      gap: 14px;
      background: linear-gradient(135deg, #f8fafc, #f1f5f9);
      border: 1.5px solid #e2e8f0;
      border-radius: 14px;
      padding: 14px 18px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
      flex-shrink: 0;
      max-width: 100%;
      box-sizing: border-box;
      transition: all 0.25s ease;
    }

    .qr-image-frame {
      width: 95px;
      height: 95px;
      flex-shrink: 0;
      background: white;
      border-radius: 10px;
      padding: 6px;
      border: 1px solid #cbd5e1;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      cursor: pointer;
      overflow: hidden;
      box-shadow: 0 2px 6px rgba(0,0,0,0.06);
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .qr-image-frame:hover {
      transform: scale(1.03);
      box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
    }

    .qr-image-frame img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      display: block;
      border-radius: 6px;
    }

    .qr-hover-zoom {
      position: absolute;
      inset: 0;
      background: rgba(17, 24, 39, 0.45);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      opacity: 0;
      transition: opacity 0.2s;
      border-radius: 6px;
    }

    .qr-image-frame:hover .qr-hover-zoom {
      opacity: 1;
    }

    .qr-info-meta {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .qr-meta-top {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .qr-meta-title {
      font-size: 13.5px;
      font-weight: 700;
      color: #0f172a;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .qr-type-badge {
      font-size: 10.5px;
      font-weight: 600;
      padding: 2px 7px;
      border-radius: 12px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .badge-auto {
      background: #e0e7ff;
      color: #4338ca;
    }

    .badge-custom {
      background: #dcfce7;
      color: #15803d;
    }

    .qr-meta-desc {
      font-size: 12px;
      color: #64748b;
      margin: 0;
    }

    .qr-btn-group {
      display: flex;
      gap: 8px;
      margin-top: 6px;
    }

    .btn-qr-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      border: none;
      transition: all 0.2s;
      font-family: inherit;
      text-decoration: none;
      line-height: 1.2;
    }

    .btn-upload {
      background: #4f46e5;
      color: white;
    }

    .btn-upload:hover {
      background: #4338ca;
      transform: translateY(-1px);
    }

    .btn-download {
      background: #f1f5f9;
      color: #334155;
      border: 1px solid #cbd5e1;
    }

    .btn-download:hover {
      background: #e2e8f0;
      color: #0f172a;
      transform: translateY(-1px);
    }

    /* QR MODAL STYLES */
    .qr-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.7);
      backdrop-filter: blur(4px);
      z-index: 10000;
      align-items: center;
      justify-content: center;
      padding: 20px;
      box-sizing: border-box;
      opacity: 0;
      transition: opacity 0.25s ease;
    }

    .qr-modal-overlay.active {
      display: flex;
      opacity: 1;
    }

    .qr-modal-dialog {
      background: white;
      border-radius: 20px;
      max-width: 380px;
      width: 100%;
      padding: 28px 24px;
      text-align: center;
      position: relative;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      transform: scale(0.95);
      transition: transform 0.25s ease;
    }

    .qr-modal-overlay.active .qr-modal-dialog {
      transform: scale(1);
    }

    .qr-modal-close {
      position: absolute;
      top: 14px;
      right: 18px;
      background: none;
      border: none;
      font-size: 26px;
      color: #94a3b8;
      cursor: pointer;
      line-height: 1;
      padding: 4px;
      border-radius: 6px;
      transition: color 0.2s;
    }

    .qr-modal-close:hover {
      color: #0f172a;
    }

    .qr-modal-header h3 {
      font-size: 18px;
      font-weight: 700;
      color: #0f172a;
      margin: 0 0 4px 0;
    }

    .qr-modal-header p {
      font-size: 12.5px;
      color: #64748b;
      margin: 0 0 16px 0;
    }

    .qr-modal-img-wrap {
      background: white;
      border: 2px solid #e2e8f0;
      border-radius: 16px;
      padding: 16px;
      display: inline-block;
      margin-bottom: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }

    .qr-modal-img-wrap img {
      width: 220px;
      height: 220px;
      object-fit: contain;
      display: block;
    }

    .qr-modal-hint {
      font-size: 12px;
      color: #64748b;
      margin: 0 0 18px 0;
    }

    .qr-modal-footer {
      display: flex;
      justify-content: center;
      gap: 10px;
    }

    /* =========================
       MONTH FILTER CARD
    ========================= */
    .month-filter-card {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
      margin-bottom: 24px;
      background: white;
      padding: 18px 22px;
      border-radius: 12px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
      border: 1px solid #f1f5f9;
    }

    .month-filter-card .month-filter-title {
      font-size: 13px;
      color: #64748b;
      font-weight: 500;
      margin-bottom: 2px;
    }

    .month-filter-card .month-filter-value {
      font-size: 16px;
      color: #0f172a;
      font-weight: 600;
    }

    .month-filter-card form {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }

    .month-filter-card select {
      padding: 10px 14px;
      border-radius: 8px;
      border: 1px solid #cbd5e1;
      font-family: inherit;
      font-size: 14px;
      background: white;
      color: #334155;
      cursor: pointer;
      outline: none;
      transition: border 0.2s, box-shadow 0.2s;
    }

    .month-filter-card select:focus {
      border-color: #4f46e5;
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }

    .month-filter-card button {
      padding: 10px 18px;
      background: #111827;
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 500;
      font-size: 14px;
      cursor: pointer;
      transition: background 0.2s;
    }

    .month-filter-card button:hover {
      background: #1f2937;
    }

    /* =========================
       STATISTICS GRID
    ========================= */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 14px;
      margin-bottom: 24px;
    }

    .stat-card {
      padding: 16px 18px;
      border-radius: 12px;
      color: white;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 6px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      min-height: 86px;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
    }

    .stat-card .stat-label {
      font-size: 12px;
      font-weight: 500;
      opacity: 0.92;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .stat-card .stat-val {
      font-size: 22px;
      font-weight: 700;
      line-height: 1.1;
    }

    .stat-bg-worked   { background: linear-gradient(135deg, #0f766e, #115e59); }
    .stat-bg-present  { background: linear-gradient(135deg, #16a34a, #15803d); }
    .stat-bg-late     { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
    .stat-bg-half     { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .stat-bg-absent   { background: linear-gradient(135deg, #dc2626, #b91c1c); }
    .stat-bg-cl       { background: linear-gradient(135deg, #0d9488, #0f766e); }
    .stat-bg-company  { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
    .stat-bg-ot       { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    .stat-bg-ot-pen   { background: linear-gradient(135deg, #ea580c, #c2410c); }

    /* =========================
       CARDS
    ========================= */
    .card {
      background: white;
      padding: 24px;
      border-radius: 14px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
      margin-bottom: 24px;
      border: 1px solid #f1f5f9;
    }

    .card h2 {
      margin-bottom: 18px;
      color: #111827;
      font-size: 18px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      border-bottom: 2px solid #f1f5f9;
      padding-bottom: 12px;
    }

    /* Warning Notice */
    .warning-box {
      background: #fffbeb;
      color: #b45309;
      padding: 16px;
      border-radius: 10px;
      margin-bottom: 18px;
      border-left: 4px solid #f59e0b;
      font-size: 14px;
      line-height: 1.5;
    }

    /* Attendance Today Info Grid */
    .today-att-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 14px;
      margin-top: 10px;
    }

    .today-att-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 14px 16px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .today-att-box .box-lbl {
      font-size: 12px;
      color: #64748b;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .today-att-box .box-val {
      font-size: 15.5px;
      font-weight: 600;
      color: #0f172a;
    }

    .no-data-msg {
      color: #64748b;
      font-size: 14px;
      text-align: center;
      padding: 20px 0;
    }

    /* =========================
       TABLE STYLING
    ========================= */
    .table-wrapper {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      border-radius: 10px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    th {
      background: #f8fafc;
      color: #475569;
      padding: 13px 16px;
      font-size: 12.5px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid #e2e8f0;
      white-space: nowrap;
    }

    td {
      padding: 13px 16px;
      border-bottom: 1px solid #e2e8f0;
      font-size: 13.5px;
      color: #334155;
      vertical-align: middle;
    }

    tr:hover td {
      background: #f8fafc;
    }

    /* Badges */
    .status-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      color: white;
      display: inline-block;
      white-space: nowrap;
    }
    .status-approved, .status-present { background: #16a34a; }
    .status-rejected, .status-absent   { background: #dc2626; }
    .status-pending                   { background: #f59e0b; }
    .status-late                      { background: #2563eb; }
    .status-halfday, .status-half-day { background: #f59e0b; }

    /* ==================================
       RESPONSIVE DESIGN BREAKPOINTS
    ================================== */
    @media (max-width: 992px) {
      .layout {
        flex-direction: column;
      }

      .mobile-top-bar {
        display: flex;
      }

      .sidebar {
        transform: translateX(-100%);
        width: 280px;
        max-width: 85vw;
        box-shadow: 10px 0 25px rgba(0, 0, 0, 0.3);
      }

      .sidebar.active {
        transform: translateX(0);
      }

      .sidebar-close-btn {
        display: block;
      }

      .sidebar-overlay.active {
        display: block;
      }

      .sidebar .logout {
        margin-top: 30px;
      }

      .main {
        margin-left: 0;
        width: 100%;
        padding: 20px 16px;
      }

      .welcome-banner-card {
        padding: 20px 22px;
        gap: 18px;
      }

      .welcome-banner-main h1 {
        font-size: 22px;
      }
    }

    @media (max-width: 768px) {
      .welcome-banner-card {
        flex-direction: column;
        align-items: stretch;
        padding: 18px 16px;
        gap: 16px;
        border-radius: 14px;
      }

      .welcome-qr-card {
        width: 100%;
        box-sizing: border-box;
        padding: 14px 16px;
        gap: 14px;
      }

      .qr-info-meta {
        flex: 1;
        min-width: 0;
      }

      .qr-btn-group {
        display: flex;
        gap: 8px;
        width: 100%;
        flex-wrap: wrap;
      }

      .btn-qr-pill {
        flex: 1;
        min-width: 110px;
        justify-content: center;
        padding: 8px 12px;
        font-size: 12px;
      }

      .card {
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 18px;
      }

      .card h2 {
        font-size: 16px;
        margin-bottom: 14px;
        padding-bottom: 10px;
      }

      /* Transform tables into clean, structured card blocks */
      .responsive-table table,
      .responsive-table thead,
      .responsive-table tbody,
      .responsive-table th,
      .responsive-table td,
      .responsive-table tr {
        display: block;
      }

      .responsive-table thead tr {
        position: absolute;
        top: -9999px;
        left: -9999px;
      }

      .responsive-table tr {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 12px;
        padding: 8px 14px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
      }

      .responsive-table tr:hover {
        background: #ffffff;
      }

      .responsive-table td {
        border: none;
        border-bottom: 1px dashed #f1f5f9;
        position: relative;
        padding: 9px 0 !important;
        display: flex;
        justify-content: space-between;
        align-items: center;
        text-align: right;
        min-height: 38px;
        font-size: 13px;
      }

      .responsive-table td:last-child {
        border-bottom: none;
      }

      .responsive-table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        font-size: 11px;
        text-align: left;
        padding-right: 12px;
        flex-shrink: 0;
      }

      /* Clean handling for empty table rows */
      .responsive-table tr.empty-row td,
      .responsive-table td.empty-cell {
        display: block;
        text-align: center;
        padding: 14px 8px !important;
        color: #94a3b8;
      }

      .responsive-table tr.empty-row td::before,
      .responsive-table td.empty-cell::before {
        display: none !important;
      }

      .month-filter-card {
        flex-direction: column;
        align-items: stretch;
        padding: 16px;
      }

      .month-filter-card form {
        width: 100%;
        display: flex;
        gap: 8px;
      }

      .month-filter-card select {
        flex: 1;
        min-width: 0;
      }
    }

    @media (max-width: 580px) {
      .welcome-banner-card {
        padding: 16px 14px;
        gap: 14px;
      }

      .welcome-banner-main h1 {
        font-size: 20px;
      }

      .emp-id-text {
        font-size: 12.5px;
        gap: 6px;
      }

      .welcome-qr-card {
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 16px 12px;
        gap: 12px;
      }

      .qr-image-frame {
        width: 110px;
        height: 110px;
      }

      .qr-info-meta {
        align-items: center;
        width: 100%;
        text-align: center;
      }

      .qr-meta-top {
        justify-content: center;
      }

      .qr-meta-desc {
        text-align: center;
        font-size: 11.5px;
      }

      .qr-btn-group {
        width: 100%;
        justify-content: center;
        gap: 8px;
      }

      .btn-qr-pill {
        flex: 1;
        min-width: 100px;
        justify-content: center;
        padding: 8px 10px;
        font-size: 12px;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
      }

      .stat-card {
        padding: 12px 14px;
        border-radius: 10px;
        min-height: 76px;
      }

      .stat-card .stat-label {
        font-size: 11px;
      }

      .stat-card .stat-val {
        font-size: 18px;
      }

      .today-att-grid {
        grid-template-columns: 1fr;
        gap: 10px;
      }
    }

    @media (max-width: 360px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .qr-btn-group {
        flex-direction: column;
        width: 100%;
      }

      .btn-qr-pill {
        width: 100%;
      }
    }
  </style>
</head>

<body>

<!-- MOBILE TOP BAR -->
<div class="mobile-top-bar">
  <div class="bar-title">
    <i class="bi bi-person-badge"></i> Employee Panel
  </div>
  <button class="hamburger-btn" id="menuToggle" aria-label="Toggle navigation menu">
    <i class="bi bi-list"></i>
  </button>
</div>

<div class="layout">

  <!-- SIDEBAR -->
  <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <h2><i class="bi bi-person-badge"></i> Employee Panel</h2>
      <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">&times;</button>
    </div>

    <div class="sidebar-nav">
      <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="apply_leave.php"><i class="bi bi-calendar-plus"></i> Apply Leave</a>
    </div>

    <a href="../auth/logout.php" class="logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>
  
  <!-- BACKDROP OVERLAY FOR MOBILE -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- MAIN CONTENT CONTAINER -->
  <div class="main">

    <!-- TOP WELCOME BANNER WITH QR CODE -->
    <div class="welcome-banner-card">
      <div class="welcome-banner-main">
        <div class="welcome-role-pill">
          <i class="bi bi-person-badge"></i> Employee Portal
        </div>
        <h1>Welcome back, <?= htmlspecialchars($user['name']) ?> 👋</h1>
        <div class="emp-id-text">
          <span>Employee ID:</span>
          <span class="emp-badge-tag"><?= htmlspecialchars($user['employee_id']) ?></span>
          <span>•</span>
          <span>Branch: <strong><?= htmlspecialchars($branchData['branch_name'] ?? ucfirst($userBranch)) ?></strong></span>
          <span>•</span>
          <span>Shift: <strong><?= date("h:i A", strtotime($officeStartTime)) ?> – <?= date("h:i A", strtotime($officeEndTime)) ?></strong> (<?= number_format($empWorkingHours, 1) ?> hrs)</span>
          <span>•</span>
          <span>Work Days: <strong><?= htmlspecialchars(formatCheckInDaysDisplay($user['check_in_days'] ?? '', $branchName)) ?></strong></span>
        </div>
      </div>

      <!-- QR CODE SECTION -->
      <div class="welcome-qr-card">
        <div class="qr-image-frame" onclick="openQrModal()" title="Click to view full size">
          <img 
            id="dashboardQrImg" 
            src="<?= htmlspecialchars($qrDisplaySrc) ?>" 
            alt="Check-in QR Code"
            crossorigin="anonymous"
          >
          <div class="qr-hover-zoom">
            <i class="bi bi-arrows-fullscreen"></i>
          </div>
        </div>

        <div class="qr-info-meta">
          <div class="qr-meta-top">
            <span id="qrBadge" class="qr-type-badge <?= $hasCustomQr ? 'badge-custom' : 'badge-auto' ?>">
              <?= $hasCustomQr ? 'Custom' : 'Official QR' ?>
            </span>
          </div>
       

          <div class="qr-btn-group">
            <!-- <label for="qrFileInput" class="btn-qr-pill btn-upload" id="uploadLabel">
              <i class="bi bi-cloud-arrow-up-fill"></i> <span id="uploadBtnText"><?= $hasCustomQr ? 'Change QR' : 'Upload QR' ?></span>
            </label> -->
            <input type="file" id="qrFileInput" accept="image/*" style="display:none;" onchange="handleQrUpload(event)">

            <button type="button" class="btn-qr-pill btn-download" onclick="downloadQrCode()" title="Download QR Image">
              <i class="bi bi-download"></i> Download
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MONTH FILTER -->
    <div class="month-filter-card">
      <div>
        <div class="month-filter-title">Monthly Attendance Period</div>
        <div class="month-filter-value"><?= htmlspecialchars($selectedMonthLabel) ?></div>
      </div>

      <form method="GET">
        <select name="month" aria-label="Select month">
          <?php
          for ($i = -8; $i <= 3; $i++) {
              $target = strtotime("$i month", strtotime(date('Y-m-01')));
              $value = date('Y-m', $target);

              if ($isThirthahalliBranch) {
                  $label = date('F Y', $target);
              } else {
                  $cycleStart = date('21 M', strtotime('-1 month', $target));
                  $cycleEnd = date('20 M Y', $target);
                  $label = $cycleStart . ' - ' . $cycleEnd;
              }

              $selected = ($value === $selectedMonth) ? 'selected' : '';
              echo '<option value="' . htmlspecialchars($value) . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
          }
          ?>
        </select>
        <button type="submit"><i class="bi bi-filter"></i> Filter</button>
      </form>
    </div>

    <!-- STATS SUMMARY GRID -->
    <div class="stats-grid">
      <div class="stat-card stat-bg-worked">
        <span class="stat-label"><i class="bi bi-clock-history"></i> Worked Today</span>
        <span class="stat-val"><?= $todayWorked ?></span>
      </div>

      <div class="stat-card stat-bg-present">
        <span class="stat-label"><i class="bi bi-check-circle"></i> Present</span>
        <span class="stat-val"><?= $totalPresentDays ?></span>
      </div>

      <div class="stat-card stat-bg-late">
        <span class="stat-label"><i class="bi bi-hourglass-split"></i> Late</span>
        <span class="stat-val"><?= $totalLateDays ?></span>
      </div>

      <div class="stat-card stat-bg-half">
        <span class="stat-label"><i class="bi bi-pie-chart"></i> Half Day</span>
        <span class="stat-val"><?= $totalHalfDays ?></span>
      </div>

      <div class="stat-card stat-bg-absent">
        <span class="stat-label"><i class="bi bi-x-circle"></i> Absent</span>
        <span class="stat-val"><?= $totalAbsentDays ?></span>
      </div>

      <div class="stat-card stat-bg-cl">
        <span class="stat-label"><i class="bi bi-calendar2-check"></i> Monthly CL</span>
        <span class="stat-val">
          <?= $totalPLDays ?>
          <?php if (isset($user['monthly_cl'])): ?>
            <span style="font-size:13px; font-weight:normal; opacity:0.85;">/ <?= (float)$user['monthly_cl'] ?></span>
          <?php endif; ?>
        </span>
      </div>

      <div class="stat-card stat-bg-company">
        <span class="stat-label"><i class="bi bi-building-check"></i> Company Leave</span>
        <span class="stat-val"><?= $totalCLDays ?></span>
      </div>

      <div class="stat-card stat-bg-ot">
        <span class="stat-label"><i class="bi bi-lightning-charge"></i> Overtime</span>
        <span class="stat-val"><?= $totalOvertimeDays ?></span>
      </div>

      <?php if ($totalOTPendingDays > 0): ?>
      <div class="stat-card stat-bg-ot-pen">
        <span class="stat-label"><i class="bi bi-clock"></i> OT Pending</span>
        <span class="stat-val"><?= $totalOTPendingDays ?></span>
      </div>
      <?php endif; ?>
    </div>

    <!-- TODAY'S ATTENDANCE CARD -->
    <div class="card">
      <h2><i class="bi bi-calendar-event"></i> Today's Attendance</h2>
      <?php if ($attendance) { 
        $checkInTime = date("H:i:s", strtotime($attendance['check_in']));
        $isLateWarning = (($attendance['status'] ?? '') === 'Late');
        
        if ($isLateWarning) { ?>
          <div class="warning-box">
            <strong><i class="bi bi-exclamation-triangle-fill"></i> Late Check-In Notice</strong><br>
            You checked in at <strong><?= date("h:i A", strtotime($attendance['check_in'])) ?></strong>. Your lunch break time has been reduced to <strong>10 minutes</strong> today.
          </div>
        <?php } ?>

        <div class="today-att-grid">
          <div class="today-att-box">
            <span class="box-lbl">Status</span>
            <span class="box-val">
              <span class="status-badge status-<?= strtolower(str_replace(' ', '', $attendance['status'] ?? 'pending')) ?>">
                <?= htmlspecialchars($attendance['status'] ?? 'Marked'); ?>
              </span>
            </span>
          </div>

          <div class="today-att-box">
            <span class="box-lbl">Check In</span>
            <span class="box-val">
              <?= !empty($attendance['check_in']) ? date("h:i A", strtotime($attendance['check_in'])) : '-' ?>
            </span>
          </div>

          <div class="today-att-box">
            <span class="box-lbl">Check Out</span>
            <span class="box-val">
              <?= !empty($attendance['check_out']) ? date("h:i A", strtotime($attendance['check_out'])) : '<span style="color:#d97706; font-weight:500;">Active</span>' ?>
            </span>
          </div>

          <?php if (!empty($attendance['lunch_out'])): ?>
          <div class="today-att-box">
            <span class="box-lbl">Lunch Out</span>
            <span class="box-val"><?= date("h:i A", strtotime($attendance['lunch_out'])) ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($attendance['lunch_in'])): ?>
          <div class="today-att-box">
            <span class="box-lbl">Lunch In</span>
            <span class="box-val"><?= date("h:i A", strtotime($attendance['lunch_in'])) ?></span>
          </div>
          <?php endif; ?>

          <div class="today-att-box">
            <span class="box-lbl">Worked Today</span>
            <span class="box-val" style="color:#0f766e; font-weight:700;"><?= $todayWorked ?></span>
          </div>
        </div>
      <?php } else { ?>
        <p class="no-data-msg"><i class="bi bi-info-circle"></i> No attendance marked for today yet.</p>
      <?php } ?>
    </div>

    <!-- LEAVE HISTORY CARD -->
    <div class="card responsive-table">
      <h2><i class="bi bi-journal-text"></i> Leave History</h2>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Reason</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($leaves && $leaves->num_rows > 0) { ?>
              <?php while ($row = $leaves->fetch_assoc()) { ?>
              <tr>
                <td data-label="Date"><?= htmlspecialchars($row['date']) ?></td>
                <td data-label="Type"><?= htmlspecialchars($row['type']) ?></td>
                <td data-label="Reason"><?= htmlspecialchars($row['reason'] ?: '-') ?></td>
                <td data-label="Status">
                  <span class="status-badge status-<?= strtolower($row['status']) ?>">
                    <?= ucfirst(htmlspecialchars($row['status'])) ?>
                  </span>
                </td>
              </tr>
              <?php } ?>
            <?php } else { ?>
              <tr class="empty-row">
                <td colspan="4" class="empty-cell">No leave requests found for this month</td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- COMPANY LEAVE ANNOUNCEMENTS -->
    <div class="card responsive-table">
      <h2><i class="bi bi-megaphone"></i> Company Leave Announcements</h2>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Leave Date</th>
              <th>Title</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if($companyLeaves && $companyLeaves->num_rows > 0): ?>
              <?php while($leave = $companyLeaves->fetch_assoc()): 
                $isToday = ($leave['leave_date'] == $today);
                $isUpcoming = ($leave['leave_date'] > $today);
              ?>
              <tr>
                <td data-label="Leave Date"><?= date("d M Y", strtotime($leave['leave_date'])) ?></td>
                <td data-label="Title"><?= htmlspecialchars($leave['title']) ?></td>
                <td data-label="Status">
                  <?php if($isToday): ?>
                    <span class="status-badge" style="background:#7c3aed;">Today</span>
                  <?php elseif($isUpcoming): ?>
                    <span class="status-badge" style="background:#2563eb;">Upcoming</span>
                  <?php else: ?>
                    <span class="status-badge" style="background:#6b7280;">Completed</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr class="empty-row">
                <td colspan="3" class="empty-cell">No company leaves announced for this period</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ATTENDANCE HISTORY -->
    <div class="card responsive-table">
      <h2><i class="bi bi-clock-history"></i> Attendance History - <?= htmlspecialchars($selectedMonthLabel) ?></h2>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Employee ID</th>
              <th>Date</th>
              <th>Check In</th>
              <th>Check Out</th>
              <th>Total Hours</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($allAttendance && $allAttendance->num_rows > 0) { ?>
              <?php while ($row = $allAttendance->fetch_assoc()) { ?>
              <tr>
                <td data-label="Employee ID"><?= htmlspecialchars($row['employee_id']) ?></td>
                <td data-label="Date"><?= date("d M Y", strtotime($row['date'])) ?></td>
                <td data-label="Check In"><?= !empty($row['check_in']) ? date("h:i A", strtotime($row['check_in'])) : '-' ?></td>
                <td data-label="Check Out"><?= !empty($row['check_out']) ? date("h:i A", strtotime($row['check_out'])) : '-' ?></td>
                <td data-label="Total Hours" style="font-weight:600; color:#16a34a;">
                  <?php
                  if (!empty($row['check_in']) && !empty($row['check_out'])) {
                      $checkIn = strtotime($row['check_in']);
                      $checkOut = strtotime($row['check_out']);
                      $totalSeconds = max(0, $checkOut - $checkIn);
                      $lunchSeconds = 0;

                      if (!empty($row['lunch_out']) && !empty($row['lunch_in'])) {
                          $lunchSeconds = max(0, strtotime($row['lunch_in']) - strtotime($row['lunch_out']));
                      }

                      $finalSeconds = max(0, $totalSeconds - $lunchSeconds);
                      $hours = floor($finalSeconds / 3600);
                      $minutes = floor(($finalSeconds % 3600) / 60);

                      echo $hours . "h " . $minutes . "m";
                  } else {
                      echo "-";
                  }
                  ?>
                </td>
                <td data-label="Status">
                  <?php
                  $status = $row['status'] ?? '';
                  if ($status == 'CL') {
                      $leaveTitle = !empty($row['leave_title']) ? $row['leave_title'] : 'Company Leave';
                  ?>
                      <span class="status-badge" style="background:#7c3aed;">
                          <?= htmlspecialchars($leaveTitle) ?>
                      </span>
                  <?php
                  } else {
                      $color = '#6b7280';
                      if ($status == 'Present') $color = '#16a34a';
                      elseif ($status == 'Absent') $color = '#dc2626';
                      elseif ($status == 'Half Day') $color = '#f59e0b';
                      elseif ($status == 'Late') $color = '#2563eb';
                      elseif ($status == 'Overtime') $color = '#7c3aed';
                      elseif ($status == 'Overtime Pending') $color = '#d97706';
                      elseif ($status == 'PL') $color = '#0d9488';
                      elseif ($status == 'Half Day PL') $color = '#0ea5a8';
                      elseif ($status == 'Half Day Absent') $color = '#94644a';
                  ?>
                      <span class="status-badge" style="background:<?= $color ?>;">
                          <?= htmlspecialchars($status ?: 'Pending') ?>
                      </span>
                  <?php } ?>
                </td>
              </tr>
              <?php } ?>
            <?php } else { ?>
              <tr class="empty-row">
                <td colspan="6" class="empty-cell">No attendance records found for this month</td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
  const menuToggle = document.getElementById('menuToggle');
  const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    sidebar.classList.add('active');
    sidebarOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  if (menuToggle) menuToggle.addEventListener('click', openSidebar);
  if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
  if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

  // Close sidebar on pressing Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
      closeSidebar();
    }
  });

  // Automatically close sidebar if a link is clicked on mobile
  const sidebarLinks = sidebar.querySelectorAll('a');
  sidebarLinks.forEach(function(link) {
    link.addEventListener('click', function() {
      if (window.innerWidth <= 992) {
        closeSidebar();
      }
    });
  });
</script>

<!-- ENLARGED QR MODAL -->
<div id="qrEnlargeModal" class="qr-modal-overlay" onclick="closeQrModal(event)">
  <div class="qr-modal-dialog" onclick="event.stopPropagation()">
    <button type="button" class="qr-modal-close" onclick="closeQrModal()">&times;</button>
    <div class="qr-modal-header">
      <h3><?= htmlspecialchars($user['name']) ?></h3>
      <p>Employee ID: <strong><?= htmlspecialchars($user['employee_id']) ?></strong> • <?= htmlspecialchars($branchData['branch_name'] ?? ucfirst($userBranch)) ?></p>
    </div>
    <div class="qr-modal-body">
      <div class="qr-modal-img-wrap">
        <img id="modalQrImg" src="<?= htmlspecialchars($qrDisplaySrc) ?>" alt="QR Code Full Size" crossorigin="anonymous">
      </div>
      <p class="qr-modal-hint">Present this QR code to the scanner for Check-in & Check-out</p>
    </div>
    <div class="qr-modal-footer">
      <!-- <label for="qrFileInputModal" class="btn-qr-pill btn-upload">
        <i class="bi bi-cloud-arrow-up-fill"></i> Change QR
      </label> -->
      <input type="file" id="qrFileInputModal" accept="image/*" style="display:none;" onchange="handleQrUpload(event)">
      <button type="button" class="btn-qr-pill btn-download" onclick="downloadQrCode()">
        <i class="bi bi-download"></i> Download QR
      </button>
    </div>
  </div>
</div>

<script>
function showToast(message, error = false) {
  const toast = document.createElement("div");
  toast.innerText = message;
  toast.style.position = "fixed";
  toast.style.top = "20px";
  toast.style.right = "20px";
  toast.style.maxWidth = "380px";
  toast.style.whiteSpace = "pre-line";
  toast.style.padding = "14px 20px";
  toast.style.borderRadius = "10px";
  toast.style.color = "white";
  toast.style.fontWeight = "600";
  toast.style.fontSize = "14px";
  toast.style.lineHeight = "1.4";
  toast.style.zIndex = "99999";
  toast.style.background = error ? "#ef4444" : "#16a34a";
  toast.style.boxShadow = "0 8px 25px rgba(0,0,0,0.2)";
  toast.style.animation = "slideIn 0.3s ease";
  toast.style.transition = "opacity 0.3s ease";

  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = "0";
    setTimeout(() => {
      toast.remove();
    }, 300);
  }, 3500);
}

function openQrModal() {
  const modal = document.getElementById("qrEnlargeModal");
  if (modal) modal.classList.add("active");
  document.body.style.overflow = "hidden";
}

function closeQrModal(e) {
  if (e && e.target && e.target !== e.currentTarget && !e.target.classList.contains("qr-modal-close")) return;
  const modal = document.getElementById("qrEnlargeModal");
  if (modal) modal.classList.remove("active");
  document.body.style.overflow = "";
}

async function handleQrUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/svg+xml'];
  if (!validTypes.includes(file.type)) {
    showToast("Please select a valid image file (PNG, JPG, JPEG, WEBP, SVG)", true);
    event.target.value = '';
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    showToast("File size exceeds 5MB limit", true);
    event.target.value = '';
    return;
  }

  const formData = new FormData();
  formData.append('qr_image_file', file);
  formData.append('upload_qr_action', '1');

  const uploadBtnText = document.getElementById("uploadBtnText");
  const origText = uploadBtnText ? uploadBtnText.innerText : 'Upload QR';
  if (uploadBtnText) uploadBtnText.innerText = 'Uploading...';

  try {
    const res = await fetch('dashboard.php', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: formData
    });
    const data = await res.json();

    if (data.success) {
      const qrImg = document.getElementById("dashboardQrImg");
      const modalImg = document.getElementById("modalQrImg");
      const newUrl = data.qr_url + '?t=' + Date.now();
      if (qrImg) qrImg.src = newUrl;
      if (modalImg) modalImg.src = newUrl;

      const badge = document.getElementById("qrBadge");
      if (badge) {
        badge.innerText = 'Custom';
        badge.className = 'qr-type-badge badge-custom';
      }

      if (uploadBtnText) uploadBtnText.innerText = 'Change QR';
      showToast("QR Code uploaded successfully! ✅");
    } else {
      showToast(data.message || "Upload failed", true);
      if (uploadBtnText) uploadBtnText.innerText = origText;
    }
  } catch (err) {
    console.error(err);
    showToast("Error uploading QR code. Please try again.", true);
    if (uploadBtnText) uploadBtnText.innerText = origText;
  } finally {
    event.target.value = '';
  }
}

async function downloadQrCode() {
  const img = document.getElementById("dashboardQrImg");
  if (!img) return;

  const empId = "<?= htmlspecialchars($user['employee_id']) ?>";
  const fileName = empId + "_QR.png";

  try {
    const response = await fetch(img.src);
    const blob = await response.blob();
    const blobUrl = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = blobUrl;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(blobUrl);
    showToast("QR Code downloaded successfully! 📥");
  } catch (e) {
    const a = document.createElement("a");
    a.href = img.src;
    a.download = fileName;
    a.target = "_blank";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }
}

<?php if (isset($_SESSION['flash_success'])): ?>
  showToast(<?= json_encode($_SESSION['flash_success']) ?>);
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
  showToast(<?= json_encode($_SESSION['flash_error']) ?>, true);
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>
</script>

</body>
</html>