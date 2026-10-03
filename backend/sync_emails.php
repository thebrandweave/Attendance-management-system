<?php
/**
 * Backend Script: Sync Employee Emails to Database
 *
 * Usage from command line:
 * php backend/sync_emails.php
 *
 * This script reads config/employee_emails.php and updates the
 * `users.email` column in MySQL for matching employee IDs.
 */

require_once __DIR__ . '/../config/db.php';

$isCli = (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST']));

if (!$isCli) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        die("Unauthorized access. Admin privileges required.");
    }
}

$emailsFile = __DIR__ . '/../config/employee_emails.php';
if (!file_exists($emailsFile)) {
    die("Error: config/employee_emails.php not found.\n");
}

$emailMap = require $emailsFile;
if (!is_array($emailMap)) {
    die("Error: config/employee_emails.php must return an array.\n");
}

$total = count($emailMap);
$updated = 0;
$skipped = 0;
$errors = 0;

echo $isCli ? "=== SYNCING EMPLOYEE EMAILS TO DATABASE ===\n" : "<h3>Syncing Employee Emails to Database</h3><ul>";

$stmt = $conn->prepare("UPDATE users SET email = ? WHERE employee_id = ?");

foreach ($emailMap as $empCode => $data) {
    $email = trim($data['email'] ?? '');
    $name = $data['name'] ?? $empCode;

    if (empty($email)) {
        $skipped++;
        continue;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Invalid email format for $empCode ($name): $email";
        echo $isCli ? "[WARN] $msg\n" : "<li><span style='color:orange;'>[WARN]</span> $msg</li>";
        $errors++;
        continue;
    }

    $stmt->bind_param("ss", $email, $empCode);
    if ($stmt->execute() && $stmt->affected_rows >= 0) {
        $updated++;
        $msg = "Updated $empCode ($name) -> $email";
        echo $isCli ? "[OK] $msg\n" : "<li><span style='color:green;'>[OK]</span> $msg</li>";
    } else {
        $errors++;
        $msg = "Failed to update $empCode ($name): " . $conn->error;
        echo $isCli ? "[ERR] $msg\n" : "<li><span style='color:red;'>[ERR]</span> $msg</li>";
    }
}

$stmt->close();

$summary = sprintf(
    "Finished: %d total entries in registry | %d updated in DB | %d skipped (no email entered) | %d errors",
    $total, $updated, $skipped, $errors
);

echo $isCli ? "\n$summary\n" : "</ul><p><strong>$summary</strong></p>";
