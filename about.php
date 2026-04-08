<?php
session_start();
include_once 'includes/config.php';
include_once 'includes/functions.php';
$page_title = 'About Us';
include_once 'includes/head.php';
include_once 'includes/header.php';
?>
    
    <main>
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>About Agriconnect</h1>
                    <p>Connecting Kenyan farmers and consumers through a trusted digital marketplace.</p>
                </div>
            </div>
        </section>

        <section class="about-content">
            <div class="container">
                <h2>Our Story</h2>
                <p>
                    Agriconnect bridges the gap between smallholder farmers and consumers by making fresh, affordable produce accessible.
                    We empower farmers with fair market access while giving consumers transparency and quality.
                </p>

                <div class="image-grid">
                    <div class="grid-item">
                        <img src="Images/istockphoto-518782849-612x612.jpg" alt="Kenyan farmers and consumers">
                    </div>
                    <div class="grid-item">
                        <img src="Images/autonomous-agriculture.webp" alt="Autonomous agriculture innovations">
                    </div>
                </div>

                <h2 style="margin-top:2rem;">Mission & Vision</h2>
                <p>
                    Our mission is to strengthen food systems by creating direct, efficient supply chains.
                    Our vision is a connected Kenya where every farmer thrives and every consumer has access to fresh produce.
                </p>

                <h2 style="margin-top:2rem;">Values</h2>
                <ul>
                    <li>Transparency and trust</li>
                    <li>Fair pricing and inclusivity</li>
                    <li>Sustainability and innovation</li>
                </ul>

                <h2 style="margin-top:2rem;">Our Team</h2>
                <p>
                    We are a passionate team of technologists, farmers, and community advocates working to transform agriculture in Kenya.
                </p>
            </div>
        </section>
    </main>

    <?php include_once 'includes/footer.php'; ?>