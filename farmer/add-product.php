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

$farmerId = $_SESSION['user_id'];
$errors = [];

// Get all categories
$categories = [];
$query = "SELECT id, name FROM product_categories ORDER BY name";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate inputs
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity_available']);
    $categoryId = intval($_POST['category_id']);
    $unit = sanitize($_POST['unit']);
    $status = sanitize($_POST['status']);
    
    // Validation
    if (empty($name)) {
        $errors[] = "Product name is required";
    }
    
    if (empty($description)) {
        $errors[] = "Product description is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than zero";
    }
    
    if ($quantity < 0) {
        $errors[] = "Quantity cannot be negative";
    }
    
    if ($categoryId <= 0) {
        $errors[] = "Please select a valid category";
    }
    
    // If no errors, insert the product
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            $imageFilename = '';
            
            // Handle single image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $uploadDir = '../uploads/products/';
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['image']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $imageFilename = 'prod_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $uploadPath = $uploadDir . $imageFilename;
                    
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                        throw new Exception("Failed to upload image");
                    }
                }
            }

            // Insert product
            $query = "INSERT INTO products (name, description, price, unit, quantity_available, category_id, farmer_id, image, status, date_added) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssdsiiiss", $name, $description, $price, $unit, $quantity, $categoryId, $farmerId, $imageFilename, $status);
            $stmt->execute();
            
            $conn->commit();
            setFlashMessage('success', 'Product added successfully');
            redirect('products.php');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error adding product: " . $e->getMessage();
        }
    }
}

$page_title = 'Add New Product';
include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <h3><?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?></h3>
            <p>Farmer</p>
        </div>
        <nav class="dashboard-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="active"><a href="products.php"><i class="fas fa-leaf"></i> My Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> Orders</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>Add New Product</h1>
            <a href="products.php" class="btn-text"><i class="fas fa-arrow-left"></i> Back to Products</a>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form action="" method="POST" enctype="multipart/form-data" class="product-form">
                <div class="form-section">
                    <h2>Basic Information</h2>
                    
                    <div class="form-group">
                        <label for="name">Product Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category <span class="required">*</span></label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Price (KSh) <span class="required">*</span></label>
                            <input type="number" id="price" name="price" step="0.01" min="0" required value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="unit">Unit <span class="required">*</span></label>
                            <select id="unit" name="unit" required>
                                <option value="kg" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'kg') ? 'selected' : ''; ?>>Kilogram (kg)</option>
                                <option value="g" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'g') ? 'selected' : ''; ?>>Gram (g)</option>
                                <option value="piece" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'piece') ? 'selected' : ''; ?>>Piece</option>
                                <option value="bunch" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'bunch') ? 'selected' : ''; ?>>Bunch</option>
                                <option value="dozen" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'dozen') ? 'selected' : ''; ?>>Dozen</option>
                                <option value="liter" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'liter') ? 'selected' : ''; ?>>Liter</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity_available">Quantity Available <span class="required">*</span></label>
                        <input type="number" id="quantity_available" name="quantity_available" min="0" required value="<?php echo isset($_POST['quantity_available']) ? htmlspecialchars($_POST['quantity_available']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Product Details</h2>
                    
                    <div class="form-group">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" rows="5" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <p class="form-hint">Describe your product in detail. Include information about quality, taste, and usage suggestions.</p>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Product Image</h2>
                    
                    <div class="form-group">
                        <label for="image">Upload Product Image</label>
                        <input type="file" id="image" name="image" accept="image/*">
                        <p class="form-hint">Upload a clear, high-quality image of your product.</p>
                    </div>
                    
                    <div id="image-preview" class="image-preview-container"></div>
                </div>
                
                <div class="form-section">
                    <h2>Product Status</h2>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="available" <?php echo (isset($_POST['status']) && $_POST['status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                            <option value="hidden" <?php echo (isset($_POST['status']) && $_POST['status'] == 'hidden') ? 'selected' : ''; ?>>Hidden</option>
                            <option value="sold_out" <?php echo (isset($_POST['status']) && $_POST['status'] == 'sold_out') ? 'selected' : ''; ?>>Sold Out</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Product</button>
                    <a href="products.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Image preview functionality
    document.getElementById('image').addEventListener('change', function(event) {
        const preview = document.getElementById('image-preview');
        preview.innerHTML = '';
        
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.maxWidth = '200px';
                img.style.marginTop = '10px';
                img.style.borderRadius = '4px';
                preview.appendChild(img);
            }
            reader.readAsDataURL(file);
        }
    });
</script>

<?php include '../includes/footer.php'; ?>