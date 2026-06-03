<?php
session_start();
include "db.php";

/* UPDATE STATUS */
if(isset($_POST['update_status'])){

    $ticket_id = $_POST['ticket_id'];
    $status = $_POST['status'];

    mysqli_query($conn, "
        UPDATE tickets
        SET status='$status'
        WHERE id='$ticket_id'
    ");

    echo "<script>alert('Status updated');</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Tickets</title>

    <style>
        body{
            font-family: Arial, sans-serif;
            background:#f4f6f8;
            padding:20px;
        }

        h2{
            color:#333;
            margin-bottom:15px;
        }

        /* ===== TABLE ===== */
        table{
            width:100%;
            border-collapse:collapse;
            background:white;
            box-shadow:0 3px 12px rgba(0, 0, 0, 0.1);
            border-radius:10px;
            overflow:hidden;
        }

        th{
            background:#222;
            color:white;
            padding:12px;
            text-align:left;
            font-size:14px;
        }

        td{
            padding:12px;
            border-bottom:1px solid #eee;
            font-size:14px;
        }

        tr:hover{
            background:#f9f9f9;
        }

        /* ===== STATUS COLORS ===== */
        .status{
            font-weight:bold;
        }

        .open{ color:#ff9800; }
        .in_progress{ color:#2196f3; }
        .resolved{ color:#4caf50; }
        .closed{ color:#555; }

        /* ===== FORM INSIDE TABLE ===== */
        select{
            padding:6px;
            border:1px solid #ccc;
            border-radius:5px;
        }

        button{
            padding:6px 10px;
            background:#007bff;
            color:white;
            border:none;
            border-radius:5px;
            cursor:pointer;
        }

        button:hover{
            background:#0056b3;
        }

        /* ===== TITLE BAR ===== */
        .title-bar{
            background:#416eb5;
            text-align:center;
            color:white;
            padding:10px;
            border-radius:8px;
            margin-bottom:15px;
        }
    </style>
</head>

<body>

<div class="title-bar">
    <h2>📩 All Support Tickets (Admin Panel)</h2>
</div>

<table>
<tr>
    <th>User ID</th>
    <th>Type</th>
    <th>Subject</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php
$result = mysqli_query($conn, "SELECT * FROM tickets ORDER BY created_at DESC");

while($row = mysqli_fetch_assoc($result)) {
?>

<tr>
    <td><?= $row['user_id'] ?></td>
    <td><?= $row['type'] ?></td>
    <td><?= $row['subject'] ?></td>

    <td>
        <span class="status <?= $row['status'] ?>">
            <?= strtoupper($row['status']) ?>
        </span>
    </td>

    <td>
        <form method="POST">
            <input type="hidden" name="ticket_id" value="<?= $row['id'] ?>">

            <select name="status">
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
            </select>

            <button type="submit" name="update_status">Update</button>
        </form>
    </td>
</tr>

<?php } ?>

</table>

</body>
</html>