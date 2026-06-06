<?php
// customer/orders.php - COMPLETELY FIXED VERSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: ../index.php');
    exit();
}

// Database connection
require_once '../config/database.php';

// Initialize variables
$pending_orders = [];
$completed_orders = [];
$cancelled_orders = [];
$order_items = [];

try {
    // ========== GET CUSTOMER PROFILE USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_customer_get_profile(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    if ($user_data) {
        $_SESSION['full_name'] = $user_data['full_name'];
        $_SESSION['email'] = $user_data['email'];
        $_SESSION['credit_balance'] = $user_data['credit_balance'];
        $_SESSION['profile_picture'] = $user_data['profile_picture'];
    }
    
    // ========== GET ALL ORDERS USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_customer_get_all_orders(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $all_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // ========== GET ALL ORDER ITEMS IN ONE CALL (Bulk) ==========
    $stmt = $pdo->prepare("CALL sp_customer_get_all_order_items(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Organize items by order_id
    foreach ($all_items as $item) {
        $order_id = $item['order_id'];
        if (!isset($order_items[$order_id])) {
            $order_items[$order_id] = [];
        }
        $order_items[$order_id][] = $item;
    }
    
    // Split orders by status - THIS IS THE ONLY PLACE WE COUNT ORDERS
    foreach ($all_orders as $order) {
        $status = $order['status'] ?? 'New';
        if (in_array($status, ['New', 'Preparing', 'Ready', 'Pending'])) {
            $pending_orders[] = $order;
        } elseif ($status == 'Completed') {
            $completed_orders[] = $order;
        } elseif ($status == 'Cancelled') {
            $cancelled_orders[] = $order;
        }
    }
    
} catch(PDOException $e) {
    $pending_orders = [];
    $completed_orders = [];
    $cancelled_orders = [];
    $order_items = [];
    error_log("Database error in orders: " . $e->getMessage());
}

// Calculate counts directly from the arrays (NO DOUBLE COUNTING)
$pending_count = count($pending_orders);
$completed_count = count($completed_orders);
$cancelled_count = count($cancelled_orders);

$user_email = $_SESSION['email'] ?? '';
$user_balance = $_SESSION['credit_balance'] ?? 0;
$profile_picture = $_SESSION['profile_picture'] ?? null;

// Prepare avatar HTML
$nav_avatar_html = '';
$dropdown_avatar_html = '';

if ($profile_picture && file_exists('../' . $profile_picture)) {
    $nav_avatar_html = '<img src="../' . $profile_picture . '" class="nav-avatar-img" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">';
    $dropdown_avatar_html = '<img src="../' . $profile_picture . '" class="dropdown-avatar-img" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover;">';
} else {
    $nav_avatar_html = '<div class="nav-avatar">' . strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) . '</div>';
    $dropdown_avatar_html = '<div class="dropdown-avatar">' . strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | CHIBUGAN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/customer.css">
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
        /* Additional styles for better visibility */
        .badge {
            font-size: 0.8rem;
        }
        .nav-tabs .badge {
            margin-left: 5px;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="toast-notification"></div>

    <!-- ========== NAVIGATION BAR ========== -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-shop"></i> CHIBUGAN
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-house-door-fill me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="menu.php">
                            <i class="bi bi-list-check me-1"></i>Menu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="orders.php">
                            <i class="bi bi-clock-history me-1"></i>Orders
                        </a>
                    </li>
                    
                    <!-- Credit Balance -->
                    <li class="nav-item me-2">
                        <span class="nav-link text-white">
                            <span class="credit-badge">
                                <i class="bi bi-credit-card-fill me-1"></i> ₱<?php echo number_format($user_balance, 2); ?>
                            </span>
                        </span>
                    </li>
                    
                    <!-- Profile Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle nav-link-profile" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo $nav_avatar_html; ?>
                            <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                            <li>
                                <div class="dropdown-profile-header">
                                    <?php echo $dropdown_avatar_html; ?>
                                    <div class="dropdown-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                                    <div class="dropdown-email"><?php echo htmlspecialchars($user_email); ?></div>
                                </div>
                            </li>
                            <li>
                                <a class="dropdown-item dropdown-item-custom" href="profile.php">
                                    <i class="bi bi-person-circle"></i> My Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item dropdown-item-custom" href="#" data-bs-toggle="modal" data-bs-target="#walletModal">
                                    <i class="bi bi-wallet2"></i> My Wallet
                                    <span class="badge bg-success float-end">₱<?php echo number_format($user_balance, 2); ?></span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item dropdown-item-custom" href="orders.php">
                                    <i class="bi bi-clock-history"></i> My Orders
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item dropdown-item-custom" href="menu.php">
                                    <i class="bi bi-cart-plus"></i> Order Now
                                </a>
                            </li>
                            <li><hr class="dropdown-divider-custom"></li>
                            <li>
                                <a class="dropdown-item dropdown-item-custom text-danger" href="../api/logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </li>
                            <li>
                                <div class="dropdown-footer">
                                    <i class="bi bi-shield-check"></i> Secure Connection
                                </div>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ========== MAIN CONTENT ========== -->
    <div class="content">
        <div class="container py-4">
            <div class="row">
                <div class="col-12">
                    <h2 class="fs-4 fw-bold mb-3 text-dark">
                        <i class="bi bi-clock-history me-2 text-success"></i> My Order History
                    </h2>

                    <!-- User Balance Card -->
                    <div class="user-balance-card">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Customer'); ?></h5>
                                <p class="mb-0 opacity-75">Current credit balance</p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <h3 class="mb-0 fw-bold">₱<?php echo number_format($user_balance, 2); ?></h3>
                                <small class="opacity-75">Available for orders</small>
                            </div>
                        </div>
                    </div>

                    <!-- Order Tabs -->
                    <ul class="nav nav-tabs mb-4" id="ordersTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                                <i class="bi bi-hourglass-split me-2"></i>Active
                                <span class="badge bg-warning ms-2"><?php echo $pending_count; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
                                <i class="bi bi-check-circle me-2"></i>Completed
                                <span class="badge bg-success ms-2"><?php echo $completed_count; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" data-bs-target="#cancelled" type="button" role="tab">
                                <i class="bi bi-x-circle me-2"></i>Cancelled
                                <span class="badge bg-danger ms-2"><?php echo $cancelled_count; ?></span>
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="ordersTabContent">
                        
                        <!-- Active Orders Tab -->
                        <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                            <?php if (empty($pending_orders)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    <h5 class="text-muted">No Active Orders</h5>
                                    <p class="text-muted">You don't have any active orders at the moment.</p>
                                    <a href="menu.php" class="btn btn-success mt-2">Browse Menu</a>
                                </div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($pending_orders as $order): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card order-card p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="fw-bold mb-1 order-number"><?php echo htmlspecialchars($order['order_code']); ?></h6>
                                                    <small class="text-muted order-date"><?php echo date('F d, Y · h:i A', strtotime($order['created_at'])); ?></small>
                                                </div>
                                                <span class="status-badge status-<?php echo $order['status']; ?>"><?php echo $order['status']; ?></span>
                                            </div>
                                            <div class="mb-2">
                                                <span class="order-type-badge rounded-pill"><?php echo htmlspecialchars($order['order_type'] ?? 'Take-Out'); ?></span>
                                            </div>
                                            <div class="order-items mb-2">
                                                <?php 
                                                $items = $order_items[$order['id']] ?? [];
                                                $display_count = 0;
                                                foreach ($items as $item): 
                                                    if ($display_count++ >= 3) break;
                                                ?>
                                                <div class="d-flex justify-content-between small">
                                                    <span><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['item_name']); ?></span>
                                                    <span class="item-price">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                                </div>
                                                <?php endforeach; ?>
                                                <?php if (count($items) > 3): ?>
                                                    <div class="small text-muted">+<?php echo count($items) - 3; ?> more items</div>
                                                <?php endif; ?>
                                            </div>
                                            <hr class="my-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold">Total: ₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                                <?php if ($order['status'] == 'New'): ?>
                                                    <button class="btn btn-sm btn-outline-danger cancel-order-btn" 
                                                            data-order-id="<?php echo $order['id']; ?>" 
                                                            data-order-number="<?php echo htmlspecialchars($order['order_code']); ?>" 
                                                            data-total-amount="<?php echo $order['total_amount']; ?>">
                                                        <i class="bi bi-x-circle me-1"></i> Cancel
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Processing</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (isset($order['payment_status']) && $order['payment_status'] == 'pending'): ?>
                                                <div class="mt-2">
                                                    <span class="badge payment-pending">Payment: Pending</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Completed Orders Tab -->
                        <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
                            <?php if (empty($completed_orders)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-check-circle"></i>
                                    <h5 class="text-muted">No Completed Orders</h5>
                                    <p class="text-muted">Your completed orders will appear here.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($completed_orders as $order): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card order-card completed-order p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="fw-bold mb-1 order-number"><?php echo htmlspecialchars($order['order_code']); ?></h6>
                                                    <small class="text-muted order-date"><?php echo date('F d, Y · h:i A', strtotime($order['created_at'])); ?></small>
                                                </div>
                                                <span class="status-badge status-Completed">Completed</span>
                                            </div>
                                            <div class="mb-2">
                                                <span class="order-type-badge rounded-pill"><?php echo htmlspecialchars($order['order_type'] ?? 'Take-Out'); ?></span>
                                            </div>
                                            <div class="order-items mb-2">
                                                <?php 
                                                $items = $order_items[$order['id']] ?? [];
                                                $display_count = 0;
                                                foreach ($items as $item): 
                                                    if ($display_count++ >= 3) break;
                                                ?>
                                                <div class="d-flex justify-content-between small">
                                                    <span><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['item_name']); ?></span>
                                                    <span class="item-price">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                                </div>
                                                <?php endforeach; ?>
                                                <?php if (count($items) > 3): ?>
                                                    <div class="small text-muted">+<?php echo count($items) - 3; ?> more items</div>
                                                <?php endif; ?>
                                            </div>
                                            <hr class="my-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold">Total: ₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                                <span class="badge bg-success"><i class="bi bi-check-lg"></i> Done</span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Cancelled Orders Tab -->
                        <div class="tab-pane fade" id="cancelled" role="tabpanel" aria-labelledby="cancelled-tab">
                            <?php if (empty($cancelled_orders)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-x-circle"></i>
                                    <h5 class="text-muted">No Cancelled Orders</h5>
                                    <p class="text-muted">You haven't cancelled any orders.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($cancelled_orders as $order): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card order-card cancelled-order p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="fw-bold mb-1 order-number"><?php echo htmlspecialchars($order['order_code']); ?></h6>
                                                    <small class="text-muted order-date"><?php echo date('F d, Y · h:i A', strtotime($order['created_at'])); ?></small>
                                                </div>
                                                <span class="status-badge status-Cancelled">Cancelled</span>
                                            </div>
                                            <div class="mb-2">
                                                <span class="order-type-badge rounded-pill"><?php echo htmlspecialchars($order['order_type'] ?? 'Take-Out'); ?></span>
                                            </div>
                                            <div class="order-items mb-2">
                                                <?php 
                                                $items = $order_items[$order['id']] ?? [];
                                                $display_count = 0;
                                                foreach ($items as $item): 
                                                    if ($display_count++ >= 3) break;
                                                ?>
                                                <div class="d-flex justify-content-between small">
                                                    <span><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['item_name']); ?></span>
                                                    <span class="item-price">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                                </div>
                                                <?php endforeach; ?>
                                                <?php if (count($items) > 3): ?>
                                                    <div class="small text-muted">+<?php echo count($items) - 3; ?> more items</div>
                                                <?php endif; ?>
                                            </div>
                                            <hr class="my-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold text-muted">Total: ₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                                <span class="badge refund-badge"><i class="bi bi-arrow-counterclockwise"></i> Refunded</span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Order Confirmation Modal -->
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title text-dark" id="cancelOrderModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Confirm Order Cancellation
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-x-circle-fill text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="text-center fw-bold mb-3 text-dark" id="cancelOrderNumber">Cancel Order</h6>
                    
                    <div class="alert alert-warning">
                        <div class="d-flex justify-content-between">
                            <span>Order Total:</span>
                            <span class="fw-bold" id="cancelOrderAmount">₱ 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span>Refund Amount:</span>
                            <span class="fw-bold text-success" id="cancelRefundAmount">₱ 0.00</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label fw-semibold text-dark">Reason for Cancellation (Optional)</label>
                        <textarea class="form-control" id="cancelReason" rows="3" 
                                  placeholder="Please specify the reason for cancelling this order..."></textarea>
                    </div>
                    
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-diamond-fill me-2"></i>
                        <strong>Important:</strong> This action cannot be undone. Once cancelled, the order will be removed and credits will be refunded.
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmCancellation">
                        <label class="form-check-label text-dark" for="confirmCancellation">
                            I understand that this action is irreversible
                        </label>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-secondary w-50" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i> Go Back
                    </button>
                    <button type="button" class="btn btn-danger w-50" id="confirmCancelBtn" disabled>
                        <i class="bi bi-check-circle me-2"></i> Cancel Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Wallet Modal -->
    <div class="modal fade" id="walletModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-wallet2 me-2"></i>My Wallet</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="wallet-status mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1 opacity-75">Available Balance</p>
                                <h2 class="fw-bolder mb-0">₱ <?php echo number_format($user_balance, 2); ?></h2>
                                <small class="opacity-75">CHIBUGAN Credits</small>
                            </div>
                            <div>
                                <i class="bi bi-credit-card-2-front fs-1 opacity-75"></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-grid gap-2 mb-4">
                        <button class="btn btn-success py-3" type="button" data-bs-toggle="modal" data-bs-target="#topupRequestModal" data-bs-dismiss="modal">
                            <i class="bi bi-cash me-2"></i>Request Credit Top Up
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Top-Up Request Modal -->
    <div class="modal fade" id="topupRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Credit Top-Up Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="topupForm">
                    <div class="modal-body">
                        <p>Enter the amount you wish to top up. After submitting, please wait for cashier approval.</p>
                        <div class="form-group">
                            <label class="form-label fw-semibold">Top-Up Amount (₱)</label>
                            <input type="number" id="topupAmount" class="form-control p-3 fs-4" required min="50" max="50000" step="any" placeholder="Enter amount">
                            <div class="form-text mt-2">Minimum: ₱50 | Maximum: ₱50,000</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-bold">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-3">
        <div class="container">
            <p class="mb-0">&copy; 2025 CHIBUGAN. All Rights Reserved.</p>
        </div>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showToast(message, type) {
        if (typeof type === 'undefined') type = 'success';
        const toastContainer = document.querySelector('.toast-notification');
        const toast = document.createElement('div');
        toast.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' alert-dismissible fade show shadow';
        toast.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        toastContainer.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 5000);
    }
    
    function showSpinner(show) {
        var spinner = document.getElementById('globalSpinner');
        if (!spinner) {
            spinner = document.createElement('div');
            spinner.id = 'globalSpinner';
            spinner.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: none; justify-content: center; align-items: center;';
            spinner.innerHTML = '<div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status"><span class="visually-hidden">Loading...</span></div>';
            document.body.appendChild(spinner);
        }
        spinner.style.display = show ? 'flex' : 'none';
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        var currentOrderId = null;
        var currentOrderAmount = 0;
        var currentOrderNumber = '';
        
        // Cancel order button click
        var cancelBtns = document.querySelectorAll('.cancel-order-btn');
        for (var i = 0; i < cancelBtns.length; i++) {
            cancelBtns[i].addEventListener('click', function(e) {
                e.stopPropagation();
                currentOrderId = this.dataset.orderId;
                currentOrderNumber = this.dataset.orderNumber;
                currentOrderAmount = parseFloat(this.dataset.totalAmount);
                
                document.getElementById('cancelOrderNumber').textContent = 'Cancel Order: ' + currentOrderNumber;
                document.getElementById('cancelOrderAmount').textContent = '₱ ' + currentOrderAmount.toFixed(2);
                document.getElementById('cancelRefundAmount').textContent = '₱ ' + currentOrderAmount.toFixed(2);
                
                document.getElementById('cancelReason').value = '';
                document.getElementById('confirmCancellation').checked = false;
                document.getElementById('confirmCancelBtn').disabled = true;
                
                var cancelModal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
                cancelModal.show();
            });
        }
        
        // Confirmation checkbox change
        document.getElementById('confirmCancellation').addEventListener('change', function() {
            document.getElementById('confirmCancelBtn').disabled = !this.checked;
        });
        
        // Confirm cancellation button
        document.getElementById('confirmCancelBtn').addEventListener('click', async function() {
            if (!currentOrderId) return;
            
            var cancelReason = document.getElementById('cancelReason').value;
            
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cancelling...';
            showSpinner(true);
            
            try {
                var response = await fetch('../api/cancel_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        order_id: currentOrderId,
                        reason: cancelReason 
                    })
                });
                
                var data = await response.json();
                showSpinner(false);
                
                if (data.success) {
                    showToast(data.message);
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showToast(data.message || 'Failed to cancel order', 'danger');
                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-check-circle me-2"></i> Cancel Order';
                }
            } catch (error) {
                showSpinner(false);
                showToast('Error cancelling order', 'danger');
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-check-circle me-2"></i> Cancel Order';
            }
        });
    });
    
    // Top-up form submission
    var topupForm = document.getElementById('topupForm');
    if (topupForm) {
        topupForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            var amount = document.getElementById('topupAmount').value;
            
            if (!amount || parseFloat(amount) < 50) {
                showToast('Minimum top-up amount is ₱50', 'danger');
                return;
            }
            if (parseFloat(amount) > 50000) {
                showToast('Maximum top-up amount is ₱50,000', 'danger');
                return;
            }
            
            try {
                var response = await fetch('../api/request_topup.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ amount: amount })
                });
                
                var data = await response.json();
                showToast(data.message);
                
                if (data.success) {
                    var modal = bootstrap.Modal.getInstance(document.getElementById('topupRequestModal'));
                    if (modal) modal.hide();
                    document.getElementById('topupAmount').value = '';
                    var walletModal = bootstrap.Modal.getInstance(document.getElementById('walletModal'));
                    if (walletModal) walletModal.hide();
                    setTimeout(function() { location.reload(); }, 1500);
                }
            } catch (error) {
                showToast('Error submitting request', 'danger');
            }
        });
    }
</script>
</body>
</html>