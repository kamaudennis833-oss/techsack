<?php
session_start();
include "db.php";

/* =========================
   CREATE TICKET (FIXED)
========================= */

if (isset($_POST['submit_ticket'])) {

    /* =========================
       GET LOGGED IN USER
    ========================= */
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$user_id) {
        die("You must be logged in to submit a ticket.");
    }

    /* =========================
       INPUT SANITIZATION
    ========================= */
    $type    = mysqli_real_escape_string($conn, trim($_POST['type']));
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject']));
    $message = mysqli_real_escape_string($conn, trim($_POST['message']));

    /* =========================
       VALIDATION
    ========================= */
    if (empty($type) || empty($subject) || empty($message)) {
        header("Location: student_support.php?error=empty");
        exit();
    }

    /* =========================
       CHECK IF USER EXISTS (IMPORTANT FIX)
    ========================= */
    $check = mysqli_query($conn, "
        SELECT id FROM users WHERE id = '$user_id' LIMIT 1
    ");

    if (mysqli_num_rows($check) == 0) {
        die("Invalid user. Please log in again.");
    }

    /* =========================
       INSERT TICKET
    ========================= */
    $sql = "
        INSERT INTO tickets
        (user_id, type, subject, message, status)
        VALUES
        ('$user_id', '$type', '$subject', '$message', 'open')
    ";

    if (mysqli_query($conn, $sql)) {

        header("Location: name.php?success=1");
        exit();

    } else {
        die("Database Error: " . mysqli_error($conn));
    }
}
?>