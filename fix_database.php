<?php
// Include database connection
require_once 'includes/config.php';

// Create the user_otps table
$sql = "CREATE TABLE IF NOT EXISTS user_otps (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Execute the query
if ($conn->query($sql)) {
    echo "<p style='color:green'>✓ user_otps table created successfully!</p>";
} else {
    echo "<p style='color:red'>✗ Error creating table: " . $conn->error . "</p>";
}

// Test email functionality
require_once 'includes/functions.php';
$testEmail = "test@example.com";
$testSubject = "Test Email from Agriconnect";
$testBody = "<h1>Test Email</h1><p>This is a test email to verify the email functionality is working.</p>";

if (sendEmail($testEmail, $testSubject, $testBody)) {
    echo "<p style='color:green'>✓ Test email sent successfully!</p>";
    echo "<p>Since MAIL_DEBUG is enabled, check the logs folder for the email content.</p>";
} else {
    echo "<p style='color:red'>✗ Failed to send test email.</p>";
}

// Show link to login page
echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>