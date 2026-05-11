<?php include("config/db.php"); ?>

<?php
$message = "";
$type = ""; // success / error

if (isset($_POST['login'])) {
  $id = $_POST['employee_id'];
  $pass = $_POST['password'];

  $stmt = $conn->prepare("SELECT * FROM users WHERE employee_id=?");
  $stmt->bind_param("s", $id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    if (password_verify($pass, $user['password'])) {
      $_SESSION['user'] = $user;

      if ($user['role'] == "admin") {
        header("Location: admin/dashboard.php");
      } else {
        header("Location: employee/dashboard.php");
      }
      exit();
    } else {
      $message = "Wrong password ❌";
      $type = "error";
    }

  } else {
    $message = "User not found ❌";
    $type = "error";
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Login</title>
  <link rel="stylesheet" href="./styles/index.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<script>
function togglePassword() {
  const pass = document.getElementById("password");
  const icon = document.getElementById("eyeIcon");

  if (pass.type === "password") {
    pass.type = "text";
    icon.classList.remove("fa-eye");
    icon.classList.add("fa-eye-slash");
  } else {
    pass.type = "password";
    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");
  }
}
</script>
<body>

<!-- ✅ TOAST -->
<?php if ($message) { ?>
  <div id="toast" class="toast <?php echo $type; ?>">
    <?php echo $message; ?>
  </div>
<?php } ?>

<form method="POST">
  <h2>Login</h2>

  <input name="employee_id" placeholder="Employee ID" required>
<div style="position:relative; width:100%;">
  <input 
    type="password" 
    id="password" 
    name="password" 
    placeholder="Password"
    style="width:100%; padding-right:45px;"
    required
  >

  <i 
    id="eyeIcon"
    class="fa-solid fa-eye"
    onclick="togglePassword()"
    style="
      position:absolute;
      right:12px;
      top:50%;
      transform:translateY(-50%);
      cursor:pointer;
      color:#555;
    "
  ></i>
</div>

  <button name="login">Login</button>
</form>

<script>
  const toast = document.getElementById("toast");
  if (toast) {
    setTimeout(() => toast.classList.add("show"), 100);
    setTimeout(() => toast.classList.remove("show"), 3000);
  }
</script>

</body>
</html>