<?php
session_start();
include "db.php";

/*  STUDENT ACTIONS  */

// ACTIVATE / SUSPEND / DELETE / RESET PASSWORD
if(isset($_POST['student_action'])){

    $student_id = $_POST['student_id'];
    $action = $_POST['action'];

    if($action == "activate"){
        mysqli_query($conn, "UPDATE students SET status='active' WHERE id='$student_id'");
    }

    if($action == "suspend"){
        mysqli_query($conn, "UPDATE students SET status='suspended' WHERE id='$student_id'");
    }

    if($action == "delete"){
        mysqli_query($conn, "DELETE FROM students WHERE id='$student_id'");
    }

    if($action == "reset_password"){
        $newPass = password_hash("123456", PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE students SET password='$newPass' WHERE id='$student_id'");
    }

}

/*  ENROLLMENT ACTIONS  */

if(isset($_POST['enroll_action'])){

    $enroll_id = $_POST['enroll_id'];
    $action = $_POST['action'];

    // APPROVE ENROLLMENT
    if($action == "approve"){
        mysqli_query($conn, "
            UPDATE enrollments 
            SET status='approved' 
            WHERE id='$enroll_id'
        ");
    }

    // REJECT ENROLLMENT
    if($action == "reject"){
        mysqli_query($conn, "
            UPDATE enrollments 
            SET status='rejected' 
            WHERE id='$enroll_id'
        ");    
    }
    // DELETE ENROLLMENT
   if($action == "delete"){
    mysqli_query($conn, "
        UPDATE enrollments
        SET status='deleted'
        WHERE id='$enroll_id'
    ");
   }


}

/* FETCH DATA  */

$students = mysqli_query($conn, "
    SELECT * FROM students 
    ORDER BY id DESC
");

$enrollments = mysqli_query($conn, "
    SELECT 
        e.*,
        u.full_name,
        c.title
    FROM enrollments e

    JOIN users u
        ON e.user_id = u.id

    JOIN courses c
        ON e.course_id = c.id

    ORDER BY e.id DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Management</title>

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
    padding:20px;
    margin:0;
}

.container{
    max-width:1200px;
    margin:auto;
}

.card{
    background:#fff;
    padding:20px;
    margin-bottom:20px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

th,td{
    border:1px solid #ddd;
    padding:10px;
    text-align:left;
}

button{
    padding:6px 10px;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

.btn-success{
    background:#28a745;
    color:#fff;
}

.btn-warning{
    background:#f0ad4e;
    color:#fff;
}

.btn-danger{
    background:#dc3545;
    color:#fff;
}

.btn-primary{
    background:#007bff;
    color:#fff;
}

.status{
    padding:4px 8px;
    border-radius:5px;
    color:#fff;
    font-size:12px;
}

.active{
    background:green;
}

.suspended{
    background:red;
}

.pending{
    background:orange;
}

.approved{
    background:green;
}

.rejected{
    background:red;
}

select{
    padding:6px;
    border-radius:5px;
}

</style>
</head>

<body>

<div class="container">

<!--  STUDENT RECORDS  -->

<div class="card">

<h2>Student Records</h2>

<table>

<tr>
<th>ID</th>
<th>Full Name</th>
<th>Email</th>
<th>Phone</th>
<th>Registered</th>
<th>Status</th>
<th>Actions</th>
</tr>

<?php while($s = mysqli_fetch_assoc($students)){ ?>

<tr>

<td><?= $s['id'] ?></td>
<td><?= $s['full_name'] ?></td>
<td><?= $s['email'] ?></td>
<td><?= $s['phone'] ?></td>
<td><?= $s['created_at'] ?></td>

<td>
    <span class="status <?= $s['status'] ?>">
        <?= $s['status'] ?>
    </span>
</td>

<td>

<form method="POST" style="display:inline;">

    <input 
        type="hidden" 
        name="student_id" 
        value="<?= $s['id'] ?>"
    >

    <select name="action">
        <option value="activate">Activate</option>
        <option value="suspend">Suspend</option>
        <option value="reset_password">Reset Password</option>
        <option value="delete">Delete</option>
    </select>

    <button 
        type="submit"
        class="btn-primary" 
        name="student_action">
        Go
    </button>

</form>

</td>

</tr>

<?php } ?>

</table>

</div>

<!--  ENROLLMENT MONITORING  -->

<div class="card">

<h2>Enrollment Monitoring</h2>

<table>

<tr>
<th>ID</th>
<th>Student</th>
<th>Course</th>
<th>Status</th>
<th>Progress %</th>
<th>Enrolled At</th>
<th>Actions</th>
</tr>

<?php while($e = mysqli_fetch_assoc($enrollments)){ ?>

<tr>

<td><?= $e['id'] ?></td>
<td><?= $e['full_name'] ?></td>
<td><?= $e['title'] ?></td>

<td>
    <span class="status <?= $e['status'] ?>">
        <?= $e['status'] ?>
    </span>
</td>

<td><?= $e['progress'] ?>%</td>
<td><?= $e['enrolled_at'] ?></td>

<td>

<form method="POST">

    <input 
        type="hidden" 
        name="enroll_id" 
        value="<?= $e['id'] ?>"
    >

    <button 
        type="submit"
        class="btn-success"
        name="action"
        value="approve">
        Approve
    </button>

    <button 
        type="submit"
        class="btn-warning"
        name="action"
        value="reject">
        Reject
    </button>

    <button 
    type="submit"
    class="btn-danger"
    name="action"
    value="delete"
    onclick="return confirm('Delete this enrollment?')">
    Delete
    </button>

    <input 
        type="hidden" 
        name="enroll_action" 
        value="1"
    >

</form>

</td>

</tr>

<?php } ?>

</table>

</div>

</div>

</body>
</html>