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
$farmerName = '';
$totalProducts = countFarmerProducts($farmerId);
$totalOrders = countFarmerOrders($farmerId);
$totalRevenue = calculateFarmerRevenue($farmerId);
$unreadMessages = countUnreadMessages($farmerId);

// Get farmer name
$query = "SELECT name FROM farmer_profiles WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $farmerName = $row['name'];
}

// Get recent orders
$recentOrders = getFarmerOrders($farmerId, 5);

// Get low stock products (less than 10 items)
$lowStockProducts = [];
$query = "SELECT id, name, price, stock_quantity FROM products 
          WHERE user_id = ? AND stock_quantity < 10 
          ORDER BY stock_quantity ASC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $lowStockProducts[] = $row;
}

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
                <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-leaf"></i> My Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> Orders</a></li>
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
        <h1>Farmer Dashboard</h1>
        
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon products-icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <div class="stat-details">
                    <h3>Total Products</h3>
                    <p class="stat-number"><?php echo $totalProducts; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orders-icon">
                    <i class="fas fa-shopping-basket"></i>
                </div>
                <div class="stat-details">
                    <h3>Total Orders</h3>
                    <p class="stat-number"><?php echo $totalOrders; ?></p>
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
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-details">
                    <h3>Messages</h3>
                    <p class="stat-number"><?php echo $unreadMessages; ?> unread</p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-sections">
            <div class="dashboard-section">
                <h2>Recent Orders</h2>
                <?php if (empty($recentOrders)): ?>
                    <p class="no-data">No recent orders found.</p>
                <?php else: ?>
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo strtolower($order['status']); ?>"><?php echo $order['status']; ?></span></td>
                                    <td><a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn-small">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="view-all">
                        <a href="orders.php" class="btn-text">View All Orders <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="dashboard-section">
                <h2>Low Stock Products</h2>
                <?php if (empty($lowStockProducts)): ?>
                    <p class="no-data">No low stock products.</p>
                <?php else: ?>
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td>KSh <?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <span class="stock-level <?php echo $product['stock_quantity'] <= 5 ? 'critical' : 'warning'; ?>">
                                            <?php echo $product['stock_quantity']; ?> left
                                        </span>
                                    </td>
                                    <td><a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn-small">Update</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="view-all">
                        <a href="products.php?filter=low_stock" class="btn-text">View All Low Stock <i class="fas fa-arrow-right"></i></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-sections">
            <div class="dashboard-section full-width">
                <h2>Quick Actions</h2>
                <div class="quick-actions">
                    <a href="add-product.php" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <h3>Add New Product</h3>
                        <p>List a new product for sale</p>
                    </a>
                    
                    <a href="profile.php" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="fas fa-user-edit"></i>
                        </div>
                        <h3>Update Profile</h3>
                        <p>Edit your farm details</p>
                    </a>
                    
                    <a href="messages.php" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="fas fa-envelope-open-text"></i>
                        </div>
                        <h3>Check Messages</h3>
                        <p>Respond to customer inquiries</p>
                    </a>
                    
                    <a href="../marketplace.php" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <h3>Visit Marketplace</h3>
                        <p>See how your products appear</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Add any dashboard-specific JavaScript here
    document.addEventListener('DOMContentLoaded', function() {
        // Example: Auto-refresh dashboard stats every 5 minutes
        setInterval(function() {
            // This would typically use AJAX to refresh stats without page reload
            // For now, we'll just log a message
            console.log('Dashboard stats would refresh here');
        }, 300000); // 5 minutes
    });
</script>

<?php include '../includes/footer.php'; ?>