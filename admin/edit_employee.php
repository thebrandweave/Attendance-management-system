<?php
include("../config/db.php");
require_once "../config/branch_helper.php";

ensureEmployeeSettingsColumns($conn);

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
    header("Location: ../index.php");
    exit();
}

$id = intval($_GET['id'] ?? 0);

$employee = $conn->query("
    SELECT * FROM users 
    WHERE id=$id AND role='employee'
")->fetch_assoc();

if (!$employee) {
    die("Employee not found");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $working_hours = isset($_POST['working_hours']) ? max(0, min(24, (float)$_POST['working_hours'])) : 8.00;
    $monthly_cl = isset($_POST['monthly_cl']) ? max(0, min(31, (float)$_POST['monthly_cl'])) : 2.00;
    $check_in_days = !empty($_POST['check_in_days']) ? (is_array($_POST['check_in_days']) ? implode(',', $_POST['check_in_days']) : trim($_POST['check_in_days'])) : 'Mon,Tue,Wed,Thu,Fri,Sat';

    $stmt = $conn->prepare("
        UPDATE users 
        SET name=?, working_hours=?, monthly_cl=?, check_in_days=?
        WHERE id=?
    ");
    $stmt->bind_param("sddsi", $name, $working_hours, $monthly_cl, $check_in_days, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Employee</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body{
            font-family:'Poppins',sans-serif;
            background:#eef2f7;
            display:flex;
            justify-content:center;
            align-items:center;
            min-height:100vh;
            padding:20px;
        }

        .box{
            background:white;
            padding:30px;
            border-radius:14px;
            width:400px;
            max-width:100%;
            box-shadow:0 8px 25px rgba(0,0,0,0.08);
        }

        h2{
            margin-bottom:8px;
            color:#111827;
        }

        .sub {
            font-size:13px;
            color:#6b7280;
            margin-bottom:20px;
        }

        label {
            display:block;
            font-size:12.5px;
            font-weight:600;
            color:#374151;
            margin-bottom:6px;
        }

        input{
            width:100%;
            padding:11px 14px;
            border:1px solid #d1d5db;
            border-radius:8px;
            margin-bottom:16px;
            font-family:inherit;
            font-size:14px;
        }

        input:focus {
            outline:none;
            border-color:#667eea;
            box-shadow:0 0 0 3px rgba(102,126,234,0.15);
        }

        button{
            width:100%;
            padding:12px;
            border:none;
            border-radius:8px;
            background:linear-gradient(135deg, #667eea, #764ba2);
            color:white;
            font-weight:600;
            cursor:pointer;
            font-family:inherit;
            font-size:14px;
            transition:0.2s;
        }

        button:hover{
            opacity:0.95;
            transform:translateY(-1px);
        }

        .back-link {
            display:inline-block;
            margin-top:15px;
            text-align:center;
            width:100%;
            color:#6b7280;
            text-decoration:none;
            font-size:13px;
        }
        .days-selector { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 5px; margin-bottom: 8px; }
        .day-pill { padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; border: 1.5px solid #d1d5db; background: #f9fafb; color: #4b5563; cursor: pointer; user-select: none; transition: 0.2s; }
        .day-pill:hover { border-color: #667eea; color: #667eea; }
        .day-pill.selected { background: #667eea; border-color: #667eea; color: white; }
        .quick-presets { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
        .btn-preset { padding: 2px 7px; font-size: 11px; border-radius: 4px; border: 1px dashed #9ca3af; background: white; color: #4b5563; cursor: pointer; font-family: inherit; }
        .btn-preset:hover { background: #e0e7ff; border-color: #667eea; color: #4338ca; }
    </style>
</head>
<body>

<div class="box">
    <h2>Edit Employee</h2>
    <div class="sub">ID: <?= htmlspecialchars($employee['employee_id']) ?></div>

    <form method="POST">
        <label>Employee Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($employee['name']) ?>" required>

        <label>Daily Working Hours</label>
        <input type="number" step="0.5" min="1" max="24" name="working_hours" value="<?= htmlspecialchars($employee['working_hours'] ?? '8.0') ?>" required>

        <label>Monthly CL Allowance</label>
        <input type="number" step="0.5" min="0" max="31" name="monthly_cl" value="<?= htmlspecialchars($employee['monthly_cl'] ?? '2.0') ?>" required>

        <label>Specific Check-in Days</label>
        <input type="hidden" name="check_in_days" id="editCheckInDaysInput" value="<?= htmlspecialchars($employee['check_in_days'] ?? 'Mon,Tue,Wed,Thu,Fri,Sat') ?>">
        <?php 
          $currDays = explode(',', (string)($employee['check_in_days'] ?? 'Mon,Tue,Wed,Thu,Fri,Sat'));
          $currDays = array_map('trim', $currDays);
        ?>
        <div class="days-selector" id="editDaysSelector">
          <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
            <div class="day-pill <?= in_array($d, $currDays) ? 'selected' : '' ?>" data-day="<?= $d ?>" onclick="toggleEditDay(this)">
              <?= $d ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="quick-presets">
          <button type="button" class="btn-preset" onclick="setEditPreset('mon-sat')">Mon - Sat</button>
          <button type="button" class="btn-preset" onclick="setEditPreset('mon-fri')">Mon - Fri</button>
          <button type="button" class="btn-preset" onclick="setEditPreset('all')">All Days</button>
        </div>

        <button type="submit">Update Employee</button>
        <a href="dashboard.php" class="back-link">← Cancel and Back</a>
    </form>
</div>

<script>
function toggleEditDay(el) {
  el.classList.toggle('selected');
  updateEditDays();
}
function updateEditDays() {
  const selected = [];
  document.querySelectorAll('#editDaysSelector .day-pill.selected').forEach(pill => {
    selected.push(pill.getAttribute('data-day'));
  });
  document.getElementById('editCheckInDaysInput').value = selected.join(',');
}
function setEditPreset(preset) {
  document.querySelectorAll('#editDaysSelector .day-pill').forEach(pill => {
    const d = pill.getAttribute('data-day');
    if (preset === 'all') {
      pill.classList.add('selected');
    } else if (preset === 'mon-sat') {
      pill.classList.toggle('selected', d !== 'Sun');
    } else if (preset === 'mon-fri') {
      pill.classList.toggle('selected', d !== 'Sat' && d !== 'Sun');
    }
  });
  updateEditDays();
}
</script>
</body>
</html>