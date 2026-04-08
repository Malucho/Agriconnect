<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setFlashMessage('Please login to access your profile', 'warning');
    redirect('login.php');
}

// Redirect to specific profile page based on user type
if (isFarmer()) {
    redirect('farmer/profile.php');
} elseif (isConsumer()) {
    // If consumer folder/profile doesn't exist yet, we'll create it or handle it
    if (file_exists('consumer/profile.php')) {
        redirect('consumer/profile.php');
    } else {
        // Fallback for now if consumer profile isn't ready
        setFlashMessage('Consumer profile is under maintenance.', 'info');
        redirect('index.php');
    }
} elseif (isAdmin()) {
    // Redirect admins to their profile page
    redirect('admin/profile.php');
} else {
    redirect('index.php');
}
?>
