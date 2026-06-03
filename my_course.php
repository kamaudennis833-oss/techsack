<?php
include "db.php";
session_start();

/* TEACHER ID  */
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
<html>
<head>
<title>Teacher Courses</title>

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
</head>

<body>

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

</body>
</html>