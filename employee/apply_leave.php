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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {

    $date = $conn->real_escape_string($_POST['date']);
    $type = $conn->real_escape_string($_POST['type']);
    $reason = $conn->real_escape_string($_POST['reason']);

    $employee_id = (int)$user['id'];

    $insert = $conn->query("
        INSERT INTO leave_requests
        (
            employee_id,
            date,
            type,
            reason,
            status
        )
        VALUES
        (
            $employee_id,
            '$date',
            '$type',
            '$reason',
            'pending'
        )
    ");

    header('Content-Type: application/json');

    echo json_encode([
        'success' => $insert ? true : false
    ]);

    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Apply Leave</title>

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
      color: #334155;
    }

    /* ===== LAYOUT ===== */
    .layout {
      display: flex;
      min-height: 100vh;
    }

    /* =========================
       MOBILE HEADER (HAMBURGER)
    ========================= */
    .mobile-top-bar {
      display: none;
      background: #111827;
      color: white;
      padding: 15px 20px;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .mobile-top-bar h2 {
      font-size: 16px;
      font-weight: 500;
    }

    .hamburger-btn {
      background: none;
      border: none;
      color: white;
      font-size: 24px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content:end;
    }

    /* ===== SIDEBAR (MATCHED) ===== */
    .sidebar {
      width: 260px;
      background: linear-gradient(180deg, #111827, #1f2937);
      color: white;
      padding: 24px;
      position: sticky;
      top: 0;
      height: 100vh;
      display: flex;
      flex-direction: column;
      transition: transform 0.3s ease;
      z-index: 999;
    }

    .sidebar h2 {
      margin-bottom: 30px;
      text-align: center;
      font-size: 20px;
      font-weight: 600;
    }

    .sidebar a {
      display: block;
      padding: 12px 16px;
      margin: 6px 0;
      color: #cbd5e1;
      text-decoration: none;
      border-radius: 8px;
      transition: 0.3s;
      font-size: 14px;
    }

    .sidebar a:hover {
      background: rgba(255,255,255,0.08);
      color: white;
      transform: translateX(4px);
    }

    .sidebar .logout {
      background: #ef4444;
      color: white;
      margin-top: auto;
      text-align: center;
    }

    .sidebar .logout:hover {
      background: #dc2626;
      transform: none;
    }

    /* Overlay for Mobile Navigation drawer */
    .sidebar-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(0, 0, 0, 0.5);
      z-index: 998;
    }

    /* ===== MAIN ===== */
    .main {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 40px;
    }

    /* ===== CARD ===== */
    .form-card {
      width: 100%;
      max-width: 450px;
      background: white;
      padding: 30px;
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.05);
      text-align: center;
      animation: fadeIn 0.4s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .form-card h2 {
      margin-bottom: 24px;
      color: #111827;
      font-size: 22px;
      font-weight: 600;
    }

    .form-group {
      text-align: left;
      margin-bottom: 18px;
    }

    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: #475569;
      margin-bottom: 6px;
    }

    input, select {
      width: 100%;
      padding: 12px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      outline: none;
      transition: 0.3s;
      font-size: 14px;
      color: #334155;
      background-color: #fff;
    }

    input:focus, select:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }

    button {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 500;
      font-size: 15px;
      cursor: pointer;
      transition: 0.2s ease;
      margin-top: 6px;
    }

    button:hover {
      opacity: 0.95;
      transform: translateY(-1px);
    }
    
    button:active {
      transform: translateY(0);
    }

    .success {
      margin-top: 15px;
      padding: 12px;
      background: #dcfce7;
      color: #15803d;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
    }

    .back-btn {
      display: inline-block;
      margin-top: 20px;
      padding: 10px 18px;
      background: #f1f5f9;
      color: #475569;
      text-decoration: none;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      transition: 0.2s;
    }

    .back-btn:hover {
      background: #e2e8f0;
      color: #1e293b;
    }

    /* ==================================
       RESPONSIVE DESIGN SYSTEM
    ================================== */
    @media (max-width: 992px) {
      .layout {
        flex-direction: column;
      }

      .mobile-top-bar {
        display: flex;
      }

      .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 280px;
        transform: translateX(-100%);
        box-shadow: 5px 0 15px rgba(0,0,0,0.2);
      }

      .sidebar.active {
        transform: translateX(0);
      }

      .sidebar.active + .sidebar-overlay {
        display: block;
      }

      .sidebar .logout {
        margin-top: 40px; 
      }

      .main {
        padding: 30px 20px;
      }
    }

    @media (max-width: 480px) {
      .form-card {
        padding: 20px;
      }
    }
  </style>
</head>

<body>

<div class="mobile-top-bar">
  <h2>Employee Panel</h2>
  <button class="hamburger-btn" id="menuToggle">☰</button>
</div>

<div class="layout">

  <div class="sidebar" id="sidebar">
    <h2>Employee Panel</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="apply_leave.php">Apply Leave</a>
    <a href="../auth/logout.php" class="logout">🚪 Logout</a>
  </div>
  
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <div class="main">

    <div class="form-card">

      <h2>Apply Leave</h2>

    <form id="leaveForm">

    <div class="form-group">
        <label>Select Date</label>
        <input type="date" name="date" required>
    </div>

    <div class="form-group">
        <label>Leave Type</label>

        <select name="type">
            <option value="Full Day">Full Day</option>
            <option value="Half Day">Half Day</option>
        </select>
    </div>

    <div class="form-group">
        <label>Reason for Leave</label>

        <input
            type="text"
            name="reason"
            placeholder="Reason (optional)"
        >
    </div>

    <button type="submit">
        Apply Leave
    </button>

</form>

   

      <a href="dashboard.php" class="back-btn">⬅ Go Back</a>

    </div>

  </div>

</div>

<script>
  const menuToggle = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  function toggleMenu() {
    sidebar.classList.toggle('active');
  }

  menuToggle.addEventListener('click', toggleMenu);
  sidebarOverlay.addEventListener('click', toggleMenu);
</script>
<script>
function showToast(message, error = false) {

    const toast = document.createElement("div");

    toast.innerText = message;

    toast.style.position = "fixed";
    toast.style.top = "20px";
    toast.style.right = "20px";
    toast.style.padding = "12px 18px";
    toast.style.borderRadius = "10px";
    toast.style.color = "white";
    toast.style.fontWeight = "600";
    toast.style.zIndex = "9999";
    toast.style.background =
        error ? "#ef4444" : "#22c55e";

    toast.style.boxShadow =
        "0 5px 15px rgba(0,0,0,0.15)";

    toast.style.animation =
        "slideIn 0.3s ease";

    document.body.appendChild(toast);

    setTimeout(() => {

        toast.style.opacity = "0";

        setTimeout(() => {
            toast.remove();
        }, 300);

    }, 2500);
}



</script>

<script>
document.getElementById("leaveForm")
.addEventListener("submit", async function(e){

    e.preventDefault();

    const formData = new FormData(this);
    formData.append("ajax", "1");

    try {

        const response = await fetch(
            "apply_leave.php",
            {
                method: "POST",
                body: formData
            }
        );

        const data = await response.json();

        if(data.success){

            showToast("Leave Applied Successfully ✅");

            this.reset();

        } else {

            showToast("Failed to Apply Leave", true);
        }

    } catch(error){

        console.error(error);

        showToast("Something went wrong", true);
    }

});
</script>
</body>
</html>