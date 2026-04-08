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

// Handle order deletion
if (isset($_POST['delete_order']) && isset($_POST['order_id'])) {
    $orderId = intval($_POST['order_id']);
    
    $conn->begin_transaction();
    try {
        // Delete related order items first
        $conn->query("DELETE FROM order_items WHERE order_id = $orderId");
        // Delete the order
        $conn->query("DELETE FROM orders WHERE id = $orderId");
        
        $conn->commit();
        setFlashMessage('Order and its items deleted successfully', 'success');
    } catch (Exception $e) {
        $conn->rollback();
        setFlashMessage('Error deleting order: ' . $e->getMessage(), 'danger');
    }
    
    redirect('orders.php');
    exit();
}

// Get all orders
$orders = getAllOrders(50);

$page_title = 'Manage Orders';
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
            <h1>Manage Orders</h1>
            <a href="add-order.php" class="btn btn-primary">Create Manual Order</a>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <div class="table-responsive">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Payment Status</th>
                        <th>Order Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                        <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                        <td><span class="status-badge status-<?php echo $order['payment_status']; ?>"><?php echo ucfirst($order['payment_status']); ?></span></td>
                        <td><span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                        <td>
                            <div class="action-buttons" style="display: flex; gap: 5px;">
                                <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn-small">View</a>
                                <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this order? This cannot be undone.')">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="delete_order" value="1" class="btn-small btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
