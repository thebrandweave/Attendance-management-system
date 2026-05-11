<?php include("../config/db.php");

$id = $_GET['id'];

$data = $conn->query("SELECT * FROM leave_requests WHERE id=$id")->fetch_assoc();

if (isset($_POST['update'])) {
  $type = $_POST['type'];
  $date = $_POST['date'];

  $conn->query("UPDATE leave_requests SET type='$type', date='$date' WHERE id=$id");

  header("Location: leave_requests.php");
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Leave</title>
  <style>
    body {
      font-family: Arial;
      background: #f4f6f8;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .box {
      background: white;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.1);
      width: 300px;
    }

    input {
      width: 100%;
      padding: 10px;
      margin: 8px 0;
    }

    button {
      width: 100%;
      padding: 10px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 6px;
    }
  </style>
</head>
<body>

<div class="box">
  <h3>Edit Leave</h3>

  <form method="POST">
    <input type="date" name="date" value="<?= $data['date'] ?>" required>
    <input type="text" name="type" value="<?= $data['type'] ?>" required>
    <button type="submit" name="update">Update</button>
  </form>
</div>

</body>
</html>