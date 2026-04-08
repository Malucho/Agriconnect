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

// Handle product deletion
if (isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $productId = intval($_POST['product_id']);
    
    // First, delete related entries
    $conn->query("DELETE FROM reviews WHERE product_id = $productId");
    $conn->query("DELETE FROM order_items WHERE product_id = $productId");
    $conn->query("DELETE FROM products WHERE id = $productId");
    
    setFlashMessage('Product deleted successfully', 'success');
    redirect('products.php');
    exit();
}

// Handle product status update
if (isset($_POST['update_status']) && isset($_POST['product_id']) && isset($_POST['status'])) {
    $productId = intval($_POST['product_id']);
    $newStatus = sanitize($_POST['status']);
    
    $query = "UPDATE products SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $newStatus, $productId);
    
    if ($stmt->execute()) {
        setFlashMessage('Product status updated successfully', 'success');
    } else {
        setFlashMessage('Error updating product status', 'danger');
    }
    
    redirect('products.php');
    exit();
}

// Get all products
$products = getAllProducts(50);

$page_title = 'Manage Products';
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
                <li class="active"><a href="products.php"><i class="fas fa-leaf"></i> Manage Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> Manage Orders</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>Manage Products</h1>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <div class="table-responsive">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Product Name</th>
                        <th>Farmer</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td>#<?php echo $product['id']; ?></td>
                        <td>
                            <?php if (!empty($product['image'])): ?>
                                <img src="<?php echo SITE_URL; ?>/uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="Product" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                            <?php else: ?>
                                <img src="<?php echo SITE_URL; ?>/assets/images/product-placeholder.jpg" alt="No image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                        <td>KSh <?php echo number_format($product['price'], 2); ?></td>
                        <td><span class="status-badge status-<?php echo $product['status']; ?>"><?php echo ucfirst($product['status']); ?></span></td>
                        <td>
                            <form action="" method="POST" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <?php if ($product['status'] == 'available'): ?>
                                    <button type="submit" name="update_status" value="1" class="btn-small btn-warning">
                                        <input type="hidden" name="status" value="hidden">
                                        Hide
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="update_status" value="1" class="btn-small btn-success">
                                        <input type="hidden" name="status" value="available">
                                        Show
                                    </button>
                                <?php endif; ?>
                                <button type="submit" name="delete_product" value="1" class="btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this product?')">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
