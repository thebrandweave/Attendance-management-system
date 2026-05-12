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
$absent = 0;

while ($row = $attendance->fetch_assoc()) {

    if ($row['status'] == "Present")
        $present++;

    elseif ($row['status'] == "Half Day")
        $half++;

    elseif ($row['status'] == "Pending")
        $absent++;
}

$subject = "Monthly Attendance Report";

$message = "
<h2>Attendance Report</h2>

<p>Hello {$user['name']},</p>

<table border='1' cellpadding='10' cellspacing='0'>

<tr>
    <th>Present</th>
    <th>Half Day</th>
    <th>Absent</th>
</tr>

<tr>
    <td>$present</td>
    <td>$half</td>
    <td>$absent</td>
</tr>

</table>

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
alert('Attendance Report Sent ✅');
window.location.href='dashboard.php';
</script>
";
?>