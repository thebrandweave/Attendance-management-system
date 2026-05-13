<?php
session_start();
include("../config/db.php");

if (
    !isset($_SESSION['user']) ||
    $_SESSION['user']['role'] != "admin"
) {
    header("Location: ../index.php");
    exit();
}

$branch = $_SESSION['user']['branch'];
?>

<!DOCTYPE html>
<html>
<head>
  <title>Leave Requests</title>

  <!-- FONT -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

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

    /* ===== SIDEBAR (MATCHED STYLE) ===== */
    .sidebar {
      width: 250px;
      background: linear-gradient(180deg, #111827, #1f2937);
      color: white;
      padding: 20px;
    }

    .sidebar h2 {
      text-align: center;
      font-size:20px;
      margin-bottom: 25px;
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
      padding: 25px;
    }

    .header {
      background: white;
      padding: 18px;
      border-radius: 12px;
      font-weight: 600;
      margin-bottom: 20px;
      box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    }

    /* ===== CARD ===== */
    .card {
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    /* ===== TABLE ===== */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
      overflow: hidden;
      border-radius: 10px;
    }

    th {
      background: #667eea;
      color: white;
      padding: 12px;
      font-size: 13px;
    }

    td {
      padding: 12px;
      text-align: center;
      border-bottom: 1px solid #eee;
      font-size: 13px;
    }

    tr:hover {
      background: #f9fafb;
    }

    /* ===== STATUS ===== */
   .status {
  font-weight: 600;
  font-size: 13px;
}

.status-pending {
  color: orange;
}

.status-approved {
  color: #22c55e;
}

.status-rejected {
  color: #ef4444;
}

    /* ===== BUTTONS ===== */
    .btn {
      padding: 6px 10px;
      border-radius: 6px;
      text-decoration: none;
      color: white;
      font-size: 12px;
      margin: 2px;
      display: inline-block;
    }

    .btn-green { background: #22c55e; }
    .btn-green:hover { background: #16a34a; }

    .btn-red { background: #ef4444; }
    .btn-red:hover { background: #dc2626; }

  </style>
</head>

<body>

<div class="layout">

  <!-- SIDEBAR -->
  <div class="sidebar">
   <h2><?= htmlspecialchars($branch) ?> Admin</h2>

    <a href="dashboard.php"> Dashboard</a>
    <a href="create_employee.php">👤 Create Employee</a>
       <a href="../api/checkin.php">🟢 Check In</a>
  <a href="../api/checkout.php">🔴 Check Out</a>
    <a href="leave_requests.php">📩 Leave Requests</a>
    <a href="reports.php">📊 Reports</a>
    <a href="../auth/logout.php" class="logout">🚪 Logout</a>
  </div>

  <!-- MAIN -->
  <div class="main">

    <div class="header">
      Leave Requests Management
    </div>

    <div class="card">
      <h3>All Leave Requests</h3>

    <table>
    <thead>
        <tr>
          <th>Employee Name</th>
          <th>Employee ID</th>
          <th>Date</th>
          <th>Type</th>
          <th>Reason</th>
          <th>Status</th>
          <th>Request</th>
          <th>Actions</th>
        </tr>
    </thead>

    <tbody id="leaveTableBody">

        <?php
      $res = $conn->query("
    SELECT 
        lr.*,
        u.name AS employee_name,
        u.employee_id AS employee_code

    FROM leave_requests lr

    LEFT JOIN users u
    ON lr.employee_id = u.id

    WHERE u.branch = '$branch'

    ORDER BY lr.id DESC
");

        while ($row = $res->fetch_assoc()) {
        ?>

        <tr>
          <td><?= $row['employee_name'] ?? 'Unknown' ?></td>
          <td><?= $row['employee_code'] ?? '-' ?></td>
          <td><?= $row['date'] ?></td>
          <td><?= $row['type'] ?></td>
          <td><?= $row['reason'] ?></td>

          <td id="status-<?= $row['id'] ?>">
            <span class="status status-<?= $row['status'] ?>">
              <?= ucfirst($row['status']) ?>
            </span>
          </td>

          <td id="actions-<?= $row['id'] ?>">

          <?php if ($row['status'] == 'pending') { ?>

            <button
              class="btn btn-green"
              onclick="updateLeaveStatus(<?= $row['id'] ?>, 'approved')">
              Approve
            </button>

            <button
              class="btn btn-red"
              onclick="updateLeaveStatus(<?= $row['id'] ?>, 'rejected')">
              Reject
            </button>

          <?php } else { ?>

            <span class="status status-<?= $row['status'] ?>">
              <?= ucfirst($row['status']) ?>
            </span>

          <?php } ?>

          </td>

          <td>
            <a class="btn btn-red"
              onclick="return confirm('Delete this leave request?')"
              href="delete_leave.php?id=<?= $row['id'] ?>">
              Delete
            </a>
          </td>
        </tr>

        <?php } ?>

    </tbody>
</table>
    </div>

  </div>

</div>
<script>

function updateLeaveStatus(id, status) {

    fetch(`../api/leave_action.php?id=${id}&status=${status}`)
    .then(res => res.text())
    .then(() => {

        // Update status badge
        document.getElementById(`status-${id}`).innerHTML = `
            <span class="status status-${status}">
                ${status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
        `;

        // Remove buttons
        document.getElementById(`actions-${id}`).innerHTML = `
            <span class="status status-${status}">
                ${status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
        `;

        showToast(`Leave ${status} successfully`);

    })
    .catch(() => {
        showToast("Something went wrong", true);
    });
}

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
    toast.style.background = error ? "#ef4444" : "#22c55e";
    toast.style.boxShadow = "0 5px 15px rgba(0,0,0,0.15)";

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 2500);
}

function loadLeaveRequests() {

   fetch("fetch_leave_requests.php?branch=<?= urlencode($branch) ?>")
    .then(res => res.text())
    .then(data => {

        document.getElementById("leaveTableBody").innerHTML = data;

    });

}

/* Auto refresh every 5 seconds */
setInterval(loadLeaveRequests, 5000);

</script>
</body>
</html>