<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a consumer
if (!isLoggedIn() || $_SESSION['user_type'] != 'consumer') {
    setFlashMessage('Please login as a consumer to access this page', 'danger');
    redirect('../login.php');
    exit();
}

$consumerId = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('Invalid order ID', 'danger');
    redirect('orders.php');
    exit();
}

$orderId = $_GET['id'];

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND consumer_id = ?");
$stmt->bind_param("ii", $orderId, $consumerId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    setFlashMessage('Order not found', 'danger');
    redirect('orders.php');
    exit();
}

// Get order items
$items = [];
$query = "SELECT oi.*, p.name, p.image, u.first_name as farmer_name, u.last_name as farmer_last 
          FROM order_items oi 
          JOIN products p ON oi.product_id = p.id 
          JOIN users u ON oi.farmer_id = u.id 
          WHERE oi.order_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

$page_title = 'Order Details #' . $orderId;
include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar">
                <?php if (!empty($_SESSION['profile_image'])): ?>
                    <img src="../uploads/profiles/<?php echo $_SESSION['profile_image']; ?>" alt="Profile Picture">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            <h3><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h3>
            <p>Consumer Dashboard</p>
        </div>
        <nav class="dashboard-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="active"><a href="orders.php"><i class="fas fa-shopping-basket"></i> My Orders</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="../marketplace.php"><i class="fas fa-leaf"></i> Shop Products</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>Order #<?php echo $orderId; ?></h1>
            <a href="orders.php" class="btn btn-outline">Back to Orders</a>
        </div>
        
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Order Information</h2>
            </div>
            <div class="order-info-grid">
                <div>
                    <h3>Order Details</h3>
                    <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($order['order_date'])); ?></p>
                    <p><strong>Status:</strong> <span class="status-badge status-<?php echo strtolower($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span></p>
                    <p><strong>Payment:</strong> <?php echo ucfirst($order['payment_status']); ?> (<?php echo str_replace('_', ' ', $order['payment_method']); ?>)</p>
                </div>
                <div>
                    <h3>Shipping Address</h3>
                    <p><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                </div>
            </div>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h2>Order Items</h2>
            </div>
            <div class="table-responsive">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Farmer</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="product-info-cell">
                                    <?php if ($item['image']): ?>
                                        <img src="../uploads/products/<?php echo $item['image']; ?>" alt="Product Image" class="product-thumb">
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($item['farmer_name'] . ' ' . $item['farmer_last']); ?></td>
                            <td>KSh <?php echo number_format($item['unit_price'], 2); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>KSh <?php echo number_format($item['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-right font-bold">Total Paid:</td>
                            <td class="font-bold text-primary text-lg">KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
