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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = trim($_POST['name']);
                $price = floatval($_POST['price']);
                $quantity = intval($_POST['quantity']);
                
                if (!empty($name) && $price > 0 && $quantity >= 0) {
                    $stmt = $pdo->prepare("INSERT INTO products (name, price, quantity) VALUES (?, ?, ?)");
                    if ($stmt->execute([$name, $price, $quantity])) {
                        $message = 'Product added successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error adding product.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Please fill all fields with valid values.';
                    $messageType = 'warning';
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $name = trim($_POST['name']);
                $price = floatval($_POST['price']);
                $quantity = intval($_POST['quantity']);
                
                if ($id > 0 && !empty($name) && $price > 0 && $quantity >= 0) {
                    $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, quantity = ? WHERE id = ?");
                    if ($stmt->execute([$name, $price, $quantity, $id])) {
                        $message = 'Product updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error updating product.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Please fill all fields with valid values.';
                    $messageType = 'warning';
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                if ($id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $message = 'Product deleted successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error deleting product.';
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get products with search functionality
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE name LIKE ? ORDER BY name");
    $stmt->execute(['%' . $search . '%']);
} else {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY name");
}
$products = $stmt->fetchAll();

// Get product for editing if requested
$editProduct = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - POS System</title>
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
                    <h1 class="h2">Products Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Search Bar -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form method="GET" class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <?php if ($search): ?>
                            <a href="products.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear Search
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Products List 
                            <span class="badge bg-primary"><?php echo count($products); ?> items</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="fas fa-box-open fa-3x mb-3"></i><br>
                                                No products found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?php echo $product['id']; ?></td>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                                            <td><?php echo $product['quantity']; ?></td>
                                            <td>
                                                <?php if ($product['quantity'] == 0): ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php elseif ($product['quantity'] < 10): ?>
                                                    <span class="badge bg-warning">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="form-label">Price</label>
                            <input type="number" class="form-control" id="price" name="price" 
                                   step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Initial Stock</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" 
                                   min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_price" class="form-label">Price</label>
                            <input type="number" class="form-control" id="edit_price" name="price" 
                                   step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_quantity" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="edit_quantity" name="quantity" 
                                   min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this product?</p>
                    <p><strong id="delete_product_name"></strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editProduct(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_quantity').value = product.quantity;
            
            var modal = new bootstrap.Modal(document.getElementById('editProductModal'));
            modal.show();
        }
        
        function deleteProduct(id, name) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_product_name').textContent = name;
            
            var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
        
        // Auto-submit search form on input
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    </script>
</body>
</html>