<?php
session_start();
include "db.php";

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Tickets</title>

    <style>
        body{
            font-family: Arial, sans-serif;
            background:#f4f6f8;
            padding:20px;
        }

        h3{
            color:#333;
            margin-bottom:10px;
        }

        /* ===== FORM BOX ===== */
        form{
            background:white;
            padding:20px;
            max-width:500px;
            border-radius:10px;
            box-shadow:0 2px 10px rgba(0,0,0,0.1);
            margin-bottom:20px;
        }

        select, input, textarea{
            width:100%;
            padding:10px;
            margin-top:10px;
            border:1px solid #ccc;
            border-radius:5px;
            font-size:14px;
        }

        textarea{
            height:100px;
            resize:none;
        }

        button{
            width:100%;
            padding:12px;
            margin-top:15px;
            background:#007bff;
            color:white;
            border:none;
            border-radius:5px;
            cursor:pointer;
            font-size:15px;
        }

        button:hover{
            background:#0056b3;
        }

        hr{
            margin:30px 0;
            border:0;
            border-top:1px solid #ddd;
        }

        /* ===== TABLE ===== */
        table{
            width:100%;
            border-collapse:collapse;
            background:white;
            box-shadow:0 2px 10px rgba(0,0,0,0.1);
            border-radius:10px;
            overflow:hidden;
        }

        th{
            background:#333;
            color:white;
            padding:12px;
            text-align:left;
        }

        td{
            padding:12px;
            border-bottom:1px solid #eee;
        }

        tr:hover{
            background:#f1f1f1;
        }

        /* ===== STATUS COLORS ===== */
        .status-open{
            color:#ff9800;
            font-weight:bold;
        }

        .status-progress{
            color:#2196f3;
            font-weight:bold;
        }

        .status-resolved{
            color:#4caf50;
            font-weight:bold;
        }

        .status-closed{
            color:#555;
            font-weight:bold;
        }

    </style>
</head>

<body>

<!-- CREATE TICKET -->
<?php if($role != 'admin') { ?>

<h3>Raise Ticket</h3>

<form method="POST" action="create_ticket.php">

    <select name="type" required>
        <option value="">Select Type</option>
        <option value="academic">Academic</option>
        <option value="technical">Technical</option>
        <option value="attendance">Attendance</option>
        <option value="general">General</option>
    </select>

    <input type="text" name="subject" placeholder="Subject" required>

    <textarea name="message" placeholder="Message" required></textarea>

    <button type="submit">Submit Ticket</button>
</form>

<?php } ?>

<hr>

<!-- VIEW TICKETS -->

<h3>My Tickets</h3>

<table>
<tr>
    <th>Type</th>
    <th>Subject</th>
    <th>Status</th>
    <th>Date</th>
</tr>

<?php
$result = mysqli_query($conn, "
    SELECT * FROM tickets
    WHERE user_id='$user_id'
    ORDER BY created_at DESC
");

while($row = mysqli_fetch_assoc($result)) {
?>

<tr>
    <td><?= $row['type'] ?></td>
    <td><?= $row['subject'] ?></td>

    <td>
        <?php
            if($row['status']=='open')
                echo "<span class='status-open'>🟡 Open</span>";
            elseif($row['status']=='in_progress')
                echo "<span class='status-progress'>🔵 In Progress</span>";
            elseif($row['status']=='resolved')
                echo "<span class='status-resolved'>🟢 Resolved</span>";
            else
                echo "<span class='status-closed'>⚫ Closed</span>";
        ?>
    </td>

    <td><?= $row['created_at'] ?></td>
</tr>

<?php } ?>

</table>

</body>
</html>