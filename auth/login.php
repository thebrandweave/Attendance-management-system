<?php
include("../config/db.php");

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  $employee_id = $_POST['employee_id'];
  $password = $_POST['password'];

  $stmt = $conn->prepare("SELECT * FROM users WHERE employee_id = ?");
  $stmt->bind_param("s", $employee_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {

      $_SESSION['user'] = $user;

      if ($user['role'] === "admin") {
        header("Location: ../admin/dashboard.php");
      } else {
        header("Location: ../employee/dashboard.php");
      }
      exit();

    } else {
      $message = "Wrong password ❌";
    }

  } else {
    $message = "User not found ❌";
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Login</title>
  <link rel="stylesheet" href="../style.css">
</head>
<style>
    /* Toast base */
.toast {
  position: fixed;
  top: 20px;
  right: -300px;
  padding: 15px 20px;
  border-radius: 8px;
  color: white;
  font-weight: bold;
  transition: 0.4s;
  z-index: 1000;
}

/* Show animation */
.toast.show {
  right: 20px;
}

/* Error */
.toast.error {
  background: #dc3545;
}

/* Success (for later use) */
.toast.success {
  background: #28a745;
}
</style>
<body>

<?php if ($message) { ?>
  <div id="toast" class="toast error"><?php echo $message; ?></div>
<?php } ?>

<script>
  const toast = document.getElementById("toast");
  if (toast) {
    setTimeout(() => {
      toast.classList.add("show");
    }, 100);

    setTimeout(() => {
      toast.classList.remove("show");
    }, 3000);
  }
</script>

</body>
</html>