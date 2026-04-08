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

// Get all orders for this consumer
$orders = [];
$query = "SELECT * FROM orders WHERE consumer_id = ? ORDER BY order_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $consumerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$page_title = 'My Orders';
include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar"><i class="fas fa-user-circle"></i></div>
            <h3><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h3>
            <p>Consumer</p>
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
        <div class="content-header"><h1>My Orders</h1></div>
        <?php displayFlashMessages(); ?>
        
        <?php if (empty($orders)): ?>
            <div class="no-data-container">
                <div class="no-data-message">
                    <i class="fas fa-shopping-bag"></i>
                    <h2>No orders yet</h2>
                    <p>You haven't placed any orders yet. <a href="../marketplace.php">Start shopping!</a></p>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                            <td><span class="status-badge status-<?php echo $order['payment_status']; ?>"><?php echo ucfirst($order['payment_status']); ?></span></td>
                            <td><span class="status-badge status-<?php echo strtolower($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span></td>
                            <td><a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn-small">View Details</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
