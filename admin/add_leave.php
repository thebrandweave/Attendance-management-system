<?php
session_start();
include("../config/db.php");

if (
    !isset($_SESSION['user']) ||
    $_SESSION['user']['role'] != 'admin'
) {
    header("Location: ../index.php");
    exit();
}

$message = "";

$branchId = $_SESSION['user']['branch_id'] ?? $_SESSION['branch_id'] ?? 0;
$branch = $_SESSION['user']['branch'] ?? $_SESSION['branch'] ?? '';

$bStmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ? OR LOWER(branch_name) = LOWER(?)");
$bStmt->bind_param("is", $branchId, $branch);
$bStmt->execute();
$bRes = $bStmt->get_result()->fetch_assoc();
$branchName = $bRes ? $bRes['branch_name'] : ucfirst($branch);

if (isset($_GET['delete_id'])) {
    $delId = intval($_GET['delete_id']);
    $delStmt = $conn->prepare("DELETE FROM company_leaves WHERE id=? AND (branch_id=? OR branch=?)");
    $delStmt->bind_param("iis", $delId, $branchId, $branch);
    if ($delStmt->execute()) {
        $message = "Company leave deleted successfully.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $leaveDate = $_POST['leave_date'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);

    $check = $conn->prepare("
        SELECT id
        FROM company_leaves
        WHERE leave_date=? AND (branch_id=? OR branch=?)
    ");

    $check->bind_param("sis", $leaveDate, $branchId, $branch);
    $check->execute();

    $exists = $check->get_result()->fetch_assoc();

    if ($exists) {

        $message = "Leave already added for this date.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO company_leaves (
                leave_date,
                title,
                description,
                branch_id,
                branch
            ) VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sssis",
            $leaveDate,
            $title,
            $description,
            $branchId,
            $branch
        );

        if ($stmt->execute()) {
            $message = "Leave added successfully.";
        }
    }
}

$leavesStmt = $conn->prepare("
    SELECT *
    FROM company_leaves
    WHERE branch_id=? OR branch=?
    ORDER BY leave_date DESC
");
$leavesStmt->bind_param("is", $branchId, $branch);
$leavesStmt->execute();
$leaves = $leavesStmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>

<title>Add Leave</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { margin: 0; font-family: 'Poppins', sans-serif; background: #eef2f7; color: #111827; }
  .layout { display: flex; min-height: 100vh; }
  .sidebar { width: 250px; background: linear-gradient(180deg, #111827, #1f2937); color: white; padding: 20px; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 1000; box-sizing: border-box; }
  .sidebar h2 { margin-bottom: 25px; font-size: 20px; text-align: center; font-family: 'Poppins', sans-serif; font-weight: 600; }
  .sidebar a { display: block; padding: 12px 14px; margin: 8px 0; color: white; text-decoration: none; border-radius: 8px; transition: 0.3s; font-size: 14px; font-family: 'Poppins', sans-serif; line-height: 1.5; }
  .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); transform: translateX(4px); font-weight: 600; }
  .sidebar .logout { background: #ef4444; }
  .sidebar .logout:hover { background: #dc2626; transform: none; }

/* ===== MAIN ===== */
.main {
  flex: 1;
  margin-left: 250px;
  width: calc(100% - 250px);
  padding: 25px;
  min-width: 0;
  overflow-x: auto;
  box-sizing: border-box;
}

.card{
    max-width:700px;
    margin:auto;
    background:white;
    padding:25px;
    border-radius:16px;
    box-shadow:0 4px 15px rgba(0,0,0,0.08);
}

h2{
    margin-bottom:20px;
}

input,
textarea{
    width:100%;
    padding:12px;
    margin-top:8px;
    margin-bottom:18px;
    border:1px solid #ddd;
    border-radius:10px;
    font-family:inherit;
}

button{
    background:#4f46e5;
    color:white;
    border:none;
    padding:12px 18px;
    border-radius:10px;
    cursor:pointer;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:25px;
}

th,td{
    border:1px solid #ddd;
    padding:12px;
    text-align:center;
}

th{
    background:#111827;
    color:white;
}

.message{
    background:#dcfce7;
    color:#166534;
    padding:12px;
    border-radius:10px;
    margin-bottom:15px;
}

</style>
</head>

<body>
    <div class="layout">

  <!-- SIDEBAR -->
  <div class="sidebar">
   <h2 style="text-align:center;">
  <?= htmlspecialchars($branchName) ?> Admin 
</h2>

    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="create_employee.php">👤 Create Employee</a>
    <a href="../api/checkin.php">🟢 Check In- Morning</a>
    <a href="../api/lunch.php">🍽️ Lunch Break</a>
    <a href="../api/checkout.php">🔴 Check Out- Evening</a>
    <?php
      $leaveCountQuery = $conn->query("SELECT COUNT(*) as total FROM leave_requests lr LEFT JOIN users u ON lr.employee_id = u.id WHERE (u.branch_id='$branchId' OR u.branch='$branch') AND lr.status='pending'");
      $leaveCount = $leaveCountQuery ? $leaveCountQuery->fetch_assoc()['total'] : 0;
    ?>
    <a href="leave_requests.php">📩 Manage Leaves <?php if($leaveCount > 0) { ?><span style="background:#ef4444; color:white; padding:2px 8px; border-radius:50px; font-size:12px; margin-left:8px; font-weight:600;"><?= $leaveCount ?></span><?php } ?></a>
    <a href="add_leave.php" class="active">📅 Company Leaves</a>
    <a href="reports.php">📊 Reports</a>
    <a href="../auth/logout.php" class="logout">🚪 Logout</a>
  </div>

<div class="card">

<h2>📅 Add Company Leave</h2>

<?php if($message): ?>
<div class="message">
    <?= $message ?>
</div>
<?php endif; ?>

<form method="POST">

<label>Leave Date</label>
<input type="date" name="leave_date" required>

<label>Leave Title</label>
<input type="text" name="title" placeholder="Leave Title" required>

<!-- <label>Description</label>
<textarea name="description" rows="4" placeholder="Optional"></textarea> -->

<button type="submit">
    Add Leave
</button>

</form>

<h2 style="margin-top:30px;">
    Leave List
</h2>

<table>
<tr>
    <th>Date</th>
    <th>Title</th>
    <!-- <th>Description</th> -->
    <th>Status</th>
    <th>Action</th>
</tr>

<?php while($row = $leaves->fetch_assoc()): ?>
<tr>

    <td>
        <?= date("d M Y", strtotime($row['leave_date'])) ?>
    </td>

    <td>
        <?= htmlspecialchars($row['title']) ?>
    </td>

    <td>

<?php

$today = date("Y-m-d");

if ($row['leave_date'] == $today) {

    echo '
    <span style="
        background:#f59e0b;
        color:white;
        padding:6px 12px;
        border-radius:20px;
        font-size:12px;
        font-weight:600;
    ">
        Today
    </span>
    ';

} elseif ($row['leave_date'] > $today) {

    echo '
    <span style="
        background:#16a34a;
        color:white;
        padding:6px 12px;
        border-radius:20px;
        font-size:12px;
        font-weight:600;
    ">
        Upcoming
    </span>
    ';

} else {

    echo '
    <span style="
        background:#6b7280;
        color:white;
        padding:6px 12px;
        border-radius:20px;
        font-size:12px;
        font-weight:600;
    ">
        Completed
    </span>
    ';
}

?>

    </td>
    <td>
        <a href="add_leave.php?delete_id=<?= $row['id'] ?>" onclick="return confirm('Delete this company leave?')" style="background:#ef4444; color:white; padding:6px 12px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:600;">Delete</a>
    </td>

</tr>
<?php endwhile; ?>

</table>

</div>

</body>
</html>