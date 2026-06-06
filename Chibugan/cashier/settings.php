<?php
// cashier/settings.php - USING STORED PROCEDURES (NO RAW SQL)
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'cashier' && $_SESSION['role'] != 'admin')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$user = null;
$pending_count = 0;

try {
    // ========== GET USER PROFILE USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_get_user_profile(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    if ($user) {
        $_SESSION['full_name'] = $user['full_name'] ?? $_SESSION['full_name'] ?? 'Cashier';
        $_SESSION['email'] = $user['email'] ?? $_SESSION['email'] ?? '';
        $_SESSION['phone_number'] = $user['phone_number'] ?? $_SESSION['phone_number'] ?? '';
        $_SESSION['profile_picture'] = $user['profile_picture'] ?? $_SESSION['profile_picture'] ?? null;
    }
    
    // ========== GET PENDING COUNT USING STORED PROCEDURE ==========
    $stmt = $pdo->prepare("CALL sp_get_pending_count()");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_count = $result['pending_count'] ?? 0;
    $stmt->nextRowset();
    
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    // Keep existing session values or set defaults
    $_SESSION['full_name'] = $_SESSION['full_name'] ?? 'Cashier';
    $_SESSION['email'] = $_SESSION['email'] ?? '';
    $_SESSION['phone_number'] = $_SESSION['phone_number'] ?? '';
    $_SESSION['profile_picture'] = $_SESSION['profile_picture'] ?? null;
}

$user_email = $_SESSION['email'] ?? '';
$profile_picture = $_SESSION['profile_picture'] ?? null;
$user_role = $_SESSION['role'] ?? 'cashier';
$user_phone = $_SESSION['phone_number'] ?? '';
$member_since = $user['member_since'] ?? date('F Y');

$nav_avatar_html = '';
$dropdown_avatar_html = '';
$profile_image_html = '';

if ($profile_picture && file_exists('../' . $profile_picture)) {
    $nav_avatar_html = '<img src="../' . $profile_picture . '" class="nav-avatar-img" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">';
    $dropdown_avatar_html = '<img src="../' . $profile_picture . '" class="dropdown-avatar-img" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover;">';
    $profile_image_html = '<img src="../' . $profile_picture . '" class="profile-pic-lg" alt="Profile Picture">';
} else {
    $nav_avatar_html = '<div class="nav-avatar">' . strtoupper(substr($_SESSION['full_name'] ?? 'C', 0, 1)) . '</div>';
    $dropdown_avatar_html = '<div class="dropdown-avatar">' . strtoupper(substr($_SESSION['full_name'] ?? 'C', 0, 1)) . '</div>';
    $profile_image_html = '<div class="profile-icon-lg"><i class="bi bi-person-circle"></i></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | CHIBUGAN Cashier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/cashier.css">
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
    <div class="toast-notification"></div>
    <div class="spinner-overlay" id="spinnerOverlay">
        <div class="spinner-border text-light" style="width: 3rem; height: 3rem;"></div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-lg sticky-top z-index-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand text-success fs-4 fw-bold" href="dashboard.php">
                <i class="bi bi-shop me-2"></i>CHIBUGAN Cashier Panel
            </a>
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle nav-link-profile" href="#" data-bs-toggle="dropdown">
                        <?php echo $nav_avatar_html; ?>
                        <span class="ms-2 fw-semibold text-dark"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Cashier'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                        <li>
                            <div class="dropdown-profile-header">
                                <?php echo $dropdown_avatar_html; ?>
                                <div class="dropdown-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Cashier'); ?></div>
                                <div class="dropdown-email"><?php echo htmlspecialchars($user_email); ?></div>
                            </div>
                        </li>
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
                <a href="../admin/dashboard.php" class="btn btn-danger py-3 px-4 fw-bolder border-0 text-white">
                    <i class="bi bi-gear-fill me-1"></i> Admin Panel
                </a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn text-white py-3 px-4 fw-semibold opacity-75">
                <i class="bi bi-bar-chart-fill me-1"></i> Dashboard
            </a>
            <a href="pending.php" class="btn text-white py-3 px-4 fw-semibold opacity-75">
                <i class="bi bi-bell-fill me-1"></i> Pending Orders 
                <span class="badge bg-warning ms-1 text-dark"><?php echo $pending_count; ?></span>
            </a>
            <a href="completed.php" class="btn text-white py-3 px-4 fw-semibold opacity-75">
                <i class="bi bi-check2-circle me-1"></i> Completed Orders
            </a>
            <a href="customer_wallet.php" class="btn text-white py-3 px-4 fw-semibold opacity-75">
                <i class="bi bi-credit-card-fill me-1"></i> Customer Wallet
            </a>
            <a href="settings.php" class="btn btn-success py-3 px-4 fw-bold border-0">
                <i class="bi bi-gear me-1"></i> Settings
            </a>
        </div>
    </div>

    <div class="content">
        <div class="settings-container">
            <div class="settings-card">
                <div class="settings-header">
                    <div class="settings-header-icon">
                        <i class="bi bi-gear-wide-connected"></i>
                    </div>
                    <h2>Account Settings</h2>
                    <p>Manage your profile information and security preferences</p>
                </div>
                
                <div class="settings-body">
                    <form id="profileForm" enctype="multipart/form-data" method="POST">
                        <!-- Profile Picture Section -->
                        <div class="profile-picture-section">
                            <div class="profile-picture-wrapper">
                                <div id="profileImageContainer">
                                    <?php echo $profile_image_html; ?>
                                </div>
                            </div>
                            <div class="upload-btn-wrapper mt-3">
                                <label for="profile_picture" class="btn btn-upload">
                                    <i class="bi bi-camera me-2"></i> Change Profile Picture
                                </label>
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" style="display: none;" onchange="previewImage(this)">
                            </div>
                            <div id="imagePreviewContainer" class="mt-3" style="display: none;">
                                <div class="alert alert-info">
                                    <i class="bi bi-image me-2"></i> New image preview:
                                    <img id="imagePreview" src="" alt="Preview" class="profile-pic-lg mt-2" style="width: 80px; height: 80px;">
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">Supported formats: JPG, PNG. Max size: 2MB</small>
                        </div>
                        
                        <!-- Personal Information Section -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-person-badge"></i>
                                Personal Information
                            </div>
                            
                            <div class="form-group">
                                <label for="full_name"><i class="bi bi-person me-2"></i>Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>" 
                                       placeholder="Enter your full name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email"><i class="bi bi-envelope me-2"></i>Email Address</label>
                                <input type="email" class="form-control" id="email" 
                                       value="<?php echo htmlspecialchars($user_email); ?>" 
                                       readonly disabled>
                                <small class="text-muted">Email address cannot be changed. Contact administrator for assistance.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone_number"><i class="bi bi-telephone me-2"></i>Phone Number</label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                       value="<?php echo htmlspecialchars($user_phone); ?>" 
                                       placeholder="Enter your phone number">
                            </div>
                        </div>
                        
                        <!-- Security Section -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-shield-lock"></i>
                                Security
                            </div>
                            
                            <div class="password-section">
                                <div class="password-header">
                                    <h5><i class="bi bi-key me-2"></i>Change Password</h5>
                                    <span class="password-toggle-btn" onclick="togglePasswordFields()">
                                        <i class="bi bi-pencil-square me-1"></i> Click to change password
                                    </span>
                                </div>
                                <p class="text-muted small">Only fill these fields if you wish to change your password.</p>
                                
                                <div id="passwordFields" class="password-fields">
                                    <div class="form-group">
                                        <label for="current_password">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" 
                                               placeholder="Enter your current password">
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="password">New Password</label>
                                                <input type="password" class="form-control" id="password" name="password" 
                                                       placeholder="Min 6 characters">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="password_confirmation">Confirm New Password</label>
                                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" 
                                                       placeholder="Confirm your new password">
                                            </div>
                                        </div>
                                    </div>
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
        </div>
    </div>

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
    
    function previewImage(input) {
        const container = document.getElementById('imagePreviewContainer');
        const preview = document.getElementById('imagePreview');
        
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                container.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            container.style.display = 'none';
        }
    }
    
    function togglePasswordFields() {
        const fields = document.getElementById('passwordFields');
        const btn = document.querySelector('.password-toggle-btn');
        
        if (fields.classList.contains('show')) {
            fields.classList.remove('show');
            btn.innerHTML = '<i class="bi bi-pencil-square me-1"></i> Click to change password';
            btn.style.color = '#198754';
            document.getElementById('current_password').value = '';
            document.getElementById('password').value = '';
            document.getElementById('password_confirmation').value = '';
        } else {
            fields.classList.add('show');
            btn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Cancel password change';
            btn.style.color = '#dc3545';
        }
    }
    
    function resetForm() {
        if (confirm('Reset all changes? Unsaved changes will be lost.')) {
            location.reload();
        }
    }
    
    document.getElementById('profileForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const currentPassword = document.getElementById('current_password').value;
        const newPassword = document.getElementById('password').value;
        const confirmPassword = document.getElementById('password_confirmation').value;
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
        
        showSpinner(true);
        
        try {
            const response = await fetch('../api/update_profile.php', { method: 'POST', body: formData });
            const data = await response.json();
            showSpinner(false);
            
            if (data.success) {
                showToast(data.message, 'success');
                if (document.getElementById('passwordFields').classList.contains('show')) {
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
            console.error('Fetch error:', error);
            showToast('Connection error: ' + error.message, 'danger');
        }
    });
    
    var uploadBtn = document.querySelector('.btn-upload');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', function() {
            document.getElementById('profile_picture').click();
        });
    }
</script>
</body>
</html>