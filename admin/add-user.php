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

$adminName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $userType = sanitize($_POST['user_type']);
    $status = sanitize($_POST['status']);

    if (empty($firstName) || empty($lastName)) $errors[] = "Full name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $errors[] = "Email already registered";

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO users (user_type, first_name, last_name, email, phone, password, status, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
            $stmt->bind_param("sssssss", $userType, $firstName, $lastName, $email, $phone, $hashedPassword, $status);
            $stmt->execute();
            $userId = $conn->insert_id;

            // If farmer, create an empty profile
            if ($userType == 'farmer') {
                $stmt = $conn->prepare("INSERT INTO farmer_profiles (user_id, farm_name) VALUES (?, ?)");
                $farmName = $firstName . "'s Farm";
                $stmt->bind_param("is", $userId, $farmName);
                $stmt->execute();
            }

            $conn->commit();
            setFlashMessage('User created successfully', 'success');
            redirect('users.php');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error creating user: " . $e->getMessage();
        }
    }
}

$page_title = 'Add New User';
include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar"><i class="fas fa-user-shield"></i></div>
            <h3><?php echo htmlspecialchars($adminName); ?></h3>
            <p>Administrator</p>
        </div>
        <nav class="dashboard-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="active"><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="products.php"><i class="fas fa-leaf"></i> Manage Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> Manage Orders</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>Add New User</h1>
            <a href="users.php" class="btn btn-outline">Back to Users</a>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        
        <div class="form-container" style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); max-width: 600px;">
            <form action="" method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Email Address *</label>
                    <input type="email" name="email" required class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Password *</label>
                    <input type="password" name="password" required class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div class="form-group">
                        <label>User Type</label>
                        <select name="user_type" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="consumer">Consumer</option>
                            <option value="farmer">Farmer</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Account Status</label>
                        <select name="status" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="add_user" class="btn btn-primary" style="width: 100%; padding: 12px;">Create User</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
