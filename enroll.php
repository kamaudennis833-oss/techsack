<?php
session_start();
include "db.php";

/* =========================
   LOGIN CHECK
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/* =========================
   COURSE ID
========================= */
$course_id = isset($_GET['course_id'])
    ? (int)$_GET['course_id']
    : 0;

if ($course_id <= 0) {
    die("Invalid course.");
}

/* =========================
   GET COURSE
========================= */
$stmt = $conn->prepare("
    SELECT *
    FROM courses
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $course_id);
$stmt->execute();

$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    die("Course not found.");
}

/* =========================
   CHECK USER EXISTS
========================= */
$stmt = $conn->prepare("
    SELECT id
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    die("User account not found.");
}

/* =========================
   CHECK ALREADY ENROLLED
========================= */
$stmt = $conn->prepare("
    SELECT id, status
    FROM enrollments
    WHERE user_id = ?
    AND course_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $user_id,
    $course_id
);

$stmt->execute();

$existingEnrollment = $stmt->get_result()->fetch_assoc();

if ($existingEnrollment) {

    header("Location: course1.php?id=" . $course_id);
    exit;
}

/* =========================
   FREE COURSE
========================= */
if ((float)$course['price'] <= 0) {

    $conn->begin_transaction();

    try {

        /* DOUBLE CHECK */
        $check = $conn->prepare("
            SELECT id
            FROM enrollments
            WHERE user_id = ?
            AND course_id = ?
            LIMIT 1
        ");

        $check->bind_param(
            "ii",
            $user_id,
            $course_id
        );

        $check->execute();

        if ($check->get_result()->num_rows > 0) {

            $conn->rollback();

            header("Location: course1.php?id=" . $course_id);
            exit;
        }

        /* CREATE ACTIVE ENROLLMENT */
        $stmt = $conn->prepare("
            INSERT INTO enrollments
            (
                user_id,
                course_id,
                progress,
                status,
                enrolled_at
            )
            VALUES
            (
                ?, ?, 0, 'ongoing', NOW()
            )
        ");

        $stmt->bind_param(
            "ii",
            $user_id,
            $course_id
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        /* UPDATE COURSE COUNT */
        $stmt = $conn->prepare("
            UPDATE courses
            SET enrolled_students =
                COALESCE(enrolled_students,0) + 1
            WHERE id = ?
        ");

        $stmt->bind_param(
            "i",
            $course_id
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        $conn->commit();

        $_SESSION['success'] =
            "Successfully enrolled in course.";

        header("Location: course1.php?id=" . $course_id);
        exit;

    } catch (Exception $e) {

        $conn->rollback();

        die(
            "Enrollment Error: " .
            htmlspecialchars($e->getMessage())
        );
    }
}

if (isset($_POST['pay'])) {

    $phone = trim($_POST['phone']);
    $amount = (float)$course['price'];

    if (empty($phone)) {
        die("Phone number required.");
    }

    /* ===== MPESA CONFIG ===== */
    $consumerKey = $_ENV['MPESA_CONSUMER_KEY'];
    $consumerSecret =$_ENV['MPESA_CONSUMER_SECRET'];
    $BusinessShortCode =$_ENV['MPESA_BUSINESS_SHORTCODE'];
    $Passkey =$_ENV['MPESA_PASSKEY'];

    /* ACCESS TOKEN */
    $credentials =
        base64_encode(
            $consumerKey . ":" . $consumerSecret
        );

    $ch = curl_init(
        "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials"
    );

    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        ["Authorization: Basic " . $credentials]
    );

    curl_setopt(
        $ch,
        CURLOPT_RETURNTRANSFER,
        true
    );

    $response =
        json_decode(
            curl_exec($ch)
        );

    curl_close($ch);

    if (!isset($response->access_token)) {
        die("Failed to get M-Pesa access token.");
    }

    $access_token =
        $response->access_token;

    /* TIMESTAMP */
    $timestamp = date("YmdHis");

    $password = base64_encode(
        $BusinessShortCode .
        $Passkey .
        $timestamp
    );

    /* CALLBACK URL */
    $callbackURL =
    $_ENV['MPESA_CALLBACK_URL'];
    $stkData = [

        "BusinessShortCode" =>
            $BusinessShortCode,

        "Password" =>
            $password,

        "Timestamp" =>
            $timestamp,

        "TransactionType" =>
            "CustomerPayBillOnline",

        "Amount" =>
            $amount,

        "PartyA" =>
            $phone,

        "PartyB" =>
            $BusinessShortCode,

        "PhoneNumber" =>
            $phone,

        "CallBackURL" =>
            $callbackURL,

        "AccountReference" =>
            "COURSE" . $course_id,

        "TransactionDesc" =>
            "Course Payment"
    ];

    $ch = curl_init(
        "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest"
    );

    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        [
            "Content-Type: application/json",
            "Authorization: Bearer " . $access_token
        ]
    );

    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        json_encode($stkData)
    );

    curl_setopt(
        $ch,
        CURLOPT_RETURNTRANSFER,
        true
    );

    $result = curl_exec($ch);

    curl_close($ch);

    echo "<pre>";
    print_r(json_decode($result, true));
    echo "</pre>";

    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Enroll Course</title>

    <style>


*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body{
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:30px;
    background:linear-gradient(
        135deg,
        #0f172a,
        #1e293b,
        #2563eb
    );
}

.card{
    width:100%;
    max-width:700px;
    background:#ffffff;
    border-radius:20px;
    padding:35px;
    box-shadow:
        0 15px 40px rgba(0,0,0,.15);
    animation:fadeIn .5s ease;
}

@keyframes fadeIn{
    from{
        opacity:0;
        transform:translateY(20px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}

.card h2{
    color:#1e293b;
    margin-bottom:15px;
    font-size:28px;
}

.card p{
    color:#64748b;
    line-height:1.7;
    margin-bottom:12px;
}

.price{
    display:inline-block;
    margin-top:10px;
    color:#2563eb;
    font-size:28px;
    font-weight:700;
}

hr{
    border:none;
    height:1px;
    background:#e5e7eb;
    margin:25px 0;
}

label{
    display:block;
    margin-bottom:8px;
    color:#334155;
    font-weight:600;
}

input{
    width:100%;
    padding:14px 16px;
    border:1px solid #dbeafe;
    border-radius:12px;
    font-size:15px;
    transition:.3s;
    outline:none;
}

input:focus{
    border-color:#2563eb;
    box-shadow:0 0 0 4px rgba(37,99,235,.15);
}

.btn{
    display:inline-block;
    width:100%;
    padding:15px;
    margin-top:15px;
    border:none;
    border-radius:12px;
    background:linear-gradient(
        135deg,
        #2563eb,
        #1d4ed8
    );
    color:#fff;
    font-size:16px;
    font-weight:600;
    cursor:pointer;
    text-decoration:none;
    text-align:center;
    transition:.3s;
}

.btn:hover{
    transform:translateY(-2px);
    box-shadow:0 10px 25px rgba(37,99,235,.35);
}

.course-meta{
    display:flex;
    gap:15px;
    flex-wrap:wrap;
    margin-top:15px;
}

.badge{
    background:#eff6ff;
    color:#2563eb;
    padding:8px 14px;
    border-radius:50px;
    font-size:13px;
    font-weight:600;
}

.success{
    background:#dcfce7;
    color:#166534;
}

.warning{
    background:#fef3c7;
    color:#92400e;
}

</style>
</head>
<body>

<div class="card">

    <h2>
        <?= htmlspecialchars($course['title']) ?>
    </h2>

    <p>
        <?= htmlspecialchars($course['description']) ?>
    </p>

    <p>
        Price:
        <span class="price">
            KES <?= number_format($course['price']) ?>
        </span>
    </p>

    <p>
        Instructor:
        <?= htmlspecialchars($course['instructor'] ?? 'N/A') ?>
    </p>

    <hr>

    <?php if ((float)$course['price'] > 0) { ?>

        <form method="POST">

            <label>
                Phone Number (2547XXXXXXXX)
            </label>

            <input
                type="text"
                name="phone"
                required
            >

            <button
                type="submit"
                name="pay"
                class="btn"
            >
                Pay with M-Pesa & Enroll
            </button>

        </form>

    <?php } else { ?>

        <a
            href="?course_id=<?= $course_id ?>"
            class="btn"
            style="
                display:block;
                text-align:center;
                text-decoration:none;
            "
        >
            Enroll for Free
        </a>

    <?php } ?>

</div>

</body>
</html>