<?php
session_start();
require_once "db.php";

/* ==========================
   USER AUTH
========================== */
$user_id = $_SESSION['user_id'] ?? 0;
$user_role = strtolower(trim($_SESSION['role'] ?? ''));

if (!$user_id) {
    die("Unauthorized");
}

/* ==========================
   CSRF TOKEN
========================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ==========================
   HELPERS
========================== */
function isValidVideo($tmpFile) {
    $allowed = ['video/mp4', 'video/webm', 'video/ogg'];
    return in_array(mime_content_type($tmpFile), $allowed);
}

function convertDriveLink($url) {
    if (strpos($url, 'drive.google.com') !== false) {
        preg_match('/\/d\/(.*?)\//', $url, $m);
        if (!empty($m[1])) {
            return "https://drive.google.com/file/d/{$m[1]}/preview";
        }
    }
    return $url;
}

/* ==========================
   EDIT FETCH
========================== */
$editVideo = null;

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM course_videos WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $editVideo = $stmt->get_result()->fetch_assoc();
}

/* ==========================
   UPLOAD VIDEO
========================== */
if (isset($_POST['upload_video'])) {

    if (!in_array($user_role, ['admin','teacher'])) {
        die("Unauthorized");
    }

    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF Error");
    }

    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $access_type = $_POST['access_type'];
    $cloud_url = trim($_POST['cloud_url']);

    $local_path = null;
    $file_size = 0;
    $mime_type = null;
    $upload_status = 'cloud';

    /* FILE UPLOAD */
    if (!empty($_FILES['video']['name'])) {

        if (!isValidVideo($_FILES['video']['tmp_name'])) {
            die("Only MP4, WEBM, OGG allowed");
        }

        $upload_dir = "uploads/videos/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = time() . "_" . basename($_FILES['video']['name']);
        $local_path = $upload_dir . $filename;

        move_uploaded_file($_FILES['video']['tmp_name'], $local_path);

        $file_size = $_FILES['video']['size'];
        $mime_type = mime_content_type($local_path);
        $upload_status = 'local';
    }

    $stmt = $conn->prepare("
        INSERT INTO course_videos
        (course_id, title, local_path, cloud_url, upload_status, file_size, mime_type, access_type, uploaded_by, description)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "issssissis",
        $course_id,
        $title,
        $local_path,
        $cloud_url,
        $upload_status,
        $file_size,
        $mime_type,
        $access_type,
        $user_id,
        $description
    );

    $stmt->execute();

    header("Location: videos.php");
    exit;
}

/* ==========================
   UPDATE VIDEO
========================== */
if (isset($_POST['update_video'])) {

    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF Error");
    }

    $id = (int)$_POST['video_id'];
    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $cloud_url = trim($_POST['cloud_url']);
    $access_type = $_POST['access_type'];

    /* NEW FILE UPLOAD */
    if (!empty($_FILES['video']['name'])) {

        $stmt = $conn->prepare("SELECT local_path FROM course_videos WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();

        if (!empty($old['local_path']) && file_exists($old['local_path'])) {
            unlink($old['local_path']);
        }

        if (!isValidVideo($_FILES['video']['tmp_name'])) {
            die("Invalid video file");
        }

        $upload_dir = "uploads/videos/";
        $filename = time() . "_" . basename($_FILES['video']['name']);
        $local_path = $upload_dir . $filename;

        move_uploaded_file($_FILES['video']['tmp_name'], $local_path);

        $conn->query("
            UPDATE course_videos
            SET local_path='$local_path', upload_status='local'
            WHERE id=$id
        ");
    }

    $stmt = $conn->prepare("
        UPDATE course_videos
        SET course_id=?, title=?, description=?, cloud_url=?, access_type=?
        WHERE id=?
    ");

    $stmt->bind_param("issssi", $course_id, $title, $description, $cloud_url, $access_type, $id);
    $stmt->execute();

    header("Location: videos.php");
    exit;
}

/* ==========================
   DELETE VIDEO
========================== */
if (isset($_POST['delete_video'])) {

    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF Error");
    }

    $id = (int)$_POST['video_id'];

    $stmt = $conn->prepare("SELECT local_path FROM course_videos WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $video = $stmt->get_result()->fetch_assoc();

    if (!empty($video['local_path']) && file_exists($video['local_path'])) {
        unlink($video['local_path']);
    }

    $stmt = $conn->prepare("DELETE FROM course_videos WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: videos.php");
    exit;
}

/* ==========================
   VIEW VIDEO
========================== */
if (isset($_GET['view'])) {

    $id = (int)$_GET['view'];

    $stmt = $conn->prepare("SELECT * FROM course_videos WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $video = $stmt->get_result()->fetch_assoc();

    if (!$video) die("Video not found");

    /* PAID ACCESS CHECK */
    if ($video['access_type'] == 'paid') {

        $check = $conn->query("
            SELECT 1 FROM enrollments
            WHERE user_id=$user_id
            AND course_id={$video['course_id']}
        ");

        if ($check->num_rows == 0 && !in_array($user_role, ['admin','teacher'])) {
            die("You are not enrolled in this course");
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
<title><?= htmlspecialchars($video['title']) ?></title>
</head>
<body>

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

</body>
</html>
<?php exit; } ?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Video Management</title>

<style>
body{font-family:Arial;background:#f4f6f9;padding:20px;}
.container{max-width:1100px;margin:auto;}
input,select,textarea{width:100%;padding:10px;margin:5px 0;}
button{padding:10px 15px;background:#2563eb;color:#fff;border:none;cursor:pointer;}
button:hover{background:#1e40af;}
table{width:100%;border-collapse:collapse;background:#fff;}
th{background:#2563eb;color:#fff;}
th,td{padding:12px;border:1px solid #ddd;}
</style>

</head>
<body>

<div class="container">

<h2>🎬 Video Management System</h2>

<!-- ================= UPLOAD FORM ================= -->
<?php if (in_array($user_role, ['admin','teacher'])) { ?>

<h3><?= $editVideo ? "✏️ Edit Video" : "⬆️ Upload Video" ?></h3>

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

<?php } ?>

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

</div>

</body>
</html>