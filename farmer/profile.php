<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a farmer
if (!isLoggedIn() || $_SESSION['user_type'] != 'farmer') {
    setFlashMessage('error', 'You must be logged in as a farmer to access this page');
    redirect('../login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$unreadMessages = getUnreadMessagesCount($userId);

// Get farmer profile data
$query = "SELECT u.*, f.* FROM users u 
          JOIN farmer_profiles f ON u.id = f.user_id 
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlashMessage('error', 'Farmer profile not found');
    redirect('../logout.php');
    exit();
}

$farmer = $result->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $farm_name = sanitize($_POST['farm_name']);
    $county = sanitize($_POST['county']);
    $location = sanitize($_POST['location']);
    $farm_description = sanitize($_POST['farm_description']);
    $farming_practices = sanitize($_POST['farming_practices']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($first_name) || empty($last_name)) {
        $errors[] = "First and last name are required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($farm_name)) {
        $errors[] = "Farm name is required";
    }
    
    if (empty($county)) {
        $errors[] = "County is required";
    }
    
    if (empty($location)) {
        $errors[] = "Location is required";
    }
    
    // Check if email already exists for another user
    if ($email != $farmer['email']) {
        $query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $email, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Email already in use by another account";
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Update user table
            $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $userId);
            $stmt->execute();
            
            // Update farmer_profiles table
            $query = "UPDATE farmer_profiles SET farm_name = ?, county = ?, location = ?, farm_description = ?, farming_practices = ? WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssssi", $farm_name, $county, $location, $farm_description, $farming_practices, $userId);
            $stmt->execute();
            
            // Handle profile image upload
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['profile_image']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $newFilename = 'farmer_' . $userId . '_' . time() . '.' . $ext;
                    $uploadPath = '../uploads/profiles/' . $newFilename;
                    
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {
                        // Update profile image in database
                        $query = "UPDATE farmer_profiles SET profile_image = ? WHERE user_id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("si", $newFilename, $userId);
                        $stmt->execute();
                    }
                }
            }
            
            $conn->commit();
            
            // Update session variables
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            
            setFlashMessage('success', 'Profile updated successfully');
            redirect('profile.php');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            setFlashMessage('error', 'Error updating profile: ' . $e->getMessage());
        }
    } else {
        // Set error messages
        foreach ($errors as $error) {
            setFlashMessage('error', $error);
        }
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate inputs
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($new_password != $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    // Verify current password
    if (empty($errors)) {
        $query = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
    }
    
    // Update password if no errors
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $hashed_password, $userId);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Password updated successfully');
            redirect('profile.php');
            exit();
        } else {
            setFlashMessage('error', 'Failed to update password');
        }
    } else {
        // Set error messages
        foreach ($errors as $error) {
            setFlashMessage('error', $error);
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
                <?php if (!empty($farmer['profile_image'])): ?>
                    <img src="<?php echo '../uploads/profiles/' . htmlspecialchars($farmer['profile_image']); ?>" alt="Profile Image">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            <h3><?php echo htmlspecialchars($farmer['first_name'] . ' ' . $farmer['last_name']); ?></h3>
            <p><?php echo htmlspecialchars($farmer['farm_name'] ?? 'Farmer'); ?></p>
        </div>
        <nav class="dashboard-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-leaf"></i> My Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> Orders</a></li>
                <li class="active"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages
                    <?php if ($unreadMessages > 0): ?>
                        <span class="badge"><?php echo $unreadMessages; ?></span>
                    <?php endif; ?>
                </a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>My Profile</h1>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <div class="profile-container">
            <div class="profile-section">
                <h2>Personal Information</h2>
                <form action="" method="POST" enctype="multipart/form-data" class="profile-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($farmer['first_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($farmer['last_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($farmer['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($farmer['phone']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="profile_image">Profile Image</label>
                            <div class="image-upload-container">
                                <div class="current-image">
                                    <?php if (!empty($farmer['profile_image'])): ?>
                                        <img src="<?php echo '../uploads/profiles/' . htmlspecialchars($farmer['profile_image']); ?>" alt="Current Profile Image" id="profile-preview">
                                    <?php else: ?>
                                        <div class="no-image" id="profile-preview-placeholder">
                                            <i class="fas fa-user"></i>
                                            <p>No Image</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" id="profile_image" name="profile_image" accept="image/*">
                            </div>
                        </div>
                    </div>
                    
                    <h2>Farm Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="farm_name">Farm Name</label>
                            <input type="text" id="farm_name" name="farm_name" value="<?php echo htmlspecialchars($farmer['farm_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="county">County</label>
                            <input type="text" id="county" name="county" value="<?php echo htmlspecialchars($farmer['county']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($farmer['location']); ?>" required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="farm_description">Farm Description</label>
                            <textarea id="farm_description" name="farm_description" rows="4"><?php echo htmlspecialchars($farmer['farm_description']); ?></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="farming_practices">Farming Practices</label>
                            <textarea id="farming_practices" name="farming_practices" rows="4"><?php echo htmlspecialchars($farmer['farming_practices']); ?></textarea>
                            <small>Describe your farming methods, sustainability practices, certifications, etc.</small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
            
            <div class="profile-section">
                <h2>Change Password</h2>
                <form action="" method="POST" class="password-form">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <small>Password must be at least 8 characters long</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_password" class="btn btn-secondary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Image preview functionality
    document.addEventListener('DOMContentLoaded', function() {
        const profileImageInput = document.getElementById('profile_image');
        const profilePreview = document.getElementById('profile-preview');
        const profilePreviewPlaceholder = document.getElementById('profile-preview-placeholder');
        
        if (profileImageInput) {
            profileImageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        if (profilePreview) {
                            profilePreview.src = e.target.result;
                            profilePreview.style.display = 'block';
                        }
                        
                        if (profilePreviewPlaceholder) {
                            profilePreviewPlaceholder.style.display = 'none';
                        }
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>