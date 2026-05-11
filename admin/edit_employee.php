<?php
include("../config/db.php");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
    header("Location: ../index.php");
    exit();
}

$id = intval($_GET['id']);

$employee = $conn->query("
    SELECT * FROM users 
    WHERE id=$id AND role='employee'
")->fetch_assoc();

if (!$employee) {
    die("Employee not found");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name = mysqli_real_escape_string($conn, $_POST['name']);

    $conn->query("
        UPDATE users 
        SET name='$name'
        WHERE id=$id
    ");

    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Employee</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        body{
            font-family:'Poppins',sans-serif;
            background:#eef2f7;
            display:flex;
            justify-content:center;
            align-items:center;
            height:100vh;
        }

        .box{
            background:white;
            padding:30px;
            border-radius:12px;
            width:350px;
            box-shadow:0 5px 15px rgba(0,0,0,0.1);
        }

        h2{
            margin-bottom:20px;
        }

        input{
            width:100%;
            padding:12px;
            border:1px solid #ddd;
            border-radius:8px;
            margin-bottom:20px;
        }

        button{
            width:100%;
            padding:12px;
            border:none;
            border-radius:8px;
            background:#667eea;
            color:white;
            font-weight:600;
            cursor:pointer;
        }

        button:hover{
            background:#5a67d8;
        }
    </style>
</head>
<body>

<div class="box">
    <h2>Edit Employee</h2>

    <form method="POST">

        <input type="text"
               name="name"
               value="<?= $employee['name'] ?>"
               required>

        <button type="submit">
            Update Employee
        </button>

    </form>
</div>

</body>
</html>