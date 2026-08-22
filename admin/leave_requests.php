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

$branchId = $_SESSION['user']['branch_id'] ?? $_SESSION['branch_id'] ?? 0;
$branch = $_SESSION['user']['branch'] ?? $_SESSION['branch'] ?? '';

$bStmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ? OR LOWER(branch_name) = LOWER(?)");
$bStmt->bind_param("is", $branchId, $branch);
$bStmt->execute();
$bRes = $bStmt->get_result()->fetch_assoc();
$branchName = $bRes ? $bRes['branch_name'] : ucfirst($branch);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Leave Requests</title>

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
      border:none;
      outline:none;
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
   <h2><?= htmlspecialchars($branchName) ?> Admin</h2>

    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="create_employee.php">👤 Create Employee</a>
    <a href="../api/checkin.php">🟢 Check In- Morning</a>
    <a href="../api/lunch.php">🍽️ Lunch Break</a>
    <a href="../api/checkout.php">🔴 Check Out- Evening</a>
    <?php
      $adminBranchId = $_SESSION['user']['branch_id'] ?? $_SESSION['branch_id'] ?? 0;
      $adminBranch = $_SESSION['user']['branch'] ?? $_SESSION['branch'] ?? '';
      $leaveCountQuery = $conn->query("SELECT COUNT(*) as total FROM leave_requests lr LEFT JOIN users u ON lr.employee_id = u.id WHERE (u.branch_id='$adminBranchId' OR u.branch='$adminBranch') AND lr.status='pending'");
      $leaveCount = $leaveCountQuery ? $leaveCountQuery->fetch_assoc()['total'] : 0;
    ?>
    <a href="leave_requests.php" class="active">📩 Manage Leaves <?php if($leaveCount > 0) { ?><span style="background:#ef4444; color:white; padding:2px 8px; border-radius:50px; font-size:12px; margin-left:8px; font-weight:600;"><?= $leaveCount ?></span><?php } ?></a>
    <a href="add_leave.php">📅 Company Leaves</a>
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

    WHERE (u.branch_id = '$branchId' OR u.branch = '$branch')

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

          <?php if (strtolower($row['status']) == 'pending') { ?>

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

    fetch(`../api/leave_action.php?id=${id}&status=${status}&ajax=1`)
    .then(res => res.json())
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


function checkAutoRedirect() {

    const now = new Date();
    const hours = now.getHours();
    const minutes = now.getMinutes();

    // 9:30 AM to 9:40 AM CHECK-IN (ONLY ONCE)
    if (hours === 9 && minutes >= 30 && minutes <= 40) {

        if (!localStorage.getItem("auto_checkin_done")) {
            localStorage.setItem("auto_checkin_done", "1");
            window.location.href = "../api/checkin.php";
        }
    }

    // 5:24 PM CHECK-OUT (ONLY ONCE)
    if (hours === 17 && minutes === 24) {

        if (!localStorage.getItem("auto_checkout_done")) {
            localStorage.setItem("auto_checkout_done", "1");
            window.location.href = "../api/checkout.php";
        }
    }
}
</script>
</body>
</html>