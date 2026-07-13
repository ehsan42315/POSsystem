<?php
/**
 * ============================================
 * NAVBAR – Enterprise POS Header
 * Live clock | Notifications | User menu
 * ============================================
 */

// Get low stock count for notification badge (optional)
$lowStockCount = 0;
if (isset($pdo)) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE quantity < 10");
    $lowStockCount = $stmt->fetchColumn();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm">
    <div class="container-fluid px-3">
        <!-- Brand -->
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="fas fa-cash-register me-2 text-primary"></i>
            POS Pro
        </a>

        <!-- Toggler -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Collapsible Content -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- Left: Navigation Links (hidden on mobile, sidebar handles it) -->
            <ul class="navbar-nav me-auto d-none d-lg-flex">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'new_sale.php' ? 'active' : '' ?>" href="new_sale.php">
                        <i class="fas fa-plus-circle me-1"></i>New Sale
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>" href="products.php">
                        <i class="fas fa-box me-1"></i>Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'sales_history.php' ? 'active' : '' ?>" href="sales_history.php">
                        <i class="fas fa-history me-1"></i>History
                    </a>
                </li>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>" href="users.php">
                        <i class="fas fa-users me-1"></i>Users
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Right: Live Clock, Notifications, User -->
            <ul class="navbar-nav ms-auto align-items-center">
                <!-- Live Clock -->
                <li class="nav-item d-none d-md-block">
                    <span class="nav-link text-light-50" id="liveClock">
                        <i class="far fa-clock me-1"></i>
                        <span id="clockDisplay">--:--:--</span>
                    </span>
                </li>

                <!-- Notification Bell -->
                <li class="nav-item">
                    <a class="nav-link position-relative" href="products.php?low=1" title="Low Stock Alerts">
                        <i class="fas fa-bell fa-lg"></i>
                        <?php if ($lowStockCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $lowStockCount ?>
                            <span class="visually-hidden">low stock alerts</span>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- User Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <span class="avatar-placeholder rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;font-size:14px;font-weight:600;">
                            <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                        </span>
                        <span class="d-none d-sm-inline"><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <span class="badge bg-secondary ms-1 d-none d-sm-inline"><?= ucfirst($_SESSION['role']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="userDropdown">
                        <li><span class="dropdown-item-text text-muted small">Signed in as <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit me-2"></i>Profile</a></li>
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                        <li><a class="dropdown-item" href="users.php"><i class="fas fa-users-cog me-2"></i>Manage Users</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Live Clock Script -->
<script>
    function updateClock() {
        const now = new Date();
        const options = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        document.getElementById('clockDisplay').textContent = now.toLocaleTimeString('en-US', options);
    }
    updateClock();
    setInterval(updateClock, 1000);
</script>

<style>
    /* Navbar overrides – uses theme variables from style.css */
    .navbar {
        background: #1a1a2e !important; /* Matches sidebar */
        border-bottom: 2px solid rgba(102, 126, 234, 0.3);
        padding: 0.6rem 1rem;
    }
    .navbar .nav-link {
        color: rgba(255,255,255,0.75) !important;
        font-weight: 500;
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        transition: all 0.2s;
    }
    .navbar .nav-link:hover,
    .navbar .nav-link.active {
        color: #fff !important;
        background: rgba(255,255,255,0.08);
    }
    .navbar .nav-link i {
        font-size: 1.1rem;
        margin-right: 4px;
    }
    .dropdown-menu {
        border-radius: 12px;
        padding: 0.5rem 0;
    }
    .dropdown-item {
        padding: 0.5rem 1.2rem;
        transition: background 0.15s;
    }
    .dropdown-item:hover {
        background: rgba(102, 126, 234, 0.08);
    }
    .avatar-placeholder {
        background: linear-gradient(135deg, #667eea, #764ba2) !important;
    }
    #liveClock {
        color: rgba(255,255,255,0.6);
        font-size: 0.9rem;
        letter-spacing: 0.5px;
    }
    /* Notification badge pulse */
    .badge.bg-danger {
        animation: pulse-bell 2s infinite;
    }
    @keyframes pulse-bell {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
</style>
