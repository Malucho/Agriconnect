<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a consumer
if (!isLoggedIn() || $_SESSION['user_type'] != 'consumer') {
    setFlashMessage('Please login as a consumer to access this page', 'danger');
    redirect('../login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    
    $errors = [];
    
    if (empty($first_name) || empty($last_name)) $errors[] = "Names are required";
    if (empty($email)) $errors[] = "Email is required";
    
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $userId);
        
        if ($stmt->execute()) {
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            setFlashMessage('Profile updated successfully', 'success');
            redirect('profile.php');
            exit();
        } else {
            $errors[] = "Error updating profile";
        }
    }
}

$page_title = 'My Profile';
include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar">
                <?php if (!empty($_SESSION['profile_image'])): ?>
                    <img src="../uploads/profiles/<?php echo $_SESSION['profile_image']; ?>" alt="Profile Picture">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
            <p>Consumer Dashboard</p>
        </div>
        <nav class="dashboard-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> My Orders</a></li>
                <li class="active"><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="../marketplace.php"><i class="fas fa-leaf"></i> Shop Products</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header"><h1>My Profile</h1></div>
        <?php displayFlashMessages(); ?>
        
        <div class="form-container">
            <form action="" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="form-control">
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
