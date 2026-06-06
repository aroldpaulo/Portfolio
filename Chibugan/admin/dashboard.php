<?php
// admin/dashboard.php - TOP SELLING ITEMS FROM COMPLETED ORDERS ONLY
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
$today_sales = 0;
$yesterday_sales = 0;
$percent_change = 0;
$active_orders = 0;
$low_stock = 0;
$out_of_stock = 0;
$total_users = 0;
$new_users = 0;
$top_items = [];
$weekly_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$weekly_data = [0, 0, 0, 0, 0, 0, 0];
$pending_topups = 0;

try {
    // ========== SINGLE STORED PROCEDURE CALL FOR ALL DASHBOARD DATA ==========
    $stmt = $pdo->prepare("CALL sp_admin_get_dashboard_data(?)");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Result Set 1: Admin Profile
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin_data) {
        $_SESSION['full_name'] = $admin_data['full_name'];
        $_SESSION['email'] = $admin_data['email'];
        $_SESSION['profile_picture'] = $admin_data['profile_picture'];
        $admin_email = $admin_data['email'];
        $profile_picture = $admin_data['profile_picture'];
    }
    $stmt->nextRowset();
    
    // Result Set 2: Today's and Yesterday's Sales
    $sales_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_sales = $sales_data['today_sales'] ?? 0;
    $yesterday_sales = $sales_data['yesterday_sales'] ?? 0;
    $stmt->nextRowset();
    
    // Calculate percentage change
    if ($yesterday_sales > 0) {
        $percent_change = round((($today_sales - $yesterday_sales) / $yesterday_sales) * 100, 1);
    }
    
    // Result Set 3: Counts (Active Orders, Low Stock, etc.)
    $counts_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $active_orders = $counts_data['active_orders'] ?? 0;
    $low_stock = $counts_data['low_stock'] ?? 0;
    $out_of_stock = $counts_data['out_of_stock'] ?? 0;
    $total_users = $counts_data['total_users'] ?? 0;
    $new_users = $counts_data['new_users'] ?? 0;
    $pending_topups = $counts_data['pending_topups'] ?? 0;
    $stmt->nextRowset();
    
    // SKIP the old Result Set 4 (Top Selling Items from dashboard procedure)
    // because it counts ALL orders including cancelled
    $stmt->nextRowset(); // Skip the unreliable top items
    
    // Result Set 5: Weekly Sales Data
    $weekly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert weekly sales to labels and data arrays
    $weekly_labels = [];
    $weekly_data = [];
    foreach ($weekly_sales as $week) {
        $weekly_labels[] = $week['day_name'];
        $weekly_data[] = (float)$week['daily_sales'];
    }
    $stmt->nextRowset();
    
    // ========== FIXED: GET TOP SELLING ITEMS FROM COMPLETED ORDERS ONLY ==========
    // This query EXCLUDES cancelled and pending orders
    $top_items_query = "
        SELECT 
            m.id,
            m.name,
            COALESCE(SUM(oi.quantity), 0) as total_sold
        FROM menu_items m
        INNER JOIN order_items oi ON m.id = oi.menu_item_id
        INNER JOIN orders o ON oi.order_id = o.id 
        WHERE o.status = 'Completed'
        GROUP BY m.id, m.name
        ORDER BY total_sold DESC
        LIMIT 5
    ";
    $stmt_items = $pdo->prepare($top_items_query);
    $stmt_items->execute();
    $top_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Database error in admin dashboard: " . $e->getMessage());
    // Fallback query if above fails
    try {
        $fallback_query = "
            SELECT 
                m.id,
                m.name,
                COALESCE(SUM(oi.quantity), 0) as total_sold
            FROM menu_items m
            LEFT JOIN order_items oi ON m.id = oi.menu_item_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'Completed'
            GROUP BY m.id, m.name
            HAVING total_sold > 0
            ORDER BY total_sold DESC
            LIMIT 5
        ";
        $stmt_items = $pdo->prepare($fallback_query);
        $stmt_items->execute();
        $top_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e2) {
        $top_items = [];
    }
}

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

$change_class = $percent_change >= 0 ? 'text-success' : 'text-danger';
$change_icon = $percent_change >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHIBUGAN Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="icon" type="image/x-icon" href="../logo.jpg">
</head>
<body class="min-vh-100">

    <div class="toast-notification"></div>

    <!-- ========== TOP NAVIGATION BAR ========== -->
    <nav class="navbar navbar-admin sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand" href="dashboard.php">
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
            <a href="dashboard.php" class="btn btn-danger py-3 px-4 rounded-0 fw-bold border-0 active-nav">
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

        <!-- Dashboard Section -->
        <div id="dashboard" class="mb-5">
            <h2 class="fs-3 fw-bold text-danger mb-4">
                <i class="bi bi-bar-chart-line-fill me-2"></i> System Overview
            </h2>

            <!-- Stats Cards Row -->
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-4">
                
                <!-- Today's Sales -->
                <div class="col">
                    <div class="card card-admin info-card-success p-4">
                        <p class="mb-1 text-uppercase fw-semibold text-muted">Today's Sales</p>
                        <h3 class="fs-2 fw-bolder text-success">₱ <?php echo number_format($today_sales, 2); ?></h3>
                        <span class="text-sm <?php echo $change_class; ?>">
                            <i class="bi <?php echo $change_icon; ?>"></i> <?php echo abs($percent_change); ?>% from yesterday
                        </span>
                    </div>
                </div>

                <!-- Active Orders -->
                <div class="col">
                    <div class="card card-admin info-card-primary p-4">
                        <p class="mb-1 text-uppercase fw-semibold text-muted">Active Orders</p>
                        <h3 class="fs-2 fw-bolder text-primary"><?php echo $active_orders; ?></h3>
                        <span class="text-sm text-muted">
                            <i class="bi bi-clock-fill"></i> Needs attention
                        </span>
                    </div>
                </div>
                
                <!-- Low Stock Items -->
                <div class="col">
                    <div class="card card-admin info-card-warning p-4">
                        <p class="mb-1 text-uppercase fw-semibold text-muted">Low Stock Items</p>
                        <h3 class="fs-2 fw-bolder text-warning"><?php echo $low_stock; ?></h3>
                        <span class="text-sm text-warning">
                            <i class="bi bi-exclamation-triangle-fill"></i> Needs re-order
                        </span>
                        <?php if ($out_of_stock > 0): ?>
                            <small class="text-danger d-block mt-1">
                                <i class="bi bi-x-circle"></i> <?php echo $out_of_stock; ?> out of stock
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Registered Users -->
                <div class="col">
                    <div class="card card-admin info-card-danger p-4">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <p class="mb-0 text-uppercase fw-semibold text-muted">Registered Users</p>
                        </div>
                        <h3 class="fs-2 fw-bolder text-danger mb-1"><?php echo $total_users; ?></h3>
                        <span class="text-sm text-secondary">
                            <i class="bi bi-person-plus-fill"></i> <?php echo $new_users; ?> new this week
                        </span>
                    </div>
                </div>

            </div>

            <!-- Charts & Top Sellers Row -->
            <div class="row g-4">
                
                <!-- Sales Trend Chart -->
                <div class="col-lg-8">
                    <div class="card card-admin bg-white p-4 h-100">
                        <h4 class="fs-5 fw-bold text-danger mb-3 border-bottom pb-2">
                            Sales Trend (Last 7 Days)
                        </h4>
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Selling Items - BASED ON COMPLETED ORDERS ONLY -->
                <div class="col-lg-4">
                    <div class="card card-admin bg-white p-4 h-100">
                        <h4 class="fs-5 fw-bold text-danger mb-3 border-bottom pb-2">
                            <i class="bi bi-trophy-fill me-2"></i> Top Selling Items
                            <small class="text-muted fs-6 fw-normal">(Completed Orders Only)</small>
                        </h4>
                        <ul class="list-group list-group-flush">
                            <?php if (empty($top_items)): ?>
                                <li class="list-group-item text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-1"></i>
                                    <p class="mt-2 mb-0">No completed orders yet</p>
                                    <small>Complete orders will appear here</small>
                                </li>
                            <?php else: 
                                $rank = 1;
                                foreach ($top_items as $item): 
                                    if (($item['total_sold'] ?? 0) == 0) continue;
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center fw-semibold">
                                    <span>
                                        <?php if ($rank == 1): ?>
                                            <i class="bi bi-trophy-fill text-warning me-2"></i>
                                        <?php elseif ($rank == 2): ?>
                                            <i class="bi bi-award-fill text-secondary me-2"></i>
                                        <?php elseif ($rank == 3): ?>
                                            <i class="bi bi-award-fill me-2" style="color: #cd7f32;"></i>
                                        <?php else: ?>
                                            <i class="bi bi-<?php echo $rank; ?>-circle-fill text-secondary me-2"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </span>
                                    <span class="badge <?php echo $rank == 1 ? 'bg-danger' : 'bg-secondary'; ?> rounded-pill top-badge">
                                        <?php echo $item['total_sold']; ?> sold
                                    </span>
                                </li>
                            <?php 
                                    $rank++;
                                    if ($rank > 5) break;
                                endforeach; 
                            endif; ?>
                        </ul>
                    </div>
                </div>

            </div>
            
            <!-- Pending Top-up Requests Alert -->
            <?php if ($pending_topups > 0): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong><?php echo $pending_topups; ?> pending top-up request(s)!</strong> 
                        <a href="topup_requests.php" class="alert-link">Click here to review and approve.</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Action Buttons -->
        <div class="row g-3 mt-2">
            <div class="col-md-3">
                <a href="menu_management.php" class="btn btn-outline-success w-100 py-3 fw-semibold">
                    <i class="bi bi-plus-circle me-2"></i> Add New Menu Item
                </a>
            </div>
            <div class="col-md-3">
                <a href="users.php" class="btn btn-outline-primary w-100 py-3 fw-semibold">
                    <i class="bi bi-person-plus me-2"></i> Register New User
                </a>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-warning w-100 py-3 fw-semibold" onclick="exportReport()">
                    <i class="bi bi-file-earmark-spreadsheet me-2"></i> Export Sales Report
                </button>
            </div>
            <div class="col-md-3">
                <a href="settings.php" class="btn btn-outline-info w-100 py-3 fw-semibold">
                    <i class="bi bi-gear me-2"></i> System Settings
                </a>
            </div>
        </div>
    </div>

    <!-- ========== FOOTER ========== -->
    <footer class="bg-dark text-white py-3 mt-4">
        <div class="container text-center">
            <p class="mb-0 small">&copy; 2025 CHIBUGAN Admin Panel. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize all dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
            var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });
            
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                toggle.addEventListener('show.bs.dropdown', function() {
                    const dropdownMenu = this.nextElementSibling;
                    if (dropdownMenu) {
                        dropdownMenu.style.zIndex = '1060';
                    }
                });
            });
        });
        
        // Sales Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        const chartLabels = <?php echo json_encode($weekly_labels); ?>;
        const chartData = <?php echo json_encode($weekly_data); ?>;
        
        const hasData = chartData.some(value => value > 0);
        
        if (hasData) {
            const salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Daily Sales (₱)',
                        data: chartData,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#dc3545',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return '₱ ' + context.raw.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱ ' + value.toFixed(0);
                                }
                            }
                        }
                    }
                }
            });
        } else {
            ctx.font = '16px Arial';
            ctx.fillStyle = '#999';
            ctx.textAlign = 'center';
            ctx.fillText('No sales data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
        }
        
        function showToast(message, type = 'success') {
            const container = document.querySelector('.toast-notification');
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show shadow`;
            toast.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        function exportReport() {
            showToast('Exporting sales report...', 'info');
            setTimeout(() => {
                showToast('Report exported successfully!', 'success');
            }, 1500);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const navButtons = document.querySelectorAll('.admin-nav .btn');
            
            navButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    navButtons.forEach(btn => {
                        btn.classList.remove('active-nav', 'btn-danger', 'btn-success');
                        btn.classList.add('opacity-75');
                    });
                    
                    this.classList.remove('opacity-75');
                    this.classList.add('active-nav');
                    
                    if (this.textContent.includes('Dashboard')) {
                        this.classList.add('btn-danger');
                    }
                });
            });
        });
    </script>
</body>
</html>