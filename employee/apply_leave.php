<?php 
include("../config/db.php");

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit();
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html>
<head>
  <title>Apply Leave</title>

  <!-- FONT -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

  <style>

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background: #eef2f7;
    }

    /* ===== LAYOUT ===== */
    .layout {
      display: flex;
      min-height: 100vh;
    }

    /* ===== SIDEBAR (MATCHED) ===== */
    .sidebar {
      width: 250px;
      background: linear-gradient(180deg, #111827, #1f2937);
      color: white;
      padding: 20px;
    }

    .sidebar h2 {
      margin-bottom: 25px;
      text-align: center;
      font-size: 20px;
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

    /* ===== MAIN ===== */
    .main {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 30px;
    }

    /* ===== CARD ===== */
    .form-card {
      width: 400px;
      background: white;
      padding: 30px;
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
      text-align: center;
      animation: fadeIn 0.4s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .form-card h2 {
      margin-bottom: 20px;
    }

    input, select {
      width: 100%;
      padding: 12px;
      margin-bottom: 15px;
      border: 1px solid #ddd;
      border-radius: 10px;
      outline: none;
      transition: 0.3s;
    }

    input:focus, select:focus {
      border-color: #667eea;
      box-shadow: 0 0 5px rgba(102,126,234,0.4);
    }

    button {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.3s;
    }

    button:hover {
      transform: scale(1.03);
    }

    .success {
      margin-top: 15px;
      color: green;
      font-weight: 600;
    }

    .back-btn {
      display: inline-block;
      margin-top: 15px;
      padding: 10px 15px;
      background: #111827;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      font-size: 13px;
    }

    .back-btn:hover {
      background: #374151;
    }

  </style>
</head>

<body>

<div class="layout">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <h2>Employee Panel</h2>

    <a href="dashboard.php"> Dashboard</a>
    <a href="apply_leave.php"> Apply Leave</a>
    <!-- <a href="../api/checkin.php">🟢 Check In</a>
    <a href="../api/checkout.php">🔴 Check Out</a> -->
    <a href="../auth/logout.php" class="logout">🚪 Logout</a>
  </div>

  <!-- MAIN -->
  <div class="main">

    <div class="form-card">

      <h2>Apply Leave</h2>

      <form method="POST">
        <input type="date" name="date" required>

        <select name="type">
          <option value="full">Full Day</option>
          <option value="half">Half Day</option>
        </select>

        <input name="reason" placeholder="Reason (optional)">

        <button name="apply">Apply Leave</button>
      </form>

      <?php
      if (isset($_POST['apply'])) {

        $date = $_POST['date'];
        $type = $_POST['type'];
        $reason = $_POST['reason'];

        $employee_id = $user['id'];

        $conn->query("
          INSERT INTO leave_requests (employee_id, date, type, reason, status)
          VALUES ($employee_id, '$date', '$type', '$reason', 'pending')
        ");

        echo "<div class='success'>Leave Applied Successfully ✅</div>";
      }
      ?>

      <a href="dashboard.php" class="back-btn">⬅ Go Back</a>

    </div>

  </div>

</div>

</body>
</html>