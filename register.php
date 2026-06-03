<?php
include "db.php";
include "sendmail.php";

$error = "";

if(isset($_POST['register'])){

    $name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $passwordRaw = $_POST['password'];

    if($name == "" || $email == "" || $phone == "" || $passwordRaw == ""){
        $error = "All fields are required!";
    } else {

        // check if user exists in FINAL table
        $check = $conn->prepare("SELECT id FROM students WHERE email=? OR phone=?");
        $check->bind_param("ss",$email,$phone);
        $check->execute();
        $check->store_result();

        if($check->num_rows > 0){
            $error = "User already registered!";
        } else {

            // hash password
            $password = password_hash($passwordRaw, PASSWORD_BCRYPT);

            // OTP system
            $otp = random_int(100000,999999);
            $otp_created = date("Y-m-d H:i:s");

            // save to pending table
            $stmt = $conn->prepare("
                INSERT INTO pending_students(full_name,email,phone,password,otp,otp_created)
                VALUES (?,?,?,?,?,?)
            ");

            $stmt->bind_param(
                "ssssss",
                $name,
                $email,
                $phone,
                $password,
                $otp,
                $otp_created
            );

            if($stmt->execute()){

                // SEND OTP EMAIL
                if(sendOTP($email, $otp)){

                    header("Location: verify.php?email=" . urlencode($email));
                    exit();

                } else {
                    $error = "Failed to send OTP email. Check SMTP settings.";
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
    outline:none;
}

input:focus{
    border-color:green;
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
}
</style>
</head>

<body>

<div class="box">

<h2>Student Registration</h2>

<?php if($error) echo "<div class='error'>$error</div>"; ?>

<form method="POST">

    <input type="text" name="full_name" placeholder="Full Name">
    <input type="email" name="email" placeholder="Email Address">
    <input type="text" name="phone" placeholder="Phone Number">
    <input type="password" name="password" placeholder="Password">

    <button type="submit" name="register">Create Account</button>

</form>
 <div class="login-link">
            You have an account?
            <a href="Login.php">login</a>
        </div>

</div>

</body>
</html>