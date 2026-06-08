<?php
session_start();
include "db.php";

$student_id = 1;

/* =========================
   CSRF TOKEN
========================= */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   UPLOAD DIR
========================= */
$upload_dir = "uploads/submissions/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

/* =========================
   SUBMIT ASSIGNMENT
========================= */
if (isset($_POST['submit_assignment'])) {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    $assignment_id = intval($_POST['assignment_id']);
    $file_path = NULL;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {

        $file = $_FILES['file'];

        $allowed = ['pdf','doc','docx','zip','rar','jpg','jpeg','png'];
        $max_size = 20 * 1024 * 1024;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed) && $file['size'] <= $max_size) {

            $new_name = bin2hex(random_bytes(16)) . "." . $ext;
            $destination = $upload_dir . $new_name;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $file_path = $destination;
            }
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO submissions
        (assignment_id, student_id, file_path)
        VALUES (?, ?, ?)
    ");

    $stmt->bind_param("iis", $assignment_id, $student_id, $file_path);
    $stmt->execute();
}

/* =========================
   FETCH COURSES
========================= */
$courses = $conn->query("
    SELECT id, title
    FROM courses
    ORDER BY title ASC
");
/* =========================
   SELECTED COURSE
========================= */
$selected_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

/* =========================
   FETCH ASSIGNMENTS BY COURSE
========================= */
if ($selected_course > 0) {

    $stmt = $conn->prepare("
        SELECT id, title
        FROM assignments
        WHERE course_id = ?
        ORDER BY id DESC
    ");

    $stmt->bind_param("i", $selected_course);
    $stmt->execute();
    $assignments = $stmt->get_result();

} else {

    $assignments = $conn->query("
        SELECT id, title
        FROM assignments
        ORDER BY id DESC
    ");
}

/* =========================
   MY SUBMISSIONS (SAFE JOIN)
========================= */
$stmt = $conn->prepare("
    SELECT s.id, s.status, s.file_path, s.submitted_at, a.title
    FROM submissions s
    JOIN assignments a ON a.id = s.assignment_id
    WHERE s.student_id = ?
    ORDER BY s.id DESC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();
$submissions = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Student Submissions</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f9;
    padding:30px;
}

.container{
    max-width:1100px;
    margin:auto;
}

.card{
    background:white;
    padding:20px;
    margin-bottom:20px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
}

select,input{
    width:100%;
    padding:10px;
    margin-bottom:10px;
}

button{
    background:#28a745;
    color:white;
    padding:10px;
    border:none;
    cursor:pointer;
    border-radius:5px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#28a745;
    color:white;
    padding:10px;
}

td{
    padding:10px;
    border-bottom:1px solid #eee;
}

a.download{
    background:#007bff;
    color:white;
    padding:5px 10px;
    text-decoration:none;
    border-radius:5px;
}
</style>
</head>

<body>

<div class="container">

<!-- =========================
     COURSE FILTER
========================= -->
<div class="card">
<h2>Select Course</h2>

<form method="GET">

<select name="course_id" onchange="this.form.submit()">

    <option value="0">-- All Courses --</option>

    <?php while($c = $courses->fetch_assoc()){ ?>

        <option value="<?= (int)$c['id']; ?>"
            <?= (isset($selected_course) && $selected_course == $c['id']) ? 'selected' : '' ?>>

            <?= htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?>

        </option>

    <?php } ?>

</select>

</form>

<!-- =========================
     SUBMIT ASSIGNMENT
========================= -->
<div class="card">

<h2>Submit Assignment</h2>

<form method="POST" enctype="multipart/form-data">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

<label>Select Assignment</label>

<select name="assignment_id" required>

    <option value="">-- Choose Assignment --</option>

    <?php while($a = $assignments->fetch_assoc()){ ?>

        <option value="<?= (int)$a['id']; ?>">
            <?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8'); ?>
        </option>

    <?php } ?>

</select>

<label>Upload File</label>
<input type="file" name="file"
       accept=".pdf,.doc,.docx,.zip,.rar,.jpg,.jpeg,.png"
       required>

<button type="submit" name="submit_assignment">
    Submit
</button>

</form>

</div>

<!-- =========================
     SUBMISSIONS
========================= -->
<div class="card">

<h2>My Submissions</h2>

<table>

<tr>
    <th>ID</th>
    <th>Assignment</th>
    <th>Status</th>
    <th>File</th>
    <th>Date</th>
</tr>

<?php while($s = $submissions->fetch_assoc()){ ?>

<tr>

<td><?= (int)$s['id']; ?></td>

<td><?= htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8'); ?></td>

<td><?= htmlspecialchars($s['status'], ENT_QUOTES, 'UTF-8'); ?></td>

<td>
<?php if(!empty($s['file_path'])){ ?>
    <a class="download"
       href="<?= htmlspecialchars($s['file_path'], ENT_QUOTES, 'UTF-8'); ?>"
       target="_blank">
       Download
    </a>
<?php } ?>
</td>

<td><?= htmlspecialchars($s['submitted_at'], ENT_QUOTES, 'UTF-8'); ?></td>

</tr>

<?php } ?>

</table>

</div>

</div>

</body>
</html>