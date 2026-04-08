<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and is a consumer
if (!isLoggedIn()) {
    setFlashMessage('You must be logged in to checkout', 'danger');
    redirect('login.php');
    exit();
}

if ($_SESSION['user_type'] != 'consumer') {
    setFlashMessage('Only consumers can checkout', 'danger');
    redirect('marketplace.php');
    exit();
}

// Check if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    setFlashMessage('Your cart is empty', 'danger');
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
              u.first_name as farmer_first_name, u.last_name as farmer_last_name, u.id as farmer_id
              FROM products p
              JOIN users u ON p.farmer_id = u.id
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
                'farmer_name' => $product['farmer_first_name'] . ' ' . $product['farmer_last_name'],
                'items' => []
            ];
        }
        
        $farmerGroups[$product['farmer_id']]['items'][] = $product;
    }
}

// Process checkout form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $shippingAddress = sanitize($_POST['shipping_address']);
    $shippingCity = sanitize($_POST['shipping_city']);
    $shippingPhone = sanitize($_POST['shipping_phone']);
    $paymentMethod = sanitize($_POST['payment_method']);
    $notes = sanitize($_POST['notes']);
    
    // Map payment method to DB enum
    $db_payment_method = 'cash_on_delivery';
    if ($paymentMethod == 'M-Pesa') $db_payment_method = 'mpesa';
    if ($paymentMethod == 'Bank Transfer') $db_payment_method = 'bank_transfer';

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
    
    // If no errors, create order
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            $full_address = $shippingAddress . ", " . $shippingCity;
            
            // Create order
            $query = "INSERT INTO orders (consumer_id, total_amount, delivery_address, delivery_notes, payment_method, order_date) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("idsss", $userId, $totalAmount, $full_address, $notes, $db_payment_method);
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
                    if ($product['quantity_available'] < $quantity) {
                        throw new Exception("Not enough stock for " . $product['name']);
                    }
                    
                    $subtotal = $product['price'] * $quantity;
                    
                    // Add order item
                    $query = "INSERT INTO order_items (order_id, product_id, farmer_id, quantity, unit_price, subtotal, status) 
                              VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iiiddd", $orderId, $productId, $product['farmer_id'], $quantity, $product['price'], $subtotal);
                    $stmt->execute();
                    
                    // Update product stock
                    $newStock = $product['quantity_available'] - $quantity;
                    $query = "UPDATE products SET quantity_available = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("di", $newStock, $productId);
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
            setFlashMessage('Error processing order: ' . $e->getMessage(), 'danger');
        }
    } else {
        // Set error messages
        foreach ($errors as $error) {
            setFlashMessage($error, 'danger');
        }
    }
}

$page_title = 'Checkout';
include 'includes/head.php';
include 'includes/header.php';
?>

<div class="container" style="padding: 40px 15px;">
    <div class="checkout-header" style="margin-bottom: 30px;">
        <h1>Checkout</h1>
    </div>
    
    <?php displayFlashMessages(); ?>
    
    <div class="checkout-content" style="display: grid; grid-template-columns: 1fr 400px; gap: 30px;">
        <div class="checkout-form-container">
            <form action="" method="POST" id="checkout-form">
                <div class="form-section" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px;">
                    <h2 style="margin-bottom: 20px; font-size: 1.5rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">Shipping Information</h2>
                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Full Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" readonly style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
                        </div>
                        
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="shipping_address" style="display: block; margin-bottom: 8px; font-weight: 600;">Address *</label>
                            <input type="text" id="shipping_address" name="shipping_address" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_city" style="display: block; margin-bottom: 8px; font-weight: 600;">City/Town *</label>
                            <input type="text" id="shipping_city" name="shipping_city" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <div class="form-group">
                            <label for="shipping_phone" style="display: block; margin-bottom: 8px; font-weight: 600;">Phone Number *</label>
                            <input type="text" id="shipping_phone" name="shipping_phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                </div>
                
                <div class="form-section" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px;">
                    <h2 style="margin-bottom: 20px; font-size: 1.5rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">Payment Method</h2>
                    <div class="payment-options" style="display: flex; flex-direction: column; gap: 15px;">
                        <label class="payment-option" style="display: flex; align-items: center; gap: 15px; padding: 15px; border: 1px solid #eee; border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="payment_method" value="M-Pesa" checked>
                            <div>
                                <div style="font-weight: 600;">M-Pesa</div>
                                <div style="font-size: 0.85rem; color: #777;">Pay via mobile money</div>
                            </div>
                        </label>
                        
                        <label class="payment-option" style="display: flex; align-items: center; gap: 15px; padding: 15px; border: 1px solid #eee; border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="payment_method" value="Bank Transfer">
                            <div>
                                <div style="font-weight: 600;">Bank Transfer</div>
                                <div style="font-size: 0.85rem; color: #777;">Direct bank transfer</div>
                            </div>
                        </label>

                        <label class="payment-option" style="display: flex; align-items: center; gap: 15px; padding: 15px; border: 1px solid #eee; border-radius: 8px; cursor: pointer;">
                            <input type="radio" name="payment_method" value="Cash on Delivery">
                            <div>
                                <div style="font-weight: 600;">Cash on Delivery</div>
                                <div style="font-size: 0.85rem; color: #777;">Pay when you receive items</div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="form-section" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <h2 style="margin-bottom: 20px; font-size: 1.5rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">Additional Information</h2>
                    <div class="form-group">
                        <label for="notes" style="display: block; margin-bottom: 8px; font-weight: 600;">Order Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="4" placeholder="Special instructions for delivery or order" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;"></textarea>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="order-summary" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); height: fit-content; position: sticky; top: 20px;">
            <h2 style="margin-bottom: 20px; font-size: 1.5rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">Order Summary</h2>
            
            <div class="summary-items" style="margin-bottom: 20px; max-height: 400px; overflow-y: auto;">
                <?php foreach ($farmerGroups as $farmerId => $farmerGroup): ?>
                    <div class="farmer-group" style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #f9f9f9;">
                        <h3 style="font-size: 0.9rem; color: #4CAF50; margin-bottom: 10px;">From: <?php echo htmlspecialchars($farmerGroup['farmer_name']); ?></h3>
                        
                        <?php foreach ($farmerGroup['items'] as $item): ?>
                            <div class="summary-item" style="display: flex; gap: 12px; margin-bottom: 10px;">
                                <div class="item-image" style="width: 50px; height: 50px; border-radius: 4px; overflow: hidden; background: #eee; flex-shrink: 0;">
                                    <?php if ($item['image']): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="Product" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #ccc;"><i class="fas fa-image"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="item-details" style="flex: 1; min-width: 0;">
                                    <h4 style="font-size: 0.9rem; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p style="font-size: 0.8rem; color: #777; margin: 0;"><?php echo $item['quantity']; ?> x <?php echo formatPrice($item['price']); ?></p>
                                </div>
                                <div class="item-price" style="font-weight: 600; font-size: 0.9rem;"><?php echo formatPrice($item['subtotal']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="summary-totals" style="border-top: 2px solid #eee; padding-top: 15px; margin-bottom: 20px;">
                <div class="summary-row" style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 1.1rem; font-weight: 700; color: #4CAF50;">
                    <span>Total</span>
                    <span><?php echo formatPrice($totalAmount); ?></span>
                </div>
            </div>
            
            <button type="submit" form="checkout-form" name="place_order" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 1.1rem;">
                Place Order
            </button>
            <a href="cart.php" class="btn-text" style="display: block; text-align: center; margin-top: 15px; color: #777; font-size: 0.9rem;">
                <i class="fas fa-arrow-left"></i> Return to Cart
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
