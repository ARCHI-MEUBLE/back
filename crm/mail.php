<?php
$to = "cypdus@gmail.com";
$subject = "Test Email from PHP";
$message = "This is a test email sent using sendmail-bin.";
$headers = "From: sender@example.com";

if (mail($to, $subject, $message, $headers)) {
    echo "Mail sent successfully.";
} else {
    echo "Mail failed to send.";
}
?>
