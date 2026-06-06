<?php
// customer/dashboard.php - FIXED VERSION using stored procedures
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
$recent_orders = [];
$transactions = [];

try {
    // ========== GET CUSTOMER PROFILE USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_customer_get_profile(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    if ($user_data) {
        $_SESSION['credit_balance'] = $user_data['credit_balance'];
        $_SESSION['full_name'] = $user_data['full_name'];
        $_SESSION['email'] = $user_data['email'];
        $_SESSION['profile_picture'] = $user_data['profile_picture'];
    }
    
    // ========== GET ALL ORDERS USING STORED PROCEDURE (SAME AS ORDERS.PHP) ==========
    $stmt = $pdo->prepare("CALL sp_customer_get_all_orders(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $all_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // Split orders by status - SAME LOGIC AS ORDERS.PHP
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
    
    // Get recent orders (pending orders for dashboard)
    $recent_orders = array_slice($pending_orders, 0, 5);
    
    // ========== GET TRANSACTIONS ==========
    $stmt = $pdo->prepare("CALL sp_customer_get_transactions(?, 10)");
    $stmt->execute([$_SESSION['user_id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
} catch(PDOException $e) {
    error_log("Database error in dashboard: " . $e->getMessage());
    $pending_orders = [];
    $completed_orders = [];
    $cancelled_orders = [];
    $transactions = [];
}

// Calculate counts from the arrays
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
    <title>CHIBUGAN Customer Dashboard</title>
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
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .status-New { background: #FF9800; color: white; }
        .status-Completed { background: #4CAF50; color: white; }
        .status-Processing { background: #2196F3; color: white; }
        .status-Preparing { background: #9C27B0; color: white; }
        .status-Ready { background: #00BCD4; color: white; }
        .status-Cancelled { background: #f44336; color: white; }
        .welcome-banner {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
        }
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
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
                <i class="bi bi-shop"></i>
                CHIBUGAN
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-house-door-fill me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="menu.php">
                            <i class="bi bi-list-check me-1"></i>Menu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
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
        <div class="container mt-5 py-4">
            
            <!-- Welcome Banner -->
            <div class="welcome-banner mb-5">
                <div class="row align-items-center">
                    <div class="col">
                        <h1 class="fw-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Customer'); ?>! <i class="bi bi-hand-thumbs-up-fill"></i></h1>
                        <p class="lead mb-0">Quick access to your favorite features and latest updates.</p>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-emoji-smile-fill fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- Dashboard Cards -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="card shadow h-100 p-3" onclick="window.location.href='menu.php'" style="cursor: pointer;">
                        <div class="card-body text-center">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="bi bi-cart-plus-fill text-success fs-1"></i>
                            </div>
                            <h5 class="card-title fw-bold">Order Now</h5>
                            <p class="card-text">Browse our menu and place your order instantly.</p>
                            <a href="menu.php" class="btn btn-success mt-2 w-100">
                                <i class="bi bi-shop me-1"></i> View Menu
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow h-100 p-3" onclick="window.location.href='orders.php'" style="cursor: pointer;">
                        <div class="card-body text-center">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="bi bi-clock-history text-primary fs-1"></i>
                            </div>
                            <h5 class="card-title fw-bold">Order History</h5>
                            <p class="card-text">View your pending orders and review past purchases.</p>
                            <a href="orders.php" class="btn btn-outline-success mt-2 w-100">
                                <i class="bi bi-receipt me-1"></i> View Orders
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow h-100 p-3" data-bs-toggle="modal" data-bs-target="#walletModal" style="cursor: pointer;">
                        <div class="card-body text-center">
                            <div class="bg-info bg-opacity-10 rounded-circle p-3 d-inline-block mb-3">
                                <i class="bi bi-wallet-fill text-info fs-1"></i>
                            </div>
                            <h5 class="card-title fw-bold">My Wallet</h5>
                            <p class="card-text">Available Credits: ₱<?php echo number_format($user_balance, 2); ?></p>
                            <a href="#" class="btn btn-outline-info mt-2 w-100" data-bs-toggle="modal" data-bs-target="#walletModal">
                                <i class="bi bi-cash me-1"></i> Manage Credits
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Row - 3 Stats -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="stat-card" onclick="window.location.href='orders.php'">
                        <i class="bi bi-hourglass-split text-warning fs-2"></i>
                        <div class="stat-number"><?php echo $pending_count; ?></div>
                        <div class="stat-label">Pending Orders</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card" onclick="window.location.href='orders.php'">
                        <i class="bi bi-check-circle-fill text-success fs-2"></i>
                        <div class="stat-number"><?php echo $completed_count; ?></div>
                        <div class="stat-label">Completed Orders</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card" onclick="window.location.href='orders.php'">
                        <i class="bi bi-x-circle-fill text-danger fs-2"></i>
                        <div class="stat-number"><?php echo $cancelled_count; ?></div>
                        <div class="stat-label">Cancelled Orders</div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders Section -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold"><i class="bi bi-clock-history me-2 text-success"></i>Recent Orders (Pending)</h4>
                        <a href="orders.php" class="btn btn-sm btn-outline-success">View All <i class="bi bi-arrow-right"></i></a>
                    </div>
                    
                    <?php if (empty($recent_orders)): ?>
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle me-2"></i>You have no pending orders.
                            <a href="menu.php" class="alert-link">Start ordering now!</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive order-table shadow-sm">
                            <table class="table table-hover mb-0">
                                <thead class="table-success">
                                    <tr>
                                        <th>Order Code</th>
                                        <th>Date</th>
                                        <th>Total Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($order['order_code'] ?? 'N/A'); ?></strong></td>
                                        <td><?php echo isset($order['created_at']) ? date('M d, Y h:i A', strtotime($order['created_at'])) : 'N/A'; ?></td>
                                        <td class="text-success fw-bold">₱<?php echo number_format($order['total_amount'] ?? 0, 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status'] ?? 'New'; ?>">
                                                <?php echo $order['status'] ?? 'New'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (($order['status'] ?? '') == 'New'): ?>
                                                <button class="btn btn-sm btn-danger" onclick="cancelOrder(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_code']); ?>', <?php echo $order['total_amount']; ?>)">
                                                    <i class="bi bi-x-circle"></i> Cancel
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled>
                                                    <i class="bi bi-check-circle"></i> Processing
                                                </button>
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
            
            <!-- Quick Tips -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="alert alert-light border shadow-sm">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <i class="bi bi-lightbulb-fill text-warning fs-2"></i>
                            </div>
                            <div class="col">
                                <strong class="text-success">Quick Tips:</strong>
                                <ul class="mb-0 mt-1">
                                    <li>Use your credit balance for faster checkout!</li>
                                    <li>You can cancel orders while they're still "New" status.</li>
                                    <li>Need more credits? Click "My Wallet" to request top-up.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== WALLET MODAL ========== -->
    <div class="modal fade" id="walletModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
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
                    
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Recent Transactions</h6>
                    
                    <?php if (empty($transactions)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-credit-card fs-1 opacity-25"></i>
                            <p class="mt-2">No transactions found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr><th>Date</th><th>Type</th><th class="text-end">Amount</th><th>Description</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $trans): ?>
                                    <tr>
                                        <td><small><?php echo date('M d, Y h:i A', strtotime($trans['created_at'])); ?></small></td>
                                        <td>
                                            <?php if ($trans['type'] == 'Top-Up'): ?>
                                                <span class="badge bg-success">Top-Up</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Spend</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end <?php echo $trans['type'] == 'Top-Up' ? 'text-success' : 'text-danger'; ?> fw-bold">
                                            <?php echo $trans['type'] == 'Top-Up' ? '+' : '-'; ?> ₱<?php echo number_format($trans['amount'], 2); ?>
                                        </td>
                                        <td><small><?php echo htmlspecialchars(substr($trans['reason'], 0, 50)); ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TOP-UP REQUEST MODAL -->
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

    <!-- ========== FOOTER ========== -->
    <footer class="py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-shop me-2"></i>CHIBUGAN Carinderia</h5>
                    <p>Serving Panabo City with affordable, tasty meals since 1995.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h5>Contact Us</h5>
                    <p><i class="bi bi-envelope-fill me-1"></i> chibugan@gmail.com</p>
                    <p><i class="bi bi-telephone-fill me-1"></i> 0995-102-1259</p>
                    <p><i class="bi bi-geo-alt-fill me-1"></i> Brgy. Cagangohan, Panabo City</p>
                </div>
            </div>
            <hr>
            <p class="text-center mb-0">&copy; 2025 CHIBUGAN. All Rights Reserved.</p>
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

    function cancelOrder(orderId, orderCode, totalAmount) {
        if (confirm('Are you sure you want to cancel order ' + orderCode + '? You will be refunded ₱' + totalAmount.toFixed(2))) {
            fetch('../api/cancel_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                showToast(data.message);
                setTimeout(function() { location.reload(); }, 1500);
            })
            .catch(function(error) {
                showToast('Error cancelling order', 'danger');
            });
        }
    }
    
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