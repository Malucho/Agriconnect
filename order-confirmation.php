<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in and is a consumer
if (!isLoggedIn()) {
    setFlashMessage('You must be logged in to view order confirmation', 'danger');
    redirect('login.php');
    exit();
}

if ($_SESSION['user_type'] != 'consumer') {
    setFlashMessage('Only consumers can view order confirmation', 'danger');
    redirect('marketplace.php');
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('Invalid order ID', 'danger');
    redirect('marketplace.php');
    exit();
}

$orderId = $_GET['id'];
$userId = $_SESSION['user_id'];

// Get order details
$query = "SELECT * FROM orders WHERE id = ? AND consumer_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlashMessage('Order not found or you do not have permission to view it', 'danger');
    redirect('marketplace.php');
    exit();
}

$order = $result->fetch_assoc();

// Get order items grouped by farmer
$query = "SELECT oi.*, p.name as product_name, p.price, p.unit, p.image,
          u.first_name as farmer_first_name, u.last_name as farmer_last_name, u.id as farmer_id
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          JOIN users u ON p.farmer_id = u.id
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
            'farmer_name' => $item['farmer_first_name'] . ' ' . $item['farmer_last_name'],
            'items' => []
        ];
    }
    
    $farmerGroups[$item['farmer_id']]['items'][] = $item;
}

$page_title = 'Order Confirmation';
include 'includes/head.php';
include 'includes/header.php';
?>

<div class="container" style="padding: 60px 15px; text-align: center;">
    <div class="confirmation-header" style="margin-bottom: 40px;">
        <div class="confirmation-icon" style="font-size: 5rem; color: #4CAF50; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1 style="font-size: 2.5rem; margin-bottom: 10px;">Order Confirmed!</h1>
        <p style="font-size: 1.1rem; color: #777;">Your order #<?php echo $orderId; ?> has been placed successfully. Thank you for shopping with Agriconnect!</p>
    </div>
    
    <div class="order-details-card" style="max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); text-align: left;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
            <div>
                <h2 style="font-size: 1.2rem; margin-bottom: 15px;">Order Information</h2>
                <div style="margin-bottom: 8px;"><span style="font-weight: 600;">Date:</span> <?php echo date('F d, Y h:i A', strtotime($order['order_date'])); ?></div>
                <div style="margin-bottom: 8px;"><span style="font-weight: 600;">Payment Method:</span> <?php echo str_replace('_', ' ', ucfirst($order['payment_method'])); ?></div>
                <div style="margin-bottom: 8px;"><span style="font-weight: 600;">Payment Status:</span> <?php echo ucfirst($order['payment_status']); ?></div>
            </div>
            <div>
                <h2 style="font-size: 1.2rem; margin-bottom: 15px;">Delivery Address</h2>
                <div style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></div>
            </div>
        </div>

        <h2 style="font-size: 1.2rem; margin-bottom: 20px;">Order Summary</h2>
        <div class="order-summary-items">
            <?php foreach ($farmerGroups as $farmerId => $group): ?>
                <div style="margin-bottom: 20px; border-bottom: 1px solid #f9f9f9; padding-bottom: 15px;">
                    <h3 style="font-size: 1rem; color: #4CAF50; margin-bottom: 12px;">Farmer: <?php echo htmlspecialchars($group['farmer_name']); ?></h3>
                    <?php foreach ($group['items'] as $item): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 40px; height: 40px; background: #eee; border-radius: 4px; overflow: hidden; flex-shrink: 0;">
                                    <?php if ($item['image']): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="Product" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #ccc;"><i class="fas fa-image"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div style="font-size: 0.85rem; color: #777;"><?php echo $item['quantity']; ?> x <?php echo formatPrice($item['price']); ?></div>
                                </div>
                            </div>
                            <div style="font-weight: 600;"><?php echo formatPrice($item['subtotal']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="display: flex; justify-content: space-between; margin-top: 20px; padding-top: 15px; border-top: 2px solid #eee; font-size: 1.2rem; font-weight: 700; color: #4CAF50;">
            <span>Total Paid</span>
            <span><?php echo formatPrice($order['total_amount']); ?></span>
        </div>

        <div style="margin-top: 40px; display: flex; gap: 15px; justify-content: center;">
            <a href="marketplace.php" class="btn btn-primary" style="padding: 12px 25px;">Continue Shopping</a>
            <a href="consumer/orders.php" class="btn btn-outline" style="padding: 12px 25px;">View My Orders</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
