<?php
session_start();
include "db.php";

/*  FETCH COURSES  */
$courses = mysqli_query($conn, "SELECT * FROM courses ORDER BY id DESC");

/*  HANDLE CREATE COURSE  */
if(isset($_POST['create_course'])){

    $title = $_POST['title'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $custom_category = $_POST['custom_category'];
    $type = $_POST['type'];
    $price = $_POST['price'];
    $status = $_POST['status'];

    /*  IMAGE UPLOAD  */
    $thumbnail = "";
    if(!empty($_FILES['thumbnail']['name'])){
        $target_dir = "uploads/";
        if(!is_dir($target_dir)) mkdir($target_dir);

        $thumbnail = $target_dir . time() . "_" . basename($_FILES["thumbnail"]["name"]);
        move_uploaded_file($_FILES["thumbnail"]["tmp_name"], $thumbnail);
    }

    $stmt = $conn->prepare("INSERT INTO courses 
    (title, description, category, custom_category, thumbnail, type, price, status)
    VALUES (?,?,?,?,?,?,?,?)");

    $stmt->bind_param("ssssssds",
        $title,
        $description,
        $category,
        $custom_category,
        $thumbnail,
        $type,
        $price,
        $status
    );

    $stmt->execute();

    header("Location: courses.php");
    exit;
}

/*  HANDLE DELETE / ARCHIVE  */
if(isset($_POST['delete_course'])){

    $id = $_POST['course_id'];
    $action = $_POST['action'];

    if($action == "delete"){
        mysqli_query($conn, "DELETE FROM courses WHERE id='$id'");
    } else {
        mysqli_query($conn, "UPDATE courses SET status='archived' WHERE id='$id'");
    }

    header("Location: courses.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>LMS Course Management</title>

<style>
body{font-family:Arial;background:#f4f6f9;padding:20px;margin:0}
.container{max-width:1200px;margin:auto}
.card{background:#fff;padding:20px;margin-bottom:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
input,textarea,select{width:100%;padding:10px;margin-bottom:10px;border:1px solid #ccc;border-radius:5px}
button{padding:10px;border:none;border-radius:5px;cursor:pointer}
.btn-primary{background:#007bff;color:#fff}
.btn-warning{background:#f0ad4e;color:#fff}
.btn-danger{background:#dc3545;color:#fff}
.btn-success{background:#28a745;color:#fff}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ddd;padding:10px}
.status{padding:5px 10px;border-radius:5px;color:#fff}
.draft{background:gray}
.published{background:green}
.archived{background:orange}
.suspended{background:red}
img{width:80px;border-radius:5px}
</style>

</head>
<body>

<div class="container">

<!--  CREATE COURSE  -->
<div class="card">
<h2>Create Course</h2>

<form method="POST" enctype="multipart/form-data">

    <input type="text" name="title" placeholder="Course Title" required>

    <textarea name="description" placeholder="Course Description" required></textarea>

    <label>Category / Skill Area</label>
    <select name="category" required>
        <option value="">Select Category</option>
        <option value="web_dev">Web Development</option>
        <option value="mobile_dev">Mobile Development</option>
        <option value="data_science">Data Science</option>
        <option value="ui_ux">UI/UX Design</option>
        <option value="cyber_security">Cyber Security</option>
        <option value="ai_ml">AI / ML</option>
    </select>

    <input type="text" name="custom_category" placeholder="Or custom category">

    <label>Thumbnail</label>
    <input type="file" name="thumbnail" required>

    <select name="type">
        <option value="free">Free</option>
        <option value="paid">Paid</option>
    </select>

    <input type="number" name="price" placeholder="Price">

    <select name="status">
        <option value="draft">Draft</option>
        <option value="published">Published</option>
    </select>

    <button class="btn-primary" name="create_course">Create Course</button>
</form>
</div>

<!--  DELETE / ARCHIVE  -->
<div class="card">
<h2>Delete / Archive</h2>

<form method="POST">

    <select name="course_id" required>
        <option value="">Select Course</option>
        <?php while($c = mysqli_fetch_assoc($courses)){ ?>
            <option value="<?= $c['id'] ?>">
                <?= $c['title'] ?>
            </option>
        <?php } ?>
    </select>

    <select name="action">
        <option value="archive">Archive</option>
        <option value="delete">Delete</option>
    </select>

    <button class="btn-danger" name="delete_course">Apply</button>
</form>
</div>

<!--  COURSE TABLE  -->
<div class="card">
<h2>Course List</h2>

<table>
<tr>
<th>ID</th>
<th>Title</th>
<th>Category</th>
<th>Thumbnail</th>
<th>Price</th>
<th>Status</th>
</tr>

<?php
$courses2 = mysqli_query($conn, "SELECT * FROM courses ORDER BY id DESC");
while($row = mysqli_fetch_assoc($courses2)){
?>

<tr>
<td><?= $row['id'] ?></td>
<td><?= $row['title'] ?></td>
<td><?= $row['category'] ?></td>
<td>
    <?php if($row['thumbnail']){ ?>
        <img src="<?= $row['thumbnail'] ?>">
    <?php } ?>
</td>
<td>$<?= $row['price'] ?></td>
<td>
    <span class="status <?= $row['status'] ?>">
        <?= $row['status'] ?>
    </span>
</td>
</tr>

<?php } ?>

</table>
</div>

</div>

</body>
</html>