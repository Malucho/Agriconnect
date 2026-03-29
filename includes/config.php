<?php
/**
 * Database Configuration
 * 
 * This file contains the database connection settings for the Agriconnect platform.
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'agriconnect');

// Establish database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to UTF-8
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // If database doesn't exist, create it
    if (strpos($e->getMessage(), "Unknown database") !== false) {
        $temp_conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($temp_conn->connect_error) {
            die("Connection failed: " . $temp_conn->connect_error);
        }
        
        // Create database
        $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
        if ($temp_conn->query($sql) === TRUE) {
            // Connect to the newly created database
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $conn->set_charset("utf8mb4");
            
            // Include and run the database setup script
            include_once 'db_setup.php';
            setupDatabase($conn);
            
            // Log success
            error_log("Database created successfully and tables initialized.");
        } else {
            die("Error creating database: " . $temp_conn->error);
        }
        
        $temp_conn->close();
    } else {
        die("Connection failed: " . $e->getMessage());
    }
}

// Set application constants
$base_dir = dirname(__DIR__) . '/';
define('SITE_URL', (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . '/Agriconnect');
define('UPLOAD_DIR', $base_dir . 'uploads/');
define('PRODUCT_IMG_DIR', UPLOAD_DIR . 'products/');
define('PROFILE_IMG_DIR', UPLOAD_DIR . 'profiles/');
define('LOGS_DIR', $base_dir . 'logs/');

// Email configuration
define('MAIL_DEBUG', true); // Set to true to log emails instead of sending them
define('MAIL_FROM', 'noreply@agriconnect.com');
define('MAIL_FROM_NAME', 'Agriconnect');

// Create directories if they don't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!file_exists(PRODUCT_IMG_DIR)) {
    mkdir(PRODUCT_IMG_DIR, 0755, true);
}
if (!file_exists(PROFILE_IMG_DIR)) {
    mkdir(PROFILE_IMG_DIR, 0755, true);
}
if (!file_exists(LOGS_DIR)) {
    mkdir(LOGS_DIR, 0755, true);
}