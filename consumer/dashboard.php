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
$consumerName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Get recent orders
$recentOrders = [];
$query = "SELECT * FROM orders WHERE consumer_id = ? ORDER BY order_date DESC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $consumerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentOrders[] = $row;
}

// Get stats
$totalOrders = count($_SESSION['cart'] ?? []); // Placeholder for active cart items
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE consumer_id = ?");
$stmt->bind_param("i", $consumerId);
$stmt->execute();
$totalOrdersCount = $stmt->get_result()->fetch_assoc()['count'];

$page_title = 'Consumer Dashboard';
include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <h3><?php echo htmlspecialchars($consumerName); ?></h3>
            <p>Consumer</p>
        </div>
        <nav class="dashboard-nav">
            <ul>
                <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> My Orders</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="../marketplace.php"><i class="fas fa-leaf"></i> Shop Products</a></li>
                <li><a href="../cart.php"><i class="fas fa-shopping-cart"></i> View Cart</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon products-icon">
                    <i class="fas fa-shopping-basket"></i>
                </div>
                <div class="stat-details">
                    <h3>Total Orders</h3>
                    <p class="stat-number"><?php echo $totalOrdersCount; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orders-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-details">
                    <h3>Items in Cart</h3>
                    <p class="stat-number"><?php echo count($_SESSION['cart'] ?? []); ?></p>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Recent Orders</h2>
                    <a href="orders.php" class="btn-small">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No orders found. <a href="../marketplace.php">Start shopping!</a></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                    <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo strtolower($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                    <td><a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn-small">View</a></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
