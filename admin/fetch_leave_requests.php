<?php
session_start();
include("../config/db.php");

$branch = $_SESSION['user']['branch'];

$res = $conn->query("
    SELECT 
        lr.*,
        u.name AS employee_name,
        u.employee_id AS employee_code

    FROM leave_requests lr

    LEFT JOIN users u
    ON lr.employee_id = u.id

    WHERE u.branch = '$branch'

    ORDER BY lr.id DESC
");

while ($row = $res->fetch_assoc()) {
?>

<tr>
    <td><?= $row['employee_name'] ?? 'Unknown' ?></td>
    <td><?= $row['employee_code'] ?? '-' ?></td>
    <td><?= $row['date'] ?></td>
    <td><?= $row['type'] ?></td>
    <td><?= $row['reason'] ?></td>

    <td id="status-<?= $row['id'] ?>">
        <span class="status status-<?= $row['status'] ?>">
            <?= ucfirst($row['status']) ?>
        </span>
    </td>

    <td id="actions-<?= $row['id'] ?>">

    <?php if ($row['status'] == 'pending') { ?>

        <button
            class="btn btn-green"
            onclick="updateLeaveStatus(<?= $row['id'] ?>, 'approved')">
            Approve
        </button>

        <button
            class="btn btn-red"
            onclick="updateLeaveStatus(<?= $row['id'] ?>, 'rejected')">
            Reject
        </button>

    <?php } else { ?>

        <span class="status status-<?= $row['status'] ?>">
            <?= ucfirst($row['status']) ?>
        </span>

    <?php } ?>

    </td>

    <td>
        <a class="btn btn-red"
           onclick="return confirm('Delete this leave request?')"
           href="delete_leave.php?id=<?= $row['id'] ?>">
           Delete
        </a>
    </td>
</tr>

<?php } ?>