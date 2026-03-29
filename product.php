<?php
session_start();
include_once 'includes/config.php';
include_once 'includes/functions.php';

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('marketplace.php');
}

$product_id = (int)$_GET['id'];
$product = getProductById($product_id);

// If product doesn't exist or is not active, redirect to marketplace
if (!$product || $product['status'] !== 'active') {
    setFlashMessage('error', 'Product not found or no longer available.');
    redirect('marketplace.php');
}

// Get product images
$images = getProductImages($product_id);

// Get farmer details
$farmer = getUserById($product['farmer_id']);

// Get product reviews
$reviews = getProductReviews($product_id);
$avg_rating = calculateAverageRating($product_id);

// Process review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && isLoggedIn() && $_SESSION['user_type'] === 'consumer') {
    $rating = (int)$_POST['rating'];
    $comment = sanitize($_POST['comment']);
    $user_id = $_SESSION['user_id'];
    
    // Validate input
    $errors = [];
    
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Rating must be between 1 and 5";
    }
    
    if (empty($comment)) {
        $errors[] = "Please provide a review comment";
    }
    
    // Check if user has already reviewed this product
    $stmt = $conn->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $product_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "You have already reviewed this product";
    }
    
    // Check if user has purchased this product
    $stmt = $conn->prepare("SELECT oi.id FROM order_items oi 
                           JOIN orders o ON oi.order_id = o.id 
                           WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'completed'");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $errors[] = "You can only review products you have purchased";
    }
    
    // If no errors, save the review
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $product_id, $user_id, $rating, $comment);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Your review has been submitted successfully.');
            redirect("product.php?id=$product_id");
        } else {
            $errors[] = "Failed to submit review. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Agriconnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    
    <main>
        <section class="product-detail">
            <div class="container">
                <div class="breadcrumb">
                    <a href="index.php">Home</a> &gt;
                    <a href="marketplace.php">Marketplace</a> &gt;
                    <a href="marketplace.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a> &gt;
                    <span><?php echo htmlspecialchars($product['name']); ?></span>
                </div>
                
                <div class="product-detail-container">
                    <div class="product-gallery">
                        <?php if (!empty($images)): ?>
                            <div class="main-image">
                                <img id="main-product-image" src="<?php echo htmlspecialchars($images[0]['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                            <?php if (count($images) > 1): ?>
                                <div class="thumbnail-images">
                                    <?php foreach ($images as $index => $image): ?>
                                        <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" onclick="changeMainImage('<?php echo htmlspecialchars($image['image_url']); ?>', this)">
                                            <img src="<?php echo htmlspecialchars($image['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?> thumbnail">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="main-image">
                                <img src="assets/images/product-placeholder.jpg" alt="No image available">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-info">
                        <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                        
                        <div class="product-meta">
                            <span class="category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                            <div class="product-rating">
                                <?php 
                                $rating = round($avg_rating);
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<i class="fas fa-star"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                                <span class="review-count">(<?php echo count($reviews); ?> reviews)</span>
                            </div>
                        </div>
                        
                        <div class="product-price">
                            <span class="price"><?php echo formatPrice($product['price']); ?></span>
                            <span class="unit">per <?php echo htmlspecialchars($product['unit']); ?></span>
                        </div>
                        
                        <div class="product-availability">
                            <?php if ($product['stock_quantity'] > 0): ?>
                                <span class="in-stock">In Stock (<?php echo $product['stock_quantity']; ?> <?php echo htmlspecialchars($product['unit']); ?> available)</span>
                            <?php else: ?>
                                <span class="out-of-stock">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-description">
                            <h3>Description</h3>
                            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        </div>
                        
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <form class="add-to-cart-form">
                                <div class="quantity-selector">
                                    <label for="quantity">Quantity (<?php echo htmlspecialchars($product['unit']); ?>):</label>
                                    <div class="quantity-controls">
                                        <button type="button" class="quantity-btn minus" onclick="decrementQuantity()">-</button>
                                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                                        <button type="button" class="quantity-btn plus" onclick="incrementQuantity(<?php echo $product['stock_quantity']; ?>)">+</button>
                                    </div>
                                </div>
                                
                                <button type="button" class="btn btn-primary add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <div class="farmer-info">
                            <h3>About the Farmer</h3>
                            <div class="farmer-profile">
                                <div class="farmer-image">
                                    <?php if (!empty($farmer['profile_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($farmer['profile_image']); ?>" alt="<?php echo htmlspecialchars($farmer['first_name'] . ' ' . $farmer['last_name']); ?>">
                                    <?php else: ?>
                                        <img src="assets/images/farmer-placeholder.jpg" alt="Farmer profile">
                                    <?php endif; ?>
                                </div>
                                <div class="farmer-details">
                                    <h4><?php echo htmlspecialchars($farmer['first_name'] . ' ' . $farmer['last_name']); ?></h4>
                                    <p class="farmer-location"><?php echo htmlspecialchars($farmer['county'] . ', ' . $farmer['sub_county']); ?></p>
                                    <a href="farmer-profile.php?id=<?php echo $farmer['id']; ?>" class="btn btn-outline">View Profile</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="product-tabs">
                    <div class="tabs-header">
                        <button class="tab-btn active" data-tab="reviews">Reviews (<?php echo count($reviews); ?>)</button>
                        <button class="tab-btn" data-tab="shipping">Shipping & Delivery</button>
                    </div>
                    
                    <div class="tab-content active" id="reviews">
                        <?php if (isLoggedIn() && $_SESSION['user_type'] === 'consumer'): ?>
                            <div class="write-review">
                                <h3>Write a Review</h3>
                                
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <ul>
                                            <?php foreach ($errors as $error): ?>
                                                <li><?php echo $error; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                
                                <form action="product.php?id=<?php echo $product_id; ?>" method="post">
                                    <div class="form-group">
                                        <label for="rating">Rating:</label>
                                        <div class="star-rating">
                                            <input type="radio" id="star5" name="rating" value="5" required>
                                            <label for="star5"><i class="far fa-star"></i></label>
                                            <input type="radio" id="star4" name="rating" value="4">
                                            <label for="star4"><i class="far fa-star"></i></label>
                                            <input type="radio" id="star3" name="rating" value="3">
                                            <label for="star3"><i class="far fa-star"></i></label>
                                            <input type="radio" id="star2" name="rating" value="2">
                                            <label for="star2"><i class="far fa-star"></i></label>
                                            <input type="radio" id="star1" name="rating" value="1">
                                            <label for="star1"><i class="far fa-star"></i></label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="comment">Your Review:</label>
                                        <textarea id="comment" name="comment" rows="4" required></textarea>
                                    </div>
                                    
                                    <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <div class="reviews-list">
                            <h3>Customer Reviews</h3>
                            
                            <?php if (empty($reviews)): ?>
                                <p>No reviews yet. Be the first to review this product!</p>
                            <?php else: ?>
                                <?php foreach ($reviews as $review): ?>
                                    <div class="review-item">
                                        <div class="review-header">
                                            <div class="reviewer-info">
                                                <h4><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></h4>
                                                <span class="review-date"><?php echo date('F j, Y', strtotime($review['created_at'])); ?></span>
                                            </div>
                                            <div class="review-rating">
                                                <?php 
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= $review['rating']) {
                                                        echo '<i class="fas fa-star"></i>';
                                                    } else {
                                                        echo '<i class="far fa-star"></i>';
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="review-content">
                                            <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="shipping">
                        <h3>Shipping & Delivery Information</h3>
                        <div class="shipping-info">
                            <div class="shipping-section">
                                <h4><i class="fas fa-truck"></i> Delivery Options</h4>
                                <p>We offer several delivery options:</p>
                                <ul>
                                    <li><strong>Pickup:</strong> Collect directly from the farmer at agreed location</li>
                                    <li><strong>Local Delivery:</strong> Available for select areas within the county</li>
                                    <li><strong>Courier Service:</strong> Nationwide delivery through our partner couriers</li>
                                </ul>
                            </div>
                            
                            <div class="shipping-section">
                                <h4><i class="fas fa-calendar-alt"></i> Delivery Timeframes</h4>
                                <ul>
                                    <li><strong>Local Delivery:</strong> 1-2 business days</li>
                                    <li><strong>Courier Service:</strong> 2-5 business days depending on location</li>
                                </ul>
                            </div>
                            
                            <div class="shipping-section">
                                <h4><i class="fas fa-money-bill-wave"></i> Shipping Costs</h4>
                                <p>Shipping costs are calculated at checkout based on:</p>
                                <ul>
                                    <li>Delivery location</li>
                                    <li>Order weight and size</li>
                                    <li>Selected delivery method</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <section class="related-products">
                    <h2>Related Products</h2>
                    <div class="products-slider">
                        <!-- Related products will be loaded via AJAX -->
                    </div>
                </section>
            </div>
        </section>
    </main>
    
    <?php include_once 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script>
        // Change main product image
        function changeMainImage(imageUrl, thumbnail) {
            document.getElementById('main-product-image').src = imageUrl;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            thumbnail.classList.add('active');
        }
        
        // Quantity controls
        function incrementQuantity(max) {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            if (currentValue < max) {
                quantityInput.value = currentValue + 1;
            }
        }
        
        function decrementQuantity() {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        }
        
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons and content
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                // Add active class to clicked button and corresponding content
                this.classList.add('active');
                document.getElementById(this.getAttribute('data-tab')).classList.add('active');
            });
        });
        
        // Star rating
        document.querySelectorAll('.star-rating input').forEach(input => {
            input.addEventListener('change', function() {
                const rating = this.value;
                const stars = document.querySelectorAll('.star-rating label i');
                
                stars.forEach((star, index) => {
                    if (index < rating) {
                        star.className = 'fas fa-star';
                    } else {
                        star.className = 'far fa-star';
                    }
                });
            });
        });
        
        // Add to cart
        document.querySelector('.add-to-cart-btn').addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const quantity = document.getElementById('quantity').value;
            
            // Send AJAX request to add item to cart
            fetch('ajax/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart count in header
                    const cartCount = document.querySelector('.cart-count');
                    if (cartCount) {
                        cartCount.textContent = data.cart_count;
                    }
                    
                    // Show success message
                    alert('Product added to cart!');
                } else {
                    alert(data.message || 'Failed to add product to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
        
        // Load related products
        document.addEventListener('DOMContentLoaded', function() {
            fetch(`ajax/related_products.php?product_id=<?php echo $product_id; ?>&category_id=<?php echo $product['category_id']; ?>`)
            .then(response => response.text())
            .then(data => {
                document.querySelector('.products-slider').innerHTML = data;
            })
            .catch(error => {
                console.error('Error loading related products:', error);
            });
        });
    </script>
</body>
</html>