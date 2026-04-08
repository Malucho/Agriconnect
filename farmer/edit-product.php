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
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

// Get product details
$query = "SELECT * FROM products WHERE id = ? AND farmer_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $productId, $farmerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlashMessage('error', 'Product not found');
    redirect('products.php');
    exit();
}

$product = $result->fetch_assoc();

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
    
    // If no errors, update the product
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            $imageFilename = $product['image'];
            
            // Handle single image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $uploadDir = '../uploads/products/';
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['image']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $imageFilename = 'prod_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $uploadPath = $uploadDir . $imageFilename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                        // Delete old image if it exists
                        if (!empty($product['image']) && file_exists($uploadDir . $product['image'])) {
                            unlink($uploadDir . $product['image']);
                        }
                    } else {
                        throw new Exception("Failed to upload image");
                    }
                }
            }

            // Update product
            $query = "UPDATE products SET name = ?, description = ?, price = ?, unit = ?, quantity_available = ?, category_id = ?, image = ?, status = ? WHERE id = ? AND farmer_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssdsiiisii", $name, $description, $price, $unit, $quantity, $categoryId, $imageFilename, $status, $productId, $farmerId);
            $stmt->execute();
            
            $conn->commit();
            setFlashMessage('success', 'Product updated successfully');
            redirect('products.php');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error updating product: " . $e->getMessage();
        }
    }
}

$page_title = 'Edit Product';
include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
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
            <h1>Edit Product</h1>
            <a href="products.php" class="btn btn-outline">Back to Products</a>
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
        
        <div class="form-container">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-section">
                    <h2>Basic Information</h2>
                    
                    <div class="form-group">
                        <label for="name">Product Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category <span class="required">*</span></label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo ($product['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Price (KSh) <span class="required">*</span></label>
                            <input type="number" id="price" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars($product['price']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="unit">Unit <span class="required">*</span></label>
                            <select id="unit" name="unit" required>
                                <option value="kg" <?php echo ($product['unit'] == 'kg') ? 'selected' : ''; ?>>Kilogram (kg)</option>
                                <option value="g" <?php echo ($product['unit'] == 'g') ? 'selected' : ''; ?>>Gram (g)</option>
                                <option value="piece" <?php echo ($product['unit'] == 'piece') ? 'selected' : ''; ?>>Piece</option>
                                <option value="bunch" <?php echo ($product['unit'] == 'bunch') ? 'selected' : ''; ?>>Bunch</option>
                                <option value="dozen" <?php echo ($product['unit'] == 'dozen') ? 'selected' : ''; ?>>Dozen</option>
                                <option value="liter" <?php echo ($product['unit'] == 'liter') ? 'selected' : ''; ?>>Liter</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity_available">Quantity Available <span class="required">*</span></label>
                        <input type="number" id="quantity_available" name="quantity_available" min="0" required value="<?php echo htmlspecialchars($product['quantity_available']); ?>">
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Product Details</h2>
                    
                    <div class="form-group">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" rows="5" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Product Image</h2>
                    
                    <div class="form-group">
                        <label for="image">Change Product Image</label>
                        <?php if ($product['image']): ?>
                            <div class="current-image" style="margin-bottom: 10px;">
                                <img src="../uploads/products/<?php echo $product['image']; ?>" alt="Current Image" style="max-width: 200px; border-radius: 4px;">
                                <p><small>Current Image</small></p>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="image" name="image" accept="image/*">
                    </div>
                    
                    <div id="image-preview" class="image-preview-container"></div>
                </div>
                
                <div class="form-section">
                    <h2>Product Status</h2>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="available" <?php echo ($product['status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                            <option value="hidden" <?php echo ($product['status'] == 'hidden') ? 'selected' : ''; ?>>Hidden</option>
                            <option value="sold_out" <?php echo ($product['status'] == 'sold_out') ? 'selected' : ''; ?>>Sold Out</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Product</button>
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