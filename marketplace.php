<?php
session_start();
include_once 'includes/config.php';
include_once 'includes/functions.php';

// Get categories for filter
$categories = getProductCategories();

// Handle filtering and pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // Products per page
$offset = ($page - 1) * $limit;

// Default query parameters
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search_term = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if ($category_id > 0) {
    $conditions[] = "p.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

if (!empty($search_term)) {
    $search_term = "%$search_term%";
    $conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// Only show active products
$conditions[] = "p.status = 'available'";

// Build the WHERE clause
$where_clause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : '';

// Build the ORDER BY clause
switch ($sort_by) {
    case 'price_low':
        $order_by = "ORDER BY p.price ASC";
        break;
    case 'price_high':
        $order_by = "ORDER BY p.price DESC";
        break;
    case 'name_asc':
        $order_by = "ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $order_by = "ORDER BY p.name DESC";
        break;
    case 'oldest':
        $order_by = "ORDER BY p.date_added ASC";
        break;
    case 'newest':
    default:
        $order_by = "ORDER BY p.date_added DESC";
        break;
}

// Count total products for pagination
$count_sql = "SELECT COUNT(*) as total FROM products p $where_clause";
$stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    // Fix: Use bind_param with individual parameters instead of spread operator
    $ref_params = array();
    $ref_params[] = &$types;
    for($i = 0; $i < count($params); $i++) {
        $ref_params[] = &$params[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $ref_params);
}

$stmt->execute();
$total_result = $stmt->get_result()->fetch_assoc();
$total_products = $total_result['total'];
$total_pages = ceil($total_products / $limit);

// Get products
$sql = "SELECT p.*, c.name as category_name, u.first_name, u.last_name, 
        (SELECT AVG(rating) FROM reviews WHERE product_id = p.id) as avg_rating,
        (SELECT COUNT(*) FROM reviews WHERE product_id = p.id) as review_count
        FROM products p
        LEFT JOIN product_categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.farmer_id = u.id
        $where_clause
        $order_by
        LIMIT ?, ?";

$stmt = $conn->prepare($sql);

// Add limit and offset to params
$params[] = $offset;
$params[] = $limit;
$types .= 'ii';

// Fix: Use bind_param with individual parameters instead of spread operator
$ref_params = array();
$ref_params[] = &$types;
for($i = 0; $i < count($params); $i++) {
    $ref_params[] = &$params[$i];
}
call_user_func_array(array($stmt, 'bind_param'), $ref_params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$page_title = 'Marketplace';
include_once 'includes/head.php';
include_once 'includes/header.php';
?>
    
    <main>
        <section class="marketplace-header">
            <div class="container">
                <h1>Marketplace</h1>
                <p>Browse fresh produce directly from Kenyan farmers</p>
            </div>
        </section>
        
        <section class="marketplace-filters">
            <div class="container">
                <form action="marketplace.php" method="get" class="filter-form">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search_term); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </div>
                    
                    <div class="filter-options">
                        <div class="filter-group">
                            <label for="category">Category:</label>
                            <select name="category" id="category" onchange="this.form.submit()">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="sort">Sort By:</label>
                            <select name="sort" id="sort" onchange="this.form.submit()">
                                <option value="newest" <?php echo ($sort_by == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo ($sort_by == 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="price_low" <?php echo ($sort_by == 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo ($sort_by == 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="name_asc" <?php echo ($sort_by == 'name_asc') ? 'selected' : ''; ?>>Name: A to Z</option>
                                <option value="name_desc" <?php echo ($sort_by == 'name_desc') ? 'selected' : ''; ?>>Name: Z to A</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </section>
        
        <section class="products-grid">
            <div class="container">
                <?php if (empty($products)): ?>
                    <div class="no-products">
                        <h3>No products found</h3>
                        <p>Try adjusting your search or filter criteria</p>
                    </div>
                <?php else: ?>
                    <div class="products-container">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <?php if (!empty($product['image'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <img src="<?php echo SITE_URL; ?>/assets/images/product-placeholder.jpg" alt="No image available">
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <h3><a href="product.php?id=<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a></h3>
                                    <div class="product-meta">
                                        <span class="category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                        <span class="farmer">By <?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></span>
                                    </div>
                                    <div class="product-rating">
                                        <?php 
                                        $rating = round($product['avg_rating'] ?? 0);
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $rating) {
                                                echo '<i class="fas fa-star"></i>';
                                            } else {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                        }
                                        ?>
                                        <span class="review-count">(<?php echo $product['review_count'] ?? 0; ?>)</span>
                                    </div>
                                    <div class="product-price">
                                        <span class="price"><?php echo formatPrice($product['price']); ?></span>
                                        <span class="unit">per <?php echo htmlspecialchars($product['unit']); ?></span>
                                    </div>
                                    <div class="product-actions">
                                        <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-outline">View Details</a>
                                        <?php if ($product['quantity_available'] > 0): ?>
                                            <button class="btn btn-primary add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                                                <i class="fas fa-shopping-cart"></i> Add to Cart
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-disabled" disabled>Out of Stock</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php echo getPaginationLinks($page, $total_pages, "marketplace.php?category=$category_id&search=" . urlencode($search_term) . "&sort=$sort_by&page=%d"); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <?php include_once 'includes/footer.php'; ?>