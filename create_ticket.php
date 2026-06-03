<?php
session_start();
include "db.php";



$user_id = $_SESSION['user_id'];

/* =========================
   HANDLE FORM SUBMISSION
========================= */
if(isset($_POST['type'])){

    // sanitize inputs
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    // extra safety check
    if(empty($type) || empty($subject) || empty($message)){
        die("All fields are required.");
    }

    // insert ticket
    $insert = mysqli_query($conn, "
        INSERT INTO tickets (user_id, type, subject, message, status)
        VALUES ('$user_id', '$type', '$subject', '$message', 'open')
    ");

    if($insert){
        header("Location: name.php?success=1");
        exit();
    } else {
        echo "Database Error: " . mysqli_error($conn);
    }
}
?>