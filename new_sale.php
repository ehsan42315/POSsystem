<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$message = '';
$messageType = '';

// Process sale
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_sale'])) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $message = 'Invalid request.';
        $messageType = 'danger';
    } else {
        $cart = json_decode($_POST['cart_data'], true);
        $total = floatval($_POST['total_amount']);
        if (!empty($cart) && $total > 0) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO sales (total_amount, user_id) VALUES (?, ?)");
                $stmt->execute([$total, $_SESSION['user_id']]);
                $saleId = $pdo->lastInsertId();
                foreach ($cart as $item) {
                    $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$saleId, $item['id'], $item['quantity'], $item['price']]);
                    $stmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                    $stmt->execute([$item['quantity'], $item['id']]);
                }
                $pdo->commit();
                header("Location: receipt.php?sale_id=$saleId");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'Cart is empty.';
            $messageType = 'warning';
        }
    }
}

// Get products
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
    <title>⚡ New Sale - POS Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; font-family: 'Inter', sans-serif; }
        .sidebar { background: #1a1a2e; min-height: 100vh; }
        .product-card { cursor: pointer; transition: all 0.2s; border-radius: 12px; border: 1px solid #e9ecef; }
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1); }
        .cart-item { background: #f8f9fa; border-radius: 10px; padding: 0.75rem; margin-bottom: 0.5rem; }
        .cart-total { border-top: 2px solid #dee2e6; padding-top: 1rem; }
        .floating-actions { position: fixed; bottom: 30px; right: 30px; z-index: 999; display: flex; flex-direction: column; gap: 10px; }
        .floating-actions .btn { border-radius: 50px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn-floating { border-radius: 50%; width: 60px; height: 60px; font-size: 24px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .barcode-input { position: relative; }
        .barcode-input input { padding-left: 40px; }
        .barcode-input i { position: absolute; left: 15px; top: 12px; color: #6c757d; }
        @media (max-width: 768px) { .sidebar { min-height: auto; } }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-cart-plus me-2 text-primary"></i>New Sale</h1>
                    <div>
                        <button class="btn btn-outline-danger btn-sm" onclick="clearCart()"><i class="fas fa-trash"></i> Clear Cart</button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show"><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="row">
                    <!-- Products -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm mb-3">
                            <div class="card-header bg-transparent"><i class="fas fa-search me-2"></i>Search / Barcode</div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-md-8">
                                        <div class="barcode-input">
                                            <i class="fas fa-barcode"></i>
                                            <input type="text" class="form-control" id="productSearch" placeholder="Search product name or scan barcode..." value="<?= htmlspecialchars($search) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-secondary w-100" onclick="document.getElementById('productSearch').value=''; this.form.submit();"><i class="fas fa-sync"></i> Reset</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm">
                            <div class="card-header bg-transparent d-flex justify-content-between">
                                <span><i class="fas fa-boxes me-2"></i>Available Products</span>
                                <span class="badge bg-primary"><?= count($products) ?> items</span>
                            </div>
                            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                <div class="row" id="productsGrid">
                                    <?php if (empty($products)): ?>
                                        <div class="col-12 text-center text-muted py-5"><i class="fas fa-box-open fa-3x mb-3"></i><br>No products in stock.</div>
                                    <?php else: foreach ($products as $p): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="product-card card h-100" onclick="addToCart(<?= htmlspecialchars(json_encode($p)) ?>)">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title"><?= htmlspecialchars($p['name']) ?></h6>
                                                    <p class="card-text">
                                                        <strong class="text-success">$<?= number_format($p['price'], 2) ?></strong><br>
                                                        <small class="text-muted">Stock: <?= $p['quantity'] ?></small>
                                                    </p>
                                                    <button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm sticky-top" style="top: 80px;">
                            <div class="card-header bg-transparent d-flex justify-content-between">
                                <span><i class="fas fa-shopping-cart me-2"></i>Cart</span>
                                <span class="badge bg-primary" id="cartCount">0</span>
                            </div>
                            <div class="card-body" id="cartBody">
                                <div id="cartItems">
                                    <div class="text-center text-muted py-4"><i class="fas fa-cart-plus fa-3x mb-3"></i><br>Cart is empty</div>
                                </div>
                                <div id="cartTotal" style="display: none;">
                                    <div class="d-flex justify-content-between"><span>Subtotal</span><span id="subtotal">$0.00</span></div>
                                    <div class="d-flex justify-content-between"><span>Tax (8%)</span><span id="tax">$0.00</span></div>
                                    <hr>
                                    <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span id="total">$0.00</span></div>
                                    <form method="POST" id="saleForm" style="display: none;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="process_sale" value="1">
                                        <input type="hidden" name="cart_data" id="cartData">
                                        <input type="hidden" name="total_amount" id="totalAmount">
                                        <button type="submit" class="btn btn-success w-100 mt-3"><i class="fas fa-credit-card me-2"></i>Process Sale</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Quantity Modal -->
    <div class="modal fade" id="qtyModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Quantity</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="number" class="form-control" id="qtyInput" min="1" value="1">
                    <small class="text-muted">Available: <span id="availStock"></span></small>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" onclick="confirmAdd()">Add</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Quick Actions -->
    <div class="floating-actions">
        <a href="new_sale.php" class="btn btn-primary btn-sm"><i class="fas fa-cart-plus me-1"></i>New Sale</a>
        <a href="products.php" class="btn btn-info btn-sm"><i class="fas fa-box me-1"></i>Products</a>
        <a href="sales_history.php" class="btn btn-secondary btn-sm"><i class="fas fa-history me-1"></i>History</a>
    </div>
    <button class="btn-floating" id="floatingBtn"><i class="fas fa-bolt"></i></button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let cart = [];
        let currentProduct = null;
        const TAX = 0.08;

        function addToCart(product) {
            currentProduct = product;
            document.getElementById('qtyInput').value = 1;
            document.getElementById('qtyInput').max = product.quantity;
            document.getElementById('availStock').textContent = product.quantity;
            new bootstrap.Modal(document.getElementById('qtyModal')).show();
        }

        function confirmAdd() {
            const qty = parseInt(document.getElementById('qtyInput').value);
            if (qty < 1 || qty > currentProduct.quantity) { alert('Invalid quantity'); return; }
            const existing = cart.findIndex(item => item.id === currentProduct.id);
            if (existing !== -1) {
                if (cart[existing].quantity + qty <= currentProduct.quantity) cart[existing].quantity += qty;
                else { alert('Not enough stock'); return; }
            } else {
                cart.push({ id: currentProduct.id, name: currentProduct.name, price: parseFloat(currentProduct.price), quantity: qty, stock: currentProduct.quantity });
            }
            updateCart();
            bootstrap.Modal.getInstance(document.getElementById('qtyModal')).hide();
        }

        function removeItem(index) { cart.splice(index, 1); updateCart(); }
        function changeQty(index, delta) {
            const newQty = cart[index].quantity + delta;
            if (newQty <= 0) { removeItem(index); return; }
            if (newQty <= cart[index].stock) { cart[index].quantity = newQty; updateCart(); }
            else alert('Not enough stock');
        }

        function updateCart() {
            const container = document.getElementById('cartItems');
            const count = document.getElementById('cartCount');
            const totalDiv = document.getElementById('cartTotal');
            const form = document.getElementById('saleForm');
            count.textContent = cart.length;
            if (cart.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-cart-plus fa-3x mb-3"></i><br>Cart is empty</div>';
                totalDiv.style.display = 'none';
                form.style.display = 'none';
                return;
            }
            let html = '', subtotal = 0;
            cart.forEach((item, idx) => {
                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;
                html += `<div class="cart-item">
                    <div class="d-flex justify-content-between"><strong>${item.name}</strong> <button class="btn btn-sm btn-outline-danger" onclick="removeItem(${idx})"><i class="fas fa-times"></i></button></div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <div><small class="text-muted">$${item.price.toFixed(2)} each</small></div>
                        <div class="input-group input-group-sm" style="width: 110px;">
                            <button class="btn btn-outline-secondary" onclick="changeQty(${idx}, -1)"><i class="fas fa-minus"></i></button>
                            <input type="text" class="form-control text-center" value="${item.quantity}" readonly>
                            <button class="btn btn-outline-secondary" onclick="changeQty(${idx}, 1)"><i class="fas fa-plus"></i></button>
                        </div>
                        <strong>$${itemTotal.toFixed(2)}</strong>
                    </div>
                </div>`;
            });
            container.innerHTML = html;
            const tax = subtotal * TAX;
            const total = subtotal + tax;
            document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('tax').textContent = '$' + tax.toFixed(2);
            document.getElementById('total').textContent = '$' + total.toFixed(2);
            document.getElementById('cartData').value = JSON.stringify(cart);
            document.getElementById('totalAmount').value = total.toFixed(2);
            totalDiv.style.display = 'block';
            form.style.display = 'block';
        }

        function clearCart() { if (cart.length && confirm('Clear cart?')) { cart = []; updateCart(); } }

        // Search with debounce
        const searchInput = document.getElementById('productSearch');
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const val = this.value;
                window.location.href = 'new_sale.php' + (val ? '?search=' + encodeURIComponent(val) : '');
            }, 300);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'c') { e.preventDefault(); clearCart(); }
            if (e.key === 'Enter' && document.activeElement === searchInput) { e.preventDefault(); searchInput.dispatchEvent(new Event('input')); }
        });

        // Floating button toggle
        let actionsVisible = false;
        document.getElementById('floatingBtn').onclick = function() {
            actionsVisible = !actionsVisible;
            document.querySelector('.floating-actions').style.display = actionsVisible ? 'flex' : 'none';
            this.querySelector('i').classList.toggle('fa-times');
        };
        document.querySelector('.floating-actions').style.display = 'none';
    </script>
</body>
</html>
