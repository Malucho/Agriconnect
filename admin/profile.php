<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['user_type'] != 'admin') {
    setFlashMessage('You must be logged in as an admin to access this page', 'danger');
    redirect('login.php');
    exit();
}

$adminId = $_SESSION['user_id'];

// Fetch admin data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$errors = [];

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    
    if (empty($first_name) || empty($last_name)) $errors[] = "Names are required";
    if (empty($email)) $errors[] = "Email is required";
    
    // Check email uniqueness
    if ($email !== $admin['email']) {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $adminId);
        $check->execute();
        if ($check->get_result()->num_rows > 0) $errors[] = "Email already in use";
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $adminId);
        if ($stmt->execute()) {
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            setFlashMessage('Profile updated successfully', 'success');
            redirect('profile.php');
            exit();
        } else {
            $errors[] = "Failed to update profile";
        }
    }
}

// Update password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || strlen($new_password) < 8) $errors[] = "New password must be at least 8 characters";
    if ($new_password !== $confirm_password) $errors[] = "New passwords do not match";
    if (!password_verify($current_password, $admin['password'])) $errors[] = "Current password is incorrect";
    
    if (empty($errors)) {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $adminId);
        if ($stmt->execute()) {
            setFlashMessage('Password updated successfully', 'success');
            redirect('profile.php');
            exit();
        } else {
            $errors[] = "Failed to update password";
        }
    }
}

$page_title = 'Admin Profile';
include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar"><i class="fas fa-user-shield"></i></div>
            <h3><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h3>
            <p>Administrator</p>
        </div>
        <nav class="dashboard-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="products.php"><i class="fas fa-leaf"></i> Manage Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> Manage Orders</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
                <li class="active"><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header"><h1>My Profile</h1></div>
        <?php displayFlashMessages(); ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul><?php foreach ($errors as $e): ?><li><?php echo $e; ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        
        <div class="form-container" style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); max-width: 700px;">
            <form action="" method="POST" style="margin-bottom: 30px;">
                <h2>Personal Information</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($admin['first_name']); ?>" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($admin['last_name']); ?>" required class="form-control">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 30px;">
                    <label>Phone Number</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($admin['phone']); ?>" class="form-control">
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary" style="width: 100%; padding: 12px;">Update Profile</button>
            </form>
            
            <form action="" method="POST">
                <h2>Change Password</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required class="form-control">
                    </div>
                    <div></div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required class="form-control">
                    </div>
                </div>
                <button type="submit" name="update_password" class="btn btn-outline" style="width: 100%; padding: 12px;">Change Password</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

