<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Management System - Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Google Fonts & FontAwesome -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root {
      --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
      --card-bg: #ffffff;
      --text-main: #0f172a;
      --text-muted: #64748b;
      --primary: #2563eb;
      --primary-hover: #1d4ed8;
      --success: #16a34a;
      --success-hover: #15803d;
      --purple: #7c3aed;
      --purple-hover: #6d28d9;
      --border-color: #e2e8f0;
      --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
      --radius: 16px;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 24px;
      color: var(--text-main);
      position: relative;
      overflow-x: hidden;
    }

    .video-bg-container {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      overflow: hidden;
      z-index: -1;
    }

    #bgVideo {
      position: absolute;
      top: 50%;
      left: 50%;
      min-width: 100%;
      min-height: 100%;
      width: auto;
      height: auto;
      transform: translate(-50%, -50%);
      object-fit: cover;
    }

    .video-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      /* background: rgba(184, 191, 206, 0.55); */
      /* backdrop-filter: blur(3px); */
      /* -webkit-backdrop-filter: blur(3px); */
    }

    .gateway-container {
      width: 100%;
      max-width: 480px;
      background: rgba(255, 255, 255, 0.94);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-radius: var(--radius);
      padding: 40px 32px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      border: 1px solid rgba(255, 255, 255, 0.5);
      animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
      z-index: 1;
    }

    .gateway-header {
      text-align: center;
      margin-bottom: 32px;
    }

    .gateway-header .logo-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 54px;
      height: 54px;
      background: #eff6ff;
      color: var(--primary);
      border-radius: 14px;
      font-size: 24px;
      margin-bottom: 16px;
    }

    .gateway-header h1 {
      font-size: 24px;
      font-weight: 700;
      color: var(--text-main);
      letter-spacing: -0.02em;
    }

    .gateway-header p {
      font-size: 14px;
      color: var(--text-muted);
      margin-top: 6px;
    }

    .portal-options {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .portal-card {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 18px 20px;
      border: 1px solid var(--border-color);
      border-radius: 12px;
      text-decoration: none;
      color: var(--text-main);
      background: #ffffff;
      transition: all 0.2s ease-in-out;
      cursor: pointer;
    }

    .portal-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }

    .portal-card.admin-card:hover {
      border-color: var(--primary);
      background: #f8fafc;
    }

    .portal-card.employee-card:hover {
      border-color: var(--success);
      background: #f0fdf4;
    }

    .portal-card.create-card:hover {
      border-color: var(--purple);
      background: #f5f3ff;
    }

    .portal-icon {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }

    .admin-card .portal-icon { background: #eff6ff; color: var(--primary); }
    .employee-card .portal-icon { background: #f0fdf4; color: var(--success); }
    .create-card .portal-icon { background: #f5f3ff; color: var(--purple); }

    .portal-info { flex: 1; }
    .portal-info h3 { font-size: 16px; font-weight: 600; color: var(--text-main); }
    .portal-info p { font-size: 13px; color: var(--text-muted); margin-top: 2px; }

    .portal-arrow {
      color: #94a3b8;
      font-size: 14px;
      transition: transform 0.2s ease;
    }

    .portal-card:hover .portal-arrow {
      transform: translateX(4px);
      color: var(--text-main);
    }

    .footer-note {
      text-align: center;
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 28px;
    }

    /* Modal Styling */
    .modal-backdrop {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(15, 23, 42, 0.55);
      backdrop-filter: blur(4px);
      z-index: 999;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      opacity: 0;
      visibility: hidden;
      transition: all 0.25s ease-in-out;
    }

    .modal-backdrop.show {
      opacity: 1;
      visibility: visible;
    }

    .modal {
      background: #ffffff;
      border-radius: 18px;
      width: 100%;
      max-width: 480px;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
      border: 1px solid var(--border-color);
      transform: scale(0.95);
      transition: transform 0.25s ease-in-out;
      overflow: hidden;
    }

    .modal-backdrop.show .modal { transform: scale(1); }

    .modal-header {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .modal-header h3 { font-size: 18px; font-weight: 700; color: var(--text-main); }

    .modal-close {
      background: none;
      border: none;
      font-size: 20px;
      color: var(--text-muted);
      cursor: pointer;
      padding: 4px;
      border-radius: 6px;
    }
    .modal-close:hover { color: var(--text-main); background: #f1f5f9; }

    .modal-body { padding: 24px; }
    .modal-footer {
      padding: 16px 24px;
      border-top: 1px solid var(--border-color);
      background: #f8fafc;
      display: flex;
      justify-content: flex-end;
      gap: 12px;
    }

    .form-group { margin-bottom: 16px; }
    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--text-main);
      margin-bottom: 6px;
    }
    .form-control {
      width: 100%;
      padding: 11px 14px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      font-size: 14px;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-control:focus {
      border-color: var(--purple);
      box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    }

    .password-wrapper { position: relative; }
    .password-wrapper .form-control { padding-right: 42px; }
    .password-wrapper i {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: var(--text-muted);
      font-size: 15px;
      transition: color 0.2s;
    }
    .password-wrapper i:hover { color: var(--purple); }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 11px 18px;
      font-size: 14px;
      font-weight: 600;
      border-radius: 8px;
      border: none;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-purple { background: var(--purple); color: white; }
    .btn-purple:hover { background: var(--purple-hover); }
    .btn-secondary { background: #e2e8f0; color: var(--text-main); }
    .btn-secondary:hover { background: #cbd5e1; }

    .alert-box {
      padding: 12px 14px;
      border-radius: 8px;
      font-size: 13px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

<div class="video-bg-container">
  <video autoplay loop muted playsinline id="bgVideo">
    <source src="./public/media/bg1.mp4" type="video/mp4">
  </video>
  <div class="video-overlay"></div>
</div>

<div class="gateway-container">
  <div class="gateway-header">
    <div class="logo-badge">
      <i class="fa-solid fa-building-user"></i>
    </div>
    <h1>Attendance Portal Gateway</h1>
    <p>Select your portal or manage branch access</p>
  </div>

  <div class="portal-options">
    <a href="./auth/admin_login.php" class="portal-card admin-card">
      <div class="portal-icon">
        <i class="fa-solid fa-user-shield"></i>
      </div>
      <div class="portal-info">
        <h3>Admin Portal</h3>
        <p>Branch admin dashboard & login</p>
      </div>
      <i class="fa-solid fa-chevron-right portal-arrow"></i>
    </a>

    <a href="./auth/employee_login.php" class="portal-card employee-card">
      <div class="portal-icon">
        <i class="fa-solid fa-id-card-clip"></i>
      </div>
      <div class="portal-info">
        <h3>Employee Portal</h3>
        <p>Employee check-in & workspace</p>
      </div>
      <i class="fa-solid fa-chevron-right portal-arrow"></i>
    </a>

    <div onclick="openCreateModal()" class="portal-card create-card">
      <div class="portal-icon">
        <i class="fa-solid fa-code-branch"></i>
      </div>
      <div class="portal-info">
        <h3>Create Branch / Admin</h3>
        <p>Add new branch & branch admin</p>
      </div>
      <i class="fa-solid fa-plus portal-arrow"></i>
    </div>
  </div>

  <div class="footer-note">
    Attendance Management System &bull; Multi-Branch Platform
  </div>
</div>

<!-- Create Branch & Admin Modal -->
<div class="modal-backdrop" id="createModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modalTitle"><i class="fa-solid fa-shield-lock" style="color: var(--purple);"></i> Security Verification</h3>
      <button class="modal-close" onclick="closeCreateModal()">&times;</button>
    </div>

    <!-- Step 1: Security Admin Login Verification -->
    <form id="verifyForm" onsubmit="handleVerifySubmit(event)">
      <div class="modal-body">
        <div id="verifyAlert"></div>
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
          Please verify existing admin credentials before creating a new branch or admin.
        </p>
        <div class="form-group">
          <label>Admin ID *</label>
          <input type="text" name="admin_id" class="form-control" placeholder="e.g. ADMIN001" required autocomplete="username">
        </div>
        <div class="form-group">
          <label>Admin Password *</label>
          <div class="password-wrapper">
            <input type="password" id="verifyPassInput" name="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
            <i class="fa-solid fa-eye-slash" onclick="togglePassVisibility('verifyPassInput', this)"></i>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
        <button type="submit" class="btn btn-purple">Verify & Continue &rarr;</button>
      </div>
    </form>

    <!-- Step 2: Branch & Admin Creation Form -->
    <form id="createForm" style="display: none;" onsubmit="handleCreateSubmit(event)">
      <div class="modal-body">
        <div id="createAlert"></div>
        <p style="font-size: 13px; color: var(--purple); font-weight: 600; margin-bottom: 16px;">
          <i class="fa-solid fa-circle-check"></i> Admin Verified. Enter New Branch & Admin details:
        </p>
        
        <h4 style="font-size: 14px; font-weight: 700; color: var(--text-main); margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
          1. Branch Details
        </h4>
        <div class="form-group">
          <label>Branch Name *</label>
          <input type="text" name="branch_name" class="form-control" placeholder="e.g. Thirthahalli" required>
        </div>
        <div style="display: flex; gap: 12px;">
          <div class="form-group" style="flex: 1;">
            <label>Branch Code</label>
            <input type="text" name="branch_code" class="form-control" placeholder="e.g. THI">
          </div>
          <div class="form-group" style="flex: 1;">
            <label>Location / City</label>
            <input type="text" name="location" class="form-control" placeholder="e.g. Thirthahalli">
          </div>
        </div>

        <h4 style="font-size: 14px; font-weight: 700; color: var(--text-main); margin-top: 14px; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
          2. Branch Admin Account
        </h4>
        <div class="form-group">
          <label>Admin Full Name *</label>
          <input type="text" name="admin_name" class="form-control" placeholder="e.g. Thirthahalli Admin" required>
        </div>
        <div class="form-group">
          <label>New Admin ID *</label>
          <input type="text" name="admin_id" class="form-control" placeholder="e.g. ADMIN003" required>
        </div>
        <div class="form-group">
          <label>Admin Password *</label>
          <div class="password-wrapper">
            <input type="password" id="createPassInput" name="admin_password" class="form-control" placeholder="Create password" required minlength="4">
            <i class="fa-solid fa-eye-slash" onclick="togglePassVisibility('createPassInput', this)"></i>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
        <button type="submit" class="btn btn-purple"><i class="fa-solid fa-plus-circle"></i> Create Branch & Admin</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCreateModal() {
  document.getElementById('verifyForm').reset();
  document.getElementById('createForm').reset();
  document.getElementById('verifyAlert').innerHTML = '';
  document.getElementById('createAlert').innerHTML = '';
  document.getElementById('verifyForm').style.display = 'block';
  document.getElementById('createForm').style.display = 'none';
  document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-shield-lock" style="color: var(--purple);"></i> Security Verification';
  document.getElementById('createModal').classList.add('show');
}

function closeCreateModal() {
  document.getElementById('createModal').classList.remove('show');
}

function handleVerifySubmit(e) {
  e.preventDefault();
  const alertBox = document.getElementById('verifyAlert');
  alertBox.innerHTML = '';
  const formData = new FormData(e.target);
  formData.append('action', 'verify_admin');

  fetch('./auth/create_branch_api.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      document.getElementById('verifyForm').style.display = 'none';
      document.getElementById('createForm').style.display = 'block';
      document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-code-branch" style="color: var(--purple);"></i> Add Branch & Admin';
    } else {
      alertBox.innerHTML = `<div class="alert-box alert-error"><i class="fa-solid fa-circle-exclamation"></i> ${data.message}</div>`;
    }
  })
  .catch(() => {
    alertBox.innerHTML = '<div class="alert-box alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Server or network error</div>';
  });
}

function handleCreateSubmit(e) {
  e.preventDefault();
  const alertBox = document.getElementById('createAlert');
  alertBox.innerHTML = '';
  const formData = new FormData(e.target);
  formData.append('action', 'create_branch_admin');

  fetch('./auth/create_branch_api.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      alertBox.innerHTML = `<div class="alert-box alert-success"><i class="fa-solid fa-circle-check"></i> ${data.message}</div>`;
      setTimeout(() => {
        closeCreateModal();
        alert(data.message);
      }, 1200);
    } else {
      alertBox.innerHTML = `<div class="alert-box alert-error"><i class="fa-solid fa-circle-exclamation"></i> ${data.message}</div>`;
    }
  })
  .catch(() => {
    alertBox.innerHTML = '<div class="alert-box alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Server or network error</div>';
  });
}

function togglePassVisibility(inputId, iconElement) {
  const input = document.getElementById(inputId);
  if (input.type === 'password') {
    input.type = 'text';
    iconElement.classList.remove('fa-eye-slash');
    iconElement.classList.add('fa-eye');
  } else {
    input.type = 'password';
    iconElement.classList.remove('fa-eye');
    iconElement.classList.add('fa-eye-slash');
  }
}
</script>
</body>
</html>