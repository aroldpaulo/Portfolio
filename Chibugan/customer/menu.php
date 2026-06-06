<?php
// customer/menu.php - WITH WORKING WALLET & TOP-UP MODAL
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

// Get customer data using stored procedure
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
        $_SESSION['phone_number'] = $user_data['phone_number'] ?? '';
    }
    
    // ========== GET MENU ITEMS USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_customer_get_menu_items()");
    $stmt->execute();
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // ========== GET TRANSACTIONS FOR WALLET MODAL ==========
    $stmt = $pdo->prepare("CALL sp_customer_get_transactions(?, 10)");
    $stmt->execute([$_SESSION['user_id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
} catch(PDOException $e) {
    $menu_items = [];
    $transactions = [];
    error_log("Database error in menu: " . $e->getMessage());
}

$user_email = $_SESSION['email'] ?? '';
$user_balance = $_SESSION['credit_balance'] ?? 0;
$user_phone = $_SESSION['phone_number'] ?? '';
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
    <title>CHIBUGAN Customer Menu</title>
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
        /* Wallet Modal Styles */
        .wallet-status-card {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            border-radius: 16px;
            padding: 25px;
            color: white;
            text-align: center;
        }
        .transaction-table {
            max-height: 300px;
            overflow-y: auto;
        }
        .quick-amount-btn {
            margin: 3px;
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
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-house-door-fill me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="menu.php"><i class="bi bi-list-check me-1"></i>Menu</a></li>
                    <li class="nav-item"><a class="nav-link" href="orders.php"><i class="bi bi-clock-history me-1"></i>Orders</a></li>
                    
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
                        <a class="nav-link dropdown-toggle nav-link-profile" href="#" data-bs-toggle="dropdown" role="button">
                            <?php echo $nav_avatar_html; ?>
                            <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                            <li><div class="dropdown-profile-header">
                                <?php echo $dropdown_avatar_html; ?>
                                <div class="dropdown-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                                <div class="dropdown-email"><?php echo htmlspecialchars($user_email); ?></div>
                            </div></li>
                            <li><a class="dropdown-item dropdown-item-custom" href="profile.php"><i class="bi bi-person-circle"></i> My Profile</a></li>
                            <li><a class="dropdown-item dropdown-item-custom" href="#" data-bs-toggle="modal" data-bs-target="#walletModal"><i class="bi bi-wallet2"></i> My Wallet <span class="badge bg-success float-end">₱<?php echo number_format($user_balance, 2); ?></span></a></li>
                            <li><a class="dropdown-item dropdown-item-custom" href="orders.php"><i class="bi bi-clock-history"></i> My Orders</a></li>
                            <li><a class="dropdown-item dropdown-item-custom" href="menu.php"><i class="bi bi-cart-plus"></i> Order Now</a></li>
                            <li><hr class="dropdown-divider-custom"></li>
                            <li><a class="dropdown-item dropdown-item-custom text-danger" href="../api/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            <li><div class="dropdown-footer"><i class="bi bi-shield-check"></i> Secure Connection</div></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ========== MAIN CONTENT ========== -->
    <div class="content">
        <div class="container py-4">
            <div class="row g-4">
                
                <!-- Menu Items Panel - Left Side (8 columns) -->
                <div class="col-lg-8">
                    <!-- Category Pills -->
                    <ul class="nav nav-pills mb-3 bg-white p-2 rounded-3 shadow-sm" id="pills-tab" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-category="all">All Items</button></li>
                        <li class="nav-item"><button class="nav-link" data-category="Main Dish">Main Dish</button></li>
                        <li class="nav-item"><button class="nav-link" data-category="Drinks">Drinks</button></li>
                        <li class="nav-item"><button class="nav-link" data-category="Sides">Sides</button></li>
                        <li class="nav-item"><button class="nav-link" data-category="Desserts">Desserts</button></li>
                    </ul>

                    <!-- Menu Items Grid -->
                    <div class="row g-3" id="availableItemsContainer">
                        <?php if (empty($menu_items)): ?>
                            <div class="col-12 text-center text-muted py-5">
                                <i class="bi bi-emoji-frown fs-1"></i>
                                <p>No menu items available at the moment.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($menu_items as $item): 
                                $quantity = $item['quantity'] ?? 0;
                                $low_stock_threshold = $item['low_stock_threshold'] ?? 5;
                                $category = $item['category'] ?? 'Main Dish';
                                $image_path = $item['image'] ?? null;
                                
                                $stock_status = '';
                                if ($quantity == 0) {
                                    $stock_status = 'SOLD OUT';
                                } elseif ($quantity <= $low_stock_threshold) {
                                    $stock_status = 'LOW STOCK';
                                } else {
                                    $stock_status = 'AVAILABLE';
                                }
                                $status_badge_class = $quantity == 0 ? 'bg-danger' : ($quantity <= $low_stock_threshold ? 'bg-warning text-dark' : 'bg-success');
                                $quantity_class = $quantity == 0 ? 'quantity-out' : ($quantity <= $low_stock_threshold ? 'quantity-low' : 'quantity-good');
                                
                                $image_html = '';
                                if ($image_path && file_exists('../' . $image_path)) {
                                    $image_html = '<img src="../' . $image_path . '" class="menu-item-image" alt="' . htmlspecialchars($item['name']) . '">';
                                } else {
                                    $icon_class = ($category == 'Main Dish') ? 'bi-egg-fried' : (($category == 'Drinks') ? 'bi-cup-straw' : (($category == 'Desserts') ? 'bi-cake2' : 'bi-egg-fried'));
                                    $icon_text = ($category == 'Main Dish') ? 'Main Dish' : (($category == 'Drinks') ? 'Drink' : (($category == 'Desserts') ? 'Dessert' : 'Side Dish'));
                                    $image_html = '<div class="no-image-placeholder">
                                        <i class="bi ' . $icon_class . '"></i>
                                        <span>' . $icon_text . '</span>
                                    </div>';
                                }
                            ?>
                            <div class="col-6 col-md-4 col-xl-3 menu-item-category" data-category="<?php echo htmlspecialchars($category); ?>">
                                <div class="card menu-item-card h-100">
                                    <div class="image-container">
                                        <?php echo $image_html; ?>
                                        <span class="badge <?php echo $status_badge_class; ?> status-badge"><?php echo $stock_status; ?></span>
                                    </div>
                                    <div class="card-body p-3">
                                        <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($category); ?></span>
                                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($item['name'] ?? 'Unknown'); ?></h6>
                                        <p class="fw-bold text-success mb-2">₱ <?php echo number_format($item['price'] ?? 0, 2); ?></p>
                                        <span class="quantity-display <?php echo $quantity_class; ?> mb-2 d-inline-block"><?php echo $quantity; ?> left</span>
                                        
                                        <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                                            <div class="d-flex align-items-center gap-1">
                                                <button class="btn btn-sm btn-outline-secondary minus-btn" data-target="quantity-<?php echo $item['id']; ?>" <?php echo $quantity == 0 ? 'disabled' : ''; ?>><i class="bi bi-dash"></i></button>
                                                <input type="number" class="form-control form-control-sm text-center quantity-input" 
                                                       id="quantity-<?php echo $item['id']; ?>" value="1" min="0" max="<?php echo $quantity; ?>" 
                                                       data-max-quantity="<?php echo $quantity; ?>" <?php echo $quantity == 0 ? 'disabled' : ''; ?>>
                                                <button class="btn btn-sm btn-outline-secondary plus-btn" data-target="quantity-<?php echo $item['id']; ?>" <?php echo $quantity == 0 ? 'disabled' : ''; ?>><i class="bi bi-plus"></i></button>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2 mt-3">
                                            <?php if ($quantity > 0): ?>
                                                <button class="btn add-to-order-btn btn-sm" data-item-id="<?php echo $item['id']; ?>" data-item-name="<?php echo htmlspecialchars($item['name']); ?>" data-item-price="<?php echo $item['price']; ?>"><i class="bi bi-plus-circle-fill"></i> Add</button>
                                                <button class="btn buy-now-btn btn-sm" data-item-id="<?php echo $item['id']; ?>" data-item-name="<?php echo htmlspecialchars($item['name']); ?>" data-item-price="<?php echo $item['price']; ?>"><i class="bi bi-lightning-fill"></i> Buy Now</button>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm disabled" disabled><i class="bi bi-x-circle-fill"></i> Sold Out</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Order Panel - Right Side (4 columns) -->
                <div class="col-lg-4">
                    <div class="order-panel">
                        <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-receipt me-2 text-success"></i>Your Order</h5>

                        <!-- Credit Balance -->
                        <div class="credit-balance">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="opacity-75">Current User:</small>
                                    <h6 class="mb-0 fw-bold text-white"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Customer'); ?></h6>
                                </div>
                                <div class="text-end">
                                    <small class="opacity-75">Balance</small>
                                    <h5 class="mb-0 fw-bold text-white">₱ <?php echo number_format($user_balance, 2); ?></h5>
                                </div>
                            </div>
                        </div>

                        <!-- Order Type -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-dark">Order Type</label>
                            <select id="orderType" class="form-select form-select-sm">
                                <option value="Take-Out">Take-Out</option>
                                <option value="Dine-In">Dine-In</option>
                                <option value="Delivery">Delivery</option>
                            </select>
                        </div>
                        
                        <!-- Order Items List -->
                        <div class="order-item-list mb-3 p-2">
                            <ul class="list-group list-group-flush bg-transparent" id="orderItemsList">
                                <li class="list-group-item text-center text-muted bg-transparent">No items added yet</li>
                            </ul>
                        </div>
                        
                        <!-- Order Summary -->
                        <div class="summary-area">
                            <div class="d-flex justify-content-between summary-row">
                                <span class="text-dark">Subtotal:</span>
                                <span class="fw-bold text-dark" id="subtotal">₱ 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between summary-row border-bottom pb-2 mb-2">
                                <span class="fs-6 fw-bold text-dark">TOTAL:</span>
                                <span class="fs-5 fw-bold text-success" id="totalDue">₱ 0.00</span>
                            </div>
                            <div class="small text-muted">
                                <i class="bi bi-wallet2 me-1"></i> Auto deduct from credits
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <div class="d-grid gap-2">
                                <button class="btn btn-process py-2" id="processPaymentBtn" disabled>
                                    <i class="bi bi-credit-card-fill me-2"></i> Process Order
                                </button>
                                <button class="btn btn-outline-clear py-2" id="clearOrderBtn" disabled>
                                    <i class="bi bi-trash-fill me-2"></i> Clear Order
                                </button>
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
                <div class="modal-body p-4">
                    
                    <!-- Balance Card -->
                    <div class="wallet-status-card mb-4">
                        <i class="bi bi-credit-card-2-front fs-1 mb-2 d-block"></i>
                        <h2 class="fw-bolder mb-0">₱ <?php echo number_format($user_balance, 2); ?></h2>
                        <p class="mb-0 opacity-75">Available Credits</p>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="d-grid gap-2 mb-4">
                        <button class="btn btn-success py-3 fw-bold" type="button" data-bs-toggle="modal" data-bs-target="#topupRequestModal" data-bs-dismiss="modal">
                            <i class="bi bi-cash-stack me-2"></i>Request Credit Top-Up
                        </button>
                    </div>
                    
                    <!-- Customer Info -->
                    <div class="alert alert-light border mb-3">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Account Name</small>
                                <p class="fw-bold mb-0"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Customer'); ?></p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Phone Number</small>
                                <p class="fw-bold mb-0"><?php echo htmlspecialchars($user_phone ?: 'Not provided'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Transactions -->
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-success"></i>Recent Transactions</h6>
                    
                    <?php if (empty($transactions)): ?>
                        <div class="text-center text-muted py-4 bg-light rounded">
                            <i class="bi bi-credit-card fs-1 opacity-25"></i>
                            <p class="mt-2 mb-0">No transactions found.</p>
                            <small>Make your first order to see transactions here.</small>
                        </div>
                    <?php else: ?>
                        <div class="transaction-table">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th class="text-end">Amount</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $trans): ?>
                                    <tr>
                                        <td><small><?php echo date('M d, Y h:i A', strtotime($trans['created_at'])); ?></small></td>
                                        <td>
                                            <?php if ($trans['type'] == 'Top-Up'): ?>
                                                <span class="badge bg-success">+ Top-Up</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">- Spend</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end <?php echo $trans['type'] == 'Top-Up' ? 'text-success' : 'text-danger'; ?> fw-bold">
                                            <?php echo $trans['type'] == 'Top-Up' ? '+' : '-'; ?> ₱<?php echo number_format($trans['amount'], 2); ?>
                                        </td>
                                        <td><small><?php echo htmlspecialchars(substr($trans['reason'], 0, 40)); ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Wallet Info Note -->
                    <div class="alert alert-info mt-3 small">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Credits are used automatically when you place orders. Top-up requests need cashier approval.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="menu.php" class="btn btn-success">
                        <i class="bi bi-cart-plus me-1"></i> Order Now
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== TOP-UP REQUEST MODAL ========== -->
    <div class="modal fade" id="topupRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-cash-stack me-2"></i>Credit Top-Up Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="topupForm">
                    <div class="modal-body">
                        <p>Enter the amount you wish to top up. After submitting, please wait for cashier approval.</p>
                    
                        
                        <div class="form-group">
                            <label class="form-label fw-semibold">Top-Up Amount (₱)</label>
                            <input type="number" id="topupAmount" class="form-control p-3 fs-4 text-center" required min="50" max="50000" step="any" placeholder="Enter amount">
                            <div class="form-text mt-2 text-center">Minimum: ₱50 | Maximum: ₱50,000</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-bold px-4">
                            <i class="bi bi-send me-2"></i>Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Buy Now Confirmation Modal -->
    <div class="modal fade" id="buyNowConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title text-white"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Purchase</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3"><i class="bi bi-cart-check-fill text-success" style="font-size: 2.5rem;"></i></div>
                    <h6 class="text-center fw-bold mb-3 text-dark" id="buyNowItemName">Item Name</h6>
                    <div class="alert alert-success-custom">
                        <div class="d-flex justify-content-between"><span class="text-dark">Quantity:</span><span class="fw-bold text-dark" id="buyNowQuantity">1</span></div>
                        <div class="d-flex justify-content-between mt-2"><span class="text-dark">Price:</span><span class="fw-bold text-dark" id="buyNowPrice">₱ 0.00</span></div>
                        <div class="d-flex justify-content-between mt-2 border-top pt-2"><span class="fw-bold text-dark">Total:</span><span class="fw-bold fs-5 text-success" id="buyNowTotal">₱ 0.00</span></div>
                    </div>
                    <div class="alert alert-success">
                        <div class="d-flex justify-content-between"><span class="text-dark">Your Balance:</span><span class="fw-bold text-dark" id="buyNowBalance">₱ <?php echo number_format($user_balance, 2); ?></span></div>
                        <div class="d-flex justify-content-between mt-2"><span class="text-dark">After Purchase:</span><span class="fw-bold text-dark" id="buyNowAfterBalance">₱ <?php echo number_format($user_balance, 2); ?></span></div>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-secondary w-50" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success w-50" id="confirmBuyNowBtn">Confirm Purchase</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Confirmation Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title text-white"><i class="bi bi-check-circle-fill me-2"></i>Confirm Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="orderSummaryContent"></div>
                    <div class="customer-info mt-3 p-3 bg-light rounded">
                        <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-person me-2"></i>Ordering as:</h6>
                        <span id="customerNameDisplay" class="fw-semibold text-dark"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Customer'); ?></span>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Edit Order</button>
                    <button type="button" class="btn btn-success fw-bold" id="confirmPaymentBtn">Confirm Order</button>
                </div>
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
    var userBalance = <?php echo $user_balance; ?>;
    var cart = [];
    var currentBuyNowItem = null;
    
    function showToast(message, type) {
        if (typeof type === 'undefined') type = 'success';
        var container = document.querySelector('.toast-notification');
        var toast = document.createElement('div');
        toast.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' alert-dismissible fade show shadow';
        toast.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        container.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 5000);
    }
    
    function setAmount(amount) {
        document.getElementById('topupAmount').value = amount;
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
    
    function getItemAvailableQuantity(itemId) {
        var input = document.getElementById('quantity-' + itemId);
        return input ? parseInt(input.dataset.maxQuantity) || 0 : 0;
    }
    
    function addToCart(id, name, price, quantity) {
        var itemId = parseInt(id);
        var available = getItemAvailableQuantity(itemId);
        if (quantity > available) { showToast('Only ' + available + ' available!', 'warning'); return; }
        
        var existing = cart.find(function(item) { return item.id === itemId; });
        if (existing) {
            if (existing.quantity + quantity > available) { showToast('Max ' + available + ' units!', 'warning'); return; }
            existing.quantity += quantity;
        } else {
            cart.push({ id: itemId, name: name, price: price, quantity: quantity, availableQuantity: available });
        }
        updateCartDisplay();
        showToast(quantity + 'x ' + name + ' added!');
    }
    
    function updateCartDisplay() {
        var list = document.getElementById('orderItemsList');
        var subtotalSpan = document.getElementById('subtotal');
        var totalSpan = document.getElementById('totalDue');
        var processBtn = document.getElementById('processPaymentBtn');
        var clearBtn = document.getElementById('clearOrderBtn');
        
        if (cart.length === 0) {
            list.innerHTML = '<li class="list-group-item text-center text-muted bg-transparent">No items added yet</li>';
            subtotalSpan.textContent = '₱ 0.00';
            totalSpan.textContent = '₱ 0.00';
            processBtn.disabled = true;
            clearBtn.disabled = true;
            return;
        }
        
        var subtotal = 0;
        list.innerHTML = '';
        for (var i = 0; i < cart.length; i++) {
            var item = cart[i];
            var itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            list.innerHTML += '<li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0">' +
                '<div><span class="badge bg-success me-2">' + item.quantity + 'x</span> <span class="text-dark">' + item.name + '</span></div>' +
                '<div class="d-flex align-items-center gap-2">' +
                '<span class="fw-bold text-dark">₱ ' + itemTotal.toFixed(2) + '</span>' +
                '<button class="btn btn-sm btn-outline-danger py-0 px-1 remove-item" data-id="' + item.id + '"><i class="bi bi-trash"></i></button>' +
                '</div></li>';
        }
        
        var removeBtns = document.querySelectorAll('.remove-item');
        for (var i = 0; i < removeBtns.length; i++) {
            removeBtns[i].addEventListener('click', function() {
                removeFromCart(parseInt(this.dataset.id));
            });
        }
        
        subtotalSpan.textContent = '₱ ' + subtotal.toFixed(2);
        totalSpan.textContent = '₱ ' + subtotal.toFixed(2);
        processBtn.disabled = false;
        clearBtn.disabled = false;
    }
    
    function removeFromCart(id) {
        cart = cart.filter(function(item) { return item.id !== id; });
        updateCartDisplay();
        showToast('Item removed', 'warning');
    }
    
    function clearCart() {
        cart = [];
        updateCartDisplay();
        showToast('Order cleared', 'info');
    }
    
    function showOrderSummary() {
        if (cart.length === 0) { showToast('Cart is empty!', 'warning'); return; }
        var subtotal = 0;
        for (var i = 0; i < cart.length; i++) {
            subtotal += cart[i].price * cart[i].quantity;
        }
        var html = '<div class="card border-success mb-3"><div class="card-body p-3">' +
            '<h6 class="fw-bold mb-2 text-dark">Order for ' + document.getElementById('orderType').value + ':</h6>' +
            '<ul class="list-unstyled mb-0">';
        for (var i = 0; i < cart.length; i++) {
            html += '<li class="d-flex justify-content-between small mb-1"><span class="text-dark">' + cart[i].quantity + 'x ' + cart[i].name + '</span><span class="text-dark">₱ ' + (cart[i].price * cart[i].quantity).toFixed(2) + '</span></li>';
        }
        html += '</ul><hr><div class="d-flex justify-content-between fw-bold"><span class="text-dark">Total:</span><span class="text-success">₱ ' + subtotal.toFixed(2) + '</span></div></div></div>' +
            '<div class="alert ' + (subtotal > userBalance ? 'alert-danger' : 'alert-success') + ' fw-bold text-dark">Total Due: ₱ ' + subtotal.toFixed(2) + '</div>';
        document.getElementById('orderSummaryContent').innerHTML = html;
        document.getElementById('confirmPaymentBtn').disabled = subtotal > userBalance;
        new bootstrap.Modal(document.getElementById('paymentModal')).show();
    }
    
    async function processOrder() {
        var subtotal = 0;
        for (var i = 0; i < cart.length; i++) {
            subtotal += cart[i].price * cart[i].quantity;
        }
        if (subtotal > userBalance) { showToast('Insufficient balance!', 'danger'); return; }
        
        var orderData = {
            items: cart.map(function(item) {
                return { id: item.id, name: item.name, price: item.price, quantity: item.quantity };
            }),
            order_type: document.getElementById('orderType').value,
            total_amount: subtotal
        };
        
        showSpinner(true);
        
        try {
            var response = await fetch('../api/place_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(orderData)
            });
            
            var data = await response.json();
            showSpinner(false);
            
            if (data.success) {
                showToast(data.message, 'success');
                userBalance = data.new_balance;
                document.querySelector('.credit-badge').innerHTML = '<i class="bi bi-credit-card-fill me-1"></i> ₱' + data.new_balance.toFixed(2);
                document.querySelector('.credit-balance h5').textContent = '₱ ' + data.new_balance.toFixed(2);
                clearCart();
                var modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                if (modal) modal.hide();
                setTimeout(function() {
                    window.location.href = 'orders.php';
                }, 2000);
            } else {
                showToast(data.message, 'danger');
            }
        } catch (error) {
            showSpinner(false);
            showToast('Error placing order: ' + error.message, 'danger');
        }
    }
    
    async function processBuyNow() {
        if (!currentBuyNowItem) return;
        
        var total = currentBuyNowItem.price * currentBuyNowItem.quantity;
        
        var orderData = {
            items: [{
                id: currentBuyNowItem.id,
                name: currentBuyNowItem.name,
                price: currentBuyNowItem.price,
                quantity: currentBuyNowItem.quantity
            }],
            order_type: document.getElementById('orderType').value,
            total_amount: total
        };
        
        showSpinner(true);
        
        try {
            var response = await fetch('../api/place_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(orderData)
            });
            
            var data = await response.json();
            showSpinner(false);
            
            if (data.success) {
                showToast(data.message, 'success');
                userBalance = data.new_balance;
                document.querySelector('.credit-badge').innerHTML = '<i class="bi bi-credit-card-fill me-1"></i> ₱' + data.new_balance.toFixed(2);
                document.querySelector('.credit-balance h5').textContent = '₱ ' + data.new_balance.toFixed(2);
                
                var modal = bootstrap.Modal.getInstance(document.getElementById('buyNowConfirmModal'));
                if (modal) modal.hide();
                setTimeout(function() {
                    window.location.href = 'orders.php';
                }, 2000);
            } else {
                showToast(data.message, 'danger');
            }
        } catch (error) {
            showSpinner(false);
            showToast('Error placing order: ' + error.message, 'danger');
        }
    }
    
    function filterByCategory(category) {
        var allItems = document.querySelectorAll('.menu-item-category');
        
        if (category === 'all') {
            for (var i = 0; i < allItems.length; i++) {
                allItems[i].style.display = '';
            }
        } else {
            for (var i = 0; i < allItems.length; i++) {
                if (allItems[i].getAttribute('data-category') === category) {
                    allItems[i].style.display = '';
                } else {
                    allItems[i].style.display = 'none';
                }
            }
        }
    }
    
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
            
            // Show loading on button
            var submitBtn = this.querySelector('button[type="submit"]');
            var originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            
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
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
    
    // Event Listeners
    document.addEventListener('DOMContentLoaded', function() {
        var filterButtons = document.querySelectorAll('#pills-tab .nav-link');
        for (var i = 0; i < filterButtons.length; i++) {
            filterButtons[i].addEventListener('click', function() {
                var category = this.getAttribute('data-category');
                for (var j = 0; j < filterButtons.length; j++) {
                    filterButtons[j].classList.remove('active');
                }
                this.classList.add('active');
                filterByCategory(category);
            });
        }
        
        var addButtons = document.querySelectorAll('.add-to-order-btn');
        for (var i = 0; i < addButtons.length; i++) {
            addButtons[i].addEventListener('click', function() {
                var card = this.closest('.menu-item-card');
                var qtyInput = card.querySelector('.quantity-input');
                var qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
                addToCart(this.dataset.itemId, this.dataset.itemName, parseFloat(this.dataset.itemPrice), qty);
                if (qtyInput) qtyInput.value = 1;
            });
        }
        
        var buyNowBtns = document.querySelectorAll('.buy-now-btn');
        for (var i = 0; i < buyNowBtns.length; i++) {
            buyNowBtns[i].addEventListener('click', function() {
                var card = this.closest('.menu-item-card');
                var qtyInput = card.querySelector('.quantity-input');
                var qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
                var available = getItemAvailableQuantity(this.dataset.itemId);
                if (qty > available) { showToast('Only ' + available + ' available!', 'warning'); return; }
                var total = parseFloat(this.dataset.itemPrice) * qty;
                if (total > userBalance) { showToast('Need ₱' + (total - userBalance).toFixed(2) + ' more!', 'danger'); return; }
                currentBuyNowItem = { id: this.dataset.itemId, name: this.dataset.itemName, price: parseFloat(this.dataset.itemPrice), quantity: qty };
                document.getElementById('buyNowItemName').textContent = this.dataset.itemName;
                document.getElementById('buyNowQuantity').textContent = qty;
                document.getElementById('buyNowPrice').textContent = '₱ ' + parseFloat(this.dataset.itemPrice).toFixed(2);
                document.getElementById('buyNowTotal').textContent = '₱ ' + total.toFixed(2);
                document.getElementById('buyNowAfterBalance').textContent = '₱ ' + (userBalance - total).toFixed(2);
                new bootstrap.Modal(document.getElementById('buyNowConfirmModal')).show();
            });
        }
        
        var plusBtns = document.querySelectorAll('.plus-btn');
        for (var i = 0; i < plusBtns.length; i++) {
            plusBtns[i].addEventListener('click', function() {
                var input = document.getElementById(this.dataset.target);
                if (input && !input.disabled) {
                    var max = parseInt(input.dataset.maxQuantity);
                    var val = parseInt(input.value) || 0;
                    if (val < max) input.value = val + 1;
                }
            });
        }
        
        var minusBtns = document.querySelectorAll('.minus-btn');
        for (var i = 0; i < minusBtns.length; i++) {
            minusBtns[i].addEventListener('click', function() {
                var input = document.getElementById(this.dataset.target);
                if (input && !input.disabled) {
                    var val = parseInt(input.value) || 1;
                    if (val > 1) input.value = val - 1;
                }
            });
        }
        
        var clearBtn = document.getElementById('clearOrderBtn');
        if (clearBtn) clearBtn.addEventListener('click', clearCart);
        
        var processBtn = document.getElementById('processPaymentBtn');
        if (processBtn) processBtn.addEventListener('click', showOrderSummary);
        
        var confirmBtn = document.getElementById('confirmPaymentBtn');
        if (confirmBtn) confirmBtn.addEventListener('click', processOrder);
        
        var confirmBuyNow = document.getElementById('confirmBuyNowBtn');
        if (confirmBuyNow) confirmBuyNow.addEventListener('click', processBuyNow);
    });
</script>
</body>
</html>