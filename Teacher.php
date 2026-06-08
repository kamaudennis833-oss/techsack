<?php
session_start();
include "db.php";

/* =========================
   CHECK LOGIN
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* =========================
   GET TEACHER INFO (ONCE)
========================= */
$stmt = $conn->prepare("
    SELECT t.id, t.*, u.full_name, u.email
    FROM teachers t
    LEFT JOIN users u ON u.id = t.user_id
    WHERE t.user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$teacherInfo = $result->fetch_assoc();

if (!$teacherInfo) {
    die("Access denied: Not a teacher account");
}

$teacher_id = $teacherInfo['id'];

/* =========================
   COURSES (THIS TEACHER ONLY)
========================= */
$stmtCourses = $conn->prepare("
    SELECT 
        c.id,
        c.title,
        c.description,
        c.price,
        c.created_at,
        COUNT(DISTINCT e.user_id) AS students,
        COUNT(DISTINCT cc.id) AS contents
    FROM courses c
    JOIN course_teachers ct ON ct.course_id = c.id
    LEFT JOIN enrollments e ON e.course_id = c.id
    LEFT JOIN course_contents cc ON cc.course_id = c.id
    WHERE ct.teacher_id = ?
    GROUP BY c.id, c.title, c.description, c.price, c.created_at
    ORDER BY c.created_at DESC
");

$stmtCourses->bind_param("i", $teacher_id);
$stmtCourses->execute();
$courses = $stmtCourses->get_result();

/* =========================
   STATS COUNTS
========================= */

// Courses count
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM course_teachers
    WHERE teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$courses_count = $stmt->get_result()->fetch_assoc()['total'];

// Students count
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT e.user_id) AS total
    FROM enrollments e
    JOIN course_teachers ct ON ct.course_id = e.course_id
    WHERE ct.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$students_count = $stmt->get_result()->fetch_assoc()['total'];

// Notes count
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM course_contents cc
    JOIN course_teachers ct ON ct.course_id = cc.course_id
    WHERE ct.teacher_id = ? AND cc.content_type = 'PDF'
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$notes_count = $stmt->get_result()->fetch_assoc()['total'];

// Videos count
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM course_contents cc
    JOIN course_teachers ct ON ct.course_id = cc.course_id
    WHERE ct.teacher_id = ? AND cc.content_type = 'Video'
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$videos_count = $stmt->get_result()->fetch_assoc()['total'];

// Quiz count
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM quizzes q
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$quiz_count = $stmt->get_result()->fetch_assoc()['total'];

/* =========================
   ACTIVITIES
========================= */
$activities = $conn->query("
    SELECT message, created_at
    FROM activities
    ORDER BY created_at DESC
    LIMIT 5
");

/* =========================
   UPDATE COURSE
========================= */
if (isset($_POST['update_course'])) {

    $course_id = intval($_POST['id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);

    $stmt = $conn->prepare("
        UPDATE courses c
        JOIN course_teachers ct ON ct.course_id = c.id
        SET c.title = ?, c.description = ?, c.price = ?
        WHERE c.id = ? AND ct.teacher_id = ?
    ");

    $stmt->bind_param(
        "ssdii",
        $title,
        $description,
        $price,
        $course_id,
        $teacher_id
    );

    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "<script>alert('Course Updated Successfully');</script>";
    } else {
        echo "<script>alert('No changes made or unauthorized');</script>";
    }
}

/* =========================
   DELETE COURSE (SECURE POST)
========================= */
if (isset($_POST['delete_course'])) {

    $course_id = intval($_POST['course_id']);

    $stmt = $conn->prepare("
        DELETE c
        FROM courses c
        JOIN course_teachers ct ON ct.course_id = c.id
        WHERE c.id = ? AND ct.teacher_id = ?
    ");

    $stmt->bind_param("ii", $course_id, $teacher_id);
    $stmt->execute();

    header("Location: teacher_courses.php");
    exit;
}

/* =========================
   FETCH COURSES AGAIN
========================= */
$stmtCourses = $conn->prepare("
    SELECT 
        c.id,
        c.title,
        c.description,
        c.price,
        c.created_at,
        COUNT(DISTINCT e.user_id) AS students,
        COUNT(DISTINCT cc.id) AS contents
    FROM courses c
    JOIN course_teachers ct ON ct.course_id = c.id
    LEFT JOIN enrollments e ON e.course_id = c.id
    LEFT JOIN course_contents cc ON cc.course_id = c.id
    WHERE ct.teacher_id = ?
    GROUP BY c.id, c.title, c.description, c.price, c.created_at
    ORDER BY c.created_at DESC
");

$stmtCourses->bind_param("i", $teacher_id);
$stmtCourses->execute();
$courses = $stmtCourses->get_result();

/* VEDIO  */ 
$user_id = $_SESSION['user_id'];

/* =========================
   GET TEACHER
========================= */
$stmt = $conn->prepare("
    SELECT id
    FROM teachers
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

if (!$teacher) {
    die("Access denied");
}

$teacher_id = $teacher['id'];

/* =========================
   UPLOAD FOLDER
========================= */
$upload_dir = "uploads/videos/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

/* =========================
   UPLOAD VIDEO
========================= */
if (isset($_POST['upload_video'])) {

    $course_id = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $access = $_POST['access_type'];

    $file_path = "";

    if (!empty($_FILES['video']['name'])) {

        $file_name = time() . "_" . basename($_FILES['video']['name']);
        $target = $upload_dir . $file_name;

        move_uploaded_file($_FILES['video']['tmp_name'], $target);

        $file_path = $target;
    }

    $stmt = $conn->prepare("
        INSERT INTO course_videos
        (course_id, title, video_path, access_type, uploaded_by)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssi",
        $course_id,
        $title,
        $file_path,
        $access,
        $user_id
    );

    $stmt->execute();

    echo "<script>alert('Video uploaded successfully');</script>";
}

/* =========================
   DELETE VIDEO (SAFE)
========================= */
if (isset($_GET['delete'])) {

    $id = intval($_GET['delete']);

    $old = $conn->query("
        SELECT video_path 
        FROM course_videos 
        WHERE id=$id
    ")->fetch_assoc();

    if ($old && file_exists($old['video_path'])) {
        unlink($old['video_path']);
    }

    $conn->query("DELETE FROM course_videos WHERE id=$id");

    header("Location: videos.php");
    exit;
}

/* =========================
   VIEW VIDEO (SECURE)
========================= */
if (isset($_GET['view'])) {

    $id = intval($_GET['view']);

    $video = $conn->query("
        SELECT v.*, c.title AS course_title
        FROM course_videos v
        JOIN courses c ON c.id = v.course_id
        WHERE v.id = $id
    ")->fetch_assoc();

    if (!$video) {
        die("Video not found");
    }

    /* ENROLLMENT CHECK */
    $check = $conn->query("
        SELECT * FROM enrollments
        WHERE user_id = $user_id
        AND course_id = {$video['course_id']}
    ");

    $isEnrolled = $check->num_rows > 0;

    if ($video['access_type'] == 'paid' && !$isEnrolled) {
        die("<h2>Access Denied ❌</h2><p>You must enroll to watch this video.</p>");
    }
    ?>

    <div style="background:#000;padding:20px;color:#fff;">
        <h2><?php echo htmlspecialchars($video['title']); ?></h2>

        <video width="100%" controls controlsList="nodownload" oncontextmenu="return false">
            <source src="<?php echo $video['video_path']; ?>" type="video/mp4">
        </video>
    </div>

    <script>
        document.addEventListener("contextmenu", e => e.preventDefault());
    </script>

<?php exit; } ?>

<!-- =========================
   FETCH VIDEOS
========================= -->
<?php
$videos = $conn->query("
SELECT v.*, c.title AS course_title
FROM course_videos v
JOIN courses c ON c.id = v.course_id
ORDER BY v.id DESC
");


/* NOTES */
$user_id = $_SESSION['user_id'];

/* =========================
   GET TEACHER ID
========================= */
$stmt = $conn->prepare("
    SELECT id 
    FROM teachers 
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

if (!$teacher) {
    die("Access denied");
}

$teacher_id = $teacher['id'];

/* =========================
   UPLOAD FOLDER
========================= */
$upload_dir = "uploads/notes/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

/* =========================
   ADD NOTE
========================= */
if (isset($_POST['add_note'])) {

    $course_id = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $access_type = $_POST['access_type'];

    $file_path = "";

    if (!empty($_FILES['file']['name'])) {

        $file_name = time() . "_" . basename($_FILES['file']['name']);
        $target = $upload_dir . $file_name;

        move_uploaded_file($_FILES['file']['tmp_name'], $target);

        $file_path = $target;
    }

    $stmt = $conn->prepare("
        INSERT INTO notes
        (course_id, title, content, access_type, created_by, file_path)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssis",
        $course_id,
        $title,
        $content,
        $access_type,
        $teacher_id,
        $file_path
    );

    $stmt->execute();

    echo "<script>alert('Note uploaded successfully');</script>";
}

/* =========================
   DELETE NOTE
========================= */
if (isset($_GET['delete'])) {

    $id = intval($_GET['delete']);

    $old = $conn->query("
        SELECT file_path 
        FROM notes 
        WHERE id=$id
    ")->fetch_assoc();

    if ($old && !empty($old['file_path']) && file_exists($old['file_path'])) {
        unlink($old['file_path']);
    }

    $conn->query("DELETE FROM notes WHERE id=$id");

    header("Location: teacher_notes.php");
    exit;
}

/* =========================
   VIEW NOTE (SECURE)
========================= */
if (isset($_GET['view'])) {

    $note_id = intval($_GET['view']);

    $note = $conn->query("
        SELECT n.*, c.title AS course_title
        FROM notes n
        JOIN courses c ON c.id = n.course_id
        WHERE n.id = $note_id
    ")->fetch_assoc();

    if (!$note) {
        die("Note not found");
    }

    /* ENROLLMENT CHECK */
    $check = $conn->query("
        SELECT * FROM enrollments 
        WHERE user_id = $user_id
        AND course_id = {$note['course_id']}
    ");

    $isEnrolled = $check->num_rows > 0;

    if ($note['access_type'] == 'paid' && !$isEnrolled) {
        die("<h2>Access Denied ❌</h2><p>You must enroll to access this note.</p>");
    }
?>

<div style="padding:20px;font-family:Arial;user-select:none;">

    <h2><?php echo htmlspecialchars($note['title']); ?></h2>

    <?php if (!empty($note['file_path'])) { ?>
        <iframe src="<?php echo $note['file_path']; ?>" width="100%" height="500px"></iframe>
    <?php } ?>

    <p style="white-space:pre-line;">
        <?php echo htmlspecialchars($note['content']); ?>
    </p>

</div>

<script>
document.addEventListener("contextmenu", e => e.preventDefault());
document.addEventListener("copy", e => e.preventDefault());
document.addEventListener("cut", e => e.preventDefault());
document.addEventListener("paste", e => e.preventDefault());
</script>

<?php exit; } ?>

<?php
/* =========================
   FETCH NOTES (TEACHER ONLY)
========================= */
$notes = $conn->query("
SELECT n.*, c.title AS course_title
FROM notes n
JOIN courses c ON c.id = n.course_id
JOIN course_teachers ct ON ct.course_id = c.id
WHERE ct.teacher_id = $teacher_id
ORDER BY n.id DESC
");


/* QUIZS */
$user_id = $_SESSION['user_id'];

/* =========================
   GET TEACHER ID
========================= */
$stmt = $conn->prepare("
    SELECT id 
    FROM teachers 
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

$teacher = $stmt->get_result()->fetch_assoc();

if (!$teacher) {
    die("Access denied: Teacher account required");
}

$teacher_id = $teacher['id'];

/* =========================
   CREATE QUIZ
========================= */
if (isset($_POST['create_quiz'])) {

    $course_id = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $passing_marks = intval($_POST['passing_marks']);
    $duration = intval($_POST['duration']);

    /* ensure teacher owns course */
    $check = $conn->prepare("
        SELECT ct.course_id 
        FROM course_teachers ct
        WHERE ct.course_id = ? AND ct.teacher_id = ?
    ");
    $check->bind_param("ii", $course_id, $teacher_id);
    $check->execute();

    if ($check->get_result()->num_rows == 0) {
        die("❌ Unauthorized course access");
    }

    $stmt = $conn->prepare("
        INSERT INTO quizzes
        (course_id, title, description, passing_score, duration)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "issii",
        $course_id,
        $title,
        $description,
        $passing_marks,
        $duration
    );

    $stmt->execute();
}

/* =========================
   ADD QUESTION
========================= */
if (isset($_POST['add_question'])) {

    $quiz_id = intval($_POST['quiz_id']);
    $question = trim($_POST['question']);
    $question_type = $_POST['question_type'];

    $option_a = $_POST['option_a'] ?? null;
    $option_b = $_POST['option_b'] ?? null;
    $option_c = $_POST['option_c'] ?? null;
    $option_d = $_POST['option_d'] ?? null;

    $correct_answer = trim($_POST['correct_answer']);
    $marks = intval($_POST['marks']);

    /* check quiz belongs to teacher */
    $check = $conn->prepare("
        SELECT q.id 
        FROM quizzes q
        JOIN course_teachers ct ON ct.course_id = q.course_id
        WHERE q.id = ? AND ct.teacher_id = ?
    ");
    $check->bind_param("ii", $quiz_id, $teacher_id);
    $check->execute();

    if ($check->get_result()->num_rows == 0) {
        die("❌ Unauthorized quiz access");
    }

    $stmt = $conn->prepare("
        INSERT INTO quiz_questions
        (quiz_id, question, question_type, option_a, option_b, option_c, option_d, correct_answer, marks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssssssi",
        $quiz_id,
        $question,
        $question_type,
        $option_a,
        $option_b,
        $option_c,
        $option_d,
        $correct_answer,
        $marks
    );

    $stmt->execute();
}

/* =========================
   STATS (TEACHER ONLY)
========================= */

$total_quizzes = $conn->query("
    SELECT COUNT(*) AS total
    FROM quizzes q
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = $teacher_id
")->fetch_assoc()['total'] ?? 0;

$total_questions = $conn->query("
    SELECT COUNT(*) AS total
    FROM quiz_questions qq
    JOIN quizzes q ON q.id = qq.quiz_id
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = $teacher_id
")->fetch_assoc()['total'] ?? 0;

$total_attempts = $conn->query("
    SELECT COUNT(*) AS total
    FROM quiz_attempts qa
    JOIN quizzes q ON q.id = qa.quiz_id
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = $teacher_id
")->fetch_assoc()['total'] ?? 0;

$passed = $conn->query("
    SELECT COUNT(*) AS total
    FROM quiz_attempts qa
    JOIN quizzes q ON q.id = qa.quiz_id
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = $teacher_id AND qa.result='Pass'
")->fetch_assoc()['total'] ?? 0;

$failed = $conn->query("
    SELECT COUNT(*) AS total
    FROM quiz_attempts qa
    JOIN quizzes q ON q.id = qa.quiz_id
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = $teacher_id AND qa.result='Fail'
")->fetch_assoc()['total'] ?? 0;

$average_score = $conn->query("
    SELECT AVG(qa.score) AS avg_score
    FROM quiz_attempts qa
    JOIN quizzes q ON q.id = qa.quiz_id
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = $teacher_id
")->fetch_assoc()['avg_score'] ?? 0;

/* STUDENT MANAGEMENT*/
$user_id = $_SESSION['user_id'];

$getTeacher = mysqli_query($conn,"
    SELECT id 
    FROM teachers 
    WHERE user_id = $user_id
");

$teacherData = mysqli_fetch_assoc($getTeacher);
$teacher_id = $teacherData['id'];

/* =========================
   STUDENT ACTIONS (SAFE + SYNCED)
========================= */

if(isset($_POST['student_action'])){

    $student_id = intval($_POST['student_id']);
    $action = $_POST['action'];

    /* GET STUDENT USER ID */
    $student = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT user_id 
        FROM students 
        WHERE id=$student_id
    "));

    if(!$student){
        die("Student not found");
    }

    $userId = $student['user_id'];

    /* ACTIVATE */
    if($action == "activate"){

        mysqli_query($conn,"
            UPDATE students
            SET status='active'
            WHERE id=$student_id
        ");

        mysqli_query($conn,"
            UPDATE users
            SET status='active'
            WHERE id=$userId
        ");
    }

    /* SUSPEND */
    if($action == "suspend"){

        mysqli_query($conn,"
            UPDATE students
            SET status='suspended'
            WHERE id=$student_id
        ");

        mysqli_query($conn,"
            UPDATE users
            SET status='suspended'
            WHERE id=$userId
        ");
    }

    /* RESET PASSWORD */
    if($action == "reset_password"){

        $newPass = password_hash("123456", PASSWORD_DEFAULT);

        mysqli_query($conn,"
            UPDATE users
            SET password='$newPass'
            WHERE id=$userId
        ");
    }

    /* DELETE */
    if($action == "delete"){

        mysqli_query($conn,"
            DELETE FROM enrollments
            WHERE user_id=$userId
        ");

        mysqli_query($conn,"
            DELETE FROM students
            WHERE id=$student_id
        ");

        mysqli_query($conn,"
            DELETE FROM users
            WHERE id=$userId
        ");
    }
}

/* =========================
   ENROLLMENT ACTIONS (TEACHER SAFE)
========================= */

if(isset($_POST['enroll_action'])){

    $enroll_id = intval($_POST['enroll_id']);
    $action = $_POST['action'];

    /* VERIFY ENROLLMENT BELONGS TO TEACHER */
    $check = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT e.id
        FROM enrollments e
        JOIN course_teachers ct ON ct.course_id = e.course_id
        WHERE e.id = $enroll_id
        AND ct.teacher_id = $teacher_id
    "));

    if(!$check){
        die("Unauthorized enrollment action");
    }

    if($action == "approve"){

        mysqli_query($conn,"
            UPDATE enrollments
            SET status='approved'
            WHERE id=$enroll_id
        ");
    }

    if($action == "reject"){

        mysqli_query($conn,"
            UPDATE enrollments
            SET status='rejected'
            WHERE id=$enroll_id
        ");
    }

    if($action == "delete"){

        mysqli_query($conn,"
            DELETE FROM enrollments
            WHERE id=$enroll_id
        ");
    }
}

/* =========================
   FETCH STUDENTS (ONLY TEACHER COURSES)
========================= */

$students = mysqli_query($conn,"
SELECT DISTINCT
    s.*
FROM students s
JOIN enrollments e ON e.user_id = s.user_id
JOIN course_teachers ct ON ct.course_id = e.course_id
WHERE ct.teacher_id = $teacher_id
ORDER BY s.id DESC
");

/* =========================
   FETCH ENROLLMENTS (ONLY TEACHER COURSES)
========================= */

$enrollments = mysqli_query($conn,"
SELECT
    e.*,
    u.full_name,
    c.title
FROM enrollments e
JOIN users u ON u.id = e.user_id
JOIN courses c ON c.id = e.course_id
JOIN course_teachers ct ON ct.course_id = c.id
WHERE ct.teacher_id = $teacher_id
ORDER BY e.id DESC
");

/* REPORT */
$user_id = $_SESSION['user_id'];

$getTeacher = $conn->prepare("
    SELECT id 
    FROM teachers 
    WHERE user_id = ?
");
$getTeacher->bind_param("i", $user_id);
$getTeacher->execute();
$teacher = $getTeacher->get_result()->fetch_assoc();

if(!$teacher){
    die("Access denied: Not a teacher account");
}

$teacher_id = $teacher['id'];

/* =========================
   TOTAL COURSES
========================= */

$total_courses = $conn->query("
SELECT COUNT(*) AS total
FROM course_teachers
WHERE teacher_id=$teacher_id
")->fetch_assoc()['total'];

/* =========================
   TOTAL STUDENTS
========================= */

$total_students = $conn->query("
SELECT COUNT(DISTINCT e.user_id) AS total
FROM enrollments e
JOIN course_teachers ct ON ct.course_id = e.course_id
WHERE ct.teacher_id=$teacher_id
")->fetch_assoc()['total'];

/* =========================
   TOTAL CONTENT
========================= */

$total_content = $conn->query("
SELECT COUNT(*) AS total
FROM course_contents cc
JOIN course_teachers ct ON ct.course_id = cc.course_id
WHERE ct.teacher_id=$teacher_id
")->fetch_assoc()['total'];

/* =========================
   TOTAL VIDEOS
========================= */

$total_videos = $conn->query("
SELECT COUNT(*) AS total
FROM course_videos v
JOIN course_teachers ct ON ct.course_id = v.course_id
WHERE ct.teacher_id=$teacher_id
")->fetch_assoc()['total'];

/* =========================
   TOTAL NOTES
========================= */

$total_notes = $conn->query("
SELECT COUNT(*) AS total
FROM notes n
JOIN course_teachers ct ON ct.course_id = n.course_id
WHERE ct.teacher_id=$teacher_id
")->fetch_assoc()['total'];

/* =========================
   REVENUE
========================= */

$revenue = $conn->query("
SELECT COALESCE(SUM(p.amount),0) AS total_revenue
FROM payments p
JOIN enrollments e ON e.user_id = p.user_id
JOIN course_teachers ct ON ct.course_id = e.course_id
WHERE ct.teacher_id=$teacher_id
AND p.status='success'
")->fetch_assoc()['total_revenue'];

/* =========================
   TOP COURSES
========================= */

$top_courses = $conn->query("
SELECT 
    c.title,
    COUNT(e.id) AS students
FROM courses c
JOIN course_teachers ct ON ct.course_id = c.id
LEFT JOIN enrollments e ON e.course_id = c.id
WHERE ct.teacher_id=$teacher_id
GROUP BY c.id
ORDER BY students DESC
LIMIT 5
");


 /* ANNOUNCEMENT */
 if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

$user_id = $_SESSION['user_id'];

$getTeacher = $conn->prepare("
    SELECT id 
    FROM teachers 
    WHERE user_id = ?
");
$getTeacher->bind_param("i", $user_id);
$getTeacher->execute();
$teacher = $getTeacher->get_result()->fetch_assoc();

if (!$teacher) {
    die("Access denied: Not a teacher");
}

$teacher_id = $teacher['id'];

/* =========================
   CREATE ANNOUNCEMENT
========================= */

if (isset($_POST['add'])) {

    $course_id = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);

    // Ensure teacher owns course
    $check = $conn->prepare("
        SELECT 1 
        FROM course_teachers 
        WHERE course_id = ? AND teacher_id = ?
    ");
    $check->bind_param("ii", $course_id, $teacher_id);
    $check->execute();

    if ($check->get_result()->num_rows == 0) {
        die("Unauthorized course access");
    }

    $stmt = $conn->prepare("
        INSERT INTO announcements (course_id, teacher_id, title, message)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiss", $course_id, $teacher_id, $title, $message);
    $stmt->execute();
}

/* =========================
   DELETE ANNOUNCEMENT
========================= */

if (isset($_GET['delete'])) {

    $id = intval($_GET['delete']);

    $conn->query("
        DELETE FROM announcements
        WHERE id = $id AND teacher_id = $teacher_id
    ");
}

/* =========================
   COURSES (ONLY TEACHER)
========================= */

$courses = $conn->query("
SELECT c.id, c.title
FROM courses c
JOIN course_teachers ct ON ct.course_id = c.id
WHERE ct.teacher_id = $teacher_id
");

/* =========================
   ANNOUNCEMENTS LIST
========================= */

$announcements = $conn->query("
SELECT a.*, c.title AS course
FROM announcements a
JOIN courses c ON c.id = a.course_id
WHERE a.teacher_id = $teacher_id
ORDER BY a.created_at DESC
");

 /*PROFILE */
if(isset($_POST['update_profile'])){

    $specialization = $_POST['specialization'];
    $qualification = $_POST['qualification'];
    $experience_years = $_POST['experience_years'];

    $stmt = $conn->prepare("
        UPDATE teachers
        SET specialization = ?, qualification = ?, experience_years = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssii",
        $specialization,
        $qualification,
        $experience_years,
        $teacher_id
    );

    $stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Dashboard</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial;}
body{background:#f5f6fa;display:flex;}
.sidebar{width:260px;height:100vh;background:rgb(65, 110, 181);color:white;position:fixed;}
.logo{text-align:center;padding:25px;font-size:22px;font-weight:bold;border-bottom:1px solid rgba(65, 110, 181, 0.1);}
.sidebar ul{list-style:none;}
.sidebar ul li a{display:block;color:white;text-decoration:none;padding:1.3rem ;padding-left:30px;}
.sidebar ul li a:hover{background:#334155;}
.main{margin-left:260px;width:100%;padding:25px;}
.header{background:white;padding:20px;border-radius:10px;display:flex;justify-content:space-between;}
.cards{margin-top:25px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;}
.card{background:white;padding:20px;border-radius:10px;}
.card h2{margin-top:10px;color:#2563eb;}
.section{margin-top:25px;background:white;padding:20px;border-radius:10px;}
table{width:100%;border-collapse:collapse;}
table th,table td{border:1px solid #ddd;padding:12px;}
table th{background:rgb(65, 110, 181);color:white;}
.btn{padding:8px 15px;border:none;border-radius:5px;color:white;}
.view{background:#16a34a;}
.edit{background:#f59e0b;}
.delete{background:#dc2626;}
.activity{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #eee;}
</style>

</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
<div class="logo">LMS Teacher</div>
<ul>
<li><a href="#" onclick="showDashboard()"><i class="fas fa-home"></i> Dashboard</a></li>
<li><a href="#" onclick="showMY_Course()"><i class="fas fa-book"></i> My Courses</a></li>
<li><a href="#" onclick="showNotes()"><i class="fas fa-file-pdf"></i> Notes</a></li>
<li><a href="#" onclick="showVedio()"><i class="fas fa-video"></i> Videos</a></li>
<li><a href="#" onclick="showQuiz()"><i class="fas fa-question-circle"></i> Quizzes</a></li>
<li><a href="#" onclick="showStudent()"><i class="fas fa-users"></i> Students</a></li>
<li><a href="#" onclick="showReport()"><i class="fas fa-chart-line"></i> Reports</a></li>
<li><a href="#" onclick="showAnnouncement()"><i class="fas fa-bullhorn"></i> Announcements</a></li>
<li><a href="#" onclick="showProfile()"><i class="fas fa-user"></i> Profile</a></li>
<li><a href="Login.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</div>

<!-- MAIN -->
<div class="main">

<!-- DASHBOARD SECTION -->
<div class="box"  id="dashboardSection" style="display:none;">

   <div class="header">
    <div>
        <h2>Teacher Dashboard</h2>
        <p>Welcome back, <b><?php echo htmlspecialchars($teacherInfo['full_name'] ?? 'Teacher'); ?></b></p>
    </div>

    <div>
        <strong>Email:</strong>
        <?php echo htmlspecialchars($teacherInfo['email'] ?? 'No Email'); ?>
    </div>
</div>

    <!-- CARDS -->
    <div class="cards">

        <div class="card">
            <i class="fas fa-book"></i>
            <h2><?php echo $courses_count; ?></h2>
            <p>My Courses</p>
        </div>

        <div class="card">
            <i class="fas fa-users"></i>
            <h2><?php echo $students_count; ?></h2>
            <p>Students</p>
        </div>

        <div class="card">
            <i class="fas fa-file-pdf"></i>
            <h2><?php echo $notes_count; ?></h2>
            <p>Notes Uploaded</p>
        </div>

        <div class="card">
            <i class="fas fa-video"></i>
            <h2><?php echo $videos_count; ?></h2>
            <p>Videos Uploaded</p>
        </div>

        <div class="card">
            <i class="fas fa-question-circle"></i>
            <h2><?php echo $quiz_count; ?></h2>
            <p>Quizzes</p>
        </div>

    </div>

    <!-- COURSES -->
    <div class="section">
        <h3>My Courses</h3>

        <table>
            <tr>
                <th>Course</th>
                <th>Price</th>
                <th style="width:160px;">Enrolled Students</th>
                <th>Status</th>
            </tr>

            <?php while($row = $courses->fetch_assoc()) { ?>

                <?php
                $course_id = $row['id'];

                $stmt = $conn->prepare("
                    SELECT COUNT(*) AS total
                    FROM enrollments
                    WHERE course_id = ?
                ");

                $stmt->bind_param("i", $course_id);
                $stmt->execute();

                $enrolled = $stmt
                    ->get_result()
                    ->fetch_assoc()['total'];
                ?>

                <tr>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td>KES <?php echo number_format($row['price']); ?></td>
                    <td><b><?php echo $enrolled; ?></b></td>
                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                </tr>

            <?php } ?>

        </table>
    </div>

    <!-- ACTIVITIES -->
    <div class="section">
        <h3>Recent Activities</h3>

        <?php while($a = $activities->fetch_assoc()) { ?>
            <div class="activity">
                <span>
                    <?php echo htmlspecialchars($a['message']); ?>
                </span>

                <span>
                    <?php echo $a['created_at']; ?>
                </span>
            </div>
        <?php } ?>

    </div> 

</div>

<!-- My_COURSE ---> 
 <div class="box" id="My_CourseSection" style="display:none;">
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
</div>

<!-- VEDI----> 
<div class="box" id="vedioSection" style="display:none;">
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
<div class="container">

<h2>🎬 Teacher Video Management</h2>

<!-- UPLOAD FORM -->
<form method="POST" enctype="multipart/form-data">

<h3>Upload Video</h3>

<select name="course_id" required>
<option value="">Select Course</option>
<?php
$courses = $conn->query("SELECT id, title FROM courses");
while ($c = $courses->fetch_assoc()) {
    echo "<option value='{$c['id']}'>{$c['title']}</option>";
}
?>
</select>

<input name="title" placeholder="Video Title" required>

<input type="file" name="video" required>

<select name="access_type">
<option value="free">Free</option>
<option value="paid">Paid</option>
</select>

<button name="upload_video">Upload</button>

</form>

<hr>

<!-- LIST VIDEOS -->
<table>
<tr>
<th>Course</th>
<th>Title</th>
<th>Access</th>
<th>Action</th>
</tr>

<?php while ($v = $videos->fetch_assoc()) { ?>

<tr>
<td><?php echo htmlspecialchars($v['course_title']); ?></td>
<td><?php echo htmlspecialchars($v['title']); ?></td>
<td><?php echo htmlspecialchars($v['access_type']); ?></td>

<td>
<a href="?view=<?php echo $v['id']; ?>" target="_blank">Watch</a>
| <a href="?delete=<?php echo $v['id']; ?>" onclick="return confirm('Delete video?')">Delete</a>
</td>
</tr>
<?php } ?>
</table>
</div>
</div>

<!-- NOTES --> 
 <div id="notesSection" style="display:none;">
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
<div class="container">

<h2>📘 Teacher Notes Management</h2>

<!-- ADD NOTE -->
<form method="POST" enctype="multipart/form-data">

<h3>Create Note</h3>

<select name="course_id" required>
<option value="">Select Course</option>
<?php
$courses = $conn->query("
    SELECT c.id, c.title
    FROM courses c
    JOIN course_teachers ct ON ct.course_id = c.id
    WHERE ct.teacher_id = $teacher_id
");

while ($c = $courses->fetch_assoc()) {
    echo "<option value='{$c['id']}'>{$c['title']}</option>";
}
?>
</select>

<input name="title" placeholder="Note Title" required>

<textarea name="content" placeholder="Write notes..." rows="4"></textarea>

<input type="file" name="file">

<select name="access_type">
<option value="free">Free</option>
<option value="paid">Paid</option>
</select>

<button name="add_note">Upload Note</button>

</form>

<hr>

<!-- LIST NOTES -->
<table>

<tr>
<th>Course</th>
<th>Title</th>
<th>Access</th>
<th>File</th>
<th>Action</th>
</tr>

<?php while ($n = $notes->fetch_assoc()) { ?>

<tr>
<td><?php echo htmlspecialchars($n['course_title']); ?></td>
<td><?php echo htmlspecialchars($n['title']); ?></td>
<td><?php echo htmlspecialchars($n['access_type']); ?></td>
<td>
<?php echo !empty($n['file_path']) ? "Uploaded" : "Text Only"; ?>
</td>

<td>
<a href="?view=<?php echo $n['id']; ?>" target="_blank">View</a> |
<a href="?delete=<?php echo $n['id']; ?>" onclick="return confirm('Delete note?')">Delete</a>
</td>
</tr>
<?php } ?>
</table>
</div>
</div>
 

<!-- QUIZS --> 
<div id="quizSection" style="display:none;">
<div class="container">

<h1>
<i class="fas fa-file-alt"></i>
Quiz Management
</h1>

<!-- STATS -->

<div class="cards">

<div class="card">
<h2><?= $total_quizzes ?></h2>
<p>Total Quizzes</p>
</div>

<div class="card">
<h2><?= $total_questions ?></h2>
<p>Total Questions</p>
</div>

<div class="card">
<h2><?= $total_attempts ?></h2>
<p>Attempts</p>
</div>

<div class="card">
<h2><?= $passed ?></h2>
<p>Passed</p>
</div>

<div class="card">
<h2><?= $failed ?></h2>
<p>Failed</p>
</div>

<div class="card">
<h2><?= round($average_score,2) ?></h2>
<p>Average Score</p>
</div>

</div>
<!-- CREATE QUIZ -->

<div class="section">

<h3>Create Quiz</h3>

<form method="POST">

<select name="course_id" required>

<option value="">Select Course</option>

<?php
$courses = mysqli_query($conn,"SELECT * FROM courses");

while($course=mysqli_fetch_assoc($courses))
{
?>
<option value="<?= $course['id'] ?>">
<?= htmlspecialchars($course['title']) ?>
</option>
<?php } ?>

</select>

<input type="text"
name="title"
placeholder="Quiz Title"
required>

<textarea
name="description"
placeholder="Quiz Description"></textarea>

<input
type="number"
name="passing_marks"
placeholder="Passing Marks (%)"
required>

<input
type="number"
name="duration"
placeholder="Duration (Minutes)"
required>

<button type="submit" name="create_quiz">
Create Quiz
</button>

</form>

</div>

<!-- ADD QUESTION -->

<div class="section">

<h3>Add Question</h3>

<form method="POST">

<select name="quiz_id" required>

<option value="">Select Quiz</option>

<?php
$quiz_list = mysqli_query($conn,"
SELECT *
FROM quizzes
ORDER BY id DESC
");

while($quiz=mysqli_fetch_assoc($quiz_list))
{
?>
<option value="<?= $quiz['id'] ?>">
<?= htmlspecialchars($quiz['title']) ?>
</option>
<?php } ?>

</select>

<textarea
name="question"
placeholder="Enter Question"
required></textarea>

<select name="question_type">

<option value="mcq">
Multiple Choice Question
</option>

<option value="short_answer">
Short Answer
</option>

</select>

<input type="text"
name="option_a"
placeholder="Option A">

<input type="text"
name="option_b"
placeholder="Option B">

<input type="text"
name="option_c"
placeholder="Option C">

<input type="text"
name="option_d"
placeholder="Option D">

<input type="text"
name="correct_answer"
placeholder="Correct Answer"
required>

<input
type="number"
name="marks"
value="1">

<button type="submit" name="add_question">
Add Question
</button>

</form>

</div>

<!-- QUIZZES -->

<div class="section">

<h3>Quiz List</h3>

<table>

<tr>
<th>ID</th>
<th>Course</th>
<th>Quiz</th>
<th>Passing</th>
<th>Duration</th>
<th>Status</th>
</tr>

<?php

$quizzes = mysqli_query($conn,"
SELECT q.*, c.title AS course_name
FROM quizzes q
LEFT JOIN courses c
ON c.id=q.course_id
ORDER BY q.id DESC
");

while($row=mysqli_fetch_assoc($quizzes))
{
?>

<tr>

<td><?= $row['id'] ?></td>
<td><?= $row['course_name'] ?></td>
<td><?= $row['title'] ?></td>
<td><?= $row['passing_marks'] ?>%</td>
<td><?= $row['duration'] ?> mins</td>
<td><?= $row['status'] ?></td>

</tr>

<?php } ?>

</table>

</div>

<!-- STUDENT ATTEMPTS -->

<div class="section">

<h3>Student Quiz Attempts</h3>

<table>

<tr>
<th>Student</th>
<th>Quiz</th>
<th>Score</th>
<th>Percentage</th>
<th>Result</th>
<th>Date</th>
</tr>

<?php

$attempts = mysqli_query($conn,"
SELECT
    qa.*,
    u.full_name,
    q.title AS quiz_title,

    (
        SELECT COALESCE(SUM(qq.marks),0)
        FROM quiz_questions qq
        WHERE qq.quiz_id = q.id
    ) AS total_marks

FROM quiz_attempts qa

LEFT JOIN users u
    ON u.id = qa.user_id

LEFT JOIN quizzes q
    ON q.id = qa.quiz_id

ORDER BY qa.id DESC
");

while($attempt = mysqli_fetch_assoc($attempts))
{

    $percentage = 0;

    if($attempt['total_marks'] > 0){
        $percentage = round(
            ($attempt['score'] / $attempt['total_marks']) * 100,
            2
        );
    }
?>

<tr>

    <td>
        <?= htmlspecialchars($attempt['full_name'] ?? 'Unknown Student') ?>
    </td>

    <td>
        <?= htmlspecialchars($attempt['quiz_title'] ?? 'Unknown Quiz') ?>
    </td>

    <td>
        <?= (int)$attempt['score'] ?>
    </td>

    <td>
        <?= $percentage ?>%
    </td>

    <td class="<?= strtolower($attempt['result'] ?? 'fail') ?>">
        <?= htmlspecialchars($attempt['result'] ?? 'Fail') ?>
    </td>

    <td>
        <?= !empty($attempt['finished_at'])
            ? $attempt['finished_at']
            : $attempt['started_at']; ?>
    </td>

</tr>

<?php } ?>

</table>

</div>

</div>
</div>


<!-- STUDENT MONITORING -->
<div id="studentSection" style="display:none;">

<div class="card">

<h2>👨‍🎓 My Students</h2>

<table class="modern-table">

<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Status</th>
    <th>Registered</th>
    <th>Actions</th>
</tr>

<?php while($s = mysqli_fetch_assoc($students)){ ?>

<tr>

<td><?= $s['id'] ?></td>

<td><?= htmlspecialchars($s['full_name']) ?></td>

<td><?= htmlspecialchars($s['email']) ?></td>

<td><?= htmlspecialchars($s['phone']) ?></td>

<td>
<span class="badge <?= $s['status'] ?>">
<?= ucfirst($s['status']) ?>
</span>
</td>

<td><?= $s['created_at'] ?></td>

<td>

<form method="POST" class="action-form">

<input type="hidden" name="student_id" value="<?= $s['id'] ?>">

<select name="action" class="action-select">

<option value="activate">Activate</option>
<option value="suspend">Suspend</option>
<option value="reset_password">Reset Password</option>
<option value="delete">Delete</option>

</select>

<button type="submit" name="student_action" class="btn btn-primary">
Apply
</button>

</form>

</td>

</tr>

<?php } ?>

</table>

</div>


<!-- =========================
   ENROLLMENT MANAGEMENT
========================= -->

<div class="card">

<h2>📚 My Course Enrollments</h2>

<table class="modern-table">

<tr>
    <th>ID</th>
    <th>Student</th>
    <th>Course</th>
    <th>Status</th>
    <th>Progress</th>
    <th>Enrolled At</th>
    <th>Actions</th>
</tr>

<?php while($e = mysqli_fetch_assoc($enrollments)){ ?>

<tr>

<td><?= $e['id'] ?></td>

<td><?= htmlspecialchars($e['full_name']) ?></td>

<td><?= htmlspecialchars($e['title']) ?></td>

<td>
<span class="badge <?= $e['status'] ?>">
<?= ucfirst($e['status']) ?>
</span>
</td>

<td><?= $e['progress'] ?>%</td>

<td><?= $e['enrolled_at'] ?></td>

<td>

<form method="POST" class="action-form">

<input type="hidden" name="enroll_id" value="<?= $e['id'] ?>">

<button type="submit" name="action" value="approve" class="btn btn-success">
Approve
</button>

<button type="submit" name="action" value="reject" class="btn btn-warning">
Reject
</button>

<button type="submit" name="action" value="delete" class="btn btn-danger"
onclick="return confirm('Delete enrollment?')">
Delete
</button>

<input type="hidden" name="enroll_action" value="1">

</form>

</td>

</tr>

<?php } ?>

</table>

</div>


<!-- =========================
   STYLING
========================= -->

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
}

.card{
    background:#fff;
    padding:25px;
    margin-bottom:25px;
    border-radius:15px;
    box-shadow:0 5px 20px rgba(0,0,0,.08);
}

.card h2{
    margin-bottom:18px;
    color:#1e293b;
}

.modern-table{
    width:100%;
    border-collapse:collapse;
}

.modern-table th{
    background:#2563eb;
    color:#fff;
    padding:14px;
    text-align:left;
}

.modern-table td{
    padding:14px;
    border-bottom:1px solid #e5e7eb;
}

.modern-table tr:hover{
    background:#f8fafc;
}

/* BADGES */
.badge{
    display:inline-block;
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
    font-weight:600;
}

.active{ background:#dcfce7; color:#166534; }
.suspended{ background:#fee2e2; color:#991b1b; }
.inactive{ background:#e5e7eb; color:#374151; }

.pending{ background:#fef3c7; color:#92400e; }
.approved{ background:#dcfce7; color:#166534; }
.rejected{ background:#fee2e2; color:#991b1b; }

.ongoing{ background:#dbeafe; color:#1e40af; }
.completed{ background:#dcfce7; color:#166534; }

/* FORM */
.action-form{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.action-select{
    padding:8px;
    border:1px solid #ccc;
    border-radius:8px;
}

/* BUTTONS */
.btn{
    border:none;
    padding:8px 12px;
    border-radius:8px;
    cursor:pointer;
    color:white;
    font-size:13px;
}

.btn-primary{ background:#2563eb; }
.btn-success{ background:#16a34a; }
.btn-warning{ background:#ea580c; }
.btn-danger{ background:#dc2626; }

.btn:hover{
    opacity:0.9;
}

@media(max-width:768px){
    .modern-table{
        display:block;
        overflow-x:auto;
    }
}

</style>
</div>

<!-- REPORT --> 
<div id="reportSection" style="display:none;">
<div class="container">

<h2>📊 Teacher Reports Dashboard</h2>

<!-- =========================
   STATS
========================= -->

<div class="grid">

<div class="card">
<h2><?= $total_courses ?></h2>
<p>My Courses</p>
</div>

<div class="card">
<h2><?= $total_students ?></h2>
<p>Total Students</p>
</div>

<div class="card">
<h2><?= $total_content ?></h2>
<p>Course Content</p>
</div>

<div class="card">
<h2><?= $total_videos ?></h2>
<p>Videos Uploaded</p>
</div>

<div class="card">
<h2><?= $total_notes ?></h2>
<p>Notes Uploaded</p>
</div>

<div class="card">
<h2>KES <?= number_format($revenue) ?></h2>
<p>Total Revenue</p>
</div>

</div>

<!-- =========================
   TOP COURSES
========================= -->

<h2 style="margin-top:30px;">🏆 Top Performing Courses</h2>

<table>

<tr>
<th>Course</th>
<th>Students</th>
</tr>

<?php while($row = $top_courses->fetch_assoc()){ ?>

<tr>
<td><?= htmlspecialchars($row['title']) ?></td>
<td><?= $row['students'] ?></td>
</tr>

<?php } ?>

</table>

</div>

</div>

<!-- ANNOUNCEMENT --> 
<div id="anouncementSection" style="display:none;">
<div class="container">

<h2>📢 Teacher Announcements</h2>

<!-- =========================
   FORM
========================= -->

<div class="box">

<form method="POST">

<label>Select Course</label>
<select name="course_id" required>

<option value="">-- Select Course --</option>

<?php while ($c = $courses->fetch_assoc()) { ?>
    <option value="<?= $c['id'] ?>">
        <?= htmlspecialchars($c['title']) ?>
    </option>
<?php } ?>

</select>

<input type="text" name="title" placeholder="Announcement Title" required>

<textarea name="message" placeholder="Write message..." required></textarea>

<button name="add" style="border-radius:4px; max-width:auto; margin:10px;">Post Announcement</button>

</form>

</div>

<!-- =========================
   TABLE
========================= -->

<table>

<tr>
<th>Course</th>
<th>Title</th>
<th>Message</th>
<th>Date</th>
<th>Action</th>
</tr>

<?php while ($a = $announcements->fetch_assoc()) { ?>

<tr>
<td><?= htmlspecialchars($a['course']) ?></td>
<td><?= htmlspecialchars($a['title']) ?></td>
<td><?= htmlspecialchars($a['message']) ?></td>
<td><?= $a['created_at'] ?></td>
<td>
<a class="delete"
   href="?delete=<?= $a['id'] ?>"
   onclick="return confirm('Delete announcement?')">
   Delete
</a>
</td>
</tr>

<?php } ?>

</table>

</div>

</div>

<!-- PROFILES --> 
<div class="box" id="profileSection" style="display:none;">
<div class="container">
<style>
    .container{
    max-width:1100px;
    margin:40px auto;
    padding:20px;
}

/* PAGE TITLE */
.container h2{
    font-size:28px;
    margin-bottom:25px;
    color:#222;
}

/* CARD DESIGN */
.card{
    background:#fff;
    padding:25px;
    border-radius:12px;
    box-shadow:0 6px 20px rgba(0,0,0,0.08);
    margin-bottom:20px;
    transition:0.3s;
}

.card:hover{
    transform:translateY(-3px);
}

/* PROFILE TEXT */
.card h3{
    margin-top:0;
    font-size:22px;
    color:#1a1a1a;
}

.card p{
    margin:6px 0;
    font-size:15px;
    color:#555;
}

/* STATS GRID */
.grid{
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap:15px;
    margin-bottom:20px;
}

/* STAT BOX */
.stat{
    background:linear-gradient(135deg,#4e73df,#224abe);
    color:#fff;
    padding:20px;
    border-radius:12px;
    text-align:center;
    box-shadow:0 6px 15px rgba(0,0,0,0.1);
    transition:0.3s;
}

.stat:hover{
    transform:scale(1.05);
}

.stat h2{
    font-size:26px;
    margin:0;
}

.stat p{
    margin:5px 0 0;
    font-size:14px;
    opacity:0.9;
}

/* FORM INPUTS */
form input{
    width:100%;
    padding:12px;
    margin:10px 0;
    border:1px solid #ddd;
    border-radius:8px;
    outline:none;
    font-size:14px;
}

form input:focus{
    border-color:#4e73df;
    box-shadow:0 0 5px rgba(78,115,223,0.3);
}

/* BUTTON */
button{
    background:#4e73df;
    color:#fff;
    border:none;
    padding:12px 18px;
    border-radius:8px;
    cursor:pointer;
    font-size:15px;
    width:100%;
    transition:0.3s;
}

button:hover{
    background:#2e59d9;
}

/* RESPONSIVE */
@media (max-width: 600px){
    .container{
        padding:10px;
    }

    .stat h2{
        font-size:20px;
    }
}
</style>
<h2 style="text-align:center;">👨‍🎓 Teacher Profile</h2>

<?php
// Use the already fetched teacher info from TOP of your file
// $teacherInfo = from first query (users + teachers join)
?>

<!-- PROFILE INFO -->
<div class="card">

<h3><?= htmlspecialchars($teacherInfo['full_name']) ?></h3>
<p>Email: <?= htmlspecialchars($teacherInfo['email']) ?></p>
<p>Employee No: <?= htmlspecialchars($teacherInfo['employee_no']) ?></p>
<p>Specialization: <?= htmlspecialchars($teacherInfo['specialization']) ?></p>
<p>Qualification: <?= htmlspecialchars($teacherInfo['qualification']) ?></p>
<p>Experience: <?= $teacherInfo['experience_years'] ?> years</p>

</div>

<!-- STATS (USE REAL COUNT VARIABLES FROM YOUR PHP) -->
<div class="grid">

<div class="stat">
<h2><?= $total_courses ?></h2>
<p>Courses</p>
</div>

<div class="stat">
<h2><?= $total_students ?></h2>
<p>Students</p>
</div>

<div class="stat">
<h2><?= $total_videos ?></h2>
<p>Videos</p>
</div>

<div class="stat">
<h2><?= $total_notes ?></h2>
<p>Notes</p>
</div>

<div class="stat">
<h2><?= $total_quizzes ?></h2>
<p>Quizzes</p>
</div>

<div class="stat">
<h2><?= $revenue ?></h2>
<p>Revenue</p>
</div>

</div>

<!-- UPDATE PROFILE -->
<div class="card">

<h3>Update Profile</h3>

<form method="POST">

<input type="text" name="specialization"
value="<?= htmlspecialchars($teacherInfo['specialization']) ?>"
placeholder="Specialization">

<input type="text" name="qualification"
value="<?= htmlspecialchars($teacherInfo['qualification']) ?>"
placeholder="Qualification">

<input type="number" name="experience_years"
value="<?= $teacherInfo['experience_years'] ?>"
placeholder="Experience Years">

<button name="update_profile">Update Profile</button>

</form>

</div>

</div>

</div>




<script>

function hideAllSections(){

    let sections = [
       "My_CourseSection",
       "dashboardSection",
       "vedioSection",
       "notesSection",
       "quizSection",
       "studentSection",
       "reportSection",
       "anouncementSection",
       "profileSection"

    ];

    sections.forEach(id => {

        let el = document.getElementById(id);

        if(el){
            el.style.display = "none";
        }

    });
}
function showDashboard(){
    hideAllSections()
    document.getElementById("dashboardSection").style.display ="block";
}
function showMY_Course(){
    hideAllSections()
    document.getElementById("My_CourseSection").style.display ="block";
}
function showVedio(){
    hideAllSections()
    document.getElementById("vedioSection").style.display ="block";
}
function showNotes(){
    hideAllSections()
    document.getElementById("notesSection").style.display ="block";
}
function showQuiz(){
    hideAllSections()
    document.getElementById("quizSection").style.display ="block";
}
function showStudent(){
    hideAllSections()
    document.getElementById("studentSection").style.display ="block";
}
function showReport(){
    hideAllSections()
    document.getElementById("reportSection").style.display ="block";
}
function showAnnouncement(){
    hideAllSections()
    document.getElementById("anouncementSection").style.display ="block";
}
function showProfile(){
    hideAllSections()
    document.getElementById("profileSection").style.display ="block";
}

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


/* =========================
   DEFAULT VIEW
========================= */

window.onload = function(){

    hideAllSections();   
        document.getElementById("dashboardSection").style.display = "block";

}

</script>

</div>
</div>
</body>
</html>