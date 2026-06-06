<?php
// cashier/order_details.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'cashier' && $_SESSION['role'] != 'admin')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

try {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name as customer_name, u.email as customer_email 
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header('Location: dashboard.php');
        exit();
    }
    
    // Get order items
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: dashboard.php');
    exit();
}

$user_name = $_SESSION['full_name'] ?? 'Cashier';
$user_email = $_SESSION['email'] ?? '';
$profile_picture = $_SESSION['profile_picture'] ?? null;
$user_role = $_SESSION['role'] ?? 'cashier';

// Get pending count for badge
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_count FROM orders WHERE status IN ('New', 'Preparing', 'Ready')");
    $stmt->execute();
    $result = $stmt->fetch();
    $pending_count = $result['pending_count'] ?? 0;
} catch(PDOException $e) {
    $pending_count = 0;
}

// Prepare avatar HTML
$nav_avatar_html = '';
$dropdown_avatar_html = '';

if ($profile_picture && file_exists('../' . $profile_picture)) {
    $nav_avatar_html = '<img src="../' . $profile_picture . '" class="nav-avatar-img" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">';
    $dropdown_avatar_html = '<img src="../' . $profile_picture . '" class="dropdown-avatar-img" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover;">';
} else {
    $nav_avatar_html = '<div class="nav-avatar">' . strtoupper(substr($user_name, 0, 1)) . '</div>';
    $dropdown_avatar_html = '<div class="dropdown-avatar">' . strtoupper(substr($user_name, 0, 1)) . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details | CHIBUGAN Cashier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f5f6fa; font-family: 'Segoe UI', system-ui; min-height: 100vh; }
        .card-pos { border: none; border-radius: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .admin-nav { background-color: #212529; }
        .admin-nav .btn { transition: all 0.2s ease; }
        .admin-nav .btn:hover { opacity: 1 !important; background-color: rgba(255,255,255,0.1); }
        .sticky-nav-offset { top: 56px; }
        .z-index-top { z-index: 1030; }
        footer { background-color: #1e1e2f; color: #ddd; padding: 1rem 0; margin-top: 2rem; }
        .profile-dropdown { padding: 0; min-width: 280px; border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); margin-top: 10px; }
        .dropdown-profile-header { background: linear-gradient(135deg, #198754, #0d6efd); padding: 20px; border-radius: 16px 16px 0 0; text-align: center; color: white; }
        .dropdown-name { font-size: 1rem; font-weight: 600; margin-bottom: 4px; color: white !important; }
        .dropdown-email { font-size: 0.75rem; opacity: 0.85; color: rgba(255,255,255,0.9) !important; }
        .dropdown-item-custom { padding: 10px 20px; transition: all 0.2s; color: #1a1a2e !important; }
        .dropdown-item-custom:hover { background-color: #f8f9fa; padding-left: 28px; }
        .dropdown-item-custom i { width: 24px; margin-right: 10px; color: #198754; }
        .dropdown-item-custom.text-danger i { color: #dc3545; }
        .dropdown-footer { padding: 10px 20px; background-color: #f8f9fa; border-radius: 0 0 16px 16px; font-size: 0.7rem; color: #6c757d; text-align: center; }
        .nav-link-profile { display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .status-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 20px; font-weight: 600; }
        .status-New { background-color: #ffc107; color: #856404; }
        .status-Preparing { background-color: #17a2b8; color: white; }
        .status-Ready { background-color: #28a745; color: white; }
        .status-Completed { background-color: #28a745; color: white; }
        .order-type-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 20px; font-weight: 600; }
        .order-type-Take-Out { background-color: #6c757d; color: white; }
        .order-type-Dine-In { background-color: #0d6efd; color: white; }
        @media (max-width: 768px) { .admin-nav .btn { font-size: 0.8rem; padding: 0.75rem 0.75rem !important; } }
    </style>
</head>
<body>

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
        <a href="pending.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-bell-fill me-1"></i> Pending Orders <span class="badge bg-warning ms-1 text-dark"><?php echo $pending_count; ?></span></a>
        <a href="completed.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-check2-circle me-1"></i> Completed Orders</a>
        <a href="customer_wallet.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-credit-card-fill me-1"></i> Customer Wallet</a>
        <a href="settings.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-gear me-1"></i> Settings</a>
    </div>
</div>

<div class="container-fluid px-4 mt-4 pb-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card card-pos bg-white p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-dark mb-0">Order Details</h3>
                    <a href="completed.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p><strong>Order Code:</strong> <?php echo htmlspecialchars($order['order_code']); ?></p>
                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                        <p><strong>Order Type:</strong> <span class="order-type-badge <?php echo $order['order_type'] == 'Take-Out' ? 'order-type-Take-Out' : 'order-type-Dine-In'; ?>"><?php echo $order['order_type']; ?></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> <span class="status-badge status-<?php echo $order['status']; ?>"><?php echo $order['status']; ?></span></p>
                        <p><strong>Date:</strong> <?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?></p>
                        <p><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method'] ?? 'Credit'); ?></p>
                        <p><strong>Payment Status:</strong> <span class="badge bg-<?php echo $order['payment_status'] == 'paid' ? 'success' : 'warning'; ?>"><?php echo ucfirst($order['payment_status']); ?></span></p>
                    </div>
                </div>
                
                <h5 class="fw-bold mb-3">Order Items</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr><th>Item</th><th class="text-center">Quantity</th><th class="text-end">Unit Price</th><th class="text-end">Subtotal</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                <td class="text-end">₱ <?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="text-end">₱ <?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="3" class="text-end fw-bold">Total:</td>
                            <td class="text-end fw-bold text-success">₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
                
                <?php if ($order['status'] != 'Completed' && $order['status'] != 'Cancelled'): ?>
                <div class="mt-4 text-end">
                    <button class="btn btn-success" onclick="completeOrder(<?php echo $order['id']; ?>)"><i class="bi bi-check-circle"></i> Mark as Completed</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<footer class="text-center py-3">
    <div class="container"><p class="mb-0">&copy; 2025 CHIBUGAN POS System. All Rights Reserved.</p></div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function completeOrder(orderId) {
        if (confirm('Mark this order as COMPLETED?')) {
            fetch('../api/update_order_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId, status: 'Completed' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.href = 'completed.php';
                } else {
                    alert(data.message);
                }
            })
            .catch(error => alert('Error: ' + error.message));
        }
    }
</script>
</body>
</html>