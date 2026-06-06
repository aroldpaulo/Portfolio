<?php
// admin/orders.php - ADVANCED DATABASE VERSION (NO RAW SQL)
// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once '../config/database.php';

// Check if user is logged in and is staff (admin or cashier)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Allow both admin and cashier to access order monitoring
$allowed_roles = ['admin', 'cashier'];
if (!in_array(strtolower($_SESSION['role'] ?? ''), $allowed_roles)) {
    header('Location: ../index.php');
    exit();
}

// Initialize variables
$admin_email = '';
$profile_picture = null;
$new_orders = [];
$preparing_orders = [];
$completed_orders = [];
$cancelled_orders = [];
$order_items = [];
$total_orders = 0;

try {
    // ========== GET STAFF PROFILE ==========
    $stmt = $pdo->prepare("CALL sp_staff_get_profile(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin_data) {
        $_SESSION['full_name'] = $admin_data['full_name'];
        $_SESSION['email'] = $admin_data['email'];
        $_SESSION['profile_picture'] = $admin_data['profile_picture'];
        $admin_email = $admin_data['email'];
        $profile_picture = $admin_data['profile_picture'];
    }
    $stmt->nextRowset();
    
    // ========== GET ALL ORDERS BY STATUS (4 Result Sets) ==========
    $stmt = $pdo->prepare("CALL sp_staff_get_orders_by_status()");
    $stmt->execute();
    
    // Result Set 1: New Orders
    $new_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Result Set 2: Preparing Orders
    $preparing_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Result Set 3: Completed Orders
    $completed_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Result Set 4: Cancelled Orders
    $cancelled_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // ========== GET ALL ORDER ITEMS (Bulk) ==========
    $stmt = $pdo->prepare("CALL sp_staff_get_order_items_bulk()");
    $stmt->execute();
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Organize items by order_id
    foreach ($all_items as $item) {
        $order_id = $item['order_id'];
        if (!isset($order_items[$order_id])) {
            $order_items[$order_id] = [];
        }
        $order_items[$order_id][] = [
            'item_name' => $item['item_name'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price']
        ];
    }
    
    // Calculate total orders
    $total_orders = count($new_orders) + count($preparing_orders) + 
                    count($completed_orders) + count($cancelled_orders);
    
} catch(PDOException $e) {
    $new_orders = [];
    $preparing_orders = [];
    $completed_orders = [];
    $cancelled_orders = [];
    $order_items = [];
    error_log("Database error in order monitoring: " . $e->getMessage());
}

// Prepare avatar HTML for navbar and dropdown
$nav_avatar_html = '';
$dropdown_avatar_html = '';

if ($profile_picture && file_exists('../' . $profile_picture)) {
    $nav_avatar_html = '<img src="../' . $profile_picture . '" class="nav-avatar-img" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">';
    $dropdown_avatar_html = '<img src="../' . $profile_picture . '" class="dropdown-avatar-img" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover;">';
} else {
    $nav_avatar_html = '<div class="nav-avatar">' . strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) . '</div>';
    $dropdown_avatar_html = '<div class="dropdown-avatar">' . strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Monitoring | CHIBUGAN Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
           <link rel="stylesheet" href="../css/admin.css">

        <link rel="icon" type="image/x-icon" href="../logo.jpg">
</head>
<body>

<div class="spinner-overlay" id="spinnerOverlay">
    <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<div class="toast-notification"></div>

<!-- ========== TOP NAVIGATION BAR ========== -->
<nav class="navbar navbar-admin sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand text-danger fs-4 fw-bold" href="dashboard.php">
            <i class="bi bi-gear-fill me-2"></i>CHIBUGAN Admin Panel
        </a>
        <div class="d-flex align-items-center">
            <div class="dropdown">
                <a class="nav-link dropdown-toggle nav-link-profile d-flex align-items-center" href="#" data-bs-toggle="dropdown" aria-expanded="false" role="button">
                    <?php echo $nav_avatar_html; ?>
                    <span class="ms-2 fw-semibold text-dark"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                    <li>
                        <div class="dropdown-profile-header">
                            <?php echo $dropdown_avatar_html; ?>
                            <div class="dropdown-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?></div>
                            <div class="dropdown-email"><?php echo htmlspecialchars($admin_email); ?></div>
                        </div>
                    </li>
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

<!-- ========== ADMIN NAVIGATION BAR ========== -->
<div class="admin-nav shadow-sm sticky-nav-offset">
    <div class="container-fluid px-4 d-flex overflow-x-auto">
        <a href="dashboard.php" class="btn text-white py-3 px-4 rounded-0 fw-semibold opacity-75">
            <i class="bi bi-bar-chart-fill me-1"></i> Dashboard
        </a>
        <a href="users.php" class="btn text-white py-3 px-4 rounded-0 fw-semibold opacity-75">
            <i class="bi bi-people-fill me-1"></i> Manage Users
        </a>
        <a href="menu_management.php" class="btn text-white py-3 px-4 rounded-0 fw-semibold opacity-75">
            <i class="bi bi-list-nested me-1"></i> Menu Management
        </a>
        <a href="orders.php" class="btn btn-danger text-white fw-bold py-3 px-4 rounded-0 active-nav">
            <i class="bi bi-bell-fill me-1"></i> Order Monitoring
        </a>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="container-fluid px-4 mt-4 pb-5">

    <h1 class="fs-2 fw-bold text-danger mb-4 border-bottom pb-3 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-grid-3x3-gap-fill me-2"></i> Real-time Order Monitoring Board</span>
        <button class="btn btn-outline-danger fw-bold" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
        </button>
    </h1>

    <!-- Success/Error Alert -->
    <div id="ajaxAlert" class="alert alert-dismissible fade d-none" role="alert">
        <span id="ajaxAlertMessage"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- Overview Stats -->
    <div class="alert alert-warning mb-4">
        <strong>Overview:</strong> 
        Total Orders: <?php echo $total_orders; ?> | 
        New: <?php echo count($new_orders); ?> |
        Preparing: <?php echo count($preparing_orders); ?> |
        Completed: <?php echo count($completed_orders); ?> |
        Cancelled: <?php echo count($cancelled_orders); ?>
    </div>

    <!-- ALL 4 COLUMNS IN ONE ROW -->
    <div class="row g-4">

        <!-- NEW ORDERS -->
        <div class="col-lg-3 col-md-6">
            <div class="order-column p-3 rounded shadow-sm">
                <h3 class="fs-5 fw-bold text-primary mb-3">
                    <i class="bi bi-plus-circle me-2"></i> New Orders (<?php echo count($new_orders); ?>)
                </h3>
                
                <?php if (empty($new_orders)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2">No new orders</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($new_orders as $order): 
                        $items = $order_items[$order['id']] ?? [];
                        $items_text = '';
                        foreach ($items as $item) {
                            $items_text .= $item['quantity'] . 'x ' . $item['item_name'] . ', ';
                        }
                        $items_text = rtrim($items_text, ', ');
                    ?>
                    <div class="card order-card p-3 shadow-sm mb-3" id="order-<?php echo $order['id']; ?>">
                        <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                            <span class="fw-bolder fs-6 text-primary">#<?php echo htmlspecialchars($order['order_code']); ?></span>
                            <div>
                                <span class="badge bg-dark"><?php echo $order['order_type']; ?></span>
                                <span class="badge bg-info status-badge"><?php echo $order['status']; ?></span>
                            </div>
                        </div>
                        <p class="mb-1 small">Customer: <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                        <p class="mb-2 small">Total: <span class="fw-bold text-danger">₱ <?php echo number_format($order['total_amount'], 2); ?></span></p>
                        <p class="mb-2 small text-muted">Items: <?php echo $items_text; ?></p>
                        <small class="text-muted"><i class="bi bi-clock me-1"></i> <?php echo date('M d, h:i A', strtotime($order['created_at'])); ?></small>
                        
                        <div class="mt-2 text-center">
                            <div class="row g-2">
                                <div class="col-6">
                                    <button class="btn btn-sm btn-outline-primary w-100 fw-bold start-order-btn" data-order-id="<?php echo $order['id']; ?>" data-status="Preparing">
                                        <i class="bi bi-arrow-right"></i> Start
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-sm btn-outline-danger w-100 fw-bold cancel-order-btn" data-order-id="<?php echo $order['id']; ?>" data-status="Cancelled">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- PREPARING ORDERS -->
        <div class="col-lg-3 col-md-6">
            <div class="order-column order-column-preparing p-3 rounded shadow-sm">
                <h3 class="fs-5 fw-bold text-warning mb-3">
                    <i class="bi bi-clock-history me-2"></i> Preparing (<?php echo count($preparing_orders); ?>)
                </h3>
                
                <?php if (empty($preparing_orders)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-clock fs-1"></i>
                        <p class="mt-2">No orders being prepared</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($preparing_orders as $order): 
                        $items = $order_items[$order['id']] ?? [];
                        $items_text = '';
                        foreach ($items as $item) {
                            $items_text .= $item['quantity'] . 'x ' . $item['item_name'] . ', ';
                        }
                        $items_text = rtrim($items_text, ', ');
                    ?>
                    <div class="card order-card p-3 shadow-sm mb-3" id="order-<?php echo $order['id']; ?>">
                        <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                            <span class="fw-bolder fs-6 text-warning">#<?php echo htmlspecialchars($order['order_code']); ?></span>
                            <div>
                                <span class="badge bg-dark"><?php echo $order['order_type']; ?></span>
                                <span class="badge bg-warning text-dark status-badge"><?php echo $order['status']; ?></span>
                            </div>
                        </div>
                        <p class="mb-1 small">Customer: <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                        <p class="mb-2 small">Total: <span class="fw-bold text-danger">₱ <?php echo number_format($order['total_amount'], 2); ?></span></p>
                        <p class="mb-2 small text-muted">Items: <?php echo $items_text; ?></p>
                        <small class="text-muted"><i class="bi bi-clock me-1"></i> <?php echo date('M d, h:i A', strtotime($order['created_at'])); ?></small>
                        
                        <div class="mt-2 text-center">
                            <div class="row g-2">
                                <div class="col-6">
                                    <button class="btn btn-sm btn-outline-warning w-100 fw-bold complete-order-btn" data-order-id="<?php echo $order['id']; ?>" data-status="Completed">
                                        <i class="bi bi-check"></i> Complete
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-sm btn-outline-danger w-100 fw-bold cancel-order-btn" data-order-id="<?php echo $order['id']; ?>" data-status="Cancelled">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- COMPLETED ORDERS -->
        <div class="col-lg-3 col-md-6">
            <div class="order-column order-column-completed p-3 rounded shadow-sm">
                <h3 class="fs-5 fw-bold text-success mb-3">
                    <i class="bi bi-check-circle-fill me-2"></i> Completed (<?php echo count($completed_orders); ?>)
                </h3>

                <?php if (empty($completed_orders)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-circle fs-1"></i>
                        <p class="mt-2">No completed orders</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($completed_orders as $order): 
                        $items = $order_items[$order['id']] ?? [];
                        $items_text = '';
                        foreach ($items as $item) {
                            $items_text .= $item['quantity'] . 'x ' . $item['item_name'] . ', ';
                        }
                        $items_text = rtrim($items_text, ', ');
                    ?>
                    <div class="card order-card p-3 shadow-sm mb-3" id="order-<?php echo $order['id']; ?>">
                        <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                            <span class="fw-bolder fs-6 text-success">#<?php echo htmlspecialchars($order['order_code']); ?></span>
                            <div>
                                <span class="badge bg-dark"><?php echo $order['order_type']; ?></span>
                                <span class="badge bg-success status-badge"><?php echo $order['status']; ?></span>
                            </div>
                        </div>
                        <p class="mb-1 small">Customer: <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                        <p class="mb-2 small">Total: <span class="fw-bold text-danger">₱ <?php echo number_format($order['total_amount'], 2); ?></span></p>
                        <p class="mb-2 small text-muted">Items: <?php echo $items_text; ?></p>
                        <small class="text-muted"><i class="bi bi-check-all me-1"></i> Completed: <?php echo date('M d, h:i A', strtotime($order['updated_at'])); ?></small>
                        <div class="mt-2 text-center">
                            <span class="badge bg-success p-2"><i class="bi bi-check-circle me-1"></i> ORDER COMPLETED</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- CANCELLED ORDERS -->
        <div class="col-lg-3 col-md-6">
            <div class="order-column order-column-cancelled p-3 rounded shadow-sm">
                <h3 class="fs-5 fw-bold text-danger mb-3">
                    <i class="bi bi-x-circle-fill me-2"></i> Cancelled (<?php echo count($cancelled_orders); ?>)
                </h3>

                <?php if (empty($cancelled_orders)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-x-circle fs-1"></i>
                        <p class="mt-2">No cancelled orders</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($cancelled_orders as $order): 
                        $items = $order_items[$order['id']] ?? [];
                        $items_text = '';
                        foreach ($items as $item) {
                            $items_text .= $item['quantity'] . 'x ' . $item['item_name'] . ', ';
                        }
                        $items_text = rtrim($items_text, ', ');
                    ?>
                    <div class="card order-card p-3 shadow-sm mb-3" id="order-<?php echo $order['id']; ?>">
                        <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                            <span class="fw-bolder fs-6 text-danger">#<?php echo htmlspecialchars($order['order_code']); ?></span>
                            <div>
                                <span class="badge bg-dark"><?php echo $order['order_type']; ?></span>
                                <span class="badge bg-secondary status-badge"><?php echo $order['status']; ?></span>
                            </div>
                        </div>
                        <p class="mb-1 small">Customer: <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                        <p class="mb-1 small">Total: <span class="fw-bold text-danger">₱ <?php echo number_format($order['total_amount'], 2); ?></span></p>
                        <p class="mb-2 small">
                            <span class="badge bg-success">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Refunded: ₱ <?php echo number_format($order['total_amount'], 2); ?>
                            </span>
                        </p>
                        <small class="text-muted"><i class="bi bi-clock me-1"></i> <?php echo date('M d, h:i A', strtotime($order['cancelled_at'] ?? $order['created_at'])); ?></small>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Footer -->
<footer class="py-3 mt-5">
    <div class="container-fluid px-4 text-center">
        <small>&copy; 2025 CHIBUGAN Admin System.</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Initialize dropdowns
    document.addEventListener('DOMContentLoaded', function() {
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });
    });
    
    function showToast(message, type) {
        if (typeof type === 'undefined') type = 'success';
        const container = document.querySelector('.toast-notification');
        const toast = document.createElement('div');
        toast.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' alert-dismissible fade show shadow';
        toast.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        container.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 5000);
    }
    
    function showSpinner(show) {
        const spinner = document.getElementById('spinnerOverlay');
        if (show) {
            spinner.classList.add('show');
        } else {
            spinner.classList.remove('show');
        }
    }
    
    async function updateOrderStatus(orderId, newStatus) {
        if (!confirm('Update order #' + orderId + ' to "' + newStatus + '"?')) return;
        
        showSpinner(true);
        
        try {
            const response = await fetch('../api/update_order_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId, status: newStatus })
            });
            
            const data = await response.json();
            showSpinner(false);
            
            if (data.success) {
                showToast(data.message);
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                showToast(data.message, 'danger');
            }
        } catch (error) {
            showSpinner(false);
            showToast('Error updating order status: ' + error.message, 'danger');
        }
    }
    
    // Attach event listeners to buttons
    var startButtons = document.querySelectorAll('.start-order-btn, .complete-order-btn, .cancel-order-btn');
    for (var i = 0; i < startButtons.length; i++) {
        startButtons[i].addEventListener('click', function() {
            var orderId = this.getAttribute('data-order-id');
            var status = this.getAttribute('data-status');
            updateOrderStatus(orderId, status);
        });
    }
</script>
</body>
</html>