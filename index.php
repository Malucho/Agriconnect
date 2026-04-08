<?php
session_start();
include_once 'includes/config.php';
include_once 'includes/functions.php';

$page_title = 'Home';
include_once 'includes/head.php';
include_once 'includes/header.php';
?>
    
    <main>
        <section class="hero-section">
            <div class="container">
                <div class="hero-content">
                    <h1>Connecting Farmers & Consumers for a Fresher Future</h1>
                    <p>Agriconnect bridges the gap between local farmers and your table, ensuring fresh produce and fair prices.</p>
                    <div class="hero-buttons">
                        <a href="marketplace.php" class="btn btn-primary btn-lg">Explore Marketplace</a>
                        <a href="about.php" class="btn btn-outline-light btn-lg">Learn More</a>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="Images/autonomous-agriculture.webp" alt="Farmers and fresh produce">
                </div>
            </div>
        </section>

        <section class="features-section bg-light">
            <div class="container">
                <h2 class="section-title">Why Choose Agriconnect?</h2>
                <div class="features-grid">
                    <div class="feature-item">
                        <i class="fas fa-hand-holding-usd feature-icon"></i>
                        <h3>Fair Pricing</h3>
                        <p>Eliminate middlemen and ensure farmers receive fair compensation for their hard work.</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-leaf feature-icon"></i>
                        <h3>Fresh & Local</h3>
                        <p>Access the freshest, high-quality produce directly from local Kenyan farms.</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-sync-alt feature-icon"></i>
                        <h3>Transparent Trade</h3>
                        <p>Know exactly where your food comes from and support sustainable farming practices.</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users feature-icon"></i>
                        <h3>Community Support</h3>
                        <p>Strengthen local economies and build direct relationships with your farmers.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="how-it-works-section">
            <div class="container">
                <h2 class="section-title">How It Works</h2>
                <div class="steps-grid">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <h3>Farmers List Produce</h3>
                        <p>Farmers easily upload their available produce, set prices, and manage their inventory.</p>
                    </div>
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <h3>Consumers Browse & Order</h3>
                        <p>Consumers browse a wide variety of fresh listings, view farmer profiles, and place orders securely.</p>
                    </div>
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <h3>Direct Connection</h3>
                        <p>Farmers and consumers coordinate delivery or convenient pickup options directly.</p>
                    </div>
                    <div class="step-item">
                        <div class="step-number">4</div>
                        <h3>Sustainable Growth</h3>
                        <p>Both parties benefit from fair prices, fresh food, and a thriving local agricultural ecosystem.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="featured-products-section bg-light">
            <div class="container">
                <h2 class="section-title">Our Fresh Picks</h2>
                <div class="product-grid">
                    <?php
                    $featured_products = getFeaturedProducts(8); // Get up to 8 featured products
                    if ($featured_products && count($featured_products) > 0) {
                        foreach ($featured_products as $product) {
                            include 'includes/product-card.php';
                        }
                    } else {
                        echo '<p class="no-products">No featured products available at the moment. Check back soon!</p>';
                    }
                    ?>
                </div>
                <div class="text-center mt-5">
                    <a href="marketplace.php" class="btn btn-primary btn-lg">View All Products <i class="fas fa-arrow-right ml-2"></i></a>
                </div>
            </div>
        </section>

        <section class="testimonials-section">
            <div class="container">
                <h2 class="section-title">What Our Community Says</h2>
                <div class="testimonials-slider">
                    <div class="testimonial-card">
                        <p class="testimonial-text">"Agriconnect has revolutionized my farming business. I now sell directly to consumers at much better prices than I ever received from middlemen. It's a game-changer!"</p>
                        <div class="testimonial-author">
                            <img src="Images/istockphoto-518782849-612x612.jpg" alt="John Mwangi" class="author-avatar">
                            <div>
                                <p class="author-name">John Mwangi</p>
                                <p class="author-title">Small-scale Farmer, Nyeri</p>
                            </div>
                        </div>
                    </div>
                    <div class="testimonial-card">
                        <p class="testimonial-text">"I absolutely love Agriconnect! The produce is incredibly fresh, and I feel great knowing I'm supporting local farmers directly. It's convenient and affordable."</p>
                        <div class="testimonial-author">
                            <img src="Images/Green and White Organic Agriculture Logo.png" alt="Sarah Ochieng" class="author-avatar">
                            <div>
                                <p class="author-name">Sarah Ochieng</p>
                                <p class="author-title">Consumer, Nairobi</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include_once 'includes/footer.php'; ?>