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
$totalProducts = countFarmerProducts($farmerId);
$totalOrders = countFarmerOrders($farmerId);
$totalRevenue = calculateFarmerRevenue($farmerId);
$unreadMessages = getUnreadMessagesCount($farmerId);

// Get farm name
$query = "SELECT farm_name FROM farmer_profiles WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $farmName = $row['farm_name'];
} else {
    $farmName = $farmerName . "'s Farm";
}

// Get recent orders
$recentOrders = getFarmerOrders($farmerId, 5);

// Get low stock products (less than 10 items)
$lowStockProducts = [];
$query = "SELECT id, name, price, quantity_available FROM products 
          WHERE farmer_id = ? AND quantity_available < 10 
          ORDER BY quantity_available ASC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $farmerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $lowStockProducts[] = $row;
}

include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar">
                <?php if (!empty($_SESSION['profile_image'])): ?>
                    <img src="../uploads/profiles/<?php echo $_SESSION['profile_image']; ?>" alt="Profile Picture">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            <h3><?php echo htmlspecialchars($farmerName); ?></h3>
            <p><?php echo htmlspecialchars($farmName); ?></p>
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
                    <h3>Unread Messages</h3>
                    <p class="stat-number"><?php echo $unreadMessages; ?></p>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-section recent-orders">
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
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentOrders)): ?>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                    <td>KSh <?php echo number_format($order['subtotal'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo strtolower($order['status']); ?>"><?php echo $order['status']; ?></span></td>
                                    <td><a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn-small">View</a></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-data">No orders found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="dashboard-section low-stock">
                <div class="section-header">
                    <h2>Low Stock Alerts</h2>
                    <a href="products.php?filter=low_stock" class="btn-small">View All</a>
                </div>
                <div class="table-responsive">
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
                            <?php if (!empty($lowStockProducts)): ?>
                                <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td>KSh <?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <span class="stock-level <?php echo $product['quantity_available'] <= 5 ? 'critical' : 'warning'; ?>">
                                            <?php echo $product['quantity_available']; ?> left
                                        </span>
                                    </td>
                                    <td><a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn-small">Update</a></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="no-data">No low stock alerts</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
