<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$saleId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($saleId <= 0) { header('Location: sales_history.php'); exit(); }

// Get sale details
$stmt = $pdo->prepare("SELECT s.*, u.username FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->execute([$saleId]);
$sale = $stmt->fetch();
if (!$sale) { header('Location: sales_history.php'); exit(); }

// Get items
$stmt = $pdo->prepare("SELECT si.*, p.name as product_name, p.quantity as current_stock, p.cost_price FROM sale_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
$stmt->execute([$saleId]);
$items = $stmt->fetchAll();

$subtotal = 0; $totalQty = 0; $profit = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $totalQty += $item['quantity'];
    if ($item['cost_price']) $profit += ($item['price'] - $item['cost_price']) * $item['quantity'];
}
$tax = $subtotal * 0.08;
$total = $sale['total_amount'];

// Previous/Next
$prev = $pdo->prepare("SELECT id FROM sales WHERE id < ? ORDER BY id DESC LIMIT 1");
$prev->execute([$saleId]); $prevId = $prev->fetchColumn();
$next = $pdo->prepare("SELECT id FROM sales WHERE id > ? ORDER BY id ASC LIMIT 1");
$next->execute([$saleId]); $nextId = $next->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale #<?= $saleId ?> - POS Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js" rel="stylesheet">
    <style>
        body { background: #f4f6f9; font-family: 'Inter', sans-serif; }
        .sidebar { background: #1a1a2e; min-height: 100vh; }
        .stat-box { background: white; border-radius: 12px; padding: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        @media print { .no-print { display: none !important; } body { background: white; } .stat-box { box-shadow: none; border: 1px solid #ddd; } }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-receipt me-2 text-primary"></i>Sale #<?= str_pad($saleId, 6, '0', STR_PAD_LEFT) ?></h1>
                    <div class="btn-toolbar no-print">
                        <div class="btn-group me-2">
                            <a href="receipt.php?sale_id=<?= $saleId ?>" class="btn btn-sm btn-success"><i class="fas fa-receipt"></i> Receipt</a>
                            <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                            <a href="sales_history.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Left: Sale Info + Summary -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-transparent"><i class="fas fa-info-circle me-2"></i>Sale Information</div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr><td><strong>Sale ID</strong></td><td>#<?= str_pad($saleId, 6, '0', STR_PAD_LEFT) ?></td></tr>
                                    <tr><td><strong>Date</strong></td><td><?= date('M d, Y', strtotime($sale['sale_date'])) ?></td></tr>
                                    <tr><td><strong>Time</strong></td><td><?= date('H:i:s', strtotime($sale['sale_date'])) ?></td></tr>
                                    <tr><td><strong>Cashier</strong></td><td><?= htmlspecialchars($sale['username']) ?></td></tr>
                                    <tr><td><strong>Items</strong></td><td><?= count($items) ?> products</td></tr>
                                    <tr><td><strong>Total Qty</strong></td><td><?= $totalQty ?></td></tr>
                                </table>
                            </div>
                        </div>
                        <div class="card shadow-sm mt-3">
                            <div class="card-header bg-transparent"><i class="fas fa-calculator me-2"></i>Summary</div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr><td>Subtotal</td><td class="text-end">$<?= number_format($subtotal, 2) ?></td></tr>
                                    <tr><td>Tax (8%)</td><td class="text-end">$<?= number_format($tax, 2) ?></td></tr>
                                    <tr><td><strong>Total</strong></td><td class="text-end fw-bold text-success">$<?= number_format($total, 2) ?></td></tr>
                                    <?php if ($profit > 0): ?>
                                    <tr><td><small>Est. Profit</small></td><td class="text-end text-info">$<?= number_format($profit, 2) ?></td></tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Items Table + Chart -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm">
                            <div class="card-header bg-transparent"><i class="fas fa-list me-2"></i>Items Sold</div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>#</th><th>Product</th><th>Price</th><th>Qty</th><th>Total</th><th>Stock</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($items as $idx => $item): ?>
                                            <tr>
                                                <td><?= $idx+1 ?></td>
                                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                                <td>$<?= number_format($item['price'], 2) ?></td>
                                                <td><span class="badge bg-primary"><?= $item['quantity'] ?></span></td>
                                                <td><strong>$<?= number_format($item['price'] * $item['quantity'], 2) ?></strong></td>
                                                <td>
                                                    <?php if (!is_null($item['current_stock'])): ?>
                                                        <?php if ($item['current_stock'] == 0): ?>
                                                            <span class="badge bg-danger">Out</span>
                                                        <?php elseif ($item['current_stock'] < 10): ?>
                                                            <span class="badge bg-warning"><?= $item['current_stock'] ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success"><?= $item['current_stock'] ?></span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Mini chart of quantities -->
                        <div class="card shadow-sm mt-3">
                            <div class="card-header bg-transparent"><i class="fas fa-chart-bar me-2"></i>Quantity Distribution</div>
                            <div class="card-body">
                                <canvas id="qtyChart" height="80"></canvas>
                            </div>
                        </div>

                        <!-- Navigation -->
                        <div class="d-flex justify-content-between mt-3 no-print">
                            <?php if ($prevId): ?>
                                <a href="view_sale.php?id=<?= $prevId ?>" class="btn btn-outline-primary"><i class="fas fa-chevron-left"></i> Previous</a>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <span class="text-muted">Sale #<?= str_pad($saleId, 6, '0', STR_PAD_LEFT) ?></span>
                            <?php if ($nextId): ?>
                                <a href="view_sale.php?id=<?= $nextId ?>" class="btn btn-outline-primary">Next <i class="fas fa-chevron-right"></i></a>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Qty Chart
        const labels = <?= json_encode(array_column($items, 'product_name')) ?>;
        const qties = <?= json_encode(array_column($items, 'quantity')) ?>;
        new Chart(document.getElementById('qtyChart'), {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: 'Quantity', data: qties, backgroundColor: '#667eea', borderRadius: 6 }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } }, maintainAspectRatio: false }
        });
    </script>
</body>
</html>
