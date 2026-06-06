<?php
// admin/settings.php - ADVANCED DATABASE VERSION (NO RAW SQL)
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
$admin_email = '';
$profile_picture = null;
$audit_logs = [];
$settings = [];

try {
    // ========== GET ADMIN PROFILE ==========
    $stmt = $pdo->prepare("CALL sp_admin_get_profile(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    if ($admin_data) {
        $_SESSION['full_name'] = $admin_data['full_name'];
        $_SESSION['email'] = $admin_data['email'];
        $_SESSION['profile_picture'] = $admin_data['profile_picture'];
        $admin_email = $admin_data['email'];
        $profile_picture = $admin_data['profile_picture'];
    }
    
    // ========== GET AUDIT LOGS ==========
    $stmt = $pdo->prepare("CALL sp_admin_get_audit_logs(50, NULL)");
    $stmt->execute();
    $audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    // ========== GET SYSTEM SETTINGS ==========
    $stmt = $pdo->prepare("CALL sp_admin_get_system_settings()");
    $stmt->execute();
    $settings_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
    foreach ($settings_rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
} catch(PDOException $e) {
    $audit_logs = [];
    error_log("Database error in settings: " . $e->getMessage());
}

$store_name = $settings['store_name'] ?? 'CHIBUGAN Filipino Cuisine';
$low_stock_threshold = $settings['low_stock_threshold'] ?? 10;
$currency = $settings['currency'] ?? 'PHP';
$timezone = $settings['timezone'] ?? 'Asia/Manila';
$password_policy = $settings['password_policy'] ?? 'Minimum 6 Characters';
$two_factor_auth = $settings['two_factor_auth'] ?? '1';

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
    <title>System Settings | CHIBUGAN Admin</title>
    <link rel="icon" type="image/x-icon" href="../logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    
    <style>
        /* Additional styles for settings page */
        .action-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .action-LOGIN { background-color: #28a745; color: white; }
        .action-LOGOUT { background-color: #6c757d; color: white; }
        .action-STATUS_CHANGE { background-color: #ffc107; color: #856404; }
        .action-SETTINGS { background-color: #17a2b8; color: white; }
        .action-PROFILE_UPDATE { background-color: #007bff; color: white; }
        .action-PASSWORD_CHANGE { background-color: #fd7e14; color: white; }
        .action-USER_ACTION { background-color: #6f42c1; color: white; }
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
        <a href="orders.php" class="btn text-white py-3 px-4 rounded-0 fw-semibold opacity-75">
            <i class="bi bi-bell-fill me-1"></i> Order Monitoring
        </a>
        <a href="settings.php" class="btn btn-danger text-white fw-bold py-3 px-4 rounded-0 active-nav">
            <i class="bi bi-gear-wide-connected me-1"></i> Settings
        </a>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="container-fluid px-4 mt-4 pb-5">

    <h1 class="fs-2 fw-bold text-danger mb-4 border-bottom pb-3">
        <i class="bi bi-gear-wide-connected me-2"></i> System Settings & Configuration
    </h1>

    <!-- Success/Error Alert -->
    <div id="ajaxAlert" class="alert alert-dismissible fade d-none" role="alert">
        <span id="ajaxAlertMessage"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <div class="row g-4">
        <!-- Left Sidebar Navigation -->
        <div class="col-lg-3">
            <div class="card card-admin bg-white p-3">
                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    <button class="nav-link text-start active" id="v-pills-general-tab" data-bs-toggle="pill" 
                            data-bs-target="#v-pills-general" type="button" role="tab">
                        <i class="bi bi-toggles2 me-2"></i> General Configuration
                    </button>
                    <button class="nav-link text-start" id="v-pills-security-tab" data-bs-toggle="pill" 
                            data-bs-target="#v-pills-security" type="button" role="tab">
                        <i class="bi bi-shield-lock me-2"></i> Security
                    </button>
                    <button class="nav-link text-start" id="v-pills-logs-tab" data-bs-toggle="pill" 
                            data-bs-target="#v-pills-logs" type="button" role="tab">
                        <i class="bi bi-journals me-2"></i> Audit Logs
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Content Area -->
        <div class="col-lg-9">
            <div class="tab-content" id="v-pills-tabContent">
                
                <!-- General Configuration Tab -->
                <div class="tab-pane fade show active" id="v-pills-general" role="tabpanel">
                    <div class="card card-admin bg-white p-4">
                        <h4 class="fw-bold text-danger mb-3">General Configuration</h4>
                        <form id="generalSettingsForm">
                            <div class="mb-3">
                                <label for="storeName" class="form-label fw-semibold">Store Name</label>
                                <input type="text" class="form-control" id="storeName" value="<?php echo htmlspecialchars($store_name); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="currency" class="form-label fw-semibold">Default Currency</label>
                                <select id="currency" class="form-select">
                                    <option value="PHP" <?php echo $currency == 'PHP' ? 'selected' : ''; ?>>Philippine Peso (PHP)</option>
                                    <option value="USD" <?php echo $currency == 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="timezone" class="form-label fw-semibold">Timezone</label>
                                <select id="timezone" class="form-select">
                                    <option value="Asia/Manila" <?php echo $timezone == 'Asia/Manila' ? 'selected' : ''; ?>>Asia/Manila (PST)</option>
                                    <option value="GMT" <?php echo $timezone == 'GMT' ? 'selected' : ''; ?>>Greenwich Mean Time (GMT)</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="lowStockThreshold" class="form-label fw-semibold">Low Stock Alert Threshold</label>
                                <input type="number" class="form-control" id="lowStockThreshold" value="<?php echo $low_stock_threshold; ?>" min="1" max="50" required>
                                <small class="text-muted">Items below this quantity will be flagged as "Low Stock"</small>
                            </div>
                            <button type="submit" class="btn btn-danger fw-bold">
                                <i class="bi bi-save me-2"></i> Save General Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Security Tab -->
                <div class="tab-pane fade" id="v-pills-security" role="tabpanel">
                    <div class="card card-admin bg-white p-4">
                        <h4 class="fw-bold text-danger mb-3">Security Settings</h4>
                        <form id="securitySettingsForm">
                            <div class="mb-3">
                                <label for="passwordPolicy" class="form-label fw-semibold">Password Requirements</label>
                                <select id="passwordPolicy" class="form-select">
                                    <option value="Minimum 6 Characters" <?php echo $password_policy == 'Minimum 6 Characters' ? 'selected' : ''; ?>>Minimum 6 Characters</option>
                                    <option value="Minimum 8 Characters, Mixed Case" <?php echo $password_policy == 'Minimum 8 Characters, Mixed Case' ? 'selected' : ''; ?>>Minimum 8 Characters, Mixed Case</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="twoFactorAuth" <?php echo $two_factor_auth == '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="twoFactorAuth">
                                        Enable Two-Factor Authentication (2FA) for Admin Accounts
                                    </label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-danger fw-bold">
                                <i class="bi bi-save me-2"></i> Save Security Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Audit Logs Tab -->
                <div class="tab-pane fade" id="v-pills-logs" role="tabpanel">
                    <div class="card card-admin bg-white p-4">
                        <h4 class="fw-bold text-danger mb-3">System Audit Logs</h4>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i> 
                            This section shows detailed, time-stamped records of all system actions.
                        </div>
                        
                        <!-- Filter & Search -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="logSearch" placeholder="Search logs..." onkeyup="filterLogs()">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="logActionFilter" onchange="filterLogs()">
                                    <option value="">All Actions</option>
                                    <option value="LOGIN">Login</option>
                                    <option value="LOGOUT">Logout</option>
                                    <option value="STATUS_CHANGE">Status Change</option>
                                    <option value="SETTINGS">Settings Change</option>
                                    <option value="PROFILE_UPDATE">Profile Update</option>
                                    <option value="PASSWORD_CHANGE">Password Change</option>
                                    <option value="USER_ACTION">User Action</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-secondary w-100" onclick="clearLogFilters()">
                                    <i class="bi bi-x-circle me-1"></i> Clear
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-success w-100" onclick="exportLogs()">
                                    <i class="bi bi-cloud-arrow-down me-2"></i> Export CSV
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-sm table-hover" id="auditLogsTable">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>User ID</th>
                                        <th>Action</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($audit_logs)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                No audit logs found
                                            </td>
                                        </tr>
                                    <?php else: 
                                        foreach ($audit_logs as $log): 
                                            $action_type = str_replace(' ', '_', strtoupper($log['action_type']));
                                    ?>
                                        <tr data-action="<?php echo $action_type; ?>" data-user="<?php echo $log['user_id']; ?>">
                                            <td><?php echo date('m/d/Y h:i A', strtotime($log['created_at'])); ?></td>
                                            <td><?php echo $log['user_id'] ? 'User #' . $log['user_id'] : 'SYSTEM'; ?></td>
                                            <td><span class="action-badge action-<?php echo $action_type; ?>"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                                            <td><?php echo htmlspecialchars($log['description']); ?></td>
                                        </tr>
                                    <?php 
                                        endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted" id="logCount">Showing <?php echo count($audit_logs); ?> entries</small>
                        </div>
                    </div>
                </div>

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
    
    function showAjaxAlert(message, type) {
        if (typeof type === 'undefined') type = 'success';
        const alertDiv = document.getElementById('ajaxAlert');
        const alertMessage = document.getElementById('ajaxAlertMessage');
        
        alertDiv.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        alertDiv.classList.add('alert-' + type, 'show');
        alertMessage.innerHTML = message;
        
        setTimeout(function() {
            alertDiv.classList.remove('show');
            alertDiv.classList.add('d-none');
        }, 5000);
    }

    // Filter audit logs
    function filterLogs() {
        const searchTerm = document.getElementById('logSearch').value.toLowerCase();
        const actionFilter = document.getElementById('logActionFilter').value;
        
        const rows = document.querySelectorAll('#auditLogsTable tbody tr');
        let visibleCount = 0;
        
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            if (row.cells.length === 4) {
                var action = row.getAttribute('data-action');
                var text = row.textContent.toLowerCase();
                
                var show = true;
                if (searchTerm && text.indexOf(searchTerm) === -1) show = false;
                if (actionFilter && action !== actionFilter) show = false;
                
                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            }
        }
        
        var totalRows = rows.length;
        var countElement = document.getElementById('logCount');
        if (searchTerm || actionFilter) {
            countElement.textContent = 'Showing ' + visibleCount + ' of ' + totalRows + ' entries (filtered)';
        } else {
            countElement.textContent = 'Showing ' + totalRows + ' entries';
        }
    }

    // Clear log filters
    function clearLogFilters() {
        document.getElementById('logSearch').value = '';
        document.getElementById('logActionFilter').value = '';
        filterLogs();
    }

    // Export logs to CSV
    function exportLogs() {
        var rows = document.querySelectorAll('#auditLogsTable tbody tr');
        var csvContent = 'Timestamp,User ID,Action,Description\n';
        
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            if (row.style.display !== 'none' && row.cells.length === 4) {
                var timestamp = row.cells[0].textContent.trim();
                var user = row.cells[1].textContent.trim();
                var action = row.cells[2].textContent.trim();
                var description = row.cells[3].textContent.trim();
                
                csvContent += '"' + timestamp + '","' + user + '","' + action + '","' + description + '"\n';
            }
        }
        
        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', 'audit_logs_' + new Date().toISOString().slice(0, 10) + '.csv');
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showToast('Logs exported successfully!');
    }

    // Save general settings
    var generalForm = document.getElementById('generalSettingsForm');
    if (generalForm) {
        generalForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            showSpinner(true);
            
            const storeName = document.getElementById('storeName').value;
            const currency = document.getElementById('currency').value;
            const timezone = document.getElementById('timezone').value;
            const lowStockThreshold = document.getElementById('lowStockThreshold').value;
            
            try {
                // Save each setting using stored procedure
                var settingsToSave = [
                    { key: 'store_name', value: storeName },
                    { key: 'currency', value: currency },
                    { key: 'timezone', value: timezone },
                    { key: 'low_stock_threshold', value: lowStockThreshold }
                ];
                
                for (var i = 0; i < settingsToSave.length; i++) {
                    await fetch('../api/update_setting.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ setting_key: settingsToSave[i].key, setting_value: settingsToSave[i].value })
                    });
                }
                
                showSpinner(false);
                showToast('General settings saved successfully!');
            } catch (error) {
                showSpinner(false);
                showToast('Error saving settings: ' + error.message, 'danger');
            }
        });
    }

    // Save security settings
    var securityForm = document.getElementById('securitySettingsForm');
    if (securityForm) {
        securityForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            showSpinner(true);
            
            const passwordPolicy = document.getElementById('passwordPolicy').value;
            const twoFactorAuth = document.getElementById('twoFactorAuth').checked ? '1' : '0';
            
            try {
                await fetch('../api/update_setting.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ setting_key: 'password_policy', setting_value: passwordPolicy })
                });
                
                await fetch('../api/update_setting.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ setting_key: 'two_factor_auth', setting_value: twoFactorAuth })
                });
                
                showSpinner(false);
                showToast('Security settings saved successfully!');
            } catch (error) {
                showSpinner(false);
                showToast('Error saving settings: ' + error.message, 'danger');
            }
        });
    }
</script>
</body>
</html>