<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>

    <!-- FONT AWESOME -->
    <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:Arial, sans-serif;
        }

        body{
            display:flex;
            background:#f5f7fb;
        }

        /* ================= SIDEBAR ================= */

        .sidebar{
            width:260px;
            height:100vh;
            background:#111827;
            position:fixed;
            left:0;
            top:0;
            overflow-y:auto;
        }

        .logo{
            padding:25px;
            text-align:center;
            border-bottom:1px solid rgba(255,255,255,0.1);
        }

        .logo h2{
            color:#fff;
            font-size:24px;
        }

        .menu{
            list-style:none;
            margin-top:10px;
        }

        .menu li a{
            display:flex;
            align-items:center;
            gap:15px;
            padding:16px 25px;
            color:#d1d5db;
            text-decoration:none;
            transition:0.3s;
            font-size:15px;
        }

        .menu li a:hover{
            background:#1f2937;
            color:#fff;
            padding-left:32px;
        }

        .menu li a i{
            width:20px;
            text-align:center;
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

        /* ================= MAIN CONTENT ================= */

        .main-content{
            margin-left:260px;
            padding:30px;
            width:100%;
        }

        .header{
            background:#fff;
            padding:25px;
            border-radius:12px;
            box-shadow:0 2px 10px rgba(0,0,0,0.05);
            margin-bottom:25px;
        }

        .header h1{
            font-size:30px;
            color:#111827;
            margin-bottom:8px;
        }

        .header p{
            color:#6b7280;
        }

        .stats{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:20px;
            margin-bottom:25px;
        }

        .stat-card{
            background:#fff;
            padding:25px;
            border-radius:12px;
            box-shadow:0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card i{
            font-size:30px;
            color:#2563eb;
            margin-bottom:15px;
        }

        .stat-card h2{
            font-size:28px;
            margin-bottom:8px;
            color:#111827;
        }

        .stat-card p{
            color:#6b7280;
        }

        .content-grid{
            display:grid;
            grid-template-columns:2fr 1fr;
            gap:20px;
        }

        .box{
            background:#fff;
            border-radius:12px;
            padding:25px;
            box-shadow:0 2px 10px rgba(0,0,0,0.05);
        }

        .course{
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:15px 0;
            border-bottom:1px solid #eee;
        }

        .course:last-child{border-bottom:none;}

        .progress{
            width:120px;
            height:8px;
            background:#e5e7eb;
            border-radius:10px;
            overflow:hidden;
            margin-top:8px;
        }

        .progress-bar{
            height:100%;
            background:#10b981;
        }

        .btn{
            display:inline-block;
            padding:10px 18px;
            background:#2563eb;
            color:#fff;
            text-decoration:none;
            border-radius:8px;
            margin-top:10px;
            transition:0.3s;
        }

        .btn:hover{background:#1d4ed8;}

        .activity{
            padding:12px 0;
            border-bottom:1px solid #eee;
        }

        .activity:last-child{border-bottom:none;}

        .profile-box{
            background:#fff;
            border-radius:12px;
            padding:30px;
            box-shadow:0 2px 10px rgba(0,0,0,0.05);
            display:none;
        }

    </style>
</head>

<body>

<!-- ================= SIDEBAR ================= -->
<div class="sidebar">

    <div class="logo">
        <h2>LMS Portal</h2>
    </div>

    <ul class="menu">

        <li class="active"><a href="#" onclick="showDashboard()"><i class="fas fa-home"></i>Dashboard</a></li>
        <li><a href="#" onclick="showCourses()"><i class="fas fa-book-open"></i>My Courses</a></li>
        <li><a href="#" onclick="showBrowseCourses()"><i class="fas fa-search"></i>Browse Courses</a></li>
        <li><a href="#" onclick="showProgress()"><i class="fas fa-chart-line"></i>Progress</a></li>
        <li><a href="#" onclick="showQuizzes()"><i class="fas fa-question-circle"></i>Quizzes</a></li>
        <li><a href="#" onclick="showPayments()"><i class="fas fa-credit-card"></i>Payments</a></li>
        <li><a href="#" onclick="showNotifications()"><i class="fas fa-bell"></i>Notifications</a></li>
        <li><a href="#" onclick="showBookmarks()"><i class="fas fa-bookmark"></i>Bookmarks</a></li>
        <li><a href="#" onclick="showProfile()"><i class="fas fa-user"></i>Profile</a></li>
        <li><a href="#" onclick="showSettings()"><i class="fas fa-cog"></i>Settings</a></li>

        <li class="logout">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
        </li>

    </ul>
</div>

<!-- ================= MAIN CONTENT ================= -->
<div class="main-content">

<!-- DASHBOARD -->
<div id="dashboardContent">

    <div class="header">
        <h1>Welcome Back, Student 👋</h1>
        <p>Browse courses, continue learning, track your progress, and manage your LMS activities.</p>
    </div>

    <div class="stats">
        <div class="stat-card"><i class="fas fa-book"></i><h2>12</h2><p>Enrolled Courses</p></div>
        <div class="stat-card"><i class="fas fa-check-circle"></i><h2>5</h2><p>Completed Courses</p></div>
        <div class="stat-card"><i class="fas fa-spinner"></i><h2>7</h2><p>Ongoing Courses</p></div>
        <div class="stat-card"><i class="fas fa-chart-pie"></i><h2>76%</h2><p>Progress</p></div>
    </div>

</div>

<!-- PROFILE -->
<div class="profile-box" id="profileSection">
    <h2>Profile Section</h2>
    <p>John Doe - Student Account</p>
</div>

<!-- OTHER SECTIONS (PRESERVED) -->
<div class="box" id="bookmarksSection" style="display:none;">Bookmarks Section</div>
<div class="box" id="notificationsSection" style="display:none;">Notifications Section</div>
<div class="box" id="paymentsSection" style="display:none;">Payments Section</div>
<div class="box" id="progressSection" style="display:none;">Progress Section</div>
<div class="box" id="coursesSection" style="display:none;">Courses Section</div>
<div class="box" id="browseCoursesSection" style="display:none;">Browse Courses Section</div>
<div class="box" id="quizzesSection" style="display:none;">Quizzes Section</div>
<div class="box" id="settingsSection" style="display:none;">Settings Section</div>

</div>

<script>

function hideAllSections(){
    document.getElementById("dashboardContent").style.display="none";
    document.getElementById("profileSection").style.display="none";
    document.getElementById("bookmarksSection").style.display="none";
    document.getElementById("notificationsSection").style.display="none";
    document.getElementById("paymentsSection").style.display="none";
    document.getElementById("progressSection").style.display="none";
    document.getElementById("coursesSection").style.display="none";
    document.getElementById("browseCoursesSection").style.display="none";
    document.getElementById("quizzesSection").style.display="none";
    document.getElementById("settingsSection").style.display="none";
}

function showDashboard(){hideAllSections();document.getElementById("dashboardContent").style.display="block";}
function showProfile(){hideAllSections();document.getElementById("profileSection").style.display="block";}
function showBookmarks(){hideAllSections();document.getElementById("bookmarksSection").style.display="block";}
function showNotifications(){hideAllSections();document.getElementById("notificationsSection").style.display="block";}
function showPayments(){hideAllSections();document.getElementById("paymentsSection").style.display="block";}
function showProgress(){hideAllSections();document.getElementById("progressSection").style.display="block";}
function showCourses(){hideAllSections();document.getElementById("coursesSection").style.display="block";}
function showBrowseCourses(){hideAllSections();document.getElementById("browseCoursesSection").style.display="block";}
function showQuizzes(){hideAllSections();document.getElementById("quizzesSection").style.display="block";}
function showSettings(){hideAllSections();document.getElementById("settingsSection").style.display="block";}

</script>

</body>
</html>