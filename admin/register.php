<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is already logged in
if (isLoggedIn() && isAdmin()) {
    redirect('dashboard.php');
}

$errors = [];
$success = '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $security_code = sanitize($_POST['security_code']);
    
    // Admin Security Code - Change this for production
    $ADMIN_SECRET = "AGRI-ADMIN-2024";
    
    // Validation
    if (empty($first_name) || empty($last_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if ($security_code !== $ADMIN_SECRET) {
        $errors[] = "Invalid Admin Security Code. You are not authorized to create an admin account.";
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already registered. Please login.";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $user_type = 'admin';
        $status = 'active'; // Admins are active by default if they have the secret code
        
        $stmt = $conn->prepare("INSERT INTO users (user_type, first_name, last_name, email, phone, password, status, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssssss", $user_type, $first_name, $last_name, $email, $phone, $hashed_password, $status);
        
        if ($stmt->execute()) {
            setFlashMessage('Admin account created successfully! You can now login.', 'success');
            redirect('login.php');
            exit();
        } else {
            $errors[] = "Error creating account: " . $conn->error;
        }
    }
}

$page_title = 'Admin Registration';
include_once '../includes/head.php';
?>

<div class="admin-login-container" style="display: flex; justify-content: center; align-items: center; min-height: 90vh; background: #f4f7f6; padding: 40px 0;">
    <div class="form-container" style="background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 500px;">
        <div class="form-title" style="text-align: center; margin-bottom: 30px;">
            <img src="<?php echo SITE_URL; ?>/Images/Green and White Organic Agriculture Logo.png" alt="Logo" style="width: 80px; margin-bottom: 15px;">
            <h2 style="color: #4CAF50;">Admin Registration</h2>
            <p style="color: #777;">Create a new administrator account</p>
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
        
        <form action="" method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">First Name</label>
                    <input type="text" name="first_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Last Name</label>
                    <input type="text" name="last_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Email Address</label>
                <input type="email" name="email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Phone Number</label>
                <input type="text" name="phone" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Password</label>
                    <input type="password" name="password" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Confirm Password</label>
                    <input type="password" name="confirm_password" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 25px; padding: 15px; background: #fffde7; border: 1px solid #fff59d; border-radius: 4px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f57f17;">Admin Security Code</label>
                <input type="password" name="security_code" required placeholder="Enter secret code to register" style="width: 100%; padding: 10px; border: 1px solid #fbc02d; border-radius: 4px;">
                <small style="color: #777;">Only authorized staff can register as admins.</small>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; background: #4CAF50; color: #fff; border: none; border-radius: 4px; cursor: pointer;">
                Create Admin Account
            </button>
            
            <div style="text-align: center; margin-top: 20px;">
                <p style="color: #777; font-size: 0.9rem;">Already have an admin account? <a href="login.php" style="color: #4CAF50; font-weight: 600; text-decoration: none;">Login Here</a></p>
                <a href="<?php echo SITE_URL; ?>/index.php" style="display: block; margin-top: 15px; color: #777; text-decoration: none; font-size: 0.85rem;">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </form>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
