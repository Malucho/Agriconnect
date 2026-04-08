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

// Get monthly revenue for the last 6 months
$monthlyRevenue = [];
$query = "SELECT DATE_FORMAT(order_date, '%b %Y') as month, SUM(total_amount) as revenue 
          FROM orders 
          WHERE order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          AND (status = 'completed' OR payment_status = 'paid')
          GROUP BY month 
          ORDER BY order_date ASC";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $monthlyRevenue[] = $row;
}

// Get top 5 selling products
$topProducts = [];
$query = "SELECT p.name, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as total_revenue
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          WHERE oi.status = 'completed'
          GROUP BY p.id
          ORDER BY total_sold DESC
          LIMIT 5";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $topProducts[] = $row;
}

// Get revenue by category
$categoryRevenue = [];
$query = "SELECT c.name, SUM(oi.subtotal) as revenue
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          JOIN product_categories c ON p.category_id = c.id
          WHERE oi.status = 'completed'
          GROUP BY c.id
          ORDER BY revenue DESC";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $categoryRevenue[] = $row;
}

$page_title = 'Site Reports & Analytics';
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
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> Manage Orders</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li class="active"><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>Reports & Analytics</h1>
            <button onclick="window.print()" class="btn btn-outline"><i class="fas fa-print"></i> Print Report</button>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <div class="reports-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- Revenue Trends -->
            <div class="dashboard-section">
                <h2>Revenue Trends (Last 6 Months)</h2>
                <?php if (empty($monthlyRevenue)): ?>
                    <p class="no-data">No revenue data available yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthlyRevenue as $data): ?>
                                <tr>
                                    <td><?php echo $data['month']; ?></td>
                                    <td>KSh <?php echo number_format($data['revenue'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Category Performance -->
            <div class="dashboard-section">
                <h2>Category Performance</h2>
                <?php if (empty($categoryRevenue)): ?>
                    <p class="no-data">No category data available yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryRevenue as $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($data['name']); ?></td>
                                    <td>KSh <?php echo number_format($data['revenue'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Selling Products -->
            <div class="dashboard-section" style="grid-column: span 2;">
                <h2>Top 5 Selling Products</h2>
                <?php if (empty($topProducts)): ?>
                    <p class="no-data">No product data available yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Units Sold</th>
                                    <th>Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topProducts as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo $product['total_sold']; ?></td>
                                    <td>KSh <?php echo number_format($product['total_revenue'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.no-data {
    padding: 20px;
    text-align: center;
    color: #777;
    background: #f9f9f9;
    border-radius: 4px;
}
@media print {
    .dashboard-sidebar, .content-header .btn, header, footer {
        display: none !important;
    }
    .dashboard-container {
        display: block !important;
    }
    .dashboard-content {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
