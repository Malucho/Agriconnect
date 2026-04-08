<?php
session_start();
include_once 'includes/config.php';
include_once 'includes/functions.php';

// Check if user is already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

// Set default user type
$user_type = isset($_GET['type']) && in_array($_GET['type'], ['farmer', 'consumer']) ? $_GET['type'] : 'consumer';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = sanitize($_POST['user_type']);
    $county = sanitize($_POST['county']);
    $location = sanitize($_POST['location']);
    $terms = isset($_POST['terms']) ? true : false;
    
    // Validation
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($county)) {
        $errors[] = "County is required";
    }
    
    if (empty($location)) {
        $errors[] = "Location is required";
    }
    
    if (!$terms) {
        $errors[] = "You must agree to the terms and conditions";
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Email already exists. Please use a different email or login.";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate verification token
        $verification_token = generateToken();
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (user_type, first_name, last_name, email, phone, password, county, location, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $user_type, $first_name, $last_name, $email, $phone, $hashed_password, $county, $location, $verification_token);
            $stmt->execute();
            
            $user_id = $conn->insert_id;
            
            // If user is a farmer, create farmer profile
            if ($user_type === 'farmer') {
                $farm_name = $first_name . "'s Farm"; // Default farm name
                
                $stmt = $conn->prepare("INSERT INTO farmer_profiles (user_id, farm_name, farm_location) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $user_id, $farm_name, $location);
                $stmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            setFlashMessage("Registration successful! Please check your email to verify your account.", "success");
            
            // Send verification email
            $verification_link = SITE_URL . "/verify.php?token=" . $verification_token;
            $subject = "Verify your Agriconnect Account";
            $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1;'>
                    <h2 style='color: #4CAF50;'>Welcome to Agriconnect, $first_name!</h2>
                    <p>Thank you for joining our platform. Please click the button below to verify your account and get started.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$verification_link' style='background-color: #4CAF50; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Verify Account</a>
                    </div>
                    <p>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p>$verification_link</p>
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #777;'>If you didn't create an account with us, please ignore this email.</p>
                </div>
            ";
            
            sendEmail($email, $subject, $body);
            
            // Redirect to login page
            redirect('login.php');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}
$page_title = 'Register';
include_once 'includes/head.php';
include_once 'includes/header.php';
?>
    
    <main>
        <section class="form-section auth-section">
            <div class="container">
                <div class="auth-container">
                    <div class="auth-header">
                        <img src="Images/Green and White Organic Agriculture Logo.png" alt="Agriconnect Logo" class="auth-logo">
                        <h2>Create Your Account</h2>
                        <p>Join Agriconnect as a <?php echo ucfirst($user_type); ?></p>
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
                    
                    <form action="register.php" method="post" class="auth-form">
                        <div class="form-group">
                            <label>Register as:</label>
                            <div class="user-type-toggle">
                                <div class="toggle-option <?php echo $user_type === 'farmer' ? 'active' : ''; ?>">
                                    <input type="radio" name="user_type" id="farmer" value="farmer" <?php echo $user_type === 'farmer' ? 'checked' : ''; ?>>
                                    <label for="farmer"><i class="fas fa-tractor"></i> Farmer</label>
                                </div>
                                <div class="toggle-option <?php echo $user_type === 'consumer' ? 'active' : ''; ?>">
                                    <input type="radio" name="user_type" id="consumer" value="consumer" <?php echo $user_type === 'consumer' ? 'checked' : ''; ?>>
                                    <label for="consumer"><i class="fas fa-shopping-basket"></i> Consumer</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo isset($first_name) ? htmlspecialchars($first_name) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo isset($last_name) ? htmlspecialchars($last_name) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>" placeholder="e.g. +254700000000" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="form-text">Password must be at least 8 characters long</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="county">County</label>
                                <select class="form-control" id="county" name="county" required>
                                    <option value="">Select County</option>
                                    <?php foreach (getKenyanCounties() as $county_option): ?>
                                        <option value="<?php echo htmlspecialchars($county_option); ?>" <?php echo isset($_POST['county']) && $_POST['county'] === $county_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($county_option); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="location">Specific Location</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo isset($location) ? htmlspecialchars($location) : ''; ?>" placeholder="e.g. Westlands, Nairobi" required>
                            </div>
                        </div>
                        
                        <div class="form-check" style="margin-bottom: 20px;">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a></label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Register</button>
                    </form>
                    
                    <div class="auth-footer">
                        <p>Already have an account? <a href="login.php">Login</a></p>
                        <p class="admin-links">
                            <a href="admin/login.php">Admin Login</a> | 
                            <a href="admin/register.php">Admin Register</a>
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <script>
        // Toggle user type selection
        document.querySelectorAll('.user-type-toggle .toggle-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.user-type-toggle .toggle-option').forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('input[type="radio"]').checked = true;
                // Update the user type display in the header
                const userTypeDisplay = document.querySelector('.auth-header p');
                if (userTypeDisplay) {
                    const selectedType = this.querySelector('input[type="radio"]').value;
                    userTypeDisplay.innerHTML = 'Join Agriconnect as a ' + selectedType.charAt(0).toUpperCase() + selectedType.slice(1);
                }
            });
        });
    </script>
    
    <?php include_once 'includes/footer.php'; ?>