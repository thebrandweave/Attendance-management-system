<!DOCTYPE html>
<html>
<head>

  <title>Login Selection</title>

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

  <style>

    *{
      margin:0;
      padding:0;
      box-sizing:border-box;
    }

    body{
      min-height:100vh;
      font-family:'Poppins', sans-serif;
      display:flex;
      justify-content:center;
      align-items:center;
      background:linear-gradient(135deg,#eef2f7,#dbeafe);
      padding:20px;
    }

    .box{
      background:white;
      padding:40px 30px;
      border-radius:20px;
      width:100%;
      max-width:380px;
      text-align:center;
      box-shadow:0 10px 30px rgba(0,0,0,0.12);
      animation:fadeIn 0.5s ease;
    }

    .box h2{
      margin-bottom:25px;
      font-size:28px;
      color:#111827;
    }

    .btn{
      display:block;
      text-decoration:none;
      padding:15px;
      margin-top:18px;
      border-radius:12px;
      color:white;
      font-size:17px;
      font-weight:600;
      transition:0.3s;
    }

    .btn:hover{
      transform:translateY(-2px);
      opacity:0.95;
    }

    .admin{
      background:#2563eb;
    }

    .employee{
      background:#16a34a;
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

      .box{
        padding:30px 20px;
        border-radius:16px;
      }

      .box h2{
        font-size:24px;
      }

      .btn{
        padding:14px;
        font-size:16px;
      }
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