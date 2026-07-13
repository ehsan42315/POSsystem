<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$message = ''; $msgType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $message = 'Invalid request.'; $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action == 'add') {
            $name = trim($_POST['name']); $price = floatval($_POST['price']); $qty = intval($_POST['quantity']);
            if ($name && $price > 0 && $qty >= 0) {
                $stmt = $pdo->prepare("INSERT INTO products (name, price, quantity) VALUES (?,?,?)");
                if ($stmt->execute([$name, $price, $qty])) { $message = 'Product added.'; $msgType = 'success'; }
                else { $message = 'Error.'; $msgType = 'danger'; }
            } else { $message = 'Invalid values.'; $msgType = 'warning'; }
        } elseif ($action == 'edit') {
            $id = intval($_POST['id']); $name = trim($_POST['name']); $price = floatval($_POST['price']); $qty = intval($_POST['quantity']);
            if ($id > 0 && $name && $price > 0 && $qty >= 0) {
                $stmt = $pdo->prepare("UPDATE products SET name=?, price=?, quantity=? WHERE id=?");
                if ($stmt->execute([$name, $price, $qty, $id])) { $message = 'Product updated.'; $msgType = 'success'; }
                else { $message = 'Error.'; $msgType = 'danger'; }
            } else { $message = 'Invalid values.'; $msgType = 'warning'; }
        } elseif ($action == 'delete') {
            $id = intval($_POST['id']);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
                if ($stmt->execute([$id])) { $message = 'Product deleted.'; $msgType = 'success'; }
                else { $message = 'Error.'; $msgType = 'danger'; }
            }
        }
    }
}

// Search
$search = $_GET['search'] ?? '';
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE name LIKE ? ORDER BY name");
    $stmt->execute(['%' . $search . '%']);
} else {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY name");
}
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 Products - POS Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; font-family: 'Inter', sans-serif; }
        .sidebar { background: #1a1a2e; min-height: 100vh; }
        .stock-bar { height: 6px; border-radius: 10px; background: #e9ecef; overflow: hidden; width: 100px; display: inline-block; vertical-align: middle; }
        .stock-bar .fill { height: 100%; border-radius: 10px; transition: width 0.4s; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-boxes me-2 text-primary"></i>Products</h1>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fas fa-plus"></i> Add Product</button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-transparent d-flex justify-content-between">
                        <span><i class="fas fa-list me-2"></i>All Products</span>
                        <span class="badge bg-primary"><?= count($products) ?> items</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="productsTable" class="table table-hover">
                                <thead><tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td><?= $p['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                        <td>$<?= number_format($p['price'], 2) ?></td>
                                        <td>
                                            <?= $p['quantity'] ?>
                                            <div class="stock-bar"><div class="fill <?= $p['quantity'] == 0 ? 'bg-danger' : ($p['quantity'] < 10 ? 'bg-warning' : 'bg-success') ?>" style="width: <?= min(100, ($p['quantity'] / 20) * 100) ?>%"></div></div>
                                        </td>
                                        <td>
                                            <?php if ($p['quantity'] == 0): ?><span class="badge bg-danger">Out</span>
                                            <?php elseif ($p['quantity'] < 10): ?><span class="badge bg-warning">Low</span>
                                            <?php else: ?><span class="badge bg-success">In Stock</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>')"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST"><div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="add">
                <div class="mb-3"><label>Name</label><input type="text" class="form-control" name="name" required></div>
                <div class="mb-3"><label>Price</label><input type="number" class="form-control" name="price" step="0.01" min="0" required></div>
                <div class="mb-3"><label>Stock</label><input type="number" class="form-control" name="quantity" min="0" required></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div></form>
        </div></div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Edit Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST"><div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-3"><label>Name</label><input type="text" class="form-control" id="edit_name" name="name" required></div>
                <div class="mb-3"><label>Price</label><input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0" required></div>
                <div class="mb-3"><label>Stock</label><input type="number" class="form-control" id="edit_qty" name="quantity" min="0" required></div>
            </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update</button></div></form>
        </div></div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p>Delete <strong id="delName"></strong>?</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="delId"><button type="submit" class="btn btn-danger">Delete</button></form>
            </div>
        </div></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editProduct(p) {
            document.getElementById('edit_id').value = p.id;
            document.getElementById('edit_name').value = p.name;
            document.getElementById('edit_price').value = p.price;
            document.getElementById('edit_qty').value = p.quantity;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
        function deleteProduct(id, name) {
            document.getElementById('delId').value = id;
            document.getElementById('delName').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
