<?php
session_start();
include "db.php";

/* =========================
   ACCESS CHECK
========================= */
if (!isset($_SESSION['user_id'])) {
    die("Access denied. Please login.");
}

$message = "";

/* =========================
   ASSIGN / REASSIGN TEACHER
========================= */
if (isset($_POST['assign_teacher'])) {

    $course_id  = (int) $_POST['course_id'];
    $teacher_id = (int) $_POST['teacher_id'];

    /* =========================
       VALIDATE COURSE
    ========================= */
    $courseStmt = $conn->prepare("
        SELECT id, title 
        FROM courses 
        WHERE id = ?
    ");
    $courseStmt->bind_param("i", $course_id);
    $courseStmt->execute();
    $course = $courseStmt->get_result()->fetch_assoc();

    if (!$course) {
        die("Course not found.");
    }

    /* =========================
       VALIDATE TEACHER
    ========================= */
    $teacherStmt = $conn->prepare("
        SELECT id, full_name 
        FROM users 
        WHERE id = ? AND role = 'teacher'
    ");
    $teacherStmt->bind_param("i", $teacher_id);
    $teacherStmt->execute();
    $teacher = $teacherStmt->get_result()->fetch_assoc();

    if (!$teacher) {
        die("Invalid teacher selected.");
    }

    /* =========================
       CHECK IF COURSE ALREADY ASSIGNED
    ========================= */
    $check = $conn->prepare("
        SELECT id 
        FROM course_teachers 
        WHERE course_id = ?
    ");
    $check->bind_param("i", $course_id);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();

    if ($existing) {

        /* =========================
           REASSIGN (UPDATE)
        ========================= */
        $update = $conn->prepare("
            UPDATE course_teachers
            SET teacher_id = ?, assigned_at = NOW()
            WHERE course_id = ?
        ");
        $update->bind_param("ii", $teacher_id, $course_id);
        $update->execute();

        $message = "Teacher reassigned successfully to {$course['title']}";

    } else {

        /* =========================
           FIRST TIME ASSIGN
        ========================= */
        $insert = $conn->prepare("
            INSERT INTO course_teachers (course_id, teacher_id)
            VALUES (?, ?)
        ");
        $insert->bind_param("ii", $course_id, $teacher_id);
        $insert->execute();

        $message = "Teacher assigned successfully to {$course['title']}";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Assign Teacher</title>

<style>
body{
    font-family:Arial;
    background:#f5f7fb;
    padding:40px;
}

.card{
    max-width:600px;
    margin:auto;
    background:#fff;
    padding:25px;
    border-radius:12px;
    box-shadow:0 4px 15px rgba(0,0,0,.1);
}

h2{
    text-align:center;
}

select, button{
    width:100%;
    padding:12px;
    margin:10px 0;
    border-radius:8px;
    border:1px solid #ccc;
}

button{
    background:#2563eb;
    color:#fff;
    border:none;
    cursor:pointer;
    font-weight:bold;
}

button:hover{
    background:#1d4ed8;
}

.message{
    text-align:center;
    color:green;
    margin-bottom:15px;
}
</style>

</head>

<body>

<div class="card">

<h2>Assign / Reassign Teacher</h2>

<?php if (!empty($message)) { ?>
    <div class="message">
        <?= htmlspecialchars($message) ?>
    </div>
<?php } ?>

<form method="POST">

    <!-- COURSES -->
    <label>Select Course</label>
    <select name="course_id" required>
        <option value="">-- Select Course --</option>
        <?php
        $courses = mysqli_query($conn, "SELECT id, title FROM courses ORDER BY title ASC");
        while ($c = mysqli_fetch_assoc($courses)) {
            echo "<option value='{$c['id']}'>{$c['title']}</option>";
        }
        ?>
    </select>

    <!-- TEACHERS -->
    <label>Select Teacher</label>
    <select name="teacher_id" required>
        <option value="">-- Select Teacher --</option>
        <?php
        $teachers = mysqli_query($conn, "
            SELECT id, full_name 
            FROM users 
            WHERE role = 'teacher'
            ORDER BY full_name ASC
        ");

        while ($t = mysqli_fetch_assoc($teachers)) {
            echo "<option value='{$t['id']}'>{$t['full_name']}</option>";
        }
        ?>
    </select>

    <button type="submit" name="assign_teacher">
        Assign / Reassign Teacher
    </button>

</form>

</div>

</body>
</html>