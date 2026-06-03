<?php
session_start();
include 'db.php';

/* =USER  */
$user_id = $_SESSION['user_id'] ?? 1;

/* = STATS = */
$enrolled = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM enrollments WHERE user_id='$user_id'"
))['total'] ?? 0;

$completed = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM enrollments WHERE user_id='$user_id' AND status='completed'"
))['total'] ?? 0;

$ongoing = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM enrollments WHERE user_id='$user_id' AND status='ongoing'"
))['total'] ?? 0;

$progress = ($enrolled > 0) ? round(($completed / $enrolled) * 100) : 0;

/* = COURSES = */
$courses = mysqli_query($conn,
"SELECT c.title, e.progress 
FROM courses c
JOIN enrollments e ON c.id = e.course_id
WHERE e.user_id='$user_id'
LIMIT 5"
);

/* = ACTIVITIES = */
$activities = mysqli_query($conn,
"SELECT message, created_at 
FROM activities 
WHERE user_id='$user_id'
ORDER BY created_at DESC
LIMIT 5"
);

/*  BOOKMARKS */
$bookmarks = mysqli_query($conn,
"SELECT title, created_at 
FROM bookmarks 
WHERE user_id='$user_id'
ORDER BY created_at DESC
LIMIT 10"
);

/*quizzies */
$quizStats = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT 
    COUNT(*) AS total_quizzes
FROM quiz_results
WHERE user_id='$user_id'
"))['total_quizzes'] ?? 0;

$passed = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total 
FROM quiz_results 
WHERE user_id='$user_id' AND status='passed'"
))['total'] ?? 0;

$failed = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total 
FROM quiz_results 
WHERE user_id='$user_id' AND status='failed'"
))['total'] ?? 0;

$avgScore = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT AVG(score) AS avg_score 
FROM quiz_results 
WHERE user_id='$user_id'"
))['avg_score'] ?? 0;

$quizzes = mysqli_query($conn,
"SELECT * FROM quizzes ORDER BY id DESC"
);

$results = mysqli_query($conn,
"SELECT * FROM quiz_results 
WHERE user_id='$user_id'
ORDER BY created_at DESC
LIMIT 5"
);

$history = mysqli_query($conn,
"SELECT * FROM quiz_history 
WHERE user_id='$user_id'
ORDER BY completed_at DESC"
);

$achievements = mysqli_query($conn,
"SELECT * FROM quiz_achievements 
WHERE user_id='$user_id'"
);

$certs = mysqli_query($conn,
"SELECT * FROM certificates"
);

/* NOTIFICATIONS  */

$user_id = intval($user_id);

$notifications = mysqli_query($conn, "

SELECT
    message,
    created_at,
    'notification' AS type
FROM notifications
WHERE user_id = $user_id

UNION ALL

SELECT
    CONCAT('[Announcement] ', a.title, ' - ', a.message) AS message,
    a.created_at,
    'announcement' AS type
FROM announcements a
INNER JOIN enrollments e
    ON e.course_id = a.course_id
WHERE e.user_id = $user_id
AND e.status IN ('approved','ongoing','completed')

ORDER BY created_at DESC
LIMIT 10

");

if(!$notifications){
    die(mysqli_error($conn));
}

/*  USER PROFILE  */

$user_id = $_SESSION['user_id'] ?? 1;

$profile_query = mysqli_query($conn,
"SELECT * FROM users WHERE id='$user_id' LIMIT 1"
);

$profile = mysqli_fetch_assoc($profile_query);

/*  FALLBACK */

if(!$profile){

    $profile = [
        'full_name' => 'Unknown User',
        'email' => 'No Email',
        'phone' => 'No Phone',
        'location' => 'No Location',
        'bio' => 'No bio added yet.',
        'created_at' => date('Y-m-d'),
        'profile_image' => ''
    ];
}
/* UPDATED USER PROFILE */

if(isset($_POST['update_profile']))
    {
        $full_name = trim($_POST['full_name']);
        $email     = trim($_POST['email']);
        $phone     = trim($_POST['phone']);
        $location  = trim($_POST['location']);
        $bio       = trim($_POST['bio']);
    
        $student_id = $_SESSION['student_id'];
    
        $photo_sql = "";
    
        if(!empty($_FILES['photo']['name']))
        {
            $photo = time().'_'.$_FILES['photo']['name'];
    
            move_uploaded_file(
                $_FILES['photo']['tmp_name'],
                "uploads/".$photo
            );
    
            $photo_sql = ", profile_photo='$photo'";
        }
    
        $sql = "
        UPDATE students
        SET
            full_name='$full_name',
            email='$email',
            phone='$phone',
            location='$location',
            bio='$bio'
            $photo_sql
        WHERE id='$student_id'
        ";
    
        if(mysqli_query($conn,$sql))
        {
            echo "
            <script>
                alert('Profile updated successfully');
                window.location.href=window.location.href;
            </script>
            ";
        }
    }

/*  ENROLLED COURSES COUNT  */

$total_courses = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total 
FROM enrollments 
WHERE user_id='$user_id'"
))['total'] ?? 0;

/* COURSE STATS  */

$total_courses = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total 
FROM enrollments 
WHERE user_id='$user_id'"
))['total'] ?? 0;

$active_courses = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total 
FROM enrollments 
WHERE user_id='$user_id'
AND status='ongoing'"
))['total'] ?? 0;

$completed_courses = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total 
FROM enrollments 
WHERE user_id='$user_id'
AND status='completed'"
))['total'] ?? 0;

$certificates_earned = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total 
FROM certificates 
WHERE user_id='$user_id'"
))['total'] ?? 0;

/*  ACTIVE COURSES  */

$active_courses_list = mysqli_query($conn,
"SELECT 
courses.id,
courses.title,
courses.instructor,
courses.lessons,
enrollments.progress
FROM enrollments
INNER JOIN courses 
ON enrollments.course_id = courses.id
WHERE enrollments.user_id='$user_id'
AND enrollments.status='ongoing'
ORDER BY enrollments.id DESC"
);

/*  COMPLETED COURSES  */

$completed_courses_list = mysqli_query($conn,
"SELECT 
courses.title,
enrollments.completed_at
FROM enrollments
INNER JOIN courses 
ON enrollments.course_id = courses.id
WHERE enrollments.user_id='$user_id'
AND enrollments.status='completed'
ORDER BY enrollments.completed_at DESC"
);

/*  ASSIGNMENTS */

$assignments = mysqli_query($conn,
"SELECT * FROM assignments
WHERE user_id='$user_id'
ORDER BY due_date ASC
LIMIT 10"
);

/*  COURSE MATERIALS  */

$materials = mysqli_query($conn,
"SELECT * FROM course_materials
ORDER BY id DESC
LIMIT 10"
);

/* LIVE CLASSES  */

$live_classes = mysqli_query($conn,
"SELECT * FROM live_classes
ORDER BY schedule_time ASC
LIMIT 10"
);

/* BROWSE COURSES BACKEND */

 // SEARCH VALUE
$search = $_GET['search'] ?? '';
$search = trim($search);

/*   FETCH FEATURED  ALL COURSES */

if($search != ""){

    $safe_search = mysqli_real_escape_string($conn, $search);

    $browseCourses = mysqli_query($conn,
    "SELECT *
    FROM courses
    WHERE title LIKE '%$safe_search%'
    OR description LIKE '%$safe_search%'
    OR instructor LIKE '%$safe_search%'
    ORDER BY created_at DESC");

}else{

    $browseCourses = mysqli_query($conn,
    "SELECT *
    FROM courses
    ORDER BY created_at DESC
    LIMIT 12");

}

/* TRENDING COURSES */

$trendingCourses = mysqli_query($conn,
"SELECT title, enrolled_students
FROM courses
ORDER BY enrolled_students DESC
LIMIT 5");

/*  TOP INSTRUCTORS */

$topInstructors = mysqli_query($conn,
"SELECT *
FROM instructors
ORDER BY rating DESC
LIMIT 5");

/* COURSE BENEFITS */

$courseBenefits = [
    [
        "title" => "Industry Expert Instructors",
        "description" => "Learn from experienced professionals"
    ],
    [
        "title" => "Certificates After Completion",
        "description" => "Boost your professional portfolio"
    ],
    [
        "title" => "Lifetime Access",
        "description" => "Study anytime at your own pace"
    ],
    [
        "title" => "Practical Projects",
        "description" => "Build real-world applications"
    ]
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

$progressTotalCourses = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total
FROM enrollments
WHERE user_id='$user_id'"
))['total'] ?? 0;

$progressCompletedCourses = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total
FROM enrollments
WHERE user_id='$user_id'
AND status='completed'"
))['total'] ?? 0;

$progressOngoingCourses = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total
FROM enrollments
WHERE user_id='$user_id'
AND status='ongoing'"
))['total'] ?? 0;

$overallProgress = 0;

if($progressTotalCourses > 0){

    $overallProgress = round(
        ($progressCompletedCourses / $progressTotalCourses) * 100
    );
}

/* COURSE PROGRESS */

$courseProgress = mysqli_query($conn,
"SELECT
courses.title,
enrollments.progress,
enrollments.status
FROM enrollments
INNER JOIN courses
ON enrollments.course_id = courses.id
WHERE enrollments.user_id='$user_id'
ORDER BY enrollments.progress DESC");

/* QUIZ PERFORMANCE */

$quizPerformance = mysqli_query($conn,
"SELECT *
FROM quiz_results
WHERE user_id='$user_id'
ORDER BY created_at DESC
LIMIT 10");

/* CERTIFICATES */

$progressCertificates = mysqli_query($conn,
"SELECT *
FROM certificates
WHERE user_id='$user_id'
ORDER BY issued_at DESC");

/* LEARNING ANALYTICS */

$analytics = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT *
FROM learning_stats
WHERE user_id='$user_id'
LIMIT 1"));

if(!$analytics){

    $analytics = [
        'study_hours' => 0,
        'lessons_completed' => 0,
        'assignments_submitted' => 0
    ];
}

/* RECENT ACHIEVEMENTS */

$progressAchievements = [];

/* COMPLETED COURSES */

if($progressCompletedCourses > 0){

    $progressAchievements[] = [
        "title" => "🏆 Completed Courses",
        "description" =>
        "$progressCompletedCourses courses completed successfully"
    ];
}

/* LEARNING PROGRESS */

if($overallProgress >= 50){

    $progressAchievements[] = [
        "title" => "🔥 Learning Progress",
        "description" =>
        "You reached $overallProgress% learning progress"
    ];
}

/* TOP QUIZ PERFORMANCE */

$topQuiz = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT *
FROM quiz_results
WHERE user_id='$user_id'
ORDER BY score DESC
LIMIT 1"));

if($topQuiz){

    $progressAchievements[] = [
        "title" => "⭐ Top Quiz Performer",
        "description" =>
        $topQuiz['quiz_title'] .
        " - Score: " .
        $topQuiz['score'] . "%"
    ];
}

/*   PAYMENTS & BILLING  */

/* TOTAL PAID */
$totalPaidRow = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COALESCE(SUM(amount),0) AS total
FROM payments
WHERE user_id='$user_id' AND status='success'"
));
$totalPaid = $totalPaidRow['total'] ?? 0;

/* PENDING */
$pendingRow = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COALESCE(SUM(amount),0) AS total
FROM payments
WHERE user_id='$user_id' AND status='pending'"
));
$pendingPayments = $pendingRow['total'] ?? 0;

/* SUCCESS TRANSACTIONS */
$transactionsCountRow = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total
FROM payments
WHERE user_id='$user_id' AND status='success'"
));
$totalTransactions = $transactionsCountRow['total'] ?? 0;

/* INVOICES */
$invoiceCountRow = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total
FROM invoices
WHERE user_id='$user_id'"
));
$invoiceCount = $invoiceCountRow['total'] ?? 0;

/* TRANSACTIONS  */

$transactions = mysqli_query($conn,
"SELECT *
FROM payments
WHERE user_id='$user_id'
ORDER BY created_at DESC"
);

/*  INVOICES  */

$invoices = mysqli_query($conn,
"SELECT *
FROM invoices
WHERE user_id='$user_id'
ORDER BY created_at DESC"
);

/*  PAYMENT METHODS  */

$paymentMethods = mysqli_query($conn,
"SELECT *
FROM payment_methods
WHERE user_id='$user_id'
ORDER BY created_at DESC"
);


/*  PAYMENT  FORM */

if(isset($_POST['submit_payment'])){

    $user_id = $_SESSION['user_id'];

    $course_id = intval($_POST['course_id']);

    $amount = floatval($_POST['amount']);

    $method = $_POST['payment_method'];

    $phone = trim($_POST['payer_phone']);

    $course = mysqli_fetch_assoc(
        mysqli_query(
            $conn,
            "SELECT * FROM courses WHERE id='$course_id'"
        )
    );

    if($course){

        $courseTitle = $course['title'];

        $invoiceNo =
            "INV".time();

        mysqli_query($conn,"
        INSERT INTO payments
        (
            user_id,
            course_id,
            payer_name,
            payer_phone,
            amount,
            payment_method,
            status,
            verification_status
        )
        VALUES
        (
            '$user_id',
            '$course_id',
            'Student',
            '$phone',
            '$amount',
            '$method',
            'success',
            'verified'
        )
        ");

        $exists =
        mysqli_query(
        $conn,
        "SELECT id
         FROM enrollments
         WHERE user_id='$user_id'
         AND course_id='$course_id'"
        );

        if(mysqli_num_rows($exists)==0){

            mysqli_query($conn,"
            INSERT INTO enrollments
            (
                user_id,
                course_id,
                progress,
                status
            )
            VALUES
            (
                '$user_id',
                '$course_id',
                0,
                'approved'
            )
            ");

        }

        mysqli_query($conn,"
        INSERT INTO invoices
        (
            user_id,
            invoice_no,
            description,
            status
        )
        VALUES
        (
            '$user_id',
            '$invoiceNo',
            'Payment for $courseTitle',
            'paid'
        )
        ");

        echo "
        <script>
        alert('Payment Successful. Enrollment Activated.');
        location.href=location.href;
        </script>";
    }
}
/* SETTINGS SECTION BACKEND */

$settingsRow = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT *
FROM user_settings
WHERE user_id='$user_id'
LIMIT 1"
));

if(!$settingsRow){
    $settingsRow = [
        'theme' => 'light',
        'email_notifications' => 1,
        'sms_notifications' => 1,
        'password_updated_at' => null
    ];
}

/* ATTENDANCE SECTION 
$user_id = $_SESSION['user_id'] ?? 0;

if($user_id == 0){
    die("Please login first");
}
 =========================
   SIGN ATTENDANCE
========================= */
if(isset($_POST['sign_attendance'])) {

    $course_id = intval($_POST['course_id']);

    if($course_id <= 0){
        echo "<script>alert('Please select a course');</script>";
    } else {

        // check duplicate attendance today
        $check = mysqli_query($conn, "
            SELECT * FROM attendance
            WHERE user_id='$user_id'
            AND course_id='$course_id'
            AND DATE(signed_at)=CURDATE()
        ");

        if(mysqli_num_rows($check) > 0){
            echo "<script>alert('You already signed attendance today');</script>";
        } else {

            mysqli_query($conn, "
            INSERT INTO attendance (user_id, course_id, attendance_date, status)
            VALUES ('$user_id', '$course_id', CURDATE(), 'present');
            ");

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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">


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
    background:#2563eb;
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
}

.btn:hover{
    background:#1d4ed8;
    transform:translateY(-2px);
}

/* 
   PROFILE
 */
.profile-box{
    width:100%;
    background:#fff;
    border-radius:20px;
    padding:30px;
    box-shadow:0 4px 15px rgba(0,0,0,.06);
    display:none;
}

.profile-header{
    display:flex;
    align-items:center;
    gap:25px;
    flex-wrap:wrap;
    margin-bottom:35px;
}

.profile-image img{
    width:120px;
    height:120px;
    border-radius:50%;
    object-fit:cover;
    border:4px solid #2563eb;
}

.profile-info h2{
    color:#0f172a;
    font-size:28px;
    margin-bottom:8px;
}

.profile-info p{
    color:#64748b;
}

/* 
   PROFILE ACTIONS
 */
.profile-actions{
    display:flex;
    gap:15px;
    flex-wrap:wrap;
    margin:25px 0;
}

.action-btn{
    border:none;
    padding:12px 20px;
    border-radius:12px;
    color:#fff;
    font-weight:600;
    cursor:pointer;
    transition:.3s;
    background-color:#49d7d2;
}

.edit-btn{
    background:#2563eb;
}

.password-btn{
    background:#2563eb;
}

.photo-btn{
    background:#2563eb;
}

.action-btn:hover{
    transform:translateY(-3px);
}

/* 
   DETAIL CARDS
 */
.detail-card{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:22px;
    transition:.3s;
}

.detail-card:hover{
    transform:translateY(-5px);
    border-color:#2563eb;
    box-shadow:0 10px 20px rgba(0,0,0,.08);
}

.detail-card h4{
    color:#2563eb;
    margin-bottom:10px;
}

.detail-card p{
    color:#475569;
    line-height:1.7;
}

.full-width{
    grid-column:1/-1;
}

/* 
   MODALS
 */
.modal,
.payment-modal,
.profile-modal{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.65);
    display:none;
    justify-content:center;
    align-items:center;
    padding:20px;
    z-index:99999;
    backdrop-filter:blur(5px);
}

.modal-box,
.payment-content,
.modal-content
{
    width:100%;
    padding:20px;
    max-width:700px;
    background:#fff;
    border-radius:22px;
    overflow:hidden;
    animation:popup .3s ease;
    max-height:90vh;
    overflow-y:auto;
}

/* 
   ANIMATION
 */
@keyframes popup{
    from{
        opacity:0;
        transform:translateY(-20px) scale(.95);
    }
    to{
        opacity:1;
        transform:translateY(0) scale(1);
    }
}

/* 
   MODAL HEADER
 */
.modal-header{
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    color:#fff;
    padding:20px 25px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.modal-header h3{
    margin:0;
    font-size:22px;
}

.close,
.close-btn,
.close-payment{
    color:#fff;
    font-size:28px;
    cursor:pointer;
}

/* 
   FORMS
 */
.modal form,
{
    padding:25px;
}
.payment-content form{
    padding:30px;
}

.form-group{
    margin-bottom:20px;
}

.form-group label{
    display:block;
    margin-bottom:8px;
    color:#334155;
    font-weight:600;
}

.form-group input,
.form-group textarea,
.form-group select{
    width:100%;
    padding:14px 15px;
    border:1px solid #cbd5e1;
    border-radius:12px;
    outline:none;
    transition:.3s;
    font-size:15px;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus{
    border-color:#2563eb;
    box-shadow:0 0 0 4px rgba(37,99,235,.12);
}

.form-group textarea{
    resize:vertical;
    min-height:120px;
}

/* 
   SUBMIT BUTTON
 */
.save-btn,
.btn-submit{
    width:100%;
    border:none;
    background:#2563eb;
    color:#fff;
    padding:14px;
    border-radius:12px;
    cursor:pointer;
    font-size:15px;
    font-weight:600;
    transition:.3s;
}

.save-btn:hover,
.btn-submit:hover{
    background:#1d4ed8;
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
        <li class="logout"><a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
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
<?php { ?>

<h3>Raise Ticket</h3>

<form method="POST" action="create_ticket.php">

    <select name="type" required>
        <option value="">Select Type</option>
        <option value="academic">Academic</option>
        <option value="technical">Technical</option>
        <option value="attendance">Attendance</option>
        <option value="general">General</option>
    </select>

    <input type="text" name="subject" placeholder="Subject" required>

    <textarea name="message" placeholder="Message" required></textarea>

    <button type="submit">Submit Ticket</button>
</form>

<?php } ?>

<hr>

<!-- VIEW TICKETS -->

<h3>My Tickets</h3>

<table>
<tr>
    <th>Type</th>
    <th>Subject</th>
    <th>Status</th>
    <th>Date</th>
</tr>

<?php
$result = mysqli_query($conn, "
    SELECT * FROM tickets
    WHERE user_id='$user_id'
    ORDER BY created_at DESC
");

while($row = mysqli_fetch_assoc($result)) {
?>

<tr>
    <td><?= $row['type'] ?></td>
    <td><?= $row['subject'] ?></td>

    <td>
        <?php
            if($row['status']=='open')
                echo "<span class='status-open'>🟡 Open</span>";
            elseif($row['status']=='in_progress')
                echo "<span class='status-progress'>🔵 In Progress</span>";
            elseif($row['status']=='resolved')
                echo "<span class='status-resolved'>🟢 Resolved</span>";
            else
                echo "<span class='status-closed'>⚫ Closed</span>";
        ?>
    </td>

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

        <a href="browse_courses.php" class="btn">
            <i class="fas fa-plus"></i>
            Enroll New Course
        </a>

    </div>

    <!--  SUMMARY  -->

    <div class="stats">

        <div class="stat-card">
            <i class="fas fa-book"></i>
            <h2><?= $total_courses ?></h2>
            <p>Total Courses</p>
        </div>

        <div class="stat-card">
            <i class="fas fa-play-circle"></i>
            <h2><?= $active_courses ?></h2>
            <p>Active Courses</p>
        </div>

        <div class="stat-card">
            <i class="fas fa-check-circle"></i>
            <h2><?= $completed_courses ?></h2>
            <p>Completed Courses</p>
        </div>

        <div class="stat-card">
            <i class="fas fa-award"></i>
            <h2><?= $certificates_earned ?></h2>
            <p>Certificates Earned</p>
        </div>

    </div>

    <!--  ACTIVE COURSES  -->

    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-laptop-code"></i>
            Active Courses
        </h3>

        <?php if(mysqli_num_rows($active_courses_list) > 0){ ?>

            <?php while($course = mysqli_fetch_assoc($active_courses_list)){ ?>

                <div class="course">

                    <div class="course-info">

                        <h4>
                            <?= htmlspecialchars($course['title']) ?>
                        </h4>

                        <p>
                            Instructor:
                            <?= htmlspecialchars($course['instructor']) ?>

                            •

                            <?= $course['lessons'] ?> Lessons

                            •

                            <?= $course['progress'] ?>% Completed
                        </p>

                        <div class="progress">
                            <div class="progress-bar"
                            style="width:<?= $course['progress'] ?>%;">
                            </div>
                        </div>

                    </div>

                    <a href="course1.php?id=<?= $course['id'] ?>" class="btn">
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

    <!--  COMPLETED COURSES  -->

    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-check-double"></i>
            Completed Courses
        </h3>

        <?php if(mysqli_num_rows($completed_courses_list) > 0){ ?>

            <?php while($completed = mysqli_fetch_assoc($completed_courses_list)){ ?>

                <div class="activity">

                    <p>
                        <?= htmlspecialchars($completed['title']) ?>
                    </p>

                    <span>
                        Completed on
                        <?= date("d F Y", strtotime($completed['completed_at'])) ?>
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

    <!--  ASSIGNMENTS  -->

    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-tasks"></i>
            Assignments
        </h3>

        <?php if(mysqli_num_rows($assignments) > 0){ ?>

            <?php while($assignment = mysqli_fetch_assoc($assignments)){ ?>

                <div class="activity">

                    <p>
                        <?= htmlspecialchars($assignment['title']) ?>
                    </p>

                    <span>
                        <?= htmlspecialchars($assignment['status']) ?>

                        <?php if(!empty($assignment['due_date'])){ ?>
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

    <!--  COURSE MATERIALS  -->

    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-folder-open"></i>
            Course Materials
        </h3>

        <?php if(mysqli_num_rows($materials) > 0){ ?>

            <?php while($material = mysqli_fetch_assoc($materials)){ ?>

                <div class="activity">

                    <p>
                        <?= htmlspecialchars($material['title']) ?>
                    </p>

                    <span>
                        Downloaded
                        <?= $material['downloads'] ?>
                        Times
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

    <!--  LIVE CLASSES  -->

    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-video"></i>
            Upcoming Live Classes
        </h3>

        <?php if(mysqli_num_rows($live_classes) > 0){ ?>

            <?php while($live = mysqli_fetch_assoc($live_classes)){ ?>

                <div class="activity">

                    <p>
                        <?= htmlspecialchars($live['title']) ?>
                    </p>

                    <span>
                        <?= date("d F Y • h:i A", strtotime($live['schedule_time'])) ?>
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
        <h2><?= $quizStats ?></h2>
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
        <h2><?= round($avgScore) ?>%</h2>
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

    <div style="
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:15px;
    margin-bottom:25px;">

        <div>

            <h3>
                <i class="fas fa-search"></i>
                Browse Courses
            </h3>

            <p style="
            color:#6b7280;
            font-size:14px;
            margin-top:5px;">
                Explore trending courses, discover new skills,
                and enroll in professional learning programs.
            </p>

        </div>


    </div>

    <!-- SEARCH BAR -->


    
    <form method="GET"
    style="
    display:flex;
    gap:15px;
    flex-wrap:wrap;
    margin-bottom:25px;">

        <input
        type="text"
        name="search"
        value="<?= htmlspecialchars($search) ?>"
        placeholder="Search courses..."
        style="
        flex:1;
        padding:14px;
        border:1px solid #d1d5db;
        border-radius:10px;
        outline:none;
        font-size:15px;
        ">

        <button type="submit" class="btn">
            <i class="fas fa-search"></i>
            Search
        </button>

    </form>

    <!-- COURSE CATEGORIES -->

    <div class="box">

        <h3>
            <i class="fas fa-layer-group"></i>
            Categories
        </h3>

        <div style="
        display:flex;
        gap:10px;
        flex-wrap:wrap;
        margin-top:15px;">

            <?php foreach($categories as $category){ ?>

                <button class="btn">
                    <?= htmlspecialchars($category) ?>
                </button>

            <?php } ?>

        </div>

    </div>

    <!-- FEATURED COURSES -->

    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-star"></i>
            Featured Courses
        </h3>

        <?php if(mysqli_num_rows($browseCourses) > 0){ ?>

            <?php while($course = mysqli_fetch_assoc($browseCourses)){ ?>

                <div class="course">

                    <div class="course-info">

                        <h4>
                            <?= htmlspecialchars($course['title']) ?>
                        </h4>

                        <p>
                            <?= htmlspecialchars($course['description']) ?>
                        </p>

                        <p style="
                        margin-top:8px;
                        color:#2563eb;
                        font-weight:bold;">

                            KES <?= number_format($course['price']) ?>

                        </p>

                        <p style="
                        margin-top:8px;
                        color:#6b7280;
                        font-size:14px;">

                            Instructor:
                            <?= htmlspecialchars($course['instructor']) ?>

                            • <?= (int)$course['lessons'] ?> Lessons

                            • <?= (int)$course['enrolled_students'] ?> Students

                        </p>

                    </div>

                    <a href="enroll.php?course_id=<?= $course['id'] ?>"
                    class="btn">

                        Enroll Now

                    </a>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">

                <p>No courses found</p>

                <span>
                    Try searching with another keyword
                </span>

            </div>

        <?php } ?>

    </div>

    <!-- TRENDING COURSES -->

    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-fire"></i>
            Trending Courses
        </h3>

        <?php if(mysqli_num_rows($trendingCourses) > 0){ ?>

            <?php while($trend = mysqli_fetch_assoc($trendingCourses)){ ?>

                <div class="activity">

                    <p>
                        <?= htmlspecialchars($trend['title']) ?>
                    </p>

                    <span>
                        <?= number_format($trend['enrolled_students']) ?>
                        Students Enrolled
                    </span>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">

                <p>No trending courses available</p>

                <span>
                    Courses will appear here automatically
                </span>

            </div>

        <?php } ?>

    </div>

    <!-- TOP INSTRUCTORS -->

    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-chalkboard-teacher"></i>
            Top Instructors
        </h3>

        <?php if(mysqli_num_rows($topInstructors) > 0){ ?>

            <?php while($instructor = mysqli_fetch_assoc($topInstructors)){ ?>

                <div class="activity">

                    <p>
                        <?= htmlspecialchars($instructor['name']) ?>
                    </p>

                    <span>

                        <?= htmlspecialchars($instructor['title']) ?>

                        • Rating:
                        <?= (int)$instructor['rating'] ?>/5

                    </span>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="activity">

                <p>No instructors available</p>

                <span>
                    Instructor data will appear here
                </span>

            </div>

        <?php } ?>

    </div>

    <!-- COURSE BENEFITS -->

    <div class="box" style="margin-top:25px;">

        <h3>
            <i class="fas fa-gift"></i>
            Why Learn With Us?
        </h3>

        <?php foreach($courseBenefits as $benefit){ ?>

            <div class="activity">

                <p>
                    <?= htmlspecialchars($benefit['title']) ?>
                </p>

                <span>
                    <?= htmlspecialchars($benefit['description']) ?>
                </span>

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
<div class="box" id="ticketSection" style="display:none">
<?php if($role != 'admin') { ?>

<div class="ticket-container">

    <!-- =========================
         RAISE TICKET SECTION
    ========================== -->
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
            <input type="text" name="subject" placeholder="Enter subject..." required>

            <label>Message</label>
            <textarea name="message" placeholder="Describe your issue..." required></textarea>

            <button type="submit">Submit Ticket</button>

        </form>
    </div>

    <hr>

    <!-- =========================
         TICKET HISTORY
    ========================== -->
    <div class="ticket-box">

        <h3>📄 My Tickets</h3>

        <table>
            <tr>
                <th>Type</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Date</th>
            </tr>

            <?php
            $result = mysqli_query($conn, "
                SELECT * FROM tickets
                WHERE user_id='$user_id'
                ORDER BY created_at DESC
            ");

            while($row = mysqli_fetch_assoc($result)) {
            ?>

            <tr>
                <td><?= $row['type'] ?></td>
                <td><?= $row['subject'] ?></td>

                <td>
                    <?php
                        if($row['status']=='open')
                            echo "<span class='status-open'>🟡 Open</span>";
                        elseif($row['status']=='in_progress')
                            echo "<span class='status-progress'>🔵 In Progress</span>";
                        elseif($row['status']=='resolved')
                            echo "<span class='status-resolved'>🟢 Resolved</span>";
                        else
                            echo "<span class='status-closed'>⚫ Closed</span>";
                    ?>
                </td>

                <td><?= $row['created_at'] ?></td>
            </tr>

            <?php } ?>

        </table>

    </div>

</div>

<?php } ?>
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

            <?php if(!empty($profile['profile_image'])) { ?>

                <img src="uploads/<?= htmlspecialchars($profile['profile_image']) ?>" alt="Profile Image">

            <?php } else { ?>

                <img src="https://via.placeholder.com/120" alt="Profile Image">

            <?php } ?>

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

            <div class="form-group">
                <label>Full Name</label>
                <input type="text"
                       name="full_name"
                       value="<?= htmlspecialchars($profile['full_name']) ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email"
                       name="email"
                       value="<?= htmlspecialchars($profile['email']) ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="text"
                       name="phone"
                       value="<?= htmlspecialchars($profile['phone']) ?>">
            </div>

            <div class="form-group">
                <label>Location</label>
                <input type="text"
                       name="location"
                       value="<?= htmlspecialchars($profile['location']) ?>">
            </div>

            <div class="form-group">
                <label>Bio</label>
                <textarea name="bio"><?= htmlspecialchars($profile['bio']) ?></textarea>
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

</body>
</html>