<?php include("../config/db.php");

$id = $_GET['id'];
$status = $_GET['status'];

$conn->query("UPDATE leave_requests SET status='$status' WHERE id=$id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Update Status</title>

  <!-- Toastr CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

  <style>
    body {
      margin: 0;
      height: 100vh;
      background: #f4f6f8;
    }
  </style>
</head>
<body>

<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<!-- Toastr JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
  toastr.options = {
    "closeButton": true,
    "progressBar": true,
    "positionClass": "toast-top-right",
    "timeOut": "2000"
  };

  toastr.success("Leave status updated successfully!");

  setTimeout(() => {
    window.history.back(); // or redirect to admin page
  }, 2000);
</script>

</body>
</html>