<?php
session_start();
include_once 'includes/config.php';
include_once 'includes/functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriconnect - Connecting Kenyan Farmers and Consumers</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
    
    <main>
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Welcome to Agriconnect</h1>
                    <p>Connecting Kenyan farmers directly with consumers for fair, transparent agricultural trade</p>
                    <div class="hero-buttons">
                        <a href="register.php?type=farmer" class="btn btn-primary">Join as Farmer</a>
                        <a href="register.php?type=consumer" class="btn btn-secondary">Join as Consumer</a>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="assets/images/hero-image.svg" alt="Kenyan farmers and consumers connected">
                </div>
            </div>
        </section>

        <section class="features">
            <div class="container">
                <h2>Why Choose Agriconnect?</h2>
                <div class="feature-grid">
                    <div class="feature-card">
                        <i class="fas fa-hand-holding-usd"></i>
                        <h3>Fair Pricing</h3>
                        <p>Eliminate middlemen and ensure farmers receive fair compensation for their produce.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-leaf"></i>
                        <h3>Fresh Produce</h3>
                        <p>Access fresh, quality produce directly from local Kenyan farms.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-sync-alt"></i>
                        <h3>Transparent Trade</h3>
                        <p>Know exactly where your food comes from and who grew it.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-users"></i>
                        <h3>Community Support</h3>
                        <p>Support local farmers and strengthen Kenya's agricultural economy.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="how-it-works">
            <div class="container">
                <h2>How Agriconnect Works</h2>
                <div class="steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <h3>Farmers List Produce</h3>
                        <p>Farmers upload their available produce, set prices, and manage inventory.</p>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <h3>Consumers Browse & Order</h3>
                        <p>Consumers browse listings, view farmer profiles, and place orders.</p>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <h3>Direct Connection</h3>
                        <p>Farmers and consumers coordinate delivery or pickup options.</p>
                    </div>
                    <div class="step">
                        <div class="step-number">4</div>
                        <h3>Sustainable Growth</h3>
                        <p>Both parties benefit from fair prices and direct relationships.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="featured-products">
            <div class="container">
                <h2>Featured Products</h2>
                <div class="product-grid">
                    <?php
                    // Display featured products
                    $featured_products = getFeaturedProducts(4);
                    if ($featured_products && count($featured_products) > 0) {
                        foreach ($featured_products as $product) {
                            include 'includes/product-card.php';
                        }
                    } else {
                        echo '<p class="no-products">Featured products coming soon!</p>';
                    }
                    ?>
                </div>
                <div class="view-more">
                    <a href="marketplace.php" class="btn btn-outline">View All Products</a>
                </div>
            </div>
        </section>

        <section class="testimonials">
            <div class="container">
                <h2>Success Stories</h2>
                <div class="testimonial-slider">
                    <div class="testimonial">
                        <div class="testimonial-content">
                            <p>"Agriconnect has transformed my farming business. I now sell directly to consumers at better prices than I ever received from middlemen."</p>
                        </div>
                        <div class="testimonial-author">
                            <img src="assets/images/farmer1.svg" alt="Farmer">
                            <div>
                                <h4>John Mwangi</h4>
                                <p>Small-scale Farmer, Nyeri</p>
                            </div>
                        </div>
                    </div>
                    <div class="testimonial">
                        <div class="testimonial-content">
                            <p>"I love knowing exactly where my food comes from. The produce is fresher and more affordable than what I find in supermarkets."</p>
                        </div>
                        <div class="testimonial-author">
                            <img src="assets/images/consumer1.svg" alt="Consumer">
                            <div>
                                <h4>Sarah Ochieng</h4>
                                <p>Consumer, Nairobi</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include_once 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>