<?php
include 'includes/config.php';
include 'includes/functions.php';

$first_name = 'Test';
$email = 'testuser@example.com';
$verification_token = 'test-token-123';
$verification_link = SITE_URL . '/verify.php?token=' . $verification_token;
$subject = 'Verify your Agriconnect Account';
$body = 'Test Body ' . $verification_link;

if (sendEmail($email, $subject, $body)) {
    echo "Email sent successfully\n";
} else {
    echo "Failed to send email\n";
}
?>