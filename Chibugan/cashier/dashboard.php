<?php
// cashier/dashboard.php - FIXED VERSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'cashier' && $_SESSION['role'] != 'admin')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$today_sales = 0;
$pending_orders = 0;
$completed_today = 0;
$total_customers = 0;
$recent_orders = [];
$low_stock_items = [];
$user_name = $_SESSION['full_name'] ?? 'Cashier User';
$user_email = $_SESSION['email'] ?? '';
$profile_picture = $_SESSION['profile_picture'] ?? null;
$pending_badge_count = 0;
$user_role = $_SESSION['role'] ?? 'cashier';

try {
    // ========== GET DASHBOARD STATISTICS ==========
    $stmt = $pdo->prepare("CALL sp_get_dashboard_stats(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        $today_sales = $stats['today_sales'] ?? 0;
        $pending_orders = $stats['pending_orders'] ?? 0;
        $completed_today = $stats['completed_today'] ?? 0;
        $total_customers = $stats['total_customers'] ?? 0;
    }
    
    // Move to second result set (user profile)
    $stmt->nextRowset();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_data) {
        $user_name = $user_data['full_name'];
        $user_email = $user_data['email'];
        $profile_picture = $user_data['profile_picture'];
        $_SESSION['full_name'] = $user_name;
        $_SESSION['email'] = $user_email;
        $_SESSION['profile_picture'] = $profile_picture;
    }
    $stmt->nextRowset();
    
    // ========== GET RECENT ORDERS (Pending) ==========
    $stmt = $pdo->prepare("CALL sp_get_recent_orders(10)");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // ========== GET LOW STOCK ITEMS ==========
    $stmt = $pdo->prepare("CALL sp_get_low_stock(5)");
    $stmt->execute();
    $low_stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // ========== GET PENDING COUNT FOR BADGE ==========
    $stmt = $pdo->prepare("CALL sp_get_pending_count()");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_badge_count = $result['pending_count'] ?? 0;
    $stmt->nextRowset();
    
    // DEBUG: Uncomment to see what's being returned
    // error_log("pending_badge_count: " . $pending_badge_count);
    // error_log("recent_orders count: " . count($recent_orders));
    
} catch(PDOException $e) {
    error_log("Database error in dashboard: " . $e->getMessage());
}

// Prepare avatar HTML
$nav_avatar_html = '';
$dropdown_avatar_html = '';

if ($profile_picture && file_exists('../' . $profile_picture)) {
    $nav_avatar_html = '<img src="../' . $profile_picture . '" class="nav-avatar-img" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">';
    $dropdown_avatar_html = '<img src="../' . $profile_picture . '" class="dropdown-avatar-img" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover;">';
} else {
    $nav_avatar_html = '<div class="nav-avatar" style="width: 32px; height: 32px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white;">' . strtoupper(substr($user_name, 0, 1)) . '</div>';
    $dropdown_avatar_html = '<div class="dropdown-avatar" style="width: 70px; height: 70px; background: rgba(255,255,255,0.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 2rem; font-weight: bold; border: 2px solid white; color: white;">' . strtoupper(substr($user_name, 0, 1)) . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard | CHIBUGAN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/cashier.css">
    <link rel="icon" type="image/x-icon" href="../logo.jpg">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        .wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .content {
            flex: 1;
        }
        footer {
            margin-top: auto;
        }
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-left: 4px solid #198754;
            border-radius: 0.75rem;
            padding: 1.25rem;
        }
        .stat-card h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        .stat-card p {
            color: #6c757d;
            margin-bottom: 0;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-New { background-color: #ffc107; color: #856404; }
        .status-Preparing { background-color: #17a2b8; color: white; }
        .status-Ready { background-color: #28a745; color: white; }
        .order-type-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
        }
        .order-type-Take-Out { background-color: #6c757d; color: white; }
        .order-type-Dine-In { background-color: #0d6efd; color: white; }
        .order-type-Delivery { background-color: #fd7e14; color: white; }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="toast-notification"></div>

    <!-- TOP NAVIGATION BAR -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-lg sticky-top z-index-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand text-success fs-4 fw-bold" href="dashboard.php">
                <i class="bi bi-shop me-2"></i>CHIBUGAN Cashier Panel
            </a>
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle nav-link-profile d-flex align-items-center" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo $nav_avatar_html; ?>
                        <span class="ms-2 fw-semibold text-dark"><?php echo htmlspecialchars($user_name); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                        <li><div class="dropdown-profile-header"><?php echo $dropdown_avatar_html; ?><div class="dropdown-name"><?php echo htmlspecialchars($user_name); ?></div><div class="dropdown-email"><?php echo htmlspecialchars($user_email); ?></div></div></li>
                        <li><a class="dropdown-item dropdown-item-custom" href="profile.php"><i class="bi bi-person-circle"></i> My Profile</a></li>
                        <li><a class="dropdown-item dropdown-item-custom" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                        <li><hr class="dropdown-divider-custom"></li>
                        <li><a class="dropdown-item dropdown-item-custom text-danger" href="../api/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        <li><div class="dropdown-footer"><i class="bi bi-shield-check"></i> Secure Connection</div></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- CASHIER NAVIGATION BAR -->
    <div class="bg-dark shadow-sm sticky-top admin-nav sticky-nav-offset">
        <div class="container-fluid px-4 d-flex flex-wrap">
            <?php if ($user_role == 'admin'): ?>
                <a href="../admin/dashboard.php" class="btn btn-danger py-3 px-4 fw-bolder border-0 text-white"><i class="bi bi-gear-fill me-1"></i> Admin Panel</a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-success py-3 px-4 fw-bold border-0"><i class="bi bi-bar-chart-fill me-1"></i> Dashboard</a>
            <a href="pending.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-bell-fill me-1"></i> Pending Orders <span class="badge bg-warning ms-1 text-dark"><?php echo $pending_badge_count; ?></span></a>
            <a href="completed.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-check2-circle me-1"></i> Completed Orders</a>
            <a href="customer_wallet.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-credit-card-fill me-1"></i> Customer Wallet</a>
            <a href="settings.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-gear me-1"></i> Settings</a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="content">
        <div class="container-fluid px-4 mt-4">
            
            <!-- Welcome Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card card-pos bg-success text-white p-4">
                        <h2 class="fw-bold mb-2">Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h2>
                        <p class="mb-0 opacity-75">Here's what's happening with your operations today.</p>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card stat-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Today's Sales</p>
                                <h2 class="text-success mb-0">₱<?php echo number_format($today_sales, 2); ?></h2>
                            </div>
                            <i class="bi bi-cash-stack fs-1 text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="card stat-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Pending Orders</p>
                                <h2 class="text-warning mb-0"><?php echo $pending_orders; ?></h2>
                            </div>
                            <i class="bi bi-clock-history fs-1 text-warning opacity-50"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="card stat-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Completed Today</p>
                                <h2 class="text-primary mb-0"><?php echo $completed_today; ?></h2>
                            </div>
                            <i class="bi bi-check-circle fs-1 text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="card stat-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Total Customers</p>
                                <h2 class="text-info mb-0"><?php echo $total_customers; ?></h2>
                            </div>
                            <i class="bi bi-people fs-1 text-info opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders and Low Stock -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card card-pos bg-white p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="fw-bold text-dark mb-0"><i class="bi bi-bell-fill text-success me-2"></i> Recent Orders</h4>
                            <a href="pending.php" class="btn btn-sm btn-outline-success">View All</a>
                        </div>
                        
                        <?php if (empty($recent_orders)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No pending orders at the moment.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Status</th>
                                            <th>Type</th>
                                            <th>Total</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): 
                                            $status_class = '';
                                            switch ($order['status']) {
                                                case 'New': $status_class = 'status-New'; break;
                                                case 'Preparing': $status_class = 'status-Preparing'; break;
                                                case 'Ready': $status_class = 'status-Ready'; break;
                                            }
                                            $type_class = '';
                                            switch ($order['order_type']) {
                                                case 'Take-Out': $type_class = 'order-type-Take-Out'; break;
                                                case 'Dine-In': $type_class = 'order-type-Dine-In'; break;
                                                case 'Delivery': $type_class = 'order-type-Delivery'; break;
                                            }
                                        ?>
                                        <tr data-order-id="<?php echo $order['id']; ?>" style="cursor: pointer;">
                                            <td class="fw-bold"><?php echo htmlspecialchars($order['order_code']); ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $order['status']; ?></span></td>
                                            <td><span class="order-type-badge <?php echo $type_class; ?>"><?php echo $order['order_type']; ?></span></td>
                                            <td>₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <?php if ($order['status'] == 'New'): ?>
                                                <button class="btn btn-sm btn-warning start-order-btn" data-order-id="<?php echo $order['id']; ?>" data-status="Preparing" onclick="event.stopPropagation(); updateOrderStatus(this)"><i class="bi bi-play-fill"></i> Start</button>
                                                <?php elseif ($order['status'] == 'Preparing'): ?>
                                                <button class="btn btn-sm btn-info ready-order-btn" data-order-id="<?php echo $order['id']; ?>" data-status="Ready" onclick="event.stopPropagation(); updateOrderStatus(this)"><i class="bi bi-check"></i> Ready</button>
                                                <?php elseif ($order['status'] == 'Ready'): ?>
                                                <button class="btn btn-sm btn-success complete-order-btn" data-order-id="<?php echo $order['id']; ?>" data-status="Completed" onclick="event.stopPropagation(); updateOrderStatus(this)"><i class="bi bi-check-circle"></i> Complete</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card card-pos bg-white p-4">
                        <h4 class="fw-bold text-dark mb-3"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Low Stock Alert</h4>
                        
                        <?php if (empty($low_stock_items)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-check-circle fs-1 text-success"></i>
                                <p class="text-success mt-2">All items are well-stocked!</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($low_stock_items as $item): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($item['category']); ?></small>
                                    </div>
                                    <span class="badge bg-warning text-dark"><?php echo $item['quantity']; ?> left</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-3">
                                <a href="../admin/menu_management.php" class="btn btn-sm btn-outline-warning w-100"><i class="bi bi-box-seam me-1"></i> Manage Inventory</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="text-center py-3">
        <div class="container">
            <p class="mb-0">&copy; 2025 CHIBUGAN POS System. All Rights Reserved.</p>
        </div>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showToast(message, type) {
        if (typeof type === 'undefined') type = 'success';
        const container = document.querySelector('.toast-notification');
        const toast = document.createElement('div');
        toast.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' alert-dismissible fade show shadow';
        toast.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        container.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 3000);
    }
    
    function updateOrderStatus(button) {
        const orderId = button.getAttribute('data-order-id');
        const newStatus = button.getAttribute('data-status');
        
        var confirmMessage = 'Update this order to "' + newStatus + '"?';
        if (newStatus === 'Completed') {
            confirmMessage = 'Mark order as COMPLETED? This will finalize the order.';
        }
        
        if (!confirm(confirmMessage)) return;
        
        fetch('../api/update_order_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, status: newStatus })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast(data.message);
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                showToast(data.message, 'danger');
            }
        })
        .catch(function(error) { showToast('Error updating order status', 'danger'); });
    }
    
    var rows = document.querySelectorAll('tbody tr');
    for (var i = 0; i < rows.length; i++) {
        rows[i].addEventListener('click', function(e) {
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
            var orderId = this.dataset.orderId;
            if (orderId) window.location.href = 'order.php?id=' + orderId;
        });
    }
</script>
</body>
</html>