<?php
session_start();
include "db.php";

/* =========================
   DEMO TEACHER ID (replace with session)
========================= */
$teacher_id = 1;

/* =========================
   CSRF TOKEN
========================= */
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   UPLOAD FOLDER
========================= */
$upload_dir = "uploads/assignments/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

/* =========================
   ADD ASSIGNMENT (SECURE)
========================= */
if (isset($_POST['add_assignment'])) {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    $title = htmlspecialchars(trim($_POST['title']));
    $description = htmlspecialchars(trim($_POST['description']));
    $due_date = $_POST['due_date'];

    $attachment = NULL;

    /* FILE UPLOAD SECURITY */
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === 0) {

        $file = $_FILES['attachment'];

        $allowed_ext = ['pdf','doc','docx','ppt','pptx','xls','xlsx','zip','rar','jpg','jpeg','png'];
        $max_size = 20 * 1024 * 1024; // 20MB

        $file_name = $file['name'];
        $tmp_name = $file['tmp_name'];
        $file_size = $file['size'];

        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed_ext) && $file_size <= $max_size) {

            $new_name = bin2hex(random_bytes(16)) . "." . $ext;
            $destination = $upload_dir . $new_name;

            if (move_uploaded_file($tmp_name, $destination)) {
                $attachment = $destination;
            }
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO assignments
        (title, teacher_id, description, due_date, attachment)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("sisss",
        $title,
        $teacher_id,
        $description,
        $due_date,
        $attachment
    );

    $stmt->execute();
}

/* =========================
   DELETE ASSIGNMENT (SECURE)
========================= */
if (isset($_GET['delete'])) {

    $id = intval($_GET['delete']);

    /* get file first */
    $stmt = $conn->prepare("
        SELECT attachment
        FROM assignments
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && !empty($row['attachment'])) {
        if (file_exists($row['attachment'])) {
            unlink($row['attachment']);
        }
    }

    /* delete record */
    $stmt = $conn->prepare("
        DELETE FROM assignments
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: assignments.php");
    exit;
}

/* =========================
   FETCH ASSIGNMENTS
========================= */
$result = $conn->query("
    SELECT a.*, t.employee_no
    FROM assignments a
    LEFT JOIN teachers t ON t.id = a.teacher_id
    ORDER BY a.id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Assignments</title>

<style>
body{
    font-family: Arial;
    background:#f4f6f9;
    padding:30px;
}

.container{max-width:1100px;margin:auto;}

.card{
    background:white;
    padding:20px;
    margin-bottom:20px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
}

input,textarea{
    width:100%;
    padding:10px;
    margin-top:5px;
    margin-bottom:15px;
    border:1px solid #ddd;
    border-radius:5px;
}

button{
    background:#007bff;
    color:white;
    border:none;
    padding:10px 15px;
    cursor:pointer;
    border-radius:5px;
}

button:hover{background:#0056b3;}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#007bff;
    color:white;
    padding:10px;
}

td{
    padding:10px;
    border-bottom:1px solid #eee;
}

a.delete{
    background:red;
    color:white;
    padding:5px 10px;
    text-decoration:none;
    border-radius:5px;
}
</style>
</head>

<body>

<div class="container">

<div class="card">
<h2>Create Assignment</h2>

<form method="POST" enctype="multipart/form-data">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

<label>Title</label>
<input type="text" name="title" required>

<label>Description</label>
<textarea name="description" required></textarea>

<label>Due Date</label>
<input type="date" name="due_date" required>

<label>Attachment (Optional)</label>
<input type="file" name="attachment"
       accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.rar,.jpg,.jpeg,.png">

<button type="submit" name="add_assignment">Publish</button>

</form>
</div>

<div class="card">
<h2>All Assignments</h2>

<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Description</th>
    <th>Due Date</th>
    <th>File</th>
    <th>Action</th>
</tr>

<?php while($row = $result->fetch_assoc()){ ?>

<tr>
<td><?= $row['id']; ?></td>

<td><?= htmlspecialchars($row['title']); ?></td>

<td><?= nl2br(htmlspecialchars($row['description'])); ?></td>

<td><?= $row['due_date']; ?></td>

<td>
<?php if($row['attachment']){ ?>
    <a href="<?= htmlspecialchars($row['attachment']); ?>" target="_blank">
        Download
    </a>
<?php } else { echo "No file"; } ?>
</td>

<td>
<a class="delete"
   href="?delete=<?= $row['id']; ?>"
   onclick="return confirm('Delete assignment?')">
   Delete
</a>
</td>

</tr>

<?php } ?>

</table>
s
</div>

</div>

</body>
</html>