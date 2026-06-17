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

$user_id = (int)$_SESSION['user_id'];

/* =========================
   GET TEACHER INFO
========================= */
$stmt = $conn->prepare("
    SELECT t.*, u.full_name, u.email
    FROM teachers t
    LEFT JOIN users u ON u.id = t.user_id
    WHERE t.user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$teacherInfo = $stmt->get_result()->fetch_assoc();

if (!$teacherInfo) {
    die("Access denied: Not a teacher account");
}

/*
IMPORTANT:
course_teachers.teacher_id stores users.id
*/
$teacher_id = $user_id;

/* =========================
   COURSES (ONLY ONCE - FIXED)
========================= */
$stmtallCourses = $conn->prepare("
    SELECT
        c.id,
        c.title,
        c.description,
        COALESCE(c.price,0) AS price,
        c.created_at,

        (
            SELECT COUNT(DISTINCT e.user_id)
            FROM enrollments e
            WHERE e.course_id = c.id
        ) AS students,

        (
            SELECT COUNT(*)
            FROM course_contents cc
            WHERE cc.course_id = c.id
        ) AS contents

    FROM courses c
    INNER JOIN course_teachers ct
        ON ct.course_id = c.id
    WHERE ct.teacher_id = ?
      AND ct.status = 'active'
    ORDER BY c.created_at DESC
");

$stmtallCourses->bind_param("i", $teacher_id);
$stmtallCourses->execute();
$allcourses = $stmtallCourses->get_result();

/* =========================
   TOTAL COURSES
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT course_id) AS total
    FROM course_teachers
    WHERE teacher_id = ?
      AND status = 'active'
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$courses_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   TOTAL STUDENTS
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT e.user_id) AS total
    FROM enrollments e
    INNER JOIN course_teachers ct
        ON ct.course_id = e.course_id
    WHERE ct.teacher_id = ?
      AND ct.status = 'active'
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$students_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   NOTES COUNT
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM course_contents cc
    INNER JOIN course_teachers ct
        ON ct.course_id = cc.course_id
    WHERE ct.teacher_id = ?
      AND ct.status = 'active'
      AND cc.content_type = 'PDF'
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$notes_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   VIDEOS COUNT
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM course_contents cc
    INNER JOIN course_teachers ct
        ON ct.course_id = cc.course_id
    WHERE ct.teacher_id = ?
      AND ct.status = 'active'
      AND cc.content_type = 'Video'
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$videos_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   QUIZZES COUNT
========================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM quizzes q
    INNER JOIN course_teachers ct
        ON ct.course_id = q.course_id
    WHERE ct.teacher_id = ?
      AND ct.status = 'active'
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$quiz_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

/* =========================
   ACTIVITIES (FIXED)
========================= */
$stmt = $conn->prepare("
    SELECT message, created_at
    FROM activities
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$activities = $stmt->get_result();

/* =========================
   UPDATE COURSE
========================= */
if (isset($_POST['update_course'])) {

    $course_id = (int)$_POST['id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];

    $stmt = $conn->prepare("
        UPDATE courses c
        INNER JOIN course_teachers ct
            ON ct.course_id = c.id
        SET c.title = ?, c.description = ?, c.price = ?
        WHERE c.id = ?
          AND ct.teacher_id = ?
          AND ct.status = 'active'
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

    echo "<script>alert('Course Updated');</script>";
}

/* =========================
   DELETE COURSE (FIXED - GET)
========================= */
if (isset($_GET['delete_course'])) {

    $course_id = (int)$_GET['delete_course'];

    $stmt = $conn->prepare("
        DELETE FROM course_teachers
        WHERE course_id = ?
          AND teacher_id = ?
    ");

    $stmt->bind_param("ii", $course_id, $teacher_id);
    $stmt->execute();

    echo "<script>alert('Course Removed');</script>";
}

/* =========================
   UPLOAD DIRECTORIES (FIXED)
========================= */
$video_dir = "uploads/videos/";
$note_dir  = "uploads/notes/";

if (!file_exists($video_dir)) mkdir($video_dir, 0777, true);
if (!file_exists($note_dir)) mkdir($note_dir, 0777, true);


/* =====================================================
   UPLOAD VIDEO
===================================================== */
if (isset($_POST['upload_video'])) {

    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $access = $_POST['access_type'];

    $file_path = "";

    if (!empty($_FILES['video']['name'])) {

        $file_name = time() . "_" . basename($_FILES['video']['name']);
        $target = $video_dir . $file_name;

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
        $teacher_id
    );

    $stmt->execute();
}


/* =====================================================
   DELETE VIDEO (SECURE FIXED)
===================================================== */
if (isset($_GET['delete_video'])) {

    $id = (int)$_GET['delete_video'];

    $stmt = $conn->prepare("
        SELECT video_path
        FROM course_videos
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $old = $stmt->get_result()->fetch_assoc();

    if ($old && file_exists($old['video_path'])) {
        unlink($old['video_path']);
    }

    $stmt = $conn->prepare("
        DELETE FROM course_videos
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}


/* =====================================================
   VIEW VIDEO (SECURE FIXED)
===================================================== */
if (isset($_GET['view_video'])) {

    $id = (int)$_GET['view_video'];

    $stmt = $conn->prepare("
        SELECT v.*, c.title AS course_title
        FROM course_videos v
        INNER JOIN courses c ON c.id = v.course_id
        INNER JOIN course_teachers ct ON ct.course_id = c.id
        WHERE v.id = ?
          AND ct.teacher_id = ?
    ");

    $stmt->bind_param("ii", $id, $teacher_id);
    $stmt->execute();

    $video = $stmt->get_result()->fetch_assoc();

    if (!$video) {
        die("Video not found or access denied");
    }

    /* ENROLLMENT CHECK */
    $stmt = $conn->prepare("
        SELECT 1
        FROM enrollments
        WHERE user_id = ?
          AND course_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $user_id, $video['course_id']);
    $stmt->execute();

    $isEnrolled = $stmt->get_result()->num_rows > 0;

    if ($video['access_type'] === 'paid' && !$isEnrolled) {
        die("<h2>Access Denied ❌</h2><p>You must enroll to watch this video.</p>");
    }

    ?>

    <div style="background:#000;padding:20px;color:#fff;">
        <h2><?= htmlspecialchars($video['title']) ?></h2>

        <video width="100%" controls controlsList="nodownload" oncontextmenu="return false">
            <source src="<?= htmlspecialchars($video['video_path']) ?>" type="video/mp4">
        </video>
    </div>

    <script>
        document.addEventListener("contextmenu", e => e.preventDefault());
    </script>

<?php exit; }


/* =====================================================
   FETCH VIDEOS (TEACHER ONLY)
===================================================== */
$videos = $conn->query("
    SELECT v.*, c.title AS course_title
    FROM course_videos v
    JOIN courses c ON c.id = v.course_id
    JOIN course_teachers ct ON ct.course_id = c.id
    WHERE ct.teacher_id = $teacher_id
    ORDER BY v.id DESC
");


/* =====================================================
   ADD NOTE
===================================================== */
if (isset($_POST['add_note'])) {

    /* -------------------------
       VERIFY TOKEN
    ------------------------- */
    if (
        !isset($_POST['note_token']) ||
        !hash_equals($_SESSION['note_token'], $_POST['note_token'])
    ) {
        die("Invalid or duplicate submission.");
    }

    $course_id = (int)$_POST['course_id'];

    /* -------------------------
       VERIFY COURSE OWNERSHIP
    ------------------------- */
    $check = $conn->prepare("
        SELECT 1
        FROM course_teachers
        WHERE course_id = ?
        AND teacher_id = ?
        LIMIT 1
    ");

    $check->bind_param("ii", $course_id, $teacher_id);
    $check->execute();

    if ($check->get_result()->num_rows == 0) {
        die("❌ Unauthorized course access");
    }

    $title       = trim($_POST['title'] ?? '');
    $content     = trim($_POST['content'] ?? '');
    $access_type = $_POST['access_type'] ?? 'free';

    $file_path = "";

    /* -------------------------
       FILE UPLOAD
    ------------------------- */
    if (
        isset($_FILES['file']) &&
        !empty($_FILES['file']['name'])
    ) {

        $file_name = time() . "_" . basename($_FILES['file']['name']);
        $target = $note_dir . $file_name;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            $file_path = $target;
        }
    }

    /* -------------------------
       INSERT NOTE
    ------------------------- */
    $stmt = $conn->prepare("
        INSERT INTO notes
        (
            course_id,
            title,
            content,
            access_type,
            created_by,
            file_path
        )
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

    if ($stmt->execute()) {

        /* Generate new token */
        $_SESSION['note_token'] = bin2hex(random_bytes(32));

        $_SESSION['success_message'] =
            "Note added successfully.";

        /* IMPORTANT:
           Redirect to same page
           prevents resubmission on refresh
        */
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;

    } else {

        $_SESSION['error_message'] =
            "Failed to save note.";

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

/* =====================================================
   SUCCESS / ERROR MESSAGE
===================================================== */
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success">'
        . htmlspecialchars($_SESSION['success_message'])
        . '</div>';

    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger">'
        . htmlspecialchars($_SESSION['error_message'])
        . '</div>';

    unset($_SESSION['error_message']);
}

/* =====================================================
   FETCH NOTES
===================================================== */
$notes = $conn->query("
    SELECT n.*, c.title AS course_title
    FROM notes n
    JOIN courses c ON c.id = n.course_id
    JOIN course_teachers ct ON ct.course_id = c.id
    WHERE ct.teacher_id = $teacher_id
    ORDER BY n.id DESC
");

/* =====================================================
   DELETE NOTE
===================================================== */
if (isset($_GET['delete_note'])) {

    $id = (int)$_GET['delete_note'];

    $stmt = $conn->prepare("
        DELETE n
        FROM notes n
        JOIN course_teachers ct
            ON ct.course_id = n.course_id
        WHERE n.id = ?
        AND ct.teacher_id = ?
    ");

    $stmt->bind_param("ii", $id, $teacher_id);
    $stmt->execute();

    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* =====================================================
   VIEW NOTE
===================================================== */
if (isset($_GET['view_note'])) {

    $note_id = (int)$_GET['view_note'];

    $stmt = $conn->prepare("
        SELECT n.*, c.title AS course_title
        FROM notes n
        JOIN courses c ON c.id = n.course_id
        JOIN course_teachers ct ON ct.course_id = c.id
        WHERE n.id = ?
        AND ct.teacher_id = ?
    ");

    $stmt->bind_param("ii", $note_id, $teacher_id);
    $stmt->execute();

    $note = $stmt->get_result()->fetch_assoc();

    if (!$note) {
        die("Note not found");
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM enrollments
        WHERE user_id = ?
        AND course_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $user_id, $note['course_id']);
    $stmt->execute();

    $isEnrolled = $stmt->get_result()->num_rows > 0;

    if (
        $note['access_type'] === 'paid' &&
        !$isEnrolled
    ) {
        die("Access Denied ❌");
    }
}

/* =====================================================
   FETCH NOTES (TEACHER ONLY)
===================================================== */
$notes = $conn->query("
    SELECT n.*, c.title AS course_title
    FROM notes n
    JOIN courses c ON c.id = n.course_id
    JOIN course_teachers ct ON ct.course_id = c.id
    WHERE ct.teacher_id = $teacher_id
    ORDER BY n.id DESC
");


/* =====================================================
   CREATE QUIZ (SECURE FIXED)
===================================================== */
if (isset($_POST['create_quiz'])) {

    $course_id = (int)$_POST['course_id'];

    $check = $conn->prepare("
        SELECT 1
        FROM course_teachers
        WHERE course_id = ?
          AND teacher_id = ?
        LIMIT 1
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
        $_POST['title'],
        $_POST['description'],
        $_POST['passing_marks'],
        $_POST['duration']
    );

    $stmt->execute();
}


/* =====================================================
   ADD QUESTION (SECURE FIXED)
===================================================== */
if (isset($_POST['add_question'])) {

    $quiz_id = (int)$_POST['quiz_id'];

    $check = $conn->prepare("
        SELECT 1
        FROM quizzes q
        JOIN course_teachers ct ON ct.course_id = q.course_id
        WHERE q.id = ?
          AND ct.teacher_id = ?
        LIMIT 1
    ");

    $check->bind_param("ii", $quiz_id, $teacher_id);
    $check->execute();

    if ($check->get_result()->num_rows == 0) {
        die("❌ Unauthorized quiz access");
    }

    $stmt = $conn->prepare("
        INSERT INTO quiz_questions
        (quiz_id, question, question_type,
         option_a, option_b, option_c, option_d,
         correct_answer, marks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssssssi",
        $quiz_id,
        $_POST['question'],
        $_POST['question_type'],
        $_POST['option_a'],
        $_POST['option_b'],
        $_POST['option_c'],
        $_POST['option_d'],
        $_POST['correct_answer'],
        $_POST['marks']
    );

    $stmt->execute();
}


/* =====================================================
   QUIZ STATS (SAFE FIXED)
===================================================== */

function fetchCount($conn, $sql, $teacher_id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'] ?? 0;
}

$total_quizzes = fetchCount($conn, "
    SELECT COUNT(*) AS total
    FROM quizzes q
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = ?
", $teacher_id);

$total_questions = fetchCount($conn, "
    SELECT COUNT(*) AS total
    FROM quiz_questions qq
    JOIN quizzes q ON q.id = qq.quiz_id
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = ?
", $teacher_id);

$total_attempts = fetchCount($conn, "
    SELECT COUNT(*) AS total
    FROM quiz_attempts qa
    JOIN quizzes q ON q.id = qa.quiz_id
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = ?
", $teacher_id);

$passed = fetchCount($conn, "
    SELECT COUNT(*) AS total
    FROM quiz_attempts qa
    JOIN quizzes q ON q.id = qa.quiz_id
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = ? AND qa.result='Pass'
", $teacher_id);

$failed = fetchCount($conn, "
    SELECT COUNT(*) AS total
    FROM quiz_attempts qa
    JOIN quizzes q ON q.id = qa.quiz_id
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = ? AND qa.result='Fail'
", $teacher_id);

$stmt = $conn->prepare("
    SELECT AVG(qa.score) AS avg_score
    FROM quiz_attempts qa
    JOIN quizzes q ON q.id = qa.quiz_id
    JOIN course_teachers ct ON ct.course_id = q.course_id
    WHERE ct.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();

$average_score = $stmt->get_result()->fetch_assoc()['avg_score'] ?? 0;
/* =====================================================
   STUDENT ACTIONS (SECURE FIXED)
===================================================== */
if (isset($_POST['student_action'])) {

    $student_id = (int)$_POST['student_id'];
    $action = $_POST['action'];

    // Get user_id from student table
    $stmt = $conn->prepare("
        SELECT user_id
        FROM students
        WHERE id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if (!$student) {
        die("Student not found");
    }

    $userId = $student['user_id'];

    if ($action === "activate") {

        $stmt = $conn->prepare("UPDATE students SET status='active' WHERE id=?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE users SET status='active' WHERE id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }

    if ($action === "suspend") {

        $stmt = $conn->prepare("UPDATE students SET status='suspended' WHERE id=?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE users SET status='suspended' WHERE id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }

    if ($action === "reset_password") {

        $newPass = password_hash("123456", PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $newPass, $userId);
        $stmt->execute();
    }

    if ($action === "delete") {

        $stmt = $conn->prepare("DELETE FROM enrollments WHERE user_id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM students WHERE id=?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
}


/* =====================================================
   ENROLLMENT ACTIONS (SECURE FIXED)
===================================================== */
if (isset($_POST['enroll_action'])) {

    $enroll_id = (int)$_POST['enroll_id'];
    $action = $_POST['action'];

    $check = $conn->prepare("
        SELECT e.id
        FROM enrollments e
        JOIN course_teachers ct ON ct.course_id = e.course_id
        WHERE e.id = ? AND ct.teacher_id = ?
    ");
    $check->bind_param("ii", $enroll_id, $teacher_id);
    $check->execute();

    if ($check->get_result()->num_rows == 0) {
        die("Unauthorized enrollment action");
    }

    if ($action === "approve") {

        $stmt = $conn->prepare("
            UPDATE enrollments 
            SET status='approved' 
            WHERE id=?
        ");
        $stmt->bind_param("i", $enroll_id);
        $stmt->execute();

    } elseif ($action === "reject") {

        $stmt = $conn->prepare("
            UPDATE enrollments 
            SET status='rejected' 
            WHERE id=?
        ");
        $stmt->bind_param("i", $enroll_id);
        $stmt->execute();

    } elseif ($action === "delete") {

        $stmt = $conn->prepare("
            DELETE FROM enrollments 
            WHERE id=?
        ");
        $stmt->bind_param("i", $enroll_id);
        $stmt->execute();
    }
}
/* =====================================================
   FETCH STUDENTS (SAFE)
===================================================== */
$stmt = $conn->prepare("
SELECT DISTINCT s.*
FROM students s
JOIN enrollments e ON e.user_id = s.user_id
JOIN course_teachers ct ON ct.course_id = e.course_id
WHERE ct.teacher_id = ?
ORDER BY s.id DESC
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$students = $stmt->get_result();


/* =====================================================
   FETCH ENROLLMENTS (SAFE)
===================================================== */
$stmt = $conn->prepare("
SELECT e.*, u.full_name, c.title
FROM enrollments e
JOIN users u ON u.id = e.user_id
JOIN courses c ON c.id = e.course_id
JOIN course_teachers ct ON ct.course_id = c.id
WHERE ct.teacher_id = ?
ORDER BY e.id DESC
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$enrollments = $stmt->get_result();


/* =====================================================
   TOTAL STATS (SAFE FUNCTION)
===================================================== */
function getCount($conn, $sql, $teacher_id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['total'] ?? 0;
}

$total_courses = getCount($conn, "
SELECT COUNT(*) AS total
FROM course_teachers
WHERE teacher_id = ?
", $teacher_id);

$total_students = getCount($conn, "
SELECT COUNT(DISTINCT e.user_id) AS total
FROM enrollments e
JOIN course_teachers ct ON ct.course_id = e.course_id
WHERE ct.teacher_id = ?
", $teacher_id);

$total_content = getCount($conn, "
SELECT COUNT(*) AS total
FROM course_contents cc
JOIN course_teachers ct ON ct.course_id = cc.course_id
WHERE ct.teacher_id = ?
", $teacher_id);

$total_videos = getCount($conn, "
SELECT COUNT(*) AS total
FROM course_videos v
JOIN course_teachers ct ON ct.course_id = v.course_id
WHERE ct.teacher_id = ?
", $teacher_id);

$total_notes = getCount($conn, "
SELECT COUNT(*) AS total
FROM notes n
JOIN course_teachers ct ON ct.course_id = n.course_id
WHERE ct.teacher_id = ?
", $teacher_id);


/* =====================================================
   REVENUE
===================================================== */
$stmt = $conn->prepare("
SELECT COALESCE(SUM(p.amount),0) AS total_revenue
FROM payments p
JOIN enrollments e ON e.user_id = p.user_id
JOIN course_teachers ct ON ct.course_id = e.course_id
WHERE ct.teacher_id = ?
AND p.status='success'
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$revenue = $stmt->get_result()->fetch_assoc()['total_revenue'];


/* =====================================================
   TOP COURSES
===================================================== */
$top_courses = $conn->query("
SELECT c.title, COUNT(e.id) AS students
FROM courses c
JOIN course_teachers ct ON ct.course_id = c.id
LEFT JOIN enrollments e ON e.course_id = c.id
WHERE ct.teacher_id = $teacher_id
GROUP BY c.id, c.title
ORDER BY students DESC
LIMIT 5
");


/* =====================================================
   COURSES LIST
===================================================== */
$courses = $conn->query("
SELECT c.id, c.title
FROM courses c
JOIN course_teachers ct ON ct.course_id = c.id
WHERE ct.teacher_id = $teacher_id
ORDER BY c.title
");


if (isset($_POST['add_announcement'])) {

    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);

    if ($course_id <= 0 || empty($title) || empty($message)) {
        die("Invalid input");
    }

    // Ensure teacher owns the course
    $check = $conn->prepare("
        SELECT 1
        FROM course_teachers
        WHERE course_id = ?
          AND teacher_id = ?
        LIMIT 1
    ");

    $check->bind_param("ii", $course_id, $teacher_id);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {
        die("Unauthorized course access");
    }

    // Insert announcement
    $stmt = $conn->prepare("
        INSERT INTO announcements (course_id, teacher_id, title, message)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param("iiss", $course_id, $teacher_id, $title, $message);
    $stmt->execute();
}

if (isset($_GET['delete_announcement'])) {

    $id = (int)$_GET['delete_announcement'];

    $stmt = $conn->prepare("
        DELETE a
        FROM announcements a
        INNER JOIN course_teachers ct ON ct.course_id = a.course_id
        WHERE a.id = ?
          AND ct.teacher_id = ?
    ");

    $stmt->bind_param("ii", $id, $teacher_id);
    $stmt->execute();
}

$stmt = $conn->prepare("
    SELECT 
        a.id,
        a.title,
        a.message,
        a.created_at,
        c.title AS course
    FROM announcements a
    INNER JOIN course_teachers ct ON ct.course_id = a.course_id
    INNER JOIN courses c ON c.id = a.course_id
    WHERE ct.teacher_id = ?
    ORDER BY a.created_at DESC
");

$stmt->bind_param("i", $teacher_id);
$stmt->execute();

$announcements = $stmt->get_result();
$announcementCourses = $conn->prepare("
    SELECT c.id, c.title
    FROM courses c
    INNER JOIN course_teachers ct ON ct.course_id = c.id
    WHERE ct.teacher_id = ?
    ORDER BY c.title
");

$announcementCourses->bind_param("i", $teacher_id);
$announcementCourses->execute();

$announcementList = $announcementCourses->get_result();

/* =====================================================
   PROFILE UPDATE (FIXED)
===================================================== */
if (isset($_POST['update_profile'])) {

    $specialization = trim($_POST['specialization'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $experience_years = (int)($_POST['experience_years'] ?? 0);

    if ($experience_years < 0 || $experience_years > 60) {
        die("Invalid experience value");
    }

    // FIX: correct column is user_id, NOT id
    $stmt = $conn->prepare("
        UPDATE teachers
        SET specialization = ?,
            qualification = ?,
            experience_years = ?
        WHERE user_id = ?
    ");

    $stmt->bind_param(
        "ssii",
        $specialization,
        $qualification,
        $experience_years,
        $user_id
    );

    $stmt->execute();
}
$teacherCoursesList = $conn->prepare("
    SELECT c.id, c.title, c.price, c.status
    FROM courses c
    INNER JOIN course_teachers ct ON ct.course_id = c.id
    WHERE ct.teacher_id = ?
    ORDER BY c.created_at DESC
");

$teacherCoursesList->bind_param("i", $teacher_id);
$teacherCoursesList->execute();

$teacherCourses = $teacherCoursesList->get_result();
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

            <?php while($row = $teacherCourses->fetch_assoc()) { ?>
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
    <td><?php echo htmlspecialchars($row['title'] ?? ''); ?></td>

    <td>
        KES <?php echo number_format($row['price'] ?? 0); ?>
    </td>

    <td>
        <b><?php echo $enrolled ?? 0; ?></b>
    </td>

    <td>
        <?php echo htmlspecialchars($row['status'] ?? 'Inactive'); ?>
    </td>
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
.container{
    padding:20px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
    gap:15px;
}

.card{
    background:#fff;
    padding:15px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.card h3{
    margin-top:0;
    color:#111827;
}

.btn{
    padding:8px 12px;
    border:none;
    border-radius:5px;
    color:#fff;
    cursor:pointer;
    font-size:12px;
    text-decoration:none;
    display:inline-block;
}

.view{background:#16a34a;}
.edit{background:#f59e0b;}
.del{background:#dc2626;}
.content{background:#2563eb;}

.modal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,.6);
    justify-content:center;
    align-items:center;
    z-index:9999;
}

.modal-box{
    background:#fff;
    padding:20px;
    width:500px;
    max-width:95%;
    border-radius:10px;
}

.modal-box input,
.modal-box textarea{
    width:100%;
    padding:10px;
    border:1px solid #ddd;
    border-radius:5px;
    box-sizing:border-box;
}
</style>

<div class="container">

<h2>📚 My Courses</h2>

<div class="grid">

<?php if (!empty($courses) && $courses->num_rows > 0): ?>

    <?php while($row = $allcourses->fetch_assoc()): ?>

        <?php
            $id = (int)$row['id'];
            $title = htmlspecialchars($row['title'] ?? '');
            $desc = htmlspecialchars($row['description'] ?? '');
            $price = (float)($row['price'] ?? 0);
        ?>

        <div class="card">

            <h3><?= $title ?></h3>

            <?php if (!empty($desc)): ?>
                <p><?= $desc ?></p>
            <?php endif; ?>

            <p>👨‍🎓 Students: <b><?= (int)($row['students'] ?? 0) ?></b></p>

            <p>📂 Content: <b><?= (int)($row['contents'] ?? 0) ?></b></p>

            <p>💰 Price: <b>KES <?= number_format($price,2) ?></b></p>

            <hr>

            <div style="display:flex;gap:5px;flex-wrap:wrap;">

                <button
                    type="button"
                    class="btn edit"
                    onclick="editCourse(
                        <?= $id ?>,
                        `<?= addslashes($row['title'] ?? '') ?>`,
                        `<?= addslashes($row['description'] ?? '') ?>`,
                        <?= $price ?>
                    )">
                    Edit
                </button>

                <a
                    class="btn del"
                    href="?delete_course=<?= $id ?>"
                    onclick="return confirm('Delete this course?')">
                    Delete
                </a>

            </div>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <div class="card">
        <h3>No Courses Found</h3>
        <p>No courses have been assigned to you yet.</p>
    </div>

<?php endif; ?>

</div>

</div>

<!-- MODAL -->
<div class="modal" id="modal">
    <div class="modal-box" id="modalBox"></div>
</div>

<script>

function openModal(html){
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('modalBox').innerHTML = html;
}

function editCourse(id,title,desc,price){

    let form = `
        <h3>Edit Course</h3>

        <form method="POST">

            <input type="hidden" name="id" value="${id}">

            <label>Title</label><br>
            <input type="text" name="title" value="${title}" required>
            <br><br>

            <label>Description</label><br>
            <textarea name="description" rows="5">${desc}</textarea>
            <br><br>

            <label>Price</label><br>
            <input type="number" step="0.01" name="price" value="${price}" required>
            <br><br>

            <button class="btn edit" name="update_course">
                Update Course
            </button>

        </form>
    `;

    openModal(form);
}

window.onclick = function(e){
    if(e.target.id === 'modal'){
        document.getElementById('modal').style.display = 'none';
    }
};

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

    <?php while ($c = $announcementList->fetch_assoc()) { ?>
        <option value="<?= $c['id'] ?>">
            <?= htmlspecialchars($c['title']) ?>
        </option>
    <?php } ?>

</select>

<input type="text" name="title" placeholder="Announcement Title" required>

<textarea name="message" placeholder="Write message..." required></textarea>

<button type="submit" name="add_announcement"
        style="border-radius:4px; margin:10px;">
    Post Announcement
</button>

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

<?php if ($announcements->num_rows > 0): ?>

    <?php while ($a = $announcements->fetch_assoc()) { ?>

        <tr>
            <td><?= htmlspecialchars($a['course']) ?></td>
            <td><?= htmlspecialchars($a['title']) ?></td>
            <td><?= htmlspecialchars($a['message']) ?></td>
            <td><?= $a['created_at'] ?></td>
            <td>
                <a class="delete"
                   href="?delete_announcement=<?= $a['id'] ?>"
                   onclick="return confirm('Delete announcement?')">
                    Delete
                </a>
            </td>
        </tr>

    <?php } ?>

<?php else: ?>

    <tr>
        <td colspan="5" style="text-align:center;">
            No announcements found
        </td>
    </tr>

<?php endif; ?>

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