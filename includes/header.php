<header>
    <div class="container header-container">
        <div class="logo">
            <a href="index.php">
                <img src="Images/Green and White Organic Agriculture Logo.png" alt="Agriconnect Logo">
                <h1>Agriconnect</h1>
            </a>
        </div>
        
        <div class="mobile-menu-btn">
            <i class="fas fa-bars"></i>
        </div>
        
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="marketplace.php">Marketplace</a></li>
                <li><a href="about.php">About Us</a></li>
                <li><a href="contact.php">Contact</a></li>
                <?php if (isLoggedIn() && isFarmer()): ?>
                    <li><a href="farmer/dashboard.php">Farmer Dashboard</a></li>
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
                            <a href="farmer/dashboard.php">Dashboard</a>
                        <?php elseif (isConsumer()): ?>
                            <a href="consumer/dashboard.php">Dashboard</a>
                        <?php elseif (isAdmin()): ?>
                            <a href="admin/dashboard.php">Admin Panel</a>
                        <?php endif; ?>
                        <a href="profile.php">My Profile</a>
                        <a href="messages.php">
                            Messages
                            <?php if (getUnreadMessagesCount($_SESSION['user_id']) > 0): ?>
                                <span class="badge"><?php echo getUnreadMessagesCount($_SESSION['user_id']); ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline">Login</a>
                <a href="register.php" class="btn btn-primary">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php echo displayFlashMessage(); ?>
