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

// Handle user status update
if (isset($_POST['update_status']) && isset($_POST['user_id']) && isset($_POST['status'])) {
    $userId = intval($_POST['user_id']);
    $newStatus = sanitize($_POST['status']);
    
    // Don't allow admins to deactivate themselves
    if ($userId == $_SESSION['user_id'] && $newStatus == 'inactive') {
        setFlashMessage('You cannot deactivate your own account', 'danger');
    } else {
        $query = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $newStatus, $userId);
        
        if ($stmt->execute()) {
            setFlashMessage('User status updated successfully', 'success');
        } else {
            setFlashMessage('Error updating user status', 'danger');
        }
    }
    
    redirect('users.php');
    exit();
}

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);
    
    // Don't allow admins to delete themselves
    if ($userId == $_SESSION['user_id']) {
        setFlashMessage('You cannot delete your own account', 'danger');
    } else {
        $conn->begin_transaction();
        try {
            // Delete related data first (cascading might handle some, but let's be safe)
            $conn->query("DELETE FROM messages WHERE sender_id = $userId OR receiver_id = $userId");
            $conn->query("DELETE FROM reviews WHERE user_id = $userId");
            
            if ($_SESSION['user_type'] == 'farmer') {
                $conn->query("DELETE FROM farmer_profiles WHERE user_id = $userId");
                // Products deletion should ideally handle images too, but for now:
                $conn->query("DELETE FROM products WHERE farmer_id = $userId");
            }
            
            $conn->query("DELETE FROM users WHERE id = $userId");
            $conn->commit();
            setFlashMessage('User deleted successfully', 'success');
        } catch (Exception $e) {
            $conn->rollback();
            setFlashMessage('Error deleting user: ' . $e->getMessage(), 'danger');
        }
    }
    
    redirect('users.php');
    exit();
}

// Get all users
$users = getAllUsers(50);

$page_title = 'Manage Users';
include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
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
            <h1>Manage Users</h1>
            <a href="add-user.php" class="btn btn-primary">Add New User</a>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <div class="table-responsive">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>#<?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="type-badge <?php echo $user['user_type']; ?>"><?php echo ucfirst($user['user_type']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($user['registration_date'])); ?></td>
                        <td><span class="status-badge status-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                        <td>
                            <div class="action-buttons" style="display: flex; gap: 5px;">
                                <form action="" method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <?php if ($user['status'] == 'active'): ?>
                                        <button type="submit" name="update_status" value="1" class="btn-small btn-warning">
                                            <input type="hidden" name="status" value="inactive">
                                            Deactivate
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="update_status" value="1" class="btn-small btn-success">
                                            <input type="hidden" name="status" value="active">
                                            Activate
                                        </button>
                                    <?php endif; ?>
                                </form>
                                <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user? This will remove all their data.')">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" value="1" class="btn-small btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
