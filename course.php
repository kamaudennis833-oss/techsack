<?php
session_start();
include "db.php";

/* ==========================
   SECURITY: LOGIN CHECK
========================== */
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access. Please login first.");
}

/* ==========================
   CSRF TOKEN
========================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ==========================
   FETCH TEACHERS
========================== */
$teachers = mysqli_query($conn,"
    SELECT id, full_name
    FROM users
    WHERE role='teacher'
    ORDER BY full_name ASC
");

/* ==========================
   FETCH COURSES
========================== */
$courses = mysqli_query($conn,"
    SELECT c.*, u.full_name AS teacher_name
    FROM courses c
    LEFT JOIN users u ON c.teacher_id = u.id
    ORDER BY c.id DESC
");

/* ==========================
   CREATE COURSE (SECURE)
========================== */
if(isset($_POST['create_course'])){

    /* CSRF CHECK */
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $custom_category = trim($_POST['custom_category']);
    $type = trim($_POST['type']);
    $price = floatval($_POST['price']);
    $status = trim($_POST['status']);
    $teacher_id = intval($_POST['teacher_id']);

    /* fallback category */
    if (!empty($custom_category)) {
        $category = $custom_category;
    }

    /* ================= FILE UPLOAD SECURITY ================= */
    $thumbnail = "uploads/default.jpg";

    if(!empty($_FILES['thumbnail']['name'])){

        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            die("Invalid image format");
        }

        if ($_FILES['thumbnail']['size'] > 2 * 1024 * 1024) {
            die("Image too large (max 2MB)");
        }

        $target_dir = "uploads/";

        if(!is_dir($target_dir)){
            mkdir($target_dir, 0777, true);
        }

        $file_name = "course_" . uniqid() . "." . $ext;
        $thumbnail = $target_dir . $file_name;

        move_uploaded_file(
            $_FILES['thumbnail']['tmp_name'],
            $thumbnail
        );
    }

    /* ================= INSERT (SAFE) ================= */
    $stmt = $conn->prepare("
        INSERT INTO courses (
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
        VALUES (?,?,?,?,?,?,?,?,?)
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

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* ==========================
   DELETE / ARCHIVE (SECURE)
========================== */
if(isset($_POST['delete_course'])){

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    $course_id = intval($_POST['course_id']);
    $action = $_POST['action'];

    if($action === "delete"){

        $stmt = $conn->prepare("DELETE FROM courses WHERE id=?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();

    } else {

        $stmt = $conn->prepare("
            UPDATE courses
            SET status='Inactive'
            WHERE id=?
        ");

        $stmt->bind_param("i", $course_id);
        $stmt->execute();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
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
    font-family:Arial,sans-serif;
    background:#f4f6f9;
    margin:0;
    padding:20px;
}

.container{max-width:1200px;margin:auto}

.card{
    background:#fff;
    padding:20px;
    margin-bottom:20px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,.1);
}

input,textarea,select{
    width:100%;
    padding:10px;
    margin-bottom:10px;
    border:1px solid #ccc;
    border-radius:5px;
}

button{
    padding:10px 15px;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

.btn-primary{background:#007bff;color:#fff}
.btn-danger{background:#dc3545;color:#fff}

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    border:1px solid #ddd;
    padding:10px;
}

img{
    width:80px;
    border-radius:5px;
}

.status{
    padding:5px 10px;
    color:#fff;
    border-radius:5px;
    font-size:12px;
}

.active{background:green}
.inactive{background:red}
</style>

</head>
<body>

<div class="container">

<!-- CREATE -->
<div class="card">
<h2>Create Course</h2>

<form method="POST" enctype="multipart/form-data">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

<input type="text" name="title" placeholder="Course Title" required>

<textarea name="description" placeholder="Course Description" required></textarea>

<select name="category" required>
    <option value="">Select Category</option>
    <option value="web_dev">Web Development</option>
    <option value="mobile_dev">Mobile Development</option>
    <option value="data_science">Data Science</option>
    <option value="ui_ux">UI/UX Design</option>
    <option value="cyber_security">Cyber Security</option>
    <option value="ai_ml">AI / Machine Learning</option>
</select>

<input type="text" name="custom_category" placeholder="Custom Category (Optional)">

<select name="teacher_id" required>
    <option value="">Select Teacher</option>

    <?php while($teacher = mysqli_fetch_assoc($teachers)) { ?>
        <option value="<?= $teacher['id']; ?>">
            <?= htmlspecialchars($teacher['full_name']); ?>
        </option>
    <?php } ?>
</select>

<input type="file" name="thumbnail" accept="image/*">

<select name="type">
    <option value="free">Free</option>
    <option value="paid">Paid</option>
</select>

<input type="number" step="0.01" name="price" value="0">

<select name="status">
    <option value="Active">Active</option>
    <option value="Inactive">Inactive</option>
</select>

<button type="submit" name="create_course" class="btn-primary">
Create Course
</button>

</form>
</div>

<!-- DELETE -->
<div class="card">
<h2>Delete Course</h2>

<form method="POST">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

<select name="course_id" required>
    <option value="">Select Course</option>

    <?php
    $course_list = mysqli_query($conn,"SELECT id,title FROM courses ORDER BY id DESC");
    while($c = mysqli_fetch_assoc($course_list)){
    ?>
        <option value="<?= $c['id']; ?>">
            <?= htmlspecialchars($c['title']); ?>
        </option>
    <?php } ?>
</select>

<select name="action">
    <option value="delete">Delete</option>
    <option value="archive">Archive</option>
</select>

<button type="submit" name="delete_course" class="btn-danger">
Apply
</button>

</form>
</div>

<!-- LIST -->
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

<?php while($row = mysqli_fetch_assoc($courses)) { ?>

<tr>
<td><?= $row['id']; ?></td>

<td><?= htmlspecialchars($row['title']); ?></td>

<td>
<?= !empty($row['teacher_name'])
? htmlspecialchars($row['teacher_name'])
: "Not Assigned"; ?>
</td>

<td><?= htmlspecialchars($row['category']); ?></td>

<td>
<?php if(!empty($row['thumbnail'])) { ?>
    <img src="<?= htmlspecialchars($row['thumbnail']); ?>">
<?php } else { echo "No Image"; } ?>
</td>

<td>$<?= number_format($row['price'],2); ?></td>

<td>
<span class="status <?= strtolower($row['status']); ?>">
<?= htmlspecialchars($row['status']); ?>
</span>
</td>
</tr>

<?php } ?>

</table>
</div>

</div>

</body>
</html>