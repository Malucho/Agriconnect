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
$farmerName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$unreadMessages = getUnreadMessagesCount($farmerId);

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'Invalid order ID');
    redirect('orders.php');
    exit();
}

$orderId = $_GET['id'];

// Get order details
$query = "SELECT o.*, u.first_name, u.last_name, u.email as customer_email, u.phone as customer_phone
          FROM orders o
          JOIN users u ON o.consumer_id = u.id
          WHERE o.id = ? AND o.id IN (
              SELECT DISTINCT order_id FROM order_items WHERE farmer_id = ?
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
$query = "SELECT oi.*, p.name as product_name, p.image, p.unit
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          WHERE oi.order_id = ? AND oi.farmer_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $orderId, $farmerId);
$stmt->execute();
$result = $stmt->get_result();
$orderItems = [];
$farmer_subtotal = 0;

while ($row = $result->fetch_assoc()) {
    $orderItems[] = $row;
    $farmer_subtotal += $row['subtotal'];
}

// Handle status update for individual items
if (isset($_POST['update_item_status']) && isset($_POST['item_id']) && isset($_POST['status'])) {
    $itemId = intval($_POST['item_id']);
    $newStatus = sanitize($_POST['status']);
    
    // Verify the item belongs to this farmer
    $query = "SELECT id FROM order_items WHERE id = ? AND farmer_id = ?";
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

$page_title = 'Order #' . $orderId . ' Details';
include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <h3><?php echo htmlspecialchars($farmerName); ?></h3>
            <p>Farmer</p>
        </div>
        <nav class="dashboard-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-leaf"></i> My Products</a></li>
                <li class="active"><a href="orders.php"><i class="fas fa-shopping-basket"></i> Orders</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages
                    <?php if ($unreadMessages > 0): ?>
                        <span class="badge"><?php echo $unreadMessages; ?></span>
                    <?php endif; ?>
                </a></li>
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
                            <span class="info-value"><?php echo date('F d, Y h:i A', strtotime($order['order_date'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Customer:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Payment Method:</span>
                            <span class="info-value"><?php echo str_replace('_', ' ', ucfirst($order['payment_method'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Payment Status:</span>
                            <span class="info-value"><?php echo ucfirst($order['payment_status']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="order-info-card">
                    <h2>Shipping Information</h2>
                    <div class="info-grid">
                        <div class="info-item full-width">
                            <span class="info-label">Address:</span>
                            <span class="info-value"><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></span>
                        </div>
                        <?php if (!empty($order['delivery_notes'])): ?>
                            <div class="info-item full-width">
                                <span class="info-label">Delivery Notes:</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['delivery_notes']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="order-items-section">
                <h2>My Items in this Order</h2>
                <div class="table-responsive">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td class="product-cell">
                                        <div class="product-info" style="display: flex; align-items: center; gap: 10px;">
                                            <?php if ($item['image']): ?>
                                                <img src="<?php echo '../uploads/products/' . htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                                <div class="no-image" style="width: 50px; height: 50px; background: #eee; display: flex; align-items: center; justify-content: center; border-radius: 4px;"><i class="fas fa-image"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <h4 style="margin: 0;"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                            </div>
                                        </div>
                                    </td>
                                    <td>KSh <?php echo number_format($item['unit_price'], 2); ?> / <?php echo htmlspecialchars($item['unit'] ?? 'kg'); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>KSh <?php echo number_format($item['subtotal'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($item['status']); ?>">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-small btn-update" 
                                                onclick="openItemStatusModal(<?php echo $item['id']; ?>, '<?php echo $item['status']; ?>')">
                                            Update
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right; font-weight: bold;">My Total:</td>
                                <td colspan="3" style="font-weight: bold;">KSh <?php echo number_format($farmer_subtotal, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <div class="order-actions" style="margin-top: 20px; display: flex; gap: 10px;">
                <a href="messages.php?new=<?php echo $order['consumer_id']; ?>" class="btn btn-secondary">
                    <i class="fas fa-envelope"></i> Message Customer
                </a>
                <button onclick="window.print()" class="btn btn-outline">
                    <i class="fas fa-print"></i> Print Order
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Item Status Update Modal -->
<div id="itemStatusModal" class="modal" style="display:none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color: #fff; margin: 15% auto; padding: 20px; border-radius: 8px; width: 400px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Update Item Status</h2>
            <span onclick="closeItemStatusModal()" style="cursor: pointer; font-size: 24px;">&times;</span>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="item_id" id="modal-item-id">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="status" style="display: block; margin-bottom: 8px;">New Status:</label>
                <select name="status" id="modal-item-status" required class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="processing">Processing</option>
                    <option value="ready">Ready for Pickup</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeItemStatusModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" name="update_item_status" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
function openItemStatusModal(itemId, currentStatus) {
    document.getElementById('modal-item-id').value = itemId;
    document.getElementById('modal-item-status').value = currentStatus;
    document.getElementById('itemStatusModal').style.display = 'block';
}

function closeItemStatusModal() {
    document.getElementById('itemStatusModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    var modal = document.getElementById('itemStatusModal');
    if (event.target == modal) {
        closeItemStatusModal();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
