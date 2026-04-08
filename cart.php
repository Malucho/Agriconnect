<?php
session_start();
include_once 'includes/config.php';
include_once 'includes/functions.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    setFlashMessage('Please login to access your cart.', 'warning');
    redirect('login.php');
}

// Redirect to marketplace if not a consumer
if ($_SESSION['user_type'] !== 'consumer') {
    setFlashMessage('Only consumers can access the cart.', 'warning');
    redirect('marketplace.php');
}

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Remove item from cart
    if ($action === 'remove' && isset($_GET['id'])) {
        $product_id = (int)$_GET['id'];
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
            setFlashMessage('Item removed from cart.', 'success');
        }
    }
    
    // Update quantity
    else if ($action === 'update' && isset($_POST['quantity'])) {
        foreach ($_POST['quantity'] as $product_id => $quantity) {
            $product_id = (int)$product_id;
            $quantity = (int)$quantity;
            
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$product_id]);
            } else {
                // Get product to check stock
                $product = getProductById($product_id);
                if ($product && $product['quantity_available'] >= $quantity) {
                    $_SESSION['cart'][$product_id] = $quantity;
                } else if ($product) {
                    $_SESSION['cart'][$product_id] = $product['quantity_available'];
                    setFlashMessage("Quantity for {$product['name']} adjusted to available stock.", 'warning');
                }
            }
        }
        setFlashMessage('Cart updated successfully.', 'success');
    }
    
    // Clear cart
    else if ($action === 'clear') {
        $_SESSION['cart'] = [];
        setFlashMessage('Cart cleared successfully.', 'success');
    }
    
    redirect('cart.php');
}

// Get cart items with product details
$cart_items = [];
$total_price = 0;

foreach ($_SESSION['cart'] as $product_id => $quantity) {
    $product = getProductById($product_id);
    
    if ($product && $product['status'] === 'available') {
        // Check if quantity exceeds available stock
        if ($quantity > $product['quantity_available']) {
            $_SESSION['cart'][$product_id] = $product['quantity_available'];
            $quantity = $product['quantity_available'];
            setFlashMessage("Quantity for {$product['name']} adjusted to available stock.", 'warning');
        }
        
        $item_price = $product['price'] * $quantity;
        $total_price += $item_price;
        
        $cart_items[] = [
            'id' => $product_id,
            'name' => $product['name'],
            'price' => $product['price'],
            'unit' => $product['unit'],
            'quantity' => $quantity,
            'item_price' => $item_price,
            'image' => getProductFirstImage($product_id),
            'farmer_name' => $product['first_name'] . ' ' . $product['last_name'],
            'quantity_available' => $product['quantity_available']
        ];
    } else {
        // Remove invalid products from cart
        unset($_SESSION['cart'][$product_id]);
    }
}

$page_title = 'Shopping Cart';
include_once 'includes/head.php';
include_once 'includes/header.php';
?>

<main class="cart-page">
    <div class="container">
        <div class="content-header" style="margin-bottom: 30px;">
            <h1>Shopping Cart</h1>
            <?php if (!empty($cart_items)): ?>
                <a href="cart.php?action=clear" class="btn btn-outline" onclick="return confirm('Are you sure you want to clear your cart?')"><i class="fas fa-trash-alt"></i> Clear Cart</a>
            <?php endif; ?>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <?php if (empty($cart_items)): ?>
            <div class="empty-cart-container">
                <i class="fas fa-shopping-basket"></i>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added any fresh produce to your cart yet.</p>
                <a href="marketplace.php" class="btn btn-primary">Browse Marketplace</a>
            </div>
        <?php else: ?>
            <form action="cart.php?action=update" method="POST">
                <div class="cart-container">
                    <div class="cart-items">
                        <div class="table-responsive">
                            <table class="cart-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="cart-product">
                                                    <?php if ($item['image']): ?>
                                                        <img src="uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="Product" class="cart-product-img">
                                                    <?php else: ?>
                                                        <div class="cart-product-img" style="display: flex; align-items: center; justify-content: center; color: #ccc;"><i class="fas fa-image fa-2x"></i></div>
                                                    <?php endif; ?>
                                                    <div class="cart-product-info">
                                                        <h4><a href="product.php?id=<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a></h4>
                                                        <p>Farmer: <?php echo htmlspecialchars($item['farmer_name']); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="cart-price">KES <?php echo number_format($item['price'], 2); ?></td>
                                            <td class="cart-qty">
                                                <input type="number" name="quantity[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['quantity_available']; ?>">
                                                <span style="font-size: 0.8rem; display: block; margin-top: 5px; color: #95a5a6;"><?php echo htmlspecialchars($item['unit']); ?></span>
                                            </td>
                                            <td class="cart-total">KES <?php echo number_format($item['item_price'], 2); ?></td>
                                            <td>
                                                <a href="cart.php?action=remove&id=<?php echo $item['id']; ?>" class="cart-remove" title="Remove Item"><i class="fas fa-times"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="padding: 20px; display: flex; justify-content: space-between; align-items: center; background: #fafafa; border-top: 1px solid #f1f1f1;">
                            <a href="marketplace.php" class="btn-text"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
                            <button type="submit" class="btn btn-outline">Update Quantities</button>
                        </div>
                    </div>
                    
                    <div class="cart-summary-card">
                        <h2>Order Summary</h2>
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>KES <?php echo number_format($total_price, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Delivery</span>
                            <span style="font-size: 0.85rem;">Calculated at checkout</span>
                        </div>
                        <div class="summary-row total">
                            <span>Grand Total</span>
                            <span>KES <?php echo number_format($total_price, 2); ?></span>
                        </div>
                        <a href="checkout.php" class="btn btn-primary" style="width: 100%; margin-top: 25px; padding: 15px; font-size: 1.1rem;">Proceed to Checkout <i class="fas fa-arrow-right" style="margin-left: 10px;"></i></a>
                        
                        <div style="margin-top: 20px; text-align: center;">
                            <img src="assets/images/payment-methods.png" alt="Payment Methods" style="max-width: 100%; opacity: 0.6;">
                            <p style="font-size: 0.75rem; color: #bdc3c7; margin-top: 10px;"><i class="fas fa-lock"></i> Secure Checkout Guaranteed</p>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php include_once 'includes/footer.php'; ?>
