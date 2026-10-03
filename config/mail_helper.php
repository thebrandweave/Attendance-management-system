<?php
/**
 * Email Helper & Notification Engine
 * Attendance & Leave Management System
 */

// Load PHPMailer classes
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/mail_templates.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Get Mail Configuration Array (Branch-Aware)
 */
function getMailConfig($branch = '') {
    static $allConfig = null;
    if ($allConfig === null) {
        $configFile = __DIR__ . '/mail_config.php';
        if (file_exists($configFile)) {
            $allConfig = require $configFile;
        } else {
            $allConfig = [];
        }
    }

    $default = $allConfig['default'] ?? [];
    $default['log_enabled'] = $allConfig['log_enabled'] ?? true;
    $default['log_file'] = $allConfig['log_file'] ?? (dirname(__DIR__) . '/logs/email_notifications.log');

    if (!empty($branch)) {
        $cleanBranch = strtolower(trim($branch));
        if (isset($allConfig['branches'][$cleanBranch])) {
            return array_merge($default, $allConfig['branches'][$cleanBranch]);
        }
        // Alias check: "mangalore" maps to "gdedutech"
        if (strpos($cleanBranch, 'mangalore') !== false || strpos($cleanBranch, 'gdedu') !== false) {
            if (isset($allConfig['branches']['gdedutech'])) {
                return array_merge($default, $allConfig['branches']['gdedutech']);
            }
        }
    }

    // Default to gdedutech if no branch specified, as requested by user
    if (isset($allConfig['branches']['gdedutech'])) {
        return array_merge($default, $allConfig['branches']['gdedutech']);
    }

    return $default;
}

/**
 * Write a record to the email notification log
 */
function logEmailActivity($status, $recipient, $subject, $details = '', $branch = '') {
    $config = getMailConfig($branch);
    if (empty($config['log_enabled'])) return;

    $logFile = $config['log_file'] ?? (dirname(__DIR__) . '/logs/email_notifications.log');
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }

    $time = date('Y-m-d H:i:s');
    $branchTag = !empty($branch) ? " [Branch: $branch]" : "";
    $line = sprintf("[%s] [%s]%s To: %s | Subject: %s%s\n",
        $time,
        strtoupper($status),
        $branchTag,
        $recipient,
        $subject,
        !empty($details) ? " | Details: $details" : ""
    );

    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Retrieve Employee Email (from DB first, fallback to config/employee_emails.php)
 */
function resolveEmployeeEmail($conn, $userId, $employeeCode = '') {
    $email = '';
    $empCode = $employeeCode;

    // 1. Check database users table
    if ($userId > 0) {
        $stmt = $conn->prepare("SELECT email, employee_id FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($res) {
                if (!empty($res['email'])) {
                    $email = trim($res['email']);
                }
                if (empty($empCode) && !empty($res['employee_id'])) {
                    $empCode = trim($res['employee_id']);
                }
            }
        }
    }

    // 2. If empty, check backend registry in config/employee_emails.php
    if (empty($email) && !empty($empCode)) {
        $emailsFile = __DIR__ . '/employee_emails.php';
        if (file_exists($emailsFile)) {
            $registry = require $emailsFile;
            if (isset($registry[$empCode]) && !empty($registry[$empCode]['email'])) {
                $email = trim($registry[$empCode]['email']);
            }
        }
    }

    return $email;
}

/**
 * Core Send Email Function using PHPMailer (Branch-Specific SMTP or mail() fallback)
 */
function sendAppEmail($toEmail, $toName, $subject, $htmlBody, $altBody = '', $branch = 'gdedutech') {
    $toEmail = trim($toEmail);
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        logEmailActivity('FAILED', $toEmail ?: 'EMPTY', $subject, 'Invalid or empty email address', $branch);
        return ['success' => false, 'error' => 'Invalid email address'];
    }

    $config = getMailConfig($branch);
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        $smtpEnabled = !empty($config['smtp_enabled']) && !empty($config['smtp_host']);
        if ($smtpEnabled) {
            $mail->isSMTP();
            $mail->Host       = $config['smtp_host'];
            $mail->Port       = $config['smtp_port'] ?? 587;
            $mail->SMTPAuth   = !empty($config['smtp_user']) && !empty($config['smtp_pass']);
            $mail->Username   = $config['smtp_user'] ?? '';
            $mail->Password   = $config['smtp_pass'] ?? '';

            // Ensure smooth TLS handshake on local Windows environments
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            $sec = strtolower($config['smtp_secure'] ?? 'tls');
            if ($sec === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($sec === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
        } else {
            // Standard mail() fallback
            $mail->isMail();
        }

        // Recipients
        $fromEmail = !empty($config['from_email']) ? $config['from_email'] : 'noreply@thebrandweave.com';
        $fromName  = !empty($config['from_name']) ? $config['from_name'] : 'GD Edu Tech HR';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName ?: $toEmail);

        // Content
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = !empty($altBody) ? $altBody : strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

        $mail->send();
        logEmailActivity('SUCCESS', $toEmail, $subject, $smtpEnabled ? 'Sent via SMTP (' . $config['from_email'] . ')' : 'Sent via PHP mail()', $branch);
        return ['success' => true];

    } catch (Exception $e) {
        $errMsg = $mail->ErrorInfo ?: $e->getMessage();
        logEmailActivity('ERROR', $toEmail, $subject, $errMsg, $branch);
        return ['success' => false, 'error' => $errMsg];
    }
}

/**
 * Send Employee Leave Request Approval / Rejection Notification
 */
function sendLeaveApprovalNotification($conn, $leaveRequestId, $status) {
    $ids = [];
    if (is_array($leaveRequestId)) {
        $ids = array_map('intval', $leaveRequestId);
    } elseif (is_string($leaveRequestId) && strpos($leaveRequestId, ',') !== false) {
        $ids = array_map('intval', explode(',', $leaveRequestId));
    } else {
        $id = (int)$leaveRequestId;
        if ($id > 0) $ids = [$id];
    }
    $ids = array_values(array_unique(array_filter($ids)));
    if (empty($ids)) return ['status' => 'error', 'message' => 'Invalid leave ID(s)'];

    $idList = implode(',', $ids);
    $q = $conn->query("
        SELECT lr.*, u.name AS employee_name, u.employee_id AS employee_code, u.email, u.branch
        FROM leave_requests lr
        JOIN users u ON lr.employee_id = u.id
        WHERE lr.id IN ($idList)
        ORDER BY lr.date ASC
    ");

    if (!$q || $q->num_rows === 0) {
        return ['status' => 'error', 'message' => 'Leave request record(s) not found'];
    }

    $dates = [];
    $firstRow = null;
    while ($r = $q->fetch_assoc()) {
        if (!$firstRow) $firstRow = $r;
        $dates[] = $r['date'];
    }
    $dates = array_values(array_unique($dates));
    sort($dates);

    $empName   = $firstRow['employee_name'] ?? 'Employee';
    $empCode   = $firstRow['employee_code'] ?? '';
    $userId    = (int)$firstRow['employee_id'];
    $empBranch = $firstRow['branch'] ?? 'gdedutech';

    // Resolve email
    $toEmail = resolveEmployeeEmail($conn, $userId, $empCode);

    if (empty($toEmail)) {
        logEmailActivity('SKIPPED', "Emp ID: $empCode ($empName)", "Leave Status Update: $status", "No email registered for employee in DB or backend registry", $empBranch);
        return ['status' => 'skipped', 'message' => "No email found for employee $empName ($empCode)"];
    }

    $config = getMailConfig($empBranch);

    // If branch is NOT gdedutech and SMTP is disabled, skip sending for non-gdedutech branches
    if (empty($config['smtp_enabled']) && strtolower(trim($empBranch)) !== 'gdedutech') {
        logEmailActivity('SKIPPED', "Emp ID: $empCode ($empName)", "Leave Status Update: $status", "SMTP currently enabled only for gdedutech Mangalore branch", $empBranch);
        return ['status' => 'skipped', 'message' => "SMTP currently active only for gdedutech Mangalore branch"];
    }

    $companyName = $config['company_name'] ?? 'GD Edu Tech (Mangalore)';
    $statusText  = (strtolower($status) === 'approved' || strtolower($status) === 'approve') ? 'Approved' : 'Rejected';

    $formattedDates = array_map(function($d) { return date('d M Y', strtotime($d)); }, $dates);
    if (count($dates) === 1) {
        $leaveDateDisplay = date('l, d F Y', strtotime($dates[0]));
        $dateSubjectText  = date('d M Y', strtotime($dates[0]));
    } else {
        $leaveDateDisplay = implode(', ', $formattedDates) . " (" . count($dates) . " Days)";
        $dateSubjectText  = count($dates) . " Days (" . reset($formattedDates) . " - " . end($formattedDates) . ")";
    }

    $templateData = [
        'employee_name' => $empName,
        'employee_code' => $empCode,
        'leave_date'    => $leaveDateDisplay,
        'leave_type'    => $firstRow['type'],
        'reason'        => $firstRow['reason'],
        'status'        => $status,
        'company_name'  => $companyName
    ];

    $htmlBody = getLeaveStatusEmailTemplate($templateData);
    $subject = sprintf("[%s] Your Leave Request for %s has been %s", $companyName, $dateSubjectText, $statusText);

    $res = sendAppEmail($toEmail, $empName, $subject, $htmlBody, '', $empBranch);
    return $res;
}

/**
 * Send Company Leave / Holiday Announcement to branch employees
 */
function sendCompanyLeaveNotification($conn, $leaveDate, $title, $description, $branchId, $branchName = '') {
    // If branchName not provided, look it up
    if (empty($branchName) && $branchId > 0) {
        $bQuery = $conn->query("SELECT branch_name FROM branches WHERE id = " . (int)$branchId);
        if ($bQuery && $bRow = $bQuery->fetch_assoc()) {
            $branchName = $bRow['branch_name'];
        }
    }
    if (empty($branchName)) {
        $branchName = 'gdedutech';
    }

    $config = getMailConfig($branchName);

    // As requested: currently active only for gdedutech Mangalore branch
    if (empty($config['smtp_enabled']) && strtolower(trim($branchName)) !== 'gdedutech') {
        logEmailActivity('SKIPPED', "All employees in $branchName", "Company Leave Announcement", "SMTP currently enabled only for gdedutech Mangalore branch", $branchName);
        return [
            'sent'    => [],
            'skipped' => [['id' => 'ALL', 'name' => "All employees in $branchName (SMTP active only for gdedutech)"]],
            'failed'  => []
        ];
    }

    $companyName = $config['company_name'] ?? 'GD Edu Tech (Mangalore)';

    // Query employees (exclude Inactive)
    if ($branchId > 0 || (!empty($branchName) && strtolower($branchName) !== 'all' && strtolower($branchName) !== 'company-wide')) {
        $sql = "SELECT id, name, employee_id, email, branch FROM users WHERE role = 'employee' AND (status != 'Inactive' OR status IS NULL) AND (branch_id = ? OR LOWER(branch) = LOWER(?))";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $branchId, $branchName);
    } else {
        $sql = "SELECT id, name, employee_id, email, branch FROM users WHERE role = 'employee' AND (status != 'Inactive' OR status IS NULL)";
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    $templateData = [
        'title'        => $title,
        'leave_date'   => $leaveDate,
        'description'  => $description,
        'branch_name'  => $config['branch_title'] ?? $branchName,
        'company_name' => $companyName
    ];

    $htmlBody = getCompanyLeaveEmailTemplate($templateData);
    $subject = sprintf("[%s Announcement] Company Holiday: %s (%s)", $companyName, $title, date('d M Y', strtotime($leaveDate)));

    $sent = [];
    $skipped = [];
    $failed = [];

    while ($emp = $res->fetch_assoc()) {
        $userId  = (int)$emp['id'];
        $empCode = $emp['employee_id'] ?? '';
        $empName = $emp['name'] ?? 'Employee';

        $toEmail = resolveEmployeeEmail($conn, $userId, $empCode);

        if (empty($toEmail)) {
            $skipped[] = ['id' => $empCode, 'name' => $empName];
            continue;
        }

        $mailRes = sendAppEmail($toEmail, $empName, $subject, $htmlBody, '', $branchName);
        if ($mailRes['success']) {
            $sent[] = ['id' => $empCode, 'name' => $empName, 'email' => $toEmail];
        } else {
            $failed[] = ['id' => $empCode, 'name' => $empName, 'email' => $toEmail, 'error' => $mailRes['error']];
        }
    }

    $stmt->close();

    return [
        'sent'    => $sent,
        'skipped' => $skipped,
        'failed'  => $failed
    ];
}

