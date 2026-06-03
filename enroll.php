<?php
include "db.php";
session_start();

/* TEMP USER (NO LOGIN SYSTEM) */
$user_id = 1;

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if($course_id == 0){
    die("Invalid course");
}

/* GET COURSE */
$course = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT * FROM courses WHERE id='$course_id'
"));

if(!$course){
    die("Course not found");
}

/* CHECK IF ALREADY ENROLLED */
$check = mysqli_query($conn,"
    SELECT * FROM enrollments
    WHERE user_id='$user_id' AND course_id='$course_id'
");

if(mysqli_num_rows($check) > 0){
    die("Already enrolled in this course");
}

/* =========================
   FREE COURSE → DIRECT ENROLL
========================= */
if($course['price'] <= 0){

    mysqli_query($conn,"
        INSERT INTO enrollments (user_id, course_id, progress, status)
        VALUES ('$user_id','$course_id',0,'approved')
    ");

    mysqli_query($conn,"
        UPDATE courses
        SET enrolled_students = enrolled_students + 1
        WHERE id='$course_id'
    ");

    echo "Successfully enrolled in FREE course!";
    echo "<br><a href='course1.php?id=$course_id'>Go to course</a>";
    exit;
}

/* =========================
   PAID COURSE → STK PUSH
========================= */

if(isset($_POST['pay'])){

    $phone = $_POST['phone'];
    $amount = $course['price'];

    /* ===== MPESA CONFIG ===== */
    $consumerKey = "YOUR_CONSUMER_KEY";
    $consumerSecret = "YOUR_CONSUMER_SECRET";
    $BusinessShortCode = "174379";
    $Passkey = "YOUR_PASSKEY";

    /* ACCESS TOKEN */
    $credentials = base64_encode($consumerKey.":".$consumerSecret);

    $ch = curl_init("https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic ".$credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = json_decode(curl_exec($ch));
    curl_close($ch);

    $access_token = $response->access_token;

    /* TIMESTAMP */
    $timestamp = date("YmdHis");
    $password = base64_encode($BusinessShortCode.$Passkey.$timestamp);

    /* CALLBACK */
    $callbackURL = "https://yourdomain.com/mpesa_callback.php";

    $stkData = [
        "BusinessShortCode" => $BusinessShortCode,
        "Password" => $password,
        "Timestamp" => $timestamp,
        "TransactionType" => "CustomerPayBillOnline",
        "Amount" => $amount,
        "PartyA" => $phone,
        "PartyB" => $BusinessShortCode,
        "PhoneNumber" => $phone,
        "CallBackURL" => $callbackURL,
        "AccountReference" => "COURSE".$course_id,
        "TransactionDesc" => "Course Payment"
    ];

    $ch = curl_init("https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer ".$access_token
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stkData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    echo "<pre>STK Push Sent: $result</pre>";
    exit;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Enroll Course</title>

    <style>
        body{font-family:Arial;background:#f5f7fb;padding:40px;}
        .card{max-width:600px;margin:auto;background:#fff;padding:25px;border-radius:10px;}
        .btn{background:#2563eb;color:#fff;padding:10px;border:none;border-radius:8px;cursor:pointer;}
        input{width:100%;padding:10px;margin:10px 0;}
        .price{color:#2563eb;font-weight:bold;}
    </style>
</head>
<body>

<div class="card">

    <h2><?= htmlspecialchars($course['title']) ?></h2>

    <p><?= htmlspecialchars($course['description']) ?></p>

    <p>Price: <span class="price">KES <?= number_format($course['price']) ?></span></p>

    <p>Instructor: <?= htmlspecialchars($course['instructor']) ?></p>

    <hr>

    <form method="POST">

        <label>Phone Number (2547XXXXXXXX)</label>
        <input type="text" name="phone" required>

        <button class="btn" name="pay">
            Pay with M-Pesa & Enroll
        </button>

    </form>

</div>

</body>
</html>