<?php
session_start();
include_once 'includes/config.php';
include_once 'includes/functions.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    setFlashMessage('error', 'Please login to access your cart.');
    redirect('login.php');
}

// Redirect to marketplace if not a consumer
if ($_SESSION['user_type'] !== 'consumer') {
    setFlashMessage('error', 'Only consumers can access the cart.');
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
            setFlashMessage('success', 'Item removed from cart.');
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
                if ($product && $product['stock_quantity'] >= $quantity) {
                    $_SESSION['cart'][$product_id] = $quantity;
                } else if ($product) {
                    $_SESSION['cart'][$product_id] = $product['stock_quantity'];
                    setFlashMessage('warning', "Quantity for {$product['name']} adjusted to available stock.");
                }
            }
        }
        setFlashMessage('success', 'Cart updated successfully.');
    }
    
    // Clear cart
    else if ($action === 'clear') {
        $_SESSION['cart'] = [];
        setFlashMessage('success', 'Cart cleared successfully.');
    }
    
    redirect('cart.php');
}

// Get cart items with product details
$cart_items = [];
$total_price = 0;

foreach ($_SESSION['cart'] as $product_id => $quantity) {
    $product = getProductById($product_id);
    
    if ($product && $product['status'] === 'active') {
        // Check if quantity exceeds available stock
        if ($quantity > $product['stock_quantity']) {
            $_SESSION['cart'][$product_id] = $product['stock_quantity'];
            $quantity = $product['stock_quantity'];
            setFlashMessage('warning', "Quantity for {$product['name']} adjusted to available stock.");
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
            'image_url' => getProductFirstImage($product_id),
            'farmer_name' => getFarmerName($product['farmer_id']),
            'stock_quantity' => $product['stock_quantity']
        ];
    } else {
        // Remove invalid products from cart
        unset($_SESSION['cart'][$product_id]);
    }
}

// Group cart items by farmer
$items_by_farmer = [];
foreach ($cart_items as $item) {
    if (!isset($items_by_farmer[$item['farmer_name']])) {
        $items_by_farmer[$item['farmer_name']] = [];
    }
    $items_by_farmer[$item['farmer_name']][] = $item;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Agriconnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    
    <main>
        <section class="cart-section">
            <div class="container">
                <h1>Shopping Cart</h1>
                
                <?php if (empty($cart_items)): ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart fa-4x"></i>
                        <h2>Your cart is empty</h2>
                        <p>Looks like you haven't added any products to your cart yet.</p>
                        <a href="marketplace.php" class="btn btn-primary">Browse Products</a>
                    </div>
                <?php else: ?>
                    <div class="cart-actions">
                        <a href="cart.php?action=clear" class="btn btn-outline" onclick="return confirm('Are you sure you want to clear your cart?')">
                            <i class="fas fa-trash"></i> Clear Cart
                        </a>
                    </div>
                    
                    <form action="cart.php?action=update" method="post" id="cart-form">
                        <?php foreach ($items_by_farmer as $farmer_name => $items): ?>
                            <div class="cart-farmer-group">
                                <h3>Products from <?php echo htmlspecialchars($farmer_name); ?></h3>
                                
                                <div class="cart-items">
                                    <?php foreach ($items as $item): ?>
                                        <div class="cart-item">
                                            <div class="cart-item-image">
                                                <?php if (!empty($item['image_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                <?php else: ?>
                                                    <img src="assets/images/product-placeholder.jpg" alt="No image available">
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="cart-item-details">
                                                <h4><a href="product.php?id=<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a></h4>
                                                <p class="item-price"><?php echo formatPrice($item['price']); ?> per <?php echo htmlspecialchars($item['unit']); ?></p>
                                                <p class="item-farmer">Sold by: <?php echo htmlspecialchars($item['farmer_name']); ?></p>
                                                <p class="item-stock">In stock: <?php echo $item['stock_quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?></p>
                                            </div>
                                            
                                            <div class="cart-item-quantity">
                                                <div class="quantity-controls">
                                                    <button type="button" class="quantity-btn minus" onclick="decrementQuantity(<?php echo $item['id']; ?>)">-</button>
                                                    <input type="number" name="quantity[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock_quantity']; ?>" class="quantity-input" data-id="<?php echo $item['id']; ?>" onchange="updateSubtotal(<?php echo $item['id']; ?>)">
                                                    <button type="button" class="quantity-btn plus" onclick="incrementQuantity(<?php echo $item['id']; ?>, <?php echo $item['stock_quantity']; ?>)">+</button>
                                                </div>
                                            </div>
                                            
                                            <div class="cart-item-subtotal">
                                                <p class="subtotal" id="subtotal-<?php echo $item['id']; ?>" data-price="<?php echo $item['price']; ?>"><?php echo formatPrice($item['item_price']); ?></p>
                                            </div>
                                            
                                            <div class="cart-item-actions">
                                                <a href="cart.php?action=remove&id=<?php echo $item['id']; ?>" class="remove-item" onclick="return confirm('Are you sure you want to remove this item?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="cart-summary">
                            <div class="cart-totals">
                                <div class="total-row">
                                    <span>Subtotal:</span>
                                    <span id="cart-subtotal"><?php echo formatPrice($total_price); ?></span>
                                </div>
                                <div class="total-row">
                                    <span>Delivery Fee:</span>
                                    <span>To be calculated at checkout</span>
                                </div>
                                <div class="total-row grand-total">
                                    <span>Total:</span>
                                    <span id="cart-total"><?php echo formatPrice($total_price); ?></span>
                                </div>
                            </div>
                            
                            <div class="cart-buttons">
                                <button type="submit" class="btn btn-outline">Update Cart</button>
                                <a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <?php include_once 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script>
        // Quantity controls
        function incrementQuantity(productId, maxStock) {
            const input = document.querySelector(`input[name="quantity[${productId}]"]`);
            const currentValue = parseInt(input.value);
            if (currentValue < maxStock) {
                input.value = currentValue + 1;
                updateSubtotal(productId);
                updateCartTotal();
            }
        }
        
        function decrementQuantity(productId) {
            const input = document.querySelector(`input[name="quantity[${productId}]"]`);
            const currentValue = parseInt(input.value);
            if (currentValue > 1) {
                input.value = currentValue - 1;
                updateSubtotal(productId);
                updateCartTotal();
            }
        }
        
        // Update item subtotal
        function updateSubtotal(productId) {
            const input = document.querySelector(`input[name="quantity[${productId}]"]`);
            const subtotalElement = document.getElementById(`subtotal-${productId}`);
            const price = parseFloat(subtotalElement.getAttribute('data-price'));
            const quantity = parseInt(input.value);
            
            const subtotal = price * quantity;
            subtotalElement.textContent = formatPrice(subtotal);
            
            updateCartTotal();
        }
        
        // Update cart total
        function updateCartTotal() {
            let total = 0;
            document.querySelectorAll('.subtotal').forEach(element => {
                const price = parseFloat(element.getAttribute('data-price'));
                const productId = element.id.replace('subtotal-', '');
                const quantity = parseInt(document.querySelector(`input[name="quantity[${productId}]"]`).value);
                total += price * quantity;
            });
            
            document.getElementById('cart-subtotal').textContent = formatPrice(total);
            document.getElementById('cart-total').textContent = formatPrice(total);
        }
        
        // Format price
        function formatPrice(price) {
            return 'KSh ' + price.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
    </script>
</body>
</html>