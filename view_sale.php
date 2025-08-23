<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get sale ID
$saleId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($saleId <= 0) {
    header('Location: sales_history.php');
    exit();
}

// Get sale details
$stmt = $pdo->prepare("
    SELECT s.*, u.username 
    FROM sales s 
    LEFT JOIN users u ON s.user_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch();

if (!$sale) {
    header('Location: sales_history.php');
    exit();
}

// Get sale items with product details
$stmt = $pdo->prepare("
    SELECT si.*, p.name as product_name, p.quantity as current_stock
    FROM sale_items si 
    LEFT JOIN products p ON si.product_id = p.id 
    WHERE si.sale_id = ?
    ORDER BY si.id
");
$stmt->execute([$saleId]);
$saleItems = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
$totalQuantity = 0;
foreach ($saleItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $totalQuantity += $item['quantity'];
}
$tax = $subtotal * 0.08; // 8% tax
$total = $sale['total_amount'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale #<?php echo $sale['id']; ?> - POS System</title>
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
                    <h1 class="h2">Sale Details #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="receipt.php?sale_id=<?php echo $sale['id']; ?>" class="btn btn-sm btn-success">
                                <i class="fas fa-receipt"></i> View Receipt
                            </a>
                            <button class="btn btn-sm btn-primary" onclick="printSale()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <a href="sales_history.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to History
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Sale Information -->
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-info-circle me-2"></i>Sale Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Sale ID:</strong></td>
                                        <td>#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Date:</strong></td>
                                        <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Time:</strong></td>
                                        <td><?php echo date('H:i:s', strtotime($sale['sale_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Cashier:</strong></td>
                                        <td><?php echo htmlspecialchars($sale['username']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Items Count:</strong></td>
                                        <td><?php echo count($saleItems); ?> products</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Quantity:</strong></td>
                                        <td><?php echo $totalQuantity; ?> items</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Sale Summary -->
                        <div class="card shadow mt-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-success">
                                    <i class="fas fa-calculator me-2"></i>Sale Summary
                                </h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <td>Subtotal:</td>
                                        <td class="text-end">$<?php echo number_format($subtotal, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Tax (8%):</td>
                                        <td class="text-end">$<?php echo number_format($tax, 2); ?></td>
                                    </tr>
                                    <tr class="border-top">
                                        <td><strong>Total:</strong></td>
                                        <td class="text-end"><strong class="text-success">$<?php echo number_format($total, 2); ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Sale Items -->
                    <div class="col-lg-8">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-shopping-cart me-2"></i>Items Sold
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Product Name</th>
                                                <th>Unit Price</th>
                                                <th>Quantity</th>
                                                <th>Total</th>
                                                <th>Current Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($saleItems as $index => $item): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                    <?php if (is_null($item['current_stock'])): ?>
                                                        <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Product deleted</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>$<?php echo number_format($item['price'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $item['quantity']; ?></span>
                                                </td>
                                                <td>
                                                    <strong>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <?php if (!is_null($item['current_stock'])): ?>
                                                        <?php if ($item['current_stock'] == 0): ?>
                                                            <span class="badge bg-danger">Out of Stock</span>
                                                        <?php elseif ($item['current_stock'] < 10): ?>
                                                            <span class="badge bg-warning"><?php echo $item['current_stock']; ?> left</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success"><?php echo $item['current_stock']; ?> in stock</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-active">
                                                <td colspan="3"><strong>Total</strong></td>
                                                <td><strong><?php echo $totalQuantity; ?></strong></td>
                                                <td><strong>$<?php echo number_format($subtotal, 2); ?></strong></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="card shadow mt-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-info">
                                    <i class="fas fa-cogs me-2"></i>Actions
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <a href="receipt.php?sale_id=<?php echo $sale['id']; ?>" class="btn btn-success w-100">
                                            <i class="fas fa-receipt me-2"></i>View Receipt
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <button class="btn btn-primary w-100" onclick="printSale()">
                                            <i class="fas fa-print me-2"></i>Print Details
                                        </button>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <a href="new_sale.php" class="btn btn-info w-100">
                                            <i class="fas fa-plus me-2"></i>New Sale
                                        </a>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <a href="sales_history.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-list me-2"></i>Sales History
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php
                                        // Get previous sale
                                        $stmt = $pdo->prepare("SELECT id FROM sales WHERE id < ? ORDER BY id DESC LIMIT 1");
                                        $stmt->execute([$saleId]);
                                        $prevSale = $stmt->fetchColumn();
                                        ?>
                                        <?php if ($prevSale): ?>
                                            <a href="view_sale.php?id=<?php echo $prevSale; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-chevron-left"></i> Previous Sale
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="text-center">
                                        <span class="text-muted">Sale #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                    </div>
                                    
                                    <div>
                                        <?php
                                        // Get next sale
                                        $stmt = $pdo->prepare("SELECT id FROM sales WHERE id > ? ORDER BY id ASC LIMIT 1");
                                        $stmt->execute([$saleId]);
                                        $nextSale = $stmt->fetchColumn();
                                        ?>
                                        <?php if ($nextSale): ?>
                                            <a href="view_sale.php?id=<?php echo $nextSale; ?>" class="btn btn-outline-primary">
                                                Next Sale <i class="fas fa-chevron-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printSale() {
            window.print();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey) {
                switch(e.key) {
                    case 'p':
                        e.preventDefault();
                        printSale();
                        break;
                    case 'r':
                        e.preventDefault();
                        window.location.href = 'receipt.php?sale_id=<?php echo $sale['id']; ?>';
                        break;
                    case 'n':
                        e.preventDefault();
                        window.location.href = 'new_sale.php';
                        break;
                }
            }
        });

        // Print styles
        const printStyles = `
            <style>
                @media print {
                    .no-print { display: none !important; }
                    .card { border: 1px solid #000 !important; box-shadow: none !important; }
                    .card-header { background-color: #f8f9fa !important; }
                    body { background: white !important; }
                }
            </style>
        `;
        document.head.insertAdjacentHTML('beforeend', printStyles);
    </script>
</body>
</html>