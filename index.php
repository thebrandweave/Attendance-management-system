<!DOCTYPE html>
<html>
<head>

  <title>Login Selection</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <style>

    body{
      margin:0;
      height:100vh;
      font-family: 'Poppins', sans-serif;
      display:flex;
      justify-content:center;
      align-items:center;
      background:#f4f4f4;

    }

    .box{
      background:white;
      padding:40px;
      border-radius:15px;
      width:350px;
      text-align:center;
      box-shadow:0 0 15px rgba(0,0,0,0.1);
    }

    .btn{
      display:block;
      text-decoration:none;
      padding:15px;
      margin-top:20px;
      border-radius:10px;
      color:white;
      font-size:18px;
      font-weight:bold;
    }

    .admin{
      background:#007bff;
    }

    .employee{
      background:#28a745;
    }

  </style>

</head>

<body>

<div class="box">

  <h2>Select Login</h2>

  <a href="./auth/admin_login.php" class="btn admin">
    Admin Login
  </a>

  <a href="./auth/employee_login.php" class="btn employee">
    Employee Login
  </a>

</div>

</body>
</html>