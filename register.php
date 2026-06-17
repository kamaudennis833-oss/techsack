<?php
date_default_timezone_set("Africa/Nairobi");

include "db.php";
include "sendmail.php";

$error = "";

if (isset($_POST['register'])) {

    // ======================
    // INPUTS
    // ======================
    $name = trim($_POST['full_name']);
    $email = strtolower(trim($_POST['email']));
    $phone = trim($_POST['phone']);
    $passwordRaw = $_POST['password'];

    // ======================
    // VALIDATION
    // ======================
    if (
        empty($name) ||
        empty($email) ||
        empty($phone) ||
        empty($passwordRaw)
    ) {
        $error = "All fields are required!";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address!";
    }
    else {

        // ======================
        // CHECK EXISTS (EMAIL / PHONE)
        // ======================
        $check = $conn->prepare("
            SELECT email, phone
            FROM users
            WHERE email=? OR phone=?
            LIMIT 1
        ");

        $check->bind_param("ss", $email, $phone);
        $check->execute();
        $result = $check->get_result();

        if ($row = $result->fetch_assoc()) {

            if ($row['email'] === $email && $row['phone'] === $phone) {
                $error = "Email and Phone already exist!";
            }
            elseif ($row['email'] === $email) {
                $error = "Email already exists!";
            }
            elseif ($row['phone'] === $phone) {
                $error = "Phone number already exists!";
            }

            $check->close();

        } else {

            $check->close();

            // ======================
            // REMOVE OLD OTP RECORD
            // ======================
            $delete = $conn->prepare("
                DELETE FROM pending_students
                WHERE email=?
            ");
            $delete->bind_param("s", $email);
            $delete->execute();
            $delete->close();

            // ======================
            // PASSWORD HASH
            // ======================
            $passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);

            // ======================
            // OTP
            // ======================
            $otp = random_int(100000, 999999);
            $otp_created = date("Y-m-d H:i:s");
            $otp_expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));

            // ======================
            // INSERT TEMP USER
            // ======================
            $stmt = $conn->prepare("
                INSERT INTO pending_students
                (
                    full_name,
                    email,
                    phone,
                    password,
                    otp,
                    otp_created,
                    otp_expires
                )
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

            if ($stmt->execute()) {

                // ======================
                // SEND OTP EMAIL
                // ======================
                if (sendOTP($email, $name, $otp)) {

                    header("Location: verify.php?email=" . urlencode($email));
                    exit();

                } else {
                    $error = "Failed to send OTP email.";
                }

            } else {
                $error = "Registration failed: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Register</title>

<style>
body{
    margin:0;
    font-family:Arial;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background: linear-gradient(-45deg, #0f0c29, #302b63, #24243e, #00d4ff);
    background-size:400% 400%;
    animation:bg 5s infinite;
}

@keyframes bg{
    0%{background-position:0% 50%;}
    50%{background-position:100% 50%;}
    100%{background-position:0% 50%;}
}

.box{
    width:420px;
    background:#fff;
    padding:30px;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,.2);
}

h2{
    text-align:center;
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
    color:#fff;
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
</style>

</head>
<body>

<div class="box">

<h2>Create Account</h2>

<?php if(!empty($error)){ ?>
<div class="error">
    <?php echo htmlspecialchars($error); ?>
</div>
<?php } ?>

<form method="POST">

    <input type="text" name="full_name" placeholder="Full Name" required>

    <input type="email" name="email" placeholder="Email Address" required>

    <input type="text" name="phone" placeholder="Phone Number" required>

    <input type="password" name="password" placeholder="Password" required>

    <button type="submit" name="register">Create Account</button>

</form>

</div>

</body>
</html>