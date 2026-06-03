<?php

include "db.php";

/* COURSE ID */

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Course not found");
}

$course_id = (int)$_GET['id'];

/* COURSE */

$course_query = mysqli_query(
    $conn,
    "SELECT * FROM courses WHERE id='$course_id'"
);

if (!$course_query || mysqli_num_rows($course_query) == 0) {
    die("Course not found");
}

$course = mysqli_fetch_assoc($course_query);

/* PUBLIC PAGE */

$progress = 0;

/* NOTES */

$notes = mysqli_query(
    $conn,
    "SELECT * FROM notes
     WHERE course_id='$course_id'
     ORDER BY id DESC"
);

/* VIDEOS */

$videos = mysqli_query(
    $conn,
    "SELECT * FROM course_videos
     WHERE course_id='$course_id'
     ORDER BY id ASC"
);

/* QUIZZES */

$quizzes = mysqli_query(
    $conn,
    "SELECT * FROM quizzes
     WHERE course_id='$course_id'
     ORDER BY id DESC"
);

/* CONTENTS */

$contents = mysqli_query(
    $conn,
    "SELECT * FROM course_contents
     WHERE course_id='$course_id'
     ORDER BY id ASC"
);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($course['title']) ?></title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial,sans-serif;
}

body{
    background:#f5f7fb;
    padding:30px;
}

.card{
    background:#fff;
    padding:20px;
    border-radius:12px;
    margin-bottom:20px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

h1,h2,h3{
    margin-bottom:15px;
}

.btn{
    display:inline-block;
    background:#2563eb;
    color:#fff;
    padding:10px 15px;
    border-radius:8px;
    text-decoration:none;
    margin-top:10px;
}

.btn:hover{
    background:#1d4ed8;
}

.progress{
    width:100%;
    background:#e5e7eb;
    height:12px;
    border-radius:10px;
    overflow:hidden;
}

.progress-bar{
    height:100%;
    background:#10b981;
}

video{
    width:100%;
    max-height:500px;
    border-radius:10px;
}

.item{
    padding:15px;
    border-bottom:1px solid #e5e7eb;
}

.item:last-child{
    border-bottom:none;
}

.course-meta{
    color:#6b7280;
    margin-top:10px;
}

</style>

</head>
<body>

<!-- COURSE INFO -->

<div class="card">

    <h1><?= htmlspecialchars($course['title']) ?></h1>

    <p>
        <?= htmlspecialchars($course['description']) ?>
    </p>

    <div class="course-meta">

        Instructor:
        <strong><?= htmlspecialchars($course['instructor']) ?></strong>

        <br><br>

        Category:
        <strong><?= htmlspecialchars($course['category']) ?></strong>

        <br><br>

        Lessons:
        <strong><?= $course['lessons'] ?></strong>

    </div>

</div>

<!-- PROGRESS -->

<div class="card">

    <h3>Course Progress</h3>

    <div class="progress">
        <div
            class="progress-bar"
            style="width:<?= $progress ?>%;">
        </div>
    </div>

    <br>

    <strong><?= $progress ?>% Completed</strong>

</div>

<!-- VIDEOS -->

<div class="card">

    <h2>Course Videos</h2>

    <?php if(mysqli_num_rows($videos) > 0){ ?>

        <?php while($video = mysqli_fetch_assoc($videos)){ ?>

            <div class="item">

                <h4>
                    <?= htmlspecialchars($video['title']) ?>
                </h4>

                <br>

                <?php if(!empty($video['video_path'])){ ?>

                    <video controls>

                        <source
                            src="uploads/videos/<?= htmlspecialchars($video['video_path']) ?>"
                            type="video/mp4">

                        Your browser does not support videos.

                    </video>

                <?php } else { ?>

                    <p>Video file missing.</p>

                <?php } ?>

            </div>

        <?php } ?>

    <?php } else { ?>

        <p>No videos uploaded.</p>

    <?php } ?>

</div>

<!-- NOTES -->

<div class="card">

    <h2>Course Notes</h2>

    <?php if(mysqli_num_rows($notes) > 0){ ?>

        <?php while($note = mysqli_fetch_assoc($notes)){ ?>

            <div class="item">

                <h4>
                    <?= htmlspecialchars($note['title']) ?>
                </h4>

                <br>

                <p>
                    <?= nl2br(htmlspecialchars($note['content'])) ?>
                </p>

                <?php if(!empty($note['file_path'])){ ?>

                    <a
                        href="uploads/notes/<?= htmlspecialchars($note['file_path']) ?>"
                        target="_blank"
                        class="btn">

                        Download Note

                    </a>

                <?php } ?>

            </div>

        <?php } ?>

    <?php } else { ?>

        <p>No notes available.</p>

    <?php } ?>

</div>

<!-- LEARNING MATERIALS -->

<div class="card">

    <h2>Learning Materials</h2>

    <?php if(mysqli_num_rows($contents) > 0){ ?>

        <?php while($content = mysqli_fetch_assoc($contents)){ ?>

            <div class="item">

                <strong>
                    <?= htmlspecialchars($content['content_title']) ?>
                </strong>

                <br><br>

                Type:
                <?= htmlspecialchars($content['content_type']) ?>

                <br><br>

                <?= htmlspecialchars($content['content_description']) ?>

            </div>

        <?php } ?>

    <?php } else { ?>

        <p>No learning materials available.</p>

    <?php } ?>

</div>

<!-- QUIZZES -->

<div class="card">

    <h2>Course Quizzes</h2>

    <?php if(mysqli_num_rows($quizzes) > 0){ ?>

        <?php while($quiz = mysqli_fetch_assoc($quizzes)){ ?>

            <div class="item">

                <h4>
                    <?= htmlspecialchars($quiz['title']) ?>
                </h4>

                <br>

                <p>
                    Duration:
                    <?= $quiz['duration'] ?> Minutes
                </p>

                <p>
                    Passing Score:
                    <?= $quiz['passing_score'] ?>%
                </p>

                <a
                    href="take_quiz.php?id=<?= $quiz['id'] ?>"
                    class="btn">

                    Start Quiz

                </a>

            </div>

        <?php } ?>

    <?php } else { ?>

        <p>No quizzes available.</p>

    <?php } ?>

</div>

</body>
</html>