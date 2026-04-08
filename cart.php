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

<div class="container" style="padding: 40px 15px;">
    <div class="cart-header" style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
        <h1>Your Shopping Cart</h1>
        <?php if (!empty($cart_items)): ?>
            <a href="cart.php?action=clear" class="btn btn-outline" onclick="return confirm('Are you sure you want to clear your cart?')">Clear Cart</a>
        <?php endif; ?>
    </div>
    
    <?php displayFlashMessages(); ?>
    
    <?php if (empty($cart_items)): ?>
        <div class="empty-cart" style="text-align: center; padding: 60px 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <i class="fas fa-shopping-cart" style="font-size: 4rem; color: #eee; margin-bottom: 20px;"></i>
            <h2>Your cart is empty</h2>
            <p style="color: #777; margin-bottom: 30px;">Looks like you haven't added anything to your cart yet.</p>
            <a href="marketplace.php" class="btn btn-primary">Start Shopping</a>
        </div>
    <?php else: ?>
        <form action="cart.php?action=update" method="POST">
            <div class="cart-content" style="display: grid; grid-template-columns: 1fr 350px; gap: 30px;">
                <div class="cart-items-container">
                    <div class="table-responsive">
                        <table class="dashboard-table" style="width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                            <thead>
                                <tr style="background: #f9f9f9; text-align: left;">
                                    <th style="padding: 15px;">Product</th>
                                    <th style="padding: 15px;">Price</th>
                                    <th style="padding: 15px;">Quantity</th>
                                    <th style="padding: 15px;">Total</th>
                                    <th style="padding: 15px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $item): ?>
                                    <tr style="border-bottom: 1px solid #eee;">
                                        <td style="padding: 15px;">
                                            <div style="display: flex; align-items: center; gap: 15px;">
                                                <div style="width: 60px; height: 60px; background: #eee; border-radius: 4px; overflow: hidden; flex-shrink: 0;">
                                                    <?php if ($item['image']): ?>
                                                        <img src="uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="Product" style="width: 100%; height: 100%; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #ccc;"><i class="fas fa-image"></i></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h4 style="margin: 0; font-size: 1rem;"><a href="product.php?id=<?php echo $item['id']; ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($item['name']); ?></a></h4>
                                                    <p style="margin: 0; font-size: 0.8rem; color: #777;">Farmer: <?php echo htmlspecialchars($item['farmer_name']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 15px;">KES <?php echo number_format($item['price'], 2); ?></td>
                                        <td style="padding: 15px;">
                                            <input type="number" name="quantity[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['quantity_available']; ?>" style="width: 60px; padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
                                        </td>
                                        <td style="padding: 15px; font-weight: 600;">KES <?php echo number_format($item['item_price'], 2); ?></td>
                                        <td style="padding: 15px;">
                                            <a href="cart.php?action=remove&id=<?php echo $item['id']; ?>" style="color: #e74c3c;"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                        <a href="marketplace.php" class="btn-text"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
                        <button type="submit" class="btn btn-outline">Update Cart</button>
                    </div>
                </div>
                
                <div class="cart-summary" style="background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); height: fit-content;">
                    <h2 style="margin-bottom: 20px; font-size: 1.5rem; border-bottom: 1px solid #eee; padding-bottom: 10px;">Cart Summary</h2>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                        <span>Items (<?php echo count($cart_items); ?>)</span>
                        <span>KES <?php echo number_format($total_price, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 25px; font-weight: 700; font-size: 1.2rem; color: #4CAF50;">
                        <span>Total</span>
                        <span>KES <?php echo number_format($total_price, 2); ?></span>
                    </div>
                    <a href="checkout.php" class="btn btn-primary" style="width: 100%; padding: 15px; text-align: center; display: block;">Proceed to Checkout</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include_once 'includes/footer.php'; ?>
