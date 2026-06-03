<?php
include "db.php";
session_start();

/* TEMP USER (replace with session) */
$teacher_id = 1;

/* 
   UPDATE PROFILE
 */
if(isset($_POST['update'])){

    $specialization = $_POST['specialization'];
    $qualification = $_POST['qualification'];
    $experience_years = $_POST['experience_years'];

    $conn->query("
        UPDATE teachers 
        SET specialization='$specialization',
            qualification='$qualification',
            experience_years='$experience_years'
        WHERE id=$teacher_id
    ");
}
/* 
   GET TEACHER INFO
 */
$teacher = $conn->query("
SELECT t.*, u.full_name, u.email, u.phone
FROM teachers t
JOIN users u ON u.id = t.user_id
WHERE t.id = $teacher_id
")->fetch_assoc();

/* 
   STATS
 */
$courses = $conn->query("
SELECT COUNT(*) AS total
FROM course_teachers
WHERE teacher_id=$teacher_id
")->fetch_assoc()['total'];

$students = $conn->query("
SELECT COUNT(DISTINCT e.user_id) AS total
FROM enrollments e
JOIN course_teachers ct ON ct.course_id = e.course_id
WHERE ct.teacher_id=$teacher_id
")->fetch_assoc()['total'];

$videos = $conn->query("
SELECT COUNT(*) AS total
FROM course_videos v
WHERE v.uploaded_by=$teacher_id
")->fetch_assoc()['total'];

$announcements = $conn->query("
SELECT COUNT(*) AS total
FROM announcements
WHERE teacher_id=$teacher_id
")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Teacher Profile</title>

<style>
body{
    font-family:Arial;
    background:#f5f6fa;
    margin:0;
}

.container{padding:20px;}

.card{
    background:#fff;
    padding:20px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,.1);
    margin-bottom:15px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:15px;
}

.stat{
    background:#fff;
    padding:15px;
    border-radius:10px;
    text-align:center;
}

.stat h2{
    color:#2563eb;
}

input{
    width:100%;
    padding:10px;
    margin:5px 0;
}

button{
    background:#2563eb;
    color:#fff;
    padding:10px;
    border:none;
    cursor:pointer;
    border-radius:5px;
}
</style>

</head>

<body>

<div class="container">

<h2 style="text-align:center;">👨‍🎓 Teacher Profile</h2>

<!-- PROFILE INFO -->
<div class="card">

<h3><?php echo $teacher['full_name']; ?></h3>
<p>Email: <?php echo $teacher['email']; ?></p>
<p>Phone: <?php echo $teacher['phone']; ?></p>
<p>Employee No: <?php echo $teacher['employee_no']; ?></p>

</div>

<!-- STATS -->
<div class="grid">

<div class="stat">
<h2><?php echo $courses; ?></h2>
<p>Courses</p>
</div>

<div class="stat">
<h2><?php echo $students; ?></h2>
<p>Students</p>
</div>

<div class="stat">
<h2><?php echo $videos; ?></h2>
<p>Videos</p>
</div>

<div class="stat">
<h2><?php echo $announcements; ?></h2>
<p>Announcements</p>
</div>

</div>

<!-- UPDATE PROFILE -->
<div class="card">

<h3>Update Profile</h3>

<form method="POST">

<input type="text" name="specialization" 
value="<?php echo $teacher['specialization']; ?>" 
placeholder="Specialization">

<input type="text" name="qualification" 
value="<?php echo $teacher['qualification']; ?>" 
placeholder="Qualification">

<input type="number" name="experience_years" 
value="<?php echo $teacher['experience_years']; ?>" 
placeholder="Experience Years">

<button name="update">Update Profile</button>

</form>

</div>

</div>

</body>
</html>