<?php
// cashier/order.php - FIXED: NO RAW SQL, Footer Fixed
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

$order = null;
$order_items = [];
$pending_count = 0;

try {
    // Get order details - returns two result sets
    $stmt = $pdo->prepare("CALL sp_get_order_details(?)");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    $stmt->nextRowset();
    $order_items = $stmt->fetchAll();
    
    if (!$order) { header('Location: dashboard.php'); exit(); }
    
    // Get pending count - returns result set directly
    $stmt = $pdo->prepare("CALL sp_get_pending_count()");
    $stmt->execute();
    $result = $stmt->fetch();
    $pending_count = $result['pending_count'] ?? 0;
    $stmt->nextRowset();
    
    // Get user profile - returns result set directly
    $stmt = $pdo->prepare("CALL sp_get_user_profile(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    $stmt->nextRowset();
    
    if ($user_data) {
        $_SESSION['full_name'] = $user_data['full_name'];
        $_SESSION['email'] = $user_data['email'];
        $_SESSION['profile_picture'] = $user_data['profile_picture'];
    }
    
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: dashboard.php');
    exit();
}

$user_name = $_SESSION['full_name'] ?? 'Cashier';
$user_email = $_SESSION['email'] ?? '';
$profile_picture = $_SESSION['profile_picture'] ?? null;
$user_role = $_SESSION['role'] ?? 'cashier';

$status_colors = ['New' => 'bg-info', 'Preparing' => 'bg-warning text-dark', 'Ready' => 'bg-primary', 'Completed' => 'bg-success', 'Cancelled' => 'bg-danger'];
$status_color = $status_colors[$order['status']] ?? 'bg-secondary';

$subtotal = 0;
foreach ($order_items as $item) { $subtotal += $item['subtotal']; }

$nav_avatar_html = '';
$dropdown_avatar_html = '';

if ($profile_picture && file_exists('../' . $profile_picture)) {
    $nav_avatar_html = '<img src="../' . $profile_picture . '" class="nav-avatar-img">';
    $dropdown_avatar_html = '<img src="../' . $profile_picture . '" class="dropdown-avatar-img">';
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
    <link rel="stylesheet" href="cashier.css">
            <link rel="icon" type="image/x-icon" href="../logo.jpg">

</head>
<body>

<div class="wrapper">
    <div class="toast-notification"></div>

    <!-- NORMAL VIEW (Visible on screen) -->
    <div class="normal-view">
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
                            <li><a class="dropdown-item dropdown-item-custom" href="settings.php"><i class="bi bi-person-circle"></i> My Profile</a></li>
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
    </div>

    <div class="content">
        <!-- NORMAL VIEW CONTENT -->
        <div class="normal-view">
            <div class="container px-4 mt-4 pb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="fs-2 fw-bold text-dark"><i class="bi bi-file-text me-2 text-success"></i> Order Details: <?php echo htmlspecialchars($order['order_code']); ?></h1>
                    <a href="pending.php" class="btn btn-outline-secondary fw-semibold"><i class="bi bi-arrow-left me-1"></i> Back to Queue</a>
                </div>
                
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card card-pos bg-white p-4 mb-4 border-start border-warning border-5">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div><h3 class="fs-4 fw-bold mb-2">Order Status</h3><span class="badge <?php echo $status_color; ?> fw-bold status-badge-lg"><?php echo $order['status']; ?></span></div>
                                <div class="text-end"><small class="text-muted d-block">Order Code</small><span class="fw-bold fs-5"><?php echo htmlspecialchars($order['order_code']); ?></span></div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6"><p class="mb-0 text-secondary fw-semibold">Customer:</p><p class="fs-5 fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?> <small class="text-muted">(ID: <?php echo $order['customer_id']; ?>)</small></p></div>
                                <div class="col-md-6"><p class="mb-0 text-secondary fw-semibold">Source / Table:</p><p class="fs-5 fw-bold text-info"><?php echo $order['order_type']; ?></p></div>
                                <div class="col-md-6"><p class="mb-0 text-secondary fw-semibold">Time Placed:</p><p class="fs-5 fw-bold"><?php echo date('h:i A (M d, Y)', strtotime($order['created_at'])); ?></p></div>
                                <div class="col-md-6"><p class="mb-0 text-secondary fw-semibold">Payment Method:</p><p class="fs-5 fw-bold"><span class="badge bg-success"><?php echo ucfirst($order['payment_method']); ?> Balance</span></p></div>
                            </div>
                        </div>

                        <div class="card card-pos bg-white p-4">
                            <h3 class="fs-4 fw-bold text-success mb-3 border-bottom pb-2"><i class="bi bi-list-check me-2"></i> Itemized List (<?php echo count($order_items); ?> Items)</h3>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($order_items as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                                    <div><span class="badge bg-dark me-2">x <?php echo $item['quantity']; ?></span><span class="fw-bold fs-5"><?php echo htmlspecialchars($item['item_name']); ?></span><div class="mt-1"><small class="text-muted">₱<?php echo number_format($item['unit_price'], 2); ?> each</small></div></div>
                                    <div class="text-end"><p class="mb-0 text-dark fw-semibold fs-5">₱ <?php echo number_format($item['subtotal'], 2); ?></p></div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card card-pos bg-white p-4 sticky-summary">
                            <h3 class="fs-4 fw-bold text-success mb-3 border-bottom pb-2"><i class="bi bi-currency-peso me-2"></i> Payment Summary</h3>
                            <div class="py-4 border-top border-bottom mb-4">
                                <div class="d-flex justify-content-between fw-semibold mb-2">
                                    <span>Subtotal:</span>
                                    <span>₱ <?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between fs-3 fw-bold text-danger pt-2 mt-2 border-top">
                                    <span>GRAND TOTAL:</span>
                                    <span>₱ <?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                            </div>
                            <div class="p-3 bg-light rounded-3 mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-secondary">Payment Method:</span>
                                    <span class="fw-bold text-success"><?php echo ucfirst($order['payment_method']); ?> Balance</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-secondary">Amount Paid:</span>
                                    <span class="fw-bold">₱ <?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-secondary">Customer Balance:</span>
                                    <span class="fw-bold text-muted">₱ <?php echo number_format($order['customer_balance'], 2); ?></span>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <?php if ($order['status'] == 'New'): ?>
                                    <button class="btn btn-warning fw-bold py-2" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'Preparing')"><i class="bi bi-arrow-right-circle me-2"></i> Start Preparing</button>
                                <?php endif; ?>
                                <?php if ($order['status'] == 'Preparing'): ?>
                                    <button class="btn btn-primary fw-bold py-2" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'Ready')"><i class="bi bi-check-circle-fill me-2"></i> Mark as Ready</button>
                                <?php endif; ?>
                                <?php if ($order['status'] == 'Ready'): ?>
                                    <button class="btn btn-success fw-bold py-2" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'Completed')"><i class="bi bi-check-double me-2"></i> Mark as Completed</button>
                                <?php endif; ?>
                                <?php if (!in_array($order['status'], ['Completed', 'Cancelled'])): ?>
                                    <button class="btn btn-outline-danger fw-bold py-2" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'Cancelled')"><i class="bi bi-x-circle-fill me-2"></i> Cancel Order</button>
                                <?php endif; ?>
                                <button class="btn btn-outline-primary fw-bold py-2" onclick="printReceipt()"><i class="bi bi-printer-fill me-2"></i> Print Receipt</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- RECEIPT VIEW (Hidden, appears when printing) -->
        <div class="receipt-view">
            <div class="receipt-container" id="receiptContainer">
                <div class="receipt-header">
                    <div class="store-name">CHIBUGAN Carinderia</div>
                    <p>Brgy. Cagangohan, Panabo City</p>
                    <p>Tel: 0995-102-1259</p>
                    <p>GST: 123-456-789</p>
                    <div class="receipt-divider"></div>
                    <p><strong>OFFICIAL RECEIPT</strong></p>
                    <p>Order #: <?php echo htmlspecialchars($order['order_code']); ?></p>
                    <p>Date: <?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?></p>
                    <p>Cashier: <?php echo htmlspecialchars($user_name); ?></p>
                    <div class="receipt-divider"></div>
                </div>
                
                <div class="receipt-customer">
                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p><strong>Order Type:</strong> <?php echo $order['order_type']; ?></p>
                    <div class="receipt-divider"></div>
                </div>
                
                <table class="receipt-items">
                    <thead>
                        <tr><th style="text-align:left">Item</th><th style="text-align:center">Qty</th><th style="text-align:right">Price</th><th style="text-align:right">Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td style="text-align:left"><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td style="text-align:center"><?php echo $item['quantity']; ?></td>
                            <td style="text-align:right">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td style="text-align:right">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="receipt-divider"></div>
                
                <div class="receipt-total">
                    <div class="d-flex justify-content-between"><span>Subtotal:</span><span>₱ <?php echo number_format($subtotal, 2); ?></span></div>
                    <div class="d-flex justify-content-between receipt-total-line"><strong>TOTAL:</strong><strong>₱ <?php echo number_format($order['total_amount'], 2); ?></strong></div>
                </div>
                
                <div class="receipt-divider"></div>
                
                <div class="receipt-payment">
                    <div class="d-flex justify-content-between"><span>Payment Method:</span><span><?php echo ucfirst($order['payment_method']); ?> Balance</span></div>
                    <div class="d-flex justify-content-between"><span>Amount Paid:</span><span>₱ <?php echo number_format($order['total_amount'], 2); ?></span></div>
                    <div class="d-flex justify-content-between"><span>Customer Balance:</span><span>₱ <?php echo number_format($order['customer_balance'], 2); ?></span></div>
                </div>
                
                <div class="receipt-divider"></div>
                
                <div class="receipt-footer">
                    <p>Thank you for dining with us!</p>
                    <p>Please come again</p>
                    <p>This is a computer generated receipt</p>
                    <p><?php echo date('Y-m-d h:i A'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER - MOVED OUTSIDE .normal-view so it always appears at bottom -->
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
        const toast = document.createElement('div');
        toast.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' alert-dismissible fade show position-fixed top-0 end-0 m-3 shadow';
        toast.style.zIndex = '9999';
        toast.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 3000);
    }
    
    function updateOrderStatus(orderId, newStatus) { 
        if (!confirm('Update this order to "' + newStatus + '"?')) return; 
        fetch('../api/update_order_status.php', { 
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ order_id: orderId, status: newStatus }) 
        }).then(function(res) { return res.json(); }).then(function(data) { 
            if (data.success) { 
                showToast(data.message); 
                setTimeout(function() { location.reload(); }, 1000); 
            } else { 
                showToast(data.message, 'danger'); 
            } 
        }).catch(function(error) { showToast('Error updating order status', 'danger'); }); 
    }
    
    function printReceipt() {
        var normalViews = document.querySelectorAll('.normal-view');
        var receiptViews = document.querySelectorAll('.receipt-view');
        for (var i = 0; i < normalViews.length; i++) {
            normalViews[i].style.display = 'none';
        }
        for (var i = 0; i < receiptViews.length; i++) {
            receiptViews[i].style.display = 'block';
        }
        window.print();
        setTimeout(function() {
            for (var i = 0; i < normalViews.length; i++) {
                normalViews[i].style.display = 'block';
            }
            for (var i = 0; i < receiptViews.length; i++) {
                receiptViews[i].style.display = 'none';
            }
        }, 500);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        var receiptViews = document.querySelectorAll('.receipt-view');
        var normalViews = document.querySelectorAll('.normal-view');
        for (var i = 0; i < receiptViews.length; i++) {
            receiptViews[i].style.display = 'none';
        }
        for (var i = 0; i < normalViews.length; i++) {
            normalViews[i].style.display = 'block';
        }
    });
</script>
</body>
</html>