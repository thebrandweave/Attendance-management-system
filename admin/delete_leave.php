<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../config/db.php");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "admin") {
    header("Location: ../index.php");
    exit();
}

$idInput = $_GET['ids'] ?? $_GET['id'] ?? '';
$ids = [];

if (is_array($idInput)) {
    $ids = array_map('intval', $idInput);
} else {
    foreach (explode(',', (string)$idInput) as $p) {
        $v = intval(trim($p));
        if ($v > 0) $ids[] = $v;
    }
}
$ids = array_values(array_unique(array_filter($ids)));

if (!empty($ids)) {
    $idList = implode(',', $ids);
    $conn->query("DELETE FROM leave_requests WHERE id IN ($idList)");
}

header("Location: leave_requests.php");
exit();
?>