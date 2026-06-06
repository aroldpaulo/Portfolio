<?php
// customer/profile.php - USING STORED PROCEDURES (NO RAW SQL)
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
$user = [];
$transactions = [];

try {
    // ========== GET CUSTOMER PROFILE USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_customer_get_profile(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    if ($user) {
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['credit_balance'] = $user['credit_balance'];
        $_SESSION['phone_number'] = $user['phone_number'];
        $_SESSION['member_since'] = $user['created_at'];
        $_SESSION['profile_picture'] = $user['profile_picture'];
    }
    
    // ========== GET TRANSACTION HISTORY USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_customer_get_transactions(?, 10)");
    $stmt->execute([$_SESSION['user_id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
} catch(PDOException $e) {
    $user = [
        'full_name' => $_SESSION['full_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'phone_number' => $_SESSION['phone_number'] ?? '',
        'credit_balance' => $_SESSION['credit_balance'] ?? 0,
        'profile_picture' => $_SESSION['profile_picture'] ?? null
    ];
    $transactions = [];
    error_log("Database error in profile: " . $e->getMessage());
}

$user_email = $_SESSION['email'] ?? '';
$user_balance = $_SESSION['credit_balance'] ?? 0;
$user_phone = $_SESSION['phone_number'] ?? '';
$member_since = isset($_SESSION['member_since']) ? date('F d, Y', strtotime($_SESSION['member_since'])) : '2025';
$profile_picture = $_SESSION['profile_picture'] ?? null;

// Profile image HTML
$profile_image_html = '';
$nav_avatar_html = '';
$dropdown_avatar_html = '';

if ($profile_picture && file_exists('../' . $profile_picture)) {
    $profile_image_html = '<img src="../' . $profile_picture . '" class="profile-icon-lg" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%;">';
    $nav_avatar_html = '<img src="../' . $profile_picture . '" class="nav-avatar-img" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">';
    $dropdown_avatar_html = '<img src="../' . $profile_picture . '" class="dropdown-avatar-img" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover;">';
} else {
    $profile_image_html = '<i class="bi bi-person-circle profile-icon-lg"></i>';
    $nav_avatar_html = '<div class="nav-avatar">' . strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) . '</div>';
    $dropdown_avatar_html = '<div class="dropdown-avatar">' . strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | CHIBUGAN</title>
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
    </style>
</head>
<body>

<div class="wrapper">
    <div class="spinner-overlay" id="spinnerOverlay">
        <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    
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
                        <a class="nav-link" href="orders.php">
                            <i class="bi bi-clock-history me-1"></i>Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">
                            <i class="bi bi-person-circle me-1"></i>Account
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
                            <li><a class="dropdown-item dropdown-item-custom" href="profile.php"><i class="bi bi-person-circle"></i> My Profile</a></li>
                            <li><a class="dropdown-item dropdown-item-custom" href="wallet.php"><i class="bi bi-wallet2"></i> My Wallet <span class="badge bg-success float-end">₱<?php echo number_format($user_balance, 2); ?></span></a></li>
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
        <div class="container mt-4 py-4">
            <h1 class="text-center section-title pb-2">My Account Settings</h1>
            <p class="lead text-center text-muted mb-4">Manage your personal information, wallet, and security.</p>

            <!-- TABBED INTERFACE -->
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="row">
                        
                        <!-- LEFT COLUMN: NAVIGATION PILLS -->
                        <div class="col-md-4 mb-4">
                            <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                                <button class="nav-link active" id="v-pills-profile-tab" data-bs-toggle="pill" data-bs-target="#v-pills-profile" type="button" role="tab">
                                    <i class="bi bi-gear-fill me-2"></i>Personal Details & Security
                                </button>
                                <button class="nav-link" id="v-pills-wallet-tab" data-bs-toggle="pill" data-bs-target="#v-pills-wallet" type="button" role="tab">
                                    <i class="bi bi-credit-card-fill me-2"></i>Wallet & Payments
                                </button>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: TAB CONTENT -->
                        <div class="col-md-8">
                            <div class="tab-content" id="v-pills-tabContent">
                                
                                <!-- PERSONAL DETAILS & SECURITY PANE -->
                                <div class="tab-pane fade show active" id="v-pills-profile" role="tabpanel">
                                    <div class="card shadow p-4">
                                        <h4 class="text-success mb-4"><i class="bi bi-person-fill me-2"></i>Edit Profile & Credentials</h4>
                                        
                                        <form id="profileForm" enctype="multipart/form-data">
                                            <!-- Profile Picture Display -->
                                            <div class="row align-items-center mb-4">
                                                <div class="col-md-auto text-center mb-3 mb-md-0">
                                                    <label class="form-label fw-bold d-block">Profile Picture</label>
                                                    <div id="profileImageContainer">
                                                        <?php echo $profile_image_html; ?>
                                                    </div>
                                                </div>
                                                <div class="col-md">
                                                    <label for="profile_picture" class="form-label fw-bold">Upload New Profile Picture</label>
                                                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this)">
                                                    <div class="form-text">Upload a new image (JPG, PNG, max 2MB)</div>
                                                    <div id="imagePreview" class="mt-2" style="display: none;">
                                                        <img id="preview" class="image-preview" style="width: 80px; height: 80px;">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Full Name -->
                                            <div class="mb-3">
                                                <label for="fullName" class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="fullName" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                                            </div>

                                            <!-- Email & Phone -->
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="email" class="form-label fw-bold">Email Address</label>
                                                    <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user_email); ?>" readonly>
                                                    <div class="form-text">Email cannot be changed.</div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="phone_number" class="form-label fw-bold">Phone Number</label>
                                                    <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_phone); ?>">
                                                    <div class="form-text">Your contact number for order updates.</div>
                                                </div>
                                            </div>
                                            
                                            <!-- Password Change Section -->
                                            <div class="mt-4 mb-4 pt-3 border-top">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h5 class="text-success mb-0"><i class="bi bi-lock-fill me-2"></i>Change Password (Optional)</h5>
                                                    <span class="password-toggle-btn" onclick="togglePasswordFields()">
                                                        <i class="bi bi-pencil-square me-1"></i> Click to change password
                                                    </span>
                                                </div>
                                                <p class="form-text text-muted">Only fill these fields if you want to change your password.</p>
                                                
                                                <div id="passwordFields" style="display: none;">
                                                    <div class="mb-3">
                                                        <label for="current_password" class="form-label fw-bold">Current Password</label>
                                                        <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Enter your current password">
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label for="password" class="form-label fw-bold">New Password</label>
                                                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password (min 6 chars)">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label for="password_confirmation" class="form-label fw-bold">Confirm New Password</label>
                                                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Confirm new password">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-end pt-3">
                                                <button type="submit" class="btn btn-success px-4" id="saveProfileBtn">
                                                    <i class="bi bi-save me-1"></i> Save Changes
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- WALLET & PAYMENTS PANE -->
                                <div class="tab-pane fade" id="v-pills-wallet" role="tabpanel">
                                    <div class="card shadow p-4">
                                        <h4 class="text-success mb-4"><i class="bi bi-wallet-fill me-2"></i>Wallet Management</h4>
                                        
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
                                        
                                        <div class="d-grid gap-3">
                                            <button class="btn btn-success py-3" type="button" data-bs-toggle="modal" data-bs-target="#topupRequestModal">
                                                <i class="bi bi-cash me-2"></i>Request Credit Top Up
                                            </button>
                                            <button class="btn btn-outline-info py-3" type="button" data-bs-toggle="modal" data-bs-target="#transactionHistoryModal">
                                                <i class="bi bi-arrow-down-up me-2"></i>View Transaction History
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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

    <!-- TRANSACTION HISTORY MODAL -->
    <div class="modal fade" id="transactionHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Transaction History</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($transactions)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-credit-card fs-1 opacity-25"></i>
                            <p class="mt-2">No transactions found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr><th>Date</th><th>Type</th><th class="text-end">Amount</th><th>Description</th><th class="text-end">Balance After</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $trans): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y h:i A', strtotime($trans['created_at'])); ?></td>
                                        <td><?php echo $trans['type'] == 'Top-Up' ? '<span class="badge bg-success">Top-Up</span>' : '<span class="badge bg-danger">Spend</span>'; ?></td>
                                        <td class="text-end <?php echo $trans['type'] == 'Top-Up' ? 'text-success' : 'text-danger'; ?> fw-bold"><?php echo $trans['type'] == 'Top-Up' ? '+' : '-'; ?> ₱<?php echo number_format($trans['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($trans['reason']); ?></td>
                                        <td class="text-end fw-bold">₱<?php echo number_format($trans['new_balance'], 2); ?></td>
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

    <!-- Footer -->
    <footer class="text-center py-3 mt-5">
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
    
    function showSpinner(show) {
        const spinner = document.getElementById('spinnerOverlay');
        if (show) {
            spinner.classList.add('show');
        } else {
            spinner.classList.remove('show');
        }
    }
    
    function previewImage(input) {
        const previewDiv = document.getElementById('imagePreview');
        const previewImg = document.getElementById('preview');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewDiv.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            previewDiv.style.display = 'none';
        }
    }
    
    function togglePasswordFields() {
        const passwordFields = document.getElementById('passwordFields');
        const toggleBtn = document.querySelector('.password-toggle-btn');
        
        if (passwordFields.style.display === 'none' || passwordFields.style.display === '') {
            passwordFields.style.display = 'block';
            toggleBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Cancel password change';
            toggleBtn.classList.add('text-danger');
        } else {
            passwordFields.style.display = 'none';
            toggleBtn.innerHTML = '<i class="bi bi-pencil-square me-1"></i> Click to change password';
            toggleBtn.classList.remove('text-danger');
            document.getElementById('current_password').value = '';
            document.getElementById('password').value = '';
            document.getElementById('password_confirmation').value = '';
        }
    }

    // Handle profile form submission
    var profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const fullName = document.getElementById('fullName').value;
            const phone = document.getElementById('phone_number').value;
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirmation').value;
            const profilePicture = document.getElementById('profile_picture').files[0];
            
            const passwordFieldsDiv = document.getElementById('passwordFields');
            const isPasswordSectionVisible = passwordFieldsDiv.style.display === 'block';
            
            if (isPasswordSectionVisible) {
                const anyPasswordFilled = currentPassword || newPassword || confirmPassword;
                
                if (anyPasswordFilled) {
                    if (!currentPassword || !newPassword || !confirmPassword) {
                        showToast('Please fill all password fields to change password.', 'danger');
                        return;
                    }
                    if (newPassword !== confirmPassword) {
                        showToast('New passwords do not match.', 'danger');
                        return;
                    }
                    if (newPassword.length < 6) {
                        showToast('Password must be at least 6 characters.', 'danger');
                        return;
                    }
                }
            }
            
            showSpinner(true);
            
            const formData = new FormData();
            formData.append('full_name', fullName);
            formData.append('phone_number', phone);
            formData.append('current_password', currentPassword);
            formData.append('new_password', newPassword);
            if (profilePicture) {
                formData.append('profile_picture', profilePicture);
            }
            
            try {
                const response = await fetch('../api/update_profile.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                showSpinner(false);
                
                if (data.success) {
                    showToast(data.message);
                    document.getElementById('current_password').value = '';
                    document.getElementById('password').value = '';
                    document.getElementById('password_confirmation').value = '';
                    if (document.getElementById('passwordFields').style.display === 'block') {
                        togglePasswordFields();
                    }
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showToast(data.message, 'danger');
                }
            } catch (error) {
                showSpinner(false);
                showToast('Error: ' + error.message, 'danger');
            }
        });
    }

    // Handle top-up form submission
    var topupForm = document.getElementById('topupForm');
    if (topupForm) {
        topupForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const amount = document.getElementById('topupAmount').value;
            
            if (!amount || parseFloat(amount) < 50) {
                showToast('Minimum top-up amount is ₱50', 'danger');
                return;
            }
            if (parseFloat(amount) > 50000) {
                showToast('Maximum top-up amount is ₱50,000', 'danger');
                return;
            }
            
            showSpinner(true);
            
            try {
                const response = await fetch('../api/request_topup.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ amount: amount })
                });
                
                const data = await response.json();
                showSpinner(false);
                showToast(data.message);
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('topupRequestModal'));
                    if (modal) modal.hide();
                    document.getElementById('topupAmount').value = '';
                }
            } catch (error) {
                showSpinner(false);
                showToast('Error submitting request', 'danger');
            }
        });
    }
</script>
</body>
</html>