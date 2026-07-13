<?php
session_start();
require_once 'config/database.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ----------------------------------------------------------------------
// 1. DASHBOARD STATS
// ----------------------------------------------------------------------
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Today's sales & revenue
$stmt = $pdo->prepare("SELECT COUNT(*) as total_sales, COALESCE(SUM(total_amount), 0) as total_revenue FROM sales WHERE DATE(sale_date) = ?");
$stmt->execute([$today]);
$today_stats = $stmt->fetch();

// Yesterday's revenue for comparison
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE DATE(sale_date) = ?");
$stmt->execute([$yesterday]);
$yesterday_revenue = $stmt->fetchColumn();
$revenue_trend = ($yesterday_revenue > 0) ? (($today_stats['total_revenue'] - $yesterday_revenue) / $yesterday_revenue * 100) : 0;

// Total products
$stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN quantity > 0 THEN 1 ELSE 0 END) as active FROM products");
$product_stats = $stmt->fetch();

// Low stock (quantity < 20)
$stmt = $pdo->prepare("SELECT * FROM products WHERE quantity < 20 ORDER BY quantity ASC");
$stmt->execute();
$low_stock = $stmt->fetchAll();
$low_stock_count = count($low_stock);

// ----------------------------------------------------------------------
// 2. SALES TREND (LAST 7 DAYS)
// ----------------------------------------------------------------------
$dates = [];
$revenues = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('M d', strtotime($date));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as daily_revenue FROM sales WHERE DATE(sale_date) = ?");
    $stmt->execute([$date]);
    $revenues[] = (float) $stmt->fetchColumn();
}

// ----------------------------------------------------------------------
// 3. TOP SELLING PRODUCTS (by quantity sold)
// ----------------------------------------------------------------------
$stmt = $pdo->query("
    SELECT p.name, SUM(si.quantity) as total_sold 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    GROUP BY p.id 
    ORDER BY total_sold DESC 
    LIMIT 5
");
$top_products = $stmt->fetchAll();

// ----------------------------------------------------------------------
// 4. CATEGORY SALES (if categories table exists)
// ----------------------------------------------------------------------
$category_data = [];
try {
    $stmt = $pdo->query("
        SELECT c.name as category, COALESCE(SUM(si.quantity * si.price), 0) as total 
        FROM sale_items si 
        JOIN products p ON si.product_id = p.id 
        LEFT JOIN categories c ON p.category_id = c.id 
        GROUP BY c.id
    ");
    $category_data = $stmt->fetchAll();
} catch (PDOException $e) {
    // If no categories table, fallback: group by product name
    $stmt = $pdo->query("
        SELECT p.name as category, COALESCE(SUM(si.quantity * si.price), 0) as total 
        FROM sale_items si 
        JOIN products p ON si.product_id = p.id 
        GROUP BY p.id 
        LIMIT 5
    ");
    $category_data = $stmt->fetchAll();
}

// ----------------------------------------------------------------------
// 5. RECENT SALES (with item count and profit estimate)
// ----------------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT s.*, 
           COUNT(si.id) as items_count,
           COALESCE(SUM(si.quantity * (si.price - IFNULL(p.cost_price, 0))), 0) as estimated_profit
    FROM sales s
    LEFT JOIN sale_items si ON s.id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.id
    GROUP BY s.id
    ORDER BY s.sale_date DESC
    LIMIT 10
");
$stmt->execute();
$recent_sales = $stmt->fetchAll();

// ----------------------------------------------------------------------
// 6. CSRF TOKEN (for future forms)
// ----------------------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ POS Dashboard Pro</title>
    <!-- Bootstrap 5 + Icons + Chart.js + DataTables -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Custom Styles -->
    <style>
        :root {
            --sidebar-bg: #1a1a2e;
            --sidebar-hover: #16213e;
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
            --transition-speed: 0.3s;
        }
        body {
            background: #f4f6f9;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .sidebar {
            background: var(--sidebar-bg);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            border-radius: 10px;
            margin: 4px 12px;
            padding: 12px 16px;
            transition: all var(--transition-speed);
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
            transform: translateX(5px);
        }
        .sidebar .nav-link i {
            width: 24px;
        }
        .main-content {
            padding-top: 1rem;
        }
        .stat-card {
            border: none;
            border-radius: 16px;
            background: white;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 2rem rgba(0,0,0,0.15);
        }
        .stat-card .card-body {
            padding: 1.5rem;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: white;
        }
        .stat-icon.primary { background: var(--primary-gradient); }
        .stat-icon.success { background: linear-gradient(135deg, #11998e, #38ef7d); }
        .stat-icon.warning { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .stat-icon.info { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .card-header-custom {
            background: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        .low-stock-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .low-stock-item:last-child { border-bottom: none; }
        .stock-progress {
            flex: 1;
            height: 6px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }
        .stock-progress .bar {
            height: 100%;
            border-radius: 10px;
            transition: width 0.6s ease;
        }
        .stock-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
        }
        .btn-floating {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 999;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            background: var(--primary-gradient);
            border: none;
            color: white;
            transition: transform 0.2s;
        }
        .btn-floating:hover {
            transform: scale(1.1);
            color: white;
        }
        .btn-floating i { line-height: 60px; }
        .quick-actions {
            position: fixed;
            bottom: 100px;
            right: 30px;
            z-index: 998;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .quick-actions .btn {
            border-radius: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background: white;
            color: #333;
            border: none;
            padding: 8px 16px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .quick-actions .btn:hover {
            transform: translateX(-5px);
            background: #f8f9fa;
        }
        .quick-actions .btn i { margin-right: 8px; }
        .live-clock {
            font-weight: 300;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 0.3rem 1rem;
        }
        .table th { border-top: none; }
        @media (max-width: 768px) {
            .sidebar { min-height: auto; }
            .btn-floating { width: 50px; height: 50px; font-size: 20px; bottom: 20px; right: 20px; }
            .quick-actions { bottom: 80px; right: 20px; }
        }
    </style>
</head>
<body>
    <!-- Navbar (included) -->
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (included) -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Page Header with Live Clock -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard</h1>
                    <div class="d-flex align-items-center gap-3">
                        <span class="live-clock" id="liveClock"><i class="far fa-clock me-1"></i>Loading...</span>
                        <a href="new_sale.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>New Sale
                        </a>
                    </div>
                </div>

                <!-- Stats Cards Row -->
                <div class="row g-4 mb-4">
                    <!-- Today's Sales -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="text-uppercase text-muted small fw-bold">Today's Sales</span>
                                        <h3 class="mt-2 mb-0"><?= $today_stats['total_sales'] ?></h3>
                                        <small class="text-muted">Orders</small>
                                    </div>
                                    <div class="stat-icon primary"><i class="fas fa-shopping-cart"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Today's Revenue -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="text-uppercase text-muted small fw-bold">Today's Revenue</span>
                                        <h3 class="mt-2 mb-0">$<?= number_format($today_stats['total_revenue'], 2) ?></h3>
                                        <small class="<?= $revenue_trend >= 0 ? 'trend-up' : 'trend-down' ?>">
                                            <i class="fas fa-<?= $revenue_trend >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                            <?= number_format(abs($revenue_trend), 1) ?>% vs yesterday
                                        </small>
                                    </div>
                                    <div class="stat-icon success"><i class="fas fa-dollar-sign"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Low Stock -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="text-uppercase text-muted small fw-bold">Low Stock Items</span>
                                        <h3 class="mt-2 mb-0 <?= $low_stock_count > 0 ? 'text-warning' : '' ?>"><?= $low_stock_count ?></h3>
                                        <small class="text-muted">Threshold: &lt; 20</small>
                                    </div>
                                    <div class="stat-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Total Products -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="text-uppercase text-muted small fw-bold">Total Products</span>
                                        <h3 class="mt-2 mb-0"><?= $product_stats['total'] ?></h3>
                                        <small class="text-muted"><?= $product_stats['active'] ?> active</small>
                                    </div>
                                    <div class="stat-icon info"><i class="fas fa-boxes"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-8">
                        <div class="card shadow-sm">
                            <div class="card-header-custom d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-chart-line me-2 text-primary"></i>Sales Trend (Last 7 Days)</span>
                                <span class="badge bg-secondary">Revenue</span>
                            </div>
                            <div class="card-body">
                                <canvas id="salesTrendChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header-custom">
                                <i class="fas fa-chart-pie me-2 text-primary"></i>Category Sales
                            </div>
                            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                                <canvas id="categoryChart" width="200" height="200"></canvas>
                                <div id="categoryLegend" class="mt-3 small w-100"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Products & Low Stock -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header-custom">
                                <i class="fas fa-trophy me-2 text-warning"></i>Top Selling Products
                            </div>
                            <div class="card-body">
                                <canvas id="topProductsChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header-custom d-flex justify-content-between">
                                <span><i class="fas fa-exclamation-circle me-2 text-warning"></i>Low Stock Alerts</span>
                                <span class="badge bg-warning text-dark"><?= $low_stock_count ?> items</span>
                            </div>
                            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                <?php if (empty($low_stock)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                                        All products are well stocked!
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($low_stock as $product): 
                                        $percentage = min(100, ($product['quantity'] / 20) * 100);
                                        $color = $product['quantity'] < 5 ? 'danger' : ($product['quantity'] < 10 ? 'warning' : 'info');
                                    ?>
                                    <div class="low-stock-item">
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <strong><?= htmlspecialchars($product['name']) ?></strong>
                                                <span class="stock-badge bg-<?= $color ?> text-white"><?= $product['quantity'] ?> left</span>
                                            </div>
                                            <div class="stock-progress">
                                                <div class="bar bg-<?= $color ?>" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales Table -->
                <div class="card shadow-sm mb-5">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-table me-2 text-primary"></i>Recent Sales</span>
                        <a href="sales.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="recentSalesTable" class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Sale ID</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Est. Profit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td><strong>#<?= $sale['id'] ?></strong></td>
                                        <td><?= date('M d, Y H:i', strtotime($sale['sale_date'])) ?></td>
                                        <td><span class="badge bg-secondary"><?= $sale['items_count'] ?></span></td>
                                        <td><span class="fw-bold">$<?= number_format($sale['total_amount'], 2) ?></span></td>
                                        <td><span class="text-success">$<?= number_format($sale['estimated_profit'], 2) ?></span></td>
                                        <td>
                                            <a href="view_sale.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $sale['id'] ?>)">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
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

    <!-- Floating Action Button & Quick Actions -->
    <div class="quick-actions">
        <a href="new_sale.php" class="btn btn-light shadow-sm"><i class="fas fa-cart-plus text-primary"></i> New Sale</a>
        <a href="add_product.php" class="btn btn-light shadow-sm"><i class="fas fa-plus-circle text-success"></i> Add Product</a>
        <a href="sales.php" class="btn btn-light shadow-sm"><i class="fas fa-list-ul text-info"></i> All Sales</a>
        <a href="inventory.php" class="btn btn-light shadow-sm"><i class="fas fa-warehouse text-warning"></i> Inventory</a>
    </div>
    <button class="btn-floating" id="mainFloatingBtn" title="Quick Actions">
        <i class="fas fa-bolt"></i>
    </button>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function() {
            // ===================== LIVE CLOCK =====================
            function updateClock() {
                const now = new Date();
                const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
                document.getElementById('liveClock').innerHTML = '<i class="far fa-clock me-1"></i>' + now.toLocaleDateString('en-US', options);
            }
            updateClock();
            setInterval(updateClock, 1000);

            // ===================== DATA TABLE =====================
            $('#recentSalesTable').DataTable({
                order: [[1, 'desc']],
                pageLength: 5,
                responsive: true,
                dom: '<"d-flex justify-content-between align-items-center"lf>t<"d-flex justify-content-between"ip>',
                language: { search: "_INPUT_", searchPlaceholder: "Search sales..." }
            });

            // ===================== SALES TREND CHART =====================
            const ctx1 = document.getElementById('salesTrendChart').getContext('2d');
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: <?= json_encode($dates) ?>,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: <?= json_encode($revenues) ?>,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => '$' + ctx.parsed.y.toFixed(2) } }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => '$' + v.toFixed(0) } },
                        x: { grid: { display: false } }
                    },
                    interaction: { intersect: false, mode: 'index' }
                }
            });

            // ===================== CATEGORY CHART (Doughnut) =====================
            const catLabels = <?= json_encode(array_column($category_data, 'category')) ?>;
            const catValues = <?= json_encode(array_column($category_data, 'total')) ?>;
            const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe', '#11998e', '#38ef7d'];

            const ctx2 = document.getElementById('categoryChart').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: catLabels,
                    datasets: [{
                        data: catValues,
                        backgroundColor: colors.slice(0, catLabels.length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } }
                    },
                    cutout: '65%'
                }
            });

            // ===================== TOP PRODUCTS BAR CHART =====================
            const prodLabels = <?= json_encode(array_column($top_products, 'name')) ?>;
            const prodValues = <?= json_encode(array_column($top_products, 'total_sold')) ?>;
            const ctx3 = document.getElementById('topProductsChart').getContext('2d');
            new Chart(ctx3, {
                type: 'bar',
                data: {
                    labels: prodLabels,
                    datasets: [{
                        label: 'Units Sold',
                        data: prodValues,
                        backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe'],
                        borderRadius: 6,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => ctx.parsed.x + ' units' } }
                    },
                    scales: {
                        x: { beginAtZero: true, grid: { display: false } },
                        y: { grid: { display: false } }
                    }
                }
            });

            // ===================== FLOATING ACTION BUTTON TOGGLE =====================
            let actionsVisible = false;
            $('#mainFloatingBtn').on('click', function() {
                actionsVisible = !actionsVisible;
                $('.quick-actions').fadeToggle(200);
                $(this).find('i').toggleClass('fa-bolt fa-times');
            });
            // Hide quick actions on click outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.quick-actions, #mainFloatingBtn').length) {
                    if (actionsVisible) {
                        actionsVisible = false;
                        $('.quick-actions').fadeOut(200);
                        $('#mainFloatingBtn i').toggleClass('fa-bolt fa-times');
                    }
                }
            });

            // ===================== CONFIRM DELETE (demo) =====================
            window.confirmDelete = function(id) {
                if (confirm('Delete sale #' + id + '? This action cannot be undone.')) {
                    // AJAX call to delete_sale.php (you need to implement)
                    // For now, just alert
                    alert('Delete functionality not implemented. Add delete_sale.php endpoint.');
                }
            };
        });
    </script>
</body>
</html>
