<?php
date_default_timezone_set("Africa/Nairobi");

include "db.php";
include "sendmail.php";

$error = "";

if(isset($_POST['register'])){

    // CLEAN INPUTS (IMPORTANT FIX)
    $name = trim($_POST['full_name']);
    $email = strtolower(trim($_POST['email']));
    $phone = trim($_POST['phone']);
    $passwordRaw = $_POST['password'];

    if(empty($name) || empty($email) || empty($phone) || empty($passwordRaw)){
        $error = "All fields are required!";
    } else {

        // 1. CHECK IF USER ALREADY EXISTS (FINAL TABLE)
        $check = $conn->prepare("
            SELECT id FROM users 
            WHERE email=? OR phone=?
        ");
        $check->bind_param("ss", $email, $phone);
        $check->execute();
        $check->store_result();

        if($check->num_rows > 0){
            $error = "User already registered!";
        } else {

            $check->close();

            // 2. DELETE OLD PENDING OTP (IMPORTANT FIX)
            $del = $conn->prepare("
                DELETE FROM pending_students 
                WHERE email=?
            ");
            $del->bind_param("s", $email);
            $del->execute();
            $del->close();

            // 3. HASH PASSWORD
            $passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);

            // 4. OTP + EXPIRY
            $otp = random_int(100000, 999999);
            $otp_created = date("Y-m-d H:i:s");
            $otp_expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));

            // 5. INSERT PENDING USER
            $stmt = $conn->prepare("
                INSERT INTO pending_students
                (full_name, email, phone, password, otp, otp_created, otp_expires)
                VALUES (?,?,?,?,?,?,?)
            ");

            $stmt->bind_param(
                "sssssss",
                $name,
                $email,
                $phone,
                $passwordHash,
                $otp,
                $otp_created,
                $otp_expires
            );

            if($stmt->execute()){

                // 6. SEND OTP
                if(sendOTP($email, $otp)){
                    header("Location: verify.php?email=" . urlencode($email));
                    exit();
                } else {
                    $error = "OTP email failed. Check mail server.";
                }

            } else {
                $error = "Registration failed. Try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Student Registration</title>

<style>
body{
    margin:0;
    font-family:Arial;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:linear-gradient(-45deg, cornsilk, chartreuse, #00c9ff, #92fe9d);
    background-size:400% 400%;
    animation:bg 10s infinite;
}

@keyframes bg{
    0%{background-position:0% 50%}
    50%{background-position:100% 50%}
    100%{background-position:0% 50%}
}

.box{
    width:420px;
    background:white;
    padding:30px;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,0.2);
}

h2{
    text-align:center;
    margin-bottom:20px;
}

input{
    width:100%;
    padding:12px;
    margin:8px 0;
    border:1px solid #ccc;
    border-radius:8px;
}

button{
    width:100%;
    padding:12px;
    margin-top:10px;
    background:green;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;
}

button:hover{
    background:#0a8f0a;
}

.error{
    color:red;
    text-align:center;
    margin-bottom:10px;
}

.login-link{
    margin-top: 15px;
    text-align:center;
}
</style>
</head>

<body>

<div class="box">

<h2>Student Registration</h2>

<?php if($error) echo "<div class='error'>$error</div>"; ?>

<form method="POST">

    <input type="text" name="full_name" placeholder="Full Name" required>
    <input type="email" name="email" placeholder="Email Address" required>
    <input type="text" name="phone" placeholder="Phone Number" required>
    <input type="password" name="password" placeholder="Password" required>

    <button type="submit" name="register">Create Account</button>

</form>

<div class="login-link">
    You already have an account?
    <a href="login.php">Login</a>
</div>

</div>

</body>
</html>