<?php
session_start();
include_once '../includes/config.php';
include_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to add items to your cart',
        'redirect' => 'login.php'
    ]);
    exit;
}

// Check if user is a consumer
if ($_SESSION['user_type'] !== 'consumer') {
    echo json_encode([
        'success' => false,
        'message' => 'Only consumers can add items to cart'
    ]);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

$product_id = (int)$_POST['product_id'];
$quantity = (int)$_POST['quantity'];

// Validate quantity
if ($quantity <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Quantity must be greater than zero'
    ]);
    exit;
}

// Get product details
$product = getProductById($product_id);

// Check if product exists and is active
if (!$product || $product['status'] !== 'active') {
    echo json_encode([
        'success' => false,
        'message' => 'Product not found or no longer available'
    ]);
    exit;
}

// Check if quantity is available
if ($quantity > $product['stock_quantity']) {
    echo json_encode([
        'success' => false,
        'message' => 'Not enough stock available. Only ' . $product['stock_quantity'] . ' ' . $product['unit'] . ' available.'
    ]);
    exit;
}

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add to cart or update quantity if already in cart
if (isset($_SESSION['cart'][$product_id])) {
    $new_quantity = $_SESSION['cart'][$product_id] + $quantity;
    
    // Check if new quantity exceeds available stock
    if ($new_quantity > $product['stock_quantity']) {
        $new_quantity = $product['stock_quantity'];
    }
    
    $_SESSION['cart'][$product_id] = $new_quantity;
} else {
    $_SESSION['cart'][$product_id] = $quantity;
}

// Count total items in cart
$cart_count = 0;
foreach ($_SESSION['cart'] as $qty) {
    $cart_count += $qty;
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Product added to cart successfully',
    'cart_count' => $cart_count
]);
exit;
?>