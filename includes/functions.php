<?php
/**
 * Utility Functions
 * 
 * This file contains utility functions used throughout the Agriconnect platform.
 */

/**
 * Sanitize user input
 * 
 * @param string $data The data to sanitize
 * @return string The sanitized data
 */
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    if ($conn) {
        $data = $conn->real_escape_string($data);
    }
    return $data;
}

/**
 * Redirect to a specific URL
 * 
 * @param string $url The URL to redirect to
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is a farmer
 * 
 * @return bool True if user is a farmer, false otherwise
 */
function isFarmer() {
    return isLoggedIn() && $_SESSION['user_type'] === 'farmer';
}

/**
 * Check if user is a consumer
 * 
 * @return bool True if user is a consumer, false otherwise
 */
function isConsumer() {
    return isLoggedIn() && $_SESSION['user_type'] === 'consumer';
}

/**
 * Check if user is an admin
 * 
 * @return bool True if user is an admin, false otherwise
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] === 'admin';
}

/**
 * Get user information by ID
 * 
 * @param int $user_id The user ID
 * @return array|bool User data array or false if not found
 */
function getUserById($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return false;
}

/**
 * Get farmer profile by user ID
 * 
 * @param int $user_id The user ID
 * @return array|bool Farmer profile data array or false if not found
 */
function getFarmerProfile($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM farmer_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return false;
}

/**
 * Get all product categories
 * 
 * @return array Array of product categories
 */
function getProductCategories() {
    global $conn;
    
    $result = $conn->query("SELECT * FROM product_categories ORDER BY name ASC");
    $categories = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    return $categories;
}

/**
 * Get featured products
 * 
 * @param int $limit Number of products to return
 * @return array Array of featured products
 */
function getFeaturedProducts($limit = 8) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT p.*, u.first_name, u.last_name, u.profile_image, pc.name as category_name
        FROM products p
        JOIN users u ON p.farmer_id = u.id
        JOIN product_categories pc ON p.category_id = pc.id
        WHERE p.is_featured = 1 AND p.status = 'available'
        ORDER BY p.date_added DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    return $products;
}

/**
 * Get products by category
 * 
 * @param int $category_id The category ID
 * @param int $limit Number of products to return
 * @param int $offset Offset for pagination
 * @return array Array of products
 */
function getProductsByCategory($category_id, $limit = 12, $offset = 0) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT p.*, u.first_name, u.last_name, u.profile_image, pc.name as category_name
        FROM products p
        JOIN users u ON p.farmer_id = u.id
        JOIN product_categories pc ON p.category_id = pc.id
        WHERE p.category_id = ? AND p.status = 'available'
        ORDER BY p.date_added DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $category_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    return $products;
}

/**
 * Get product by ID
 * 
 * @param int $product_id The product ID
 * @return array|bool Product data array or false if not found
 */
function getProductById($product_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT p.*, u.first_name, u.last_name, u.profile_image, u.phone, u.email, u.county, u.location, pc.name as category_name
        FROM products p
        JOIN users u ON p.farmer_id = u.id
        JOIN product_categories pc ON p.category_id = pc.id
        LEFT JOIN farmer_profiles fp ON u.id = fp.user_id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return false;
}

/**
 * Get first image of a product
 * 
 * @param int $product_id The product ID
 * @return string|bool The image filename or false if not found
 */
function getProductFirstImage($product_id) {
    global $conn;
    
    // First check the products table itself for a main image
    $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row && !empty($row['image'])) {
        return $row['image'];
    }
    
    // Then check the product_images table
    $stmt = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC LIMIT 1");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row) {
        return $row['image_path'];
    }
    
    return false;
}

/**
 * Get farmer name by ID
 * 
 * @param int $farmer_id The farmer ID
 * @return string Farmer name
 */
function getFarmerName($farmer_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row) {
        return $row['first_name'] . ' ' . $row['last_name'];
    }
    
    return 'Unknown Farmer';
}

/**
 * Get product images
 * 
 * @param int $product_id The product ID
 * @return array Array of product images
 */
function getProductImages($product_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $images = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $images[] = $row;
        }
    }
    
    return $images;
}

/**
 * Get product reviews
 * 
 * @param int $product_id The product ID
 * @return array Array of product reviews
 */
function getProductReviews($product_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT r.*, u.first_name, u.last_name, u.profile_image
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ?
        ORDER BY r.review_date DESC
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reviews = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
    }
    
    return $reviews;
}

/**
 * Get average rating for a product
 * 
 * @param int $product_id The product ID
 * @return float Average rating
 */
function calculateAverageRating($product_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM reviews WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return round($row['avg_rating'], 1) ?: 0;
}

/**
 * Get average rating for a product (Alias for calculateAverageRating)
 * 
 * @param int $product_id The product ID
 * @return float Average rating
 */
function getAverageRating($product_id) {
    return calculateAverageRating($product_id);
}

/**
 * Format price with currency
 * 
 * @param float $price The price to format
 * @return string Formatted price
 */
function formatPrice($price) {
    return 'KES ' . number_format($price, 2);
}

/**
 * Generate a random token
 * 
 * @param int $length Token length
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Upload an image
 * 
 * @param array $file The file data from $_FILES
 * @param string $destination The destination directory
 * @return string|bool The image path or false on failure
 */
function uploadImage($file, $destination) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check if the uploaded file is an image
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    // Generate a unique filename
    $filename = uniqid() . '_' . basename($file['name']);
    $target_path = $destination . $filename;
    
    // Move the uploaded file to the destination directory
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $filename;
    }
    
    return false;
}

/**
 * Display alert message
 * 
 * @param string $message The message to display
 * @param string $type The alert type (success, danger, warning, info)
 * @return string HTML for the alert
 */
function displayAlert($message, $type = 'info') {
    return '<div class="alert alert-' . $type . '">' . $message . '</div>';
}

/**
 * Set flash message
 * 
 * @param string $message The message to set
 * @param string $type The message type (success, danger, warning, info)
 * @return void
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear flash message
 * 
 * @return array|null The flash message or null if none exists
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash_message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash_message;
    }
    
    return null;
}

/**
 * Display flash message
 * 
 * @return string HTML for the flash message or empty string if none exists
 */
function displayFlashMessage() {
    $flash_message = getFlashMessage();
    
    if ($flash_message) {
        return displayAlert($flash_message['message'], $flash_message['type']);
    }
    
    return '';
}

/**
 * Display flash message (Alias for displayFlashMessage)
 * 
 * @return string HTML for the flash message or empty string if none exists
 */
function displayFlashMessages() {
    echo displayFlashMessage();
}

/**
 * Get user orders
 * 
 * @param int $user_id The user ID
 * @param int $limit Number of orders to return
 * @param int $offset Offset for pagination
 * @return array Array of orders
 */
function getUserOrders($user_id, $limit = 10, $offset = 0) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM orders
        WHERE consumer_id = ?
        ORDER BY order_date DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    return $orders;
}

/**
 * Get farmer orders
 * 
 * @param int $farmer_id The farmer ID
 * @param int $limit Number of orders to return
 * @param int $offset Offset for pagination
 * @return array Array of orders
 */
function getFarmerOrders($farmer_id, $limit = 10, $offset = 0) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT oi.*, o.order_date, o.status as order_status, o.payment_status,
               p.name as product_name, p.image as product_image,
               u.first_name, u.last_name, u.email, u.phone
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON o.consumer_id = u.id
        WHERE oi.farmer_id = ?
        ORDER BY o.order_date DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $farmer_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    return $orders;
}

/**
 * Get order items
 * 
 * @param int $order_id The order ID
 * @return array Array of order items
 */
function getOrderItems($order_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT oi.*, p.name as product_name, p.image as product_image, p.unit,
               u.first_name as farmer_first_name, u.last_name as farmer_last_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON oi.farmer_id = u.id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    
    return $items;
}

/**
 * Search products
 * 
 * @param string $query The search query
 * @param int $limit Number of products to return
 * @param int $offset Offset for pagination
 * @return array Array of products
 */
function searchProducts($query, $limit = 12, $offset = 0) {
    global $conn;
    
    $search_query = "%{$query}%";
    
    $stmt = $conn->prepare("
        SELECT p.*, u.first_name, u.last_name, u.profile_image, pc.name as category_name
        FROM products p
        JOIN users u ON p.farmer_id = u.id
        JOIN product_categories pc ON p.category_id = pc.id
        WHERE (p.name LIKE ? OR p.description LIKE ? OR pc.name LIKE ?) AND p.status = 'available'
        ORDER BY p.date_added DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sssii", $search_query, $search_query, $search_query, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    return $products;
}

/**
 * Get farmer products
 * 
 * @param int $farmer_id The farmer ID
 * @param int $limit Number of products to return
 * @param int $offset Offset for pagination
 * @return array Array of products
 */
function getFarmerProducts($farmer_id, $limit = 12, $offset = 0) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT p.*, pc.name as category_name
        FROM products p
        JOIN product_categories pc ON p.category_id = pc.id
        WHERE p.farmer_id = ?
        ORDER BY p.date_added DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $farmer_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    return $products;
}

/**
 * Count farmer products
 * 
 * @param int $farmer_id The farmer ID
 * @return int Number of products
 */
function countFarmerProducts($farmer_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE farmer_id = ?");
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

/**
 * Count farmer orders
 * 
 * @param int $farmer_id The farmer ID
 * @return int Number of orders
 */
function countFarmerOrders($farmer_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE farmer_id = ?");
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

/**
 * Calculate farmer revenue
 * 
 * @param int $farmer_id The farmer ID
 * @return float Total revenue
 */
function calculateFarmerRevenue($farmer_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT SUM(subtotal) as total_revenue
        FROM order_items
        WHERE farmer_id = ? AND status = 'completed'
    ");
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total_revenue'] ?: 0;
}

/**
 * Get unread messages count
 * 
 * @param int $user_id The user ID
 * @return int Number of unread messages
 */
function getUnreadMessagesCount($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

/**
 * Get user conversations
 * 
 * @param int $user_id The user ID
 * @return array Array of conversations
 */
function getUserConversations($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id
                ELSE m.sender_id
            END as conversation_with,
            u.first_name, u.last_name, u.profile_image, u.user_type,
            MAX(m.sent_date) as last_message_date,
            (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND sender_id = conversation_with AND is_read = 0) as unread_count
        FROM messages m
        JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
        WHERE m.sender_id = ? OR m.receiver_id = ?
        GROUP BY conversation_with
        ORDER BY last_message_date DESC
    ");
    $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }
    }
    
    return $conversations;
}

/**
 * Get conversation messages
 * 
 * @param int $user_id The user ID
 * @param int $other_user_id The other user ID
 * @param int $limit Number of messages to return
 * @param int $offset Offset for pagination
 * @return array Array of messages
 */
function getConversationMessages($user_id, $other_user_id, $limit = 20, $offset = 0) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT m.*, 
            u_sender.first_name as sender_first_name, u_sender.last_name as sender_last_name, 
            u_sender.profile_image as sender_profile_image
        FROM messages m
        JOIN users u_sender ON m.sender_id = u_sender.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.sent_date DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iiiiii", $user_id, $other_user_id, $other_user_id, $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
    }
    
    // Mark messages as read
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt->bind_param("ii", $other_user_id, $user_id);
    $stmt->execute();
    
    return array_reverse($messages);
}

/**
 * Send message
 * 
 * @param int $sender_id The sender ID
 * @param int $receiver_id The receiver ID
 * @param string $message The message content
 * @return bool True on success, false on failure
 */
function sendMessage($sender_id, $receiver_id, $message) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
    
    return $stmt->execute();
}

/**
 * Get Kenyan counties
 * 
 * @return array Array of Kenyan counties
 */
function getKenyanCounties() {
    return [
        'Baringo', 'Bomet', 'Bungoma', 'Busia', 'Elgeyo Marakwet', 'Embu', 'Garissa', 'Homa Bay', 'Isiolo', 'Kajiado',
        'Kakamega', 'Kericho', 'Kiambu', 'Kilifi', 'Kirinyaga', 'Kisii', 'Kisumu', 'Kitui', 'Kwale', 'Laikipia',
        'Lamu', 'Machakos', 'Makueni', 'Mandera', 'Marsabit', 'Meru', 'Migori', 'Mombasa', 'Murang\'a', 'Nairobi',
        'Nakuru', 'Nandi', 'Narok', 'Nyamira', 'Nyandarua', 'Nyeri', 'Samburu', 'Siaya', 'Taita Taveta', 'Tana River',
        'Tharaka Nithi', 'Trans Nzoia', 'Turkana', 'Uasin Gishu', 'Vihiga', 'Wajir', 'West Pokot'
    ];
}

/**
 * Get pagination links
 * 
 * @param int $current_page Current page number
 * @param int $total_pages Total number of pages
 * @param string $url_pattern URL pattern with %d placeholder for page number
 * @return string HTML for pagination links
 */
function getPaginationLinks($current_page, $total_pages, $url_pattern) {
    if ($total_pages <= 1) {
        return '';
    }
    
    $links = '<div class="pagination">';
    
    // Previous page link
    if ($current_page > 1) {
        $links .= '<a href="' . sprintf($url_pattern, $current_page - 1) . '"><i class="fas fa-chevron-left"></i></a>';
    } else {
        $links .= '<a href="#" class="disabled"><i class="fas fa-chevron-left"></i></a>';
    }
    
    // Page number links
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $links .= '<a href="' . sprintf($url_pattern, 1) . '">1</a>';
        if ($start_page > 2) {
            $links .= '<span class="dots">...</span>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $links .= '<a href="#" class="active">' . $i . '</a>';
        } else {
            $links .= '<a href="' . sprintf($url_pattern, $i) . '">' . $i . '</a>';
        }
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $links .= '<span class="dots">...</span>';
        }
        $links .= '<a href="' . sprintf($url_pattern, $total_pages) . '">' . $total_pages . '</a>';
    }
    
    // Next page link
    if ($current_page < $total_pages) {
        $links .= '<a href="' . sprintf($url_pattern, $current_page + 1) . '"><i class="fas fa-chevron-right"></i></a>';
    } else {
        $links .= '<a href="#" class="disabled"><i class="fas fa-chevron-right"></i></a>';
    }
    
    $links .= '</div>';
    
    return $links;
}

/**
 * Count all users by type
 * 
 * @param string $type The user type (optional)
 * @return int Total count
 */
function countAllUsers($type = null) {
    global $conn;
    $sql = "SELECT COUNT(*) as count FROM users";
    if ($type) {
        $sql .= " WHERE user_type = '$type'";
    }
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['count'];
}

/**
 * Count all products
 * 
 * @return int Total count
 */
function countAllProducts() {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as count FROM products");
    $row = $result->fetch_assoc();
    return $row['count'];
}

/**
 * Count all orders
 * 
 * @return int Total count
 */
function countAllOrders() {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as count FROM orders");
    $row = $result->fetch_assoc();
    return $row['count'];
}

/**
 * Calculate total site revenue
 * 
 * @return float Total revenue
 */
function calculateTotalRevenue() {
    global $conn;
    $result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' OR status = 'completed'");
    $row = $result->fetch_assoc();
    return $row['total'] ?: 0;
}

/**
 * Get all users with details
 */
function getAllUsers($limit = 20, $offset = 0) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users ORDER BY registration_date DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get all products with details
 */
function getAllProducts($limit = 20, $offset = 0) {
    global $conn;
    $stmt = $conn->prepare("SELECT p.*, u.first_name, u.last_name, c.name as category_name 
                           FROM products p 
                           JOIN users u ON p.farmer_id = u.id 
                           JOIN product_categories c ON p.category_id = c.id 
                           ORDER BY p.date_added DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get all orders with details
 */
function getAllOrders($limit = 20, $offset = 0) {
    global $conn;
    $stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name 
                           FROM orders o 
                           JOIN users u ON o.consumer_id = u.id 
                           ORDER BY o.order_date DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Send an email
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML supported)
 * @return bool True on success, false on failure
 */
function sendEmail($to, $subject, $body) {
    // In debug mode, log email to a file instead of sending
    if (defined('MAIL_DEBUG') && MAIL_DEBUG) {
        $log_file = LOGS_DIR . 'mail.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] TO: $to\nSUBJECT: $subject\nBODY:\n$body\n" . str_repeat("-", 50) . "\n";
        
        return file_put_contents($log_file, $log_entry, FILE_APPEND) !== false;
    }
    
    // Set headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    
    // More headers
    if (defined('MAIL_FROM')) {
        $from_name = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Agriconnect';
        $headers .= 'From: ' . $from_name . ' <' . MAIL_FROM . '>' . "\r\n";
    }
    
    // Send email
    return mail($to, $subject, $body, $headers);
}
