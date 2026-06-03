<?php
session_start();
include "db.php";

/* 
   DASHBOARD STATISTICS
 */

// Total Students
$total_students = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM students"))['total'] ?? 0;

// Active Students
$active_students = 0;

$result = mysqli_query($conn,
"SELECT COUNT(*) AS total FROM students WHERE status='active'");

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
    COUNT(enrollments.id) AS students,
    COALESCE(SUM(payments.amount),0) AS revenue,
    COALESCE(AVG(enrollments.progress),0) AS completion_rate

FROM courses

LEFT JOIN enrollments
    ON courses.id = enrollments.course_id

LEFT JOIN payments
    ON enrollments.user_id = payments.user_id
    AND payments.status = 'success'

GROUP BY courses.id, courses.title

ORDER BY students DESC
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

/* 
   HANDLE CREATE COURSE
 */
if(isset($_POST['create_course'])){

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $instructor = trim($_POST['instructor']);
    $price = floatval($_POST['price']);

    if($title == "" || $description == "" || $category == "" || $instructor == ""){
        die("All fields are required");
    }

    $thumbnail = "default.jpg";

    /*  THUMBNAIL VALIDATION */
    if(!empty($_FILES['thumbnail']['name'])){

        $fileName = $_FILES['thumbnail']['name'];
        $fileTmp  = $_FILES['thumbnail']['tmp_name'];
        $fileSize = $_FILES['thumbnail']['size'];

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if(!in_array($ext, $allowed_images)){
            die("Only image files allowed (jpg, jpeg, png, gif, webp)");
        }

        if($fileSize > 2 * 1024 * 1024){
            die("Image too large (max 2MB)");
        }

        $thumbnail = time().'_'.rand(1000,9999).'.'.$ext;

        move_uploaded_file($fileTmp, $upload_dir.$thumbnail);
    }

    mysqli_query($conn,
    "INSERT INTO courses(title,description,category,instructor,price,thumbnail)
     VALUES('$title','$description','$category','$instructor','$price','$thumbnail')"
    );

    mysqli_query($conn,
    "INSERT INTO activities(user_id,message)
     VALUES(1,'Created new course: $title')"
    );
}


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
/* COURSE SECTION  */ 
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>LMS Admin Dashboard</title>

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
    margin-left:270px;
    width:calc(100% - 270px);
    padding:20px;
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
    padding:25px;
    border-radius:15px;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
    transition:0.3s;
}

.card:hover{
    transform:translateY(-5px);
}

.card .icon{
    width:60px;
    height:60px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:24px;
    color:#fff;
    margin-bottom:15px;
}

.students{ background:#2563eb; }
.courses{ background:#16a34a; }
.payments{ background:#f59e0b; }
.revenue{ background:#dc2626; }

.card h3{
    font-size:30px;
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
                <a href="#" class="active">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li>
                <a href="stud.php" onclick="showStudent()">
                    <i class="fas fa-user-graduate"></i>
                    <span>Students</span>
                </a>
            </li>

            <li>
                <a href="course.php" onclick="showCourse()">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>
            </li>

            <li>
                <a href="vedio.php" onclick="showVideos()">
                    <i class="fas fa-video"></i>
                    <span>Videos</span>
                </a>
            </li>

            <li>
                <a href="notes.php" onclick="showNotes">
                    <i class="fas fa-file-pdf"></i>
                    <span>Notes</span>
                </a>
            </li>

            <li>
                <a href="Quiz.php"onclick="showQuizzes">
                    <i class="fas fa-question-circle"></i>
                    <span>Quizzes</span>
                </a>
            </li>

            <li>
                <a href="admin_enrollments.php" onclick="showEnrollments()">
                    <i class="fas fa-user-check"></i>
                    <span>Enrollments</span>
                </a>
            </li>

            <li>
                <a href="payment.php" onclick="showPayments">
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
                <a href="teacher_Announcement.php " onclick="showNotifications()">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>

            <li>
                <a href="#" onclick="showSettings()">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>

            <li>
                <a href="#">
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

    <!-- TOPBAR -->

    <div class="topbar">

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
            <p>Active Students</p>
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
            <h2>Revenue Graph</h2>

            <div class="chart-placeholder">
                <i class="fas fa-chart-area"></i>
                <p>Revenue analytics graph goes here</p>
            </div>
        </div>

        <div class="chart-box">
            <h2>Student Growth Chart</h2>

            <div class="chart-placeholder">
                <i class="fas fa-chart-line"></i>
                <p>Student growth chart goes here</p>
            </div>
        </div>

        <div class="chart-box">
            <h2>Enrollment Trends</h2>

            <div class="chart-placeholder">
                <i class="fas fa-chart-bar"></i>
                <p>Enrollment trend graph goes here</p>
            </div>
        </div>

        <div class="chart-box">

            <h2>Payment Status Summary</h2>

            <div class="payment-summary">

                <div class="summary-item">
                    <span>Successful Payments</span>
                    <strong><?= $successful_payments ?></strong>
                </div>

                <div class="summary-item">
                    <span>Pending Payments</span>
                    <strong><?= $pending_payments ?></strong>
                </div>

                <div class="summary-item">
                    <span>Failed Payments</span>
                    <strong><?= $failed_payments ?></strong>
                </div>

            </div>

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

                    <td><?= $course['students'] ?></td>

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

    <h2>Recent Student Registrations</h2>

    <table>

        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Course</th>
                <th>Status</th>
                <th>Action</th>
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

                <td>
                    <button class="btn edit">Edit</button>
                    <button class="btn delete">Delete</button>
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
<script>
function openModal(id){
    document.getElementById(id).style.display = "flex";
}

function closeModal(id){
    document.getElementById(id).style.display = "none";
}

// close when clicking outside
window.onclick = function(e){
    document.querySelectorAll('.modal').forEach(modal=>{
        if(e.target == modal){
            modal.style.display = "none";
        }
    });
}
</script>
</body>
</html>