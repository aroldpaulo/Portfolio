<?php
// cashier/completed.php - Completed Orders Page (USING STORED PROCEDURES)
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'cashier' && $_SESSION['role'] != 'admin')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$completed_orders = [];
$pending_count = 0;
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
    
    // ========== GET COMPLETED ORDERS USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_get_completed_orders()");
    $stmt->execute();
    $completed_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // ========== GET PENDING COUNT FOR BADGE USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_get_pending_count()");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_count = $result['pending_count'] ?? 0;
    $stmt->nextRowset();
    
} catch(PDOException $e) {
    error_log("Database error in completed orders: " . $e->getMessage());
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
    <title>Completed Orders | CHIBUGAN Cashier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/cashier.css">
            <link rel="icon" type="image/x-icon" href="../logo.jpg">

</head>
<body>

<div class="toast-notification"></div>

<!-- TOP NAVIGATION BAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-lg sticky-top z-index-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand text-success fs-4 fw-bold" href="dashboard.php">
            <i class="bi bi-shop me-2"></i>CHIBUGAN Cashier Panel
        </a>
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

<!-- CASHIER NAVIGATION BAR -->
<div class="bg-dark shadow-sm sticky-top admin-nav sticky-nav-offset">
    <div class="container-fluid px-4 d-flex flex-wrap">
        <?php if ($user_role == 'admin'): ?>
            <a href="../admin/dashboard.php" class="btn btn-danger py-3 px-4 fw-bolder border-0 text-white"><i class="bi bi-gear-fill me-1"></i> Admin Panel</a>
        <?php endif; ?>
        <a href="dashboard.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-bar-chart-fill me-1"></i> Dashboard</a>
        <a href="pending.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-bell-fill me-1"></i> Pending Orders <span class="badge bg-warning ms-1 text-dark"><?php echo $pending_count; ?></span></a>
        <a href="completed.php" class="btn btn-success py-3 px-4 fw-bold border-0"><i class="bi bi-check2-circle me-1"></i> Completed Orders</a>
        <a href="customer_wallet.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-credit-card-fill me-1"></i> Customer Wallet</a>
        <a href="settings.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-gear me-1"></i> Settings</a>
    </div>
</nav>

<!-- MAIN CONTENT -->
<div class="container-fluid px-4 mt-4 pb-5">
    <h1 class="fs-2 fw-bold text-dark mb-4"><i class="bi bi-check2-circle me-2 text-success"></i> Completed Orders</h1>
    <p class="text-secondary mb-4">List of all successfully completed orders.</p>
    
    <div class="card card-pos bg-white p-4">
        <?php if (empty($completed_orders)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-2">No completed orders yet.</p>
                <p class="text-muted small">Completed orders will appear here once orders are marked as completed.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Order Code</th>
                            <th>Customer</th>
                            <th>Order Type</th>
                            <th>Total Amount</th>
                            <th>Completed Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completed_orders as $order): ?>
                        <tr onclick="window.location.href='order.php?id=<?php echo $order['id']; ?>'" style="cursor: pointer;">
                            <td class="fw-bold"><?php echo htmlspecialchars($order['order_code']); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($order['order_type']); ?></span></td>
                            <td class="fw-bold text-success">₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($order['updated_at'] ?? $order['created_at'])); ?></td>
                            <td><i class="bi bi-eye text-primary"></i> View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- FOOTER -->
<footer class="text-center py-5">
    <div class="container">
        <p class="mb-0">&copy; 2025 CHIBUGAN POS System. All Rights Reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showToast(message, type = 'success') {
        const container = document.querySelector('.toast-notification');
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show shadow`;
        toast.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
</script>
</body>
</html>