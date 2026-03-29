<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a farmer
if (!isLoggedIn() || $_SESSION['user_type'] != 'farmer') {
    setFlashMessage('error', 'You must be logged in as a farmer to access this page');
    redirect('../login.php');
    exit();
}

$farmerId = $_SESSION['user_id'];

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'Invalid order ID');
    redirect('orders.php');
    exit();
}

$orderId = $_GET['id'];

// Get order details
$query = "SELECT o.*, u.username as customer_name, u.email as customer_email,
          o.shipping_address, o.shipping_city, o.shipping_phone, o.payment_method,
          o.created_at, o.total_amount, o.delivery_fee, o.notes
          FROM orders o
          JOIN users u ON o.user_id = u.id
          WHERE o.id = ? AND o.id IN (
              SELECT DISTINCT oi.order_id FROM order_items oi
              JOIN products p ON oi.product_id = p.id
              WHERE p.user_id = ?
          )";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $orderId, $farmerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlashMessage('error', 'Order not found or you do not have permission to view it');
    redirect('orders.php');
    exit();
}

$order = $result->fetch_assoc();

// Get order items that belong to this farmer
$query = "SELECT oi.*, p.name as product_name, p.price, p.unit,
          (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1) as image_url
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          WHERE oi.order_id = ? AND p.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $orderId, $farmerId);
$stmt->execute();
$result = $stmt->get_result();
$orderItems = [];
$subtotal = 0;

while ($row = $result->fetch_assoc()) {
    $orderItems[] = $row;
    $subtotal += $row['price'] * $row['quantity'];
}

// Handle status update for individual items
if (isset($_POST['update_item_status']) && isset($_POST['item_id']) && isset($_POST['status'])) {
    $itemId = intval($_POST['item_id']);
    $newStatus = sanitizeInput($_POST['status']);
    
    // Verify the item belongs to this farmer's product
    $query = "SELECT oi.id FROM order_items oi 
              JOIN products p ON oi.product_id = p.id 
              WHERE oi.id = ? AND p.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $itemId, $farmerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update the status
        $query = "UPDATE order_items SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $newStatus, $itemId);
        $stmt->execute();
        
        setFlashMessage('success', 'Item status updated successfully');
        redirect('order-detail.php?id=' . $orderId);
        exit();
    }
}

include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <h3><?php echo htmlspecialchars($_SESSION['username']); ?></h3>
            <p>Farmer</p>
        </div>
        <nav class="dashboard-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-leaf"></i> My Products</a></li>
                <li class="active"><a href="orders.php"><i class="fas fa-shopping-basket"></i> Orders</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>Order #<?php echo $orderId; ?> Details</h1>
            <a href="orders.php" class="btn-text"><i class="fas fa-arrow-left"></i> Back to Orders</a>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <div class="order-detail-container">
            <div class="order-info-section">
                <div class="order-info-card">
                    <h2>Order Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Order Date:</span>
                            <span class="info-value"><?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Customer:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Payment Method:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['payment_method']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="order-info-card">
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
            </div>
            
            <div class="order-items-section">
                <h2>Order Items</h2>
                <div class="table-responsive">
                    <table class="dashboard-table items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td class="product-cell">
                                        <div class="product-info">
                                            <?php if ($item['image_url']): ?>
                                                <img src="<?php echo '../' . htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                            <?php else: ?>
                                                <div class="no-image"><i class="fas fa-image"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                            </div>
                                        </div>
                                    </td>
                                    <td>KSh <?php echo number_format($item['price'], 2); ?> / <?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>KSh <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($item['status']); ?>">
                                            <?php echo $item['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-small btn-update" 
                                                data-item-id="<?php echo $item['id']; ?>" 
                                                data-status="<?php echo $item['status']; ?>">
                                            Update Status
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="order-summary-section">
                <div class="order-summary-card">
                    <h2>Order Summary</h2>
                    <div class="summary-items">
                        <div class="summary-item">
                            <span>Subtotal:</span>
                            <span>KSh <?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="summary-item">
                            <span>Delivery Fee:</span>
                            <span>KSh <?php echo number_format($order['delivery_fee'], 2); ?></span>
                        </div>
                        <div class="summary-item total">
                            <span>Total:</span>
                            <span>KSh <?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="order-actions">
                    <a href="messages.php?new=<?php echo $order['user_id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-envelope"></i> Message Customer
                    </a>
                    <a href="#" class="btn btn-primary print-order">
                        <i class="fas fa-print"></i> Print Order
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Update Item Status</h2>
        <form action="" method="POST">
            <input type="hidden" name="item_id" id="modal-item-id">
            
            <div class="form-group">
                <label for="status">New Status:</label>
                <select name="status" id="modal-status" required>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="update_item_status" class="btn btn-primary">Update Status</button>
                <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Modal functionality
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('statusModal');
        const modalItemId = document.getElementById('modal-item-id');
        const modalStatus = document.getElementById('modal-status');
        const updateButtons = document.querySelectorAll('.btn-update');
        const closeBtn = document.querySelector('.close');
        const cancelBtn = document.querySelector('.modal-cancel');
        
        // Open modal when update button is clicked
        updateButtons.forEach(button => {
            button.addEventListener('click', function() {
                const itemId = this.getAttribute('data-item-id');
                const status = this.getAttribute('data-status');
                
                modalItemId.value = itemId;
                modalStatus.value = status;
                
                modal.style.display = 'block';
            });
        });
        
        // Close modal when X is clicked
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }
        
        // Close modal when Cancel is clicked
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
        
        // Print functionality
        const printBtn = document.querySelector('.print-order');
        if (printBtn) {
            printBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.print();
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>