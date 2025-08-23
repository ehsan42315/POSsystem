<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';

// Handle sale processing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_sale'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $total = floatval($_POST['total_amount']);
    
    if (!empty($cart) && $total > 0) {
        try {
            $pdo->beginTransaction();
            
            // Insert sale record
            $stmt = $pdo->prepare("INSERT INTO sales (total_amount, user_id) VALUES (?, ?)");
            $stmt->execute([$total, $_SESSION['user_id']]);
            $saleId = $pdo->lastInsertId();
            
            // Insert sale items and update stock
            foreach ($cart as $item) {
                // Insert sale item
                $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$saleId, $item['id'], $item['quantity'], $item['price']]);
                
                // Update product stock
                $stmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['id']]);
            }
            
            $pdo->commit();
            
            // Redirect to receipt page
            header("Location: receipt.php?sale_id=$saleId");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error processing sale: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = 'Cart is empty or invalid total amount.';
        $messageType = 'warning';
    }
}

// Get all products for sale
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE name LIKE ? AND quantity > 0 ORDER BY name");
    $stmt->execute(['%' . $search . '%']);
} else {
    $stmt = $pdo->query("SELECT * FROM products WHERE quantity > 0 ORDER BY name");
}
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Sale - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">New Sale</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-sm btn-outline-secondary" onclick="clearCart()">
                            <i class="fas fa-trash"></i> Clear Cart
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Products Section -->
                    <div class="col-lg-8">
                        <!-- Search Bar -->
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Product Search</h6>
                            </div>
                            <div class="card-body">
                                <div class="search-box">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" class="form-control" id="productSearch" 
                                           placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Products Grid -->
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Available Products 
                                    <span class="badge bg-primary"><?php echo count($products); ?> items</span>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row" id="productsGrid">
                                    <?php if (empty($products)): ?>
                                        <div class="col-12 text-center text-muted py-4">
                                            <i class="fas fa-box-open fa-3x mb-3"></i><br>
                                            No products available.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card product-card h-100" onclick="addToCart(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                    <p class="card-text">
                                                        <strong class="text-success">$<?php echo number_format($product['price'], 2); ?></strong><br>
                                                        <small class="text-muted">Stock: <?php echo $product['quantity']; ?></small>
                                                    </p>
                                                    <button class="btn btn-sm btn-primary">
                                                        <i class="fas fa-plus"></i> Add to Cart
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Section -->
                    <div class="col-lg-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Shopping Cart 
                                    <span class="badge bg-primary" id="cartCount">0</span>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="cartItems">
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-shopping-cart fa-3x mb-3"></i><br>
                                        Cart is empty
                                    </div>
                                </div>
                                
                                <div id="cartTotal" class="cart-total mt-3" style="display: none;">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <span id="subtotal">$0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Tax (8%):</span>
                                        <span id="tax">$0.00</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <strong>Total:</strong>
                                        <strong id="total">$0.00</strong>
                                    </div>
                                </div>
                                
                                <form method="POST" id="saleForm" style="display: none;">
                                    <input type="hidden" name="process_sale" value="1">
                                    <input type="hidden" name="cart_data" id="cartData">
                                    <input type="hidden" name="total_amount" id="totalAmount">
                                    <button type="submit" class="btn btn-success w-100 mt-3">
                                        <i class="fas fa-credit-card"></i> Process Sale
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Quantity Modal -->
    <div class="modal fade" id="quantityModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Quantity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" min="1" value="1">
                        <small class="text-muted">Available: <span id="availableStock"></span></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmAddToCart()">Add to Cart</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let cart = [];
        let currentProduct = null;
        const TAX_RATE = 0.08;

        function addToCart(product) {
            currentProduct = product;
            document.getElementById('quantity').value = 1;
            document.getElementById('quantity').max = product.quantity;
            document.getElementById('availableStock').textContent = product.quantity;
            
            const modal = new bootstrap.Modal(document.getElementById('quantityModal'));
            modal.show();
        }

        function confirmAddToCart() {
            const quantity = parseInt(document.getElementById('quantity').value);
            
            if (quantity <= 0 || quantity > currentProduct.quantity) {
                alert('Invalid quantity');
                return;
            }

            // Check if product already in cart
            const existingIndex = cart.findIndex(item => item.id === currentProduct.id);
            
            if (existingIndex !== -1) {
                // Update quantity
                const newQuantity = cart[existingIndex].quantity + quantity;
                if (newQuantity <= currentProduct.quantity) {
                    cart[existingIndex].quantity = newQuantity;
                } else {
                    alert('Not enough stock available');
                    return;
                }
            } else {
                // Add new item
                cart.push({
                    id: currentProduct.id,
                    name: currentProduct.name,
                    price: parseFloat(currentProduct.price),
                    quantity: quantity,
                    stock: currentProduct.quantity
                });
            }

            updateCartDisplay();
            bootstrap.Modal.getInstance(document.getElementById('quantityModal')).hide();
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }

        function updateQuantity(index, newQuantity) {
            if (newQuantity <= 0) {
                removeFromCart(index);
                return;
            }
            
            if (newQuantity <= cart[index].stock) {
                cart[index].quantity = newQuantity;
                updateCartDisplay();
            } else {
                alert('Not enough stock available');
            }
        }

        function updateCartDisplay() {
            const cartItems = document.getElementById('cartItems');
            const cartCount = document.getElementById('cartCount');
            const cartTotal = document.getElementById('cartTotal');
            const saleForm = document.getElementById('saleForm');
            
            cartCount.textContent = cart.length;
            
            if (cart.length === 0) {
                cartItems.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-shopping-cart fa-3x mb-3"></i><br>
                        Cart is empty
                    </div>
                `;
                cartTotal.style.display = 'none';
                saleForm.style.display = 'none';
                return;
            }
            
            let html = '';
            let subtotal = 0;
            
            cart.forEach((item, index) => {
                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;
                
                html += `
                    <div class="cart-item">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${item.name}</h6>
                                <small class="text-muted">$${item.price.toFixed(2)} each</small>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="input-group" style="width: 120px;">
                                <button class="btn btn-outline-secondary btn-sm" type="button" 
                                        onclick="updateQuantity(${index}, ${item.quantity - 1})">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="text" class="form-control form-control-sm text-center" 
                                       value="${item.quantity}" readonly>
                                <button class="btn btn-outline-secondary btn-sm" type="button" 
                                        onclick="updateQuantity(${index}, ${item.quantity + 1})">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <strong>$${itemTotal.toFixed(2)}</strong>
                        </div>
                    </div>
                `;
            });
            
            cartItems.innerHTML = html;
            
            const tax = subtotal * TAX_RATE;
            const total = subtotal + tax;
            
            document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
            document.getElementById('tax').textContent = `$${tax.toFixed(2)}`;
            document.getElementById('total').textContent = `$${total.toFixed(2)}`;
            
            document.getElementById('cartData').value = JSON.stringify(cart);
            document.getElementById('totalAmount').value = total.toFixed(2);
            
            cartTotal.style.display = 'block';
            saleForm.style.display = 'block';
        }

        function clearCart() {
            if (cart.length > 0 && confirm('Are you sure you want to clear the cart?')) {
                cart = [];
                updateCartDisplay();
            }
        }

        // Search functionality
        document.getElementById('productSearch').addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                const searchTerm = this.value;
                window.location.href = `new_sale.php${searchTerm ? '?search=' + encodeURIComponent(searchTerm) : ''}`;
            }, 500);
        });

        // Prevent form submission on Enter in search
        document.getElementById('productSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>