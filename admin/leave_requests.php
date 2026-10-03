<?php
session_start();
include("../config/db.php");
require_once "../config/branch_helper.php";

ensureLeaveRequestColumns($conn);

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

// Fetch all leave requests for this branch
$res = $conn->query("
    SELECT 
        lr.*,
        u.name AS employee_name,
        u.employee_id AS employee_code
    FROM leave_requests lr
    LEFT JOIN users u ON lr.employee_id = u.id
    WHERE (u.branch_id = '$branchId' OR u.branch = '$branch')
    ORDER BY lr.id DESC
");

$rawRows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rawRows[] = $r;
    }
}

// Group multiple leaves requested by an employee into a single row
$groupedRequests = [];
$groupIndexMap = [];

foreach ($rawRows as $row) {
    $empId = (int)$row['employee_id'];
    $gid = !empty($row['group_id']) ? trim($row['group_id']) : null;
    $date = $row['date'];
    $reasonKey = strtolower(trim($row['reason'] ?? ''));
    $typeKey = trim($row['type'] ?? '');
    $statusKey = strtolower(trim($row['status'] ?? ''));

    $matchedIdx = null;

    if ($gid !== null && $gid !== '') {
        $key = 'gid_' . $gid;
        if (isset($groupIndexMap[$key])) {
            $matchedIdx = $groupIndexMap[$key];
        } else {
            $matchedIdx = count($groupedRequests);
            $groupIndexMap[$key] = $matchedIdx;
            $groupedRequests[$matchedIdx] = [
                'id' => (int)$row['id'],
                'group_id' => $gid,
                'employee_id' => $empId,
                'employee_name' => $row['employee_name'] ?? 'Unknown',
                'employee_code' => $row['employee_code'] ?? '-',
                'type' => $row['type'],
                'reason' => $row['reason'],
                'status' => $row['status'],
                'ids' => [(int)$row['id']],
                'dates' => [$date]
            ];
            continue;
        }
    } else {
        // Fallback for un-grouped or legacy records:
        // Group if same employee, type, reason, status where dates are within 7 days
        foreach ($groupedRequests as $idx => $grp) {
            if (!empty($grp['group_id'])) continue;
            if ($grp['employee_id'] === $empId 
                && strtolower(trim($grp['type'])) === strtolower($typeKey)
                && strtolower(trim($grp['reason'] ?? '')) === $reasonKey
                && strtolower(trim($grp['status'])) === $statusKey) {
                
                $rowTs = strtotime($date);
                $isClose = false;
                foreach ($grp['dates'] as $gd) {
                    if (abs($rowTs - strtotime($gd)) <= (7 * 86400)) {
                        $isClose = true;
                        break;
                    }
                }
                if ($isClose) {
                    $matchedIdx = $idx;
                    break;
                }
            }
        }
    }

    if ($matchedIdx !== null) {
        $groupedRequests[$matchedIdx]['ids'][] = (int)$row['id'];
        if (!in_array($date, $groupedRequests[$matchedIdx]['dates'])) {
            $groupedRequests[$matchedIdx]['dates'][] = $date;
        }
    } else {
        $newIdx = count($groupedRequests);
        $groupedRequests[$newIdx] = [
            'id' => (int)$row['id'],
            'group_id' => null,
            'employee_id' => $empId,
            'employee_name' => $row['employee_name'] ?? 'Unknown',
            'employee_code' => $row['employee_code'] ?? '-',
            'type' => $row['type'],
            'reason' => $row['reason'],
            'status' => $row['status'],
            'ids' => [(int)$row['id']],
            'dates' => [$date]
        ];
    }
}

// Calculate pending requests count for badge
$pendingCount = 0;
foreach ($groupedRequests as &$item) {
    sort($item['dates']);
    $item['total_days'] = count($item['dates']);
    $item['ids_csv'] = implode(',', $item['ids']);
    if (strtolower($item['status']) === 'pending') {
        $pendingCount++;
    }
}
unset($item);
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
      font-size: 18px;
    }

    /* ===== CARD ===== */
    .card {
      background: white;
      padding: 24px;
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
      font-weight: 600;
      text-align: center;
    }

    td {
      padding: 14px 12px;
      text-align: center;
      border-bottom: 1px solid #f1f5f9;
      font-size: 13px;
      vertical-align: middle;
    }

    tr:hover {
      background: #f8fafc;
    }

    /* ===== STATUS ===== */
    .status {
      font-weight: 600;
      font-size: 12px;
      padding: 4px 10px;
      border-radius: 20px;
      display: inline-block;
    }

    .status-pending {
      background: #fef3c7;
      color: #d97706;
    }

    .status-approved {
      background: #dcfce7;
      color: #15803d;
    }

    .status-rejected {
      background: #fee2e2;
      color: #b91c1c;
    }

    /* ===== BUTTONS ===== */
    .btn {
      padding: 6px 12px;
      border-radius: 6px;
      text-decoration: none;
      color: white;
      font-size: 12px;
      font-weight: 500;
      margin: 2px;
      display: inline-flex; 
      align-items: center;
      gap: 4px;
      border: none;
      outline: none;
      cursor: pointer;
      transition: 0.2s;
    }

    .btn-green { background: #22c55e; }
    .btn-green:hover { background: #16a34a; }

    .btn-red { background: #ef4444; }
    .btn-red:hover { background: #dc2626; }

    /* ===== MULTI-DATE STYLES ===== */
    .date-summary-box {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }

    .date-summary-title {
      font-weight: 600;
      color: #1e293b;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      justify-content: center;
    }

    .days-count-badge {
      background: #e0e7ff;
      color: #4338ca;
      font-size: 11px;
      font-weight: 600;
      padding: 2px 8px;
      border-radius: 12px;
      white-space: nowrap;
    }

    .date-tag-wrap {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
      justify-content: center;
      max-width: 280px;
      margin-top: 3px;
    }

    .date-mini-tag {
      display: inline-block;
      background: #f1f5f9;
      color: #475569;
      border: 1px solid #cbd5e1;
      padding: 1px 7px;
      border-radius: 5px;
      font-size: 11px;
      font-weight: 500;
      white-space: nowrap;
    }
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
    <a href="leave_requests.php" class="active">📩 Manage Leaves <?php if($pendingCount > 0) { ?><span style="background:#ef4444; color:white; padding:2px 8px; border-radius:50px; font-size:12px; margin-left:8px; font-weight:600;"><?= $pendingCount ?></span><?php } ?></a>
    <a href="add_leave.php">📅 Company Leaves</a>
    <a href="reports.php">📊 Reports</a>
    <a href="employee_settings.php">⚙️ Employee Settings</a>
    <a href="../auth/logout.php" class="logout">🚪 Logout</a>
  </div>

  <!-- MAIN -->
  <div class="main">

    <div class="header">
      Leave Requests Management
    </div>

    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
        <h3 style="font-size:17px; font-weight:600; color:#1e293b;">All Leave Requests</h3>
        <span style="font-size:13px; color:#64748b;">
          Total Applications: <strong><?= count($groupedRequests) ?></strong>
        </span>
      </div>

    <table>
    <thead>
        <tr>
          <th>Employee Name</th>
          <th>Employee ID</th>
          <th>Date(s)</th>
          <th>Type</th>
          <th>Reason</th>
          <th>Status</th>
          <th>Request</th>
          <th>Actions</th>
        </tr>
    </thead>

    <tbody id="leaveTableBody">

        <?php if (empty($groupedRequests)): ?>
        <tr>
          <td colspan="8" style="padding: 30px; color: #94a3b8; font-size: 14px;">
            No leave requests found.
          </td>
        </tr>
        <?php else: ?>
        <?php 
        foreach ($groupedRequests as $item) { 
            $dates = $item['dates'];
            $count = $item['total_days'];
            $primaryId = $item['id'];
            $idsCsv = $item['ids_csv'];
            $statusLower = strtolower($item['status']);

            $isConsecutive = true;
            for ($i = 0; $i < $count - 1; $i++) {
                if ((strtotime($dates[$i + 1]) - strtotime($dates[$i])) !== 86400) {
                    $isConsecutive = false;
                    break;
                }
            }
        ?>

        <tr id="row-<?= $primaryId ?>">
          <td style="font-weight: 500; text-align: left; padding-left: 16px;"><?= htmlspecialchars($item['employee_name'] ?? 'Unknown') ?></td>
          <td><?= htmlspecialchars($item['employee_code'] ?? '-') ?></td>
          <td>
            <?php if ($count === 1): ?>
              <div style="font-weight: 500;"><?= date('d M Y', strtotime($dates[0])) ?></div>
            <?php else: ?>
              <div class="date-summary-box">
                <div class="date-summary-title">
                  <?php if ($isConsecutive): ?>
                    <span><?= date('d M Y', strtotime($dates[0])) ?> – <?= date('d M Y', strtotime($dates[$count - 1])) ?></span>
                  <?php else: ?>
                    <span><?= $count ?> Days Selected</span>
                  <?php endif; ?>
                  <span class="days-count-badge"><?= $count ?> Days</span>
                </div>
                <div class="date-tag-wrap">
                  <?php foreach ($dates as $d): ?>
                    <span class="date-mini-tag"><?= date('d M', strtotime($d)) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($item['type']) ?></td>
          <td style="max-width: 220px; word-break: break-word;"><?= !empty($item['reason']) ? htmlspecialchars($item['reason']) : '-' ?></td>

          <td id="status-<?= $primaryId ?>">
            <span class="status status-<?= $statusLower ?>">
              <?= ucfirst($item['status']) ?>
            </span>
          </td>

          <td id="actions-<?= $primaryId ?>">
            <?php if ($statusLower === 'pending') { ?>
              <button
                class="btn btn-green"
                onclick="updateLeaveStatus('<?= $idsCsv ?>', 'approved', <?= $primaryId ?>, <?= $count ?>)">
                Approve<?= $count > 1 ? " ($count)" : "" ?>
              </button>

              <button
                class="btn btn-red"
                onclick="updateLeaveStatus('<?= $idsCsv ?>', 'rejected', <?= $primaryId ?>, <?= $count ?>)">
                Reject<?= $count > 1 ? " ($count)" : "" ?>
              </button>
            <?php } else { ?>
              <span class="status status-<?= $statusLower ?>">
                <?= ucfirst($item['status']) ?>
              </span>
            <?php } ?>
          </td>

          <td>
            <a class="btn btn-red"
              onclick="return confirm('Delete this leave request<?= $count > 1 ? " ($count days)" : "" ?>?')"
              href="delete_leave.php?ids=<?= $idsCsv ?>">
              Delete
            </a>
          </td>
        </tr>

        <?php } ?>
        <?php endif; ?>

    </tbody>
</table>
    </div>

  </div>

</div>
<script>

function updateLeaveStatus(ids, status, rowId, count = 1) {
    const statusCap = status.charAt(0).toUpperCase() + status.slice(1);
    const actionsCell = document.getElementById(`actions-${rowId}`);
    const originalActionsHtml = actionsCell ? actionsCell.innerHTML : '';

    if (actionsCell) {
        actionsCell.innerHTML = `<span style="font-size:12px; color:#64748b;"><i class="bi bi-hourglass-split"></i> Updating...</span>`;
    }

    fetch(`../api/leave_action.php?ids=${ids}&status=${status}&ajax=1`)
    .then(res => res.json())
    .then(data => {
        // Update status badge
        const statusCell = document.getElementById(`status-${rowId}`);
        if (statusCell) {
            statusCell.innerHTML = `
                <span class="status status-${status}">
                    ${statusCap}
                </span>
            `;
        }

        // Remove buttons and show final status
        if (actionsCell) {
            actionsCell.innerHTML = `
                <span class="status status-${status}">
                    ${statusCap}
                </span>
            `;
        }

        const daysText = count > 1 ? ` for ${count} days` : '';
        showToast(`Leave ${status}${daysText} successfully ✅`);
    })
    .catch(err => {
        console.error(err);
        showToast("Something went wrong", true);
        if (actionsCell) {
            actionsCell.innerHTML = originalActionsHtml;
        }
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
    toast.style.transition = "opacity 0.3s ease";

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = "0";
        setTimeout(() => {
            toast.remove();
        }, 300);
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