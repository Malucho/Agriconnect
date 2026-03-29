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
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock_quantity']);
    $categoryId = intval($_POST['category_id']);
    $unit = sanitizeInput($_POST['unit']);
    $origin = sanitizeInput($_POST['origin']);
    $isOrganic = isset($_POST['is_organic']) ? 1 : 0;
    $harvestDate = sanitizeInput($_POST['harvest_date']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
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
    
    if ($stock < 0) {
        $errors[] = "Stock quantity cannot be negative";
    }
    
    if ($categoryId <= 0) {
        $errors[] = "Please select a valid category";
    }
    
    // If no errors, insert the product
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Insert product
            $query = "INSERT INTO products (name, description, price, stock_quantity, category_id, user_id, unit, origin, is_organic, harvest_date, is_active, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssdiisssiis", $name, $description, $price, $stock, $categoryId, $farmerId, $unit, $origin, $isOrganic, $harvestDate, $isActive);
            $stmt->execute();
            
            $productId = $conn->insert_id;
            
            // Handle image uploads
            if (!empty($_FILES['images']['name'][0])) {
                $uploadDir = '../uploads/products/';
                
                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] == 0) {
                        $fileName = time() . '_' . $_FILES['images']['name'][$key];
                        $filePath = $uploadDir . $fileName;
                        
                        // Move uploaded file
                        if (move_uploaded_file($tmp_name, $filePath)) {
                            // Insert image record
                            $imageUrl = 'uploads/products/' . $fileName;
                            $query = "INSERT INTO product_images (product_id, image_url, created_at) VALUES (?, ?, NOW())";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("is", $productId, $imageUrl);
                            $stmt->execute();
                        } else {
                            throw new Exception("Failed to upload image");
                        }
                    }
                }
            }
            
            $conn->commit();
            setFlashMessage('success', 'Product added successfully');
            redirect('products.php');
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <h3><?php echo htmlspecialchars($_SESSION['username']); ?></h3>
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
                        <label for="stock_quantity">Stock Quantity <span class="required">*</span></label>
                        <input type="number" id="stock_quantity" name="stock_quantity" min="0" required value="<?php echo isset($_POST['stock_quantity']) ? htmlspecialchars($_POST['stock_quantity']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Product Details</h2>
                    
                    <div class="form-group">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" rows="5" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <p class="form-hint">Describe your product in detail. Include information about quality, taste, and usage suggestions.</p>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="origin">Origin Location</label>
                            <input type="text" id="origin" name="origin" value="<?php echo isset($_POST['origin']) ? htmlspecialchars($_POST['origin']) : ''; ?>">
                            <p class="form-hint">Where was this product grown? (e.g., Nakuru, Kiambu)</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="harvest_date">Harvest Date</label>
                            <input type="date" id="harvest_date" name="harvest_date" value="<?php echo isset($_POST['harvest_date']) ? htmlspecialchars($_POST['harvest_date']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="is_organic" name="is_organic" <?php echo (isset($_POST['is_organic'])) ? 'checked' : ''; ?>>
                        <label for="is_organic">This product is organically grown</label>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Product Images</h2>
                    
                    <div class="form-group">
                        <label for="images">Upload Images (Max 5)</label>
                        <input type="file" id="images" name="images[]" accept="image/*" multiple>
                        <p class="form-hint">Upload clear, high-quality images of your product. First image will be the main product image.</p>
                    </div>
                    
                    <div id="image-preview" class="image-preview-container"></div>
                </div>
                
                <div class="form-section">
                    <h2>Product Status</h2>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" checked>
                        <label for="is_active">Make this product visible in the marketplace</label>
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
    document.getElementById('images').addEventListener('change', function(event) {
        const preview = document.getElementById('image-preview');
        preview.innerHTML = '';
        
        if (this.files.length > 5) {
            alert('You can only upload a maximum of 5 images');
            this.value = '';
            return;
        }
        
        for (let i = 0; i < this.files.length; i++) {
            const file = this.files[i];
            if (!file.type.match('image.*')) {
                continue;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const imgContainer = document.createElement('div');
                imgContainer.className = 'image-preview';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                
                imgContainer.appendChild(img);
                preview.appendChild(imgContainer);
            }
            
            reader.readAsDataURL(file);
        }
    });
</script>

<?php include '../includes/footer.php'; ?>