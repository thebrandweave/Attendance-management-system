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
    $shift_start = !empty($_POST['shift_start']) ? date("H:i:s", strtotime($_POST['shift_start'])) : '09:30:00';
    $shift_end = !empty($_POST['shift_end']) ? date("H:i:s", strtotime($_POST['shift_end'])) : '20:00:00';
    $working_hours = isset($_POST['working_hours']) ? max(0, min(24, (float)$_POST['working_hours'])) : 10.50;
    $monthly_cl = isset($_POST['monthly_cl']) ? max(0, min(31, (float)$_POST['monthly_cl'])) : 2.00;
    $check_in_days = !empty($_POST['check_in_days']) ? (is_array($_POST['check_in_days']) ? implode(',', $_POST['check_in_days']) : trim($_POST['check_in_days'])) : 'Mon,Tue,Wed,Thu,Fri,Sat';

    $stmt = $conn->prepare("
        UPDATE users 
        SET name=?, shift_start=?, shift_end=?, working_hours=?, monthly_cl=?, check_in_days=?
        WHERE id=?
    ");
    $stmt->bind_param("sssddsi", $name, $shift_start, $shift_end, $working_hours, $monthly_cl, $check_in_days, $id);
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

        <?php
          $eStartParts = [
              'hour' => !empty($employee['shift_start']) ? date('h', strtotime($employee['shift_start'])) : '09',
              'minute' => !empty($employee['shift_start']) ? date('i', strtotime($employee['shift_start'])) : '30',
              'ampm' => !empty($employee['shift_start']) ? date('A', strtotime($employee['shift_start'])) : 'AM'
          ];
          $eEndParts = [
              'hour' => !empty($employee['shift_end']) ? date('h', strtotime($employee['shift_end'])) : '08',
              'minute' => !empty($employee['shift_end']) ? date('i', strtotime($employee['shift_end'])) : '00',
              'ampm' => !empty($employee['shift_end']) ? date('A', strtotime($employee['shift_end'])) : 'PM'
          ];
        ?>
        <div style="display:flex; gap:12px; margin-top:12px;">
          <div style="flex:1;">
            <label>Shift Start Time (12-hr)</label>
            <div style="display:flex; align-items:center; background:#f9fafb; border:1px solid #d1d5db; border-radius:8px; padding:2px 8px; margin-top:5px; height:42px;">
              <select id="editStartHour" onchange="syncEditTimes()" style="border:none; background:transparent; font-weight:600; font-size:14px; outline:none; cursor:pointer;">
                <?php for ($h = 1; $h <= 12; $h++): $hStr = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                  <option value="<?= $hStr ?>" <?= $hStr === $eStartParts['hour'] ? 'selected' : '' ?>><?= $hStr ?></option>
                <?php endfor; ?>
              </select>
              <span style="font-weight:700; color:#9ca3af; margin:0 2px;">:</span>
              <select id="editStartMin" onchange="syncEditTimes()" style="border:none; background:transparent; font-weight:600; font-size:14px; outline:none; cursor:pointer;">
                <?php for ($m = 0; $m < 60; $m++): $mStr = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                  <option value="<?= $mStr ?>" <?= $mStr === $eStartParts['minute'] ? 'selected' : '' ?>><?= $mStr ?></option>
                <?php endfor; ?>
              </select>
              <select id="editStartPeriod" onchange="syncEditTimes()" style="border:none; background:#eff6ff; color:#1d4ed8; font-weight:700; font-size:13px; border-radius:6px; padding:3px 6px; margin-left:auto; outline:none; cursor:pointer;">
                <option value="AM" <?= $eStartParts['ampm'] === 'AM' ? 'selected' : '' ?>>AM</option>
                <option value="PM" <?= $eStartParts['ampm'] === 'PM' ? 'selected' : '' ?>>PM</option>
              </select>
            </div>
            <input type="hidden" name="shift_start" id="editShiftStart" value="<?= htmlspecialchars($employee['shift_start'] ?? '09:30:00') ?>">
          </div>
          <div style="flex:1;">
            <label>Shift End Time (12-hr)</label>
            <div style="display:flex; align-items:center; background:#f9fafb; border:1px solid #d1d5db; border-radius:8px; padding:2px 8px; margin-top:5px; height:42px;">
              <select id="editEndHour" onchange="syncEditTimes()" style="border:none; background:transparent; font-weight:600; font-size:14px; outline:none; cursor:pointer;">
                <?php for ($h = 1; $h <= 12; $h++): $hStr = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                  <option value="<?= $hStr ?>" <?= $hStr === $eEndParts['hour'] ? 'selected' : '' ?>><?= $hStr ?></option>
                <?php endfor; ?>
              </select>
              <span style="font-weight:700; color:#9ca3af; margin:0 2px;">:</span>
              <select id="editEndMin" onchange="syncEditTimes()" style="border:none; background:transparent; font-weight:600; font-size:14px; outline:none; cursor:pointer;">
                <?php for ($m = 0; $m < 60; $m++): $mStr = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                  <option value="<?= $mStr ?>" <?= $mStr === $eEndParts['minute'] ? 'selected' : '' ?>><?= $mStr ?></option>
                <?php endfor; ?>
              </select>
              <select id="editEndPeriod" onchange="syncEditTimes()" style="border:none; background:#eff6ff; color:#1d4ed8; font-weight:700; font-size:13px; border-radius:6px; padding:3px 6px; margin-left:auto; outline:none; cursor:pointer;">
                <option value="AM" <?= $eEndParts['ampm'] === 'AM' ? 'selected' : '' ?>>AM</option>
                <option value="PM" <?= $eEndParts['ampm'] === 'PM' ? 'selected' : '' ?>>PM</option>
              </select>
            </div>
            <input type="hidden" name="shift_end" id="editShiftEnd" value="<?= htmlspecialchars($employee['shift_end'] ?? '20:00:00') ?>">
          </div>
        </div>

        <label style="margin-top:12px;">Daily Working Hours</label>
        <input type="number" step="0.5" min="1" max="24" name="working_hours" id="editWorkingHours" value="<?= htmlspecialchars($employee['working_hours'] ?? '10.5') ?>" required>

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
function syncEditTimes() {
  function partsTo24Hr(h, m, p) {
    let hour = parseInt(h, 10);
    const minute = String(parseInt(m, 10)).padStart(2, '0');
    if (p === 'PM' && hour < 12) hour += 12;
    if (p === 'AM' && hour === 12) hour = 0;
    return String(hour).padStart(2, '0') + ':' + minute + ':00';
  }
  const sH = document.getElementById('editStartHour').value;
  const sM = document.getElementById('editStartMin').value;
  const sP = document.getElementById('editStartPeriod').value;
  const s24 = partsTo24Hr(sH, sM, sP);
  document.getElementById('editShiftStart').value = s24;

  const eH = document.getElementById('editEndHour').value;
  const eM = document.getElementById('editEndMin').value;
  const eP = document.getElementById('editEndPeriod').value;
  const e24 = partsTo24Hr(eH, eM, eP);
  document.getElementById('editShiftEnd').value = e24;

  // Auto-calculate daily working hours
  const [h1, m1] = s24.split(':').map(Number);
  const [h2, m2] = e24.split(':').map(Number);
  let startMinutes = h1 * 60 + m1;
  let endMinutes = h2 * 60 + m2;
  if (endMinutes <= startMinutes) {
    endMinutes += 24 * 60;
  }
  const diffMinutes = endMinutes - startMinutes;
  const decimalHours = parseFloat((diffMinutes / 60).toFixed(1));
  const whInput = document.getElementById('editWorkingHours');
  if (whInput && decimalHours > 0) {
    whInput.value = decimalHours;
  }
}

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