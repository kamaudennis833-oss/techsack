<?php
session_start();
include "db.php";

/* FETCH TEACHERS */
$teachers = mysqli_query($conn,"
    SELECT t.id, u.full_name
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.full_name ASC
");

/* FETCH COURSES */
$courses = mysqli_query($conn,"
    SELECT c.*, u.full_name
    FROM courses c
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY c.id DESC
");

/* CREATE COURSE */
if(isset($_POST['create_course'])){

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $custom_category = trim($_POST['custom_category']);
    $type = trim($_POST['type']);
    $price = floatval($_POST['price']);
    $status = trim($_POST['status']);
    $teacher_id = intval($_POST['teacher_id']);

    /* IMAGE UPLOAD */
    $thumbnail = "";

    if(!empty($_FILES['thumbnail']['name'])){

        $target_dir = "uploads/";

        if(!is_dir($target_dir)){
            mkdir($target_dir, 0777, true);
        }

        $file_name = time() . "_" . basename($_FILES['thumbnail']['name']);
        $thumbnail = $target_dir . $file_name;

        move_uploaded_file(
            $_FILES['thumbnail']['tmp_name'],
            $thumbnail
        );
    }

    $stmt = $conn->prepare("
        INSERT INTO courses
        (
            title,
            description,
            category,
            custom_category,
            thumbnail,
            type,
            price,
            status,
            teacher_id
        )
        VALUES
        (?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "ssssssdsi",
        $title,
        $description,
        $category,
        $custom_category,
        $thumbnail,
        $type,
        $price,
        $status,
        $teacher_id
    );

    $stmt->execute();

    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

/* DELETE OR ARCHIVE */
if(isset($_POST['delete_course'])){

    $course_id = intval($_POST['course_id']);
    $action = $_POST['action'];

    if($action == "delete"){

        $stmt = $conn->prepare(
            "DELETE FROM courses WHERE id=?"
        );

        $stmt->bind_param("i",$course_id);
        $stmt->execute();

    }else{

        $stmt = $conn->prepare(
            "UPDATE courses
             SET status='archived'
             WHERE id=?"
        );

        $stmt->bind_param("i",$course_id);
        $stmt->execute();
    }

    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Course Management</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f9;
    margin:0;
    padding:20px;
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
    box-shadow:0 2px 8px rgba(0,0,0,.1);
}

input,
textarea,
select{
    width:100%;
    padding:10px;
    margin-bottom:10px;
    border:1px solid #ccc;
    border-radius:5px;
    box-sizing:border-box;
}

button{
    padding:10px 15px;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

.btn-primary{
    background:#007bff;
    color:#fff;
}

.btn-danger{
    background:#dc3545;
    color:#fff;
}

table{
    width:100%;
    border-collapse:collapse;
}

table th,
table td{
    border:1px solid #ddd;
    padding:10px;
    text-align:left;
}

img{
    width:80px;
    border-radius:5px;
}

.status{
    padding:5px 10px;
    border-radius:5px;
    color:#fff;
}

.draft{
    background:gray;
}

.published{
    background:green;
}

.archived{
    background:orange;
}
</style>

</head>
<body>

<div class="container">

<div class="card">
<h2>Create Course</h2>

<form method="POST" enctype="multipart/form-data">

    <input
        type="text"
        name="title"
        placeholder="Course Title"
        required
    >

    <textarea
        name="description"
        placeholder="Course Description"
        required
    ></textarea>

    <label>Category</label>

    <select name="category" required>
        <option value="">Select Category</option>
        <option value="web_dev">Web Development</option>
        <option value="mobile_dev">Mobile Development</option>
        <option value="data_science">Data Science</option>
        <option value="ui_ux">UI/UX Design</option>
        <option value="cyber_security">Cyber Security</option>
        <option value="ai_ml">AI / ML</option>
    </select>

    <input
        type="text"
        name="custom_category"
        placeholder="Custom Category"
    >

    <label>Assign Teacher</label>

    <select name="teacher_id" required>
        <option value="">Select Teacher</option>

        <?php
        mysqli_data_seek($teachers,0);

        while($teacher = mysqli_fetch_assoc($teachers)){
        ?>
            <option value="<?= $teacher['id']; ?>">
                <?= htmlspecialchars($teacher['full_name']); ?>
            </option>
        <?php } ?>
    </select>

    <label>Thumbnail</label>

    <input
        type="file"
        name="thumbnail"
        required
    >

    <select name="type">
        <option value="free">Free</option>
        <option value="paid">Paid</option>
    </select>

    <input
        type="number"
        step="0.01"
        name="price"
        placeholder="Price"
        value="0"
    >

    <select name="status">
        <option value="draft">Draft</option>
        <option value="published">Published</option>
    </select>

    <button
        class="btn-primary"
        name="create_course"
    >
        Create Course
    </button>

</form>
</div>

<div class="card">

<h2>Delete / Archive Course</h2>

<form method="POST">

    <select name="course_id" required>

        <option value="">Select Course</option>

        <?php
        $course_list = mysqli_query(
            $conn,
            "SELECT id,title FROM courses ORDER BY id DESC"
        );

        while($c = mysqli_fetch_assoc($course_list)){
        ?>
            <option value="<?= $c['id']; ?>">
                <?= htmlspecialchars($c['title']); ?>
            </option>
        <?php } ?>

    </select>

    <select name="action">
        <option value="archive">Archive</option>
        <option value="delete">Delete</option>
    </select>

    <button
        class="btn-danger"
        name="delete_course"
    >
        Apply
    </button>

</form>

</div>

<div class="card">

<h2>Course List</h2>

<table>

<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Teacher</th>
    <th>Category</th>
    <th>Thumbnail</th>
    <th>Price</th>
    <th>Status</th>
</tr>

<?php

$courses2 = mysqli_query($conn,"
    SELECT
        c.*,
        u.full_name
    FROM courses c
    LEFT JOIN teachers t
        ON c.teacher_id = t.id
    LEFT JOIN users u
        ON t.user_id = u.id
    ORDER BY c.id DESC
");

while($row = mysqli_fetch_assoc($courses2)){
?>

<tr>

    <td><?= $row['id']; ?></td>

    <td>
        <?= htmlspecialchars($row['title']); ?>
    </td>

    <td>
        <?= $row['full_name'] ?: 'Not Assigned'; ?>
    </td>

    <td>
        <?= htmlspecialchars($row['category']); ?>
    </td>

    <td>
        <?php if(!empty($row['thumbnail'])){ ?>
            <img src="<?= $row['thumbnail']; ?>">
        <?php } ?>
    </td>

    <td>
        $<?= number_format($row['price'],2); ?>
    </td>

    <td>
        <span class="status <?= $row['status']; ?>">
            <?= ucfirst($row['status']); ?>
        </span>
    </td>

</tr>

<?php } ?>

</table>

</div>

</div>

</body>
</html>