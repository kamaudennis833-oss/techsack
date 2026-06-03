<?php
session_start();
include "db.php";

require_once "sendmail.php";

$email = $_GET['email'] ?? '';
$msg = "";
$success = false;

if($email == ""){
    die("Invalid access. Email missing.");
}

if(isset($_POST['verify'])){

    $otp = trim($_POST['otp']);

    // check OTP + expiry
    $stmt = $conn->prepare("
        SELECT * FROM pending_students 
        WHERE email=? AND otp=? 
        AND otp_created >= (NOW() - INTERVAL 5 MINUTE)
    ");

    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if($row = $result->fetch_assoc()){

        // move to students table
        $insert = $conn->prepare("
            INSERT INTO students(full_name,email,phone,password)
            VALUES (?,?,?,?)
        ");

        $insert->bind_param(
            "ssss",
            $row['full_name'],
            $row['email'],
            $row['phone'],
            $row['password']
        );

        $insert->execute();

        // delete pending record
        $del = $conn->prepare("DELETE FROM pending_students WHERE email=?");
        $del->bind_param("s", $email);
        $del->execute();

        // session
        $_SESSION['student_email'] = $row['email'];
        $_SESSION['student_name'] = $row['full_name'];

        $success = true;

    } else {
        $msg = "Invalid or expired OTP (valid for 5 minutes)";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>OTP Verification</title>

<?php if($success): ?>
<meta http-equiv="refresh" content="3;url=login.php">
<?php endif; ?>

<style>
body{
    margin:0;
    font-family:Arial;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:linear-gradient(to right, cornsilk, chartreuse);
}

.box{
    width:360px;
    background:white;
    padding:30px;
    border-radius:12px;
    box-shadow:0 10px 20px rgba(0,0,0,0.2);
    text-align:center;
}

h2{
    margin-bottom:20px;
}

input{
    width:100%;
    padding:12px;
    margin:10px 0;
    border:1px solid #ccc;
    border-radius:8px;
}

button{
    width:100%;
    padding:12px;
    border:none;
    background:green;
    color:white;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;
}

button:hover{
    background:#0a8f0a;
}

.error{
    color:red;
    margin-bottom:10px;
}

.success{
    color:green;
    font-weight:bold;
    margin-bottom:10px;
}
</style>
</head>

<body>

<div class="box">

<h2>OTP Verification</h2>

<?php if($success): ?>

    <div class="success">
        ✅ Verification successful!<br>
        Redirecting to login...
    </div>

<?php elseif($msg): ?>

    <div class="error"><?php echo $msg; ?></div>

    <form method="POST">
        <input type="text" name="otp" placeholder="Enter OTP">
        <button type="submit">Verify Account</button>
    </form>

<?php else: ?>

    <form method="POST">
        <input type="text" name="otp" placeholder="Enter OTP">
        <button type="submit">Verify Account</button>
    </form>

<?php endif; ?>

</div>

</body>
</html>