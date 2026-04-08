<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is already logged in as admin
if (isLoggedIn() && isAdmin()) {
    redirect('dashboard.php');
}

$errors = [];

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email)) {
        $errors[] = "Email is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND user_type = 'admin'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'active') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    
                    // Update last login
                    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $user['id']);
                    $stmt->execute();
                    
                    redirect('dashboard.php');
                } else {
                    $errors[] = "Your admin account is inactive. Please contact system owner.";
                }
            } else {
                $errors[] = "Invalid email or password";
            }
        } else {
            $errors[] = "Invalid email or password";
        }
    }
}

$page_title = 'Admin Login';
// We use root head and header, but we might want a simpler one for admin login
include_once '../includes/head.php';
?>

<div class="admin-login-container" style="display: flex; justify-content: center; align-items: center; min-height: 80vh; background: #f4f7f6;">
    <div class="form-container" style="background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px;">
        <div class="form-title" style="text-align: center; margin-bottom: 30px;">
            <img src="<?php echo SITE_URL; ?>/Images/Green and White Organic Agriculture Logo.png" alt="Logo" style="width: 80px; margin-bottom: 15px;">
            <h2 style="color: #4CAF50;">Admin Portal</h2>
            <p style="color: #777;">Please login to access management tools</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form action="login.php" method="post">
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="email" style="display: block; margin-bottom: 8px; font-weight: 600;">Admin Email</label>
                <input type="email" id="email" name="email" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div class="form-group" style="margin-bottom: 30px;">
                <label for="password" style="display: block; margin-bottom: 8px; font-weight: 600;">Password</label>
                <input type="password" id="password" name="password" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; background: #4CAF50; color: #fff; border: none; border-radius: 4px; cursor: pointer;">
                Login to Dashboard
            </button>
            
            <div style="text-align: center; margin-top: 20px;">
                <p style="color: #777; font-size: 0.9rem; margin-bottom: 15px;">New admin? <a href="register.php" style="color: #4CAF50; font-weight: 600; text-decoration: none;">Register Here</a></p>
                <a href="<?php echo SITE_URL; ?>/index.php" style="color: #777; text-decoration: none; font-size: 0.9rem;">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </form>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
