
<?php
// cashier/customer_wallet.php - FIXED: NO RAW SQL
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'cashier' && $_SESSION['role'] != 'admin')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$pending_requests = [];
$pending_count = 0;
$user_name = $_SESSION['full_name'] ?? 'Cashier';
$user_email = $_SESSION['email'] ?? '';
$profile_picture = $_SESSION['profile_picture'] ?? null;

try {
    // Get user profile - returns result set directly
    $stmt = $pdo->prepare("CALL sp_get_user_profile(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    $stmt->nextRowset();
    
    if ($user_data) {
        $user_name = $user_data['full_name'];
        $user_email = $user_data['email'];
        $profile_picture = $user_data['profile_picture'];
        $_SESSION['full_name'] = $user_name;
        $_SESSION['email'] = $user_email;
        $_SESSION['profile_picture'] = $profile_picture;
    }
    
    // Get pending top-ups - returns result set directly
    $stmt = $pdo->prepare("CALL sp_get_pending_topups()");
    $stmt->execute();
    $pending_requests = $stmt->fetchAll();
    $stmt->nextRowset();
    
    // Get pending count - returns result set directly (NO @variables!)
    $stmt = $pdo->prepare("CALL sp_get_pending_count()");
    $stmt->execute();
    $result = $stmt->fetch();
    $pending_count = $result['pending_count'] ?? 0;
    $stmt->nextRowset();
    
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

$user_role = $_SESSION['role'] ?? 'cashier';

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
    <title>Customer Wallet | CHIBUGAN Cashier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="cashier.css">
                <link rel="icon" type="image/x-icon" href="../logo.jpg">

    <link rel="stylesheet" href="../css/cashier.css">
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
            <a href="customer_wallet.php" class="btn btn-success py-3 px-4 fw-bold border-0"><i class="bi bi-credit-card-fill me-1"></i> Customer Wallet</a>
            <a href="settings.php" class="btn text-white py-3 px-4 fw-semibold opacity-75"><i class="bi bi-gear me-1"></i> Settings</a>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid px-4 mt-4 pb-5">
            <h1 class="fs-2 fw-bold text-dark mb-4"><i class="bi bi-wallet2 me-2 text-success"></i> Customer Credit Management</h1>
            <p class="text-secondary mb-4">Search for a customer and add credit directly to their wallet.</p>
            
            <div class="row g-4">
                <!-- PENDING REQUESTS SECTION -->
                <div class="col-lg-5">
                    <div class="card card-pos bg-white p-4">
                        <h3 class="fs-4 fw-bold text-dark mb-3"><i class="bi bi-list-check me-2 text-warning"></i> Pending Top-Up Requests</h3>
                        <p class="text-secondary">Customers waiting for cashier approval (from customer portal).</p>
                        
                        <?php if (empty($pending_requests)): ?>
                            <div class="text-center py-4"><i class="bi bi-check-circle fs-1 text-success"></i><p class="text-success mt-2">No pending requests.</p></div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead><tr><th>Customer</th><th class="text-end">Amount</th><th>Actions</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($pending_requests as $req): ?>
                                        <tr id="request-row-<?php echo $req['request_id']; ?>">
                                            <td><div class="fw-semibold"><?php echo htmlspecialchars($req['customer_name']); ?></div><small class="text-muted">Requested: <?php echo date('M d, h:i A', strtotime($req['created_at'])); ?></small></td>
                                            <td class="fw-bold text-end text-success">₱ <?php echo number_format($req['amount'], 2); ?></td>
                                            <td><div class="d-flex gap-2"><button class="btn btn-sm btn-approve flex-fill" onclick="approveRequest(<?php echo $req['request_id']; ?>, '<?php echo htmlspecialchars($req['customer_name']); ?>', <?php echo $req['amount']; ?>)">Approve</button><button class="btn btn-sm btn-reject flex-fill" onclick="rejectRequest(<?php echo $req['request_id']; ?>, '<?php echo htmlspecialchars($req['customer_name']); ?>', <?php echo $req['amount']; ?>)">Reject</button></div></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- DIRECT TOP-UP SECTION -->
                <div class="col-lg-7">
                    <div class="card card-pos bg-white p-4">
                        <h3 class="fs-4 fw-bold text-dark mb-3"><i class="bi bi-search me-2 text-success"></i> Direct Top-Up (Instant Credit)</h3>
                        <p class="text-secondary">Search for a customer and add credit instantly to their wallet.</p>
                        
                        <div class="d-flex gap-2 mb-4">
                            <input type="text" id="customerSearch" class="form-control p-3" placeholder="Enter Customer Name, Email, or Phone" autocomplete="off">
                            <button class="btn btn-dark px-4 py-3" onclick="searchCustomer()"><i class="bi bi-search"></i> Search</button>
                        </div>
                        
                        <div id="searchResults" style="display: none;">
                            <div class="alert alert-info" id="searchResultMessage"></div>
                            <div id="customerList"></div>
                        </div>
                        
                        <div id="customerInfo" style="display: none;">
                            <div class="p-4 bg-light rounded-3 border position-relative">
                                <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" onclick="clearCustomer()">×</button>
                                <div class="text-center mb-3"><i class="bi bi-person-circle fs-1 text-success"></i></div>
                                <h4 class="fs-4 fw-bold text-success text-center mb-1" id="customerName"></h4>
                                <p class="text-secondary text-center mb-1" id="customerDetails"></p>
                                <hr>
                                <div class="text-center">
                                    <p class="text-uppercase text-secondary fw-semibold mb-1">Current Wallet Balance</p>
                                    <p class="balance-display fw-bolder text-success" id="customerBalance">₱ 0.00</p>
                                </div>
                            </div>
                        </div>
                        
                        <div id="topupSection" style="display: none;" class="mt-4">
                            <div class="card border-success shadow-sm">
                                <div class="card-body">
                                    <h5 class="fw-bold text-success"><i class="bi bi-cash-stack me-2"></i> Add Credit to Wallet</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Amount to Add (₱)</label>
                                            <input type="number" id="topupAmount" class="form-control form-control-lg" placeholder="Enter amount" min="1" step="any">
                                            <div class="mt-2 d-flex gap-2 flex-wrap">
                                                <button class="btn btn-outline-success btn-sm" onclick="setTopupAmount(50)">₱50</button>
                                                <button class="btn btn-outline-success btn-sm" onclick="setTopupAmount(100)">₱100</button>
                                                <button class="btn btn-outline-success btn-sm" onclick="setTopupAmount(200)">₱200</button>
                                                <button class="btn btn-outline-success btn-sm" onclick="setTopupAmount(500)">₱500</button>
                                                <button class="btn btn-outline-success btn-sm" onclick="setTopupAmount(1000)">₱1000</button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Reference / Notes</label>
                                            <textarea id="topupNotes" class="form-control" rows="3" placeholder="Optional notes for transaction..."></textarea>
                                        </div>
                                    </div>
                                    <button class="btn btn-success w-100 mt-3 py-3 fw-bold fs-5" onclick="processDirectTopup()">
                                        <i class="bi bi-wallet-fill me-2"></i> Add Credit Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center py-5">
        <div class="container"><p class="mb-0">&copy; 2025 CHIBUGAN POS System. All Rights Reserved.</p></div>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let selectedCustomer = null;
    
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed top-0 end-0 m-3 shadow`;
        toast.style.zIndex = '9999';
        toast.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    function approveRequest(requestId, customerName, amount) {
        if (!confirm(`Approve top-up of ₱${amount.toFixed(2)} for ${customerName}?`)) return;
        fetch('../api/approve_topup.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ request_id: requestId }) })
            .then(res => res.json()).then(data => { showToast(data.message); if (data.success) { const row = document.getElementById(`request-row-${requestId}`); if (row) row.remove(); } })
            .catch(error => showToast('Error: ' + error.message, 'danger'));
    }
    
    function rejectRequest(requestId, customerName, amount) {
        if (!confirm(`Reject top-up request of ₱${amount.toFixed(2)} for ${customerName}?`)) return;
        fetch('../api/reject_topup.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ request_id: requestId }) })
            .then(res => res.json()).then(data => { showToast(data.message, 'warning'); if (data.success) { const row = document.getElementById(`request-row-${requestId}`); if (row) row.remove(); } })
            .catch(error => showToast('Error: ' + error.message, 'danger'));
    }
    
    function setTopupAmount(amount) { document.getElementById('topupAmount').value = amount; }
    
    function searchCustomer() {
        const searchTerm = document.getElementById('customerSearch').value.trim();
        if (!searchTerm) { showToast('Please enter a search term', 'warning'); return; }
        showToast('Searching...', 'info');
        fetch(`../api/search_customer.php?search=${encodeURIComponent(searchTerm)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.customers && data.customers.length > 0) {
                    if (data.customers.length === 1) selectCustomer(data.customers[0]);
                    else showCustomerList(data.customers);
                } else { showToast(data.message || 'No customers found', 'warning'); }
            })
            .catch(error => { console.error('Search error:', error); showToast('Search failed: ' + error.message, 'danger'); });
    }
    
    function selectCustomer(customer) {
        selectedCustomer = customer;
        document.getElementById('customerInfo').style.display = 'block';
        document.getElementById('topupSection').style.display = 'block';
        document.getElementById('searchResults').style.display = 'none';
        document.getElementById('customerName').textContent = customer.full_name;
        document.getElementById('customerDetails').innerHTML = `ID: #${customer.id} | ${customer.email || 'No email'} | ${customer.phone_number || 'No phone'}`;
        document.getElementById('customerBalance').textContent = `₱ ${parseFloat(customer.credit_balance || 0).toFixed(2)}`;
        document.getElementById('topupAmount').value = '';
        document.getElementById('topupNotes').value = '';
        showToast(`Customer ${customer.full_name} selected`, 'success');
    }
    
    function showCustomerList(customers) {
        let html = '<div class="list-group">';
        customers.forEach(c => { 
            html += `<a href="#" class="list-group-item list-group-item-action customer-card" onclick='selectCustomer(${JSON.stringify(c)}); return false;'>
                        <div class="d-flex justify-content-between align-items-center">
                            <div><strong>${escapeHtml(c.full_name)}</strong><br><small>${escapeHtml(c.email || '')}</small></div>
                            <div class="text-end"><span class="fw-bold text-success">₱ ${parseFloat(c.credit_balance || 0).toFixed(2)}</span></div>
                        </div>
                    </a>`; 
        });
        html += '</div>';
        document.getElementById('customerList').innerHTML = html;
        document.getElementById('searchResults').style.display = 'block';
        document.getElementById('searchResultMessage').innerHTML = `Found ${customers.length} customer(s). Select one to continue.`;
        document.getElementById('customerInfo').style.display = 'none';
        document.getElementById('topupSection').style.display = 'none';
    }
    
    function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
    
    function clearCustomer() {
        selectedCustomer = null;
        document.getElementById('customerInfo').style.display = 'none';
        document.getElementById('topupSection').style.display = 'none';
        document.getElementById('searchResults').style.display = 'none';
        document.getElementById('customerSearch').value = '';
        document.getElementById('customerSearch').focus();
    }
    
    function processDirectTopup() {
        if (!selectedCustomer) { showToast('Please select a customer first', 'warning'); return; }
        const amount = parseFloat(document.getElementById('topupAmount').value);
        if (!amount || amount <= 0) { showToast('Please enter a valid amount', 'warning'); return; }
        if (!confirm(`Add ₱${amount.toFixed(2)} to ${selectedCustomer.full_name}'s wallet?\n\nThis will be added immediately.`)) return;
        showToast('Processing...', 'info');
        fetch('../api/direct_topup.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ customer_id: selectedCustomer.id, amount: amount, notes: document.getElementById('topupNotes').value || 'Cashier direct top-up' }) })
            .then(res => res.json())
            .then(data => { 
                if (data.success) { 
                    showToast(`✅ ₱${amount.toFixed(2)} added to ${selectedCustomer.full_name}'s wallet!`, 'success'); 
                    const newBalance = parseFloat(selectedCustomer.credit_balance || 0) + amount;
                    selectedCustomer.credit_balance = newBalance;
                    document.getElementById('customerBalance').textContent = `₱ ${newBalance.toFixed(2)}`;
                    document.getElementById('topupAmount').value = ''; 
                    document.getElementById('topupNotes').value = '';
                    setTimeout(() => { if (confirm('Clear customer selection?')) clearCustomer(); }, 2000);
                } else { showToast(data.message, 'danger'); } 
            })
            .catch(error => { console.error('Top-up error:', error); showToast('Error: ' + error.message, 'danger'); });
    }
</script>
</body>
</html>

