<?php
session_start();
include "db.php";

/* DEMO TEACHER */
$teacher_id = 1;

/* GET SUBMISSIONS */
$sql = "
SELECT 
    s.id,
    s.file_path,
    s.status,
    s.marks,
    s.feedback,
    s.submitted_at,
    u.full_name,
    a.title AS assignment_title
FROM submissions s
JOIN assignments a ON a.id = s.assignment_id
JOIN users u ON u.id = s.student_id
ORDER BY s.id DESC
";

$result = $conn->query($sql);

/* GRADE SUBMISSION */
if(isset($_POST['grade'])){

    $id = intval($_POST['submission_id']);
    $marks = intval($_POST['marks']);
    $feedback = htmlspecialchars(trim($_POST['feedback']));

    $stmt = $conn->prepare("
        UPDATE submissions
        SET marks = ?, feedback = ?, status = 'graded', graded_at = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param("isi", $marks, $feedback, $id);
    $stmt->execute();

    header("Location: grade_submissions.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Grade Submissions</title>

<style>
body{font-family:Arial;background:#f4f6f9;padding:30px;}
.container{max-width:1100px;margin:auto;}
.card{background:white;padding:20px;margin-bottom:20px;border-radius:10px;}
table{width:100%;border-collapse:collapse;}
th{background:#007bff;color:white;padding:10px;}
td{padding:10px;border-bottom:1px solid #eee;}
input,textarea{width:100%;padding:8px;margin-top:5px;}
button{background:#28a745;color:white;padding:8px;border:none;cursor:pointer;}
</style>
</head>

<body>

<div class="container">

<h2>Student Submissions</h2>

<div class="card">

<table>

<tr>
    <th>Student</th>
    <th>Assignment</th>
    <th>File</th>
    <th>Status</th>
    <th>Marks</th>
    <th>Feedback</th>
    <th>Action</th>
</tr>

<?php while($row = $result->fetch_assoc()){ ?>

<tr>

<td><?= htmlspecialchars($row['full_name']); ?></td>
<td><?= htmlspecialchars($row['assignment_title']); ?></td>

<td>
<?php if($row['file_path']){ ?>
    <a href="<?= $row['file_path']; ?>" target="_blank">Download</a>
<?php } ?>
</td>

<td><?= $row['status']; ?></td>
<td><?= $row['marks'] ?? '-'; ?></td>
<td><?= htmlspecialchars($row['feedback'] ?? ''); ?></td>

<td>

<form method="POST">

<input type="hidden" name="submission_id" value="<?= $row['id']; ?>">

<input type="number" name="marks" placeholder="Marks (0-100)" required>

<textarea name="feedback" placeholder="Feedback..." required></textarea>

<button type="submit" name="grade">Submit</button>

</form>

</td>

</tr>

<?php } ?>

</table>

</div>

</div>

</body>
</html>