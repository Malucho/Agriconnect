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

// Handle order status update
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    $orderId = intval($_POST['order_id']);
    $newStatus = sanitize($_POST['status']);
    
    // Verify the order belongs to this farmer (at least one item)
    $query = "SELECT id FROM order_items WHERE order_id = ? AND farmer_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $orderId, $farmerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update the status for all items of this farmer in this order
        $query = "UPDATE order_items SET status = ? WHERE order_id = ? AND farmer_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sii", $newStatus, $orderId, $farmerId);
        $stmt->execute();
        
        setFlashMessage('success', 'Order status updated successfully');
    } else {
        setFlashMessage('error', 'You do not have permission to update this order');
    }
    
    redirect('orders.php');
    exit();
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query based on filters
// We want to find orders that have items belonging to this farmer
$query = "SELECT DISTINCT o.id, o.order_date, o.status as order_status,
          u.first_name, u.last_name,
          (SELECT SUM(subtotal) FROM order_items WHERE order_id = o.id AND farmer_id = ?) as farmer_total,
          (SELECT COUNT(*) FROM order_items WHERE order_id = o.id AND farmer_id = ?) as item_count,
          (SELECT status FROM order_items WHERE order_id = o.id AND farmer_id = ? LIMIT 1) as farmer_item_status
          FROM orders o
          JOIN users u ON o.consumer_id = u.id
          JOIN order_items oi ON o.id = oi.order_id
          WHERE oi.farmer_id = ?";

$params = [$farmerId, $farmerId, $farmerId, $farmerId];
$types = "iiii";

if ($search) {
    $query .= " AND (o.id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

if ($status) {
    $query .= " AND EXISTS (SELECT 1 FROM order_items WHERE order_id = o.id AND farmer_id = ? AND status = ?)";
    $params[] = $farmerId;
    $params[] = $status;
    $types .= "is";
}

if ($dateFrom) {
    $query .= " AND DATE(o.order_date) >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}

if ($dateTo) {
    $query .= " AND DATE(o.order_date) <= ?";
    $params[] = $dateTo;
    $types .= "s";
}

$query .= " ORDER BY o.order_date DESC";

// Execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$page_title = 'Orders Management';
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
            <h1>Orders Management</h1>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <div class="filter-section">
            <form action="" method="GET" class="filter-form">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search by order ID or customer..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <select name="status">
                        <option value="" <?php echo $status == '' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="ready" <?php echo $status == 'ready' ? 'selected' : ''; ?>>Ready for Pickup</option>
                        <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_from">From:</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">To:</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="orders.php" class="btn btn-outline">Clear</a>
            </form>
        </div>
        
        <?php if (empty($orders)): ?>
            <div class="no-data-container">
                <div class="no-data-message">
                    <i class="fas fa-shopping-basket"></i>
                    <h2>No orders found</h2>
                    <p>You haven't received any orders that match your filters yet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>My Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                <td><?php echo $order['item_count']; ?> items</td>
                                <td>KSh <?php echo number_format($order['farmer_total'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($order['farmer_item_status']); ?>">
                                        <?php echo ucfirst($order['farmer_item_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn-small">View</a>
                                        <button type="button" class="btn-small btn-update" onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['farmer_item_status']; ?>')">Update</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Status Update Modal -->
            <div id="statusModal" class="modal" style="display:none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                <div class="modal-content" style="background-color: #fff; margin: 15% auto; padding: 20px; border-radius: 8px; width: 400px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0;">Update Order Status</h2>
                        <span onclick="closeStatusModal()" style="cursor: pointer; font-size: 24px;">&times;</span>
                    </div>
                    <form action="" method="POST">
                        <input type="hidden" name="order_id" id="modal-order-id">
                        
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="status" style="display: block; margin-bottom: 8px;">New Status:</label>
                            <select name="status" id="modal-status" required class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="processing">Processing</option>
                                <option value="ready">Ready for Pickup</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 10px;">
                            <button type="button" onclick="closeStatusModal()" class="btn btn-outline">Cancel</button>
                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function openStatusModal(orderId, currentStatus) {
    document.getElementById('modal-order-id').value = orderId;
    document.getElementById('modal-status').value = currentStatus;
    document.getElementById('statusModal').style.display = 'block';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    var modal = document.getElementById('statusModal');
    if (event.target == modal) {
        closeStatusModal();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
