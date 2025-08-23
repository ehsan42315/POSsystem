<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get filter parameters
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query with filters
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($dateFrom)) {
    $whereClause .= " AND DATE(s.sale_date) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClause .= " AND DATE(s.sale_date) <= ?";
    $params[] = $dateTo;
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) FROM sales s $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalSales = $stmt->fetchColumn();
$totalPages = ceil($totalSales / $limit);

// Get sales with pagination
$query = "
    SELECT s.*, u.username, COUNT(si.id) as items_count
    FROM sales s 
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN sale_items si ON s.id = si.sale_id
    $whereClause
    GROUP BY s.id
    ORDER BY s.sale_date DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Get summary statistics
$statsQuery = "SELECT 
    COUNT(*) as total_sales,
    COALESCE(SUM(total_amount), 0) as total_revenue,
    COALESCE(AVG(total_amount), 0) as avg_sale
    FROM sales s $whereClause";
$stmt = $pdo->prepare($statsQuery);
$stmt->execute($params);
$stats = $stmt->fetch();

// Get today's stats
$todayQuery = "SELECT 
    COUNT(*) as today_sales,
    COALESCE(SUM(total_amount), 0) as today_revenue
    FROM sales 
    WHERE DATE(sale_date) = CURDATE()";
$stmt = $pdo->query($todayQuery);
$todayStats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - POS System</title>
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
                    <h1 class="h2">Sales History</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="new_sale.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> New Sale
                            </a>
                            <button class="btn btn-sm btn-outline-secondary" onclick="exportData()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
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
                                            <?php echo $todayStats['today_sales']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
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
                                            $<?php echo number_format($todayStats['today_revenue'], 2); ?>
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
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Sales (Filtered)
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total_sales']; ?>
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
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Average Sale
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            $<?php echo number_format($stats['avg_sale'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($dateFrom); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($dateTo); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <?php if (!empty($dateFrom) || !empty($dateTo)): ?>
                        <div class="mt-3">
                            <a href="sales_history.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sales Table -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Sales Records
                            <span class="badge bg-primary"><?php echo $totalSales; ?> total</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Sale ID</th>
                                        <th>Date & Time</th>
                                        <th>Cashier</th>
                                        <th>Items</th>
                                        <th>Total Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sales)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="fas fa-receipt fa-3x mb-3"></i><br>
                                                No sales found for the selected criteria.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($sale['sale_date'])); ?><br>
                                                <small class="text-muted"><?php echo date('H:i:s', strtotime($sale['sale_date'])); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($sale['username']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $sale['items_count']; ?> items</span>
                                            </td>
                                            <td>
                                                <strong class="text-success">$<?php echo number_format($sale['total_amount'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view_sale.php?id=<?php echo $sale['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="receipt.php?sale_id=<?php echo $sale['id']; ?>" 
                                                       class="btn btn-sm btn-outline-success" title="View Receipt">
                                                        <i class="fas fa-receipt"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            onclick="printReceipt(<?php echo $sale['id']; ?>)" title="Print">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Sales pagination" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($dateFrom) ? '&date_from=' . $dateFrom : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . $dateTo : ''; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($dateFrom) ? '&date_from=' . $dateFrom : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . $dateTo : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($dateFrom) ? '&date_from=' . $dateFrom : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . $dateTo : ''; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printReceipt(saleId) {
            window.open('receipt.php?sale_id=' + saleId, '_blank');
        }

        function exportData() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'export_sales.php?' + params.toString();
        }

        // Set default date range to current month
        document.addEventListener('DOMContentLoaded', function() {
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            
            if (!dateFrom.value && !dateTo.value) {
                const today = new Date();
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                
                dateFrom.value = firstDay.toISOString().split('T')[0];
                dateTo.value = today.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>