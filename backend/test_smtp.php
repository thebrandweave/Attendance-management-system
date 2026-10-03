<?php
/**
 * SMTP Connection & Email Diagnostic Test
 * Tests Gmail SMTP settings locally for GD Edu Tech (Mangalore)
 *
 * URL: http://localhost/attendance-management-system/backend/test_smtp.php
 * CLI: php backend/test_smtp.php recipient@example.com
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail_helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$isCli = (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST']));

// Auth check for web
if (!$isCli) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        // Allow access if testing on localhost
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
            die("Unauthorized. Admin privileges required.");
        }
    }
}

$branch = 'gdedutech';
$config = getMailConfig($branch);

$testEmail = '';
$result = null;
$debugOutput = '';

if ($isCli) {
    $testEmail = $argv[1] ?? '';
    if (empty($testEmail)) {
        echo "Usage: php backend/test_smtp.php <your_email@example.com>\n";
        echo "Testing with configured from_email: " . ($config['from_email'] ?? 'none') . "\n";
        $testEmail = $config['from_email'] ?? '';
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $testEmail = trim($_POST['test_email'] ?? '');
    }
}

if (!empty($testEmail)) {
    $mail = new PHPMailer(true);
    ob_start();

    try {
        $mail->SMTPDebug = 2; // Detailed debug output
        $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
            $debugOutput .= htmlspecialchars($str) . "\n";
        };

        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'] ?? 'smtp.gmail.com';
        $mail->Port       = $config['smtp_port'] ?? 587;
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'] ?? '';
        $mail->Password   = $config['smtp_pass'] ?? '';

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
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $fromEmail = !empty($config['from_email']) ? $config['from_email'] : $config['smtp_user'];
        $fromName  = $config['from_name'] ?? 'GD Edu Tech HR (Mangalore)';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($testEmail);

        $mail->isHTML(true);
        $mail->Subject = "✅ [SMTP Test] GD Edu Tech Mangalore - " . date('d M Y H:i:s');
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif; padding:20px; border:1px solid #e2e8f0; border-radius:10px; max-width:550px;'>
                <h2 style='color:#4f46e5; margin-top:0;'>SMTP Connection Test Successful! 🎉</h2>
                <p>This is a diagnostic verification email sent from your local system:</p>
                <ul>
                    <li><strong>Branch:</strong> {$config['branch_title']}</li>
                    <li><strong>Sender:</strong> {$fromEmail}</li>
                    <li><strong>Recipient:</strong> {$testEmail}</li>
                    <li><strong>SMTP Server:</strong> {$config['smtp_host']}:{$config['smtp_port']} ({$config['smtp_secure']})</li>
                    <li><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</li>
                </ul>
                <p style='color:#16a34a; font-weight:bold;'>Your Gmail SMTP is working perfectly!</p>
            </div>
        ";
        $mail->AltBody = "SMTP Test Successful! Sent from {$config['branch_title']} at " . date('Y-m-d H:i:s');

        $mail->send();
        ob_end_clean();
        $result = ['success' => true, 'message' => "Email sent successfully to {$testEmail}!"];
        logEmailActivity('SUCCESS', $testEmail, $mail->Subject, 'Test email via SMTP', $branch);

    } catch (Exception $e) {
        ob_end_clean();
        $err = $mail->ErrorInfo ?: $e->getMessage();
        $result = ['success' => false, 'message' => $err];
        logEmailActivity('ERROR', $testEmail, 'SMTP Diagnostic Test', $err, $branch);
    }
}

if ($isCli) {
    echo "=== SMTP DIAGNOSTIC TEST (GDEDUTECH) ===\n";
    echo "Host: " . ($config['smtp_host'] ?? '') . ":" . ($config['smtp_port'] ?? '') . "\n";
    echo "User: " . ($config['smtp_user'] ?? '') . "\n";
    echo "Pass: " . (!empty($config['smtp_pass']) ? 'Configured (' . strlen($config['smtp_pass']) . ' chars)' : 'NOT CONFIGURED') . "\n";
    if ($result !== null) {
        if ($result['success']) {
            echo "\n✅ " . $result['message'] . "\n";
        } else {
            echo "\n❌ FAILED: " . $result['message'] . "\n";
            echo "\n--- Debug Trace ---\n" . $debugOutput . "\n";
        }
    }
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SMTP Diagnostic Test - GD Edu Tech</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family:'Poppins',sans-serif; background:#eef2f7; margin:0; padding:30px 15px; color:#1e293b; }
    .card { max-width:680px; margin:0 auto; background:#fff; padding:28px; border-radius:14px; box-shadow:0 10px 25px rgba(0,0,0,0.08); }
    h2 { margin-top:0; color:#1e293b; font-size:22px; display:flex; align-items:center; gap:8px; }
    .status-badge { display:inline-block; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600; }
    .badge-active { background:#ecfdf5; color:#16a34a; }
    .badge-missing { background:#fef2f2; color:#dc2626; }
    .config-table { width:100%; border-collapse:collapse; margin:16px 0; font-size:13px; }
    .config-table td { padding:8px 10px; border-bottom:1px solid #f1f5f9; }
    .config-table td:first-child { font-weight:600; color:#64748b; width:35%; }
    .form-group { margin-top:20px; }
    input[type="email"] { width:100%; height:42px; padding:8px 14px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; font-family:inherit; box-sizing:border-box; margin-top:6px; }
    button { margin-top:14px; width:100%; height:44px; background:linear-gradient(135deg,#6366f1,#4f46e5); color:#fff; border:none; border-radius:8px; font-weight:600; font-size:14px; cursor:pointer; }
    button:hover { opacity:0.95; }
    .alert { padding:14px 18px; border-radius:8px; margin-top:20px; font-size:14px; }
    .alert-success { background:#ecfdf5; color:#16a34a; border:1px solid #a7f3d0; }
    .alert-error { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
    pre { background:#0f172a; color:#38bdf8; padding:14px; border-radius:8px; font-size:11.5px; overflow-x:auto; margin-top:10px; white-space:pre-wrap; }
    .back-link { display:inline-block; margin-top:18px; color:#6366f1; text-decoration:none; font-size:13px; font-weight:600; }
  </style>
</head>
<body>
  <div class="card">
    <h2>📧 GD Edu Tech (Mangalore) SMTP Test</h2>
    <p style="font-size:13px; color:#64748b; margin-top:2px;">
      Use this diagnostic tool to test and verify your Google Mail SMTP credentials locally.
    </p>

    <table class="config-table">
      <tr>
        <td>Branch</td>
        <td><strong><?= htmlspecialchars($config['branch_title']) ?></strong></td>
      </tr>
      <tr>
        <td>SMTP Server</td>
        <td><?= htmlspecialchars($config['smtp_host']) ?>:<?= htmlspecialchars($config['smtp_port']) ?> (<?= strtoupper($config['smtp_secure']) ?>)</td>
      </tr>
      <tr>
        <td>Sender Email</td>
        <td><?= htmlspecialchars($config['from_email']) ?></td>
      </tr>
      <tr>
        <td>Sender Name</td>
        <td><?= htmlspecialchars($config['from_name']) ?></td>
      </tr>
      <tr>
        <td>App Password</td>
        <td>
          <?php if (!empty($config['smtp_pass'])): ?>
            <span class="status-badge badge-active">Configured (<?= strlen($config['smtp_pass']) ?> characters)</span>
          <?php else: ?>
            <span class="status-badge badge-missing">Missing / Not entered yet</span>
          <?php endif; ?>
        </td>
      </tr>
    </table>

    <?php if ($result !== null): ?>
      <?php if ($result['success']): ?>
        <div class="alert alert-success">
          <strong>✅ Success!</strong> <?= htmlspecialchars($result['message']) ?><br>
          <span style="font-size:12.5px;">Check your email inbox (and Spam folder) to view the test email.</span>
        </div>
      <?php else: ?>
        <div class="alert alert-error">
          <strong>❌ Delivery Failed:</strong> <?= htmlspecialchars($result['message']) ?><br>
          <span style="font-size:12.5px;">Common fix: Verify that 2-Step Verification is ON and you are using a 16-character Google App Password (not your normal password).</span>
        </div>
        <?php if (!empty($debugOutput)): ?>
          <details style="margin-top:10px;">
            <summary style="font-size:12px; color:#64748b; cursor:pointer;">View Detailed SMTP Debug Trace</summary>
            <pre><?= $debugOutput ?></pre>
          </details>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label style="font-weight:600; font-size:13px; color:#334155;">Enter Recipient Email to Receive Test Message:</label>
        <input type="email" name="test_email" required placeholder="your.personal.email@gmail.com" value="<?= htmlspecialchars($testEmail) ?>">
      </div>
      <button type="submit">🚀 Send Diagnostic Test Email</button>
    </form>

    <a href="../admin/dashboard.php" class="back-link">← Back to Admin Dashboard</a>
  </div>
</body>
</html>
