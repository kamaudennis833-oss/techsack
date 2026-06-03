<?php
session_start();
include "db.php";

$error = "";

if(isset($_POST['login'])){

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM students WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($row = $result->fetch_assoc()){

        // SIMPLE PASSWORD CHECK (FOR TESTING ONLY)
        if($password === $row['password']){

            $_SESSION['student_id'] = $row['id'];
            $_SESSION['student_name'] = $row['full_name'];
            $_SESSION['student_email'] = $row['email'];

            header("Location: name.php");
            exit();

        } else {
            $error = "Invalid password!";
        }

    } else {
        $error = "User not found!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Login</title>

<style>
body{
    margin:0;
    font-family: Arial, sans-serif;
    background: linear-gradient(to right, cornsilk, rgb(7, 7, 7));
    height: 100vh;
    display:flex;
    justify-content:center;
    align-items:center;
}

.login-1{
    display:flex;
    background:white;
    border-radius:15px;
    overflow:hidden;
    box-shadow:0 10px 25px rgba(0,0,0,0.2);
    width:1200px;
    height:95vh;
}

form{
    flex:0.4;
    padding:50px;
    display:flex;
    flex-direction:column;
    gap:12px;
    justify-content:center;
}

label{ font-weight:bold; }

input{
    padding:12px;
    border:1px solid #ccc;
    border-radius:8px;
}

button{
    padding:12px;
    border:none;
    border-radius:8px;
    cursor:pointer;
}

button[type="submit"]{
    background:green;
    color:white;
}

button[type="button"]{
    background:orange;
    color:white;
}

.image-box{
    flex:0.6;
    display:flex;
    justify-content:center;
    align-items:center;
}

.image-box img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.error{
    color:red;
    font-weight:bold;
}

.register-link{
    margin-top:10px;
    text-align:center;
    font-size:14px;
}

.register-link a{
    color:blue;
    text-decoration:none;
    font-weight:bold;
}
</style>
</head>

<body>

<section>
<div class="login-1">

<form method="POST">

    <h2>Student Login</h2>

    <?php if($error) echo "<p class='error'>$error</p>"; ?>

    <label>Email</label>
    <input type="email" name="email" required>

    <label>Password</label>
    <input type="password" name="password" required>

    <button type="submit" name="login">Login</button>

    <button type="button">Forgot Password</button>

    <div class="register-link">
        Don't have an account?
        <a href="register.php">Create Account</a>
    </div>

</form>

<div class="image-box">
    <img src="webpage.jpg" alt="login image">
</div>

</div>
</section>

</body>
</html>











































/*



session_start();
include "db.php";

$error = "";

if(isset($_POST['login'])){

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM students WHERE email=?");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($row = $result->fetch_assoc()){

        if(password_verify($password, $row['password'])){

            $_SESSION['student_id'] = $row['id'];
            $_SESSION['student_name'] = $row['full_name'];
            $_SESSION['student_email'] = $row['email'];

            header("Location: student.php");
            exit();

        } else {
            $error = "Invalid password!";
        }

    } else {
        $error = "User not found!";
    }
}
----

<!--DOCTYPE html
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Login</title>

<style>
body{
    margin:0;
    font-family: Arial, sans-serif;
    background: linear-gradient(to right, cornsilk, rgb(7, 7, 7));
    height: 100vh;
    display:flex;
    justify-content:center;
    align-items:center;
}

.login-1{
    display:flex;
    background:white;
    border-radius:15px;
    overflow:hidden;
    box-shadow:0 10px 25px rgba(0,0,0,0.2);
    width:1200px;
    height:95vh;
}

form{
    flex:0.4;
    padding:50px;
    display:flex;
    flex-direction:column;
    gap:12px;
    justify-content:center;
}

label{
    font-weight:bold;
}

input{
    padding:12px;
    border:1px solid #ccc;
    border-radius:8px;
}

button{
    padding:12px;
    border:none;
    border-radius:8px;
    cursor:pointer;
}

button[type="submit"]{
    background:green;
    color:white;
}

button[type="button"]{
    background:orange;
    color:white;
}

.image-box{
    flex:0.6;
    display:flex;
    justify-content:center;
    align-items:center;
}

.image-box img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.error{
    color:red;
    font-weight:bold;
}

/* REGISTER LINK */
.register-link{
    margin-top:10px;
    text-align:center;
    font-size:14px;
}

.register-link a{
    color:blue;
    text-decoration:none;
    font-weight:bold;
}

.register-link a:hover{
    text-decoration:underline;
}
</style>
</head>

<body>

<section>
<div class="login-1">

    <!-- FORM --
    <form method="POST">

        <h2>Student Login</h2>

        ?php if($error) echo "<p class='error'>$error</p>"; ?>

        <label>Email</label>
        <input type="email" name="email" placeholder="Enter Email" required>

        <label>Password</label>
        <input type="password" name="password" placeholder="Enter Password" required>

        <button type="submit" name="login">Login</button>

        <button type="button">Forgot Password</button>

        <!-- REGISTER LINK --
        <div class="register-link">
            Don't have an account?
            <a href="register.php">Create Account</a>
        </div>

    </form>

    <!-- IMAGE --
    <div class="image-box">
        <img src="webpage.jpg" alt="login image">
    </div>

</div>
</section>

</body>
</html>