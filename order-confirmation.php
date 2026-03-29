<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and is a consumer
if (!isLoggedIn()) {
    setFlashMessage('error', 'You must be logged in to view order confirmation');
    redirect('login.php');
    exit();
}

if ($_SESSION['user_type'] != 'consumer') {
    setFlashMessage('error', 'Only consumers can view order confirmation');
    redirect('marketplace.php');
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'Invalid order ID');
    redirect('marketplace.php');
    exit();
}

$orderId = $_GET['id'];
$userId = $_SESSION['user_id'];

// Get order details
$query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlashMessage('error', 'Order not found or you do not have permission to view it');
    redirect('marketplace.php');
    exit();
}

$order = $result->fetch_assoc();

// Get order items grouped by farmer
$query = "SELECT oi.*, p.name as product_name, p.price, p.unit, 
          u.username as farmer_name, u.id as farmer_id,
          (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1) as image_url
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          JOIN users u ON p.user_id = u.id
          WHERE oi.order_id = ?
          ORDER BY u.id";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();

$orderItems = [];
$farmerGroups = [];

while ($item = $result->fetch_assoc()) {
    $orderItems[] = $item;
    
    // Group by farmer
    if (!isset($farmerGroups[$item['farmer_id']])) {
        $farmerGroups[$item['farmer_id']] = [
            'farmer_name' => $item['farmer_name'],
            'items' => []
        ];
    }
    
    $farmerGroups[$item['farmer_id']]['items'][] = $item;
}

include 'includes/header.php';
?>

<div class="order-confirmation-container">
    <div class="confirmation-header">
        <div class="confirmation-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1>Order Confirmed!</h1>
        <p>Your order has been placed successfully. Thank you for shopping with Agriconnect!</p>
    </div>
    
    <div class="order-details">
        <div class="order-info-section">
            <h2>Order Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Order Number:</span>
                    <span class="info-value">#<?php echo $orderId; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Order Date:</span>
                    <span class="info-value"><?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['payment_method']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Delivery Method:</span>
                    <span class="info-value"><?php echo ucfirst(htmlspecialchars($order['delivery_method'])); ?> Delivery</span>
                </div>
            </div>
        </div>
        
        <div class="shipping-info-section">
            <h2>Shipping Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">City:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['shipping_city']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['shipping_phone']); ?></span>
                </div>
                <?php if (!empty($order['notes'])): ?>
                    <div class="info-item full-width">
                        <span class="info-label">Notes:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['notes']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="order-items-section">
            <h2>Order Summary</h2>
            
            <?php foreach ($farmerGroups as $farmerId => $farmerGroup): ?>
                <div class="farmer-group">
                    <h3>From: <?php echo htmlspecialchars($farmerGroup['farmer_name']); ?></h3>
                    
                    <div class="order-items">
                        <?php foreach ($farmerGroup['items'] as $item): ?>
                            <div class="order-item">
                                <div class="item-image">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                    <?php else: ?>
                                        <div class="no-image"><i class="fas fa-image"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="item-details">
                                    <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                    <p>KSh <?php echo number_format($item['price'], 2); ?> / <?php echo htmlspecialchars($item['unit']); ?></p>
                                    <p>Quantity: <?php echo $item['quantity']; ?></p>
                                </div>
                                <div class="item-price">
                                    <p>KSh <?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="order-totals">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>KSh <?php echo number_format($order['total_amount'] - $order['delivery_fee'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span>Delivery Fee:</span>
                    <span>KSh <?php echo number_format($order['delivery_fee'], 2); ?></span>
                </div>
                <div class="total-row grand-total">
                    <span>Total:</span>
                    <span>KSh <?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <div class="payment-instructions">
            <h2>Payment Instructions</h2>
            
            <?php if ($order['payment_method'] == 'M-Pesa'): ?>
                <div class="payment-method-instructions">
                    <h3>M-Pesa Payment</h3>
                    <ol>
                        <li>Go to M-Pesa on your phone</li>
                        <li>Select "Lipa na M-Pesa"</li>
                        <li>Select "Pay Bill"</li>
                        <li>Enter Business Number: <strong>123456</strong></li>
                        <li>Enter Account Number: <strong>AG<?php echo $orderId; ?></strong></li>
                        <li>Enter Amount: <strong>KSh <?php echo number_format($order['total_amount'], 2); ?></strong></li>
                        <li>Enter your M-Pesa PIN</li>
                        <li>Confirm the transaction</li>
                    </ol>
                </div>
            <?php elseif ($order['payment_method'] == 'Bank Transfer'): ?>
                <div class="payment-method-instructions">
                    <h3>Bank Transfer</h3>
                    <p>Please transfer the total amount to our bank account:</p>
                    <div class="bank-details">
                        <p><strong>Bank Name:</strong> Kenya Commercial Bank</p>
                        <p><strong>Account Name:</strong> Agriconnect Ltd</p>
                        <p><strong>Account Number:</strong> 1234567890</p>
                        <p><strong>Branch:</strong> Nairobi</p>
                        <p><strong>Reference:</strong> AG<?php echo $orderId; ?></p>
                        <p><strong>Amount:</strong> KSh <?php echo number_format($order['total_amount'], 2); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="payment-note">
                <p><i class="fas fa-info-circle"></i> Your order will be processed once payment is confirmed.</p>
            </div>
        </div>
        
        <div class="confirmation-actions">
            <a href="marketplace.php" class="btn btn-secondary">
                <i class="fas fa-shopping-basket"></i> Continue Shopping
            </a>
            <a href="#" class="btn btn-primary print-order">
                <i class="fas fa-print"></i> Print Order
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const printBtn = document.querySelector('.print-order');
        if (printBtn) {
            printBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.print();
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>