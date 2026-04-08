<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    setFlashMessage('You must be logged in as an admin to access this page', 'danger');
    redirect('login.php');
    exit();
}

$adminName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('Invalid order ID', 'danger');
    redirect('orders.php');
    exit();
}

$orderId = $_GET['id'];

// Handle order status update
if (isset($_POST['update_order_status'])) {
    $newStatus = sanitize($_POST['status']);
    $paymentStatus = sanitize($_POST['payment_status']);
    
    $query = "UPDATE orders SET status = ?, payment_status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $newStatus, $paymentStatus, $orderId);
    
    if ($stmt->execute()) {
        // Also update all items in this order to match the general order status if needed
        // Or keep them independent. Usually, if the whole order is cancelled, items should be too.
        if ($newStatus == 'cancelled') {
            $conn->query("UPDATE order_items SET status = 'cancelled' WHERE order_id = $orderId");
        }
        
        setFlashMessage('Order updated successfully', 'success');
    } else {
        setFlashMessage('Error updating order', 'danger');
    }
    
    redirect("order-detail.php?id=$orderId");
    exit();
}

// Get order details
$query = "SELECT o.*, u.first_name, u.last_name, u.email as customer_email, u.phone as customer_phone
          FROM orders o
          JOIN users u ON o.consumer_id = u.id
          WHERE o.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlashMessage('Order not found', 'danger');
    redirect('orders.php');
    exit();
}

$order = $result->fetch_assoc();

// Get order items with farmer details
$query = "SELECT oi.*, p.name as product_name, p.image, p.unit, 
          u.first_name as farmer_first_name, u.last_name as farmer_last_name
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          JOIN users u ON oi.farmer_id = u.id
          WHERE oi.order_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();
$orderItems = [];
while ($row = $result->fetch_assoc()) {
    $orderItems[] = $row;
}

$page_title = 'Order #' . $orderId . ' Details';
include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <h3><?php echo htmlspecialchars($adminName); ?></h3>
            <p>Administrator</p>
        </div>
        <nav class="dashboard-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="products.php"><i class="fas fa-leaf"></i> Manage Products</a></li>
                <li class="active"><a href="orders.php"><i class="fas fa-shopping-basket"></i> Manage Orders</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>Order #<?php echo $orderId; ?> Details</h1>
            <a href="orders.php" class="btn btn-outline">Back to Orders</a>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <div class="order-detail-grid" style="display: grid; grid-template-columns: 1fr 350px; gap: 30px;">
            <div class="order-main-info">
                <div class="dashboard-section" style="margin-bottom: 30px;">
                    <h2>Order Items</h2>
                    <div class="table-responsive">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Farmer</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Subtotal</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <?php if ($item['image']): ?>
                                                <img src="<?php echo SITE_URL; ?>/uploads/products/<?php echo $item['image']; ?>" style="width: 40px; height: 40px; border-radius: 4px; object-fit: cover;">
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($item['product_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['farmer_first_name'] . ' ' . $item['farmer_last_name']); ?></td>
                                    <td>KSh <?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>KSh <?php echo number_format($item['subtotal'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo strtolower($item['status']); ?>"><?php echo $item['status']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" style="text-align: right; font-weight: bold; padding: 15px;">Total Amount:</td>
                                    <td colspan="2" style="font-weight: bold; padding: 15px; color: #4CAF50; font-size: 1.2rem;">KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="dashboard-section">
                    <h2>Customer & Shipping</h2>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                        <div>
                            <h3 style="font-size: 1rem; margin-bottom: 10px; color: #777;">Customer Details</h3>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                        </div>
                        <div>
                            <h3 style="font-size: 1rem; margin-bottom: 10px; color: #777;">Shipping Address</h3>
                            <p><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                            <?php if ($order['delivery_notes']): ?>
                                <p style="margin-top: 10px; font-style: italic;"><strong>Notes:</strong> <?php echo htmlspecialchars($order['delivery_notes']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-sidebar-info">
                <div class="dashboard-section" style="padding: 20px;">
                    <h2>Update Status</h2>
                    <form action="" method="POST">
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px;">Order Status</label>
                            <select name="status" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $order['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="ready" <?php echo $order['status'] == 'ready' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 25px;">
                            <label style="display: block; margin-bottom: 8px;">Payment Status</label>
                            <select name="payment_status" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="pending" <?php echo $order['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo $order['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="failed" <?php echo $order['payment_status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>

                        <button type="submit" name="update_order_status" class="btn btn-primary" style="width: 100%; padding: 12px;">Update Order</button>
                    </form>
                </div>

                <div class="dashboard-section" style="margin-top: 20px; padding: 20px;">
                    <h2>Payment Info</h2>
                    <p><strong>Method:</strong> <?php echo str_replace('_', ' ', ucfirst($order['payment_method'])); ?></p>
                    <p><strong>Date:</strong> <?php echo date('F d, Y h:i A', strtotime($order['order_date'])); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
