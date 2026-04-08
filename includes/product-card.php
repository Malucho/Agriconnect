<div class="product-card">
    <div class="product-image">
        <?php if (!empty($product['image'])): ?>
            <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
        <?php else: ?>
            <img src="assets/images/product-placeholder.jpg" alt="No image available">
        <?php endif; ?>
    </div>
    <div class="product-info">
        <h3><a href="product.php?id=<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a></h3>
        <div class="product-meta">
            <span class="category"><?php echo htmlspecialchars($product['category_name']); ?></span>
            <span class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($product['location']); ?></span>
        </div>
        <div class="product-price">
            <span class="price"><?php echo formatPrice($product['price']); ?></span>
            <span class="unit">/ <?php echo htmlspecialchars($product['unit']); ?></span>
        </div>
        <div class="product-farmer">
            <?php if (!empty($product['profile_image'])): ?>
                <img src="uploads/profiles/<?php echo htmlspecialchars($product['profile_image']); ?>" alt="Farmer">
            <?php else: ?>
                <img src="assets/images/farmer-placeholder.jpg" alt="Farmer">
            <?php endif; ?>
            <span><?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></span>
        </div>
        <div class="product-actions">
            <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-outline">Details</a>
            <?php if ($product['quantity_available'] > 0): ?>
                <button class="btn btn-primary add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                    <i class="fas fa-shopping-cart"></i>
                </button>
            <?php else: ?>
                <span class="badge badge-danger">Sold Out</span>
            <?php endif; ?>
        </div>
    </div>
</div>