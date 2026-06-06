<?php
// cashier/pending.php - USING STORED PROCEDURES ONLY (NO RAW SQL)
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'cashier' && $_SESSION['role'] != 'admin')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$pending_orders = [];
$pending_badge_count = 0;
$user_name = $_SESSION['full_name'] ?? 'Cashier';
$user_email = $_SESSION['email'] ?? '';
$profile_picture = $_SESSION['profile_picture'] ?? null;
$user_role = $_SESSION['role'] ?? 'cashier';

try {
    // ========== GET USER PROFILE USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_get_user_profile(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    if ($user_data) {
        $user_name = $user_data['full_name'];
        $user_email = $user_data['email'];
        $profile_picture = $user_data['profile_picture'];
        $_SESSION['full_name'] = $user_name;
        $_SESSION['email'] = $user_email;
        $_SESSION['profile_picture'] = $profile_picture;
    }
    
    // ========== GET PENDING ORDERS USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_get_recent_orders(100)");
    $stmt->execute();
    $pending_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // ========== GET PENDING COUNT FOR BADGE USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_get_pending_count()");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_badge_count = $result['pending_count'] ?? 0;
    $stmt->nextRowset();
    
} catch(PDOException $e) {
    error_log("Database error in pending orders: " . $e->getMessage());
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
    <title>Pending Orders | CHIBUGAN Cashier</title>
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
    </style>
</head>
<body>

<div class="wrapper">
    <div class="toast-notification"></div>

    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-lg sticky-top z-index-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand text-success fs-4 fw-bold" href="dashboard.php"><i class="bi bi-shop me-2"></i>CHIBUGAN Cashier Panel</a>
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle nav-link-profile" href="#" data-bs-toggle="dropdown">
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

    <div class="bg-dark shadow-sm sticky-top admin-nav sticky-nav-offset">
        <div class="container-fluid px-4 d-flex flex-wrap">
            <?php if ($user_role == 'admin'): ?>
                <a href="../admin/dashboard.php" class="btn btn-danger py-3 px-4 fw-bolder border-0 text-white"><i class="bi bi-gear-fill me-1"></i> Admin Panel</a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-bar-chart-fill me-1"></i> Dashboard</a>
            <a href="pending.php" class="btn btn-success py-3 px-4 fw-bold border-0"><i class="bi bi-bell-fill me-1"></i> Pending Orders <span class="badge bg-warning ms-1 text-dark"><?php echo $pending_badge_count; ?></span></a>
            <a href="completed.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-check2-circle me-1"></i> Completed Orders</a>
            <a href="customer_wallet.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-credit-card-fill me-1"></i> Customer Wallet</a>
            <a href="settings.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-gear me-1"></i> Settings</a>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid px-4 mt-4 pb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="fs-2 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-warning"></i> Pending Orders Queue</h1>
                <button class="btn btn-outline-secondary" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            </div>
            
            <?php if (empty($pending_orders)): ?>
                <div class="card card-pos bg-white p-5 text-center">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <h4 class="text-muted mt-3">No Pending Orders</h4>
                    <p class="text-muted">All orders have been processed.</p>
                </div>
            <?php else: ?>
                <div class="card card-pos bg-white p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Order Code</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Order Type</th>
                                    <th>Total Amount</th>
                                    <th>Time Placed</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_orders as $order): 
                                    $status_class = '';
                                    switch ($order['status']) {
                                        case 'New': $status_class = 'status-New'; break;
                                        case 'Preparing': $status_class = 'status-Preparing'; break;
                                        case 'Ready': $status_class = 'status-Ready'; break;
                                        default: $status_class = 'status-New';
                                    }
                                    $type_class = '';
                                    switch ($order['order_type']) {
                                        case 'Take-Out': $type_class = 'order-type-Take-Out'; break;
                                        case 'Dine-In': $type_class = 'order-type-Dine-In'; break;
                                        case 'Delivery': $type_class = 'order-type-Delivery'; break;
                                        default: $type_class = 'order-type-Take-Out';
                                    }
                                    $next_status = '';
                                    $next_status_text = '';
                                    if ($order['status'] == 'New') {
                                        $next_status = 'Preparing';
                                        $next_status_text = 'Start Preparing';
                                    } elseif ($order['status'] == 'Preparing') {
                                        $next_status = 'Ready';
                                        $next_status_text = 'Mark Ready';
                                    } else {
                                        $next_status = 'Completed';
                                        $next_status_text = 'Complete';
                                    }
                                ?>
                                <tr data-order-id="<?php echo $order['id']; ?>">
                                    <td class="fw-bold"><?php echo htmlspecialchars($order['order_code']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $order['status']; ?></span></td>
                                    <td><span class="order-type-badge <?php echo $type_class; ?>"><?php echo $order['order_type']; ?></span></td>
                                    <td class="fw-bold text-success">₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><?php echo date('h:i A', strtotime($order['created_at'])); ?><br><small class="text-muted"><?php echo date('M d', strtotime($order['created_at'])); ?></small></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning update-status-btn" data-order-id="<?php echo $order['id']; ?>" data-next-status="<?php echo $next_status; ?>">
                                            <i class="bi bi-arrow-right-circle"></i> <?php echo $next_status_text; ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
    
    function updateOrderStatus(orderId, newStatus) {
        if (!confirm('Update this order to "' + newStatus + '"?')) return;
        
        fetch('../api/update_order_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, status: newStatus })
        })
        .then(function(res) { return res.json(); })
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
    
    var statusBtns = document.querySelectorAll('.update-status-btn');
    for (var i = 0; i < statusBtns.length; i++) {
        statusBtns[i].addEventListener('click', function(e) {
            e.stopPropagation();
            updateOrderStatus(this.dataset.orderId, this.dataset.nextStatus);
        });
    }
    
    var tableRows = document.querySelectorAll('tbody tr');
    for (var i = 0; i < tableRows.length; i++) {
        tableRows[i].addEventListener('click', function(e) {
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
            var orderId = this.dataset.orderId;
            if (orderId) window.location.href = 'order.php?id=' + orderId;
        });
    }
</script>
</body>
</html>