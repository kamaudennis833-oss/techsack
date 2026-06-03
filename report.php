<?php
include "db.php";
session_start();

$teacher_id = 1;

/* 
   TOTAL COURSES
 */
$total_courses = $conn->query("
SELECT COUNT(*) AS total
FROM course_teachers
WHERE teacher_id=$teacher_id
")->fetch_assoc()['total'];

/* 
   TOTAL STUDENTS
  */
$total_students = $conn->query("
SELECT COUNT(DISTINCT e.user_id) AS total
FROM enrollments e
JOIN course_teachers ct ON ct.course_id = e.course_id
WHERE ct.teacher_id=$teacher_id
")->fetch_assoc()['total'];

/* 
   TOTAL CONTENT
  */
$total_content = $conn->query("
SELECT COUNT(*) AS total
FROM course_contents cc
JOIN course_teachers ct ON ct.course_id = cc.course_id
WHERE ct.teacher_id=$teacher_id
")->fetch_assoc()['total'];

/* 
   TOTAL VIDEOS
 */
$total_videos = $conn->query("
SELECT COUNT(*) AS total
FROM course_videos v
JOIN course_teachers ct ON ct.course_id = v.course_id
WHERE ct.teacher_id=$teacher_id
")->fetch_assoc()['total'];

/* 
   TOTAL NOTES
 */
$total_notes = $conn->query("
SELECT COUNT(*) AS total
FROM notes n
JOIN course_teachers ct ON ct.course_id = n.course_id
WHERE ct.teacher_id=$teacher_id
")->fetch_assoc()['total'];

/* 
   TOP COURSES
 */
$top_courses = $conn->query("
SELECT 
    c.title,
    COUNT(e.id) AS students
FROM courses c
JOIN course_teachers ct ON ct.course_id = c.id
LEFT JOIN enrollments e ON e.course_id = c.id
WHERE ct.teacher_id=$teacher_id
GROUP BY c.id
ORDER BY students DESC
LIMIT 5
");

/* 
   REVENUE
 */
$revenue = $conn->query("
SELECT SUM(p.amount) AS total_revenue
FROM payments p
JOIN enrollments e ON e.user_id = p.user_id
JOIN course_teachers ct ON ct.course_id = e.course_id
WHERE ct.teacher_id=$teacher_id
AND p.status='success'
")->fetch_assoc()['total_revenue'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Teacher Reports</title>

<style>
body{font-family:Arial;background:#f5f6fa;margin:0;}
.container{padding:20px;}

.grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
gap:15px;
}

.card{
background:#fff;
padding:15px;
border-radius:10px;
box-shadow:0 2px 10px rgba(0,0,0,.1);
text-align:center;
}

.card h2{color:#2563eb;}

table{
width:100%;
border-collapse:collapse;
background:#fff;
margin-top:20px;
}

th,td{
padding:10px;
border:1px solid #ddd;
}

th{
background:#2563eb;
color:#fff;
}
</style>
</head>

<body>

<div class="container">

<h2>📊 Teacher Reports Dashboard</h2>

<!-- STATS -->
<div class="grid">

<div class="card">
<h2><?php echo $total_courses; ?></h2>
<p>My Courses</p>
</div>

<div class="card">
<h2><?php echo $total_students; ?></h2>
<p>Total Students</p>
</div>

<div class="card">
<h2><?php echo $total_content; ?></h2>
<p>Course Content</p>
</div>

<div class="card">
<h2><?php echo $total_videos; ?></h2>
<p>Videos Uploaded</p>
</div>

<div class="card">
<h2><?php echo $total_notes; ?></h2>
<p>Notes Uploaded</p>
</div>

<div class="card">
<h2>KES <?php echo number_format($revenue ?? 0); ?></h2>
<p>Total Revenue</p>
</div>

</div>

<!-- TOP COURSES -->
<h3 style="margin-top:30px;">🏆 Top Performing Courses</h3>

<table>

<tr>
<th>Course</th>
<th>Students</th>
</tr>

<?php while($row = $top_courses->fetch_assoc()) { ?>

<tr>
<td><?php echo $row['title']; ?></td>
<td><?php echo $row['students']; ?></td>
</tr>

<?php } ?>

</table>

</div>

</body>
</html>