<?php
/**
 * ============================================
 * SIDEBAR – Main Navigation (Dark Theme)
 * Quick actions | Active state | Icons
 * ============================================
 */

// Determine current page
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <!-- Main Navigation -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'index.php' ? 'active' : '' ?>" href="index.php">
                    <i class="fas fa-tachometer-alt me-3"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'new_sale.php' ? 'active' : '' ?>" href="new_sale.php">
                    <i class="fas fa-cart-plus me-3"></i>
                    <span>New Sale</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'products.php' ? 'active' : '' ?>" href="products.php">
                    <i class="fas fa-box me-3"></i>
                    <span>Products</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'sales_history.php' ? 'active' : '' ?>" href="sales_history.php">
                    <i class="fas fa-history me-3"></i>
                    <span>Sales History</span>
                </a>
            </li>
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage == 'users.php' ? 'active' : '' ?>" href="users.php">
                    <i class="fas fa-users-cog me-3"></i>
                    <span>User Management</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <!-- Divider -->
        <hr class="my-3 opacity-25">

        <!-- Quick Actions -->
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-3 mb-2 text-uppercase small text-muted">
            <span>Quick Actions</span>
            <i class="fas fa-bolt text-primary"></i>
        </h6>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="new_sale.php">
                    <i class="fas fa-shopping-cart me-3"></i>
                    <span>Quick Sale</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="products.php?action=add">
                    <i class="fas fa-plus-circle me-3"></i>
                    <span>Add Product</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="sales_history.php">
                    <i class="fas fa-chart-line me-3"></i>
                    <span>View Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="receipt.php?sale_id=latest">
                    <i class="fas fa-receipt me-3"></i>
                    <span>Last Receipt</span>
                </a>
            </li>
        </ul>

        <!-- Footer (optional) -->
        <div class="sidebar-footer text-center small text-muted px-3 mt-4">
            <i class="fas fa-shield-alt me-1"></i> v2.0
            <span class="d-block mt-1">&copy; <?= date('Y') ?> POS Pro</span>
        </div>
    </div>
</nav>

<style>
    /* ===== Sidebar Styles (dark theme) ===== */
    .sidebar {
        position: fixed;
        top: 56px;
        bottom: 0;
        left: 0;
        z-index: 100;
        padding: 48px 0 0;
        background: #1a1a2e !important;
        box-shadow: 2px 0 12px rgba(0,0,0,0.3);
        overflow-y: auto;
        transition: transform 0.3s ease;
        width: 260px;
    }
    .sidebar .nav-link {
        font-weight: 500;
        color: rgba(255,255,255,0.7);
        padding: 0.7rem 1.2rem;
        margin: 0.2rem 0.5rem;
        border-radius: 10px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        white-space: nowrap;
    }
    .sidebar .nav-link i {
        width: 20px;
        text-align: center;
        font-size: 1.1rem;
        opacity: 0.7;
    }
    .sidebar .nav-link:hover {
        background: rgba(255,255,255,0.08);
        color: #fff;
        transform: translateX(4px);
    }
    .sidebar .nav-link.active {
        background: rgba(102, 126, 234, 0.2);
        color: #fff;
        border-right: 3px solid #667eea;
    }
    .sidebar .nav-link.active i {
        color: #667eea;
        opacity: 1;
    }
    .sidebar-heading {
        color: rgba(255,255,255,0.4);
        font-size: 0.7rem;
        letter-spacing: 1px;
    }
    .sidebar hr {
        border-color: rgba(255,255,255,0.08);
        margin: 1rem 0.5rem;
    }
    .sidebar-footer {
        border-top: 1px solid rgba(255,255,255,0.06);
        padding-top: 0.75rem;
        margin-top: 1.5rem;
        font-size: 0.7rem;
        color: rgba(255,255,255,0.3);
    }
    /* Mobile collapse */
    @media (max-width: 767.98px) {
        .sidebar {
            transform: translateX(-100%);
            width: 280px;
        }
        .sidebar.show {
            transform: translateX(0);
        }
        /* Toggle button for mobile should be added in navbar (we'll use toggler) */
    }
</style>

<!-- Mobile toggle script (optional, if you want to open/close sidebar on mobile) -->
<script>
    // If you want to toggle sidebar on mobile via a button (e.g., in navbar)
    // You can add a custom button and call this:
    function toggleSidebar() {
        document.getElementById('sidebarMenu').classList.toggle('show');
    }
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebarMenu');
        const toggler = document.querySelector('.navbar-toggler');
        if (window.innerWidth < 768 && sidebar.classList.contains('show')) {
            if (!sidebar.contains(e.target) && !toggler.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
    });
</script>
