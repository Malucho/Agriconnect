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

$adminId = $_SESSION['user_id'];
$adminName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Get overall stats
$totalFarmers = countAllUsers('farmer');
$totalConsumers = countAllUsers('consumer');
$totalProducts = countAllProducts();
$totalOrders = countAllOrders();
$totalRevenue = calculateTotalRevenue();

// Get recent activity
$recentUsers = getAllUsers(5);
$recentProducts = getAllProducts(5);
$recentOrders = getAllOrders(5);

$page_title = 'Admin Dashboard';
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
                <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="products.php"><i class="fas fa-leaf"></i> Manage Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> Manage Orders</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>Admin Dashboard</h1>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon products-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-details">
                    <h3>Total Farmers</h3>
                    <p class="stat-number"><?php echo $totalFarmers; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orders-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-details">
                    <h3>Total Consumers</h3>
                    <p class="stat-number"><?php echo $totalConsumers; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon revenue-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-details">
                    <h3>Total Revenue</h3>
                    <p class="stat-number">KSh <?php echo number_format($totalRevenue, 2); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon messages-icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <div class="stat-details">
                    <h3>Total Products</h3>
                    <p class="stat-number"><?php echo $totalProducts; ?></p>
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
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                                <td><span class="status-badge status-<?php echo strtolower($order['status']); ?>"><?php echo $order['status']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>New Users</h2>
                    <a href="users.php" class="btn-small">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Joined</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><span class="type-badge <?php echo $user['user_type']; ?>"><?php echo ucfirst($user['user_type']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($user['registration_date'])); ?></td>
                                <td><span class="status-badge status-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
