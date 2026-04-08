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
    $query = "SELECT id FROM products WHERE id = ? AND farmer_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $productId, $farmerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
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
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN product_categories c ON p.category_id = c.id 
          WHERE p.farmer_id = ?";

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
    $query .= " AND p.quantity_available < 10";
} elseif ($filter == 'out_of_stock') {
    $query .= " AND p.quantity_available = 0";
} elseif ($filter == 'active') {
    $query .= " AND p.status = 'available'";
} elseif ($filter == 'inactive') {
    $query .= " AND p.status = 'hidden'";
}

$query .= " ORDER BY p.date_added DESC";

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

$page_title = 'My Products';
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
            <h3><?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?></h3>
            <p>Farmer Dashboard</p>
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
                            <?php if ($product['image']): ?>
                                <img src="../uploads/products/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>">
                            <?php else: ?>
                                <img src="../assets/images/product-placeholder.jpg" alt="No image">
                            <?php endif; ?>
                            
                            <div class="product-status">
                                <?php if ($product['status'] !== 'hidden'): ?>
                                    <span class="status active">Active</span>
                                <?php else: ?>
                                    <span class="status inactive">Hidden</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="product-details">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                            <div class="product-meta">
                                <span class="price">KSh <?php echo number_format($product['price'], 2); ?></span>
                                <span class="stock <?php echo ($product['quantity_available'] <= 5 && $product['quantity_available'] > 0) ? 'low-stock' : ($product['quantity_available'] == 0 ? 'out-of-stock' : ''); ?>">
                                    <?php 
                                    if ($product['quantity_available'] == 0) {
                                        echo '<i class="fas fa-times-circle"></i> Out of stock';
                                    } elseif ($product['quantity_available'] <= 5) {
                                        echo '<i class="fas fa-exclamation-triangle"></i> ' . $product['quantity_available'] . ' left';
                                    } else {
                                        echo '<i class="fas fa-check-circle"></i> ' . $product['quantity_available'] . ' in stock';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="product-actions">
                            <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn-small btn-edit" title="Edit Product">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="../product.php?id=<?php echo $product['id']; ?>" class="btn-small btn-view" target="_blank" title="Preview Product">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="products.php?delete=<?php echo $product['id']; ?>" class="btn-small btn-delete" onclick="return confirm('Are you sure you want to delete this product?')" title="Delete Product">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>