<?php

include("../config/db.php");

include("../mail/send_mail_function.php");

$id = $_GET['id'];

$user = $conn->query("
    SELECT * FROM users
    WHERE id=$id
")->fetch_assoc();

$month = date("Y-m");

$attendance = $conn->query("
    SELECT * FROM attendance
    WHERE user_id=$id
    AND date LIKE '$month%'
");

$present = 0;
$half = 0;

while ($row = $attendance->fetch_assoc()) {

    if ($row['status'] == "Present")
        $present++;

    elseif ($row['status'] == "Half Day")
        $half++;
}

$perDay = 500;

$salary =
    ($present * $perDay) +
    ($half * ($perDay / 2));

$subject = "Salary Slip";

$message = "
<h2>Salary Slip</h2>

<p>Hello {$user['name']},</p>

<p>Present Days: <b>$present</b></p>

<p>Half Days: <b>$half</b></p>

<p>Total Salary: <b>₹$salary</b></p>

<br>

<p>Thank You.</p>
";

sendEmployeeMail(
    $user['email'],
    $subject,
    $message
);

echo "
<script>
alert('Salary Slip Sent ✅');
window.location.href='dashboard.php';
</script>
";
?>