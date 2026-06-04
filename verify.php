<?php
date_default_timezone_set("Africa/Nairobi");

session_start();
include "db.php";

$email = $_GET['email'] ?? '';
$msg = "";
$success = false;

if(empty($email)){
    die("Invalid access. Email missing.");
}

if(isset($_POST['verify'])){

    $otp = trim($_POST['otp']);
    $otp = preg_replace('/\s+/', '', $otp);

    if(!preg_match('/^[0-9]{6}$/', $otp)){
        $msg = "OTP must be 6 digits.";
    } else {

        // 1. CHECK OTP FROM pending_students
        $stmt = $conn->prepare("
            SELECT * 
            FROM pending_students
            WHERE email=?
            AND otp=?
            AND otp_verified=0
            AND otp_expires > NOW()
            LIMIT 1
        ");

        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $result = $stmt->get_result();

        if($row = $result->fetch_assoc()){

            // 2. CHECK IF USER ALREADY EXISTS IN users TABLE
            $check = $conn->prepare("SELECT id FROM users WHERE email=?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if($check->num_rows > 0){
                $msg = "Account already exists. Please login.";
            } else {

                // 3. CREATE FINAL USER ACCOUNT
                $insert = $conn->prepare("
                    INSERT INTO users
                    (full_name, email, phone, password, is_verified, status)
                    VALUES (?,?,?,?,1,'active')
                ");

                $insert->bind_param(
                    "ssss",
                    $row['full_name'],
                    $row['email'],
                    $row['phone'],
                    $row['password']
                );

                if($insert->execute()){

                    // 4. MARK OTP VERIFIED
                    $update = $conn->prepare("
                        UPDATE pending_students
                        SET otp_verified=1
                        WHERE id=?
                    ");
                    $update->bind_param("i", $row['id']);
                    $update->execute();

                    // 5. DELETE TEMP RECORD
                    $delete = $conn->prepare("
                        DELETE FROM pending_students
                        WHERE id=?
                    ");
                    $delete->bind_param("i", $row['id']);
                    $delete->execute();

                    // 6. SESSION LOGIN
                    $_SESSION['user_email'] = $row['email'];
                    $_SESSION['user_name'] = $row['full_name'];

                    $success = true;

                    // OPTIONAL: redirect after success
                    header("refresh:3;url=login.php");

                } else {
                    $msg = "Failed to create account.";
                }
            }

        } else {
            $msg = "Invalid or expired OTP.";
        }
    }
}
?>

<style>
body{
    margin:0;
    font-family:Arial, sans-serif;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:linear-gradient(to right, cornsilk, chartreuse);
}

.box{
    width:360px;
    background:#fff;
    padding:30px;
    border-radius:12px;
    box-shadow:0 10px 20px rgba(0,0,0,.2);
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
    box-sizing:border-box;
}

button{
    width:100%;
    padding:12px;
    border:none;
    background:green;
    color:#fff;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;
}

button:hover{
    background:#0a8f0a;
}

.error{
    color:red;
    margin-bottom:15px;
}

.success{
    color:green;
    font-weight:bold;
    margin-bottom:15px;
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

    <?php else: ?>

        <?php if($msg): ?>
            <div class="error"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="POST">

            <input
                type="text"
                name="otp"
                placeholder="Enter 6-digit OTP"
                maxlength="6"
                required>

            <button type="submit" name="verify">
                Verify Account
            </button>

        </form>

    <?php endif; ?>

</div>

</body>
</html>