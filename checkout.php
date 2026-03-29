<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and is a consumer
if (!isLoggedIn()) {
    setFlashMessage('error', 'You must be logged in to checkout');
    redirect('login.php');
    exit();
}

if ($_SESSION['user_type'] != 'consumer') {
    setFlashMessage('error', 'Only consumers can checkout');
    redirect('marketplace.php');
    exit();
}

// Check if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    setFlashMessage('error', 'Your cart is empty');
    redirect('cart.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Get user details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get cart items
$cartItems = [];
$totalAmount = 0;
$farmerGroups = [];

foreach ($_SESSION['cart'] as $productId => $quantity) {
    $query = "SELECT p.*, 
              (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1) as image_url,
              u.username as farmer_name, u.id as farmer_id
              FROM products p
              JOIN users u ON p.user_id = u.id
              WHERE p.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    
    if ($product) {
        $product['quantity'] = $quantity;
        $product['subtotal'] = $product['price'] * $quantity;
        $totalAmount += $product['subtotal'];
        
        // Group by farmer
        if (!isset($farmerGroups[$product['farmer_id']])) {
            $farmerGroups[$product['farmer_id']] = [
                'farmer_name' => $product['farmer_name'],
                'items' => []
            ];
        }
        
        $farmerGroups[$product['farmer_id']]['items'][] = $product;
    }
}

// Process checkout form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $shippingAddress = sanitizeInput($_POST['shipping_address']);
    $shippingCity = sanitizeInput($_POST['shipping_city']);
    $shippingPhone = sanitizeInput($_POST['shipping_phone']);
    $paymentMethod = sanitizeInput($_POST['payment_method']);
    $deliveryMethod = sanitizeInput($_POST['delivery_method']);
    $notes = sanitizeInput($_POST['notes']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($shippingAddress)) {
        $errors[] = "Shipping address is required";
    }
    
    if (empty($shippingCity)) {
        $errors[] = "City is required";
    }
    
    if (empty($shippingPhone)) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($paymentMethod)) {
        $errors[] = "Payment method is required";
    }
    
    if (empty($deliveryMethod)) {
        $errors[] = "Delivery method is required";
    }
    
    // Calculate delivery fee based on method
    $deliveryFee = ($deliveryMethod == 'standard') ? 200 : 500;
    $orderTotal = $totalAmount + $deliveryFee;
    
    // If no errors, create order
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Create order
            $query = "INSERT INTO orders (user_id, total_amount, delivery_fee, shipping_address, shipping_city, 
                      shipping_phone, payment_method, delivery_method, notes, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iddssssss", $userId, $orderTotal, $deliveryFee, $shippingAddress, $shippingCity, 
                             $shippingPhone, $paymentMethod, $deliveryMethod, $notes);
            $stmt->execute();
            
            $orderId = $conn->insert_id;
            
            // Add order items
            foreach ($_SESSION['cart'] as $productId => $quantity) {
                // Get product details
                $query = "SELECT * FROM products WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $productId);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                
                if ($product) {
                    // Check if enough stock
                    if ($product['stock_quantity'] < $quantity) {
                        throw new Exception("Not enough stock for " . $product['name']);
                    }
                    
                    // Add order item
                    $query = "INSERT INTO order_items (order_id, product_id, quantity, price, status) 
                              VALUES (?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iiid", $orderId, $productId, $quantity, $product['price']);
                    $stmt->execute();
                    
                    // Update product stock
                    $newStock = $product['stock_quantity'] - $quantity;
                    $query = "UPDATE products SET stock_quantity = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $newStock, $productId);
                    $stmt->execute();
                }
            }
            
            $conn->commit();
            
            // Clear cart
            unset($_SESSION['cart']);
            
            // Redirect to order confirmation page
            redirect('order-confirmation.php?id=' . $orderId);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            setFlashMessage('error', 'Error processing order: ' . $e->getMessage());
        }
    } else {
        // Set error messages
        foreach ($errors as $error) {
            setFlashMessage('error', $error);
        }
    }
}

include 'includes/header.php';
?>

<div class="checkout-container">
    <div class="checkout-header">
        <h1>Checkout</h1>
        <div class="checkout-steps">
            <div class="step completed">
                <span class="step-number">1</span>
                <span class="step-name">Shopping Cart</span>
            </div>
            <div class="step active">
                <span class="step-number">2</span>
                <span class="step-name">Checkout</span>
            </div>
            <div class="step">
                <span class="step-number">3</span>
                <span class="step-name">Order Complete</span>
            </div>
        </div>
    </div>
    
    <?php displayFlashMessages(); ?>
    
    <div class="checkout-content">
        <div class="checkout-form-container">
            <form action="" method="POST" id="checkout-form">
                <div class="form-section">
                    <h2>Shipping Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="shipping_name">Full Name</label>
                            <input type="text" id="shipping_name" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_email">Email</label>
                            <input type="email" id="shipping_email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_address">Address *</label>
                            <input type="text" id="shipping_address" name="shipping_address" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_city">City/Town *</label>
                            <input type="text" id="shipping_city" name="shipping_city" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_phone">Phone Number *</label>
                            <input type="text" id="shipping_phone" name="shipping_phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Delivery Method</h2>
                    <div class="delivery-options">
                        <div class="delivery-option">
                            <input type="radio" id="delivery_standard" name="delivery_method" value="standard" checked>
                            <label for="delivery_standard">
                                <div class="option-info">
                                    <h3>Standard Delivery</h3>
                                    <p>3-5 business days</p>
                                </div>
                                <div class="option-price">KSh 200</div>
                            </label>
                        </div>
                        
                        <div class="delivery-option">
                            <input type="radio" id="delivery_express" name="delivery_method" value="express">
                            <label for="delivery_express">
                                <div class="option-info">
                                    <h3>Express Delivery</h3>
                                    <p>1-2 business days</p>
                                </div>
                                <div class="option-price">KSh 500</div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Payment Method</h2>
                    <div class="payment-options">
                        <div class="payment-option">
                            <input type="radio" id="payment_mpesa" name="payment_method" value="M-Pesa" checked>
                            <label for="payment_mpesa">
                                <div class="payment-logo mpesa-logo">M-Pesa</div>
                                <div class="payment-info">Pay via M-Pesa</div>
                            </label>
                        </div>
                        
                        <div class="payment-option">
                            <input type="radio" id="payment_bank" name="payment_method" value="Bank Transfer">
                            <label for="payment_bank">
                                <div class="payment-logo bank-logo">Bank</div>
                                <div class="payment-info">Bank Transfer</div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Additional Information</h2>
                    <div class="form-group">
                        <label for="notes">Order Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="4" placeholder="Special instructions for delivery or order"></textarea>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="order-summary">
            <div class="summary-header">
                <h2>Order Summary</h2>
            </div>
            
            <div class="summary-items">
                <?php foreach ($farmerGroups as $farmerId => $farmerGroup): ?>
                    <div class="farmer-group">
                        <h3>From: <?php echo htmlspecialchars($farmerGroup['farmer_name']); ?></h3>
                        
                        <?php foreach ($farmerGroup['items'] as $item): ?>
                            <div class="summary-item">
                                <div class="item-image">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php else: ?>
                                        <div class="no-image"><i class="fas fa-image"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="item-details">
                                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p>KSh <?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?></p>
                                </div>
                                <div class="item-price">KSh <?php echo number_format($item['subtotal'], 2); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="summary-totals">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>KSh <?php echo number_format($totalAmount, 2); ?></span>
                </div>
                <div class="summary-row delivery-fee">
                    <span>Delivery Fee</span>
                    <span id="delivery-fee">KSh 200</span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span id="order-total">KSh <?php echo number_format($totalAmount + 200, 2); ?></span>
                </div>
            </div>
            
            <div class="summary-actions">
                <button type="submit" form="checkout-form" name="place_order" class="btn btn-primary btn-block">
                    Place Order
                </button>
                <a href="cart.php" class="btn-text">
                    <i class="fas fa-arrow-left"></i> Return to Cart
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const standardDelivery = document.getElementById('delivery_standard');
        const expressDelivery = document.getElementById('delivery_express');
        const deliveryFeeElement = document.getElementById('delivery-fee');
        const orderTotalElement = document.getElementById('order-total');
        const subtotal = <?php echo $totalAmount; ?>;
        
        function updateTotals() {
            let deliveryFee = standardDelivery.checked ? 200 : 500;
            let total = subtotal + deliveryFee;
            
            deliveryFeeElement.textContent = 'KSh ' + deliveryFee.toFixed(2);
            orderTotalElement.textContent = 'KSh ' + total.toFixed(2);
        }
        
        standardDelivery.addEventListener('change', updateTotals);
        expressDelivery.addEventListener('change', updateTotals);
    });
</script>

<?php include 'includes/footer.php'; ?>