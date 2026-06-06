<?php
// admin/users.php - WITH WORKING ADD USER FUNCTIONALITY
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

try {
    // ========== USE STORED PROCEDURES (NO RAW SQL) ==========
    
    // Get admin profile
    $stmt = $pdo->prepare("CALL sp_admin_get_profile(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    if ($admin_data) {
        $_SESSION['full_name'] = $admin_data['full_name'];
        $_SESSION['email'] = $admin_data['email'];
        $_SESSION['profile_picture'] = $admin_data['profile_picture'];
    }
    
    // Get all users
    $stmt = $pdo->prepare("CALL sp_admin_get_all_users()");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
} catch(PDOException $e) {
    $users = [];
    error_log("Database error in users management: " . $e->getMessage());
}

$admin_email = $_SESSION['email'] ?? '';
$profile_picture = $_SESSION['profile_picture'] ?? null;

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
    <title>Manage Users | CHIBUGAN Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="icon" type="image/x-icon" href="../logo.jpg">
    <style>
        .navbar-admin { position: sticky; top: 0; z-index: 1030; }
        .admin-nav { position: sticky; top: 56px; z-index: 1020; }
        .dropdown-menu { z-index: 1060 !important; }
        .profile-dropdown { z-index: 1060 !important; }
        .empty-state { padding: 4rem 1rem; text-align: center; }
        .balance-cell { font-weight: 600; }
    </style>
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
                    <li><div class="dropdown-profile-header"><?php echo $dropdown_avatar_html; ?><div class="dropdown-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?></div><div class="dropdown-email"><?php echo htmlspecialchars($admin_email); ?></div></div></li>
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
<div class="admin-nav shadow-sm sticky-top sticky-nav-offset">
    <div class="container-fluid px-4 d-flex overflow-x-auto">
        <a href="dashboard.php" class="btn text-white py-3 px-4 rounded-0 fw-semibold opacity-75"><i class="bi bi-bar-chart-fill me-1"></i> Dashboard</a>
        <a href="users.php" class="btn btn-danger text-white fw-bold py-3 px-4 rounded-0 active-nav"><i class="bi bi-people-fill me-1"></i> Manage Users</a>
        <a href="menu_management.php" class="btn text-white py-3 px-4 rounded-0 fw-semibold opacity-75"><i class="bi bi-list-nested me-1"></i> Menu Management</a>
        <a href="orders.php" class="btn text-white py-3 px-4 rounded-0 fw-semibold opacity-75"><i class="bi bi-bell-fill me-1"></i> Order Monitoring</a>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="container-fluid px-4 mt-4 pb-5">

    <h1 class="fs-2 fw-bold text-danger mb-4 border-bottom pb-3">
        <i class="bi bi-person-gear me-2"></i> Manage Users
        <small class="fs-6 text-muted ms-2"><i class="bi bi-database"></i> Total Users: <?php echo count($users); ?></small>
    </h1>

    <div id="ajaxAlert" class="alert alert-dismissible fade d-none" role="alert">
        <span id="ajaxAlertMessage"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <div class="card card-admin bg-white p-4">

        <form id="searchForm" class="row mb-4 g-3 align-items-center">
            <div class="col-12 col-md-7 order-1 order-md-1">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control p-2" placeholder="Search users by name, ID, or email..." id="searchInput">
                    <button type="button" class="btn btn-outline-secondary" id="clearSearchBtn">&times;</button>
                </div>
            </div>
            <div class="col-6 col-md-3 order-2 order-md-2">
                <select name="role" class="form-select" id="roleFilter">
                    <option value="">Filter by Role</option>
                    <option value="Admin">Admin</option>
                    <option value="Cashier">Cashier</option>
                    <option value="Customer">Customer</option>
                </select>
            </div>
            <div class="col-6 col-md-2 order-3 order-md-3 text-end text-md-start">
                <button type="button" class="btn btn-danger fw-bold text-nowrap w-100" data-bs-toggle="modal" data-bs-target="#roleSelectModal">
                    <i class="bi bi-person-plus-fill me-1"></i> Add New User
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <?php if (empty($users)): ?>
                <div class="empty-state text-center py-5">
                    <i class="bi bi-people fs-1 text-muted"></i>
                    <h5 class="text-muted mt-3">No users found in the database</h5>
                    <p class="text-muted">Click the "Add New User" button to create your first user.</p>
                    <button class="btn btn-danger mt-2" data-bs-toggle="modal" data-bs-target="#roleSelectModal"><i class="bi bi-person-plus-fill me-1"></i> Add New User</button>
                </div>
            <?php else: ?>
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Balance</th><th>Status</th><th class="text-center">Actions</th></tr>
                </thead>
                <tbody id="users-table-body">
                    <?php foreach ($users as $user): 
                        $role_class = $user['role'] == 'Admin' ? 'badge-admin' : ($user['role'] == 'Cashier' ? 'badge-cashier' : 'badge-customer');
                        $role_badge = $user['role'] == 'Admin' ? 'Administrator' : ($user['role'] == 'Cashier' ? 'Cashier' : 'Customer');
                        $status_class = ($user['status'] ?? 'Active') == 'Active' ? 'bg-success' : 'bg-secondary';
                    ?>
                    <tr id="user-row-<?php echo $user['id']; ?>">
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone_number'] ?? 'N/A'); ?></td>
                        <td class="fw-bold"><span class="badge <?php echo $role_class; ?>"><?php echo $role_badge; ?></span></td>
                        <td class="balance-cell">₱ <?php echo number_format($user['credit_balance'] ?? 0, 2); ?></td>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo $user['status'] ?? 'Active'; ?></span></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-primary me-2 edit-user-btn" data-bs-toggle="modal" data-bs-target="#transactionModal"
                                    data-user-id="<?php echo $user['id']; ?>"
                                    data-full-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                    data-user-email="<?php echo htmlspecialchars($user['email']); ?>"
                                    data-user-role="<?php echo $user['role']; ?>"
                                    data-current-balance="<?php echo $user['credit_balance'] ?? 0; ?>"
                                    data-profile-picture="<?php echo htmlspecialchars($user['profile_picture'] ?? ''); ?>"
                                    data-status="<?php echo $user['status'] ?? 'Active'; ?>"
                                    data-phone="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button class="btn btn-sm <?php echo ($user['status'] ?? 'Active') == 'Active' ? 'btn-outline-warning' : 'btn-outline-success'; ?> toggle-status-btn"
                                    data-user-id="<?php echo $user['id']; ?>"
                                    data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                    data-current-status="<?php echo $user['status'] ?? 'Active'; ?>"
                                    <?php echo $user['role'] == 'Admin' ? 'disabled' : ''; ?>>
                                <i class="bi bi-<?php echo ($user['status'] ?? 'Active') == 'Active' ? 'pause-circle' : 'play-circle'; ?>"></i>
                                <?php echo ($user['status'] ?? 'Active') == 'Active' ? 'Suspend' : 'Activate'; ?>
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-btn" 
                                    data-user-id="<?php echo $user['id']; ?>"
                                    data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                    <?php echo $user['role'] == 'Admin' ? 'disabled' : ''; ?>>
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========== MODALS ========== -->

<!-- MODAL: EDIT USER/BALANCE -->
<div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-vcard me-2"></i> Edit User Profile & Balance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 mb-4 border rounded bg-light d-flex align-items-center">
                    <div class="me-4">
                        <div id="profilePictureContainer">
                            <img id="modalProfilePicture" src="" alt="Profile Picture" class="profile-modal-img d-none">
                            <div id="modalDefaultIcon" class="profile-modal-icon-container"><i class="bi bi-question-circle-fill profile-modal-icon"></i></div>
                        </div>
                    </div>
                    <div>
                        <p class="mb-1 text-muted small">Editing User:</p>
                        <h5 class="fw-bold text-dark mb-0" id="modalUserName"></h5>
                        <p class="mb-0 text-secondary small">ID: <span id="modalUserID"></span> | Email: <span id="modalUserEmail"></span> | Role: <span id="modalUserRole"></span></p>
                        <p class="mb-0 fs-4 fw-bolder text-success">Current Balance: <span id="modalUserBalance"></span></p>
                    </div>
                </div>
                <div class="card-admin bg-white p-0">
                    <form id="balanceAdjustmentForm">
                        <input type="hidden" name="user_id" id="modalFormUserID">
                        <h4 class="fs-5 fw-bold text-danger border-bottom pb-2 mb-3 mt-4"><i class="bi bi-key-fill me-2"></i> Password Reset</h4>
                        <p class="text-muted small">Only fill these fields if you need to reset password. Minimum 6 characters.</p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">New Password</label>
                                <input type="password" class="form-control" id="newPassword" name="password" placeholder="New Password (min 6 chars)">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Confirm Password</label>
                                <input type="password" class="form-control" id="confirmNewPassword" name="password_confirmation" placeholder="Confirm New Password">
                                <small class="text-danger" id="passwordMatchWarning" style="display: none;">Passwords do not match.</small>
                            </div>
                        </div>
                        <h4 class="fs-5 fw-bold text-success border-bottom pb-2 mb-3 mt-4"><i class="bi bi-cash-stack me-2"></i> Balance Adjustment</h4>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Adjustment Action</label>
                            <select id="transactionType" name="transaction_type" class="form-select form-select-lg">
                                <option value="" selected disabled>Select an action (Optional)</option>
                                <option value="topup">Top-Up (Add Credits)</option>
                                <option value="deduct">Deduct (Remove Credits)</option>
                                <option value="reset">Reset to Zero</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Amount (₱)</label>
                            <div class="input-group"><span class="input-group-text fw-bold">₱</span><input type="number" class="form-control form-control-lg" id="amount" name="amount" placeholder="e.g., 500.00" min="0" step="0.01"></div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Reason / Notes</label>
                            <textarea class="form-control" id="reason" name="reason" rows="2" placeholder="e.g., Customer top-up via cash, or Adjustment for error."></textarea>
                            <small class="text-muted">This will be logged in the system audit trail.</small>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Account Status</label>
                            <select id="userStatus" name="status" class="form-select"><option value="Active">Active</option><option value="Suspended">Suspended</option></select>
                            <small class="text-muted">Suspended users cannot log in or place orders.</small>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="text" class="form-control" id="editPhoneNumber" name="phone_number" placeholder="Phone number">
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="balanceAdjustmentForm" class="btn btn-success btn-lg fw-bold px-4" id="saveChangesBtn"><i class="bi bi-check-circle-fill me-2"></i> Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ROLE SELECT -->
<div class="modal fade" id="roleSelectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content p-3 shadow-lg border-0 rounded-4">
            <div class="text-center mb-3">
                <h4 class="fw-bold text-danger"><i class="bi bi-person-plus-fill me-2"></i> Select User Role</h4>
                <p class="text-muted small">Choose the type of user you want to create.</p>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <button type="button" class="btn btn-primary btn-lg w-100 mb-3 fw-bold" onclick="selectRole('Customer')" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-circle me-2"></i> Customer</button>
                <button type="button" class="btn btn-secondary btn-lg w-100 fw-bold" onclick="selectRole('Cashier')" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-cash me-2"></i> Cashier</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ADD NEW USER - FIXED WITH ONSUBMIT -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content p-4 shadow-lg border-0 rounded-4">
            <div class="text-center mb-3">
                <h4 class="fw-bold text-danger">Create New User: <span id="selectedRoleDisplay" class="badge bg-danger">Customer</span></h4>
                <p class="text-muted">Enter the details for the new user account.</p>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
            </div>
            <!-- FIXED: Added onsubmit and id for form -->
            <form id="addUserForm" onsubmit="handleAddUser(event); return false;">
                <input type="hidden" id="userRoleInput" name="role" value="Customer">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name</label>
                    <input type="text" class="form-control" id="fullName" name="full_name" placeholder="Juan Dela Cruz" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" class="form-control" id="userEmail" name="email" placeholder="user@example.com" required>
                </div>
                <div class="mb-3" id="phoneGroup">
                    <label class="form-label fw-semibold">Phone Number</label>
                    <input type="text" class="form-control" id="phoneNumber" name="phone_number" placeholder="e.g., 09171234567">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Confirm Password</label>
                        <input type="password" class="form-control" id="passwordConfirmation" name="password_confirmation" required>
                    </div>
                </div>
                <div class="mb-3" id="initialBalanceGroup">
                    <label class="form-label fw-semibold">Initial Credit Balance (₱)</label>
                    <input type="number" class="form-control" id="initialBalance" name="initial_balance" placeholder="e.g., 100.00" min="0" step="0.01" value="0">
                    <small class="text-muted">Only applies to Customer accounts.</small>
                </div>
                <div class="text-center pt-3 border-top">
                    <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Create User Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: DELETE CONFIRMATION -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-danger border-2 rounded-4">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i> Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <p class="mb-0 text-dark">Are you absolutely sure you want to permanently delete the user:</p>
                <p class="fw-bold fs-5 text-danger" id="deleteUserName"></p>
                <p class="small text-muted">This action cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0 pb-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger fw-bold">Delete User</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Helper Functions
    function showToast(message, type = 'success') {
        const container = document.querySelector('.toast-notification');
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show shadow`;
        toast.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }
    
    function showSpinner(show) {
        const spinner = document.getElementById('spinnerOverlay');
        spinner.classList.toggle('show', show);
    }
    
    function showAjaxAlert(message, type = 'success') {
        const alertDiv = document.getElementById('ajaxAlert');
        const alertMessage = document.getElementById('ajaxAlertMessage');
        alertDiv.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
        alertDiv.classList.add(`alert-${type}`, 'show');
        alertMessage.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'x-octagon-fill'} me-2"></i> ${message}`;
        setTimeout(() => { alertDiv.classList.remove('show'); alertDiv.classList.add('d-none'); }, 5000);
    }

    // ========== ADD USER FUNCTION - FIXED AND WORKING ==========
    window.handleAddUser = async function(event) {
        event.preventDefault();
        
        const fullName = document.getElementById('fullName').value;
        const email = document.getElementById('userEmail').value;
        const role = document.getElementById('userRoleInput').value;
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('passwordConfirmation').value;
        const phoneNumber = document.getElementById('phoneNumber').value;
        const initialBalance = document.getElementById('initialBalance').value || 0;
        
        // Validation
        if (!fullName || !email || !password) {
            showToast('Please fill all required fields', 'danger');
            return false;
        }
        
        if (password !== confirmPassword) {
            showToast('Passwords do not match!', 'danger');
            return false;
        }
        
        if (password.length < 6) {
            showToast('Password must be at least 6 characters.', 'danger');
            return false;
        }
        
        if (!email.includes('@')) {
            showToast('Please enter a valid email address.', 'danger');
            return false;
        }
        
        showSpinner(true);
        
        try {
            const response = await fetch('../api/create_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    full_name: fullName,
                    email: email,
                    phone_number: phoneNumber,
                    role: role,
                    password: password,
                    initial_balance: parseFloat(initialBalance) || 0
                })
            });
            
            const data = await response.json();
            showSpinner(false);
            
            if (data.success) {
                showToast(data.message, 'success');
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
                if (modal) modal.hide();
                // Reset form
                document.getElementById('addUserForm').reset();
                document.getElementById('selectedRoleDisplay').textContent = 'Customer';
                document.getElementById('userRoleInput').value = 'Customer';
                // Reload page after 1.5 seconds
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'danger');
            }
        } catch (error) {
            showSpinner(false);
            console.error('Add user error:', error);
            showToast('Error creating user: ' + error.message, 'danger');
        }
        
        return false;
    };

    // ========== ROLE SELECTION ==========
    window.selectRole = function(role) {
        document.getElementById('selectedRoleDisplay').textContent = role;
        document.getElementById('userRoleInput').value = role;
        
        const initialBalanceGroup = document.getElementById('initialBalanceGroup');
        const phoneGroup = document.getElementById('phoneGroup');
        const initialBalanceInput = document.getElementById('initialBalance');
        
        if (role === 'Customer') {
            initialBalanceGroup.style.display = 'block';
            phoneGroup.style.display = 'block';
        } else {
            initialBalanceGroup.style.display = 'none';
            phoneGroup.style.display = 'none';
            initialBalanceInput.value = '0';
        }
    };

    // ========== EDIT USER MODAL POPULATION ==========
    document.addEventListener('DOMContentLoaded', function() {
        const transactionModal = document.getElementById('transactionModal');
        const newPasswordInput = document.getElementById('newPassword');
        const confirmNewPasswordInput = document.getElementById('confirmNewPassword');
        const passwordWarning = document.getElementById('passwordMatchWarning');
        const saveChangesBtn = document.getElementById('saveChangesBtn');
        const deleteConfirmModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        let userToDeleteId = null;
        
        // Populate edit modal
        if (transactionModal) {
            transactionModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('modalUserName').textContent = button.getAttribute('data-full-name');
                document.getElementById('modalUserID').textContent = button.getAttribute('data-user-id');
                document.getElementById('modalUserEmail').textContent = button.getAttribute('data-user-email');
                document.getElementById('modalUserRole').textContent = button.getAttribute('data-user-role');
                document.getElementById('modalUserBalance').textContent = '₱ ' + parseFloat(button.getAttribute('data-current-balance')).toFixed(2);
                document.getElementById('modalFormUserID').value = button.getAttribute('data-user-id');
                document.getElementById('userStatus').value = button.getAttribute('data-status');
                document.getElementById('editPhoneNumber').value = button.getAttribute('data-phone') || '';
                
                // Profile picture
                const profilePic = button.getAttribute('data-profile-picture');
                const profileImage = document.getElementById('modalProfilePicture');
                const defaultIcon = document.getElementById('modalDefaultIcon');
                if (profilePic && profilePic !== '') {
                    profileImage.src = '../' + profilePic;
                    profileImage.classList.remove('d-none');
                    defaultIcon.classList.add('d-none');
                } else {
                    profileImage.classList.add('d-none');
                    defaultIcon.classList.remove('d-none');
                }
                
                // Reset form fields
                document.getElementById('transactionType').value = '';
                document.getElementById('amount').value = '';
                document.getElementById('reason').value = '';
                newPasswordInput.value = '';
                confirmNewPasswordInput.value = '';
                passwordWarning.style.display = 'none';
            });
        }
        
        // Save changes
        if (saveChangesBtn) {
            saveChangesBtn.addEventListener('click', async function() {
                if (newPasswordInput.value !== confirmNewPasswordInput.value) {
                    showAjaxAlert("Passwords do not match.", 'warning');
                    return;
                }
                if (newPasswordInput.value.length > 0 && newPasswordInput.value.length < 6) {
                    showAjaxAlert("Password must be at least 6 characters.", 'warning');
                    return;
                }
                
                showSpinner(true);
                const postData = {
                    user_id: document.getElementById('modalFormUserID').value,
                    transaction_type: document.getElementById('transactionType').value,
                    amount: parseFloat(document.getElementById('amount').value) || 0,
                    reason: document.getElementById('reason').value,
                    password: newPasswordInput.value,
                    status: document.getElementById('userStatus').value,
                    phone_number: document.getElementById('editPhoneNumber').value
                };
                
                try {
                    const response = await fetch('../api/update_user_balance.php', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(postData)
                    });
                    const data = await response.json();
                    showSpinner(false);
                    if (data.success) {
                        showAjaxAlert(data.message, 'success');
                        bootstrap.Modal.getInstance(transactionModal).hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAjaxAlert(data.message, 'danger');
                    }
                } catch (error) {
                    showSpinner(false);
                    showAjaxAlert('Error: ' + error.message, 'danger');
                }
            });
        }
        
        // Toggle status
        document.querySelectorAll('.toggle-status-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (this.disabled) return;
                const userId = this.getAttribute('data-user-id');
                const userName = this.getAttribute('data-user-name');
                const currentStatus = this.getAttribute('data-current-status');
                const newStatus = currentStatus === 'Active' ? 'Suspended' : 'Active';
                const action = newStatus === 'Active' ? 'activate' : 'suspend';
                if (confirm(`Are you sure you want to ${action} user "${userName}"?`)) {
                    showSpinner(true);
                    try {
                        const response = await fetch('../api/update_user_balance.php', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ user_id: userId, status: newStatus })
                        });
                        const data = await response.json();
                        showSpinner(false);
                        if (data.success) {
                            showToast(`User ${action}d successfully!`);
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast(data.message, 'danger');
                        }
                    } catch (error) {
                        showSpinner(false);
                        showToast('Error updating status', 'danger');
                    }
                }
            });
        });
        
        // Delete user
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.disabled) return;
                userToDeleteId = this.getAttribute('data-user-id');
                document.getElementById('deleteUserName').textContent = this.getAttribute('data-user-name');
                deleteConfirmModal.show();
            });
        });
        
        document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
            if (!userToDeleteId) return;
            deleteConfirmModal.hide();
            showSpinner(true);
            try {
                const response = await fetch('../api/delete_user.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ user_id: userToDeleteId })
                });
                const data = await response.json();
                showSpinner(false);
                if (data.success) {
                    showToast(data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'danger');
                }
            } catch (error) {
                showSpinner(false);
                showToast('Error deleting user', 'danger');
            }
            userToDeleteId = null;
        });
        
        // Search and filter
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const roleFilter = document.getElementById('roleFilter');
        
        function filterTable() {
            const searchTerm = searchInput?.value.toLowerCase() || '';
            const role = roleFilter?.value.toLowerCase() || '';
            const rows = document.querySelectorAll('#users-table-body tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const roleCell = row.cells[4]?.textContent.toLowerCase() || '';
                let show = true;
                if (searchTerm && !text.includes(searchTerm)) show = false;
                if (role && !roleCell.includes(role)) show = false;
                row.style.display = show ? '' : 'none';
            });
        }
        
        if (searchInput) searchInput.addEventListener('keyup', filterTable);
        if (clearSearchBtn) clearSearchBtn.addEventListener('click', () => { searchInput.value = ''; filterTable(); });
        if (roleFilter) roleFilter.addEventListener('change', filterTable);
    });
</script>
</body>
</html>