<?php
include "db.php";
date_default_timezone_set("Africa/Nairobi");
session_start();

$email = $_GET['email'] ?? '';
$msg = "";
$success = false;

if (isset($_POST['verify'])) {

    $otp = trim($_POST['otp']);

    // =========================
    // CHECK OTP
    // =========================
    $stmt = $conn->prepare("
        SELECT *
        FROM pending_teachers
        WHERE email=?
        AND otp=?
        AND otp_verified=0
        AND otp_expires > NOW()
        LIMIT 1
    ");

    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        $conn->begin_transaction();

        try {

            // =========================
            // FIX: INSERT USER (ROLE FIXED)
            // =========================
            $role = "teacher"; // FIXED (NOT NULL)

            $user = $conn->prepare("
                INSERT INTO users
                (full_name, email, password, role, is_verified, status)
                VALUES (?, ?, ?, ?, 1, 'active')
            ");

            $user->bind_param(
                "ssss",
                $row['full_name'],
                $row['email'],
                $row['password'],
                $role
            );

            $user->execute();
            $user_id = $conn->insert_id;

            // =========================
            // CREATE TEACHER PROFILE
            // =========================
            $teacher = $conn->prepare("
                INSERT INTO teachers
                (user_id, employee_no, specialization, qualification, experience_years)
                VALUES (?, ?, ?, ?, ?)
            ");

            $teacher->bind_param(
                "isssi",
                $user_id,
                $row['employee_no'],
                $row['specialization'],
                $row['qualification'],
                $row['experience_years']
            );

            $teacher->execute();

            // =========================
            // UPDATE PENDING TABLE
            // =========================
            $update = $conn->prepare("
                UPDATE pending_teachers
                SET otp_verified=1,
                    status='active'
                WHERE id=?
            ");

            $update->bind_param("i", $row['id']);
            $update->execute();

            $conn->commit();

            $success = true;

            header("refresh:3;url=login.php");

        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Error: " . $e->getMessage();
        }

    } else {
        $msg = "Invalid or expired OTP";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Verify Email</title>

<style>
body{
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
    font-family:Arial;
    background:#f4f4f4;
}

.box{
    background:#fff;
    padding:30px;
    width:350px;
    border-radius:10px;
    text-align:center;
}

input,button{
    width:100%;
    padding:12px;
    margin-top:10px;
}

button{
    background:green;
    color:#fff;
    border:none;
}

.error{ color:red; }
.success{ color:green; }
</style>
</head>

<body>

<div class="box">

<h2>Verify OTP</h2>

<?php if ($success): ?>

    <p class="success">✔ Account activated successfully</p>
    <p>Redirecting to login...</p>

<?php else: ?>

    <?php if ($msg) echo "<p class='error'>$msg</p>"; ?>

    <form method="POST">
        <input type="text" name="otp" placeholder="Enter OTP" maxlength="6" required>
        <button type="submit" name="verify">Verify</button>
    </form>

<?php endif; ?>

</div>

</body>
</html>