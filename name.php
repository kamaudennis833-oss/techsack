<?php
session_start();
include 'db.php';

/* =========================
   AUTH CHECK (ONLY ONCE)
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/* =========================
   ENROLLMENT STATS
========================= */
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS enrolled,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing,
        AVG(progress) AS avg_progress
    FROM enrollments
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc() ?? [];

/* safe defaults */
$enrolled  = (int)($data['enrolled'] ?? 0);
$completed = (int)($data['completed'] ?? 0);
$ongoing   = (int)($data['ongoing'] ?? 0);

/* progress calculation */
$progress = (float)($data['avg_progress'] ?? 0);
$progress = round($progress);

if ($progress == 0 && $enrolled > 0) {
    $progress = round(($completed / $enrolled) * 100);
}

/* =========================
   USER COURSES
========================= */
$stmt = $conn->prepare("
    SELECT c.title, e.progress
    FROM courses c
    INNER JOIN enrollments e ON c.id = e.course_id
    WHERE e.user_id = ?
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$courses = $stmt->get_result();

/* =========================
   ACTIVITIES
========================= */
$stmt = $conn->prepare("
    SELECT message, created_at
    FROM activities
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$activities = $stmt->get_result();

/* =========================
   BOOKMARKS
========================= */
$stmt = $conn->prepare("
    SELECT title, created_at
    FROM bookmarks
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookmarks = $stmt->get_result();

/* =========================
   QUIZ STATS (OPTIMIZED)
========================= */
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_quizzes,
        COALESCE(SUM(status='passed'),0) AS passed,
        COALESCE(SUM(status='failed'),0) AS failed,
        COALESCE(AVG(score),0) AS avg_score
    FROM quiz_results
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$quizStats = $stmt->get_result()->fetch_assoc();

$total_quizzes = (int)($quizStats['total_quizzes'] ?? 0);
$passed        = (int)($quizStats['passed'] ?? 0);
$failed        = (int)($quizStats['failed'] ?? 0);
$avgScore      = round((float)($quizStats['avg_score'] ?? 0), 1);
/* =========================
   QUIZ LISTS
========================= */
$quizzes = $conn->query("SELECT * FROM quizzes ORDER BY id DESC");

$results = $conn->prepare("
    SELECT * 
    FROM quiz_results 
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$results->bind_param("i", $user_id);
$results->execute();
$results = $results->get_result();

$history = $conn->prepare("
    SELECT * 
    FROM quiz_history 
    WHERE user_id = ?
    ORDER BY completed_at DESC
");
$history->bind_param("i", $user_id);
$history->execute();
$history = $history->get_result();

$achievements = $conn->prepare("
    SELECT * 
    FROM quiz_achievements 
    WHERE user_id = ?
");
$achievements->bind_param("i", $user_id);
$achievements->execute();
$achievements = $achievements->get_result();

$certs = $conn->query("SELECT * FROM certificates");

/* =========================
   NOTIFICATIONS (FIXED)
========================= */
$stmt = $conn->prepare("
    SELECT
        message,
        created_at AS notification_date,
        'notification' AS type
    FROM notifications
    WHERE user_id = ?

    UNION ALL

    SELECT
        CONCAT('[Announcement] ', a.title, ' - ', a.message) AS message,
        a.created_at AS notification_date,
        'announcement' AS type
    FROM announcements a
    INNER JOIN enrollments e ON e.course_id = a.course_id
    WHERE e.user_id = ?
      AND e.status IN ('approved','ongoing','completed')

    ORDER BY notification_date DESC
    LIMIT 10
");

$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$notifications = $stmt->get_result();

/* =========================
   USER PROFILE
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

/* fallback */
if (!$profile) {
    $profile = [
        'full_name'     => 'Unknown User',
        'email'         => '',
        'phone'         => '',
        'location'      => '',
        'bio'           => '',
        'profile_image' => '',
        'created_at'    => date('Y-m-d')
    ];
}

/* =========================
   CSRF TOKEN (ONLY ONCE)
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   UPDATE PROFILE
========================= */
if (isset($_POST['update_profile'])) {

    /* =========================
       CSRF CHECK
    ========================= */
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token. Request blocked.");
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $location  = trim($_POST['location'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');

    /* =========================
       UPDATE USERS TABLE
    ========================= */
    $stmt = $conn->prepare("
        UPDATE users
        SET full_name = ?,
            email = ?,
            phone = ?,
            location = ?,
            bio = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssssi",
        $full_name,
        $email,
        $phone,
        $location,
        $bio,
        $user_id
    );

    $stmt->execute();

    /* =========================
       UPDATE STUDENTS TABLE
    ========================= */
    $stmt = $conn->prepare("
        UPDATE students
        SET full_name = ?,
            email = ?,
            phone = ?
        WHERE user_id = ?
    ");

    $stmt->bind_param(
        "sssi",
        $full_name,
        $email,
        $phone,
        $user_id
    );

    $stmt->execute();

    /* =========================
       REFRESH PROFILE DATA
    ========================= */
    $stmt = $conn->prepare("
        SELECT *
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();

    /* =========================
       ROTATE CSRF TOKEN
    ========================= */
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    echo "<script>
        alert('Profile updated successfully');
        window.location.href='name.php?section=profile';
    </script>";
    exit;
}

/* =========================
   ENROLLED COURSES COUNT
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM enrollments
    WHERE user_id = ?
      AND status IN ('approved', 'ongoing')
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_courses = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   COURSE STATS (CLEAN + NO DUPLICATES)
========================= */
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_courses,
        SUM(status='ongoing') AS active_courses,
        SUM(status='completed') AS completed_courses
    FROM enrollments
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$courseStats = $stmt->get_result()->fetch_assoc() ?? [];

$total_courses     = $courseStats['total_courses'] ?? 0;
$active_courses    = $courseStats['active_courses'] ?? 0;
$completed_courses = $courseStats['completed_courses'] ?? 0;

/* =========================
   CERTIFICATES
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM certificates
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$certificates_earned = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   ACTIVE COURSES LIST
========================= */
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.title,
        c.teacher_id,
        c.lessons,
        e.progress
    FROM enrollments e
    INNER JOIN courses c ON e.course_id = c.id
    WHERE e.user_id = ?
      AND e.status = 'ongoing'
    ORDER BY e.id DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_courses_list = $stmt->get_result();

/* =========================
   COMPLETED COURSES LIST
========================= */
$stmt = $conn->prepare("
    SELECT 
        c.title,
        e.completed_at
    FROM enrollments e
    INNER JOIN courses c ON e.course_id = c.id
    WHERE e.user_id = ?
      AND e.status = 'completed'
    ORDER BY e.completed_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$completed_courses_list = $stmt->get_result();

/* =========================
   ASSIGNMENTS
========================= */
$stmt = $conn->prepare("
    SELECT a.*
    FROM assignments a
    INNER JOIN enrollments e ON e.course_id = a.course_id
    WHERE e.user_id = ?
    ORDER BY a.due_date ASC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$assignments = $stmt->get_result();

/* =========================
   COURSE MATERIALS
========================= */
$materials = $conn->query("
    SELECT * FROM course_materials
    ORDER BY id DESC
    LIMIT 10
");

/* =========================
   LIVE CLASSES
========================= */
$live_classes = $conn->query("
    SELECT * FROM live_classes
    ORDER BY schedule_time ASC
    LIMIT 10
");

/* =========================
   BROWSE COURSES
========================= */
$search = trim($_GET['search'] ?? '');

$sql = "
SELECT 
    c.id,
    c.title,
    c.description,
    c.price,
    c.lessons,
    c.enrolled_students,
    c.teacher_id
FROM courses c
WHERE c.status = 'Active'
";

if ($search !== '') {
    $safe_search = mysqli_real_escape_string($conn, $search);
    $sql .= " AND c.title LIKE '%$safe_search%'";
}

$sql .= " ORDER BY c.created_at DESC";

$browseCourses = mysqli_query($conn, $sql);

/* =========================
   CATEGORIES (FROM DB ONLY)
========================= */
$cat_result = mysqli_query($conn, "
    SELECT DISTINCT category 
    FROM courses 
    WHERE status='Active'
");

$categories = [];
while ($row = mysqli_fetch_assoc($cat_result)) {
    $categories[] = $row['category'];
}

/* =========================
   ENRICH COURSES (SAFE LOOP)
========================= */
$browseCoursesData = [];

while ($course = mysqli_fetch_assoc($browseCourses)) {

    $course['instructor'] = 'Unknown';

    if (!empty($course['teacher_id'])) {

        $teacher_id = (int)$course['teacher_id'];

        $stmt = $conn->prepare("
            SELECT u.full_name
            FROM teachers t
            INNER JOIN users u ON u.id = t.user_id
            WHERE t.id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $t = $stmt->get_result()->fetch_assoc();

        if ($t) {
            $course['instructor'] = $t['full_name'];
        }
    }

    $browseCoursesData[] = $course;
}

/* =========================
   TRENDING COURSES
========================= */
$trendingCourses = $conn->query("
    SELECT title, enrolled_students
    FROM courses
    WHERE status='Active'
    ORDER BY enrolled_students DESC
    LIMIT 5
");

/* =========================
   TOP INSTRUCTORS
========================= */
$topInstructors = $conn->query("
    SELECT 
        u.full_name,
        t.specialization,
        t.experience_years,
        COUNT(c.id) AS courses_count
    FROM teachers t
    INNER JOIN users u ON u.id = t.user_id
    LEFT JOIN courses c ON c.teacher_id = t.id
    GROUP BY t.id, u.full_name, t.specialization, t.experience_years
    ORDER BY courses_count DESC
    LIMIT 5
");

/* =========================
   STATIC BENEFITS
========================= */
$courseBenefits = [
    ["title" => "Learn Anytime", "description" => "Access courses 24/7 from any device."],
    ["title" => "Expert Instructors", "description" => "Learn from industry professionals."],
    ["title" => "Certified Learning", "description" => "Get certificates after completion."]
];
/*    COURSE CATEGORIES  */

$categories = [
    "Web Development",
    "Programming",
    "UI/UX Design",
    "Cyber Security",
    "Data Science",
    "Mobile Development"
];

/* SUMMARY STATS */
/* =========================
   PROGRESS STATS
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM enrollments
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$progressTotalCourses = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM enrollments
    WHERE user_id = ? AND status='completed'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$progressCompletedCourses = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM enrollments
    WHERE user_id = ? AND status='ongoing'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$progressOngoingCourses = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   OVERALL PROGRESS
========================= */
$overallProgress = 0;

if ($progressTotalCourses > 0) {
    $overallProgress = round(
        ($progressCompletedCourses / $progressTotalCourses) * 100
    );
}

/* =========================
   COURSE PROGRESS LIST
========================= */
$stmt = $conn->prepare("
    SELECT
        c.title,
        e.progress,
        e.status
    FROM enrollments e
    INNER JOIN courses c ON e.course_id = c.id
    WHERE e.user_id = ?
    ORDER BY e.progress DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$courseProgress = $stmt->get_result();

/* =========================
   QUIZ PERFORMANCE
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM quiz_results
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$quizPerformance = $stmt->get_result();

/* =========================
   CERTIFICATES
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM certificates
    WHERE user_id = ?
    ORDER BY issued_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$progressCertificates = $stmt->get_result();

/* =========================
   LEARNING ANALYTICS
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM learning_stats
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$analytics = $stmt->get_result()->fetch_assoc();

if (!$analytics) {
    $analytics = [
        'study_hours' => 0,
        'lessons_completed' => 0,
        'assignments_submitted' => 0
    ];
}

/* =========================
   ACHIEVEMENTS
========================= */
$progressAchievements = [];

/* Completed courses */
if ($progressCompletedCourses > 0) {
    $progressAchievements[] = [
        "title" => "🏆 Completed Courses",
        "description" => "$progressCompletedCourses courses completed successfully"
    ];
}

/* Learning progress */
if ($overallProgress >= 50) {
    $progressAchievements[] = [
        "title" => "🔥 Learning Progress",
        "description" => "You reached $overallProgress% learning progress"
    ];
}

/* Top quiz */
$stmt = $conn->prepare("
    SELECT *
    FROM quiz_results
    WHERE user_id = ?
    ORDER BY score DESC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$topQuiz = $stmt->get_result()->fetch_assoc();

if ($topQuiz) {
    $progressAchievements[] = [
        "title" => "⭐ Top Quiz Performer",
        "description" => $topQuiz['quiz_title'] . " - Score: " . $topQuiz['score'] . "%"
    ];
}

/* =========================
   PAYMENTS & BILLING
========================= */

/* Total paid */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS total
    FROM payments
    WHERE user_id = ? AND status='success'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$totalPaid = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* Pending payments */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS total
    FROM payments
    WHERE user_id = ? AND status='pending'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pendingPayments = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* Transactions count */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM payments
    WHERE user_id = ? AND status='success'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$totalTransactions = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* Invoices count */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM invoices
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$invoiceCount = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   TRANSACTIONS LIST
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM payments
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result();

/* =========================
   INVOICES LIST
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM invoices
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$invoices = $stmt->get_result();

/* =========================
   PAYMENT METHODS
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM payment_methods
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$paymentMethods = $stmt->get_result();

/* =========================
   PAYMENT FORM (FIXED)
========================= */
if (isset($_POST['submit_payment'])) {

    $course_id = (int) $_POST['course_id'];
    $amount    = (float) $_POST['amount'];
    $method    = trim($_POST['payment_method']);
    $phone     = trim($_POST['payer_phone']);

    /* course fetch */
    $stmt = $conn->prepare("
        SELECT title
        FROM courses
        WHERE id = ?
    ");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();

    if ($course) {

        $courseTitle = $course['title'];
        $invoiceNo = "INV" . time();

        /* payment insert */
        $stmt = $conn->prepare("
            INSERT INTO payments
            (user_id, course_id, payer_name, payer_phone, amount, payment_method, status, verification_status)
            VALUES (?, ?, 'Student', ?, ?, ?, 'success', 'verified')
        ");
        $stmt->bind_param("iisds", $user_id, $course_id, $phone, $amount, $method);
        $stmt->execute();

        /* enrollment check */
        $stmt = $conn->prepare("
            SELECT id FROM enrollments
            WHERE user_id = ? AND course_id = ?
        ");
        $stmt->bind_param("ii", $user_id, $course_id);
        $stmt->execute();
        $exists = $stmt->get_result();

        if ($exists->num_rows == 0) {

            $stmt = $conn->prepare("
                INSERT INTO enrollments
                (user_id, course_id, progress, status)
                VALUES (?, ?, 0, 'approved')
            ");
            $stmt->bind_param("ii", $user_id, $course_id);
            $stmt->execute();
        }

        /* invoice */
        $stmt = $conn->prepare("
            INSERT INTO invoices
            (user_id, invoice_no, description, status)
            VALUES (?, ?, ?, 'paid')
        ");
        $stmt->bind_param("iss", $user_id, $invoiceNo, $courseTitle);
        $stmt->execute();

        echo "<script>
            alert('Payment Successful. Enrollment Activated.');
            location.href = location.href;
        </script>";
    }
}

/* =========================
   SETTINGS
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM user_settings
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$settingsRow = $stmt->get_result()->fetch_assoc();

if (!$settingsRow) {
    $settingsRow = [
        'theme' => 'light',
        'email_notifications' => 1,
        'sms_notifications' => 1,
        'password_updated_at' => null
    ];
}

/* =========================
   ATTENDANCE (FIXED)
========================= */
if (isset($_POST['sign_attendance'])) {

    $course_id = (int) $_POST['course_id'];

    if ($course_id <= 0) {
        echo "<script>alert('Please select a course');</script>";
    } else {

        $stmt = $conn->prepare("
            SELECT id
            FROM attendance
            WHERE user_id = ?
              AND course_id = ?
              AND DATE(attendance_date) = CURDATE()
        ");
        $stmt->bind_param("ii", $user_id, $course_id);
        $stmt->execute();
        $check = $stmt->get_result();

        if ($check->num_rows > 0) {
            echo "<script>alert('You already signed attendance today');</script>";
        } else {

            $stmt = $conn->prepare("
                INSERT INTO attendance (user_id, course_id, attendance_date, status)
                VALUES (?, ?, CURDATE(), 'present')
            ");
            $stmt->bind_param("ii", $user_id, $course_id);
            $stmt->execute();

            echo "<script>alert('Attendance signed successfully');</script>";
        }
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="Advance.css">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial,sans-serif;
}

html,
body{
    width:100%;
    overflow-x:hidden;
    background:#f1f5f9;
}

/* 
   BODY LAYOUT
 */
body{
    display:flex;
}
/* 
   SIDEBAR
 */
.sidebar{
    width:260px;
    height:100vh;
    background:#0f172a;
    position:fixed;
    top:0;
    left:0;
    overflow-y:auto;
    z-index:1000;
}

.logo{
    padding:28px 20px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,.08);
}

.logo h2{
    color:#fff;
    font-size:34px;
    font-weight:700;
}

.menu{
    list-style:none;
    padding:15px 0;
}

.menu li{
    width:100%;
}

.menu li a{
    display:flex;
    align-items:center;
    gap:14px;
    width:100%;
    padding:16px 24px;
    text-decoration:none;
    color:#cbd5e1;
    transition:.3s;
    font-size:16px;
    font-weight:500;
}

.menu li a i{
    width:22px;
    text-align:center;
    font-size:16px;
}

.menu li a:hover{
    background:#1e293b;
    color:#fff;
    padding-left:30px;
}

.menu .active a{
    background:#2563eb;
    color:#fff;
}

.logout a{
    color:#ff6b6b !important;
}

.logout a:hover{
    background:#7f1d1d !important;
}

/* 
   MAIN CONTENT
 */
.main-content{
    margin-left:260px;
    width:calc(100% - 260px);
    min-height:100vh;
    padding:30px;
}

/* 
   HEADER
 */
.header{
    width:100%;
    background:#fff;
    border-radius:20px;
    padding:25px;
    margin-bottom:25px;
    box-shadow:0 4px 15px rgba(0,0,0,.05);
}

.header h1{
    color:#0f172a;
    font-size:32px;
    margin-bottom:10px;
}

.header p{
    color:#64748b;
    font-size:15px;
}

/* 
   GRID
 */
.content-grid{
    width:100%;
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:25px;
}

/* 
   BOX
 */
.box{
    width:100%;
    background:#fff;
    border-radius:22px;
    padding:30px;
    box-shadow:0 4px 15px rgba(0,0,0,.06);
    margin-bottom:25px;
}

.box h3{
    display:flex;
    align-items:center;
    gap:12px;
    color:#0f172a;
    font-size:24px;
    margin-bottom:25px;
}

.box h3 i{
    color:#2563eb;
}

/* 
   STATS
 */
.stats{
    width:100%;
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-top:20px;
}

.stat-card{
    background:#f8fafc;
    border-radius:18px;
    padding:25px;
    transition:.3s;
    border:1px solid #e2e8f0;
}

.stat-card:hover{
    transform:translateY(-5px);
    box-shadow:0 10px 25px rgba(0,0,0,.08);
}

.stat-card i{
    font-size:30px;
    color:#2563eb;
    margin-bottom:18px;
}

.stat-card h2{
    font-size:36px;
    color:#111827;
    margin-bottom:8px;
}

.stat-card p{
    color:#64748b;
    font-size:16px;
}

/* 
   ACTIVITY
 */
.activity{
    width:100%;
    background:#f8fafc;
    border-radius:16px;
    padding:18px 20px;
    margin-bottom:18px;
    border-left:5px solid #2563eb;
    border:1px solid #e2e8f0;
    transition:.3s;
}

.activity:hover{
    transform:translateY(-3px);
    box-shadow:0 8px 20px rgba(0,0,0,.06);
}

.activity p{
    font-size:17px;
    color:#111827;
    font-weight:600;
    margin-bottom:8px;
}

.activity span{
    color:#64748b;
    font-size:14px;
    line-height:1.6;
}

/* 
   COURSE
 */
.course{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:20px;
    padding:18px 0;
    border-bottom:1px solid #e5e7eb;
}

.course:last-child{
    border-bottom:none;
}

.course-info h4{
    color:#0f172a;
    margin-bottom:5px;
}

.course-info p{
    color:#64748b;
    font-size:14px;
}

.progress{
    width:140px;
    height:10px;
    background:#e5e7eb;
    border-radius:50px;
    overflow:hidden;
    margin-top:8px;
}

.progress-bar{
    height:100%;
    background:#10b981;
}

/* 
   BUTTONS
 */
.btn{
    border:none;
    background:green;
    color:#fff;
    padding:13px 22px;
    border-radius:12px;
    cursor:pointer;
    font-size:15px;
    font-weight:600;
    transition:.3s;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:10px;
    width: 10rem;
}

.btn:hover{
    background:#1d4ed8;
    transform:translateY(-2px);
}


/* 
   RESPONSIVE
 */
@media(max-width:992px){

    .content-grid{
        grid-template-columns:1fr;
    }

    .stats{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:768px){

    .sidebar{
        width:100%;
        height:auto;
        position:relative;
    }

    .main-content{
        width:100%;
        margin-left:0;
        padding:15px;
    }

    .stats{
        grid-template-columns:1fr;
    }

    .profile-header{
        flex-direction:column;
        align-items:flex-start;
    }

    .modal-box,
    .modal-content,
    .payment-content{
        max-width:100%;
    }
}

@media(max-width:480px){

    .box{
        padding:20px;
    }

    .header h1{
        font-size:24px;
    }

    .box h3{
        font-size:20px;
    }

    .stat-card h2{
        font-size:28px;
    }
}
</style>
</head>

<body>

<!-- == SIDEBAR = -->
<div class="sidebar">
    <div class="logo">
        <h2>LMS Portal</h2>
    </div>

    <ul class="menu">
        <li class="active"><a href="#" onclick="showDashboard()"><i class="fas fa-home"></i>Dashboard</a></li>

        <li><a href="#" onclick="showCourses()"><i class="fas fa-book-open"></i>My Courses</a></li>

        <li><a href="#" onclick="showBrowseCourses()"><i class="fas fa-search"></i>Browse Courses</a></li>

        <li><a href="#" onclick="showProgress()"><i class="fas fa-chart-line"></i>Progress</a></li>

       <li>
           <a href="#" onclick="showQuizzes()">
               <i class="fas fa-question-circle"></i>
                  Quizzes
           </a>
        </li>

        <li><a href="#" onclick="showPayment()"><i class="fas fa-credit-card"></i>Payments</a></li>
        <li><a href="#" onclick="showAttendance()"><i class="fas fa-user-check"></i>Attendance</a></li>
        <li><a href="#" onclick="showNotifications()"><i class="fas fa-bell"></i>Notifications</a></li>

        <li><a href="#" onclick="showBookmarks()">
            <i class="fas fa-bookmark"></i>Bookmarks
        </a></li>
        <li><a href="#" onclick="showRaiseTicket()"><i class="fas fa-ticket-alt"></i>Raise Ticket</a></li>
        <li><a href="#" onclick="showProfile()"><i class="fas fa-user"></i>Profile</a></li>
        <li><a href="#" onclick="showSettings()"><i class="fas fa-cog"></i>Settings</a></li>
        <li class="logout"><a href="Login.php"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
    </ul>
</div>

<!--  MAIN CONTENT  -->
<div class="main-content">

<!-- DASHBOARD SECTION -->
<div id="dashboardContent">

    <div class="header">
        <h1>Welcome Back 👋</h1>
        <p>Track your learning progress and manage LMS activities</p>
    </div>

    <!-- STATS -->
    <div class="stats">

        <div class="stat-card">
            <i class="fas fa-book"></i>
            <h2><?= $enrolled ?></h2>
            <p>Enrolled Courses</p>
        </div>

        <div class="stat-card">
            <i class="fas fa-check-circle"></i>
            <h2><?= $completed ?></h2>
            <p>Completed Courses</p>
        </div>

        <div class="stat-card">
            <i class="fas fa-spinner"></i>
            <h2><?= $ongoing ?></h2>
            <p>Ongoing Courses</p>
        </div>

        <div class="stat-card">
            <i class="fas fa-chart-pie"></i>
            <h2><?= $progress ?>%</h2>
            <p>Learning Progress</p>
        </div>

    </div>

    <!-- CONTENT -->
    <div class="content-grid">

        <!-- COURSES -->
        <div class="box">
            <h3>Continue Learning</h3>

            <?php while($row = mysqli_fetch_assoc($courses)) { ?>
                <div class="course">
                    <div class="course-info">
                        <h4><?= htmlspecialchars($row['title']) ?></h4>
                        <p>Progress: <?= $row['progress'] ?>%</p>

                        <div class="progress">
                            <div class="progress-bar" style="width:<?= $row['progress'] ?>%;"></div>
                        </div>
                    </div>
                    
                </div>
            <?php } ?>

        </div>

        <!-- ACTIVITIES -->
        <div class="box">
            <h3>Recent Activities</h3>

            <?php while($act = mysqli_fetch_assoc($activities)) { ?>
                <div class="activity">
                    <p><?= htmlspecialchars($act['message']) ?></p>
                    <span><?= $act['created_at'] ?></span>
                </div>
            <?php } ?>

        </div>

    </div>
</div>

<!-- RAISE TICKET --> 
 <div class="box" id="ticketSection" style="display:none;">
 <style>
        body{
            font-family: Arial, sans-serif;
            background:#f4f6f8;
            padding:20px;
        }

        h3{
            color:#333;
            margin-bottom:10px;
        }

        /* ===== FORM BOX ===== */
        form{
            background:white;
            padding:20px;
            max-width:500px;
            border-radius:10px;
            box-shadow:0 2px 10px rgba(0,0,0,0.1);
            margin-bottom:20px;
        }

        select, input, textarea{
            width:100%;
            padding:10px;
            margin-top:10px;
            border:1px solid #ccc;
            border-radius:5px;
            font-size:14px;
        }

        textarea{
            height:100px;
            resize:none;
        }

        button{
            width:10%;
            padding:12px;
            margin-top:15px;
            background:#007bff;
            color:white;
            border:none;
            border-radius:5px;
            cursor:pointer;
            font-size:15px;
        }

        button:hover{
            background:#0056b3;
        }

        hr{
            margin:30px 0;
            border:0;
            border-top:1px solid #ddd;
        }

        /* ===== TABLE ===== */
        table{
            width:100%;
            border-collapse:collapse;
            background:white;
            box-shadow:0 2px 10px rgba(0,0,0,0.1);
            border-radius:10px;
            overflow:hidden;
        }

        th{
            background:#333;
            color:white;
            padding:12px;
            text-align:left;
        }

        td{
            padding:12px;
            border-bottom:1px solid #eee;
        }

        tr:hover{
            background:#f1f1f1;
        }

        /* ===== STATUS COLORS ===== */
        .status-open{
            color:#ff9800;
            font-weight:bold;
        }

        .status-progress{
            color:#2196f3;
            font-weight:bold;
        }

        .status-resolved{
            color:#4caf50;
            font-weight:bold;
        }

        .status-closed{
            color:#555;
            font-weight:bold;
        }

    </style>
    <!-- CREATE TICKET -->
    <?php


;
?>

<h2>Raise Ticket</h2>

<form method="POST" action="create_ticket.php">

    <select name="type" required>
        <option value="">Select Type</option>
        <option value="academic">Academic</option>
        <option value="technical">Technical</option>
        <option value="attendance">Attendance</option>
        <option value="general">General</option>
    </select>

    <input
        type="text"
        name="subject"
        placeholder="Subject"
        required>

    <textarea
        name="message"
        placeholder="Describe your issue..."
        required></textarea>

    <button
        type="submit"
        name="submit_ticket">
        Submit Ticket
    </button>

</form>

<hr>

<h2>My Tickets</h2>

<table border="1" width="100%">

<tr>
    <th>Type</th>
    <th>Subject</th>
    <th>Status</th>
    <th>Date</th>
</tr>

<?php

$result = mysqli_query($conn,"
SELECT *
FROM tickets
WHERE user_id='$user_id'
ORDER BY id DESC
");

while($row=mysqli_fetch_assoc($result))
{
?>

<tr>

    <td><?= $row['type'] ?></td>

    <td><?= $row['subject'] ?></td>

    <td><?= strtoupper($row['status']) ?></td>

    <td><?= $row['created_at'] ?></td>

</tr>

<?php } ?>

</table>

 </div>
<!--  COURSES SECTION  -->
<div class="box" id="coursesSection" style="display:none;">

    <!-- HEADER -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px;">

        <div>
            <h3>
                <i class="fas fa-book-open"></i>
                My Courses
            </h3>

            <p style="color:#6b7280;font-size:14px;margin-top:5px;">
                Access enrolled courses, continue learning,
                track lessons, assignments, and certificates.
            </p>
        </div>

    </div>

    <!-- SUMMARY -->
    <div class="stats">

        <div class="stat-card">
            <i class="fas fa-book"></i>
            <h2><?= (int)($total_courses ?? 0) ?></h2>
            <p>Total Courses</p>
        </div>

        <div class="stat-card">
            <i class="fas fa-play-circle"></i>
            <h2><?= (int)($active_courses ?? 0) ?></h2>
            <p>Active Courses</p>
        </div>

        <div class="stat-card">
            <i class="fas fa-check-circle"></i>
            <h2><?= (int)($completed_courses ?? 0) ?></h2>
            <p>Completed Courses</p>
        </div>

        <div class="stat-card">
            <i class="fas fa-award"></i>
            <h2><?= (int)($certificates_earned ?? 0) ?></h2>
            <p>Certificates Earned</p>
        </div>

    </div>

    <!-- ACTIVE COURSES -->
    <div class="box" style="margin-top:25px;">

        <h3><i class="fas fa-laptop-code"></i> Active Courses</h3>

        <?php if (!empty($active_courses_list) && $active_courses_list->num_rows > 0) { ?>

            <?php while ($course = $active_courses_list->fetch_assoc()) { ?>

                <div class="course">

                    <div class="course-info">

                        <h4><?= htmlspecialchars($course['title'] ?? '') ?></h4>

                        <p>
                            Instructor:
                            <?= htmlspecialchars($course['instructor'] ?? 'Unknown') ?>
                            •
                            <?= (int)($course['lessons'] ?? 0) ?> Lessons
                            •
                            <?= (int)($course['progress'] ?? 0) ?>% Completed
                        </p>

                        <div class="progress">
                            <div class="progress-bar"
                                 style="width:<?= (int)($course['progress'] ?? 0) ?>%;">
                            </div>
                        </div>

                    </div>

                    <a href="course1.php?id=<?= (int)($course['id'] ?? 0) ?>" class="btn">
                        Continue
                    </a>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">
                <p>No active courses available</p>
                <span>Enroll into a course to start learning</span>
            </div>

        <?php } ?>

    </div>

    <!-- COMPLETED COURSES -->
    <div class="box" style="margin-top:25px;">

        <h3><i class="fas fa-check-double"></i> Completed Courses</h3>

        <?php if (!empty($completed_courses_list) && $completed_courses_list->num_rows > 0) { ?>

            <?php while ($completed = $completed_courses_list->fetch_assoc()) { ?>

                <div class="activity">

                    <p><?= htmlspecialchars($completed['title'] ?? '') ?></p>

                    <span>
                        Completed on
                        <?= !empty($completed['completed_at'])
                            ? date("d F Y", strtotime($completed['completed_at']))
                            : 'N/A' ?>
                    </span>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">
                <p>No completed courses yet</p>
                <span>Finish courses to see them here</span>
            </div>

        <?php } ?>

    </div>

    <!-- ASSIGNMENTS -->
    <div class="box" style="margin-top:25px;">

        <h3><i class="fas fa-tasks"></i> Assignments</h3>

        <?php if (!empty($assignments) && $assignments->num_rows > 0) { ?>

            <?php while ($assignment = $assignments->fetch_assoc()) { ?>

                <div class="activity">

                    <p><?= htmlspecialchars($assignment['title'] ?? '') ?></p>

                    <span>
                        <?= htmlspecialchars($assignment['status'] ?? '') ?>

                        <?php if (!empty($assignment['due_date'])) { ?>
                            • Due <?= date("d F Y", strtotime($assignment['due_date'])) ?>
                        <?php } ?>
                    </span>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">
                <p>No assignments available</p>
                <span>Assignments will appear here</span>
            </div>

        <?php } ?>

    </div>

    <!-- COURSE MATERIALS -->
    <div class="box" style="margin-top:25px;">

        <h3><i class="fas fa-folder-open"></i> Course Materials</h3>

        <?php if (!empty($materials) && $materials->num_rows > 0) { ?>

            <?php while ($material = $materials->fetch_assoc()) { ?>

                <div class="activity">

                    <p><?= htmlspecialchars($material['title'] ?? '') ?></p>

                    <span>
                        Downloaded <?= (int)($material['downloads'] ?? 0) ?> Times
                    </span>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">
                <p>No course materials available</p>
                <span>Materials will appear here</span>
            </div>

        <?php } ?>

    </div>

    <!-- LIVE CLASSES -->
    <div class="box" style="margin-top:25px;">

        <h3><i class="fas fa-video"></i> Upcoming Live Classes</h3>

        <?php if (!empty($live_classes) && $live_classes->num_rows > 0) { ?>

            <?php while ($live = $live_classes->fetch_assoc()) { ?>

                <div class="activity">

                    <p><?= htmlspecialchars($live['title'] ?? '') ?></p>

                    <span>
                        <?= !empty($live['schedule_time'])
                            ? date("d F Y • h:i A", strtotime($live['schedule_time']))
                            : 'N/A' ?>
                    </span>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">
                <p>No live classes scheduled</p>
                <span>Upcoming classes will appear here</span>
            </div>

        <?php } ?>

    </div>
</div>


<!-- quizes -->   

<div class="box" id="quizzesSection" style="display:none;">

<!-- HEADER -->
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px;">

    <div>
        <h3><i class="fas fa-question-circle"></i> Quizzes & Assessments</h3>
        <p style="color:#6b7280;font-size:14px;margin-top:5px;">
            Take quizzes, monitor scores, and improve performance.
        </p>
    </div>

    <button class="btn">
        <i class="fas fa-play"></i> Start New Quiz
    </button>

</div>

<!-- SUMMARY -->
<div class="stats">

<div class="stat-card">
    <i class="fas fa-file-alt"></i>
    <h2><?= $total_quizzes ?></h2>
    <p>Total Quizzes</p>
</div>

<div class="stat-card">
    <i class="fas fa-check-circle"></i>
    <h2><?= $passed ?></h2>
    <p>Passed</p>
</div>

<div class="stat-card">
    <i class="fas fa-times-circle"></i>
    <h2><?= $failed ?></h2>
    <p>Failed</p>
</div>

<div class="stat-card">
    <i class="fas fa-chart-line"></i>
    <h2><?= $avgScore ?>%</h2>
    <p>Average Score</p>
</div>
</div>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =====================================================
SUBMIT QUIZ + SAVE TO DATABASE
===================================================== */

if(isset($_POST['submit_quiz']))
{
    $quiz_id = intval($_POST['quiz_id']);

    $quiz_query = mysqli_query($conn,"
        SELECT *
        FROM quizzes
        WHERE id='$quiz_id'
    ");

    if(mysqli_num_rows($quiz_query) > 0)
    {
        $quiz_data = mysqli_fetch_assoc($quiz_query);

        $questions_query = mysqli_query($conn,"
            SELECT *
            FROM quiz_questions
            WHERE quiz_id='$quiz_id'
        ");

        $total = 0;
        $correct = 0;

        while($question = mysqli_fetch_assoc($questions_query))
        {
            $total++;

            $question_id = $question['id'];

            $student_answer =
            $_POST['answer'][$question_id] ?? '';

            if($student_answer == $question['correct_answer'])
            {
                $correct++;
            }
        }

        $score = ($total > 0)
        ? round(($correct / $total) * 100)
        : 0;

        $status =
        ($score >= $quiz_data['passing_score'])
        ? 'passed'
        : 'failed';

        $student_id = $_SESSION['student_id'];

        /* CHECK ATTEMPT */
        $check = mysqli_query($conn,"
            SELECT id FROM quiz_results
            WHERE student_id='$student_id'
            AND quiz_id='$quiz_id'
        ");

        if(mysqli_num_rows($check) > 0)
        {
            mysqli_query($conn,"
                UPDATE quiz_results
                SET
                    score='$score',
                    status='$status',
                    completed_at=NOW()
                WHERE student_id='$student_id'
                AND quiz_id='$quiz_id'
            ");
        }
        else
        {
            mysqli_query($conn,"
                INSERT INTO quiz_results
                (
                    student_id,
                    quiz_id,
                    score,
                    status,
                    completed_at
                )
                VALUES
                (
                    '$student_id',
                    '$quiz_id',
                    '$score',
                    '$status',
                    NOW()
                )
            ");
        }

        /* SAFE REDIRECT (NO NAVIGATION BREAK) */
        $_SESSION['quiz_success'] = "Quiz Submitted Successfully! Score: $score%";

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

/* =====================================================
FETCH DATA
===================================================== */

$all_quizzes = [];
$qres = mysqli_query($conn,"SELECT * FROM quizzes");
while($row = mysqli_fetch_assoc($qres))
{
    $all_quizzes[] = $row;
}

$all_questions = [];
$qres2 = mysqli_query($conn,"SELECT * FROM quiz_questions");
while($row = mysqli_fetch_assoc($qres2))
{
    $all_questions[] = $row;
}
?>

<!-- =====================================================
SUCCESS MESSAGE
===================================================== -->

<?php if(isset($_SESSION['quiz_success'])) { ?>
<script>
alert("<?= $_SESSION['quiz_success'] ?>");
</script>
<?php unset($_SESSION['quiz_success']); } ?>

<!-- =====================================================
AVAILABLE QUIZZES
===================================================== -->

<div class="box" style="margin-top:25px;">

    <h3>
        <i class="fas fa-pencil-alt"></i>
        Available Quizzes
    </h3>

    <?php foreach($all_quizzes as $q) { ?>

    <div class="course">

        <div class="course-info">

            <h4><?= htmlspecialchars($q['title']) ?></h4>

            <p>
                <?= $q['questions'] ?> Questions •
                <?= $q['duration'] ?> Minutes •
                Passing Score: <?= $q['passing_score'] ?>%
            </p>

        </div>

        <button class="btn" onclick="openQuiz(<?= $q['id'] ?>)">
            Start Quiz
        </button>

    </div>

    <?php } ?>

</div>

<!-- =====================================================
QUIZ MODAL
===================================================== -->

<div id="quizModal" style="
display:none;
position:fixed;
top:0;
left:0;
width:100%;
height:100%;
background:rgba(0,0,0,0.7);
z-index:9999;
overflow:auto;
padding:30px;">

    <div style="
    background:#fff;
    max-width:900px;
    margin:auto;
    border-radius:15px;
    padding:25px;
    position:relative;">

        <button onclick="closeQuiz()"
        style="
        position:absolute;
        right:20px;
        top:20px;
        width:35px;
        height:35px;
        border:none;
        border-radius:50%;
        background:red;
        color:#fff;
        cursor:pointer;">
            X
        </button>

        <div id="quizContainer"></div>

    </div>
</div>

<!-- =====================================================
STYLE
===================================================== -->

<style>
.quiz-option{
    display:block;
    background:#fff;
    border:1px solid #ddd;
    padding:14px;
    border-radius:10px;
    margin-bottom:15px;
    cursor:pointer;
}

.quiz-option:hover{
    background:#f3f4f6;
}

.quiz-option input{
    margin-right:10px;
}
</style>

<!-- =====================================================
SCRIPT
===================================================== -->

<script>

const quizzes = JSON.parse(`<?= json_encode($all_quizzes, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>`);
const questions = JSON.parse(`<?= json_encode($all_questions, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>`);

let countdown;

/* OPEN QUIZ */
function openQuiz(id)
{
    document.body.style.overflow = "hidden";
    document.getElementById("quizModal").style.display = "block";

    let quiz = quizzes.find(q => q.id == id);
    let quizQuestions = questions.filter(q => q.quiz_id == id);

    if(!quiz) return alert("Quiz not found");

    let html = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div>
            <h2>${quiz.title}</h2>
            <p>${quiz.questions} Questions • ${quiz.duration} Minutes • Passing: ${quiz.passing_score}%</p>
        </div>
        <div id="timer" style="background:#ef4444;color:#fff;padding:10px 15px;border-radius:8px;">
            ${quiz.duration}:00
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="quiz_id" value="${quiz.id}">
    `;

    let i = 1;

    quizQuestions.forEach(q => {
        html += `
        <div style="padding:15px;border:1px solid #ddd;margin-bottom:15px;border-radius:10px;">
            <h4>Q${i++}: ${q.question}</h4>

            <label class="quiz-option"><input type="radio" name="answer[${q.id}]" value="A"> ${q.option_a}</label>
            <label class="quiz-option"><input type="radio" name="answer[${q.id}]" value="B"> ${q.option_b}</label>
            <label class="quiz-option"><input type="radio" name="answer[${q.id}]" value="C"> ${q.option_c}</label>
            <label class="quiz-option"><input type="radio" name="answer[${q.id}]" value="D"> ${q.option_d}</label>
        </div>`;
    });

    html += `
        <button class="btn" name="submit_quiz">Submit Quiz</button>
    </form>`;

    document.getElementById("quizContainer").innerHTML = html;

    startTimer(parseInt(quiz.duration));
}

/* TIMER */
function startTimer(duration)
{
    clearInterval(countdown);

    let m = duration;
    let s = 0;

    countdown = setInterval(() =>
    {
        const timer = document.getElementById("timer");

        if(s === 0)
        {
            if(m === 0)
            {
                clearInterval(countdown);
                document.querySelector("#quizContainer form").submit();
                return;
            }
            m--; s = 59;
        }
        else s--;

        timer.innerHTML =
        String(m).padStart(2,'0')+":"+String(s).padStart(2,'0');

    },1000);
}

/* CLOSE */
function closeQuiz()
{
    clearInterval(countdown);
    document.getElementById("quizModal").style.display = "none";
    document.body.style.overflow = "auto";
}

</script>

<!-- RECENT RESULTS --> 
<div class="box" style="margin-top:25px;">
<h3><i class="fas fa-poll"></i> Recent Results</h3>

<?php while($r = mysqli_fetch_assoc($results)) { ?>
    <div class="activity">
        <p><?= htmlspecialchars($r['quiz_title']) ?></p>
        <span>Score: <?= $r['score'] ?>% • <?= ucfirst($r['status']) ?></span>
    </div>
<?php } ?>
</div>

<!-- QUIZ HISTORY -->
<div class="box" style="margin-top:25px;">
<h3><i class="fas fa-history"></i> Quiz History</h3>

<?php while($h = mysqli_fetch_assoc($history)) { ?>
    <div class="activity">
        <p><?= htmlspecialchars($h['title']) ?></p>
        <span>Completed on <?= $h['completed_at'] ?></span>
    </div>
<?php } ?>
</div>

<!-- LEADERBOARD / ACHIEVEMENTS -->
<div class="box" style="margin-top:25px;">
<h3><i class="fas fa-trophy"></i> Top Performance</h3>

<?php while($a = mysqli_fetch_assoc($achievements)) { ?>
    <div class="activity">
        <p><?= htmlspecialchars($a['title']) ?></p>
        <span><?= htmlspecialchars($a['value']) ?></span>
    </div>
<?php } ?>
</div>

<!-- CERTIFICATIONS -->
<div class="box" style="margin-top:25px;">
<h3><i class="fas fa-award"></i> Certification Exams</h3>

<?php while($c = mysqli_fetch_assoc($certs)) { ?>
    <div class="activity">
        <p><?= htmlspecialchars($c['title']) ?></p>
        <span><?= $c['status'] ?></span>
    </div>
<?php } ?>
</div>

</div>

<!--      PROGRESS SECTION -->

<div class="box" id="progressSection" style="display:none;">

    <!-- HEADER -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px;">

        <div>
            <h3>
                <i class="fas fa-chart-line"></i>
                Learning Progress
            </h3>

            <p style="color:#6b7280;font-size:14px;margin-top:5px;">
                Track your academic performance, completed lessons,
                quizzes, certificates, and learning analytics.
            </p>
        </div>

        <button class="btn">
            <i class="fas fa-download"></i>
            Export Report
        </button>

    </div>

    <!-- SUMMARY CARDS -->
    <div class="stats">

        <div class="stat-card">
            <i class="fas fa-book-open"></i>
            <h2><?= $progressTotalCourses ?></h2>
            <p>Enrolled Courses</p>
        </div>

        <div class="stat-card">
            <i class="fas fa-check-circle"></i>
            <h2><?= $progressCompletedCourses ?></h2>
            <p>Completed Courses</p>
        </div>

        <div class="stat-card">
            <i class="fas fa-spinner"></i>
            <h2><?= $progressOngoingCourses ?></h2>
            <p>Ongoing Courses</p>
        </div>

        <div class="stat-card">
            <i class="fas fa-chart-pie"></i>
            <h2><?= $overallProgress ?>%</h2>
            <p>Overall Progress</p>
        </div>

    </div>

    <!-- COURSE PROGRESS -->
    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-laptop-code"></i>
            Course Progress
        </h3>

        <?php if(isset($courseProgress) && mysqli_num_rows($courseProgress) > 0){ ?>

            <?php while($course = mysqli_fetch_assoc($courseProgress)){ ?>

                <div class="course">

                    <div class="course-info">

                        <h4>
                            <?= htmlspecialchars($course['title'] ?? '') ?>
                        </h4>

                        <p>
                            <?= (int)($course['progress'] ?? 0) ?>% Completed
                        </p>

                        <div class="progress">
                            <div class="progress-bar"
                                 style="width:<?= (int)($course['progress'] ?? 0) ?>%;">
                            </div>
                        </div>

                    </div>

                    <span style="color:<?= ($course['status'] ?? '') === 'completed' ? '#2563eb' : '#10b981' ?>;font-weight:bold;">
                        <?= ucfirst($course['status'] ?? 'ongoing') ?>
                    </span>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">
                <p>No course progress available</p>
                <span>Enroll in courses to track progress</span>
            </div>

        <?php } ?>

    </div>

    <!-- QUIZ PERFORMANCE -->
    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-question-circle"></i>
            Quiz Performance
        </h3>

        <?php if(isset($quizPerformance) && mysqli_num_rows($quizPerformance) > 0){ ?>

            <?php while($quiz = mysqli_fetch_assoc($quizPerformance)){ ?>

                <div class="activity">

                    <p>
                        <?= htmlspecialchars($quiz['quiz_title'] ?? '') ?>
                    </p>

                    <span>
                        Score: <?= (int)($quiz['score'] ?? 0) ?>%
                        |
                        <?= ucfirst($quiz['status'] ?? '') ?>
                        |
                        <?= !empty($quiz['created_at']) ? date("d M Y", strtotime($quiz['created_at'])) : '' ?>
                    </span>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">
                <p>No quiz results available</p>
                <span>Complete quizzes to see performance</span>
            </div>

        <?php } ?>

    </div>

    <!-- CERTIFICATES -->
    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-award"></i>
            Certificates
        </h3>

        <?php if(isset($progressCertificates) && mysqli_num_rows($progressCertificates) > 0){ ?>

            <?php while($cert = mysqli_fetch_assoc($progressCertificates)){ ?>

                <div class="activity">

                    <p><?= htmlspecialchars($cert['title'] ?? '') ?></p>

                    <span>
                        Issued on
                        <?= !empty($cert['issued_at']) ? date("d F Y", strtotime($cert['issued_at'])) : '' ?>
                    </span>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">
                <p>No certificates earned yet</p>
                <span>Complete courses to unlock certificates</span>
            </div>

        <?php } ?>

    </div>

    <!-- LEARNING ANALYTICS -->
    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-chart-bar"></i>
            Learning Analytics
        </h3>

        <div class="activity">
            <p>Total Study Hours</p>
            <span><?= (int)($analytics['study_hours'] ?? 0) ?> Hours</span>
        </div>

        <div class="activity">
            <p>Lessons Completed</p>
            <span><?= (int)($analytics['lessons_completed'] ?? 0) ?> Lessons</span>
        </div>

        <div class="activity">
            <p>Assignments Submitted</p>
            <span><?= (int)($analytics['assignments_submitted'] ?? 0) ?> Assignments</span>
        </div>

        <div class="activity">
            <p>Average Daily Study Time</p>
            <span>
                <?php
                    $study = (int)($analytics['study_hours'] ?? 0);
                    echo round($study / 30, 1);
                ?> Hours Per Day
            </span>
        </div>

    </div>

    <!-- RECENT ACHIEVEMENTS -->
    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-trophy"></i>
            Recent Achievements
        </h3>

        <?php if(!empty($progressAchievements)){ ?>

            <?php foreach($progressAchievements as $a){ ?>

                <div class="activity">
                    <p><?= htmlspecialchars($a['title']) ?></p>
                    <span><?= htmlspecialchars($a['description']) ?></span>
                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">
                <p>No achievements yet</p>
                <span>Continue learning to unlock achievements</span>
            </div>

        <?php } ?>

    </div>

</div>


<!--  BROWSE COURSES SECTION -->

<div class="box" id="browseCoursesSection" style="display:none;">

    <!-- HEADER -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px;">

        <div>
            <h3><i class="fas fa-search"></i> Browse Courses</h3>

            <p style="color:#6b7280;font-size:14px;margin-top:5px;">
                Explore trending courses, discover new skills, and enroll in professional learning programs.
            </p>
        </div>

    </div>

    <!-- SEARCH -->
    <form method="GET" style="display:flex;gap:15px;flex-wrap:wrap;margin-bottom:25px;">

        <input type="text"
               name="search"
               value="<?= htmlspecialchars($search) ?>"
               placeholder="Search courses..."
               style="flex:1;padding:14px;border:1px solid #d1d5db;border-radius:10px;font-size:15px;">

        <button type="submit" class="btn">
            <i class="fas fa-search"></i> Search
        </button>

    </form>

    <!-- CATEGORIES -->
    <div class="box">

        <h3><i class="fas fa-layer-group"></i> Categories</h3>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:15px;">

            <?php foreach($categories as $category){ ?>
                <button class="btn"><?= htmlspecialchars($category) ?></button>
            <?php } ?>

        </div>
    </div>

    <!-- FEATURED COURSES -->
    <div class="box" style="margin-top:25px;">

        <h3><i class="fas fa-star"></i> Featured Courses</h3>

        <?php if(count($browseCoursesData) > 0){ ?>

            <?php foreach($browseCoursesData as $course){ ?>

                <div class="course">

                    <div class="course-info">

                        <h4><?= htmlspecialchars($course['title']) ?></h4>

                        <p><?= htmlspecialchars($course['description']) ?></p>

                        <p style="margin-top:8px;color:#2563eb;font-weight:bold;">
                            KES <?= number_format($course['price']) ?>
                        </p>

                        <p style="margin-top:8px;color:#6b7280;font-size:14px;">

                            Instructor: <?= htmlspecialchars($course['instructor']) ?>
                            • <?= (int)$course['lessons'] ?> Lessons
                            • <?= (int)$course['enrolled_students'] ?> Students

                        </p>

                    </div>

                    <a href="enroll.php?course_id=<?= $course['id'] ?>" class="btn">
                        Enroll Now
                    </a>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">
                <p>No courses found</p>
                <span>Try searching with another keyword</span>
            </div>

        <?php } ?>

    </div>

    <!-- TRENDING -->
    <div class="box" style="margin-top:25px;">

        <h3><i class="fas fa-fire"></i> Trending Courses</h3>

        <?php if(mysqli_num_rows($trendingCourses) > 0){ ?>

            <?php while($trend = mysqli_fetch_assoc($trendingCourses)){ ?>

                <div class="activity">
                    <p><?= htmlspecialchars($trend['title']) ?></p>
                    <span><?= number_format($trend['enrolled_students']) ?> Students Enrolled</span>
                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">
                <p>No trending courses available</p>
                <span>Courses will appear automatically</span>
            </div>

        <?php } ?>

    </div>

    <!-- TOP INSTRUCTORS -->
    <div class="box" style="margin-top:25px;">

    <h3><i class="fas fa-chalkboard-teacher"></i> Top Instructors</h3>

    <?php if ($topInstructors && mysqli_num_rows($topInstructors) > 0) { ?>

        <?php while ($instructor = mysqli_fetch_assoc($topInstructors)) { ?>

            <div class="activity">

                <p>
                    <?= htmlspecialchars($instructor['full_name']) ?>
                </p>

                <span>

                    <?= htmlspecialchars($instructor['specialization'] ?? 'No specialization') ?>

                    • <?= htmlspecialchars($instructor['qualification'] ?? 'No qualification') ?>

                    • <?= (int)$instructor['experience_years'] ?> yrs experience

                    • <?= (int)$instructor['courses_count'] ?> courses

                </span>

            </div>

        <?php } ?>

    <?php } else { ?>

        <div class="activity">
            <p>No instructors available</p>
            <span>Instructor data will appear here</span>
        </div>

    <?php } ?>

</div>

    <!-- BENEFITS -->
    <div class="box" style="margin-top:25px;">

        <h3><i class="fas fa-gift"></i> Why Learn With Us?</h3>

        <?php foreach($courseBenefits as $benefit){ ?>

            <div class="activity">
                <p><?= htmlspecialchars($benefit['title']) ?></p>
                <span><?= htmlspecialchars($benefit['description']) ?></span>
            </div>

        <?php } ?>

    </div>

</div>


<!-- ATTENDANCE SECTION --> 
<div class="box" id="attendanceSection" style="display:none;">
<style>
        body{
            font-family: Arial;
            background:#f4f6f8;
            padding:20px;
        }
        select, button{
            width:100%;
            padding:10px;
            margin-top:10px;
        }
        button{
            background:green;
            color:white;
            border:none;
            cursor:pointer;
        }
        table{
            margin-top:30px;
            width:100%;
            border-collapse:collapse;
            background:white;
        }
        th, td{
            padding:10px;
            border:1px solid #ddd;
            text-align:left;
        }
        th{
            background:#333;
            color:white;
        }
    </style>
    <div class="box">
    <h2>Sign Attendance</h2>
    <form method="POST">
        <label>Select Course</label>
        <select name="course_id" required>
            <option value="">-- Choose Course --</option>
            <?php
            $courses = mysqli_query($conn, "SELECT * FROM courses");

            while($c = mysqli_fetch_assoc($courses)) {
                echo "<option value='{$c['id']}'>{$c['title']}</option>";
            }
            ?>
        </select>
        <button type="submit" name="sign_attendance">
            Sign Attendance
        </button>
    </form>
</div>

<!-- =========================
     ATTENDANCE HISTORY
========================= -->

<h2>My Attendance History</h2>

<table>
<tr>
    <th>Course</th>
    <th>Status</th>
    <th>Date</th>
</tr>

<?php
$result = mysqli_query($conn, "
    SELECT attendance.*, courses.title
    FROM attendance
    JOIN courses ON attendance.course_id = courses.id
    WHERE attendance.user_id='$user_id'
    ORDER BY attendance.signed_at DESC
");

while($row = mysqli_fetch_assoc($result)) {
?>

<tr>
    <td><?= $row['title'] ?></td>
    <td><?= $row['status'] ?></td>
    <td><?= $row['signed_at'] ?></td>
</tr>

<?php } ?>

</table>

</div>




<!-- SETTING SETCTION  --->
<div class="box" id="settingsSection" style="display:none;margin-left:20px;">

    <h3>
        <i class="fas fa-cog"></i>
        Settings
    </h3>

    <div class="activity">
        <p>Account Settings</p>
        <span>Manage your account details (<?= htmlspecialchars($profile['email'] ?? 'No Email') ?>)</span>
    </div>

    <div class="activity">
        <p>Password & Security</p>
        <span>
            Last updated: 
            <?= !empty($settingsRow['password_updated_at']) 
                ? date("d M Y", strtotime($settingsRow['password_updated_at'])) 
                : "Never updated" ?>
        </span>
    </div>

    <div class="activity">
        <p>Notification Preferences</p>
        <span>
            Email: <?= ($settingsRow['email_notifications'] ? 'Enabled' : 'Disabled') ?> |
            SMS: <?= ($settingsRow['sms_notifications'] ? 'Enabled' : 'Disabled') ?>
        </span>
    </div>

    <div class="activity">
        <p>Theme Settings</p>
        <span>
            Current theme: <?= ucfirst($settingsRow['theme']) ?>
        </span>
    </div>

</div>


<!-- TICKET SECTION --> 
<div class="box" id="ticketSection" style="display:none;">

<?php if(isset($role) && $role != 'admin'): ?>

<div class="ticket-container">

    <!-- Raise Ticket -->
    <div class="ticket-box">
        <h3>🎟️ Raise Ticket</h3>

        <form method="POST" action="create_ticket.php">

            <label>Ticket Type</label>
            <select name="type" required>
                <option value="">-- Select Type --</option>
                <option value="academic">📚 Academic</option>
                <option value="technical">💻 Technical</option>
                <option value="attendance">📊 Attendance</option>
                <option value="general">ℹ️ General</option>
            </select>

            <label>Subject</label>
            <input type="text" name="subject" required>

            <label>Message</label>
            <textarea name="message" required></textarea>

            <button type="submit">Submit Ticket</button>

        </form>
    </div>

    <hr>

    <!-- Ticket History -->
    <div class="ticket-box">

        <h3>📄 My Tickets</h3>

        <table width="100%" border="1" cellspacing="0" cellpadding="10">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>

            <?php

            $user_id = intval($_SESSION['user_id']);

            $stmt = $conn->prepare("
                SELECT *
                FROM tickets
                WHERE user_id = ?
                ORDER BY created_at DESC
            ");

            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $tickets = $stmt->get_result();

            if($tickets && $tickets->num_rows > 0):

                while($ticket = $tickets->fetch_assoc()):

                    $status = '';

                    switch($ticket['status']){

                        case 'open':
                            $status = "<span style='color:orange;font-weight:bold;'>🟡 Open</span>";
                            break;

                        case 'in_progress':
                            $status = "<span style='color:blue;font-weight:bold;'>🔵 In Progress</span>";
                            break;

                        case 'resolved':
                            $status = "<span style='color:green;font-weight:bold;'>🟢 Resolved</span>";
                            break;

                        case 'closed':
                            $status = "<span style='color:gray;font-weight:bold;'>⚫ Closed</span>";
                            break;

                        default:
                            $status = htmlspecialchars($ticket['status']);
                    }
            ?>

                <tr>
                    <td><?= htmlspecialchars($ticket['type']) ?></td>
                    <td><?= htmlspecialchars($ticket['subject']) ?></td>
                    <td><?= $status ?></td>
                    <td><?= htmlspecialchars($ticket['created_at']) ?></td>
                </tr>

            <?php
                endwhile;

            else:
            ?>

                <tr>
                    <td colspan="4" style="text-align:center;">
                        No tickets found.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>

</div>



<!---  PAYMENT SECTION  -->
<div class="box" id="paymentsSection" style="display:none;">

<!-- HEADER -->
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;margin-bottom:25px;">

    <div>
        <h3><i class="fas fa-credit-card"></i> Payments & Billing</h3>
        <p style="color:#6b7280;font-size:14px;margin-top:5px;">
            Manage payments, invoices, and methods.
        </p>
    </div>

    <button class="btn" onclick="openPaymentModal()">
       <i class="fas fa-plus"></i> Make Payment
    </button>

</div>

<!-- SUMMARY -->
<div class="stats">

    <div class="stat-card">
        <i class="fas fa-wallet"></i>
        <h2>KES <?= number_format($totalPaid) ?></h2>
        <p>Total Paid</p>
    </div>

    <div class="stat-card">
        <i class="fas fa-clock"></i>
       <h2>KES <?= number_format($pendingPayments) ?></h2>
        <p>Pending Payments</p>
    </div>

    <div class="stat-card">
        <i class="fas fa-receipt"></i>
        <h2><?= mysqli_num_rows($invoices) ?></h2>
        <p>Invoices</p>
    </div>

    <div class="stat-card">
        <i class="fas fa-check-circle"></i>
        <h2><?= mysqli_num_rows($transactions) ?></h2>
        <p>Transactions</p>
    </div>

</div>

<!-- PAYMENT METHODS -->
<div class="box" style="margin-top:25px;">
<h3><i class="fas fa-money-check-alt"></i> Payment Methods</h3>

<?php if($paymentMethods && mysqli_num_rows($paymentMethods) > 0){ ?>
    <?php while($method = mysqli_fetch_assoc($paymentMethods)){ ?>

        <div class="activity">
            <p><?= htmlspecialchars($method['method_type']) ?></p>
            <span><?= htmlspecialchars($method['account_number']) ?></span>
        </div>

    <?php } ?>
<?php } else { ?>
    <div class="activity">
        <p>No payment methods added</p>
        <span>Add M-Pesa or Card to continue</span>
    </div>
<?php } ?>

</div>

<!-- TRANSACTIONS -->
<div class="box" style="margin-top:25px;">
<h3><i class="fas fa-receipt"></i> Transactions</h3>

<?php if($transactions && mysqli_num_rows($transactions) > 0){ ?>
    <?php while($t = mysqli_fetch_assoc($transactions)){ ?>

        <div class="activity">
            <p><?= htmlspecialchars($t['course_id']) ?></p>
            <span>
                KES <?= number_format($t['amount']) ?> |
                <?= ucfirst($t['status']) ?> |
                <?= date("d M Y", strtotime($t['created_at'])) ?>
            </span>
        </div>

    <?php } ?>
<?php } else { ?>
    <div class="activity">
        <p>No transactions yet</p>
        <span>Payments will appear here</span>
    </div>
<?php } ?>

</div>

<!-- INVOICES -->
<div class="box" style="margin-top:25px;">
<h3><i class="fas fa-file-invoice"></i> Invoices</h3>

<?php if($invoices && mysqli_num_rows($invoices) > 0){ ?>
    <?php while($inv = mysqli_fetch_assoc($invoices)){ ?>

        <div class="activity">
            <p><?= htmlspecialchars($inv['invoice_no']) ?></p>
            <span>
                <?= htmlspecialchars($inv['description']) ?> |
                <?= ucfirst($inv['status']) ?>
            </span>
        </div>

    <?php } ?>
<?php } else { ?>
    <div class="activity">
        <p>No invoices available</p>
        <span>Invoices will appear after payment</span>
    </div>
<?php } ?>

</div>

</div>
<!--  BOOKMARKS SECTION  -->
<div class="box" id="bookmarksSection" style="display:none;">

    <h3>
        <i class="fas fa-bookmark"></i>
        My Bookmarks
    </h3>

    <?php if($bookmarks && mysqli_num_rows($bookmarks) > 0) { ?>

        <?php while($bm = mysqli_fetch_assoc($bookmarks)) { ?>
            <div class="activity">
                <p><?= htmlspecialchars($bm['title']) ?></p>
                <span>
                    Bookmarked <?= date("F j, Y H:i", strtotime($bm['created_at'])) ?>
                </span>
            </div>
        <?php } ?>

    <?php } else { ?>

        <div class="activity">
            <p>No bookmarks yet</p>
            <span>Start bookmarking lessons or videos</span>
        </div>

    <?php } ?>

</div>



<!--  NOTIFICATIONS SECTION  -->

<div class="box" id="notificationsSection" style="display:none;">

    <h3>
        <i class="fas fa-bell"></i>
        Notifications & Announcements
    </h3>

    <?php if($notifications && mysqli_num_rows($notifications) > 0){ ?>

        <?php while($note = mysqli_fetch_assoc($notifications)){ ?>

            <div class="activity">

                <p>
                    <?php if($note['type'] == 'announcement'){ ?>
                        <i class="fas fa-bullhorn"></i>
                    <?php } else { ?>
                        <i class="fas fa-bell"></i>
                    <?php } ?>

                    <?= htmlspecialchars($note['message']) ?>
                </p>

                <span>
                    <?= date("F j, Y g:i A", strtotime($note['created_at'])) ?>
                </span>

            </div>

        <?php } ?>

    <?php } else { ?>

        <div class="activity">
            <p>No notifications available</p>
            <span>You're all caught up 🎉</span>
        </div>

    <?php } ?>

</div>

<!--  PROFILE SECTION  -->

<div class="profile-box" id="profileSection" style="display:none;">

    <div class="profile-header">

        <!-- PROFILE IMAGE -->

        <div class="profile-image">

<?php
$image = $profile['profile_image'] ?? '';
$src = "https://via.placeholder.com/120";

/* =========================
   SECURITY CHECKS
========================= */
if (!empty($image)) {

    // 1. Prevent script injection
    $image = trim($image);
    $image = strip_tags($image);

    // 2. If it's a valid URL (cloud image)
    if (filter_var($image, FILTER_VALIDATE_URL)) {

        // Only allow safe protocols
        if (preg_match('/^https?:\/\//', $image)) {
            $src = $image;
        }

    } 
    // 3. Local file validation
    else {

        // Prevent directory traversal
        $image = basename($image);

        // Allowed extensions only
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

        $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed_ext)) {

            $file_path = "uploads/" . $image;

            // Ensure file exists before showing
            if (file_exists($file_path)) {
                $src = $file_path;
            }
        }
    }
}
?>

<img src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" alt="Profile Image">

</div>


        <!-- PROFILE INFO -->

        <div class="profile-info">

            <h2>
                <?= htmlspecialchars($profile['full_name']) ?>
            </h2>

            <p>Student Account</p>
        </div>

    </div>

    <!--  PROFILE DETAILS  -->

    <div class="profile-details">

        <!-- FULL NAME -->

        <div class="detail-card">

            <h4>
                <i class="fas fa-user"></i>
                Full Name
            </h4>

            <p>
                <?= htmlspecialchars($profile['full_name']) ?>
            </p>

        </div>

        <!-- EMAIL -->

        <div class="detail-card">

            <h4>
                <i class="fas fa-envelope"></i>
                Email Address
            </h4>

            <p>
                <?= htmlspecialchars($profile['email']) ?>
            </p>

        </div>

        <!-- PHONE -->

        <div class="detail-card">

            <h4>
                <i class="fas fa-phone"></i>
                Phone Number
            </h4>

            <p>
                <?= htmlspecialchars($profile['phone']) ?>
            </p>

        </div>

        <!-- LOCATION -->

        <div class="detail-card">

            <h4>
                <i class="fas fa-map-marker-alt"></i>
                Location
            </h4>

            <p>
                <?= htmlspecialchars($profile['location']) ?>
            </p>

        </div>

        <!-- JOIN DATE -->

        <div class="detail-card">

            <h4>
                <i class="fas fa-calendar"></i>
                Joined Date
            </h4>

            <p>
                <?= date("d F Y", strtotime($profile['created_at'])) ?>
            </p>

        </div>

        <!-- ENROLLED COURSES -->

        <div class="detail-card">

            <h4>
                <i class="fas fa-graduation-cap"></i>
                Enrolled Courses
            </h4>

            <p>
                <?= $total_courses ?> Courses
            </p>

        </div>

        <!-- BIO -->

        <div class="detail-card full-width">

            <h4>
                <i class="fas fa-info-circle"></i>
                Bio
            </h4>

            <p>

                <?php
                if(!empty($profile['bio'])){
                    echo nl2br(htmlspecialchars($profile['bio']));
                } else {
                    echo "No bio added yet.";
                }
                ?>

            </p>

        </div>

    </div>

    <!--  ACTION BUTTONS  -->

<div class="profile-actions">

<!-- UPDATE PROFILE -->
<button class="action-btn primary"
        onclick="openProfileModal()">

    <i class="fas fa-user-edit"></i>
    Update Profile

</button>

<!-- CHANGE PASSWORD -->
<button class="action-btn success"
        onclick="openPasswordModal()">

    <i class="fas fa-lock"></i>
    Change Password

</button>

<!-- UPLOAD PHOTO -->
<button class="action-btn dark"
        onclick="openPhotoModal()">

    <i class="fas fa-image"></i>
    Upload Photo

</button>

</div>
        </a>

    </div>

</div>



<!-- PAYMENT MODAL -->
<div id="paymentModal" class="payment-modal">

    <div class="payment-content">

        <span class="close-payment" onclick="closePaymentModal()">&times;</span>

        <h3>Course Payment</h3>

        <form method="POST">

            <label>Course</label>
            <select name="course_id" required>

                <option value="">Select Course</option>

                <?php
                $courseQuery = mysqli_query($conn,"SELECT * FROM courses WHERE course_type='Paid'");
                while($c = mysqli_fetch_assoc($courseQuery)){
                ?>
                    <option value="<?= $c['id'] ?>">
                        <?= htmlspecialchars($c['title']) ?>
                        (KES <?= number_format($c['price']) ?>)
                    </option>
                <?php } ?>

            </select>

            <label>Payment Method</label>

            <select name="payment_method" id="paymentMethod" onchange="togglePaymentFields()" required>
                <option value="">Select Method</option>
                <option value="mpesa">M-Pesa</option>
                <option value="card">Card</option>
                <option value="bank_transfer">Bank Transfer</option>
            </select>

            <div id="mpesaField">

                <label>M-Pesa Number</label>
                <input type="text"
                       name="payer_phone"
                       placeholder="07XXXXXXXX">

            </div>

            <label>Amount</label>
            <input type="number"
                   name="amount"
                   required>

            <button type="submit"
                    name="submit_payment"
                    class="btn"
                    style="width:100%;margin-top:15px;">

                Pay & Enroll

            </button>

        </form>

    </div>

</div>
<!-- PROFILE MODAL -->
<div class="profile-modal" id="profileModal">

    <div class="modal-content">

        <div class="modal-header">
            <h3>
                <i class="fas fa-user-edit"></i>
                Update Profile
            </h3>

            <span class="close-btn" onclick="closeProfileModal()">
                &times;
            </span>
        </div>

        <form method="POST" enctype="multipart/form-data">

            <!-- CSRF TOKEN (IMPORTANT FIX) -->
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

            <div class="form-group">
                <label>Full Name</label>
                <input type="text"
                       name="full_name"
                       value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email"
                       name="email"
                       value="<?= htmlspecialchars($profile['email'] ?? '') ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="text"
                       name="phone"
                       value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Location</label>
                <input type="text"
                       name="location"
                       value="<?= htmlspecialchars($profile['location'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Bio</label>
                <textarea name="bio"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Profile Photo</label>
                <input type="file" name="photo">
            </div>

            <button type="submit" name="update_profile" class="save-btn">
                <i class="fas fa-save"></i>
                Save Changes
            </button>

        </form>

    </div>

</div>



<!-- PASSWORD MODAL -->
<div class="modal" id="passwordModal">

    <div class="modal-box">

        <div class="modal-header">
            <h3>
                <i class="fas fa-lock"></i>
                Change Password
            </h3>

            <span onclick="closeModal('passwordModal')" class="close">
                &times;
            </span>
        </div>

        <form method="POST">

            <div class="form-group">
                <label>Current Password</label>
                <input type="password"
                       name="current_password"
                       required>
            </div>

            <div class="form-group">
                <label>New Password</label>
                <input type="password"
                       name="new_password"
                       required>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password"
                       name="confirm_password"
                       required>
            </div>

            <button type="submit" name="change_password" class="save-btn">
                <i class="fas fa-key"></i>
                Update Password
            </button>

        </form>

    </div>

</div>

<!-- PHOTO MODAL -->
<div class="modal" id="photoModal">

    <div class="modal-box">

        <div class="modal-header">
            <h3>
                <i class="fas fa-camera"></i>
                Upload Profile Photo
            </h3>

            <span onclick="closeModal('photoModal')" class="close">
                &times;
            </span>
        </div>

        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">

                <label>Select Photo</label>

                <input type="file"
                       name="profile_photo"
                       accept="image/*"
                       required>

            </div>

            <button type="submit" name="upload_photo" class="save-btn">
                <i class="fas fa-upload"></i>
                Upload Photo
            </button>

        </form>

    </div>

</div>
<!--  JAVASCRIPT -->


<script>
function hideAllSections(){

    let sections = [
        "dashboardContent",
        "bookmarksSection",
        "quizzesSection",
        "notificationsSection",
        "profileSection",
        "coursesSection",
        "browseCoursesSection",
        "progressSection",
        "paymentsSection",
        "settingsSection",
        "attendanceSection",
        "ticketSection"
    ];

    sections.forEach(id => {

        let el = document.getElementById(id);

        if(el){
            el.style.display = "none";
        }

    });
}

/* =========================
   SECTION FUNCTIONS
========================= */

function showDashboard(){
    hideAllSections();
    document.getElementById("dashboardContent").style.display = "block";
}

function showBookmarks(){
    hideAllSections();
    document.getElementById("bookmarksSection").style.display = "block";
}

function showQuizzes(){
    hideAllSections();
    document.getElementById("quizzesSection").style.display = "block";
}

function showNotifications(){
    hideAllSections();
    document.getElementById("notificationsSection").style.display = "block";
}

function showProfile(){
    hideAllSections();
    document.getElementById("profileSection").style.display = "block";
}

function showCourses(){
    hideAllSections();
    document.getElementById("coursesSection").style.display = "block";
}

function showBrowseCourses(){
    hideAllSections();
    document.getElementById("browseCoursesSection").style.display = "block";
}

function showProgress(){
    hideAllSections();
    document.getElementById("progressSection").style.display = "block";
}

function showPayment(){
    hideAllSections();

    document.getElementById("paymentsSection").style.display = "block";
}

function showSettings(){
    hideAllSections();
    document.getElementById("settingsSection").style.display = "block";
}

function showAttendance(){
    hideAllSections()
    document.getElementById("attendanceSection").style.display="block";
}

function showRaiseTicket(){
    hideAllSections()
    document.getElementById("ticketSection").style.display="block";
}

/* =========================
   DEFAULT VIEW
========================= */

window.onload = function(){

    hideAllSections();

    <?php if(isset($_SESSION['active_section']) && $_SESSION['active_section']=='quizzes'){ ?>
        document.getElementById("quizzesSection").style.display = "block";
    <?php
        unset($_SESSION['active_section']);
    } else { ?>
        document.getElementById("dashboardContent").style.display = "block";
    <?php } ?>

}

/* =========================
   PAYMENT MODAL
========================= */

function openPaymentModal(){
    document.getElementById("paymentModal").style.display = "flex";
}

function closePaymentModal(){
    document.getElementById("paymentModal").style.display = "none";
}

function togglePaymentFields(){

    let method =
        document.getElementById("paymentMethod").value;

    let mpesa =
        document.getElementById("mpesaField");

    if(method === "mpesa"){
        mpesa.style.display = "block";
    }else{
        mpesa.style.display = "none";
    }
}

/* =========================
   PROFILE MODAL
========================= */

function openProfileModal(){
    document.getElementById("profileModal").style.display = "flex";
}

function closeProfileModal(){
    document.getElementById("profileModal").style.display = "none";
}

/* =========================
   PASSWORD + PHOTO MODALS
========================= */

function openPasswordModal(){
    document.getElementById("passwordModal").style.display = "flex";
}

function openPhotoModal(){
    document.getElementById("photoModal").style.display = "flex";
}

function closeModal(id){
    document.getElementById(id).style.display = "none";
}

/* =========================
   GLOBAL MODAL CLOSE
========================= */

window.onclick = function(event){

    let paymentModal =
        document.getElementById("paymentModal");

    let profileModal =
        document.getElementById("profileModal");

    if(event.target === paymentModal){
        paymentModal.style.display = "none";
    }

    if(event.target === profileModal){
        profileModal.style.display = "none";
    }

    document.querySelectorAll(".modal").forEach(function(modal){

        if(event.target === modal){
            modal.style.display = "none";
        }

    });

}

</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>