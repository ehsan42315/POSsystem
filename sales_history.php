<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// CSRF token (for future POST actions, e.g., bulk delete)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Filter parameters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = "WHERE 1=1";
$params = [];
if (!empty($dateFrom)) {
    $where .= " AND DATE(s.sale_date) >= ?";
    $params[] = $dateFrom;
}
if (!empty($dateTo)) {
    $where .= " AND DATE(s.sale_date) <= ?";
    $params[] = $dateTo;
}

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM sales s $where");
$countStmt->execute($params);
$totalSales = $countStmt->fetchColumn();
$totalPages = ceil($totalSales / $limit);

// Sales data with items count
$query = "
    SELECT s.*, u.username, COUNT(si.id) as items_count
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN sale_items si ON s.id = si.sale_id
    $where
    GROUP BY s.id
    ORDER BY s.sale_date DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Summary stats (filtered)
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_sales,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_sale
    FROM sales s $where
");
$statsStmt->execute($params);
$stats = $statsStmt->fetch();

// Today's stats (unfiltered)
$todayStmt = $pdo->query("
    SELECT
        COUNT(*) as today_sales,
        COALESCE(SUM(total_amount), 0) as today_revenue
    FROM sales
    WHERE DATE(sale_date) = CURDATE()
");
$todayStats = $todayStmt->fetch();

// Revenue trend (last 7 days)
$trendLabels = [];
$trendValues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = date('M d', strtotime($d));
    $tStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(sale_date) = ?");
    $tStmt->execute([$d]);
    $trendValues[] = (float) $tStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Sales History - POS Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        .sidebar { background: #1a1a2e; min-height: 100vh; }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card.primary { border-color: #667eea; }
        .stat-card.success { border-color: #28a745; }
        .stat-card.info { border-color: #17a2b8; }
        .stat-card.warning { border-color: #ffc107; }
        .stat-card .stat-icon { font-size: 2rem; opacity: 0.3; }
        .filter-box { background: white; border-radius: 12px; padding: 1rem; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
        .table-card { border-radius: 16px; overflow: hidden; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); }
        .dataTables_wrapper .dataTables_filter input { border-radius: 20px; padding-left: 1rem; border: 1px solid #ced4da; }
        .page-link { border-radius: 8px !important; margin: 0 2px; }
        .btn-export { background: white; border: 1px solid #dee2e6; }
        @media (max-width: 768px) { .sidebar { min-height: auto; } }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-history me-2 text-primary"></i>Sales History</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="new_sale.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> New Sale</a>
                            <button class="btn btn-sm btn-outline-secondary" id="exportBtn"><i class="fas fa-download"></i> Export</button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card primary">
                            <div class="d-flex justify-content-between">
                                <div><small class="text-muted text-uppercase">Today's Sales</small><h3 class="mb-0"><?= $todayStats['today_sales'] ?></h3></div>
                                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card success">
                            <div class="d-flex justify-content-between">
                                <div><small class="text-muted text-uppercase">Today's Revenue</small><h3 class="mb-0">$<?= number_format($todayStats['today_revenue'], 2) ?></h3></div>
                                <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card info">
                            <div class="d-flex justify-content-between">
                                <div><small class="text-muted text-uppercase">Filtered Total</small><h3 class="mb-0"><?= $stats['total_sales'] ?></h3></div>
                                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card warning">
                            <div class="d-flex justify-content-between">
                                <div><small class="text-muted text-uppercase">Average Sale</small><h3 class="mb-0">$<?= number_format($stats['avg_sale'], 2) ?></h3></div>
                                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trend Chart -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-transparent d-flex justify-content-between">
                        <span><i class="fas fa-chart-line me-2 text-primary"></i>Revenue Trend (Last 7 Days)</span>
                        <span class="badge bg-secondary">$<?= number_format(array_sum($trendValues), 2) ?> total</span>
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" height="80"></canvas>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-box mb-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small text-muted">From Date</label>
                            <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">To Date</label>
                            <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Apply</button>
                        </div>
                        <div class="col-md-2">
                            <a href="sales_history.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times"></i> Clear</a>
                        </div>
                    </form>
                </div>

                <!-- Sales Table -->
                <div class="card table-card">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-table me-2 text-primary"></i>Sales Records</span>
                        <span class="badge bg-primary"><?= $totalSales ?> total</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="salesTable" class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sale ID</th>
                                        <th>Date & Time</th>
                                        <th>Cashier</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sales)): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-5"><i class="fas fa-receipt fa-3x mb-3 d-block"></i>No sales found.</td></tr>
                                    <?php else: foreach ($sales as $sale): ?>
                                        <tr>
                                            <td><strong>#<?= str_pad($sale['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                                            <td><?= date('M d, Y', strtotime($sale['sale_date'])) ?><br><small class="text-muted"><?= date('H:i:s', strtotime($sale['sale_date'])) ?></small></td>
                                            <td><?= htmlspecialchars($sale['username']) ?></td>
                                            <td><span class="badge bg-info"><?= $sale['items_count'] ?></span></td>
                                            <td><strong class="text-success">$<?= number_format($sale['total_amount'], 2) ?></strong></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view_sale.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                                    <a href="receipt.php?sale_id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-success" title="Receipt"><i class="fas fa-receipt"></i></a>
                                                    <button class="btn btn-sm btn-outline-info" onclick="window.open('receipt.php?sale_id=<?= $sale['id'] ?>', '_blank')" title="Print"><i class="fas fa-print"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav class="mt-3">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"><i class="fas fa-chevron-left"></i></a></li>
                                <?php endif; ?>
                                <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"><?= $i ?></a></li>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"><i class="fas fa-chevron-right"></i></a></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#salesTable').DataTable({
                paging: false,        // we use our own pagination
                info: false,
                searching: true,
                order: [[1, 'desc']],
                language: { search: "_INPUT_", searchPlaceholder: "Search sales..." },
                columnDefs: [
                    { orderable: false, targets: [5] }
                ]
            });

            // Trend Chart
            const ctx = document.getElementById('trendChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($trendLabels) ?>,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: <?= json_encode($trendValues) ?>,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102,126,234,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => '$' + ctx.parsed.y.toFixed(2) } }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => '$' + v.toFixed(0) } },
                        x: { grid: { display: false } }
                    }
                }
            });
        });

        // Export function (CSV)
        document.getElementById('exportBtn').addEventListener('click', function() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'export_sales.php?' + params.toString();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') { e.preventDefault(); window.location.href = 'new_sale.php'; }
            if (e.ctrlKey && e.key === 'e') { e.preventDefault(); document.getElementById('exportBtn').click(); }
        });

        // Set default dates to current month if not set
        document.addEventListener('DOMContentLoaded', function() {
            const from = document.querySelector('input[name="date_from"]');
            const to = document.querySelector('input[name="date_to"]');
            if (!from.value && !to.value) {
                const now = new Date();
                const first = new Date(now.getFullYear(), now.getMonth(), 1);
                from.value = first.toISOString().split('T')[0];
                to.value = now.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>
