<?php
// admin/profile.php - ADVANCED DATABASE VERSION (NO RAW SQL)
// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

// Database connection
require_once '../config/database.php';

// Initialize variables
$admin = [];
$stats = [];

try {
    // ========== SINGLE STORED PROCEDURE CALL FOR ALL DATA ==========
    $stmt = $pdo->prepare("CALL sp_admin_get_profile_with_stats(?)");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Result Set 1: Admin Profile
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    if ($admin) {
        $_SESSION['full_name'] = $admin['full_name'];
        $_SESSION['email'] = $admin['email'];
        $_SESSION['phone_number'] = $admin['phone_number'];
        $_SESSION['profile_picture'] = $admin['profile_picture'];
    }
    
    // Result Set 2: System Statistics
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
} catch(PDOException $e) {
    $admin = [
        'full_name' => $_SESSION['full_name'] ?? 'Administrator',
        'email' => $_SESSION['email'] ?? '',
        'phone_number' => $_SESSION['phone_number'] ?? '',
        'profile_picture' => $_SESSION['profile_picture'] ?? null,
        'role' => 'Admin',
        'created_at' => date('Y-m-d H:i:s')
    ];
    $stats = [
        'total_customers' => 0,
        'total_cashiers' => 0,
        'total_orders' => 0,
        'pending_orders' => 0,
        'total_items' => 0,
        'low_stock_items' => 0
    ];
    error_log("Database error in admin profile: " . $e->getMessage());
}

$admin_email = $_SESSION['email'] ?? '';
$profile_picture = $_SESSION['profile_picture'] ?? null;
$admin_phone = $_SESSION['phone_number'] ?? '';
$member_since = isset($admin['created_at']) ? date('F d, Y', strtotime($admin['created_at'])) : date('F Y');

// Prepare avatar HTML for navbar and dropdown
$nav_avatar_html = '';
$dropdown_avatar_html = '';
$profile_image_html = '';

if ($profile_picture && file_exists('../' . $profile_picture)) {
    $nav_avatar_html = '<img src="../' . $profile_picture . '" class="nav-avatar-img" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">';
    $dropdown_avatar_html = '<img src="../' . $profile_picture . '" class="dropdown-avatar-img" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover;">';
    $profile_image_html = '<img src="../' . $profile_picture . '" class="profile-pic-lg" alt="Profile Picture" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover;">';
} else {
    $nav_avatar_html = '<div class="nav-avatar">' . strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) . '</div>';
    $dropdown_avatar_html = '<div class="dropdown-avatar">' . strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) . '</div>';
    $profile_image_html = '<div class="profile-icon-lg" style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #198754, #0d6efd); display: flex; align-items: center; justify-content: center; font-size: 60px; color: white;"><i class="bi bi-person-circle"></i></div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile | CHIBUGAN</title>
    <link rel="icon" type="image/x-icon" href="../logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/admin.css">
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
<div class="admin-nav shadow-sm sticky-top sticky-nav-offset">
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
        <a href="orders.php" class="btn text-white py-3 px-4 rounded-0 fw-semibold opacity-75">
            <i class="bi bi-bell-fill me-1"></i> Order Monitoring
        </a>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="container-fluid px-4 mt-4 pb-5">
    
    <div class="profile-container">
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-header-icon">
                    <i class="bi bi-shield-shield"></i>
                </div>
                <h2>Admin Profile</h2>
                <p>View your account information</p>
            </div>
            
            <div class="profile-body">
                <!-- Profile Picture Section - DISPLAY ONLY -->
                <div class="profile-picture-section">
                    <div class="profile-picture-wrapper">
                        <div id="profileImageContainer">
                            <?php echo $profile_image_html; ?>
                        </div>
                    </div>
                </div>
                
                <form id="profileForm" method="POST">
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="bi bi-person-badge"></i>
                            Personal Information
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="full_name"><i class="bi bi-person"></i> Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>" 
                                           placeholder="Enter your full name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="role"><i class="bi bi-badge"></i> Role</label>
                                    <input type="text" class="form-control" id="role" 
                                           value="<?php echo htmlspecialchars($admin['role'] ?? 'Admin'); ?>" 
                                           readonly disabled>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email"><i class="bi bi-envelope"></i> Email Address</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?php echo htmlspecialchars($admin_email); ?>" 
                                           readonly disabled>
                                    <small class="text-muted">Email address cannot be changed.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone_number"><i class="bi bi-telephone"></i> Phone Number</label>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                           value="<?php echo htmlspecialchars($admin_phone); ?>" 
                                           placeholder="Enter your phone number">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="member_since"><i class="bi bi-calendar"></i> Member Since</label>
                                    <input type="text" class="form-control" id="member_since" 
                                           value="<?php echo $member_since; ?>" readonly disabled>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Security Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="bi bi-shield-lock"></i>
                            Security
                        </div>
                        
                        <div class="password-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0"><i class="bi bi-key"></i> Change Password</h5>
                                <span class="password-toggle-btn" onclick="togglePasswordFields()">
                                    <i class="bi bi-pencil-square me-1"></i> Click to change password
                                </span>
                            </div>
                            <p class="text-muted small">Only fill these fields if you wish to change your password. Minimum 6 characters.</p>
                            
                            <div id="passwordFields" class="password-fields">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group">
                                            <label for="current_password">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" 
                                                   placeholder="Enter your current password">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label for="password">New Password</label>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Min 6 characters">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label for="password_confirmation">Confirm New Password</label>
                                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" 
                                                   placeholder="Confirm your new password">
                                        </div>
                                    </div>
                                </div>
                                <small class="text-danger" id="passwordMatchWarning" style="display: none;">Passwords do not match.</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="button" class="btn-cancel" onclick="resetForm()">
                            <i class="bi bi-arrow-counterclockwise me-2"></i> Reset
                        </button>
                        <button type="submit" class="btn-save">
                            <i class="bi bi-check-lg me-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- System Statistics Section -->
        <div class="row mt-4 g-4">
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format($stats['total_customers'] ?? 0); ?></div>
                    <div class="stats-label">Total Customers</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format($stats['total_cashiers'] ?? 0); ?></div>
                    <div class="stats-label">Total Cashiers</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format($stats['total_orders'] ?? 0); ?></div>
                    <div class="stats-label">Completed Orders</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo number_format($stats['total_items'] ?? 0); ?></div>
                    <div class="stats-label">Menu Items</div>
                </div>
            </div>
        </div>
        
        <?php if (($stats['pending_orders'] ?? 0) > 0 || ($stats['low_stock_items'] ?? 0) > 0): ?>
        <div class="alert alert-warning mt-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Attention Needed:</strong>
            <?php if (($stats['pending_orders'] ?? 0) > 0): ?>
                <span class="badge bg-warning text-dark me-2"><?php echo $stats['pending_orders']; ?> pending orders</span>
            <?php endif; ?>
            <?php if (($stats['low_stock_items'] ?? 0) > 0): ?>
                <span class="badge bg-danger"><?php echo $stats['low_stock_items']; ?> low stock items</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
    
    function togglePasswordFields() {
        const fields = document.getElementById('passwordFields');
        const btn = document.querySelector('.password-toggle-btn');
        
        if (fields.classList.contains('show')) {
            fields.classList.remove('show');
            btn.innerHTML = '<i class="bi bi-pencil-square me-1"></i> Click to change password';
            document.getElementById('current_password').value = '';
            document.getElementById('password').value = '';
            document.getElementById('password_confirmation').value = '';
        } else {
            fields.classList.add('show');
            btn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Cancel password change';
        }
    }
    
    function resetForm() {
        if (confirm('Reset all changes? Unsaved changes will be lost.')) {
            location.reload();
        }
    }
    
    // Handle profile form submission
    var profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirmation').value;
            const anyPasswordFilled = currentPassword || newPassword || confirmPassword;
            
            const passwordFieldsDiv = document.getElementById('passwordFields');
            const isPasswordSectionVisible = passwordFieldsDiv.classList.contains('show');
            
            if (isPasswordSectionVisible && anyPasswordFilled) {
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
            
            showSpinner(true);
            
            try {
                const response = await fetch('../api/update_profile.php', { 
                    method: 'POST', 
                    body: formData 
                });
                const data = await response.json();
                showSpinner(false);
                
                if (data.success) {
                    showToast(data.message, 'success');
                    if (passwordFieldsDiv.classList.contains('show')) {
                        togglePasswordFields();
                    }
                    document.getElementById('current_password').value = '';
                    document.getElementById('password').value = '';
                    document.getElementById('password_confirmation').value = '';
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showToast(data.message, 'danger');
                }
            } catch (error) {
                showSpinner(false);
                showToast('Connection error: ' + error.message, 'danger');
            }
        });
    }
</script>

</body>
</html>