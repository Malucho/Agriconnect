<?php
/**
 * Database Setup Script
 * 
 * This file contains the function to set up the database tables for the Agriconnect platform.
 */

function setupDatabase($conn) {
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_type ENUM('farmer', 'consumer', 'admin') NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(20) NOT NULL,
        password VARCHAR(255) NOT NULL,
        profile_image VARCHAR(255) DEFAULT NULL,
        bio TEXT DEFAULT NULL,
        location VARCHAR(100) DEFAULT NULL,
        county VARCHAR(50) DEFAULT NULL,
        registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        status ENUM('active', 'inactive', 'suspended') DEFAULT 'inactive',
        verification_token VARCHAR(255) DEFAULT NULL,
        is_verified TINYINT(1) DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($sql)) {
        die("Error creating users table: " . $conn->error);
    }

    // Create user_otps table
    $sql = "CREATE TABLE IF NOT EXISTS user_otps (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        otp_code VARCHAR(10) NOT NULL,
        expires_at DATETIME NOT NULL,
        consumed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        die("Error creating user_otps table: " . $conn->error);
    }
    
    // Create farmer_profiles table
    $sql = "CREATE TABLE IF NOT EXISTS farmer_profiles (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        farm_name VARCHAR(100) NOT NULL,
        farm_size DECIMAL(10,2) DEFAULT NULL,
        farm_location VARCHAR(100) NOT NULL,
        farm_description TEXT DEFAULT NULL,
        farming_practices TEXT DEFAULT NULL,
        years_farming INT(3) DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($sql)) {
        die("Error creating farmer_profiles table: " . $conn->error);
    }
    
    // Create product_categories table
    $sql = "CREATE TABLE IF NOT EXISTS product_categories (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        description TEXT DEFAULT NULL,
        image VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($sql)) {
        die("Error creating product_categories table: " . $conn->error);
    }
    
    // Insert default product categories
    $categories = [
        ['name' => 'Vegetables', 'description' => 'Fresh vegetables from Kenyan farms'],
        ['name' => 'Fruits', 'description' => 'Fresh fruits from Kenyan farms'],
        ['name' => 'Grains', 'description' => 'Quality grains including maize, wheat, and rice'],
        ['name' => 'Dairy', 'description' => 'Fresh dairy products from Kenyan farms'],
        ['name' => 'Meat', 'description' => 'Quality meat products from Kenyan farms'],
        ['name' => 'Poultry', 'description' => 'Fresh poultry products from Kenyan farms'],
        ['name' => 'Honey', 'description' => 'Pure honey from Kenyan beekeepers'],
        ['name' => 'Herbs & Spices', 'description' => 'Fresh herbs and spices from Kenyan farms']
    ];
    
    foreach ($categories as $category) {
        $stmt = $conn->prepare("INSERT IGNORE INTO product_categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $category['name'], $category['description']);
        $stmt->execute();
        $stmt->close();
    }
    
    // Create products table
    $sql = "CREATE TABLE IF NOT EXISTS products (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        farmer_id INT(11) NOT NULL,
        category_id INT(11) NOT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        unit VARCHAR(20) NOT NULL,
        quantity_available DECIMAL(10,2) NOT NULL,
        image VARCHAR(255) DEFAULT NULL,
        location VARCHAR(100) NOT NULL,
        date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_updated TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        status ENUM('available', 'low_stock', 'out_of_stock', 'hidden') DEFAULT 'available',
        is_featured TINYINT(1) DEFAULT 0,
        FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES product_categories(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($sql)) {
        die("Error creating products table: " . $conn->error);
    }
    
    // Create product_images table
    $sql = "CREATE TABLE IF NOT EXISTS product_images (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        product_id INT(11) NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        is_primary TINYINT(1) DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($sql)) {
        die("Error creating product_images table: " . $conn->error);
    }
    
    // Create orders table
    $sql = "CREATE TABLE IF NOT EXISTS orders (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        consumer_id INT(11) NOT NULL,
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'confirmed', 'processing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
        payment_method ENUM('cash_on_delivery', 'mpesa', 'bank_transfer') DEFAULT 'cash_on_delivery',
        payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
        delivery_address TEXT NOT NULL,
        delivery_notes TEXT DEFAULT NULL,
        delivery_date DATE DEFAULT NULL,
        FOREIGN KEY (consumer_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($sql)) {
        die("Error creating orders table: " . $conn->error);
    }
    
    // Create order_items table
    $sql = "CREATE TABLE IF NOT EXISTS order_items (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        order_id INT(11) NOT NULL,
        product_id INT(11) NOT NULL,
        farmer_id INT(11) NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'confirmed', 'processing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (farmer_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($sql)) {
        die("Error creating order_items table: " . $conn->error);
    }
    
    // Create reviews table
    $sql = "CREATE TABLE IF NOT EXISTS reviews (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        product_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        rating INT(1) NOT NULL,
        comment TEXT DEFAULT NULL,
        review_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($sql)) {
        die("Error creating reviews table: " . $conn->error);
    }
    
    // Create messages table
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        sender_id INT(11) NOT NULL,
        receiver_id INT(11) NOT NULL,
        message TEXT NOT NULL,
        sent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) DEFAULT 0,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($sql)) {
        die("Error creating messages table: " . $conn->error);
    }
    
    // Create admin user if it doesn't exist
    $admin_email = 'admin@agriconnect.com';
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $admin_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO users (user_type, first_name, last_name, email, phone, password, is_verified, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $user_type = 'admin';
        $first_name = 'Admin';
        $last_name = 'User';
        $phone = '+254700000000';
        $is_verified = 1;
        $status = 'active';
        
        $stmt->bind_param("ssssssss", $user_type, $first_name, $last_name, $admin_email, $phone, $admin_password, $is_verified, $status);
        $stmt->execute();
    }
    
    $stmt->close();
    
    return true;
}