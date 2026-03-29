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

// Handle product deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $productId = $_GET['delete'];
    
    // Check if product belongs to this farmer
    $query = "SELECT id FROM products WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $productId, $farmerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Delete product images first
        $conn->query("DELETE FROM product_images WHERE product_id = $productId");
        
        // Delete product
        $conn->query("DELETE FROM products WHERE id = $productId");
        
        setFlashMessage('success', 'Product deleted successfully');
    } else {
        setFlashMessage('error', 'You do not have permission to delete this product');
    }
    
    redirect('products.php');
    exit();
}

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Build query based on filters
$query = "SELECT p.*, c.name as category_name, 
          (SELECT image_url FROM product_images WHERE product_id = p.id LIMIT 1) as image_url 
          FROM products p 
          LEFT JOIN product_categories c ON p.category_id = c.id 
          WHERE p.user_id = ?";

$params = [$farmerId];
$types = "i";

if ($search) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

if ($category > 0) {
    $query .= " AND p.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

if ($filter == 'low_stock') {
    $query .= " AND p.stock_quantity < 10";
} elseif ($filter == 'out_of_stock') {
    $query .= " AND p.stock_quantity = 0";
} elseif ($filter == 'active') {
    $query .= " AND p.is_active = 1";
} elseif ($filter == 'inactive') {
    $query .= " AND p.is_active = 0";
}

$query .= " ORDER BY p.created_at DESC";

// Get all categories for filter dropdown
$categories = [];
$catQuery = "SELECT id, name FROM product_categories ORDER BY name";
$catResult = $conn->query($catQuery);
while ($row = $catResult->fetch_assoc()) {
    $categories[] = $row;
}

// Execute the query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
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
                <li class="active"><a href="products.php"><i class="fas fa-leaf"></i> My Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> Orders</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>My Products</h1>
            <a href="add-product.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Product</a>
        </div>
        
        <div class="filter-section">
            <form action="" method="GET" class="filter-form">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <select name="category">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <select name="filter">
                        <option value="" <?php echo $filter == '' ? 'selected' : ''; ?>>All Products</option>
                        <option value="low_stock" <?php echo $filter == 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out_of_stock" <?php echo $filter == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        <option value="active" <?php echo $filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-filter">Filter</button>
                <a href="products.php" class="btn-text">Clear Filters</a>
            </form>
        </div>
        
        <?php if (empty($products)): ?>
            <div class="no-data-container">
                <div class="no-data-message">
                    <i class="fas fa-leaf"></i>
                    <h2>No products found</h2>
                    <p>You haven't added any products that match your filters yet.</p>
                    <a href="add-product.php" class="btn btn-primary">Add Your First Product</a>
                </div>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($product['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php else: ?>
                                <div class="no-image"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                            
                            <div class="product-status">
                                <?php if ($product['is_active']): ?>
                                    <span class="status active">Active</span>
                                <?php else: ?>
                                    <span class="status inactive">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="product-details">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                            <div class="product-meta">
                                <span class="price">KSh <?php echo number_format($product['price'], 2); ?></span>
                                <span class="stock <?php echo $product['stock_quantity'] <= 5 ? 'low-stock' : ''; ?>">
                                    <?php 
                                    if ($product['stock_quantity'] == 0) {
                                        echo 'Out of stock';
                                    } else {
                                        echo $product['stock_quantity'] . ' in stock';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="product-actions">
                            <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn-small btn-edit">Edit</a>
                            <a href="../product.php?id=<?php echo $product['id']; ?>" class="btn-small btn-view" target="_blank">View</a>
                            <a href="products.php?delete=<?php echo $product['id']; ?>" class="btn-small btn-delete" onclick="return confirm('Are you sure you want to delete this product?')">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>