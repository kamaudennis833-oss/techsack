<?php
include "db.php";
session_start();

$teacher_id = 1;
$user_id = 1;

/* 
   CREATE UPLOAD FOLDER
 */
$upload_dir = "uploads/notes/";
if(!file_exists($upload_dir)){
    mkdir($upload_dir, 0777, true);
}

/* 
   ADD NOTE WITH UPLOAD
 */
if(isset($_POST['add_note'])){

    $course_id = $_POST['course_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    $access_type = $_POST['access_type'];

    $file_path = "";

    if(!empty($_FILES['file']['name'])){

        $file_name = time() . "_" . basename($_FILES['file']['name']);
        $target = $upload_dir . $file_name;

        move_uploaded_file($_FILES['file']['tmp_name'], $target);

        $file_path = $target;
    }

    $conn->query("
        INSERT INTO notes(course_id,title,content,access_type,created_by,file_path)
        VALUES('$course_id','$title','$content','$access_type','$teacher_id','$file_path')
    ");
}

/* 
   DELETE NOTE
 */
if(isset($_GET['delete'])){

    $id = $_GET['delete'];

    $old = $conn->query("SELECT file_path FROM notes WHERE id=$id")->fetch_assoc();

    if(!empty($old['file_path']) && file_exists($old['file_path'])){
        unlink($old['file_path']);
    }

    $conn->query("DELETE FROM notes WHERE id=$id");

    echo "<script>window.location='teacher_notes.php';</script>";
}

/* 
   VIEW NOTE 
 */
if(isset($_GET['view'])){

    $note_id = $_GET['view'];

    $note = $conn->query("
        SELECT n.*, c.course_type
        FROM notes n
        JOIN courses c ON c.id = n.course_id
        WHERE n.id=$note_id
    ")->fetch_assoc();

    $check = $conn->query("
        SELECT * FROM enrollments 
        WHERE user_id=$user_id 
        AND course_id={$note['course_id']}
        AND status IN ('ongoing','completed','approved')
    ");

    $isEnrolled = $check->num_rows > 0;

    if($note['access_type'] == 'paid' && !$isEnrolled){
        die("<h2>Access Denied ❌</h2><p>You must enroll to access this note.</p>");
    }
?>

<div style="padding:20px;font-family:Arial;user-select:none;">

<h2><?php echo $note['title']; ?></h2>

<?php if($note['file_path']){ ?>

    <iframe src="<?php echo $note['file_path']; ?>" width="100%" height="500px"></iframe>

<?php } ?>

<p style="white-space:pre-line;"><?php echo $note['content']; ?></p>

</div>

<script>
document.addEventListener("contextmenu", e => e.preventDefault());
document.addEventListener("copy", e => e.preventDefault());
document.addEventListener("cut", e => e.preventDefault());
document.addEventListener("paste", e => e.preventDefault());
</script>

<?php exit; }

/* 
   FETCH NOTES
 */
$notes = $conn->query("
SELECT n.*, c.title AS course_title
FROM notes n
JOIN courses c ON c.id = n.course_id
JOIN course_teachers ct ON ct.course_id = c.id
WHERE ct.teacher_id = $teacher_id
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Notes System</title>

<style>
body{font-family:Arial;background:#f5f6fa;margin:0;}
.container{padding:20px;}

table{width:100%;border-collapse:collapse;background:#fff;}
th,td{padding:10px;border:1px solid #ddd;}
th{background:#2563eb;color:#fff;}

input,textarea,select{
width:100%;
padding:8px;
margin:5px 0;
}

button{
padding:8px 12px;
border:none;
background:#2563eb;
color:#fff;
cursor:pointer;
}

a{color:red;}
</style>
</head>

<body>

<div class="container">

<h2>📘 Notes Management </h2>

<!-- ADD NOTE -->
<form method="POST" enctype="multipart/form-data">

<h3>Create Note</h3>

<select name="course_id" required>
<option value="">Select Course</option>
<?php
$courses = $conn->query("SELECT * FROM courses");
while($c = $courses->fetch_assoc()){
    echo "<option value='{$c['id']}'>{$c['title']}</option>";
}
?>
</select>

<input name="title" placeholder="Note Title" required>

<textarea name="content" placeholder="Write text notes..." rows="4"></textarea>

<input type="file" name="file">

<select name="access_type">
<option value="free">Free Course</option>
<option value="paid">Paid Course</option>
</select>

<button name="add_note">Upload Note</button>

</form>

<hr>

<!-- LIST -->
<table>

<tr>
<th>Course</th>
<th>Title</th>
<th>Access</th>
<th>File</th>
<th>Action</th>
</tr>

<?php while($n = $notes->fetch_assoc()) { ?>

<tr>
<td><?php echo $n['course_title']; ?></td>
<td><?php echo $n['title']; ?></td>
<td><?php echo $n['access_type']; ?></td>
<td>
<?php if($n['file_path']) echo "Uploaded"; else echo "Text Only"; ?>
</td>

<td>
<a href="?view=<?php echo $n['id']; ?>" target="_blank">View</a> |
<a href="?delete=<?php echo $n['id']; ?>" onclick="return confirm('Delete note?')">Delete</a>
</td>

</tr>

<?php } ?>

</table>

</div>

</body>
</html>