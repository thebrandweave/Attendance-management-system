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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $leaveDate = $_POST['leave_date'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);

    $check = $conn->prepare("
        SELECT id
        FROM company_leaves
        WHERE leave_date=?
    ");

    $check->bind_param("s", $leaveDate);
    $check->execute();

    $exists = $check->get_result()->fetch_assoc();

    if ($exists) {

        $message = "Leave already added for this date.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO company_leaves (
                leave_date,
                title,
                description
            ) VALUES (?, ?, ?)
        ");

        $stmt->bind_param(
            "sss",
            $leaveDate,
            $title,
            $description
        );

        if ($stmt->execute()) {
            $message = "Leave added successfully.";
        }
    }
}

$leaves = $conn->query("
    SELECT *
    FROM company_leaves
    ORDER BY leave_date DESC
");
?>

<!DOCTYPE html>
<html>
<head>

<title>Add Leave</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>

body {
  margin: 0;
  font-family: 'Poppins', sans-serif;
  background: #eef2f7;
}



/* ===== LAYOUT ===== */
.layout {
  display: flex;
  min-height: 100vh;
}

/* ===== SIDEBAR ===== */
.sidebar {
  width: 250px;
  background: linear-gradient(180deg, #111827, #1f2937);
  color: white;
  padding: 20px;
}

.sidebar h2 {
  margin-bottom: 25px;
  font-size: 20px;
  text-align: center;
}

.sidebar a {
  display: block;
  padding: 12px 14px;
  margin: 8px 0;
  color: white;
  text-decoration: none;
  border-radius: 8px;
  transition: 0.3s;
  font-size: 14px;
}

.sidebar a:hover {
  background: rgba(255,255,255,0.1);
  transform: translateX(4px);
}

.sidebar .logout {
  background: #ef4444;
}

.sidebar .logout:hover {
  background: #dc2626;
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
  <?= htmlspecialchars($_SESSION['user']['branch']) ?> Admin 
</h2>

    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="create_employee.php">👤 Create Employee</a>
    <a href="../api/checkin.php">🟢 Check In- Morning</a>
     <a href="../api/lunch.php">🍽️ Lunch Break</a>
    <a href="../api/checkout.php">🔴 Check Out- Evening</a>
    <a href="leave_requests.php">📩 Manage Leaves</a>
    <a href="add_leave.php">📅 Company Leaves</a>
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
<input type="text" name="title" placeholder="Example: Bakrid Holiday" required>

<label>Description</label>
<textarea name="description" rows="4" placeholder="Optional"></textarea>

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
    <th>Description</th>
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
        <?= htmlspecialchars($row['description']) ?>
    </td>
</tr>
<?php endwhile; ?>

</table>

</div>

</body>
</html>