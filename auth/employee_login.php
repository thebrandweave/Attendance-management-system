<?php

session_start();

include("../config/db.php");

$message = "";
$type = "";

if (isset($_POST['login'])) {

  $id = trim($_POST['employee_id']);
  $pass = $_POST['password'];

  $stmt = $conn->prepare(
    "SELECT * FROM users WHERE employee_id=? AND role='employee'"
  );

  $stmt->bind_param("s", $id);
  $stmt->execute();

  $result = $stmt->get_result();

  if ($result->num_rows > 0) {

    $user = $result->fetch_assoc();

    if (password_verify($pass, $user['password'])) {

      $_SESSION['user'] = $user;

      header("Location: ../employee/dashboard.php");
      exit();

    } else {

      $message = "Wrong password ❌";
      $type = "error";
    }

  } else {

    $message = "Employee not found ❌";
    $type = "error";
  }
}
?>

<!DOCTYPE html>
<html>
<head>

  <title>Employee Login</title>

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

  <style>

    *{
      margin:0;
      padding:0;
      box-sizing:border-box;
    }

    body{
      min-height:100vh;
      display:flex;
      justify-content:center;
      align-items:center;
      font-family:'Poppins', sans-serif;
      padding:20px;
      position:relative;
      overflow-x:hidden;
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
      /* background: rgba(15, 23, 42, 0.55);
      backdrop-filter: blur(3px);
      -webkit-backdrop-filter: blur(3px); */
    }

    form{
      background: rgba(255, 255, 255, 0.94);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      padding:40px 30px;
      border-radius:20px;
      width:100%;
      max-width:380px;
      box-shadow:0 25px 50px -12px rgba(0, 0, 0, 0.25);
      border: 1px solid rgba(255, 255, 255, 0.5);
      animation:fadeIn 0.5s ease;
      position: relative;
      z-index: 1;
    }

    h2{
      text-align:center;
      margin-bottom:20px;
      color:#111827;
    }

    input{
      width:100%;
      padding:14px;
      margin-top:15px;
      border:1px solid #d1d5db;
      border-radius:10px;
      font-size:15px;
      outline:none;
      transition:0.3s;
    }

    input:focus{
      border-color:#16a34a;
      box-shadow:0 0 0 3px rgba(22,163,74,0.1);
    }

    button{
      width:100%;
      padding:14px;
      margin-top:22px;
      border:none;
      border-radius:10px;
      background:#16a34a;
      color:white;
      font-size:16px;
      font-weight:600;
      cursor:pointer;
      transition:0.3s;
    }

    button:hover{
      background:#15803d;
      transform:translateY(-1px);
    }

    .toast{
      position:fixed;
      top:20px;
      right:20px;
      background:#dc2626;
      color:white;
      padding:14px 18px;
      border-radius:10px;
      font-size:14px;
      z-index:999;
      box-shadow:0 5px 15px rgba(0,0,0,0.15);
    }

    .back-btn{
      display:inline-block;
      margin-bottom:18px;
      padding:10px 14px;
      background:#111827;
      color:white;
      text-decoration:none;
      border-radius:10px;
      font-size:13px;
      transition:0.3s;
    }

    .back-btn:hover{
      background:#374151;
    }

    .password-box{
      position:relative;
    }

    .password-box i{
      position:absolute;
      right:15px;
      top:30px;
      cursor:pointer;
      color:#6b7280;
    }

    @keyframes fadeIn{
      from{
        opacity:0;
        transform:translateY(20px);
      }
      to{
        opacity:1;
        transform:translateY(0);
      }
    }

    /* MOBILE */
    @media(max-width:480px){

      form{
        padding:30px 20px;
        border-radius:16px;
      }

      h2{
        font-size:24px;
      }

      input,
      button{
        font-size:15px;
        padding:13px;
      }

      .toast{
        right:10px;
        left:10px;
        top:10px;
        text-align:center;
      }
    }

  </style>

</head>

<body>

<div class="video-bg-container">
  <video autoplay loop muted playsinline id="bgVideo">
    <source src="../public/media/bg1.mp4" type="video/mp4">
  </video>
  <div class="video-overlay"></div>
</div>

<?php if($message){ ?>
  <div class="toast">
    <?php echo $message; ?>
  </div>
<?php } ?>

<form method="POST">

  <h2>Employee Login</h2>

  <a href="../index.php" class="back-btn">
    ⬅ Go Back
  </a>

  <input
    type="text"
    name="employee_id"
    placeholder="Enter Employee ID"
    required
  >

  <div class="password-box">

    <input
      type="password"
      id="password"
      name="password"
      placeholder="Password"
      required
    >

    <i
      id="eyeIcon"
      class="fa-solid fa-eye-slash"
      onclick="togglePassword()"
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

    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");

  }else{

    pass.type = "password";

    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");
  }
}

</script>

</body>
</html>