<?php
include "db.php";
date_default_timezone_set("Africa/Nairobi");
include "sendmail1.php";

$message = "";
$messageType = "";

if (isset($_POST['add_teacher'])) {

    // =========================
    // INPUTS
    // =========================
    $full_name = trim($_POST['full_name']);
    $email = strtolower(trim($_POST['email']));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'];

    $employee_no = trim($_POST['employee_no'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $experience_years = (int)($_POST['experience_years'] ?? 0);

    // =========================
    // VALIDATION
    // =========================
    if (empty($full_name) || empty($email) || empty($password)) {
        $message = "Full name, email and password are required!";
        $messageType = "error";
    } else {

        // =========================
        // CHECK DUPLICATE EMAIL
        // =========================
        $check = $conn->prepare("SELECT id FROM pending_teachers WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {

            $message = "Email already exists!";
            $messageType = "error";

        } else {

            // =========================
            // GET ROLE ID
            // =========================
            $role_name = "teacher";
            $role_stmt = $conn->prepare("SELECT id FROM roles WHERE role_name=?");
            $role_stmt->bind_param("s", $role_name);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();
            $role_data = $role_result->fetch_assoc();

            if (!$role_data) {

                $message = "Role 'teacher' not found in roles table!";
                $messageType = "error";

            } else {

                $role_id = $role_data['id'];

                // =========================
                // OTP
                // =========================
                $otp = rand(100000, 999999);
                $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

                // =========================
                // HASH PASSWORD
                // =========================
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // =========================
                // INSERT TEACHER
                // =========================
                $stmt = $conn->prepare("
                    INSERT INTO pending_teachers
                    (
                        full_name,
                        email,
                        phone,
                        password,
                        role_id,
                        employee_no,
                        specialization,
                        qualification,
                        experience_years,
                        otp,
                        otp_expires
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)
                ");

                $stmt->bind_param(
                    "ssssisssiss",
                    $full_name,
                    $email,
                    $phone,
                    $hashedPassword,
                    $role_id,
                    $employee_no,
                    $specialization,
                    $qualification,
                    $experience_years,
                    $otp,
                    $expiry
                );

                if ($stmt->execute()) {

                    // =========================
                    // SEND OTP EMAIL
                    // =========================
                    sendOTP($email, $full_name, $otp);

                    header("Location: verify2.php?email=" . urlencode($email));
                    exit;

                } else {
                    $message = "Database error: " . $stmt->error;
                    $messageType = "error";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Add Teacher</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    background:#f4f6f9;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:20px;
}

.container{
    width:100%;
    max-width:650px;
}

.card{
    background:#fff;
    padding:30px;
    border-radius:10px;
    box-shadow:0 5px 20px rgba(0,0,0,0.1);
}

h2{
    text-align:center;
    margin-bottom:20px;
    color:#333;
}

label{
    display:block;
    margin-bottom:6px;
    font-weight:bold;
    color:#555;
}

input{
    width:100%;
    padding:12px;
    margin-bottom:15px;
    border:1px solid #ccc;
    border-radius:6px;
}

button{
    width:100%;
    padding:13px;
    background:#007bff;
    color:#fff;
    border:none;
    border-radius:6px;
    cursor:pointer;
    font-size:16px;
    font-weight:bold;
}

button:hover{
    background:#0056b3;
}

.success{
    background:#d4edda;
    color:#155724;
    padding:12px;
    margin-bottom:15px;
    border-radius:5px;
}

.error{
    background:#f8d7da;
    color:#721c24;
    padding:12px;
    margin-bottom:15px;
    border-radius:5px;
}
</style>

</head>
<body>

<div class="container">
<div class="card">

<h2>Add Teacher</h2>

<?php if (!empty($message)) { ?>
    <div class="<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<form method="POST">

    <label>Teacher Name *</label>
    <input type="text" name="full_name" required>

    <label>Email *</label>
    <input type="email" name="email" required>

    <label>Password *</label>
    <input type="password" name="password" required>

    <label>Employee Number</label>
    <input type="text" name="employee_no">

    <label>Phone Number</label>
    <input type="text" name="phone">

    <label>Specialization</label>
    <input type="text" name="specialization">

    <label>Qualification</label>
    <input type="text" name="qualification">

    <label>Experience Years</label>
    <input type="number" name="experience_years" min="0">

    <button type="submit" name="add_teacher">Add Teacher</button>

</form>

</div>
</div>

</body>
</html>