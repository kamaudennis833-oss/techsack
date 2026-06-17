<?php
include "db.php";

/* =========================
   LOGIN CHECK
========================= */
if(!isset($_SESSION['user_id'])){
    die("Please login first.");
}

$user_id = (int)$_SESSION['user_id'];

/* =========================
   VERIFY TEACHER (USERS TABLE)
========================= */
$stmt = $conn->prepare("
    SELECT id, role
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

if($res->num_rows === 0){
    die("User not found.");
}

$user = $res->fetch_assoc();

if($user['role'] !== 'teacher'){
    die("Access denied. Not a teacher account.");
}

$teacher_id = $user_id;

/* =========================
   UPDATE COURSE (SECURE)
========================= */
if(isset($_POST['update_course'])){

    $id = (int)$_POST['id'];

    /* ownership check */
    $check = $conn->prepare("
        SELECT 1
        FROM course_teachers
        WHERE course_id = ?
        AND teacher_id = ?
        AND status = 'active'
    ");
    $check->bind_param("ii", $id, $teacher_id);
    $check->execute();

    if($check->get_result()->num_rows === 0){
        die("You are not allowed to edit this course.");
    }

    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $price = (float)$_POST['price'];

    $stmt = $conn->prepare("
        UPDATE courses
        SET title=?, description=?, price=?
        WHERE id=?
    ");

    $stmt->bind_param("ssdi", $title, $desc, $price, $id);
    $stmt->execute();

    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

/* =========================
   DELETE COURSE (SECURE)
========================= */
if(isset($_GET['delete'])){

    $id = (int)$_GET['delete'];

    $check = $conn->prepare("
        SELECT 1
        FROM course_teachers
        WHERE course_id = ?
        AND teacher_id = ?
        AND status = 'active'
    ");
    $check->bind_param("ii", $id, $teacher_id);
    $check->execute();

    if($check->get_result()->num_rows === 0){
        die("You are not allowed to delete this course.");
    }

    $del = $conn->prepare("
        DELETE FROM courses WHERE id=?
    ");
    $del->bind_param("i", $id);
    $del->execute();

    header("Location: teacher_courses.php");
    exit;
}

/* =========================
   FETCH TEACHER COURSES (FIXED)
========================= */
$stmt = $conn->prepare("
    SELECT
        c.*,
        COUNT(DISTINCT e.id) AS students,
        COUNT(DISTINCT cc.id) AS contents
    FROM courses c

    INNER JOIN course_teachers ct
        ON ct.course_id = c.id
        AND ct.teacher_id = ?
        AND ct.status = 'active'

    LEFT JOIN enrollments e
        ON e.course_id = c.id

    LEFT JOIN course_contents cc
        ON cc.course_id = c.id

    GROUP BY c.id
    ORDER BY c.id DESC
");

$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$courses = $stmt->get_result();
?>
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

    <?php while($row = $courses->fetch_assoc()): ?>

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