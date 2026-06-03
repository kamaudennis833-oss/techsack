<?php
include "db.php";
session_start();

$user_id = 1;
$user_role = "Teacher"; 

/* 
   UPLOAD FOLDER
 */
$upload_dir = "uploads/videos/";
if(!file_exists($upload_dir)){
    mkdir($upload_dir, 0777, true);
}

/* 
   UPLOAD VIDEO 
 */
if(isset($_POST['upload_video'])){

    if($user_role != "Teacher" && $user_role != "Admin"){
        die("Unauthorized");
    }

    $course_id = $_POST['course_id'];
    $title = $_POST['title'];
    $access = $_POST['access_type'];

    $file_path = "";

    if(!empty($_FILES['video']['name'])){

        $file_name = time() . "_" . basename($_FILES['video']['name']);
        $target = $upload_dir . $file_name;

        move_uploaded_file($_FILES['video']['tmp_name'], $target);

        $file_path = $target;
    }

    $conn->query("
        INSERT INTO course_videos(course_id,title,video_path,access_type,uploaded_by)
        VALUES('$course_id','$title','$file_path','$access','$user_id')
    ");
}

/* 
   DELETE VIDEO
 */
if(isset($_GET['delete'])){

    if($user_role != "Teacher" && $user_role != "Admin"){
        die("Unauthorized");
    }

    $id = $_GET['delete'];

    $old = $conn-script>query("SELECT video_path FROM course_videos WHERE id=$id")->fetch_assoc();

    if($old && file_exists($old['video_path'])){
        unlink($old['video_path']);
    }

    $conn->query("DELETE FROM course_videos WHERE id=$id");

    echo "<script>window.location='videos.php';</script>";
}

/* 
   SECURE VIEW VIDEO
 */
if(isset($_GET['view'])){

    $id = $_GET['view'];

    $video = $conn->query("
        SELECT v.*, c.course_type
        FROM course_videos v
        JOIN courses c ON c.id = v.course_id
        WHERE v.id=$id
    ")->fetch_assoc();

    /* ENROLLMENT CHECK */
    $check = $conn->query("
        SELECT * FROM enrollments
        WHERE user_id=$user_id
        AND course_id={$video['course_id']}
        AND status IN ('ongoing','completed','approved')
    ");

    $isEnrolled = $check->num_rows > 0;

    if($video['access_type'] == 'paid' && !$isEnrolled){
        die("<h2>Access Denied ❌</h2><p>You must enroll in this course to watch this video.</p>");
    }

?>

<div style="background:#000;padding:20px;color:#fff;">

<h2><?php echo $video['title']; ?></h2>

<video
    width="100%"
    controls
    controlsList="nodownload"
    oncontextmenu="return false"
    disablepictureinpicture
    id="videoPlayer">

    <source src="<?php echo $video['video_path']; ?>" type="video/mp4">
</video>

</div>

<>

/* BASIC PROTECTION  */
document.addEventListener("contextmenu", e => e.preventDefault());

document.addEventListener("keydown", function(e){
    if(e.key === "PrintScreen"){
        alert("Screen capture disabled (limited protection)");
    }
});

/* Disable drag */
document.getElementById("videoPlayer").onmousedown = e => e.preventDefault();

</script>

<?php exit; }

/* 
   FETCH VIDEOS
 */
$videos = $conn->query("
SELECT v.*, c.title AS course
FROM course_videos v
JOIN courses c ON c.id = v.course_id
ORDER BY v.id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Video Module</title>

<style>
body{font-family:Arial;background:#f5f6fa;}
.container{padding:20px;}

table{width:100%;border-collapse:collapse;background:#fff;}
th,td{padding:10px;border:1px solid #ddd;}
th{background:#2563eb;color:#fff;}

input,select{
width:100%;
padding:8px;
margin:5px 0;
}

button{
padding:8px 12px;
background:#2563eb;
color:#fff;
border:none;
cursor:pointer;
}

a{color:red;}
</style>
</head>

<body>

<div class="container">

<h2>🎬 Video Management System</h2>

<!-- UPLOAD FORM -->
<?php if($user_role=="Teacher" || $user_role=="Admin"){ ?>

<form method="POST" enctype="multipart/form-data">

<h3>Upload Video</h3>

<select name="course_id" required>
<option>Select Course</option>
<?php
$courses = $conn->query("SELECT * FROM courses");
while($c = $courses->fetch_assoc()){
    echo "<option value='{$c['id']}'>{$c['title']}</option>";
}
?>
</select>

<input name="title" placeholder="Video Title" required>

<input type="file" name="video" required>

<select name="access_type">
<option value="free">Free Course</option>
<option value="paid">Paid Course</option>
</select>

<button name="upload_video">Upload</button>

</form>

<hr>

<?php } ?>

<!-- LIST VIDEOS -->
<table>

<tr>
<th>Course</th>
<th>Title</th>
<th>Access</th>
<th>Action</th>
</tr>

<?php while($v = $videos->fetch_assoc()) { ?>

<tr>
<td><?php echo $v['course_title']; ?></td>
<td><?php echo $v['title']; ?></td>
<td><?php echo $v['access_type']; ?></td>

<td>
<a href="?view=<?php echo $v['id']; ?>" target="_blank">Watch</a>

<?php if($user_role=="Teacher" || $user_role=="Admin"){ ?>
 | <a href="?delete=<?php echo $v['id']; ?>" onclick="return confirm('Delete video?')">Delete</a>
<?php } ?>

</td>
</tr>

<?php } ?>

</table>

</div>

</body>
</html>