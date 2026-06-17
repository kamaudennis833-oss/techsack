<?php
session_start();
include "db.php";

/* =========================
   USER
========================= */
$user_id = $_SESSION['user_id'] ?? 0;

/* =========================
   AJAX HANDLER (PROGRESS + REVIEW)
========================= */
if (isset($_POST['action'])) {

    /* ===== VIDEO PROGRESS ===== */
    if ($_POST['action'] === 'save_progress') {

        $content_id = intval($_POST['content_id']);
        $watched = intval($_POST['watched']);

        if ($user_id && $content_id) {

            $completed = ($watched >= 90) ? 1 : 0;

            mysqli_query($conn, "
                INSERT INTO video_progress (user_id, content_id, watched_percentage, completed)
                VALUES ($user_id, $content_id, $watched, $completed)
                ON DUPLICATE KEY UPDATE
                    watched_percentage = VALUES(watched_percentage),
                    completed = VALUES(completed),
                    updated_at = CURRENT_TIMESTAMP
            ");
        }
        exit;
    }

    /* ===== REVIEW (RATING + COMMENT) ===== */
    if ($_POST['action'] === 'add_review') {

        $rating = intval($_POST['rating']);
        $comment = mysqli_real_escape_string($conn, $_POST['comment']);

        mysqli_query($conn, "
            INSERT INTO course_reviews (user_id, course_id, rating, review)
            VALUES ($user_id, $course_id, $rating, '$comment')
            ON DUPLICATE KEY UPDATE
                rating = VALUES(rating),
                review = VALUES(review)
        ");

        exit;
    }
}

/* =========================
   COURSE ID
========================= */
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Course not found");
}

$course_id = intval($_GET['id']);

/* =========================
   COURSE
========================= */
$course_query = mysqli_query($conn, "
    SELECT 
        c.*,
        u.full_name AS instructor
    FROM courses c
    LEFT JOIN teachers t ON t.id = c.teacher_id
    LEFT JOIN users u ON u.id = t.user_id
    WHERE c.id = $course_id
");

if (!$course_query || mysqli_num_rows($course_query) == 0) {
    die("Course not found");
}

$course = mysqli_fetch_assoc($course_query);

/* =========================
   PROGRESS (REAL)
========================= */
$progress_query = mysqli_query($conn, "
    SELECT IFNULL(AVG(watched_percentage),0) AS progress
    FROM video_progress vp
    JOIN course_contents cc ON cc.id = vp.content_id
    WHERE vp.user_id = $user_id
    AND cc.course_id = $course_id
");

$progress = round(mysqli_fetch_assoc($progress_query)['progress'] ?? 0);

/* =========================
   NOTES
========================= */
$notes = mysqli_query($conn, "
    SELECT * FROM notes
    WHERE course_id = $course_id
    ORDER BY id DESC
");

/* =========================
   VIDEOS
========================= */
$videos = mysqli_query($conn, "
    SELECT * FROM course_videos
    WHERE course_id = $course_id
    ORDER BY id ASC
");

/* =========================
   QUIZZES
========================= */
$quizzes = mysqli_query($conn, "
    SELECT * FROM quizzes
    WHERE course_id = $course_id
    ORDER BY id DESC
");

/* =========================
   CONTENTS
========================= */
$contents = mysqli_query($conn, "
    SELECT * FROM course_contents
    WHERE course_id = $course_id
    ORDER BY id ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($course['title']) ?></title>

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial}
body{background:#f5f7fb;padding:30px}
.card{background:#fff;padding:20px;border-radius:12px;margin-bottom:20px}
.progress{width:100%;background:#e5e7eb;height:12px;border-radius:10px;overflow:hidden}
.progress-bar{height:100%;background:#10b981}
.item{padding:15px;border-bottom:1px solid #eee}
video{width:100%;border-radius:10px}
.btn{padding:10px 15px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer}
textarea,select{width:100%;padding:8px;margin-top:10px}
</style>

</head>
<body>

<!-- COURSE INFO -->
<div class="card">
    <h1><?= htmlspecialchars($course['title']) ?></h1>
    <p><?= htmlspecialchars($course['description']) ?></p>

    <p><b>Instructor:</b> <?= htmlspecialchars($course['instructor']) ?></p>
</div>

<!-- PROGRESS -->
<div class="card">
    <h3>Course Progress</h3>

    <div class="progress">
        <div class="progress-bar" style="width:<?= $progress ?>%"></div>
    </div>

    <p><?= $progress ?>% Completed</p>
</div>

<!-- VIDEOS (WITH TRACKING) -->
<div class="card">
<h2>Course Videos</h2>

<?php while ($video = mysqli_fetch_assoc($videos)) { ?>

    <div class="item">
        <h4><?= htmlspecialchars($video['title']) ?></h4>

        <?php if (!empty($video['video_path'])) { ?>

            <video controls class="video" data-id="<?= $video['id'] ?>">
                <source src="uploads/videos/<?= htmlspecialchars($video['video_path']) ?>">
            </video>

        <?php } else { ?>
            <p>Video file missing</p>
        <?php } ?>
    </div>

<?php } ?>
</div>

<!-- NOTES -->
<div class="card">
<h2>Course Notes</h2>

<?php while ($note = mysqli_fetch_assoc($notes)) { ?>

    <div class="item">
        <h4><?= htmlspecialchars($note['title']) ?></h4>
        <p><?= nl2br(htmlspecialchars($note['content'])) ?></p>

        <?php if (!empty($note['file_path'])) { ?>
            <a class="btn" href="uploads/notes/<?= htmlspecialchars($note['file_path']) ?>">Download</a>
        <?php } ?>
    </div>

<?php } ?>
</div>

<!-- MATERIALS -->
<div class="card">
<h2>Learning Materials</h2>

<?php while ($content = mysqli_fetch_assoc($contents)) { ?>

    <div class="item">
        <b><?= htmlspecialchars($content['content_title']) ?></b>
        <p><?= htmlspecialchars($content['content_description']) ?></p>
    </div>

<?php } ?>
</div>

<!-- QUIZZES -->
<div class="card">
<h2>Course Quizzes</h2>

<?php while ($quiz = mysqli_fetch_assoc($quizzes)) { ?>

    <div class="item">
        <h4><?= htmlspecialchars($quiz['title']) ?></h4>
        <p>Duration: <?= (int)$quiz['duration'] ?> min</p>
        <p>Passing: <?= (int)$quiz['passing_score'] ?>%</p>

        <a class="btn" href="take_quiz.php?id=<?= $quiz['id'] ?>">Start Quiz</a>
    </div>

<?php } ?>
</div>

<!-- RATING -->
<div class="card">
<h2>Rate Course</h2>

<select id="rating">
    <option value="5">5 ⭐</option>
    <option value="4">4 ⭐</option>
    <option value="3">3 ⭐</option>
    <option value="2">2 ⭐</option>
    <option value="1">1 ⭐</option>
</select>

<textarea id="comment" placeholder="Write comment..."></textarea>

<button class="btn" onclick="sendReview()">Submit</button>
</div>

<script>

/* =========================
   VIDEO TRACKING
========================= */
document.querySelectorAll(".video").forEach(video => {

    let id = video.dataset.id;
    let last = 0;

    video.addEventListener("timeupdate", function () {

        let percent = Math.floor((video.currentTime / video.duration) * 100);

        if (percent === last) return;
        last = percent;

        fetch(location.href, {
            method: "POST",
            headers: {"Content-Type":"application/x-www-form-urlencoded"},
            body: "action=save_progress&content_id=" + id + "&watched=" + percent
        });

    });

});

/* =========================
   SEND REVIEW
========================= */
function sendReview() {

    let rating = document.getElementById("rating").value;
    let comment = document.getElementById("comment").value;

    fetch(location.href, {
        method: "POST",
        headers: {"Content-Type":"application/x-www-form-urlencoded"},
        body: "action=add_review&rating=" + rating + "&comment=" + encodeURIComponent(comment)
    }).then(() => location.reload());

}

</script>

</body>
</html>