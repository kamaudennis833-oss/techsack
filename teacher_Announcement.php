<?php
include "db.php";
session_start();

$teacher_id = 1;
/* 
   CREATE ANNOUNCEMENT
 */
if(isset($_POST['add'])){

    $course_id = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);

    $stmt = $conn->prepare("
        INSERT INTO announcements (course_id, teacher_id, title, message)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param("iiss", $course_id, $teacher_id, $title, $message);
    $stmt->execute();
}

/* 
   DELETE ANNOUNCEMENT
 */
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    $conn->query("
        DELETE FROM announcements
        WHERE id=$id AND teacher_id=$teacher_id
    ");
}

/* 
   COURSES (ASSIGNED TO TEACHER)
 */
$courses = $conn->query("
SELECT c.id, c.title
FROM courses c
JOIN course_teachers ct ON ct.course_id = c.id
WHERE ct.teacher_id = $teacher_id
");

/* 
   ANNOUNCEMENTS LIST
 */
$announcements = $conn->query("
SELECT a.*, c.title AS course
FROM announcements a
JOIN courses c ON c.id = a.course_id
WHERE a.teacher_id = $teacher_id
ORDER BY a.created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Teacher Announcements</title>

<style>
body{font-family:Arial;background:#f5f6fa;margin:0;}
.container{padding:20px;}

.box{
background:#fff;
padding:15px;
border-radius:10px;
margin-bottom:20px;
}

input,textarea,select{
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

table{
width:100%;
border-collapse:collapse;
background:#fff;
}

th,td{
padding:10px;
border:1px solid #ddd;
}

th{
background:#2563eb;
color:#fff;
}

.delete{
background:red;
color:white;
padding:5px 10px;
text-decoration:none;
}
</style>

</head>
<body>

<div class="container">

<h2>📢 Teacher Announcements</h2>

<!-- FORM -->
<div class="box">

<form method="POST">

<label>Select Course</label>
<select name="course_id" required>
<?php while($c = $courses->fetch_assoc()) { ?>
<option value="<?php echo $c['id']; ?>">
<?php echo $c['title']; ?>
</option>
<?php } ?>
</select>

<input type="text" name="title" placeholder="Title" required>

<textarea name="message" placeholder="Message..." required></textarea>

<button name="add">Post Announcement</button>

</form>

</div>

<!-- TABLE -->
<table>

<tr>
<th>Course</th>
<th>Title</th>
<th>Message</th>
<th>Date</th>
<th>Action</th>
</tr>

<?php while($a = $announcements->fetch_assoc()) { ?>

<tr>
<td><?php echo $a['course']; ?></td>
<td><?php echo $a['title']; ?></td>
<td><?php echo $a['message']; ?></td>
<td><?php echo $a['created_at']; ?></td>
<td>
<a class="delete" href="?delete=<?php echo $a['id']; ?>" onclick="return confirm('Delete announcement?')">Delete</a>
</td>
</tr>

<?php } ?>

</table>

</div>

</body>
</html>