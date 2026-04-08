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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $consumerId = intval($_POST['consumer_id']);
    $address = sanitize($_POST['delivery_address']);
    $paymentMethod = sanitize($_POST['payment_method']);
    $orderStatus = sanitize($_POST['order_status']);
    $paymentStatus = sanitize($_POST['payment_status']);
    $productIds = $_POST['product_ids'] ?? [];
    $quantities = $_POST['quantities'] ?? [];

    if ($consumerId <= 0) $errors[] = "Please select a consumer";
    if (empty($address)) $errors[] = "Delivery address is required";
    if (empty($productIds)) $errors[] = "Please select at least one product";

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $totalAmount = 0;
            $itemsToInsert = [];

            foreach ($productIds as $index => $productId) {
                $productId = intval($productId);
                $qty = intval($quantities[$index] ?? 0);

                if ($productId > 0 && $qty > 0) {
                    $product = getProductById($productId);
                    if ($product) {
                        $subtotal = $product['price'] * $qty;
                        $totalAmount += $subtotal;
                        $itemsToInsert[] = [
                            'product_id' => $productId,
                            'farmer_id' => $product['farmer_id'],
                            'quantity' => $qty,
                            'unit_price' => $product['price'],
                            'subtotal' => $subtotal
                        ];
                    }
                }
            }

            if (empty($itemsToInsert)) {
                throw new Exception("No valid products selected");
            }

            // Create the order
            $query = "INSERT INTO orders (consumer_id, total_amount, delivery_address, payment_method, payment_status, status, order_date) 
                      VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("idssss", $consumerId, $totalAmount, $address, $paymentMethod, $paymentStatus, $orderStatus);
            $stmt->execute();
            $orderId = $conn->insert_id;

            // Create order items
            $itemQuery = "INSERT INTO order_items (order_id, product_id, farmer_id, quantity, unit_price, subtotal, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $itemStmt = $conn->prepare($itemQuery);

            foreach ($itemsToInsert as $item) {
                $itemStmt->bind_param("iiiidds", 
                    $orderId, 
                    $item['product_id'], 
                    $item['farmer_id'], 
                    $item['quantity'], 
                    $item['unit_price'], 
                    $item['subtotal'],
                    $orderStatus
                );
                $itemStmt->execute();
            }

            $conn->commit();
            setFlashMessage('Manual order created successfully', 'success');
            redirect('orders.php');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error creating order: " . $e->getMessage();
        }
    }
}

// Get all consumers
$consumers = [];
$res = $conn->query("SELECT id, first_name, last_name, email FROM users WHERE user_type = 'consumer' AND status = 'active'");
while ($row = $res->fetch_assoc()) $consumers[] = $row;

// Get all available products
$products = [];
$res = $conn->query("SELECT p.id, p.name, p.price, p.unit, u.first_name, u.last_name 
                    FROM products p 
                    JOIN users u ON p.farmer_id = u.id 
                    WHERE p.status = 'available' AND p.quantity_available > 0");
while ($row = $res->fetch_assoc()) $products[] = $row;

$page_title = 'Create Manual Order';
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
                <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                <li><a href="products.php"><i class="fas fa-leaf"></i> Manage Products</a></li>
                <li class="active"><a href="orders.php"><i class="fas fa-shopping-basket"></i> Manage Orders</a></li>
                <li><a href="categories.php"><i class="fas fa-tags"></i> Categories</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>Create Manual Order</h1>
            <a href="orders.php" class="btn btn-outline">Back to Orders</a>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        
        <div class="form-container" style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <form action="" method="POST" id="manual-order-form">
                <div class="form-section">
                    <h2>Customer Information</h2>
                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Select Consumer *</label>
                            <select name="consumer_id" required class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="">-- Select Consumer --</option>
                                <?php foreach ($consumers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name'] . ' (' . $c['email'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Delivery Address *</label>
                            <input type="text" name="delivery_address" required class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                </div>

                <div class="form-section" style="margin-top: 30px;">
                    <h2>Order Settings</h2>
                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="cash_on_delivery">Cash on Delivery</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Payment Status</label>
                            <select name="payment_status" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Order Status</label>
                            <select name="order_status" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="processing">Processing</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section" style="margin-top: 30px;">
                    <h2>Select Products</h2>
                    <div id="product-list" style="margin-bottom: 20px;">
                        <div class="product-row" style="display: grid; grid-template-columns: 2fr 1fr 100px auto; gap: 15px; align-items: center; margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                            <div class="form-group">
                                <label>Product</label>
                                <select name="product_ids[]" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name'] . ' - KSh ' . $p['price'] . ' (by ' . $p['first_name'] . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="number" name="quantities[]" min="1" value="1" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div></div>
                            <div></div>
                        </div>
                    </div>
                    <button type="button" onclick="addProductRow()" class="btn btn-outline btn-small"><i class="fas fa-plus"></i> Add Another Product</button>
                </div>

                <div class="form-actions" style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px; text-align: right;">
                    <button type="submit" name="create_order" class="btn btn-primary" style="padding: 12px 30px;">Create Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addProductRow() {
    const list = document.getElementById('product-list');
    const firstRow = list.querySelector('.product-row');
    const newRow = firstRow.cloneNode(true);
    
    // Clear values
    newRow.querySelector('select').value = '';
    newRow.querySelector('input').value = '1';
    
    // Add remove button
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn-small btn-danger';
    removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
    removeBtn.style.height = '40px';
    removeBtn.onclick = function() { this.closest('.product-row').remove(); };
    
    newRow.lastElementChild.appendChild(removeBtn);
    list.appendChild(newRow);
}
</script>

<?php include '../includes/footer.php'; ?>
