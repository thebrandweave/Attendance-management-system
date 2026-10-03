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

    $datesInput = $_POST['dates'] ?? $_POST['date'] ?? '';
    $type = $conn->real_escape_string($_POST['type'] ?? 'Full Day');
    $reason = $conn->real_escape_string(trim($_POST['reason'] ?? ''));

    $employee_id = (int)$user['id'];

    // Support comma-separated strings or array of dates
    $rawDates = [];
    if (is_array($datesInput)) {
        $rawDates = $datesInput;
    } else {
        $parts = explode(',', (string)$datesInput);
        foreach ($parts as $p) {
            $p = trim($p);
            if (!empty($p)) {
                $rawDates[] = $p;
            }
        }
    }

    $validDates = [];
    foreach ($rawDates as $d) {
        $d = trim($d);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $validDates[] = $d;
        }
    }
    $validDates = array_values(array_unique($validDates));
    sort($validDates);

    header('Content-Type: application/json');

    if (empty($validDates)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please select at least one valid date.'
        ]);
        exit();
    }

    // Check if any of these dates already have pending or approved leave requests
    $dateListEscaped = "'" . implode("','", array_map([$conn, 'real_escape_string'], $validDates)) . "'";
    $existing = [];
    $chkRes = $conn->query("
        SELECT date, status 
        FROM leave_requests 
        WHERE employee_id = $employee_id 
          AND date IN ($dateListEscaped)
          AND status IN ('pending', 'approved')
    ");
    if ($chkRes) {
        while ($row = $chkRes->fetch_assoc()) {
            $existing[] = $row['date'];
        }
    }

    $datesToInsert = array_values(array_diff($validDates, $existing));

    if (empty($datesToInsert)) {
        echo json_encode([
            'success' => false,
            'message' => 'Leave already applied/approved for selected date(s): ' . implode(', ', $existing)
        ]);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO leave_requests (employee_id, date, type, reason, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");

    $insertedCount = 0;
    foreach ($datesToInsert as $d) {
        $stmt->bind_param("isss", $employee_id, $d, $type, $reason);
        if ($stmt->execute()) {
            $insertedCount++;
        }
    }
    $stmt->close();

    $message = ($insertedCount === 1)
        ? "Leave Applied Successfully for " . $datesToInsert[0] . " ✅"
        : "Leave Applied Successfully for $insertedCount days ✅";

    if (!empty($existing)) {
        $message .= "\n(Skipped already requested: " . implode(', ', $existing) . ")";
    }

    echo json_encode([
        'success' => ($insertedCount > 0),
        'count' => $insertedCount,
        'message' => $message,
        'inserted_dates' => $datesToInsert,
        'skipped_dates' => $existing
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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

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
      justify-content: end;
    }

    /* ===== SIDEBAR (MATCHED) ===== */
    .sidebar {
      width: 260px;
      background: linear-gradient(180deg, #111827, #1f2937);
      color: white;
      padding: 24px;
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      display: flex;
      flex-direction: column;
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 1001;
      overflow-y: auto;
      box-sizing: border-box;
    }

    .sidebar-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 25px;
      padding-bottom: 12px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-header h2 {
      font-size: 19px;
      font-weight: 600;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 0;
    }

    .sidebar-close-btn {
      display: none;
      background: none;
      border: none;
      color: #94a3b8;
      font-size: 26px;
      cursor: pointer;
      line-height: 1;
      padding: 4px;
      border-radius: 6px;
    }

    .sidebar-close-btn:hover {
      color: white;
      background: rgba(255, 255, 255, 0.1);
    }

    .sidebar-nav {
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;
    }

    .sidebar a {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      color: #cbd5e1;
      text-decoration: none;
      border-radius: 8px;
      transition: 0.25s;
      font-size: 14px;
      font-weight: 500;
    }

    .sidebar a:hover {
      background: rgba(255,255,255,0.08);
      color: white;
      transform: translateX(4px);
    }

    .sidebar a.active {
      background: #4f46e5;
      color: white;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
    }

    .sidebar .logout {
      background: #ef4444;
      color: white;
      margin-top: auto;
      justify-content: center;
      font-weight: 600;
    }

    .sidebar .logout:hover {
      background: #dc2626;
      transform: none;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    /* Overlay for Mobile Navigation drawer */
    .sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(2px);
      z-index: 1000;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
      display: block;
      opacity: 1;
      pointer-events: auto;
    }

    /* ===== MAIN ===== */
    .main {
      flex: 1;
      margin-left: 260px;
      width: calc(100% - 260px);
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 40px 20px;
      box-sizing: border-box;
    }

    /* ===== CARD ===== */
    .form-card {
      width: 100%;
      max-width: 480px;
      background: white;
      padding: 32px;
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
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }

    .form-card h2 i {
      color: #4f46e5;
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

    button[type="submit"] {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 15px;
      cursor: pointer;
      transition: 0.2s ease;
      margin-top: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    button[type="submit"]:hover {
      opacity: 0.95;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }
    
    button[type="submit"]:active {
      transform: translateY(0);
    }

    button[type="submit"]:disabled {
      background: #94a3b8;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-top: 22px;
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
       FLATPICKR CUSTOM STYLING
    ================================== */
    .flatpickr-calendar {
      box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
      border-radius: 12px !important;
      border: 1px solid #e2e8f0 !important;
      font-family: 'Poppins', sans-serif !important;
      overflow: hidden;
    }

    .flatpickr-months {
      background: #4f46e5 !important;
      color: white !important;
      padding-top: 4px !important;
    }

    .flatpickr-month {
      color: white !important;
      fill: white !important;
    }

    .flatpickr-current-month {
      color: white !important;
    }

    .flatpickr-current-month .numInputWrapper span.arrowUp:after {
      border-bottom-color: white !important;
    }

    .flatpickr-current-month .numInputWrapper span.arrowDown:after {
      border-top-color: white !important;
    }

    .flatpickr-months .flatpickr-prev-month,
    .flatpickr-months .flatpickr-next-month {
      color: white !important;
      fill: white !important;
    }

    .flatpickr-months .flatpickr-prev-month:hover svg,
    .flatpickr-months .flatpickr-next-month:hover svg {
      fill: #e0e7ff !important;
    }

    .flatpickr-weekday {
      background: #4f46e5 !important;
      color: rgba(255, 255, 255, 0.85) !important;
      font-weight: 500 !important;
    }

    .flatpickr-day.selected,
    .flatpickr-day.startRange,
    .flatpickr-day.endRange,
    .flatpickr-day.selected.inRange,
    .flatpickr-day.selected:focus,
    .flatpickr-day.selected:hover,
    .flatpickr-day.prevMonthDay.selected,
    .flatpickr-day.nextMonthDay.selected {
      background: #4f46e5 !important;
      border-color: #4f46e5 !important;
      color: white !important;
      font-weight: 600;
    }

    .flatpickr-day:hover {
      background: #eef2ff !important;
    }

    .flatpickr-day.today {
      border-color: #6366f1 !important;
    }

    .flatpickr-day.today:hover {
      background: #6366f1 !important;
      color: white !important;
    }

    /* Date Pills Container */
    .date-pills-container {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 8px;
      max-height: 120px;
      overflow-y: auto;
    }

    .date-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #eef2ff;
      color: #4338ca;
      border: 1px solid #c7d2fe;
      padding: 4px 10px;
      border-radius: 16px;
      font-size: 12px;
      font-weight: 500;
      transition: all 0.2s;
    }

    .date-pill:hover {
      background: #e0e7ff;
    }

    .date-pill .pill-remove {
      cursor: pointer;
      font-size: 14px;
      font-weight: 700;
      line-height: 1;
      color: #6366f1;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 16px;
      height: 16px;
      transition: 0.2s;
    }

    .date-pill .pill-remove:hover {
      color: #ef4444;
      background: #fee2e2;
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
        margin-left: 0;
        width: 100%;
        padding: 30px 20px;
      }
    }

    @media (max-width: 480px) {
      .form-card {
        padding: 20px 16px;
      }
    }
  </style>
</head>

<body>

<div class="mobile-top-bar">
  <div style="font-size:16px; font-weight:600; display:flex; align-items:center; gap:8px;">
    <i class="bi bi-person-badge"></i> Employee Panel
  </div>
  <button class="hamburger-btn" id="menuToggle" aria-label="Toggle navigation menu">
    <i class="bi bi-list"></i>
  </button>
</div>

<div class="layout">

  <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <h2><i class="bi bi-person-badge"></i> Employee Panel</h2>
      <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Sidebar">&times;</button>
    </div>

    <div class="sidebar-nav">
      <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="apply_leave.php" class="active"><i class="bi bi-calendar-plus"></i> Apply Leave</a>
    </div>

    <a href="../auth/logout.php" class="logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>
  
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <div class="main">

    <div class="form-card">

      <h2><i class="bi bi-calendar-plus"></i> Apply Leave</h2>

      <form id="leaveForm">

        <!-- MULTI DATE SELECTION -->
        <div class="form-group">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
            <label for="datePicker" style="margin-bottom:0; font-weight:500;">
              <i class="bi bi-calendar3" style="color:#6366f1; margin-right:4px;"></i> Select Date(s) <span style="color:#ef4444;">*</span>
            </label>
            <span id="dateCountBadge" style="display:none; font-size:11px; font-weight:600; background:#e0e7ff; color:#4338ca; padding:2px 8px; border-radius:12px;">0 days selected</span>
          </div>

          <div style="position:relative;">
            <input 
              type="text" 
              id="datePicker" 
              name="dates" 
              placeholder="Click to select one or multiple dates..." 
              readonly 
              required 
              style="padding-left:38px; padding-right:65px; cursor:pointer;"
            >
            <i class="bi bi-calendar-date" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#6366f1; font-size:16px; pointer-events:none;"></i>
            <button 
              type="button" 
              id="clearDatesBtn" 
              style="display:none; position:absolute; right:8px; top:50%; transform:translateY(-50%); width:auto; padding:3px 9px; margin:0; font-size:11px; background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; border-radius:6px; cursor:pointer; font-weight:500;"
              title="Clear all selected dates"
            >
              Clear
            </button>
          </div>

          <div id="selectedPillsContainer" class="date-pills-container"></div>
          <small style="color:#64748b; font-size:11.5px; display:block; margin-top:5px;">
            <i class="bi bi-info-circle"></i> Click to open calendar. You can select multiple individual dates or click each day in a range.
          </small>
        </div>

        <div class="form-group">
          <label><i class="bi bi-clock-history" style="color:#6366f1; margin-right:4px;"></i> Leave Type</label>
          <select name="type">
            <option value="Full Day">Full Day</option>
            <option value="Half Day">Half Day</option>
          </select>
        </div>

        <div class="form-group">
          <label><i class="bi bi-chat-left-text" style="color:#6366f1; margin-right:4px;"></i> Reason for Leave</label>
          <input
            type="text"
            name="reason"
            placeholder="Reason (optional)"
          >
        </div>

        <button type="submit" id="submitBtn">
          <i class="bi bi-send-check"></i> Apply Leave
        </button>

      </form>

      <a href="dashboard.php" class="back-btn"><i class="bi bi-arrow-left"></i> Go Back</a>

    </div>

  </div>

</div>

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
  // Mobile Sidebar Toggle
  const menuToggle = document.getElementById('menuToggle');
  const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    sidebar.classList.add('active');
    sidebarOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  if (menuToggle) menuToggle.addEventListener('click', openSidebar);
  if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
  if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar.classList.contains('active')) {
      closeSidebar();
    }
  });
</script>

<script>
function showToast(message, error = false) {
    const toast = document.createElement("div");
    toast.innerText = message;
    toast.style.position = "fixed";
    toast.style.top = "20px";
    toast.style.right = "20px";
    toast.style.maxWidth = "380px";
    toast.style.whiteSpace = "pre-line";
    toast.style.padding = "14px 20px";
    toast.style.borderRadius = "10px";
    toast.style.color = "white";
    toast.style.fontWeight = "500";
    toast.style.fontSize = "14px";
    toast.style.lineHeight = "1.4";
    toast.style.zIndex = "9999";
    toast.style.background = error ? "#ef4444" : "#16a34a";
    toast.style.boxShadow = "0 8px 20px rgba(0,0,0,0.2)";
    toast.style.animation = "slideIn 0.3s ease";
    toast.style.transition = "opacity 0.3s ease";

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = "0";
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 3500);
}
</script>

<script>
  function formatYMD(d) {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function formatDisplayDate(d) {
    return d.toLocaleDateString('en-GB', {
      weekday: 'short',
      day: 'numeric',
      month: 'short'
    });
  }

  // Initialize Flatpickr in Multiple Date Selection Mode
  const datePickerInput = document.getElementById("datePicker");
  const pillsContainer = document.getElementById("selectedPillsContainer");
  const countBadge = document.getElementById("dateCountBadge");
  const clearBtn = document.getElementById("clearDatesBtn");

  function renderSelectedPills(selectedDates) {
    pillsContainer.innerHTML = "";

    if (!selectedDates || selectedDates.length === 0) {
      countBadge.style.display = "none";
      clearBtn.style.display = "none";
      return;
    }

    countBadge.style.display = "inline-block";
    countBadge.textContent = selectedDates.length + (selectedDates.length === 1 ? " day selected" : " days selected");
    clearBtn.style.display = "block";

    // Sort chronologically
    const sorted = [...selectedDates].sort((a, b) => a - b);

    sorted.forEach(d => {
      const ymd = formatYMD(d);
      const pill = document.createElement("span");
      pill.className = "date-pill";
      pill.innerHTML = `
        <span>${formatDisplayDate(d)}</span>
        <span class="pill-remove" data-ymd="${ymd}" title="Remove ${ymd}">&times;</span>
      `;

      pill.querySelector(".pill-remove").addEventListener("click", function(e) {
        e.stopPropagation();
        removeDate(ymd);
      });

      pillsContainer.appendChild(pill);
    });
  }

  function removeDate(ymdToRemove) {
    const remaining = fp.selectedDates.filter(d => formatYMD(d) !== ymdToRemove);
    fp.setDate(remaining, true);
  }

  const fp = flatpickr("#datePicker", {
    mode: "multiple",
    dateFormat: "Y-m-d",
    conjunction: ", ",
    allowInput: false,
    onChange: function(selectedDates) {
      renderSelectedPills(selectedDates);
    }
  });

  if (clearBtn) {
    clearBtn.addEventListener("click", function(e) {
      e.stopPropagation();
      fp.clear();
      renderSelectedPills([]);
    });
  }

  // Handle Form Submission
  document.getElementById("leaveForm").addEventListener("submit", async function(e) {
    e.preventDefault();

    if (!fp.selectedDates || fp.selectedDates.length === 0) {
      showToast("Please select at least one date.", true);
      fp.open();
      return;
    }

    const submitBtn = document.getElementById("submitBtn");
    const originalBtnHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';

    const formData = new FormData(this);
    formData.append("ajax", "1");

    try {
      const response = await fetch("apply_leave.php", {
        method: "POST",
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        showToast(data.message || "Leave Applied Successfully ✅");
        this.reset();
        fp.clear();
        renderSelectedPills([]);
      } else {
        showToast(data.message || "Failed to Apply Leave", true);
      }
    } catch(error) {
      console.error(error);
      showToast("Something went wrong while applying leave.", true);
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalBtnHtml;
    }
  });
</script>

</body>
</html>