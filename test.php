<?php
session_start();
include "db.php";

$student_id = $_SESSION['student_id'] ?? 0;

$message = "";

/* ====================================
   ENROLLMENT
==================================== */

if(isset($_POST['enroll'])){

    $course_id = $_POST['course_id'];

    // check existing enrollment
    $check = mysqli_query($conn,"
        SELECT * FROM enrollments
        WHERE student_id='$student_id'
        AND course_id='$course_id'
    ");

    if(mysqli_num_rows($check) > 0){

        $message = "Already enrolled.";

    } else {

        // fetch course
        $course = mysqli_fetch_assoc(mysqli_query($conn,"
            SELECT * FROM courses
            WHERE id='$course_id'
        "));

        // FREE = APPROVED
        // PAID = PENDING PAYMENT
        $status = ($course['course_type'] == 'Free')
            ? 'approved'
            : 'pending';

        mysqli_query($conn,"
            INSERT INTO enrollments(
                student_id,
                course_id,
                status
            )
            VALUES(
                '$student_id',
                '$course_id',
                '$status'
            )
        ");

        if($course['course_type'] == 'Free'){

            $message = "Successfully enrolled.";

        } else {

            $message = "Enrollment pending payment.";
        }
    }
}

/* ====================================
   FILTERS
==================================== */

$type = $_GET['type'] ?? '';

$where = "";

if($type == "free"){
    $where = "WHERE course_type='Free'";
}

if($type == "paid"){
    $where = "WHERE course_type='Paid'";
}

/* ====================================
   FETCH COURSES FROM DB
==================================== */

$courses = mysqli_query($conn,"
    SELECT * FROM courses
    $where
    ORDER BY id DESC
");

/* ====================================
   CHECK ACCESS
==================================== */

function hasAccess($conn,$student_id,$course_id){

    $sql = mysqli_query($conn,"
        SELECT * FROM enrollments
        WHERE student_id='$student_id'
        AND course_id='$course_id'
        AND status='approved'
    ");

    return mysqli_num_rows($sql) > 0;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Course Management</title>

<style>

body{
    margin:0;
    font-family:Arial;
    background:#f4f7fb;
}

/* MAIN */

.course-management{
    padding:40px;
}

.section-title{
    text-align:center;
    margin-bottom:40px;
}

.section-title h1{
    font-size:40px;
    margin-bottom:10px;
}

.section-title p{
    color:gray;
}

/* MESSAGE */

.message{
    background:#d4edda;
    color:green;
    padding:12px;
    border-radius:8px;
    margin-top:15px;
    display:inline-block;
}

/* FILTER BUTTONS */

.filters{
    display:flex;
    gap:15px;
    margin-bottom:30px;
    flex-wrap:wrap;
}

.filters a{
    text-decoration:none;
    padding:12px 20px;
    border-radius:8px;
    color:white;
    font-weight:bold;
}

.free-btn{
    background:green;
}

.paid-btn{
    background:crimson;
}

.all-btn{
    background:#111;
}

/* BOX */

.course-box{
    background:white;
    padding:25px;
    border-radius:15px;
    margin-bottom:30px;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
}

.course-box h2{
    margin-bottom:25px;
}

/* CARD */

.course-card{
    border:1px solid #ddd;
    padding:25px;
    border-radius:12px;
    margin-bottom:20px;
}

.course-card h3{
    margin-top:0;
}

.course-details{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    margin-top:20px;
    gap:10px;
}

.category{
    color:#444;
}

.type{
    color:white;
    padding:6px 14px;
    border-radius:20px;
    font-size:14px;
}

.free{
    background:green;
}

.paid{
    background:crimson;
}

/* BUTTONS */

.action-btn{
    margin-top:20px;
    padding:12px 20px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    color:white;
    font-weight:bold;
}

.enroll-btn{
    background:#007bff;
}

.pay-btn{
    background:orange;
}

.access-btn{
    background:green;
}

.locked-btn{
    background:gray;
}

/* CONTENT SECTION */

.content-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:20px;
    margin-top:20px;
}

.content-card{
    border:1px solid #ddd;
    padding:25px;
    border-radius:12px;
    background:#fafafa;
}

.content-card h3{
    margin-top:0;
}

/* RULES */

.rules-box{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:20px;
}

.rule{
    background:#111;
    color:white;
    padding:20px;
    border-radius:12px;
    text-align:center;
    font-weight:bold;
}

/* RESPONSIVE */

@media(max-width:768px){

    .course-management{
        padding:15px;
    }

    .section-title h1{
        font-size:28px;
    }

}

</style>
</head>

<body>

<section class="course-management">

    <!-- TITLE -->

    <div class="section-title">

        <h1>Course Management</h1>

        <p>
            Skill Transfer Program (STP)
            Learning Management System
        </p>

        <?php if($message){ ?>

            <div class="message">
                <?php echo $message; ?>
            </div>

        <?php } ?>

    </div>

    <!-- FILTERS -->

    <div class="filters">

        <a href="?type=free" class="free-btn">
            Free Courses
        </a>

        <a href="?type=paid" class="paid-btn">
            Paid Courses
        </a>

        <a href="course.php" class="all-btn">
            All Courses
        </a>

    </div>

    <!-- COURSE SECTION -->

    <div class="course-box">

        <h2>Available Courses</h2>

        <?php while($course = mysqli_fetch_assoc($courses)){ ?>

        <?php

        $course_id = $course['id'];

        $has_access = hasAccess(
            $conn,
            $student_id,
            $course_id
        );

        ?>

        <div class="course-card">

            <!-- TITLE -->

            <h3>
                <?php echo $course['title']; ?>
            </h3>

            <!-- DESCRIPTION -->

            <p>
                <?php echo $course['description']; ?>
            </p>

            <!-- DETAILS -->

            <div class="course-details">

                <span class="category">

                    Category:
                    <?php echo $course['category']; ?>

                </span>

                <span class="type
                    <?php echo strtolower($course['course_type']); ?>">

                    <?php echo $course['course_type']; ?>

                </span>

            </div>

            <!-- FREE COURSES -->

            <?php if($course['course_type'] == "Free"){ ?>

                <form method="POST">

                    <input type="hidden"
                           name="course_id"
                           value="<?php echo $course_id; ?>">

                    <button
                        class="action-btn enroll-btn"
                        name="enroll">

                        Enroll Free Course

                    </button>

                </form>

                <!-- COURSE CONTENT -->

                <div class="content-grid">

                    <?php

                    $contents = mysqli_query($conn,"
                        SELECT * FROM course_contents
                        WHERE course_id='$course_id'
                    ");

                    while($content = mysqli_fetch_assoc($contents)){

                    ?>

                    <div class="content-card">

                        <h3>
                            <?php echo $content['content_title']; ?>
                        </h3>

                        <p>
                            <?php echo $content['content_type']; ?>
                        </p>

                        <a href="<?php echo $content['content_file']; ?>"
                           target="_blank">

                            Access Content

                        </a>

                    </div>

                    <?php } ?>

                </div>

            <?php } ?>

            <!-- PAID COURSES -->

            <?php if($course['course_type'] == "Paid"){ ?>

                <?php if($has_access){ ?>

                    <button class="action-btn access-btn">

                        Access Paid Course

                    </button>

                    <!-- PAID CONTENT -->

                    <div class="content-grid">

                        <?php

                        $contents = mysqli_query($conn,"
                            SELECT * FROM course_contents
                            WHERE course_id='$course_id'
                        ");

                        while($content = mysqli_fetch_assoc($contents)){

                        ?>

                        <div class="content-card">

                            <h3>
                                <?php echo $content['content_title']; ?>
                            </h3>

                            <p>
                                <?php echo $content['content_type']; ?>
                            </p>

                            <a href="<?php echo $content['content_file']; ?>"
                               target="_blank">

                                Open Content

                            </a>

                        </div>

                        <?php } ?>

                    </div>

                <?php } else { ?>

                    <form method="POST">

                        <input type="hidden"
                               name="course_id"
                               value="<?php echo $course_id; ?>">

                        <button
                            class="action-btn pay-btn"
                            name="enroll">

                            Pay & Enroll First

                        </button>

                    </form>

                    <!-- LOCKED CONTENT -->

                    <div class="content-grid">

                        <div class="content-card">

                            <h3>🔒 Notes</h3>

                            <p>
                                Enroll to access PDF Notes
                            </p>

                        </div>

                        <div class="content-card">

                            <h3>🔒 Videos</h3>

                            <p>
                                Payment required to stream videos
                            </p>

                        </div>

                        <div class="content-card">

                            <h3>🔒 Quizzes</h3>

                            <p>
                                Enroll to attempt quizzes
                            </p>

                        </div>

                    </div>

                <?php } ?>

            <?php } ?>

        </div>

        <?php } ?>

    </div>

    <!-- RULES -->

    <div class="course-box">

        <h2>Learning Material Access Rules</h2>

        <div class="rules-box">

            <?php

            $rules = mysqli_query($conn,"
                SELECT * FROM learning_rules
                ORDER BY id DESC
            ");

            while($rule = mysqli_fetch_assoc($rules)){

            ?>

            <div class="rule">

                <?php echo $rule['rule_text']; ?>

            </div>

            <?php } ?>

        </div>

    </div>

</section>

</body>
</html>