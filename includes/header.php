<header>
    <div class="container header-container">
        <div class="logo">
            <a href="<?php echo SITE_URL; ?>/index.php">
                <img src="<?php echo SITE_URL; ?>/Images/Green and White Organic Agriculture Logo.png" alt="Agriconnect Logo">
                <h1>Agriconnect</h1>
            </a>
        </div>
        
        <div class="mobile-menu-btn">
            <i class="fas fa-bars"></i>
        </div>
        
        <nav>
            <ul>
                <li><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                <li><a href="<?php echo SITE_URL; ?>/marketplace.php">Marketplace</a></li>
                <li><a href="<?php echo SITE_URL; ?>/about.php">About Us</a></li>
                <li><a href="<?php echo SITE_URL; ?>/contact.php">Contact</a></li>
                <?php if (isLoggedIn() && isFarmer()): ?>
                    <li><a href="<?php echo SITE_URL; ?>/farmer/dashboard.php">Farmer Dashboard</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="auth-buttons">
            <?php if (isLoggedIn()): ?>
                <div class="dropdown">
                    <button class="btn btn-outline dropdown-toggle">
                        <?php echo $_SESSION['first_name']; ?>
                        <?php if (getUnreadMessagesCount($_SESSION['user_id']) > 0): ?>
                            <span class="badge"><?php echo getUnreadMessagesCount($_SESSION['user_id']); ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu">
                        <?php if (isFarmer()): ?>
                            <a href="<?php echo SITE_URL; ?>/farmer/dashboard.php">Dashboard</a>
                        <?php elseif (isConsumer()): ?>
                            <a href="<?php echo SITE_URL; ?>/consumer/dashboard.php">Dashboard</a>
                        <?php elseif (isAdmin()): ?>
                            <a href="<?php echo SITE_URL; ?>/admin/dashboard.php">Admin Panel</a>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>/profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a href="<?php echo SITE_URL; ?>/messages.php">
                            Messages
                            <?php if (getUnreadMessagesCount($_SESSION['user_id']) > 0): ?>
                                <span class="badge"><?php echo getUnreadMessagesCount($_SESSION['user_id']); ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo SITE_URL; ?>/logout.php">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-outline">Login</a>
                <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-primary">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php echo displayFlashMessage(); ?>
