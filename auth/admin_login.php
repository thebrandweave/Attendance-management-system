<?php

include("../config/db.php");

$message = "";
$type = "";

if (isset($_POST['login'])) {

  $id = $_POST['employee_id'];
  $pass = $_POST['password'];

  $stmt = $conn->prepare(
    "SELECT * FROM users WHERE employee_id=? AND role='admin'"
  );

  $stmt->bind_param("s", $id);
  $stmt->execute();

  $result = $stmt->get_result();

  if ($result->num_rows > 0) {

    $user = $result->fetch_assoc();

    if (password_verify($pass, $user['password'])) {

      $_SESSION['user'] = $user;

      header("Location: ../admin/dashboard.php");
      exit();

    } else {

      $message = "Wrong password ❌";
      $type = "error";
    }

  } else {

    $message = "Admin not found ❌";
    $type = "error";
  }
}
?>

<!DOCTYPE html>
<html>
<head>

  <title>Admin Login</title>

  <link rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">


  <style>

    body{
      margin:0;
      height:100vh;
      display:flex;
      justify-content:center;
      align-items:center;
      background:#f4f4f4;
      font-family: 'Poppins', sans-serif;
    }

    form{
      background:white;
      padding:40px;
      border-radius:15px;
      width:350px;
      box-shadow:0 0 15px rgba(0,0,0,0.1);
    }

    input{
      width:100%;
      padding:12px;
      margin-top:15px;
      border:1px solid #ccc;
      border-radius:8px;
    }

    button{
      width:100%;
      padding:12px;
      margin-top:20px;
      border:none;
      border-radius:8px;
      background:#007bff;
      color:white;
      font-size:16px;
      cursor:pointer;
    }

    .toast{
      position:fixed;
      top:20px;
      right:20px;
      background:#dc3545;
      color:white;
      padding:15px;
      border-radius:8px;
    }

    .back-btn {
  display: inline-block;
  margin-bottom: 15px;
  padding: 8px 12px;
  background: #111827;
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-size: 13px;
}

.back-btn:hover {
  background: #374151;
}

  </style>

</head>

<body>

<?php if($message){ ?>
  <div class="toast">
    <?php echo $message; ?>
  </div>
<?php } ?>

<form method="POST">

  <h2>Admin Login</h2>

        <a href="../index.php" class="back-btn">⬅ Go Back</a>

  <input
    type="text"
    name="employee_id"
    placeholder="Enter Admin ID"
    required
  >

  <div style="position:relative;">

    <input
      type="password"
      id="password"
      name="password"
      placeholder="Password"
      required
    >

    <i
      id="eyeIcon"
      class="fa-solid fa-eye"
      onclick="togglePassword()"
      style="
        position:absolute;
        right:15px;
        top:28px;
        cursor:pointer;
      "
    ></i>

  </div>

  <button name="login">
    Login
  </button>



</form>

<script>

function togglePassword(){

  const pass = document.getElementById("password");
  const icon = document.getElementById("eyeIcon");

  if(pass.type === "password"){

    pass.type = "text";

    icon.classList.remove("fa-eye");
    icon.classList.add("fa-eye-slash");

  }else{

    pass.type = "password";

    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");
  }
}

</script>

</body>
</html>