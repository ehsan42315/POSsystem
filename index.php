<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get today's sales summary
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as total_sales, COALESCE(SUM(total_amount), 0) as total_revenue FROM sales WHERE DATE(sale_date) = ?");
$stmt->execute([$today]);
$today_stats = $stmt->fetch();

// Get low stock products (quantity < 10)
$stmt = $pdo->prepare("SELECT * FROM products WHERE quantity < 10 ORDER BY quantity ASC LIMIT 5");
$stmt->execute();
$low_stock = $stmt->fetchAll();

// Get recent sales
$stmt = $pdo->prepare("SELECT s.*, COUNT(si.id) as items_count FROM sales s LEFT JOIN sale_items si ON s.id = si.sale_id GROUP BY s.id ORDER BY s.sale_date DESC LIMIT 5");
$stmt->execute();
$recent_sales = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Dashboard</title>
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="new_sale.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-plus"></i> New Sale
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Today's Sales
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $today_stats['total_sales']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Today's Revenue
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            $<?php echo number_format($today_stats['total_revenue'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Low Stock Items
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo count($low_stock); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Products
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $stmt = $pdo->query("SELECT COUNT(*) FROM products");
                                            echo $stmt->fetchColumn();
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-box fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales and Low Stock -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Sales</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Sale ID</th>
                                                <th>Date</th>
                                                <th>Items</th>
                                                <th>Total</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_sales as $sale): ?>
                                            <tr>
                                                <td>#<?php echo $sale['id']; ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></td>
                                                <td><?php echo $sale['items_count']; ?> items</td>
                                                <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                                <td>
                                                    <a href="view_sale.php?id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-warning">Low Stock Alert</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($low_stock)): ?>
                                    <p class="text-muted">All products are well stocked!</p>
                                <?php else: ?>
                                    <?php foreach ($low_stock as $product): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                            <small class="text-muted">Stock: <?php echo $product['quantity']; ?></small>
                                        </div>
                                        <span class="badge bg-warning">Low</span>
                                    </div>
                                    <hr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>