<?php
session_start();
include "db.php";
include "advance.php";

/* 
   DASHBOARD STATISTICS
 */

   // Total Students
   $total_students = mysqli_fetch_assoc(
       mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'student'")
   )['total'] ?? 0;
   
   // Active Students
   $active_students = mysqli_fetch_assoc(
       mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'student' AND status = 'active'")
   )['total'] ?? 0;

$result = mysqli_query($conn,
"SELECT COUNT(*) AS total FROM users WHERE status='active'");

if ($row = mysqli_fetch_assoc($result)) {
    $active_students = $row['total'];
}

// Total Courses
$total_courses = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM courses"))['total'] ?? 0;

// Free Courses
$free_courses = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM courses 
WHERE course_type='free'"))['total'] ?? 0;

// Paid Courses
$paid_courses = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM courses 
WHERE course_type='paid'"))['total'] ?? 0;

// Total Enrollments
$total_enrollments = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM enrollments"))['total'] ?? 0;

// Revenue
$revenue = 0;

$result = mysqli_query($conn,
"SELECT SUM(amount) AS total 
 FROM payments 
 WHERE status='success'");

if ($row = mysqli_fetch_assoc($result)) {
    $revenue = $row['total'] ?? 0;
}

// Completion Rate
$completion_rate = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT AVG(progress) AS avg_progress 
FROM enrollments"))['avg_progress'] ?? 0;

// Pending Payments
$pending_payments = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total 
FROM payments 
WHERE status='pending'"))['total'] ?? 0;

// Successful Payments
$successful_payments = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total 
FROM payments 
WHERE status='successful'"))['total'] ?? 0;

// Failed Payments
$failed_payments = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total 
FROM payments 
WHERE status='failed'"))['total'] ?? 0;

/* 
   MOST POPULAR COURSES
 */
$popular_courses = mysqli_query($conn,
"SELECT 
    courses.id,
    courses.title,
    COUNT(enrollments.id) AS users,
    COALESCE(SUM(payments.amount),0) AS revenue,
    COALESCE(AVG(enrollments.progress),0) AS completion_rate

FROM courses

LEFT JOIN enrollments
    ON courses.id = enrollments.course_id

LEFT JOIN payments
    ON enrollments.user_id = payments.user_id
    AND payments.status = 'success'

GROUP BY courses.id, courses.title

ORDER BY users DESC
LIMIT 5"
);

/* 
   RECENT STUDENTS
 */
$recent_students = mysqli_query($conn,
"SELECT 
    users.id,
    users.full_name,
    users.email,
    COALESCE(enrollments.status,'Not Enrolled') AS status,
    courses.title AS course_name

FROM users

LEFT JOIN enrollments
    ON users.id = enrollments.user_id

LEFT JOIN courses
    ON enrollments.course_id = courses.id

ORDER BY users.id DESC
LIMIT 10"
);
/* 
   RECENT ACTIVITIES
 */

$activities = mysqli_query($conn,
"SELECT * FROM activities 
ORDER BY id DESC 
LIMIT 10");



/* 
   ALLOWED FILE TYPES
 */
$allowed_images = ['jpg','jpeg','png','gif','webp'];
$allowed_docs   = ['pdf','doc','docx'];

$upload_dir = "uploads/";



/*   HANDLE UPLOAD MATERIAL */
if(isset($_POST['upload_material'])){

    $title = trim($_POST['title']);
    $course_id = intval($_POST['course_id']);

    if($title == "" || $course_id == 0){
        die("Invalid input");
    }

    /*  FILE VALIDATION */
    if(!empty($_FILES['file']['name'])){

        $fileName = $_FILES['file']['name'];
        $fileTmp  = $_FILES['file']['tmp_name'];
        $fileSize = $_FILES['file']['size'];

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        /* check allowed types */
        if(!in_array($ext, array_merge($allowed_images, $allowed_docs))){
            die("Only PDF, DOC, DOCX, and Images allowed");
        }

        /* size limit 10MB */
        if($fileSize > 10 * 1024 * 1024){
            die("File too large (max 10MB)");
        }

        $newFile = time().'_'.rand(1000,9999).'.'.$ext;

        move_uploaded_file($fileTmp, $upload_dir.$newFile);

        /* determine type */
        if(in_array($ext, $allowed_docs)){
            $type = strtoupper($ext); 
        } else {
            $type = "IMAGE";
        }

        mysqli_query($conn,
        "INSERT INTO course_contents(course_id,content_title,content_type,content_file)
         VALUES('$course_id','$title','$type','$newFile')"
        );

        mysqli_query($conn,
        "INSERT INTO activities(user_id,message)
         VALUES(1,'Uploaded material: $title')"
        );
    }
}



/*  ENROLLMENT ACTIONS  */

if(isset($_POST['enroll_action'])){

    $enroll_id = $_POST['enroll_id'];
    $action = $_POST['action'];

    // APPROVE ENROLLMENT
    if($action == "approve"){
        mysqli_query($conn, "
            UPDATE enrollments 
            SET status='approved' 
            WHERE id='$enroll_id'
        ");
    }

    // REJECT ENROLLMENT
    if($action == "reject"){
        mysqli_query($conn, "
            UPDATE enrollments 
            SET status='rejected' 
            WHERE id='$enroll_id'
        ");    
    }
    // DELETE ENROLLMENT
   if($action == "delete"){
    mysqli_query($conn, "
        UPDATE enrollments
        SET status='deleted'
        WHERE id='$enroll_id'
    ");
   }


}

/* FETCH DATA  */

$students = mysqli_query($conn, "
    SELECT * FROM users 
    ORDER BY id DESC
");

$enrollments = mysqli_query($conn, "
    SELECT 
        e.*,
        u.full_name,
        c.title
    FROM enrollments e

    JOIN users u
        ON e.user_id = u.id

    JOIN courses c
        ON e.course_id = c.id

    ORDER BY e.id DESC
");


/* COURSE  */
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

/* =========================
   NOTES
========================= */

$teacher_id = 1;
$user_id = 1;

/* 
   CREATE UPLOAD FOLDER
 */
$upload_dir = "uploads/notes/";
if(!file_exists($upload_dir)){
    mkdir($upload_dir, 0777, true);
}

/* 
   ADD NOTE WITH UPLOAD
 */
if(isset($_POST['add_note'])){

    $course_id = $_POST['course_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    $access_type = $_POST['access_type'];

    $file_path = "";

    if(!empty($_FILES['file']['name'])){

        $file_name = time() . "_" . basename($_FILES['file']['name']);
        $target = $upload_dir . $file_name;

        move_uploaded_file($_FILES['file']['tmp_name'], $target);

        $file_path = $target;
    }

    $conn->query("
        INSERT INTO notes(course_id,title,content,access_type,created_by,file_path)
        VALUES('$course_id','$title','$content','$access_type','$teacher_id','$file_path')
    ");
}

/* 
   DELETE NOTE
 */
if(isset($_GET['delete'])){

    $id = $_GET['delete'];

    $old = $conn->query("SELECT file_path FROM notes WHERE id=$id")->fetch_assoc();

    if(!empty($old['file_path']) && file_exists($old['file_path'])){
        unlink($old['file_path']);
    }

    $conn->query("DELETE FROM notes WHERE id=$id");

    echo "<script>window.location='teacher_notes.php';</script>";
}

/* 
   VIEW NOTE 
 */
if(isset($_GET['view'])){

    $note_id = $_GET['view'];

    $note = $conn->query("
        SELECT n.*, c.course_type
        FROM notes n
        JOIN courses c ON c.id = n.course_id
        WHERE n.id=$note_id
    ")->fetch_assoc();

    $check = $conn->query("
        SELECT * FROM enrollments 
        WHERE user_id=$user_id 
        AND course_id={$note['course_id']}
        AND status IN ('ongoing','completed','approved')
    ");

    $isEnrolled = $check->num_rows > 0;

    if($note['access_type'] == 'paid' && !$isEnrolled){
        die("<h2>Access Denied ❌</h2><p>You must enroll to access this note.</p>");
    }
?>

<div style="padding:20px;font-family:Arial;user-select:none;">

<h2><?php echo $note['title']; ?></h2>

<?php if($note['file_path']){ ?>

    <iframe src="<?php echo $note['file_path']; ?>" width="100%" height="500px"></iframe>

<?php } ?>

<p style="white-space:pre-line;"><?php echo $note['content']; ?></p>

</div>

<script>
document.addEventListener("contextmenu", e => e.preventDefault());
document.addEventListener("copy", e => e.preventDefault());
document.addEventListener("cut", e => e.preventDefault());
document.addEventListener("paste", e => e.preventDefault());
</script>

<?php exit; }

/* 
   FETCH NOTES
 */
$notes = $conn->query("
SELECT n.*, c.title AS course_title
FROM notes n
JOIN courses c ON c.id = n.course_id
JOIN course_teachers ct ON ct.course_id = c.id
WHERE ct.teacher_id = $teacher_id
");

/* 
   CREATE QUIZ
 */

if(isset($_POST['create_quiz']))
{
    $course_id = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $passing_marks = intval($_POST['passing_marks']);
    $duration = intval($_POST['duration']);

    $stmt = $conn->prepare("
    INSERT INTO quizzes
    (course_id,title,description,passing_score,duration)
    VALUES (?,?,?,?,?)
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

/* 
   ADD QUESTION
 */

if(isset($_POST['add_question']))
{
    $quiz_id = intval($_POST['quiz_id']);
    $question = trim($_POST['question']);
    $question_type = $_POST['question_type'];

    $option_a = $_POST['option_a'] ?? null;
    $option_b = $_POST['option_b'] ?? null;
    $option_c = $_POST['option_c'] ?? null;
    $option_d = $_POST['option_d'] ?? null;

    $correct_answer = trim($_POST['correct_answer']);
    $marks = intval($_POST['marks']);

    /* 
        CHECK QUIZ EXISTS
     */
    $check = mysqli_query($conn, "
        SELECT id FROM quizzes WHERE id = '$quiz_id'
    ");

    if(mysqli_num_rows($check) == 0){
        die("❌ Error: Selected quiz does not exist.");
    }

    /* 
       INSERT QUESTION
     */
    $stmt = $conn->prepare("
        INSERT INTO quiz_questions
        (
            quiz_id,
            question,
            question_type,
            option_a,
            option_b,
            option_c,
            option_d,
            correct_answer,
            marks
        )
        VALUES (?,?,?,?,?,?,?,?,?)
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

    if($stmt->execute()){
        echo "✅ Question added successfully";
    } else {
        echo "❌ Error inserting question";
    }
}



/* 
   DASHBOARD STATS
 */

$total_quizzes = mysqli_fetch_assoc(
mysqli_query($conn,"
SELECT COUNT(*) total
FROM quizzes")
)['total'] ?? 0;

$total_questions = mysqli_fetch_assoc(
mysqli_query($conn,"
SELECT COUNT(*) total
FROM quiz_questions")
)['total'] ?? 0;

$total_attempts = mysqli_fetch_assoc(
mysqli_query($conn,"
SELECT COUNT(*) total
FROM quiz_attempts")
)['total'] ?? 0;

$passed = mysqli_fetch_assoc(
mysqli_query($conn,"
SELECT COUNT(*) total
FROM quiz_attempts
WHERE result='Pass'")
)['total'] ?? 0;

$failed = mysqli_fetch_assoc(
mysqli_query($conn,"
SELECT COUNT(*) total
FROM quiz_attempts
WHERE result='Fail'")
)['total'] ?? 0;

$average_score = mysqli_fetch_assoc(
mysqli_query($conn,"
SELECT AVG(score) avg_score
FROM quiz_attempts")
)['avg_score'] ?? 0;

/* =========================
   SINGLE APPROVE
========================= */
if(isset($_GET['approve'])){
    $id = intval($_GET['approve']);
    $conn->query("UPDATE enrollments SET status='approved' WHERE id=$id");
}

/* =========================
   SINGLE REJECT
========================= */
if(isset($_GET['reject'])){
    $id = intval($_GET['reject']);
    $conn->query("UPDATE enrollments SET status='rejected' WHERE id=$id");
}

/* =========================
   BULK ACTIONS (FIXED)
========================= */
if($_SERVER['REQUEST_METHOD'] === 'POST'){

    /* SAVE ALL (APPROVE ALL) */
    if(isset($_POST['approve_all'])){
        $conn->query("
            UPDATE enrollments 
            SET status='approved' 
            WHERE status IN ('pending','ongoing')
        ");
    }

    /* CLEAR ALL (REJECT ALL) */
    if(isset($_POST['reject_all'])){
        $conn->query("
            UPDATE enrollments 
            SET status='rejected' 
            WHERE status IN ('pending','ongoing')
        ");
    }

    /* DELETE ALL */
    if(isset($_POST['clear_all'])){
        $conn->query("DELETE FROM enrollments");
    }
}

/* =========================
   FETCH ENROLLMENTS
========================= */
$enrollments = $conn->query("
SELECT 
    e.id,
    e.status,
    e.enrolled_at,
    u.full_name,
    u.email,
    c.title AS course
FROM enrollments e
JOIN users u ON u.id = e.user_id
JOIN courses c ON c.id = e.course_id
WHERE u.role = 'student'
ORDER BY e.id DESC
");
/* 
   UPDATE PAYMENT STATUS 
 */
if(isset($_GET['action']) && isset($_GET['id'])){

    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if(in_array($action, ['success','failed','pending'])){

        $stmt = $conn->prepare("UPDATE payments SET status=? WHERE id=?");
        $stmt->bind_param("si", $action, $id);
        $stmt->execute();

    }
}

/* 
   PAYMENT COUNTS
 */
$successful = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM payments WHERE status='success'"
))['total'];

$failed = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM payments WHERE status='failed'"
))['total'];

$pending = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM payments WHERE status='pending'"
))['total'];

/* 
   REVENUE REPORTS
 */
$daily = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT SUM(amount) AS total 
FROM payments 
WHERE status='success' 
AND DATE(created_at)=CURDATE()"
))['total'] ?? 0;

$monthly = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT SUM(amount) AS total 
FROM payments 
WHERE status='success' 
AND MONTH(created_at)=MONTH(CURDATE())
AND YEAR(created_at)=YEAR(CURDATE())"
))['total'] ?? 0;

$yearly = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT SUM(amount) AS total 
FROM payments 
WHERE status='success' 
AND YEAR(created_at)=YEAR(CURDATE())"
))['total'] ?? 0;

/* 
   PAYMENT METHODS
 */
$methods = mysqli_query($conn,
"SELECT payment_method, COUNT(*) AS total, SUM(amount) AS revenue
FROM payments
WHERE status='success'
GROUP BY payment_method"
);

/* 
   REVENUE BY COURSE
 */
   $courseRevenue = mysqli_query($conn,
   "SELECT c.title, SUM(p.amount) AS total
   FROM payments p
   JOIN courses c ON p.course_id = c.id
   WHERE p.status = 'success'
   GROUP BY p.course_id, c.title
   ORDER BY total DESC"
   );

/* 
   PAYMENT MONITORING
 */
$payments = mysqli_query($conn,
"SELECT * FROM payments ORDER BY created_at DESC LIMIT 20"
);

/* ANNOUNCEMENT */

/* 
   CREATE ANNOUNCEMENT
 */
   if (isset($_POST['add'])) {

   $course_id = intval($_POST['course_id']);
   $title = trim($_POST['title']);
   $message = trim($_POST['message']);

   if ($course_id > 0 && $title !== '' && $message !== '') {

       // OPTIONAL: ensure teacher owns course
       $check = $conn->prepare("
           SELECT 1 FROM course_teachers 
           WHERE course_id = ? AND teacher_id = ?
       ");
       $check->bind_param("ii", $course_id, $teacher_id);
       $check->execute();
       $allowed = $check->get_result()->num_rows > 0;
       $check->close();

       if ($allowed) {

           $stmt = $conn->prepare("
               INSERT INTO announcements (course_id, teacher_id, title, message)
               VALUES (?, ?, ?, ?)
           ");

           $stmt->bind_param("iiss", $course_id, $teacher_id, $title, $message);

           if (!$stmt->execute()) {
               die("Insert failed: " . $stmt->error);
           }

           $stmt->close();
       } else {
           die("You are not assigned to this course");
       }
   }
}

/* =========================
  DELETE ANNOUNCEMENT
========================= */
if (isset($_GET['delete'])) {

   $id = intval($_GET['delete']);

   $stmt = $conn->prepare("
       DELETE FROM announcements
       WHERE id = ? AND teacher_id = ?
   ");

   $stmt->bind_param("ii", $id, $teacher_id);
   $stmt->execute();
   $stmt->close();
}


/* =========================
  COURSES (ASSIGNED TO TEACHER)
========================= */
$courses = $conn->query("
   SELECT c.id, c.title
   FROM courses c
   INNER JOIN course_teachers ct ON ct.course_id = c.id
   WHERE ct.teacher_id = $teacher_id
");

/* =========================
  ANNOUNCEMENTS LIST
========================= */
$announcements = $conn->query("
   SELECT a.*, c.title AS course
   FROM announcements a
   INNER JOIN courses c ON c.id = a.course_id
   WHERE a.teacher_id = $teacher_id
   ORDER BY a.created_at DESC
");



/* ROLE */
date_default_timezone_set("Africa/Nairobi");

include "sendmail1.php";
include "db.php"; // make sure DB is included

$message = "";
$messageType = "";

/* =========================
   CSRF INIT (ADD THIS)
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_POST['add_teacher'])) {

    /* =========================
       CSRF CHECK (ADDED ONLY)
    ========================= */
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token. Request blocked.");
    }

    // =========================
    // INPUTS
    // =========================
    $full_name = trim($_POST['full_name']);
    $email = strtolower(trim($_POST['email']));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'];

    $employee_no = trim($_POST['employee_no'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $experience_years = (int)($_POST['experience_years'] ?? 0);

    // =========================
    // VALIDATION
    // =========================
    if (empty($full_name) || empty($email) || empty($password)) {
        $message = "Full name, email and password are required!";
        $messageType = "error";
    } else {

        // =========================
        // CHECK DUPLICATE EMAIL
        // =========================
        $check = $conn->prepare("SELECT id FROM pending_teachers WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {

            $message = "Email already exists!";
            $messageType = "error";

        } else {

            // =========================
            // GET ROLE ID
            // =========================
            $role_name = "teacher";
            $role_stmt = $conn->prepare("SELECT id FROM roles WHERE role_name=?");
            $role_stmt->bind_param("s", $role_name);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();
            $role_data = $role_result->fetch_assoc();

            if (!$role_data) {

                $message = "Role 'teacher' not found in roles table!";
                $messageType = "error";

            } else {

                $role_id = $role_data['id'];

                // =========================
                // OTP
                // =========================
                $otp = rand(100000, 999999);
                $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

                // =========================
                // HASH PASSWORD
                // =========================
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // =========================
                // INSERT TEACHER
                // =========================
                $stmt = $conn->prepare("
                    INSERT INTO pending_teachers
                    (
                        full_name,
                        email,
                        phone,
                        password,
                        role_id,
                        employee_no,
                        specialization,
                        qualification,
                        experience_years,
                        otp,
                        otp_expires
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)
                ");

                $stmt->bind_param(
                    "ssssisssiss",
                    $full_name,
                    $email,
                    $phone,
                    $hashedPassword,
                    $role_id,
                    $employee_no,
                    $specialization,
                    $qualification,
                    $experience_years,
                    $otp,
                    $expiry
                );

                if ($stmt->execute()) {

                    sendOTP($email, $full_name, $otp);

                    header("Location: verify2.php?email=" . urlencode($email));
                    exit;

                } else {
                    $message = "Database error: " . $stmt->error;
                    $messageType = "error";
                }
            }
        }
    }
}

/* =========================
   PAYMENT STATUS COUNTS
========================= */

$successful_payments = 0;
$pending_payments = 0;
$failed_payments = 0;

$result = mysqli_query($conn,"
    SELECT status, COUNT(*) AS total
    FROM payments
    GROUP BY status
");

while($row = mysqli_fetch_assoc($result)){

    if($row['status'] == 'success'){
        $successful_payments = $row['total'];
    }

    if($row['status'] == 'pending'){
        $pending_payments = $row['total'];
    }

    if($row['status'] == 'failed'){
        $failed_payments = $row['total'];
    }
}


/* =========================
   REVENUE GRAPH DATA
========================= */

$revenue_labels = [];
$revenue_values = [];

$revenue_query = mysqli_query($conn,"
    SELECT
        DATE_FORMAT(MIN(payment_date),'%b %Y') AS month_name,
        SUM(amount) AS total
    FROM payments
    WHERE status='success'
      AND payment_date IS NOT NULL
    GROUP BY YEAR(payment_date), MONTH(payment_date)
    ORDER BY YEAR(payment_date), MONTH(payment_date)
");
if($revenue_query){

    while($row = mysqli_fetch_assoc($revenue_query)){

        $revenue_labels[] = $row['month_name'];
        $revenue_values[] = (float)$row['total'];
    }
}


/* =========================
   STUDENT GROWTH DATA
========================= */

$student_labels = [];
$student_values = [];

$student_query = mysqli_query($conn,"
    SELECT
        DATE_FORMAT(MIN(created_at),'%b %Y') AS month_name,
        COUNT(*) AS total
    FROM users
    WHERE role='student'
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
");

$running_total = 0;

if($student_query){

    while($row = mysqli_fetch_assoc($student_query)){

        $running_total += $row['total'];

        $student_labels[] = $row['month_name'];
        $student_values[] = $running_total;
    }
}


/* =========================
   ENROLLMENT TREND DATA
========================= */

$enrollment_labels = [];
$enrollment_values = [];

$enrollment_query = mysqli_query($conn,"
    SELECT
        DATE_FORMAT(MIN(enrolled_at),'%b %Y') AS month_name,
        COUNT(*) AS total
    FROM enrollments
    GROUP BY YEAR(enrolled_at), MONTH(enrolled_at)
    ORDER BY YEAR(enrolled_at), MONTH(enrolled_at)
");

if($enrollment_query){

    while($row = mysqli_fetch_assoc($enrollment_query)){

        $enrollment_labels[] = $row['month_name'];
        $enrollment_values[] = (int)$row['total'];
    }
}


/* =========================
   FALLBACK DATA
========================= */

if(empty($revenue_labels)){
    $revenue_labels = ['No Data'];
    $revenue_values = [0];
}

if(empty($student_labels)){
    $student_labels = ['No Data'];
    $student_values = [0];
}

if(empty($enrollment_labels)){
    $enrollment_labels = ['No Data'];
    $enrollment_values = [0];
}


?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>LMS Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- FONT AWESOME -->
<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, Helvetica, sans-serif;
}

body{
    display:flex;
    background:#f4f7fb;
    min-height:100vh;
    overflow-x:hidden;
}

/*  SIDEBAR */

.sidebar{
    width:270px;
    background:#0f172a;
    color:#fff;
    position:fixed;
    left:0;
    top:0;
    bottom:0;
    overflow-y:auto;
    z-index:1000;
}

.logo{
    margin-top:15px;
    padding:10px 20px;
    border-bottom:1px solid rgba(255,255,255,0.1);
    text-align:center;
}

.logo h2{
    font-size:26px;
    color:#38bdf8;
}

.menu{
    padding:10px 0;
}

.menu ul{
    list-style:none;
}

.menu ul li{
    margin:5px 0;
}

.menu ul li a{
    text-decoration:none;
    color:#fff;
    display:flex;
    align-items:center;
    gap:15px;
    padding:15px 25px;
    transition:0.3s;
    font-size:15px;
}

.menu ul li a:hover,
.menu ul li a.active{
    background:#1e293b;
    border-left:4px solid #38bdf8;
}

.menu ul li a i{
    width:20px;
    text-align:center;
}

/* MAIN */

.main{
    margin-left:250px;
    width:calc(100% - 270px);
    padding:10px;
}

/*TOPBAR */

.topbar{
    background:#fff;
    padding:18px 25px;
    border-radius:12px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
    margin-bottom:25px;
}

.topbar h1{
    font-size:28px;
    color:#0f172a;
}

.admin-profile{
    display:flex;
    align-items:center;
    gap:15px;
}

.admin-profile img{
    width:50px;
    height:50px;
    border-radius:50%;
    object-fit:cover;
}

.admin-profile .info h4{
    color:#0f172a;
    font-size:16px;
}

.admin-profile .info p{
    color:#64748b;
    font-size:13px;
}

/* CARDS */

.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-bottom:30px;
}

.card{
    background:#fff;
    padding:20px;
    border-radius:15px;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
    transition:0.3s;
}

.card:hover{
    transform:translateY(-5px);
}

.card .icon{
    width:40px;
    height:40px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:24px;
    color:#fff;
    margin-bottom:10px;
}

.students{ background:#2563eb; }
.courses{ background:#16a34a; }
.payments{ background:#f59e0b; }
.revenue{ background:#dc2626; }

.card h3{
    margin-bottom:5px;
    color:#0f172a;
}

.card p{
    color:#64748b;
    font-size:15px;
}

/* CHARTS SECTION */

.charts{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
    gap:25px;
    margin-bottom:35px;
}

.chart-box{
    background:#ffffff;
    border-radius:24px;
    padding:25px;
    position:relative;
    overflow:hidden;
    box-shadow:0 6px 18px rgba(15,23,42,0.06);
    transition:0.4s ease;
    border:1px solid rgba(226,232,240,0.7);
}

.chart-box:hover{
    transform:translateY(-8px);
}

.chart-box h2{
    font-size:22px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:22px;
}

.chart-placeholder{
    height:260px;
    border:2px dashed #cbd5e1;
    border-radius:18px;
    background:#f8fafc;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    text-align:center;
}

.chart-placeholder i{
    font-size:55px;
    color:#2563eb;
    margin-bottom:15px;
}

.chart-placeholder p{
    color:#64748b;
}

.payment-summary{
    display:flex;
    flex-direction:column;
    gap:15px;
}

.summary-item{
    background:#f8fafc;
    padding:18px;
    border-radius:14px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    border-left:5px solid #2563eb;
}

.summary-item strong{
    font-size:20px;
    color:#0f172a;
}

/*   TABLE SECTION */

.table-section{
    background:#fff;
    padding:25px;
    border-radius:15px;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
    margin-bottom:25px;
    overflow-x:auto;
}

.table-section h2{
    margin-bottom:20px;
    color:#0f172a;
}

table{
    width:100%;
    border-collapse:collapse;
}

table thead{
    background:#0f172a;
    color:#fff;
}

table th,
table td{
    padding:15px;
    text-align:left;
    border-bottom:1px solid #e2e8f0;
}

table tbody tr:hover{
    background:#f8fafc;
}

/*  STATUS */

.status{
    padding:6px 12px;
    border-radius:20px;
    font-size:12px;
    font-weight:bold;
}

.active{
    background:#dcfce7;
    color:#166534;
}

.pending{
    background:#fef3c7;
    color:#92400e;
}

.failed{
    background:#fee2e2;
    color:#991b1b;
}

/* BUTTONS */

.btn{
    padding:8px 14px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    font-size:13px;
    color:#fff;
}

.edit{
    background:#2563eb;
}

.delete{
    background:#dc2626;
}

/*  ACTIONS */

.actions{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
}

.action-box{
    background:#fff;
    padding:25px;
    border-radius:15px;
    text-align:center;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
    transition:0.3s;
}

.action-box:hover{
    transform:translateY(-5px);
}

.action-box i{
    font-size:35px;
    margin-bottom:15px;
    color:#2563eb;
}

.action-box h4{
    margin-bottom:10px;
    color:#0f172a;
}

.action-box p{
    color:#64748b;
    font-size:14px;
}

/* RESPONSIVE  */

@media(max-width:900px){

    .sidebar{
        width:100px;
    }

    .sidebar .logo h2,
    .sidebar .menu ul li a span{
        display:none;
    }

    .main{
        margin-left:100px;
        width:calc(100% - 100px);
    }

    .menu ul li a{
        justify-content:center;
    }
}

@media(max-width:768px){

    .charts{
        grid-template-columns:1fr;
    }

    .chart-placeholder{
        height:220px;
    }
}

@media(max-width:600px){

    .sidebar{
        display:none;
    }

    .main{
        margin-left:0;
        width:100%;
    }

    .topbar{
        flex-direction:column;
        gap:15px;
        align-items:flex-start;
    }

    .cards{
        grid-template-columns:1fr;
    }

    .summary-item{
        flex-direction:column;
        gap:10px;
        text-align:center;
    }
}
/*    ACTION BOXES */
.actions{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-top:20px;
}

.action-box{
    background:#fff;
    padding:20px;
    border-radius:15px;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
    cursor:pointer;
    transition:0.3s;
    text-align:center;
}

.action-box:hover{
    transform:translateY(-5px);
    background:#f1f5f9;
}

.action-box i{
    font-size:30px;
    color:#2563eb;
    margin-bottom:10px;
}

/*  MODAL BACKDROP */
.modal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.6);
    justify-content:center;
    align-items:center;
    z-index:9999;
}

/*  MODAL BOX */
.modal-content{
    background:#fff;
    width:400px;
    padding:25px;
    border-radius:15px;
    position:relative;
    animation:fadeIn 0.3s ease;
}

/* CLOSE BUTTON */
.close{
    position:absolute;
    right:15px;
    top:10px;
    font-size:22px;
    cursor:pointer;
}

/* INPUTS */
.modal-content input,
.modal-content textarea,
.modal-content select{
    width:100%;
    padding:10px;
    margin:10px 0;
    border:1px solid #ccc;
    border-radius:8px;
}

/* BUTTON */
.modal-content button{
    width:100%;
    padding:10px;
    border:none;
    background:#2563eb;
    color:#fff;
    border-radius:8px;
    cursor:pointer;
}

.modal-content button:hover{
    background:#1e40af;
}

/* ANIMATION */
@keyframes fadeIn{
    from{transform:translateY(-20px);opacity:0;}
    to{transform:translateY(0);opacity:1;}
}

.charts{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(450px,1fr));
    gap:20px;
    margin-top:20px;
}

.chart-box{
    background:#fff;
    border-radius:12px;
    padding:20px;
    box-shadow:0 3px 10px rgba(0,0,0,.08);
}

.chart-box h2{
    margin-bottom:15px;
    font-size:18px;
}

.chart-box canvas{
    width:100% !important;
    height:300px !important;
}

.payment-summary{
    display:flex;
    justify-content:space-between;
    margin-bottom:20px;
}

.summary-item{
    text-align:center;
}

.summary-item strong{
    display:block;
    font-size:22px;
    margin-top:5px;
}
</style>
</head>
<body>

<!--     SIDEBAR -->

<div class="sidebar">

    <div class="logo">
        <h2>LMS Admin</h2>
    </div>

    <div class="menu">
        <ul>

            <li>
                <a href="#" onclick="showDashboard()" class="active">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li>
                <a href="#" onclick="showStudent()">
                    <i class="fas fa-user-graduate"></i>
                    <span>Students</span>
                </a>
            </li>

            <li>
                <a href="#" onclick="showCourse()">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>
            </li>
            <li>
                <a href="#" onclick="showRole()">
                    <i class="fas fa-lock"></i>
                    <span>Role</span>
                </a>
            </li>

            <li>
                <a href="#" onclick="showVideos()">
                    <i class="fas fa-video"></i>
                    <span>Videos</span>
                </a>
            </li>

            <li>
                <a href="#" onclick="showNotes()">
                    <i class="fas fa-file-pdf"></i>
                    <span>Notes</span>
                </a>
            </li>

            <li>
                <a href="#"onclick="showQuizzes()">
                    <i class="fas fa-question-circle"></i>
                    <span>Quizzes</span>
                </a>
            </li>

            <li>
                <a href="#" onclick="showEnrollments()">
                    <i class="fas fa-user-check"></i>
                    <span>Enrollments</span>
                </a>
            </li>
            <li>
                <a href="cpassword.php">
                    <i class="fas fa-user-plus"></i>
                    <span>Assign</span>
                </a>
            </li>

            <li>
                <a href="#" onclick="showPayments()">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                </a>
            </li>

            <li>
                <a href="#" onclick="showAnalytics()">
                    <i class="fas fa-chart-pie"></i>
                    <span>Analytics</span>
                </a>
            </li>
            

            <li>
                <a href="#" onclick="showNotifications()">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>

            <li>
                <a href="#" onclick="showTicket()">
                    <i class="fas fa-file-pdf"></i>
                    <span> Tickets </span>
                </a>
            </li>

            <li>
                <a href="#" onclick="showSettings()">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>

            <li>
                <a href="login.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>

        </ul>
    </div>

</div>

<!-- 
     MAIN CONTENT
 -->

<div class="main">
<div id="dashboardSection">

    <!-- TOPBAR -->
    <div class="topbar" >

        <h1>Admin Dashboard</h1>

        <div class="admin-profile">

            <img src="https://i.pravatar.cc/100" alt="Admin">

            <div class="info">
                <h4>System Admin</h4>
                <p>Administrator</p>
            </div>

        </div>

    </div>

    <!--    STATISTICS -->

    <div class="cards">

        <div class="card">
            <div class="icon students">
                <i class="fas fa-user-graduate"></i>
            </div>
            <h3><?= $total_students ?></h3>
            <p>Total Students</p>
        </div>

        <div class="card">
            <div class="icon students">
                <i class="fas fa-user-check"></i>
            </div>
            <h3><?= $active_students ?></h3>
            <p>Active Users</p>
        </div>

        <div class="card">
            <div class="icon courses">
                <i class="fas fa-book"></i>
            </div>
            <h3><?= $total_courses ?></h3>
            <p>Total Courses</p>
        </div>

        <div class="card">
            <div class="icon courses">
                <i class="fas fa-unlock"></i>
            </div>
            <h3><?= $free_courses ?></h3>
            <p>Free Courses</p>
        </div>

        <div class="card">
            <div class="icon payments">
                <i class="fas fa-lock"></i>
            </div>
            <h3><?= $paid_courses ?></h3>
            <p>Paid Courses</p>
        </div>

        <div class="card">
            <div class="icon payments">
                <i class="fas fa-user-plus"></i>
            </div>
            <h3><?= $total_enrollments ?></h3>
            <p>Total Enrollments</p>
        </div>

        <div class="card">
            <div class="icon revenue">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <h3>KES <?= number_format($revenue) ?></h3>
            <p>Revenue Generated</p>
        </div>

        <div class="card">
            <div class="icon courses">
                <i class="fas fa-chart-line"></i>
            </div>
            <h3><?= round($completion_rate) ?>%</h3>
            <p>Course Completion Rate</p>
        </div>

        <div class="card">
            <div class="icon payments">
                <i class="fas fa-clock"></i>
            </div>
            <h3><?= $pending_payments ?></h3>
            <p>Pending Payments</p>
        </div>

    </div>

    <!-- 
         CHARTS
     -->
     <div class="charts">

<div class="chart-box">
    <h2><i class="fas fa-chart-area"></i> Revenue Graph</h2>
    <canvas id="revenueChart"></canvas>
</div>

<div class="chart-box">
    <h2><i class="fas fa-chart-line"></i> Student Growth</h2>
    <canvas id="studentChart"></canvas>
</div>

<div class="chart-box">
    <h2><i class="fas fa-chart-bar"></i> Enrollment Trends</h2>
    <canvas id="enrollmentChart"></canvas>
</div>

<div class="chart-box">
    <h2><i class="fas fa-money-bill-wave"></i> Payment Status</h2>

    <div class="payment-summary">
        <div><span>Success</span><strong><?= $successful_payments ?></strong></div>
        <div><span>Pending</span><strong><?= $pending_payments ?></strong></div>
        <div><span>Failed</span><strong><?= $failed_payments ?></strong></div>
    </div>

    <canvas id="paymentChart"></canvas>
</div>

</div>
    <!-- 
         MOST POPULAR COURSES
     -->

    <div class="table-section">

        <h2>Most Popular Courses</h2>

        <table>

            <thead>
                <tr>
                    <th>Course</th>
                    <th>Students</th>
                    <th>Revenue</th>
                    <th>Completion</th>
                </tr>
            </thead>

            <tbody>

            <?php while($course = mysqli_fetch_assoc($popular_courses)): ?>

                <tr>

                    <td><?= $course['title'] ?></td>

                    <td><?= $course['users'] ?></td>

                    <td>KES <?= number_format($course['revenue']) ?></td>

                    <td><?= round($course['completion_rate']) ?>%</td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

    <!-- 
         RECENT STUDENTS
     -->

   <div class="table-section">

    <h2>Recent Registrations</h2>

    <table>

        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Course</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>

        <?php while($student = mysqli_fetch_assoc($recent_students)): ?>

            <tr>

                <td><?= $student['id'] ?></td>

                <td><?= $student['full_name'] ?></td>

                <td><?= $student['email'] ?></td>

                <td><?= $student['course_name'] ?? 'No Course' ?></td>

                <td>
                    <span class="status 
                        <?= ($student['status'] ?? 'pending') == 'active' ? 'active' : 'pending' ?>">
                        <?= ucfirst($student['status'] ?? 'pending') ?>
                    </span>
                </td>

               

            </tr>

        <?php endwhile; ?>

        </tbody>

    </table>

</div>
   
   <div class="table-section">

    <h2>Recent Activities</h2>

    <table>

        <thead>
            <tr>
                <th>Activity</th>
                <th>User</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>

        <?php while($activity = mysqli_fetch_assoc($activities)): ?>

            <tr>

                <td><?= $activity['activity_name'] ?? '' ?></td>

                <td><?= $activity['user_name'] ?? 'System' ?></td>

                <td>
                    <?= date("d M Y", strtotime($activity['created_at'])) ?>
                </td>

                <td>
                    <span class="status active">
                        <?= ucfirst($activity['status'] ?? 'completed') ?>
                    </span>
                </td>

            </tr>

        <?php endwhile; ?>

        </tbody>

    </table>

</div>
<!-- 
     ACTIONS SECTION
 -->
<div class="actions">

    <div class="action-box" onclick="openModal('courseModal')">
        <i class="fas fa-plus-circle"></i>
        <h4>Create Course</h4>
        <p>Add and publish new courses for students.</p>
    </div>

    <div class="action-box" onclick="openModal('uploadModal')">
        <i class="fas fa-upload"></i>
        <h4>Upload Materials</h4>
        <p>Upload notes, PDFs and videos securely.</p>
    </div>

    <div class="action-box" onclick="window.location.href='payments.php'">
        <i class="fas fa-money-check-alt"></i>
        <h4>Monitor Payments</h4>
        <p>Track paid courses and student payments.</p>
    </div>

    <div class="action-box" onclick="window.location.href='reports.php'">
        <i class="fas fa-chart-bar"></i>
        <h4>View Reports</h4>
        <p>Analyze enrollments and performance.</p>
    </div>

    

</div>

<!-- 
     OVERLAY MODALS
 -->

<div id="courseModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('courseModal')">&times;</span>

        <h3>Create Course</h3>

        <form method="POST" enctype="multipart/form-data">

            <input type="text" name="title" placeholder="Course Title" required>
            <textarea name="description" placeholder="Description" required></textarea>
            <input type="text" name="category" placeholder="Category" required>
            <input type="text" name="instructor" placeholder="Instructor" required>
            <input type="number" name="price" placeholder="Price">

            <input type="file" name="thumbnail" accept="image/*">

            <button type="submit" name="create_course">Create Course</button>
        </form>
    </div>
</div>


<div id="uploadModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('uploadModal')">&times;</span>

        <h3>Upload Materials</h3>

        <form method="POST" enctype="multipart/form-data">

            <input type="text" name="title" placeholder="Material Title" required>

            <select name="course_id" required>
                <option value="">Select Course</option>
                <?php
                $courses = mysqli_query($conn,"SELECT id,title FROM courses");
                while($c = mysqli_fetch_assoc($courses)):
                ?>
                    <option value="<?= $c['id'] ?>"><?= $c['title'] ?></option>
                <?php endwhile; ?>
            </select>

            <input type="file" name="file" required accept=".pdf,.doc,.docx,image/*">

            <button type="submit" name="upload_material">Upload</button>

        </form>
    </div>
</div>   

</div>

<!--== ROLE --> 
<div class="box" id="roleSection" style="display:none;">

<div class="container">
<div class="card">

<h2>Add Teacher</h2>

<?php if (!empty($message)) { ?>
    <div class="<?php echo htmlspecialchars($messageType); ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<form method="POST" autocomplete="off">

    <!-- 🔐 CSRF PROTECTION -->
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <label>Teacher Name *</label>
    <input type="text" name="full_name" required maxlength="100" pattern="[A-Za-z\s]+" title="Only letters allowed">

    <label>Email *</label>
    <input type="email" name="email" required maxlength="150">

    <label>Password *</label>
    <input type="password" name="password" required minlength="6">

    <label>Employee Number</label>
    <input type="text" name="employee_no" maxlength="50">

    <label>Phone Number</label>
    <input type="text" name="phone" maxlength="20" pattern="[0-9+]+">

    <label>Specialization</label>
    <input type="text" name="specialization" maxlength="100">

    <label>Qualification</label>
    <input type="text" name="qualification" maxlength="100">

    <label>Experience Years</label>
    <input type="number" name="experience_years" min="0" max="60">

    <button type="submit" name="add_teacher">Add Teacher</button>

</form>

</div>
</div>
</div>





<!-- == COURSE SECTION == -->
<div class="box" id="coursesSection" style="display:none;">

<style>
body{font-family:Arial;background:#f4f6f9;padding:20px;margin:0}
.container{max-width:1200px;margin:auto}
.card{background:#fff;padding:20px;margin-bottom:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
input,textarea,select{width:100%;padding:10px;margin-bottom:10px;border:1px solid #ccc;border-radius:5px}
button{padding:10px;border:none;border-radius:5px;cursor:pointer}
.btn-primary{background:#007bff;color:#fff}
.btn-danger{background:#dc3545;color:#fff}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ddd;padding:10px}
.status{padding:5px 10px;border-radius:5px;color:#fff}
.Active{background:green}
.Inactive{background:red}
img{width:80px;border-radius:5px}
</style>

<div class="container">

<!-- CREATE COURSE -->
<div class="card">
<h2>Create Course</h2>

<form method="POST" enctype="multipart/form-data">

    <!-- 🔐 CSRF -->
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <input type="text" name="title" placeholder="Course Title" required maxlength="150">

    <textarea name="description" placeholder="Course Description" required maxlength="2000"></textarea>

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

    <input type="text" name="custom_category" placeholder="Custom Category (optional)" maxlength="100">

    <label>Thumbnail</label>
    <input type="file" name="thumbnail" accept="image/*">

    <select name="course_type">
        <option value="Free">Free</option>
        <option value="Paid">Paid</option>
    </select>

    <select name="status" required>
        <option value="Active">Active</option>
        <option value="Inactive">Inactive</option>
    </select>

    <label>Assign Teacher</label>
    <select name="teacher_id" required>
        <option value="">Select Teacher</option>

        <?php
        $teachers = mysqli_query($conn,"SELECT id, full_name FROM users WHERE role='teacher'");
        while($teacher = mysqli_fetch_assoc($teachers)){
        ?>
            <option value="<?= (int)$teacher['id']; ?>">
                <?= htmlspecialchars($teacher['full_name']) ?>
            </option>
        <?php } ?>
    </select>

    <input type="number" name="price" placeholder="Price" step="0.01" min="0">

    <button class="btn-primary" name="create_course">Create Course</button>

</form>
</div>

<!-- DELETE / ARCHIVE -->
<div class="card">
<h2>Delete / Archive</h2>

<form method="POST">

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <select name="course_id" required>
        <option value="">Select Course</option>

        <?php
        $course_list = mysqli_query($conn,"SELECT id,title FROM courses ORDER BY id DESC");
        while($c = mysqli_fetch_assoc($course_list)){
        ?>
            <option value="<?= (int)$c['id']; ?>">
                <?= htmlspecialchars($c['title']) ?>
            </option>
        <?php } ?>

    </select>

    <select name="action" required>
        <option value="archive">Archive</option>
        <option value="delete">Delete</option>
    </select>

    <button class="btn-danger" name="delete_course">Apply</button>

</form>
</div>

<!-- COURSE LIST -->
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
$courses2 = mysqli_query($conn,"SELECT * FROM courses ORDER BY id DESC");

while($row = mysqli_fetch_assoc($courses2)){
?>
<tr>
<td><?= (int)$row['id'] ?></td>
<td><?= htmlspecialchars($row['title']) ?></td>
<td><?= htmlspecialchars($row['category']) ?></td>

<td>
    <?php if (!empty($row['thumbnail'])) { ?>
        <img src="<?= htmlspecialchars($row['thumbnail']) ?>" alt="thumbnail">
    <?php } ?>
</td>

<td>$<?= htmlspecialchars($row['price']) ?></td>

<td>
    <span class="status <?= htmlspecialchars($row['status']) ?>">
        <?= htmlspecialchars($row['status']) ?>
    </span>
</td>

</tr>
<?php } ?>

</table>

</div>

</div>
</div>




<!== VEDIO SECTION == -- >
<div class="box" id="videoSection" style="display:none;">

<style>
body{font-family:Arial;background:#f5f6fa;}
.container{padding:20px;}

table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
}

th,td{
    padding:10px;
    border:1px solid #ddd;
}

th{
    background:#2563eb;
    color:#fff;
}

input,select{
    width:100%;
    padding:8px;
    margin:5px 0;
}

button{
    padding:10px 15px;
    background:#2563eb;
    color:#fff;
    border:none;
    cursor:pointer;
}

button:hover{
    background:#1e40af;
}

a{color:red;}
</style>


<div class="container">

<!-- ================= UPLOAD FORM ================= -->

<h3 ><?= $editVideo ? "✏️ Edit Video" : "⬆️ Upload Video" ?></h3>

<form method="POST" enctype="multipart/form-data">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
<input type="hidden" name="video_id" value="<?= $editVideo['id'] ?? '' ?>">

<label>Course</label>
<select name="course_id" required>
<option value="">Select Course</option>

<?php
$courses = $conn->query("SELECT id,title FROM courses ORDER BY title ASC");
while ($c = $courses->fetch_assoc()) {
    $sel = ($editVideo && $editVideo['course_id']==$c['id']) ? "selected" : "";
    echo "<option value='{$c['id']}' $sel>".htmlspecialchars($c['title'])."</option>";
}
?>
</select>

<label>Title</label>
<input type="text" name="title" value="<?= $editVideo['title'] ?? '' ?>" required>

<label>Description</label>
<textarea name="description"><?= $editVideo['description'] ?? '' ?></textarea>

<label>Video File</label>
<input type="file" name="video">

<label>Cloud Link</label>
<input type="text" name="cloud_url" value="<?= $editVideo['cloud_url'] ?? '' ?>">

<label>Access Type</label>
<select name="access_type">
<option value="free" <?= (($editVideo['access_type'] ?? '')=='free')?'selected':'' ?>>Free</option>
<option value="paid" <?= (($editVideo['access_type'] ?? '')=='paid')?'selected':'' ?>>Paid</option>
</select>

<?php if ($editVideo) { ?>
<button type="submit" name="update_video">Update Video</button>
<?php } else { ?>
<button type="submit" name="upload_video">Upload Video</button>
<?php } ?>

</form>

<hr>
<!-- ================= VIDEO LIST ================= -->

<table>
<tr>
<th>Course</th>
<th>Title</th>
<th>Access</th>
<th>Description</th>
<th>Action</th>
</tr>

<?php
$sql = "
SELECT v.*, c.title AS course
FROM course_videos v
JOIN courses c ON c.id=v.course_id
ORDER BY v.id DESC
";

$result = $conn->query($sql);

while ($v = $result->fetch_assoc()) {
?>

<tr>
<td><?= htmlspecialchars($v['course']) ?></td>
<td><?= htmlspecialchars($v['title']) ?></td>
<td><?= htmlspecialchars($v['access_type']) ?></td>
<td><?= htmlspecialchars($v['description']) ?></td>

<td>
<a href="?view=<?= $v['id'] ?>">Watch</a>

<?php if (in_array($user_role,['admin','teacher'])) { ?>
| <a href="?edit=<?= $v['id'] ?>">Edit</a>

<form method="POST" style="display:inline;">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
<input type="hidden" name="video_id" value="<?= $v['id'] ?>">

<button type="submit" name="delete_video"
onclick="return confirm('Delete Video?')">
Delete
</button>
</form>
<?php } ?>

</td>
</tr>

<?php } ?>

</table>

<?php if(isset($video) && $video) { ?>

<h2><?= htmlspecialchars($video['title']) ?></h2>
<p><?= nl2br(htmlspecialchars($video['description'])) ?></p>

<?php if (!empty($video['cloud_url'])) { ?>

<iframe
    src="<?= convertDriveLink($video['cloud_url']) ?>"
    width="100%"
    height="600"
    allowfullscreen>
</iframe>

<?php } else { ?>

<video width="100%" controls>
    <source src="<?= htmlspecialchars($video['local_path']) ?>" type="video/mp4">
</video>

<?php } ?>

<?php } else { ?>

<p>Video not found.</p>

<?php } ?>
 
</div>
</div>


<!== Quize == -- >
<div class="box" id="quizeSection" style="display:none;">
<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:Arial,sans-serif;
}

body{
background:#f4f6f9;
padding:20px;
}

.container{
max-width:1400px;
margin:auto;
}

h1{
margin-bottom:20px;
}

.cards{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:15px;
margin-bottom:30px;
}

.card{
background:#fff;
padding:20px;
border-radius:10px;
box-shadow:0 2px 10px rgba(0,0,0,.1);
}

.card h2{
color:#007bff;
margin-bottom:10px;
}

.section{
background:#fff;
padding:20px;
border-radius:10px;
margin-bottom:25px;
box-shadow:0 2px 10px rgba(0,0,0,.1);
}

.section h3{
margin-bottom:15px;
}

input,
textarea,
select{
width:100%;
padding:12px;
margin-bottom:10px;
border:1px solid #ddd;
border-radius:6px;
}

button{
background:#007bff;
color:#fff;
border:none;
padding:12px 20px;
border-radius:6px;
cursor:pointer;
}

button:hover{
background:#0056b3;
}

table{
width:100%;
border-collapse:collapse;
margin-top:15px;
}

table th{
background:#007bff;
color:white;
padding:12px;
text-align:left;
}

table td{
padding:10px;
border-bottom:1px solid #ddd;
}

.pass{
color:green;
font-weight:bold;
}

.fail{
color:red;
font-weight:bold;
}

@media(max-width:768px){
table{
display:block;
overflow-x:auto;
}
}

</style>
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
    <th>Status</th>
    <th>Date</th>
</tr>

<?php

$attempts = mysqli_query($conn,"
SELECT
    qa.*,
    u.full_name,
    q.title AS quiz_title
FROM quiz_attempts qa
LEFT JOIN users u ON u.id = qa.user_id
LEFT JOIN quizzes q ON q.id = qa.quiz_id
ORDER BY qa.id DESC
");

while($attempt = mysqli_fetch_assoc($attempts))
{
    $quiz_id = $attempt['quiz_id'];

    // Get total marks for this quiz
    $total_query = mysqli_query($conn,"
        SELECT COALESCE(SUM(marks),0) AS total_marks
        FROM quiz_questions
        WHERE quiz_id='$quiz_id'
    ");

    $total_row = mysqli_fetch_assoc($total_query);
    $total_marks = $total_row['total_marks'];

    $percentage = 0;

    if($total_marks > 0)
    {
        $percentage = round(($attempt['score'] / $total_marks) * 100);
    }
?>

<tr>

    <td><?= htmlspecialchars($attempt['full_name']) ?></td>

    <td><?= htmlspecialchars($attempt['quiz_title']) ?></td>

    <td><?= $attempt['score'] ?></td>

    <td><?= $percentage ?>%</td>

    <td class="<?= strtolower($attempt['result']) ?>">
        <?= htmlspecialchars($attempt['result']) ?>
    </td>

    <td class="<?= strtolower($attempt['status']) ?>">
        <?= ucfirst($attempt['status']) ?>
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

<!== NOTE SECTION ==-- >
<div class="box" id="noteSection" style="display:none;">

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

<h2>📘 Notes Management </h2>

<!-- ADD NOTE -->
<form method="POST" enctype="multipart/form-data">

<h3>Create Note</h3>

<select name="course_id" required>
<option value="">Select Course</option>
<?php
$courses = $conn->query("SELECT * FROM courses");
while($c = $courses->fetch_assoc()){
    echo "<option value='{$c['id']}'>{$c['title']}</option>";
}
?>
</select>

<input name="title" placeholder="Note Title" required>

<textarea name="content" placeholder="Write text notes..." rows="4"></textarea>

<input type="file" name="file">

<select name="access_type">
<option value="free">Free Course</option>
<option value="paid">Paid Course</option>
</select>

<button name="add_note">Upload Note</button>

</form>

<hr>

<!-- LIST -->
<table>

<tr>
<th>Course</th>
<th>Title</th>
<th>Access</th>
<th>File</th>
<th>Action</th>
</tr>

<?php while($n = $notes->fetch_assoc()) { ?>

<tr>
<td><?php echo $n['course_title']; ?></td>
<td><?php echo $n['title']; ?></td>
<td><?php echo $n['access_type']; ?></td>
<td>
<?php if($n['file_path']) echo "Uploaded"; else echo "Text Only"; ?>
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
 

<!--ANOUNCEMENT SECTION --> 
<div class="box" id="announcementSection" style="display:none;">
<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:Arial, sans-serif;
    background:#f5f6fa;
}

.container{
    padding:20px;
    max-width:1200px;
    margin:auto;
}

.box{
    background:#fff;
    padding:20px;
    border-radius:10px;
    margin-bottom:20px;
    box-shadow:0 2px 5px rgba(0,0,0,0.1);
}

input,
textarea,
select{
    width:100%;
    padding:12px;
    margin:8px 0 15px;
    border:1px solid #ccc;
    border-radius:5px;
    font-size:14px;
    outline:none;
    background:#fff;
}

input:focus,
textarea:focus,
select:focus{
    border-color:#2563eb;
    box-shadow:0 0 5px rgba(37,99,235,0.3);
}

textarea{
    min-height:120px;
    resize:vertical;
}

button{
    background:#2563eb;
    color:#fff;
    padding:12px 20px;
    border:none;
    cursor:pointer;
    border-radius:5px;
    font-size:14px;
}

button:hover{
    background:#1d4ed8;
}

table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    overflow:hidden;
}

th,
td{
    padding:12px;
    border:1px solid #ddd;
    text-align:left;
}

th{
    background:#2563eb;
    color:#fff;
}

.delete{
    background:red;
    color:white;
    padding:6px 12px;
    border-radius:4px;
    text-decoration:none;
    display:inline-block;
}

.delete:hover{
    background:#cc0000;
}
</style>

<div class="container">

<h2>📢 Teacher Announcements</h2>

<div class="box">

<form method="POST">

<label>Select Course</label>

<select name="course_id" onchange="this.form.submit()">

    <option value="0">-- All Courses --</option>

    <?php while($c = $courses->fetch_assoc()){ ?>

        <option value="<?= (int)$c['id']; ?>"
            <?= (isset($selected_course) && $selected_course == $c['id']) ? 'selected' : '' ?>>

            <?= htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?>

        </option>

    <?php } ?>

</select>

<input
    type="text"
    name="title"
    placeholder="Title"
    required
>

<textarea
    name="message"
    placeholder="Message..."
    required
></textarea>

<button type="submit" name="add">
    Post Announcement
</button>

</form>

</div>

<!-- TABLE -->
<table  border="1" cellpadding="10" cellspacing="0">

<tr>
<th>Course</th>
<th>Title</th>
<th>Message</th>
<th>Date</th>
<th>Action</th>
</tr>

<?php if ($announcements && $announcements->num_rows > 0) { ?>

    <?php while($a = $announcements->fetch_assoc()) { ?>

    <tr>
        <td><?= htmlspecialchars($a['course']); ?></td>
        <td><?= htmlspecialchars($a['title']); ?></td>
        <td><?= nl2br(htmlspecialchars($a['message'])); ?></td>
        <td><?= htmlspecialchars($a['created_at']); ?></td>
        <td>
            <a class="delete"
               href="?delete=<?= $a['id']; ?>"
               onclick="return confirm('Delete announcement?')">
               Delete
            </a>
        </td>
    </tr>

    <?php } ?>

<?php } else { ?>

<tr>
    <td colspan="5" style="text-align:center;">
        No announcements found
    </td>
</tr>

<?php } ?>

</table>

</div>
</div>

<!-- = ADMIN_ENROLLMEN_SECTION -->
<div class="box" id="enrollmentSection" style="display:none;">
<style>
body{
    font-family:Arial;
    background:#f5f6fa;
    margin:0;
}

.container{
    padding:20px;
}

h2{
    margin-bottom:15px;
}

/* BUTTONS */
.btn{
    padding:6px 12px;
    border:none;
    color:white;
    border-radius:5px;
    cursor:pointer;
    text-decoration:none;
    margin:2px;
}

.approve{background:#16a34a;}
.reject{background:#dc2626;}
.delete{background:#7f1d1d;}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    background:white;
}

th,td{
    border:1px solid #ddd;
    padding:10px;
    text-align:left;
}

th{
    background:#2563eb;
    color:white;
}

/* STATUS */
.status{
    padding:5px 10px;
    border-radius:5px;
    color:white;
    font-size:12px;
}

.pending{background:#f59e0b;}
.ongoing{background:#f59e0b;}
.approved{background:#16a34a;}
.rejected{background:#dc2626;}

/* TOP ACTION BAR */
.actions{
    display:flex;
    gap:10px;
    margin-bottom:15px;
}
</style>
<div class="container">

<h2>📋 Enrollment Management System</h2>

<!-- BULK ACTIONS -->
<form method="POST" class="actions">

    <button type="submit" name="approve_all" class="btn approve"
    onclick="return confirm('Approve ALL pending enrollments?')">
        Save All (Approve)
    </button>

    <button type="submit" name="reject_all" class="btn reject"
    onclick="return confirm('Reject ALL pending/ongoing enrollments?')">
        Clear All (Reject)
    </button>

    <button type="submit" name="clear_all" class="btn delete"
    onclick="return confirm('DELETE ALL enrollments permanently?')">
        Delete All
    </button>

</form>

<!-- TABLE -->
<table>

<tr>
<th>Student</th>
<th>Email</th>
<th>Course</th>
<th>Status</th>
<th>Date</th>
<th>Actions</th>
</tr>

<?php while($row = $enrollments->fetch_assoc()) { ?>

<tr>

<td><?php echo $row['full_name']; ?></td>
<td><?php echo $row['email']; ?></td>
<td><?php echo $row['course']; ?></td>

<td>
<span class="status <?php echo $row['status']; ?>">
    <?php echo strtoupper($row['status']); ?>
</span>
</td>

<td><?php echo $row['enrolled_at']; ?></td>

<td>

<a class="btn approve"
href="?approve=<?php echo $row['id']; ?>">
Approve
</a>

<a class="btn reject"
href="?reject=<?php echo $row['id']; ?>">
Reject
</a>

</td>

</tr>

<?php } ?>

</table>

</div>

</div>




<!-- TICKET SECTION -->
<div class="box" id="ticketSection" style="display:none;">
<style>
        body{
            font-family: Arial, sans-serif;
            background:#f4f6f8;
            padding:20px;
        }

        h2{
            color:#333;
            margin-bottom:15px;
        }

        /* ===== TABLE ===== */
        table{
            width:100%;
            border-collapse:collapse;
            background:white;
            box-shadow:0 3px 12px rgba(0, 0, 0, 0.1);
            border-radius:10px;
            overflow:hidden;
        }

        th{
            background:#222;
            color:white;
            padding:12px;
            text-align:left;
            font-size:14px;
        }

        td{
            padding:12px;
            border-bottom:1px solid #eee;
            font-size:14px;
        }

        tr:hover{
            background:#f9f9f9;
        }

        /* ===== STATUS COLORS ===== */
        .status{
            font-weight:bold;
        }

        .open{ color:#ff9800; }
        .in_progress{ color:#2196f3; }
        .resolved{ color:#4caf50; }
        .closed{ color:#555; }

        /* ===== FORM INSIDE TABLE ===== */
        select{
            padding:6px;
            border:1px solid #ccc;
            border-radius:5px;
        }

        button{
            padding:6px 10px;
            background:#007bff;
            color:white;
            border:none;
            border-radius:5px;
            cursor:pointer;
        }

        button:hover{
            background:#0056b3;
        }

        /* ===== TITLE BAR ===== */
        .title-bar{
            background:#416eb5;
            text-align:center;
            color:white;
            padding:10px;
            border-radius:8px;
            margin-bottom:15px;
        }
    </style>
 
<?php
if (isset($_POST['update_status'])) {

    /* CSRF CHECK */
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Invalid CSRF token.");
    }

    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    /* ALLOWED STATUSES */
    $allowed_statuses = [
        'open',
        'in_progress',
        'resolved',
        'closed'
    ];

    if (
        $ticket_id > 0 &&
        in_array($status, $allowed_statuses, true)
    ) {

        $stmt = $conn->prepare("
            UPDATE tickets
            SET status = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "si",
            $status,
            $ticket_id
        );

        $stmt->execute();
        $stmt->close();
    }
}
?>

<h2>All Support Tickets</h2>

<table border="1" width="100%">

<tr>
    <th>Student</th>
    <th>Type</th>
    <th>Subject</th>
    <th>Message</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php

$stmt = $conn->prepare("
    SELECT
        t.*,
        u.full_name
    FROM tickets t
    LEFT JOIN users u
        ON u.id = t.user_id
    ORDER BY t.id DESC
");

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
?>

<tr>

    <td><?= htmlspecialchars($row['full_name'] ?? '') ?></td>

    <td><?= htmlspecialchars($row['type'] ?? '') ?></td>

    <td><?= htmlspecialchars($row['subject'] ?? '') ?></td>

    <td><?= nl2br(htmlspecialchars($row['message'] ?? '')) ?></td>

    <td><?= strtoupper(htmlspecialchars($row['status'] ?? '')) ?></td>

    <td>

        <form method="POST">

            <!-- CSRF TOKEN -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <input
                type="hidden"
                name="ticket_id"
                value="<?= (int)$row['id'] ?>">

            <select name="status" required>

                <option value="open"
                    <?= ($row['status'] === 'open') ? 'selected' : '' ?>>
                    Open
                </option>

                <option value="in_progress"
                    <?= ($row['status'] === 'in_progress') ? 'selected' : '' ?>>
                    In Progress
                </option>

                <option value="resolved"
                    <?= ($row['status'] === 'resolved') ? 'selected' : '' ?>>
                    Resolved
                </option>

                <option value="closed"
                    <?= ($row['status'] === 'closed') ? 'selected' : '' ?>>
                    Closed
                </option>

            </select>

            <button
                type="submit"
                name="update_status"
                onclick="return confirm('Update ticket status?')">
                Update
            </button>

        </form>

    </td>

</tr>

<?php
}
$stmt->close();
?>

</table>
</div>

<!-- PAYMENT SECTION -->
<div class="box" id="paymentSection" style="display:none;">
<style>
.container{
    max-width:1200px;
    margin:auto;
}

.cards{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:15px;
}

.card{
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
    background:white;
}

th,td{
    padding:12px;
    border-bottom:1px solid #ddd;
    text-align:left;
}

th{
    background:#2c3e50;
    color:white;
}

.badge{
    padding:5px 10px;
    border-radius:5px;
    font-size:12px;
    color:white;
    text-transform:uppercase;
}

.success{background:green;}
.failed{background:red;}
.pending{background:orange;}

a{
    text-decoration:none;

}
</style>
<div class="container">

<h2><i class="fas fa-credit-card"></i> Payment Management</h2>

<!--  STATS  -->
<div class="cards">
    <div class="card">
        <h3>Successful</h3>
        <h2><?= $successful ?></h2>
    </div>

    <div class="card">
        <h3>Failed</h3>
        <h2><?= $failed ?></h2>
    </div>

    <div class="card">
        <h3>Pending</h3>
        <h2><?= $pending ?></h2>
    </div>
</div>

<!--  REVENUE  -->
<h3 style="margin-top:30px;">Revenue Reports</h3>

<div class="cards">
    <div class="card">
        <h3>Daily</h3>
        <h2>KES <?= number_format($daily,2) ?></h2>
    </div>

    <div class="card">
        <h3>Monthly</h3>
        <h2>KES <?= number_format($monthly,2) ?></h2>
    </div>

    <div class="card">
        <h3>Yearly</h3>
        <h2>KES <?= number_format($yearly,2) ?></h2>
    </div>
</div>

<!--  PAYMENT METHODS  -->
<h3 style="margin-top:30px;">Payment Methods</h3>

<table>
<tr>
    <th>Method</th>
    <th>Transactions</th>
    <th>Revenue</th>
</tr>

<?php while($row = mysqli_fetch_assoc($methods)): ?>
<tr>
    <td><?= strtoupper($row['payment_method']) ?></td>
    <td><?= $row['total'] ?></td>
    <td>KES <?= number_format($row['revenue'],2) ?></td>
</tr>
<?php endwhile; ?>
</table>

<!--  COURSE REVENUE  -->
<h3 style="margin-top:30px;">Revenue by Course</h3>

<table>
<tr>
    <th>Course</th>
    <th>Total Revenue</th>
</tr>

<?php while($row = mysqli_fetch_assoc($courseRevenue)): ?>
<tr>
    <td><?= htmlspecialchars($row['title']) ?></td>
    <td>KES <?= number_format($row['total'],2) ?></td>
</tr>
<?php endwhile; ?>
</table>

<!--  PAYMENT MONITOR  -->
<h3 style="margin-top:30px;">Payment Monitoring</h3>

<table>
<tr>
    <th>ID</th>
    <th>Course</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Status</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php while($row = mysqli_fetch_assoc($payments)): ?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['course_id']) ?></td>
    <td>KES <?= number_format($row['amount'],2) ?></td>
    <td><?= strtoupper($row['payment_method']) ?></td>

    <td>
        <span class="badge <?= $row['status'] ?>">
            <?= strtoupper($row['status']) ?>
        </span>
    </td>

    <td><?= $row['created_at'] ?></td>

    <td>

        <!-- APPROVE -->
        <a href="?action=success&id=<?= $row['id'] ?>" 
           onclick="return confirm('Approve this payment?')" 
           style="color:green;">
           Approve
        </a>

        |

        <!-- REJECT -->
        <a href="?action=failed&id=<?= $row['id'] ?>" 
           onclick="return confirm('Reject this payment?')" 
           style="color:red;">
           Reject
        </a>

        |

        <!-- UPDATE (back to pending) -->
        <a href="?action=pending&id=<?= $row['id'] ?>" 
           onclick="return confirm('Move to pending?')" 
           style="color:orange;">
           Update
        </a>

    </td>
</tr>
<?php endwhile; ?>
</table>

</div>

 <?php

/* 
   STUDENT PAYMENT MONITORING
 */
   $payments = mysqli_query($conn,
"SELECT 
    p.id,
    s.full_name AS student_name,
    u.email,
    c.title AS course,
    p.amount,
    p.payment_method,
    p.status,
    p.created_at
FROM payments p
LEFT JOIN users u ON p.user_id = u.id
LEFT JOIN students s ON s.user_id = u.id
LEFT JOIN courses c ON p.course_id = c.id
ORDER BY p.created_at DESC"
);
?>

<h3 style="margin-top:30px;">Student Payment Monitoring</h3>

<table>
<tr>
    <th>ID</th>
    <th>Student Name</th>
    <th>Email</th>
    <th>Course</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Status</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php while($row = mysqli_fetch_assoc($payments)): ?>
<tr>
    <td><?= $row['id'] ?></td>

    <td><?= htmlspecialchars($row['fullname'] ?? 'Unknown') ?></td>

    <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>

    <td><?= htmlspecialchars($row['course']) ?></td>

    <td>KES <?= number_format($row['amount'],2) ?></td>

    <td><?= strtoupper($row['payment_method']) ?></td>

    <td>
        <span class="badge <?= $row['status'] ?>">
            <?= strtoupper($row['status']) ?>
        </span>
    </td>

    <td><?= $row['created_at'] ?></td>

    <td>

        <!-- APPROVE -->
        <a href="?action=success&id=<?= $row['id'] ?>" 
           onclick="return confirm('Approve this payment?')" 
           style="color:green;">
           Approve
        </a>

        |

        <!-- REJECT -->
        <a href="?action=failed&id=<?= $row['id'] ?>" 
           onclick="return confirm('Reject this payment?')" 
           style="color:red;">
           Reject
        </a>

        |

        <!-- PENDING -->
        <a href="?action=pending&id=<?= $row['id'] ?>" 
           onclick="return confirm('Move to pending?')" 
           style="color:orange;">
           Update
        </a>

    </td>
</tr>
<?php endwhile; ?>
</table>

</div>



<!== STUDENT SECTION == -- >
<div class="box" id="studentsSection" style="display:none;">

<style>

.container{
    max-width:1200px;
    margin:auto;
}

.card{
    background:#fff;
    padding:20px;
    margin-bottom:20px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

th,td{
    border:1px solid #ddd;
    padding:10px;
    text-align:left;
}

button{
    padding:6px 10px;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

.btn-success{
    background:#28a745;
    color:#fff;
}

.btn-warning{
    background:#f0ad4e;
    color:#fff;
}

.btn-danger{
    background:#dc3545;
    color:#fff;
}

.btn-primary{
    background:#007bff;
    color:#fff;
}

.status{
    padding:4px 8px;
    border-radius:5px;
    color:#fff;
    font-size:12px;
}

.active,
.approved{
    background:green;
}

.suspended,
.rejected{
    background:red;
}

.pending{
    background:orange;
}

select{
    padding:6px;
    border-radius:5px;
}

/* MODAL */

.modal{
    display:none;
    position:fixed;
    z-index:9999;
    left:0;
    top:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,.5);
}

.modal-content{
    background:#fff;
    width:400px;
    max-width:90%;
    margin:120px auto;
    padding:20px;
    border-radius:10px;
    position:relative;
}

.close{
    position:absolute;
    right:15px;
    top:10px;
    font-size:28px;
    cursor:pointer;
}

.modal input[type=password]{
    width:100%;
    padding:10px;
    border:1px solid #ddd;
    border-radius:5px;
    margin:10px 0 15px;
}

</style>

<div class="container">

    <?php if(isset($_SESSION['success'])){ ?>

        <div style="
            background:#d4edda;
            color:#155724;
            padding:12px;
            margin-bottom:15px;
            border-radius:6px;">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>

        <?php unset($_SESSION['success']); ?>

    <?php } ?>

    <!-- STUDENT RECORDS -->

    <div class="card">

        <h2>Records</h2>

        <table>

            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Registered</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>

            <?php while($s = mysqli_fetch_assoc($students)) { ?>

            <tr>

                <td><?= (int)$s['id'] ?></td>

                <td><?= htmlspecialchars($s['full_name']) ?></td>

                <td><?= htmlspecialchars($s['email']) ?></td>

                <td><?= htmlspecialchars($s['phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($s['created_at']) ?></td>

                <td>
                    <span class="status <?= strtolower(htmlspecialchars($s['status'])) ?>">
                        <?= htmlspecialchars($s['status']) ?>
                    </span>
                </td>

                <td>

                    <form method="POST" style="display:inline;">

                        <input
                            type="hidden"
                            name="student_id"
                            value="<?= (int)$s['id'] ?>"
                        >

                        <select
                            name="action"
                            required
                            onchange="studentAction(this, <?= (int)$s['id'] ?>)">

                            <option value="">Select Action</option>
                            <option value="activate">Activate</option>
                            <option value="suspend">Suspend</option>
                            <option value="reset_password">Reset Password</option>
                            <option value="delete">Delete</option>

                        </select>

                        <button
                            type="submit"
                            name="student_action"
                            class="btn-primary"
                            onclick="return confirm('Proceed with this action?');">
                            Go
                        </button>

                    </form>

                </td>

            </tr>

            <?php } ?>

        </table>

    </div>

    <!-- ENROLLMENT MONITORING -->

    <div class="card">

        <h2>Enrollment Monitoring</h2>

        <table>

            <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Course</th>
                <th>Status</th>
                <th>Progress %</th>
                <th>Enrolled At</th>
                <th>Actions</th>
            </tr>

            <?php while($e = mysqli_fetch_assoc($enrollments)){ ?>

            <tr>

                <td><?= (int)$e['id'] ?></td>

                <td><?= htmlspecialchars($e['full_name']) ?></td>

                <td><?= htmlspecialchars($e['title']) ?></td>

                <td>
                    <span class="status <?= strtolower(htmlspecialchars($e['status'])) ?>">
                        <?= htmlspecialchars($e['status']) ?>
                    </span>
                </td>

                <td><?= (int)$e['progress'] ?>%</td>

                <td><?= htmlspecialchars($e['enrolled_at']) ?></td>

                <td>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="enroll_id"
                            value="<?= (int)$e['id'] ?>"
                        >

                        <button
                            type="submit"
                            class="btn-success"
                            name="action"
                            value="approve">
                            Approve
                        </button>

                        <button
                            type="submit"
                            class="btn-warning"
                            name="action"
                            value="reject">
                            Reject
                        </button>

                        <button
                            type="submit"
                            class="btn-danger"
                            name="action"
                            value="delete"
                            onclick="return confirm('Delete this enrollment?');">
                            Delete
                        </button>

                        <input
                            type="hidden"
                            name="enroll_action"
                            value="1"
                        >

                    </form>

                </td>

            </tr>

            <?php } ?>

        </table>

    </div>

</div>

<!-- RESET PASSWORD MODAL -->

<div id="resetPasswordModal" class="modal">

    <div class="modal-content">

        <span class="close">&times;</span>

        <h3>Reset Student Password</h3>

        <form method="POST">

            <input
                type="hidden"
                name="student_id"
                id="modal_student_id"
            >

            <label>New Password</label>

            <input
                type="password"
                name="new_password"
                minlength="6"
                required
            >

            <button
                type="submit"
                name="reset_student_password"
                class="btn-success">
                Save Password
            </button>

        </form>

    </div>

</div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
function hideAllSections(){

let sections = [
    "dashboardSection",
    "studentsSection",
    "coursesSection",
    "videoSection",
    "noteSection",
    "enrollmentSection",
    "paymentSection",
    "announcementSection",
    "ticketSection",
    "quizeSection",
    "roleSection"
];

sections.forEach(id => {

    let el = document.getElementById(id);

    if(el){
        el.style.display = "none";
    }

});
}

function showRole(){
hideAllSections();
document.getElementById("roleSection").style.display = "block";
}
function showStudent(){
hideAllSections();
document.getElementById("studentsSection").style.display = "block";
}

function showDashboard(){
hideAllSections();
document.getElementById("dashboardSection").style.display = "block";
}
function showCourse(){
    hideAllSections();
    document.getElementById("coursesSection").style.display="block";
}
function showVideos(){
    hideAllSections();
    document.getElementById("videoSection").style.display="block";
}
function showNotes(){
    hideAllSections();
    document.getElementById("noteSection").style.display="block";
}
function showQuizzes(){
    hideAllSections()
    document.getElementById("quizeSection").style.display="block"
}
function showEnrollments(){
    hideAllSections()
    document.getElementById("enrollmentSection").style.display="block"
}
function showPayments(){
    hideAllSections()
    document.getElementById("paymentSection").style.display="block"
}
function showNotifications(){
    hideAllSections()
    document.getElementById("announcementSection").style.display="block"
}
function showTicket(){
    hideAllSections()
    document.getElementById("ticketSection").style.display="block"
}



function openModal(id){
    document.getElementById(id).style.display = "flex";
}

function closeModal(id){
    document.getElementById(id).style.display = "none";
}

// close when clicking outside
window.onclick = function(e){

// close generic modals
document.querySelectorAll('.modal').forEach(modal=>{
    if(e.target === modal){
        modal.style.display = "none";
    }
});

// close reset password modal
let resetModal = document.getElementById('resetPasswordModal');
if(e.target === resetModal){
    resetModal.style.display = "none";
}
};

/* =========================
   REVENUE GRAPH
========================= */

new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($revenue_labels) ?>,
        datasets: [{
            label: 'Revenue',
            data: <?= json_encode($revenue_values) ?>,
            borderWidth: 3,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});


/* =========================
   STUDENT GROWTH
========================= */

new Chart(document.getElementById('studentChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($student_labels) ?>,
        datasets: [{
            label: 'Students',
            data: <?= json_encode($student_values) ?>,
            borderWidth: 3,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});


/* =========================
   ENROLLMENT TRENDS
========================= */

new Chart(document.getElementById('enrollmentChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($enrollment_labels) ?>,
        datasets: [{
            label: 'Enrollments',
            data: <?= json_encode($enrollment_values) ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});


/* =========================
   PAYMENT STATUS PIE
========================= */

new Chart(document.getElementById('paymentChart'), {
    type: 'pie',
    data: {
        labels: ['Successful', 'Pending', 'Failed'],
        datasets: [{
            data: [
                <?= (int)$successful_payments ?>,
                <?= (int)$pending_payments ?>,
                <?= (int)$failed_payments ?>
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});


/* =========================
SECTION FOR STUDENT POP OUT
========================= */

function studentAction(select, studentId){

    if(select.value === 'reset_password'){

        document.getElementById('modal_student_id').value =
            studentId;

        document.getElementById(
            'resetPasswordModal'
        ).style.display = 'block';

        select.selectedIndex = 0;
    }
}



document.addEventListener('DOMContentLoaded', function(){

    if(window.location.hash === '#studentsSection'){

        document.getElementById(
            'studentsSection'
        ).style.display = 'block';
    }

});
 
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>