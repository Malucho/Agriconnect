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

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Handle order status update
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    $orderId = intval($_POST['order_id']);
    $newStatus = sanitizeInput($_POST['status']);
    
    // Verify the order belongs to this farmer
    $query = "SELECT o.id FROM orders o 
              JOIN order_items oi ON o.id = oi.order_id 
              JOIN products p ON oi.product_id = p.id 
              WHERE o.id = ? AND p.user_id = ? 
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $orderId, $farmerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update the status
        $query = "UPDATE order_items oi 
                  JOIN products p ON oi.product_id = p.id 
                  SET oi.status = ? 
                  WHERE oi.order_id = ? AND p.user_id = ?";
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

// Build query based on filters
$query = "SELECT o.id, o.created_at, o.total_amount, 
          u.username as customer_name, 
          (SELECT MIN(oi.status) FROM order_items oi 
           JOIN products p ON oi.product_id = p.id 
           WHERE oi.order_id = o.id AND p.user_id = ?) as status,
          (SELECT COUNT(DISTINCT oi.id) FROM order_items oi 
           JOIN products p ON oi.product_id = p.id 
           WHERE oi.order_id = o.id AND p.user_id = ?) as item_count
          FROM orders o
          JOIN users u ON o.user_id = u.id
          WHERE o.id IN (
              SELECT DISTINCT oi.order_id FROM order_items oi
              JOIN products p ON oi.product_id = p.id
              WHERE p.user_id = ?
          )";

$params = [$farmerId, $farmerId, $farmerId];
$types = "iii";

if ($search) {
    $query .= " AND (o.id LIKE ? OR u.username LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

if ($status) {
    $query .= " AND o.id IN (
        SELECT DISTINCT oi.order_id FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE p.user_id = ? AND oi.status = ?
    )";
    $params[] = $farmerId;
    $params[] = $status;
    $types .= "is";
}

if ($dateFrom) {
    $query .= " AND DATE(o.created_at) >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}

if ($dateTo) {
    $query .= " AND DATE(o.created_at) <= ?";
    $params[] = $dateTo;
    $types .= "s";
}

$query .= " ORDER BY o.created_at DESC";

// Execute the query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
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
            <h1>Orders Management</h1>
        </div>
        
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
                        <option value="shipped" <?php echo $status == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $status == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
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
                
                <button type="submit" class="btn btn-filter">Filter</button>
                <a href="orders.php" class="btn-text">Clear Filters</a>
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
                <table class="dashboard-table orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo $order['item_count']; ?> items</td>
                                <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                        <?php echo $order['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn-small">View</a>
                                        <button type="button" class="btn-small btn-update" data-order-id="<?php echo $order['id']; ?>" data-status="<?php echo $order['status']; ?>">Update</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Status Update Modal -->
            <div id="statusModal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Update Order Status</h2>
                    <form action="" method="POST">
                        <input type="hidden" name="order_id" id="modal-order-id">
                        
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
                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                            <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Modal functionality
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('statusModal');
        const modalOrderId = document.getElementById('modal-order-id');
        const modalStatus = document.getElementById('modal-status');
        const updateButtons = document.querySelectorAll('.btn-update');
        const closeBtn = document.querySelector('.close');
        const cancelBtn = document.querySelector('.modal-cancel');
        
        // Open modal when update button is clicked
        updateButtons.forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                const status = this.getAttribute('data-status');
                
                modalOrderId.value = orderId;
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
    });
</script>

<?php include '../includes/footer.php'; ?>