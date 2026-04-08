<?php
session_start();
include_once 'includes/config.php';
include_once 'includes/functions.php';

// Check if user is already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validation
    $errors = [];
    
    if (empty($email)) {
        $errors[] = "Email is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    // If no errors, proceed with login
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if account is active
                if ($user['status'] === 'active') {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['profile_image'] = $user['profile_image'];
                    
                    // Update last login time
                    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $user['id']);
                    $stmt->execute();
                    
                    // Redirect based on user type
                    if ($user['user_type'] === 'farmer') {
                        redirect('farmer/dashboard.php');
                    } elseif ($user['user_type'] === 'consumer') {
                        redirect('consumer/dashboard.php');
                    } elseif ($user['user_type'] === 'admin') {
                        redirect('admin/dashboard.php');
                    } else {
                        redirect('index.php');
                    }
                } else {
                    $errors[] = "Your account is not active. Please contact support.";
                }
            } else {
                $errors[] = "Invalid email or password";
            }
        } else {
            $errors[] = "Invalid email or password";
        }
    }
}
$page_title = 'Login';
include_once 'includes/head.php';
include_once 'includes/header.php';
?>
    
    <main>
        <section class="form-section auth-section">
            <div class="container">
                <div class="auth-container">
                    <div class="auth-header">
                        <img src="Images/Green and White Organic Agriculture Logo.png" alt="Agriconnect Logo" class="auth-logo">
                        <h2>Welcome Back!</h2>
                        <p>Login to your Agriconnect account</p>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="login.php" method="post" class="auth-form">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="form-actions-auth">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <a href="forgot-password.php" class="forgot-password-link">Forgot Password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Login</button>
                    </form>
                    
                    <div class="auth-footer">
                        <p>Don't have an account?</p>
                        <div class="register-options">
                            <a href="register.php?type=farmer" class="btn btn-outline-secondary">Register as Farmer</a>
                            <a href="register.php?type=consumer" class="btn btn-outline-primary">Register as Consumer</a>
                        </div>
                        <p class="admin-links">
                            <a href="admin/login.php">Admin Login</a> | 
                            <a href="admin/register.php">Admin Register</a>
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <?php include_once 'includes/footer.php'; ?>