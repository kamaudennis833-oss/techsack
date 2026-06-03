<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__.'/PHPMailer/PHPMailer-master/src/Exception.php';
require __DIR__.'/PHPMailer/PHPMailer-master/src/PHPMailer.php';
require __DIR__.'/PHPMailer/PHPMailer-master/src/SMTP.php';

function sendOTP($email, $otp)
{
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        $mail->Username = 'kamaudennis833@gmail.com';
        $mail->Password = 'sfnf fixe kews irdk';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('kamaudennis833@gmail.com', 'LMS System');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Your OTP Code";

        $mail->Body = "
            <h2>Learning Management System</h2>
            <p>Your OTP code is:</p>
            <h1>{$otp}</h1>
            <p>This code expires in 5 minutes.</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return false;
    }
}
?>