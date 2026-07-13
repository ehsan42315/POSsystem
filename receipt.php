<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$saleId = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;
if ($saleId <= 0) { header('Location: sales_history.php'); exit(); }

// Fetch sale and items (same as before)
$stmt = $pdo->prepare("SELECT s.*, u.username FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->execute([$saleId]); $sale = $stmt->fetch();
if (!$sale) { header('Location: sales_history.php'); exit(); }

$stmt = $pdo->prepare("SELECT si.*, p.name as product_name FROM sale_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
$stmt->execute([$saleId]); $items = $stmt->fetchAll();

$subtotal = 0; foreach ($items as $item) $subtotal += $item['price'] * $item['quantity'];
$tax = $subtotal * 0.08;
$total = $sale['total_amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $saleId ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Courier New', monospace; }
        .receipt { max-width: 400px; margin: 2rem auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .receipt-header { text-align: center; border-bottom: 2px dashed #dee2e6; padding-bottom: 1rem; }
        .receipt-item { display: flex; justify-content: space-between; padding: 0.25rem 0; border-bottom: 1px dotted #eee; }
        .receipt-total { border-top: 2px solid #000; padding-top: 0.5rem; margin-top: 0.5rem; }
        @media print {
            body { background: white; }
            .receipt { box-shadow: none; border: none; margin: 0 auto; }
            .no-print { display: none !important; }
        }
        .company-name { font-size: 1.5rem; font-weight: 700; }
        .barcode { font-family: 'Courier New', monospace; letter-spacing: 2px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="receipt">
            <!-- Company Header -->
            <div class="receipt-header">
                <div class="company-name">🏪 RETAIL SHOP</div>
                <div>123 Main Street, City, State 12345</div>
                <div>Phone: (555) 123-4567</div>
                <div>Email: info@retailshop.com</div>
            </div>

            <!-- Sale Info -->
            <div class="d-flex justify-content-between my-2">
                <span><strong>Receipt #</strong> <?= str_pad($saleId, 6, '0', STR_PAD_LEFT) ?></span>
                <span><strong>Date</strong> <?= date('M d, Y', strtotime($sale['sale_date'])) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span><strong>Time</strong> <?= date('H:i:s', strtotime($sale['sale_date'])) ?></span>
                <span><strong>Cashier</strong> <?= htmlspecialchars($sale['username']) ?></span>
            </div>

            <!-- Items -->
            <div style="border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 0.5rem 0; margin: 0.5rem 0;">
                <?php foreach ($items as $item): ?>
                <div class="receipt-item">
                    <span><?= htmlspecialchars($item['product_name']) ?></span>
                    <span>$<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                </div>
                <div style="font-size: 0.8rem; color: #6c757d; padding-left: 0.5rem;"><?= $item['quantity'] ?> x $<?= number_format($item['price'], 2) ?></div>
                <?php endforeach; ?>
            </div>

            <!-- Totals -->
            <div class="receipt-total">
                <div class="receipt-item"><span>Subtotal</span><span>$<?= number_format($subtotal, 2) ?></span></div>
                <div class="receipt-item"><span>Tax (8%)</span><span>$<?= number_format($tax, 2) ?></span></div>
                <div class="receipt-item fw-bold fs-5"><span>TOTAL</span><span>$<?= number_format($total, 2) ?></span></div>
            </div>

            <!-- Payment -->
            <div class="receipt-item mt-2"><span>Payment</span><span>Cash</span></div>
            <div class="receipt-item"><span>Amount Paid</span><span>$<?= number_format($total, 2) ?></span></div>
            <div class="receipt-item"><span>Change</span><span>$0.00</span></div>

            <!-- Footer -->
            <div class="text-center mt-3 border-top pt-2">
                <p><strong>THANK YOU FOR YOUR BUSINESS!</strong></p>
                <p class="small">Please keep this receipt for your records.<br>Return Policy: 30 days with receipt.</p>
                <div class="barcode">||||| ||||| ||| |||| ||||| |||| |||||</div>
                <div class="small"><?= str_pad($saleId, 12, '0', STR_PAD_LEFT) ?></div>
            </div>
        </div>

        <!-- Actions (No Print) -->
        <div class="text-center no-print mt-3">
            <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            <a href="view_sale.php?id=<?= $saleId ?>" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-print (uncomment to auto print on load)
        // window.onload = function() { setTimeout(window.print, 500); };
    </script>
</body>
</html>
