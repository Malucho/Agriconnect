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

// Handle new category
if (isset($_POST['add_category']) && isset($_POST['category_name'])) {
    $name = sanitize($_POST['category_name']);
    $description = sanitize($_POST['category_description']);
    
    $query = "INSERT INTO product_categories (name, description) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $name, $description);
    
    if ($stmt->execute()) {
        setFlashMessage('Category added successfully', 'success');
    } else {
        setFlashMessage('Error adding category', 'danger');
    }
    
    redirect('categories.php');
    exit();
}

// Handle category update
if (isset($_POST['update_category']) && isset($_POST['category_id'])) {
    $categoryId = intval($_POST['category_id']);
    $name = sanitize($_POST['category_name']);
    $description = sanitize($_POST['category_description']);
    
    $query = "UPDATE product_categories SET name = ?, description = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $name, $description, $categoryId);
    
    if ($stmt->execute()) {
        setFlashMessage('Category updated successfully', 'success');
    } else {
        setFlashMessage('Error updating category', 'danger');
    }
    
    redirect('categories.php');
    exit();
}

// Handle category deletion
if (isset($_POST['delete_category']) && isset($_POST['category_id'])) {
    $categoryId = intval($_POST['category_id']);
    
    // Check if category has products
    $check = $conn->query("SELECT id FROM products WHERE category_id = $categoryId LIMIT 1");
    if ($check->num_rows > 0) {
        setFlashMessage('Cannot delete category with products', 'danger');
    } else {
        $conn->query("DELETE FROM product_categories WHERE id = $categoryId");
        setFlashMessage('Category deleted successfully', 'success');
    }
    
    redirect('categories.php');
    exit();
}

// Get all categories
$categories = [];
$result = $conn->query("SELECT * FROM product_categories ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

$page_title = 'Manage Categories';
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
                <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="products.php"><i class="fas fa-leaf"></i> Manage Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> Manage Orders</a></li>
                <li class="active"><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>Manage Categories</h1>
            <button onclick="document.getElementById('addCategoryModal').style.display='block'" class="btn btn-primary">Add Category</button>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <div class="table-responsive">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td>#<?php echo $category['id']; ?></td>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td><?php echo htmlspecialchars($category['description']); ?></td>
                        <td>
                            <div class="action-buttons" style="display: flex; gap: 5px;">
                                <button onclick='openEditModal(<?php echo json_encode($category); ?>)' class="btn-small btn-success">Edit</button>
                                <form action="" method="POST" style="display:inline;">
                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                    <button type="submit" name="delete_category" value="1" class="btn-small btn-danger" onclick="return confirm('Are you sure you want to delete this category?')">
                                        Delete
                                    </button>
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

<!-- Add Category Modal -->
<div id="addCategoryModal" class="modal" style="display:none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color: #fff; margin: 15% auto; padding: 20px; border-radius: 8px; width: 400px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Add Category</h2>
            <span onclick="document.getElementById('addCategoryModal').style.display='none'" style="cursor: pointer; font-size: 24px;">&times;</span>
        </div>
        <form action="" method="POST">
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Category Name</label>
                <input type="text" name="category_name" required class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px;">Description</label>
                <textarea name="category_description" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
            <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="document.getElementById('addCategoryModal').style.display='none'" class="btn btn-outline">Cancel</button>
                <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="modal" style="display:none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color: #fff; margin: 15% auto; padding: 20px; border-radius: 8px; width: 400px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Edit Category</h2>
            <span onclick="document.getElementById('editCategoryModal').style.display='none'" style="cursor: pointer; font-size: 24px;">&times;</span>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="category_id" id="edit_category_id">
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Category Name</label>
                <input type="text" name="category_name" id="edit_category_name" required class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px;">Description</label>
                <textarea name="category_description" id="edit_category_description" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
            </div>
            <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="document.getElementById('editCategoryModal').style.display='none'" class="btn btn-outline">Cancel</button>
                <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(category) {
    document.getElementById('edit_category_id').value = category.id;
    document.getElementById('edit_category_name').value = category.name;
    document.getElementById('edit_category_description').value = category.description;
    document.getElementById('editCategoryModal').style.display = 'block';
}
</script>

<?php include '../includes/footer.php'; ?>
