<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get sale ID
$saleId = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;

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

// Get sale items
$stmt = $pdo->prepare("
    SELECT si.*, p.name as product_name 
    FROM sale_items si 
    LEFT JOIN products p ON si.product_id = p.id 
    WHERE si.sale_id = ?
");
$stmt->execute([$saleId]);
$saleItems = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
foreach ($saleItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$tax = $subtotal * 0.08; // 8% tax
$total = $sale['total_amount'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $sale['id']; ?> - POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .receipt { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <?php include 'includes/navbar.php'; ?>
    </div>
    
    <div class="container-fluid">
        <div class="row">
            <div class="no-print">
                <?php include 'includes/sidebar.php'; ?>
            </div>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                    <h1 class="h2">Sale Receipt</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print Receipt
                            </button>
                            <a href="new_sale.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus"></i> New Sale
                            </a>
                            <a href="sales_history.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-list"></i> Sales History
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Receipt -->
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6">
                        <div class="receipt">
                            <!-- Header -->
                            <div class="receipt-header">
                                <h2 class="mb-1">RETAIL SHOP</h2>
                                <p class="mb-1">123 Main Street, City, State 12345</p>
                                <p class="mb-1">Phone: (555) 123-4567</p>
                                <p class="mb-0">Email: info@retailshop.com</p>
                            </div>

                            <!-- Sale Info -->
                            <div class="row mb-3">
                                <div class="col-6">
                                    <strong>Receipt #:</strong> <?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?><br>
                                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($sale['sale_date'])); ?><br>
                                    <strong>Time:</strong> <?php echo date('H:i:s', strtotime($sale['sale_date'])); ?>
                                </div>
                                <div class="col-6 text-end">
                                    <strong>Cashier:</strong> <?php echo htmlspecialchars($sale['username']); ?><br>
                                    <strong>Terminal:</strong> POS-01<br>
                                    <strong>Transaction:</strong> SALE
                                </div>
                            </div>

                            <!-- Items -->
                            <div class="mb-3">
                                <div class="receipt-item" style="font-weight: bold; border-bottom: 2px solid #000;">
                                    <span>ITEM</span>
                                    <span>QTY x PRICE = TOTAL</span>
                                </div>
                                
                                <?php foreach ($saleItems as $item): ?>
                                <div class="receipt-item">
                                    <div style="width: 100%;">
                                        <div class="d-flex justify-content-between">
                                            <span><?php echo htmlspecialchars($item['product_name']); ?></span>
                                            <span>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.9em;">
                                            <?php echo $item['quantity']; ?> x $<?php echo number_format($item['price'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Totals -->
                            <div class="receipt-total">
                                <div class="receipt-item">
                                    <span>SUBTOTAL:</span>
                                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="receipt-item">
                                    <span>TAX (8%):</span>
                                    <span>$<?php echo number_format($tax, 2); ?></span>
                                </div>
                                <div class="receipt-item" style="font-size: 1.2em; font-weight: bold; border-top: 2px solid #000; padding-top: 0.5rem;">
                                    <span>TOTAL:</span>
                                    <span>$<?php echo number_format($total, 2); ?></span>
                                </div>
                            </div>

                            <!-- Payment Info -->
                            <div class="mt-3 text-center">
                                <div class="receipt-item">
                                    <span>PAYMENT METHOD:</span>
                                    <span>CASH</span>
                                </div>
                                <div class="receipt-item">
                                    <span>AMOUNT PAID:</span>
                                    <span>$<?php echo number_format($total, 2); ?></span>
                                </div>
                                <div class="receipt-item">
                                    <span>CHANGE:</span>
                                    <span>$0.00</span>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="text-center mt-4" style="border-top: 2px solid #000; padding-top: 1rem;">
                                <p class="mb-1"><strong>THANK YOU FOR YOUR BUSINESS!</strong></p>
                                <p class="mb-1">Please keep this receipt for your records</p>
                                <p class="mb-1">Return Policy: 30 days with receipt</p>
                                <p class="mb-0">Visit us online: www.retailshop.com</p>
                            </div>

                            <!-- Barcode Simulation -->
                            <div class="text-center mt-3">
                                <div style="font-family: 'Courier New', monospace; font-size: 24px; letter-spacing: 2px;">
                                    ||||| |||| | ||| |||| ||||| | |||| |||||
                                </div>
                                <small><?php echo str_pad($sale['id'], 12, '0', STR_PAD_LEFT); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sale Summary Card (No Print) -->
                <div class="row mt-4 no-print">
                    <div class="col-md-8 col-lg-6 mx-auto">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-success">
                                    <i class="fas fa-check-circle me-2"></i>Sale Completed Successfully
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Sale Summary</h6>
                                        <ul class="list-unstyled">
                                            <li><strong>Items Sold:</strong> <?php echo count($saleItems); ?></li>
                                            <li><strong>Total Quantity:</strong> 
                                                <?php 
                                                $totalQty = 0;
                                                foreach ($saleItems as $item) {
                                                    $totalQty += $item['quantity'];
                                                }
                                                echo $totalQty;
                                                ?>
                                            </li>
                                            <li><strong>Sale Amount:</strong> $<?php echo number_format($total, 2); ?></li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Quick Actions</h6>
                                        <div class="d-grid gap-2">
                                            <a href="new_sale.php" class="btn btn-success btn-sm">
                                                <i class="fas fa-plus"></i> New Sale
                                            </a>
                                            <a href="view_sale.php?id=<?php echo $sale['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                            <button class="btn btn-primary btn-sm" onclick="window.print()">
                                                <i class="fas fa-print"></i> Print Again
                                            </button>
                                        </div>
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
        // Auto-print on page load (optional)
        // window.onload = function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 1000);
        // };
        
        // Print function
        function printReceipt() {
            window.print();
        }
        
        // Keyboard shortcut for printing
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>