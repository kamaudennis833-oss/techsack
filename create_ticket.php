<?php
include "db.php";

/* =========================
   CREATE TICKET (NO LOGIN)
========================= */

if (isset($_POST['submit_ticket'])) {

    // Default system user (IMPORTANT)
    // Change this to an existing user id in your users table
    $user_id = 1;

    $type = mysqli_real_escape_string($conn, trim($_POST['type']));
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject']));
    $message = mysqli_real_escape_string($conn, trim($_POST['message']));

    if (empty($type) || empty($subject) || empty($message)) {
        header("Location: student_support.php?error=empty");
        exit();
    }

    $sql = "
        INSERT INTO tickets
        (
            user_id,
            type,
            subject,
            message,
            status
        )
        VALUES
        (
            '$user_id',
            '$type',
            '$subject',
            '$message',
            'open'
        )
    ";

    $insert = mysqli_query($conn, $sql);

    if ($insert) {
        header("Location: name.php?success=1");
        exit();
    } else {
        die("Database Error: " . mysqli_error($conn));
    }
}
?>