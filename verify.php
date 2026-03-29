<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$token = isset($_GET['token']) ? sanitize($_GET['token']) : '';
$error = '';
$success = '';

if (empty($token)) {
    $error = "Verification token is missing.";
} else {
    // Check if token exists in database
    $stmt = $conn->prepare("SELECT id FROM users WHERE verification_token = ? AND is_verified = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];

        // Update user status
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1, status = 'active', verification_token = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $success = "Your account has been successfully verified! You can now login.";
            setFlashMessage($success, "success");
        } else {
            $error = "Verification failed. Please try again or contact support.";
        }
    } else {
        $error = "Invalid or expired verification token.";
    }
}

if ($error) {
    setFlashMessage($error, "danger");
}

redirect('login.php');
?>