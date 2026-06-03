<?php
include "db.php";
session_start();

/* =========================
   SINGLE APPROVE
========================= */
if(isset($_GET['approve'])){
    $id = intval($_GET['approve']);
    $conn->query("UPDATE enrollments SET status='approved' WHERE id=$id");
}

/* =========================
   SINGLE REJECT
========================= */
if(isset($_GET['reject'])){
    $id = intval($_GET['reject']);
    $conn->query("UPDATE enrollments SET status='rejected' WHERE id=$id");
}

/* =========================
   BULK ACTIONS (FIXED)
========================= */
if($_SERVER['REQUEST_METHOD'] === 'POST'){

    /* SAVE ALL (APPROVE ALL) */
    if(isset($_POST['approve_all'])){
        $conn->query("
            UPDATE enrollments 
            SET status='approved' 
            WHERE status IN ('pending','ongoing')
        ");
    }

    /* CLEAR ALL (REJECT ALL) */
    if(isset($_POST['reject_all'])){
        $conn->query("
            UPDATE enrollments 
            SET status='rejected' 
            WHERE status IN ('pending','ongoing')
        ");
    }

    /* DELETE ALL */
    if(isset($_POST['clear_all'])){
        $conn->query("DELETE FROM enrollments");
    }
}

/* =========================
   FETCH ENROLLMENTS
========================= */
$enrollments = $conn->query("
SELECT 
    e.id,
    e.status,
    e.enrolled_at,
    u.full_name,
    u.email,
    c.title AS course
FROM enrollments e
JOIN users u ON u.id = e.user_id
JOIN courses c ON c.id = e.course_id
ORDER BY e.id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Enrollment Management</title>

<style>
body{
    font-family:Arial;
    background:#f5f6fa;
    margin:0;
}

.container{
    padding:20px;
}

h2{
    margin-bottom:15px;
}

/* BUTTONS */
.btn{
    padding:6px 12px;
    border:none;
    color:white;
    border-radius:5px;
    cursor:pointer;
    text-decoration:none;
    margin:2px;
}

.approve{background:#16a34a;}
.reject{background:#dc2626;}
.delete{background:#7f1d1d;}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    background:white;
}

th,td{
    border:1px solid #ddd;
    padding:10px;
    text-align:left;
}

th{
    background:#2563eb;
    color:white;
}

/* STATUS */
.status{
    padding:5px 10px;
    border-radius:5px;
    color:white;
    font-size:12px;
}

.pending{background:#f59e0b;}
.ongoing{background:#f59e0b;}
.approved{background:#16a34a;}
.rejected{background:#dc2626;}

/* TOP ACTION BAR */
.actions{
    display:flex;
    gap:10px;
    margin-bottom:15px;
}
</style>
</head>

<body>

<div class="container">

<h2>📋 Enrollment Management System</h2>

<!-- BULK ACTIONS -->
<form method="POST" class="actions">

    <button type="submit" name="approve_all" class="btn approve"
    onclick="return confirm('Approve ALL pending enrollments?')">
        Save All (Approve)
    </button>

    <button type="submit" name="reject_all" class="btn reject"
    onclick="return confirm('Reject ALL pending/ongoing enrollments?')">
        Clear All (Reject)
    </button>

    <button type="submit" name="clear_all" class="btn delete"
    onclick="return confirm('DELETE ALL enrollments permanently?')">
        Delete All
    </button>

</form>

<!-- TABLE -->
<table>

<tr>
<th>Student</th>
<th>Email</th>
<th>Course</th>
<th>Status</th>
<th>Date</th>
<th>Actions</th>
</tr>

<?php while($row = $enrollments->fetch_assoc()) { ?>

<tr>

<td><?php echo $row['full_name']; ?></td>
<td><?php echo $row['email']; ?></td>
<td><?php echo $row['course']; ?></td>

<td>
<span class="status <?php echo $row['status']; ?>">
    <?php echo strtoupper($row['status']); ?>
</span>
</td>

<td><?php echo $row['enrolled_at']; ?></td>

<td>

<a class="btn approve"
href="?approve=<?php echo $row['id']; ?>">
Approve
</a>

<a class="btn reject"
href="?reject=<?php echo $row['id']; ?>">
Reject
</a>

</td>

</tr>

<?php } ?>

</table>

</div>

</body>
</html>