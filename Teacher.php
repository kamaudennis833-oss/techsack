<?php
session_start();
include "db.php";

/* 
   SIMULATED LOGIN 
 */
$teacher_id = 1; 

/* 
   TEACHER INFO
 */
$teacher = $conn->query("
    SELECT t.*, u.full_name, u.email
    FROM teachers t
    JOIN users u ON u.id = t.user_id
    WHERE t.id = $teacher_id
")->fetch_assoc();


/* 
   COURSES COUNT
 */
$courses_count = $conn->query("
    SELECT COUNT(*) as total
    FROM course_teachers
    WHERE teacher_id = $teacher_id
")->fetch_assoc()['total'];


/* 
   STUDENTS COUNT
 */
$students_count = $conn->query("
    SELECT COUNT(DISTINCT e.user_id) as total
    FROM enrollments e
    JOIN course_teachers ct ON ct.course_id = e.course_id
    WHERE ct.teacher_id = $teacher_id
")->fetch_assoc()['total'];


/* 
   NOTES 
 */
$notes_count = $conn->query("
    SELECT COUNT(*) as total
    FROM course_contents cc
    JOIN course_teachers ct ON ct.course_id = cc.course_id
    WHERE ct.teacher_id = $teacher_id AND cc.content_type='PDF'
")->fetch_assoc()['total'];


/* 
   VIDEOS COUNT
 */
$videos_count = $conn->query("
    SELECT COUNT(*) as total
    FROM course_contents cc
    JOIN course_teachers ct ON ct.course_id = cc.course_id
    WHERE ct.teacher_id = $teacher_id AND cc.content_type='Video'
")->fetch_assoc()['total'];


/* 
   QUIZZES COUNT
 */
$quiz_count = $conn->query("
    SELECT COUNT(*) as total
    FROM quizzes q
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = $teacher_id
")->fetch_assoc()['total'];


/* 
   COURSES LIST
 */
$courses = $conn->query("
SELECT 
    c.*,

    COUNT(DISTINCT e.id) AS enrolled_students,
    COUNT(DISTINCT cc.id) AS contents,

    SUM(CASE WHEN e.status='completed' THEN 1 ELSE 0 END) AS completed_students,

    SUM(CASE 
        WHEN c.course_type='Paid' THEN c.price
        ELSE 0
    END) AS potential_revenue

FROM courses c
JOIN course_teachers ct ON ct.course_id = c.id
LEFT JOIN enrollments e ON e.course_id = c.id
LEFT JOIN course_contents cc ON cc.course_id = c.id

WHERE ct.teacher_id = $teacher_id
GROUP BY c.id
");


/* 
   RECENT ACTIVITIES
 */
$activities = $conn->query("
    SELECT message, created_at
    FROM activities
    ORDER BY created_at DESC
    LIMIT 5
");


/* TEACHER ID  MY */
$teacher_id = 1;

/* 
   HANDLE UPDATE (EDIT)
 */
if(isset($_POST['update_course'])){

    $id = $_POST['id'];
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $price = $_POST['price'];

    $conn->query("
        UPDATE courses 
        SET title='$title',
            description='$desc',
            price='$price'
        WHERE id=$id
    ");

    echo "<script>alert('Course Updated');</script>";
}

/* 
   HANDLE DELETE
 */
if(isset($_GET['delete'])){

    $id = $_GET['delete'];

    $conn->query("DELETE FROM courses WHERE id=$id");

    echo "<script>window.location='teacher_courses.php';</script>";
}

/* 
   FETCH COURSES
 */
$courses = $conn->query("
SELECT 
    c.*,
    COUNT(DISTINCT e.id) AS students,
    COUNT(DISTINCT cc.id) AS contents
FROM courses c
JOIN course_teachers ct ON ct.course_id = c.id
LEFT JOIN enrollments e ON e.course_id = c.id
LEFT JOIN course_contents cc ON cc.course_id = c.id
WHERE ct.teacher_id = $teacher_id
GROUP BY c.id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Dashboard</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial;}
body{background:#f5f6fa;display:flex;}
.sidebar{width:260px;height:100vh;background:rgb(65, 110, 181);color:white;position:fixed;}
.logo{text-align:center;padding:25px;font-size:22px;font-weight:bold;border-bottom:1px solid rgba(65, 110, 181, 0.1);}
.sidebar ul{list-style:none;}
.sidebar ul li a{display:block;color:white;text-decoration:none;padding:1.3rem ;padding-left:30px;}
.sidebar ul li a:hover{background:#334155;}
.main{margin-left:260px;width:100%;padding:25px;}
.header{background:white;padding:20px;border-radius:10px;display:flex;justify-content:space-between;}
.cards{margin-top:25px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;}
.card{background:white;padding:20px;border-radius:10px;}
.card h2{margin-top:10px;color:#2563eb;}
.section{margin-top:25px;background:white;padding:20px;border-radius:10px;}
table{width:100%;border-collapse:collapse;}
table th,table td{border:1px solid #ddd;padding:12px;}
table th{background:rgb(65, 110, 181);color:white;}
.btn{padding:8px 15px;border:none;border-radius:5px;color:white;}
.view{background:#16a34a;}
.edit{background:#f59e0b;}
.delete{background:#dc2626;}
.activity{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #eee;}
</style>

</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
<div class="logo">LMS Teacher</div>
<ul>
<li><a href="#"><i class="fas fa-home"></i> Dashboard</a></li>
<li><a href="my_course.php"><i class="fas fa-book"></i> My Courses</a></li>
<li><a href="notes.php"><i class="fas fa-file-pdf"></i> Notes</a></li>
<li><a href="vedio.php"><i class="fas fa-video"></i> Videos</a></li>
<li><a href="Quiz.php"><i class="fas fa-question-circle"></i> Quizzes</a></li>
<li><a href="stud.php"><i class="fas fa-users"></i> Students</a></li>
<li><a href="report.php"><i class="fas fa-chart-line"></i> Reports</a></li>
<li><a href="teacher_Announcement.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
<li><a href="teacher_profile.php"><i class="fas fa-user"></i> Profile</a></li>
<li><a href="#"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</div>

<!-- MAIN -->
<div class="main">

<div class="header">
<div>
<h2>Teacher Dashboard</h2>
<p>Welcome back, <?php echo $teacher['full_name']; ?></p>
</div>

<div>
<strong><?php echo $teacher['email']; ?></strong>
</div>
</div>

<!-- CARDS -->
<div class="cards">

<div class="card">
<i class="fas fa-book"></i>
<h2><?php echo $courses_count; ?></h2>
<p>My Courses</p>
</div>

<div class="card">
<i class="fas fa-users"></i>
<h2><?php echo $students_count; ?></h2>
<p>Students</p>
</div>

<div class="card">
<i class="fas fa-file-pdf"></i>
<h2><?php echo $notes_count; ?></h2>
<p>Notes Uploaded</p>
</div>

<div class="card">
<i class="fas fa-video"></i>
<h2><?php echo $videos_count; ?></h2>
<p>Videos Uploaded</p>
</div>

<div class="card">
<i class="fas fa-question-circle"></i>
<h2><?php echo $quiz_count; ?></h2>
<p>Quizzes</p>
</div>

</div>

<!-- COURSES -->
<div class="section">
<h3>My Courses</h3>

<table>
<tr>
<th>Course</th>
<th>Price</th>
<th style="width:160px;">Enrolled Students</th>
<th>Status</th>
</tr>

<?php 
while($row = $courses->fetch_assoc()) { 

    $course_id = $row['id'];

    $enrolled = $conn->query("
        SELECT COUNT(*) AS total 
        FROM enrollments 
        WHERE course_id = $course_id
    ")->fetch_assoc()['total'];
?>

<tr>
<td><?php echo $row['title']; ?></td>
<td><?php echo $row['price']; ?></td>
<td><b><?php echo $enrolled; ?></b></td>
<td><?php echo $row['status']; ?></td>
</tr>

<?php } ?>

</table>
</div>

<!-- ACTIVITIES -->
<div class="section">
<h3>Recent Activities</h3>

<?php while($a = $activities->fetch_assoc()) { ?>
<div class="activity">
<span><?php echo $a['message']; ?></span>
<span><?php echo $a['created_at']; ?></span>
</div>
<?php } ?>

</div>

</div>

</div>



<!-- MY_COURSE -->
<div class="box" id="my_courseSection" style="display:none;">
<style>
body{font-family:Arial;background:#f5f6fa;margin:0;}
.container{padding:20px;margin-left:0;}

/* CARDS */
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:15px;}

.card{
background:#fff;padding:15px;border-radius:10px;
box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.btn{
padding:6px 10px;border:none;border-radius:5px;
color:#fff;cursor:pointer;font-size:12px;
}
.view{background:#16a34a;}
.edit{background:#f59e0b;}
.del{background:#dc2626;}
.content{background:#2563eb;}

/* MODAL */
.modal{
display:none;
position:fixed;
top:0;left:0;
width:100%;height:100%;
background:rgba(0,0,0,.6);
justify-content:center;
align-items:center;
}

.modal-box{
background:#fff;
padding:20px;
width:500px;
border-radius:10px;
}
</style>
<div class="container">

<h2>📚 My Courses</h2>

<div class="grid">

<?php while($row = $courses->fetch_assoc()) { ?>

<div class="card">

<h3><?php echo $row['title']; ?></h3>
<p><?php echo $row['category']; ?></p>

<p>👨‍🎓 Students: <b><?php echo $row['students']; ?></b></p>
<p>📂 Content: <b><?php echo $row['contents']; ?></b></p>
<p>💰 Price: KES <?php echo number_format($row['price']); ?></p>

<hr>

<div style="display:flex;gap:5px;flex-wrap:wrap;">


<button class="btn edit" onclick="editCourse(
<?php echo $row['id']; ?>,
`<?php echo addslashes($row['title']); ?>`,
`<?php echo addslashes($row['description']); ?>`,
<?php echo $row['price']; ?>
)">Edit</button>

<a href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete course?')">
<button class="btn del">Delete</button>
</a>

</div>

</div>

<?php } ?>

</div>

</div>

<!--  MODAL  -->
<div class="modal" id="modal">
<div class="modal-box" id="modalBox"></div>
</div>

<script>

/* OPEN MODAL */
function openModal(html){
    document.getElementById("modal").style.display="flex";
    document.getElementById("modalBox").innerHTML=html;
}


/* EDIT FORM (INLINE) */
function editCourse(id,title,desc,price){

    let form = `
    <h3>Edit Course</h3>

    <form method="POST">

    <input type="hidden" name="id" value="${id}">

    <label>Title</label><br>
    <input name="title" value="${title}" style="width:100%;"><br><br>

    <label>Description</label><br>
    <textarea name="description" style="width:100%;">${desc}</textarea><br><br>

    <label>Price</label><br>
    <input name="price" value="${price}" style="width:100%;"><br><br>

    <button name="update_course">Update</button>

    </form>
    `;

    openModal(form);
}

/* CLOSE MODAL */
window.onclick = function(e){
    if(e.target.id=="modal"){
        document.getElementById("modal").style.display="none";
    }
}

</script>

</div>
</div>
</body>
</html>