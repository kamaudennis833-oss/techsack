<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "db.php";

/* =========================
   AUTH CHECK
========================= */
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("Unauthorized access");
}

/* =========================
   ROLE NORMALIZATION
========================= */
$user_role = strtolower(trim($_SESSION['role'] ?? ''));

/* =========================
   CSRF INIT (ONLY ONCE)
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   UPLOAD FOLDER
========================= */
$upload_dir = "uploads/videos/temp/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

/* =========================
   =========================
   SINGLE POST HANDLER ONLY
   =========================
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* CSRF CHECK */
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    /* =========================
       COURSE CREATE
    ========================= */
    if (isset($_POST['create_course'])) {

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $teacher_id = intval($_POST['teacher_id'] ?? 0);

        if ($title && $description && $category && $teacher_id) {

            $stmt = $conn->prepare("
                INSERT INTO courses (title, description, category, teacher_id)
                VALUES (?,?,?,?)
            ");

            $stmt->bind_param("sssi", $title, $description, $category, $teacher_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['success'] = "Course created";
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    /* =========================
       VIDEO UPLOAD
    ========================= */
    if (isset($_POST['upload_video'])) {

        if (!in_array($user_role, ['teacher', 'admin'])) {
            die("Unauthorized");
        }

        $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
        $title = trim($_POST['title'] ?? '');
        $access_type = $_POST['access_type'] ?? 'free';
        $cloud_url = trim($_POST['cloud_url'] ?? '');

        if (!$course_id || !$title) {
            die("Invalid input");
        }

        $local_path = null;
        $file_size = 0;
        $mime = null;

        /* CLOUD */
        if (!empty($cloud_url)) {

            if (!filter_var($cloud_url, FILTER_VALIDATE_URL)) {
                die("Invalid URL");
            }

            $mime = "external/link";
        }

        /* LOCAL UPLOAD */
        elseif (!empty($_FILES['video']['name'])) {

            $allowed_ext = ['mp4', 'webm', 'ogg'];
            $allowed_mime = ['video/mp4', 'video/webm', 'video/ogg'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['video']['tmp_name']);
            finfo_close($finfo);

            $ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));

            if (!in_array($mime, $allowed_mime) || !in_array($ext, $allowed_ext)) {
                die("Invalid video format");
            }

            $file_name = bin2hex(random_bytes(16)) . "." . $ext;
            $local_path = $upload_dir . $file_name;

            move_uploaded_file($_FILES['video']['tmp_name'], $local_path);
            $file_size = $_FILES['video']['size'];
        } else {
            die("Provide video or link");
        }

        $stmt = $conn->prepare("
            INSERT INTO course_videos
            (course_id, title, local_path, cloud_url, upload_status, uploaded_by, file_size, mime_type, access_type)
            VALUES (?, ?, ?, ?, 'local', ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isssiiis",
            $course_id,
            $title,
            $local_path,
            $cloud_url,
            $user_id,
            $file_size,
            $mime,
            $access_type
        );

        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = "Video uploaded";

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    /* =========================
       DELETE VIDEO
    ========================= */
    if (isset($_POST['delete_video'])) {

        if (!in_array($user_role, ['teacher', 'admin'])) {
            die("Unauthorized");
        }

        $id = filter_input(INPUT_POST, 'video_id', FILTER_VALIDATE_INT);

        $stmt = $conn->prepare("SELECT local_path FROM course_videos WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $video = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($video && file_exists($video['local_path'])) {
            unlink($video['local_path']);
        }

        $stmt = $conn->prepare("DELETE FROM course_videos WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = "Video deleted";

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

/* =========================
   FETCH VIDEOS
========================= */
$stmt = $conn->prepare("
    SELECT v.*, c.title AS course
    FROM course_videos v
    JOIN courses c ON c.id = v.course_id
    ORDER BY v.id DESC
");

$stmt->execute();
$videos = $stmt->get_result();

?>