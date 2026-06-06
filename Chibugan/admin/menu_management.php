<?php
// admin/menu_management.php - ADVANCED DATABASE VERSION (NO RAW SQL)
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
$menu_items = [];

try {
    // ========== GET ADMIN PROFILE ==========
    $stmt = $pdo->prepare("CALL sp_admin_get_profile(?)");
    $stmt->execute([$_SESSION['user_id']]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin_data) {
        $_SESSION['full_name'] = $admin_data['full_name'];
        $_SESSION['email'] = $admin_data['email'];
        $_SESSION['profile_picture'] = $admin_data['profile_picture'];
        $admin_email = $admin_data['email'];
        $profile_picture = $admin_data['profile_picture'];
    }
    $stmt->nextRowset();
    
    // ========== GET ALL MENU ITEMS ==========
    $stmt = $pdo->prepare("CALL sp_admin_get_all_menu_items()");
    $stmt->execute();
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->nextRowset();
    
} catch(PDOException $e) {
    $menu_items = [];
    error_log("Database error in menu management: " . $e->getMessage());
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management | CHIBUGAN Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
       <link rel="stylesheet" href="../css/admin.css">

        <link rel="icon" type="image/x-icon" href="../logo.jpg">
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
        <a href="dashboard.php" class="btn text-white py-3 px-4 rounded-0 fw-semibold opacity-75">
            <i class="bi bi-bar-chart-fill me-1"></i> Dashboard
        </a>
        <a href="users.php" class="btn text-white py-3 px-4 rounded-0 fw-semibold opacity-75">
            <i class="bi bi-people-fill me-1"></i> Manage Users
        </a>
        <a href="menu_management.php" class="btn btn-danger text-white fw-bold py-3 px-4 rounded-0 active-nav">
            <i class="bi bi-list-nested me-1"></i> Menu Management
        </a>
        <a href="orders.php" class="btn text-white py-3 px-4 rounded-0 fw-semibold opacity-75">
            <i class="bi bi-bell-fill me-1"></i> Order Monitoring
        </a>
    </div>
</div>

<!-- ========== MAIN CONTENT ========== -->
<div class="container-fluid px-4 mt-4 pb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h1 class="fs-2 fw-bold text-danger mb-0"><i class="bi bi-list-nested me-3"></i> Menu Management</h1>
        <button class="btn btn-danger fw-bold py-2 px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="bi bi-plus-circle-fill me-2"></i> Add New Item
        </button>
    </div>

    <!-- Alert Messages -->
    <div id="ajaxAlert" class="alert alert-dismissible fade d-none" role="alert">
        <span id="ajaxAlertMessage"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- Filter & Search -->
    <div class="card card-admin bg-white p-4 shadow-sm mb-4">
        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-filter-square me-2 text-primary"></i> Filter & Search</h5>
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-secondary"></i></span>
                    <input type="text" class="form-control border-start-0" id="searchInput" placeholder="Search by Item Name...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="categoryFilter">
                    <option value="">Filter by Category</option>
                    <option value="Main Dish">Main Dish</option>
                    <option value="Drinks">Drinks</option>
                    <option value="Sides">Sides</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="statusFilter">
                    <option value="">Filter by Status</option>
                    <option value="In Stock">In Stock</option>
                    <option value="Low Stock">Low Stock</option>
                    <option value="Out of Stock">Out of Stock</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100 fw-bold" onclick="filterTable()">
                    <i class="bi bi-funnel"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Menu Items Table -->
    <div class="card card-admin bg-white shadow-lg p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="fw-bold text-dark">Image</th>
                        <th class="fw-bold text-dark">Item Name</th>
                        <th class="fw-bold text-dark">Category</th>
                        <th class="fw-bold text-dark">Price</th>
                        <th class="fw-bold text-dark">Quantity</th>
                        <th class="fw-bold text-dark">Status</th>
                        <th class="fw-bold text-dark text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="menuItemsTable">
                    <?php if (empty($menu_items)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mt-2">No menu items found. Click "Add New Item" to create one.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($menu_items as $item): 
                            $quantity = $item['quantity'] ?? 0;
                            $threshold = $item['low_stock_threshold'] ?? 10;
                            $quantity_class = $quantity <= 0 ? 'quantity-out' : ($quantity <= $threshold ? 'quantity-low' : 'quantity-good');
                            $status_class = $item['status'] == 'In Stock' ? 'bg-success' : ($item['status'] == 'Low Stock' ? 'bg-warning text-dark' : 'bg-danger');
                            $image_html = '';
                            if (!empty($item['image']) && file_exists('../' . $item['image'])) {
                                $image_html = '<img src="../' . $item['image'] . '" class="menu-image" alt="' . htmlspecialchars($item['name']) . '">';
                            } else {
                                $image_html = '<div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:60px;height:60px;"><i class="bi bi-image text-muted fs-3"></i></div>';
                            }
                        ?>
                        <tr data-item-id="<?php echo $item['id']; ?>" 
                            data-item-name="<?php echo htmlspecialchars($item['name']); ?>" 
                            data-item-description="<?php echo htmlspecialchars($item['description'] ?? ''); ?>"
                            data-item-category="<?php echo $item['category']; ?>" 
                            data-item-price="<?php echo $item['price']; ?>" 
                            data-item-quantity="<?php echo $quantity; ?>" 
                            data-item-original-quantity="<?php echo $quantity; ?>"
                            data-item-status="<?php echo $item['status']; ?>" 
                            data-item-is-available="<?php echo $item['is_available']; ?>"
                            data-item-image="<?php echo htmlspecialchars($item['image'] ?? ''); ?>">
                            <td><?php echo $image_html; ?></td>
                            <td class="fw-bold item-name"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><span class="badge bg-secondary item-category"><?php echo $item['category']; ?></span></td>
                            <td class="text-success fw-bold item-price">₱ <?php echo number_format($item['price'], 2); ?></td>
                            <td>
                                <span class="quantity-badge <?php echo $quantity_class; ?>"><?php echo $quantity; ?></span>
                                <?php if ($threshold): ?>
                                    <br><small class="text-muted">threshold: <?php echo $threshold; ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo $status_class; ?> item-status"><?php echo $item['status']; ?></span></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-primary me-1" onclick="editItem(<?php echo $item['id']; ?>)">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-info me-1" onclick="viewItemDetails(<?php echo $item['id']; ?>)">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>')">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========== ADD ITEM MODAL ========== -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-2"></i> Add New Menu Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addItemForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" id="add_description" name="description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Price (₱) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="add_price" name="price" step="0.01" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Initial Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="add_quantity" name="quantity" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Main Dish">Main Dish</option>
                                    <option value="Drinks">Drinks</option>
                                    <option value="Sides">Sides</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Low Stock Threshold</label>
                                <input type="number" class="form-control" id="add_threshold" name="low_stock_threshold" value="10" min="1">
                                <small class="text-muted">Alert when quantity drops below this number</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Item Image</label>
                                <div class="image-upload-area" onclick="document.getElementById('add_image').click()">
                                    <i class="bi bi-cloud-arrow-up display-4 text-muted"></i>
                                    <p class="mt-2 mb-1">Click to upload image</p>
                                    <small class="text-muted">JPG, PNG (Max 2MB)</small>
                                </div>
                                <input type="file" id="add_image" name="image" accept="image/*" style="display: none;" onchange="previewAddImage(event)">
                                <div id="addImagePreview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-save me-2"></i> Save Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== EDIT ITEM MODAL ========== -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Menu Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editItemForm" enctype="multipart/form-data">
                <input type="hidden" id="edit_id" name="item_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Price (₱) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_quantity" name="quantity" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_category" name="category" required>
                                    <option value="Main Dish">Main Dish</option>
                                    <option value="Drinks">Drinks</option>
                                    <option value="Sides">Sides</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Low Stock Threshold</label>
                                <input type="number" class="form-control" id="edit_threshold" name="low_stock_threshold" min="1">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Current Image</label>
                                <div id="currentImageContainer" class="mb-2"></div>
                                <label class="form-label fw-semibold">Change Image</label>
                                <div class="image-upload-area" onclick="document.getElementById('edit_image').click()">
                                    <i class="bi bi-cloud-arrow-up display-4 text-muted"></i>
                                    <p class="mt-2 mb-1">Click to upload new image</p>
                                    <small class="text-muted">Leave empty to keep current image</small>
                                </div>
                                <input type="file" id="edit_image" name="image" accept="image/*" style="display: none;" onchange="previewEditImage(event)">
                                <div id="editImagePreview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i> Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========== VIEW ITEM DETAILS MODAL ========== -->
<div class="modal fade" id="viewItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i> Item Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewItemContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editFromViewBtn">
                    <i class="bi bi-pencil-square me-2"></i> Edit Item
                </button>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    let currentViewItemId = null;
    
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
    
    function filterTable() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const category = document.getElementById('categoryFilter').value;
        const status = document.getElementById('statusFilter').value;
        
        var rows = document.querySelectorAll('#menuItemsTable tr');
        rows.forEach(function(row) {
            if (row.querySelector('td') && row.cells.length > 1) {
                var name = (row.cells[1]?.textContent.toLowerCase() || '');
                var itemCategory = (row.cells[2]?.textContent || '');
                var itemStatus = (row.cells[5]?.textContent || '');
                var show = true;
                if (searchTerm && !name.includes(searchTerm)) show = false;
                if (category && itemCategory !== category) show = false;
                if (status && itemStatus !== status) show = false;
                row.style.display = show ? '' : 'none';
            }
        });
    }
    
    document.getElementById('searchInput').addEventListener('keyup', filterTable);
    document.getElementById('categoryFilter').addEventListener('change', filterTable);
    document.getElementById('statusFilter').addEventListener('change', filterTable);
    
    function previewAddImage(event) {
        const preview = document.getElementById('addImagePreview');
        if (event.target.files && event.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<img src="' + e.target.result + '" class="image-preview" style="max-width: 100px; max-height: 100px; border-radius: 10px;">';
            };
            reader.readAsDataURL(event.target.files[0]);
        }
    }
    
    function previewEditImage(event) {
        const preview = document.getElementById('editImagePreview');
        if (event.target.files && event.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<img src="' + e.target.result + '" class="image-preview" style="max-width: 100px; max-height: 100px; border-radius: 10px;">';
            };
            reader.readAsDataURL(event.target.files[0]);
        }
    }
    
    function editItem(itemId) {
        const row = document.querySelector('tr[data-item-id="' + itemId + '"]');
        if (!row) return;
        document.getElementById('edit_id').value = itemId;
        document.getElementById('edit_name').value = row.getAttribute('data-item-name');
        document.getElementById('edit_description').value = row.getAttribute('data-item-description') || '';
        document.getElementById('edit_price').value = row.getAttribute('data-item-price');
        document.getElementById('edit_quantity').value = row.getAttribute('data-item-quantity');
        document.getElementById('edit_category').value = row.getAttribute('data-item-category');
        document.getElementById('edit_threshold').value = 10;
        
        const currentImage = row.getAttribute('data-item-image');
        const currentImageContainer = document.getElementById('currentImageContainer');
        if (currentImage && currentImage !== '') {
            currentImageContainer.innerHTML = '<img src="../' + currentImage + '" class="image-preview" style="max-width: 100px; max-height: 100px; border-radius: 10px;">';
        } else {
            currentImageContainer.innerHTML = '<div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:100px;height:100px;"><i class="bi bi-image text-muted fs-3"></i></div>';
        }
        document.getElementById('editImagePreview').innerHTML = '';
        document.getElementById('edit_image').value = '';
        new bootstrap.Modal(document.getElementById('editItemModal')).show();
    }
    
    function viewItemDetails(itemId) {
        currentViewItemId = itemId;
        const row = document.querySelector('tr[data-item-id="' + itemId + '"]');
        if (!row) return;
        const name = row.getAttribute('data-item-name');
        const desc = row.getAttribute('data-item-description') || 'No description available.';
        const category = row.getAttribute('data-item-category');
        const price = parseFloat(row.getAttribute('data-item-price')).toFixed(2);
        const quantity = row.getAttribute('data-item-quantity');
        const status = row.getAttribute('data-item-status');
        const isAvailable = row.getAttribute('data-item-is-available') === '1';
        const image = row.getAttribute('data-item-image');
        const imageHtml = image && image !== '' ? '<img src="../' + image + '" class="image-preview" style="width:200px;height:200px;object-fit:cover;border-radius:15px;">' : 
                           '<div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:200px;height:200px;border-radius:15px;"><i class="bi bi-image text-muted fs-1"></i></div>';
        document.getElementById('viewItemContent').innerHTML = `
            <div class="row">
                <div class="col-md-5 text-center">${imageHtml}</div>
                <div class="col-md-7">
                    <h3 class="fw-bold text-primary">${escapeHtml(name)}</h3>
                    <hr>
                    <p><strong>Description:</strong> ${escapeHtml(desc)}</p>
                    <p><strong>Category:</strong> <span class="badge bg-secondary">${escapeHtml(category)}</span></p>
                    <p><strong>Price:</strong> <span class="text-success fw-bold fs-4">₱ ${price}</span></p>
                    <p><strong>Quantity:</strong> <span class="fw-bold ${quantity <= 0 ? 'text-danger' : quantity <= 10 ? 'text-warning' : 'text-success'}">${quantity} units</span></p>
                    <p><strong>Status:</strong> <span class="badge ${status === 'In Stock' ? 'bg-success' : status === 'Low Stock' ? 'bg-warning text-dark' : 'bg-danger'}">${status}</span></p>
                    <p><strong>Available:</strong> ${isAvailable ? '<i class="bi bi-check-circle text-success"></i> Yes' : '<i class="bi bi-x-circle text-danger"></i> No'}</p>
                </div>
            </div>
        `;
        new bootstrap.Modal(document.getElementById('viewItemModal')).show();
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    document.getElementById('editFromViewBtn')?.addEventListener('click', function() {
        if (currentViewItemId) {
            bootstrap.Modal.getInstance(document.getElementById('viewItemModal')).hide();
            setTimeout(function() { editItem(currentViewItemId); }, 300);
        }
    });
    
    // FIXED DELETE FUNCTION
    async function deleteItem(itemId, itemName) {
        if (confirm('Are you sure you want to delete "' + itemName + '"? This action cannot be undone.')) {
            showSpinner(true);
            
            try {
                const response = await fetch('../api/delete_menu_item.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ item_id: itemId })
                });
                
                // Check if response is ok
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                
                const data = await response.json();
                showSpinner(false);
                
                if (data.success) {
                    showToast(data.message, 'success');
                    // Remove the row from the table
                    const row = document.querySelector('tr[data-item-id="' + itemId + '"]');
                    if (row) {
                        row.remove();
                    }
                    // Check if table is empty
                    const remainingRows = document.querySelectorAll('#menuItemsTable tr:not([style*="display: none"])');
                    if (remainingRows.length === 0 || (remainingRows.length === 1 && remainingRows[0].cells.length === 1)) {
                        document.getElementById('menuItemsTable').innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1"></i><p class="mt-2">No menu items found. Click "Add New Item" to create one.</p></td></tr>';
                    }
                } else {
                    showToast(data.message || 'Failed to delete item', 'danger');
                }
            } catch (error) {
                showSpinner(false);
                console.error('Delete error:', error);
                showToast('Error deleting item: ' + error.message, 'danger');
            }
        }
    }
    
    // ADD ITEM FORM SUBMIT
    document.getElementById('addItemForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        showSpinner(true);
        const formData = new FormData(this);
        try {
            const response = await fetch('../api/add_menu_item.php', { 
                method: 'POST', 
                body: formData 
            });
            const data = await response.json();
            showSpinner(false);
            if (data.success) {
                showToast(data.message);
                bootstrap.Modal.getInstance(document.getElementById('addItemModal')).hide();
                this.reset();
                document.getElementById('addImagePreview').innerHTML = '';
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showToast(data.message, 'danger');
            }
        } catch (error) {
            showSpinner(false);
            showToast('Error adding item: ' + error.message, 'danger');
        }
    });
    
    // EDIT ITEM FORM SUBMIT
    document.getElementById('editItemForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        showSpinner(true);
        const formData = new FormData(this);
        try {
            const response = await fetch('../api/update_menu_item.php', { 
                method: 'POST', 
                body: formData 
            });
            const data = await response.json();
            showSpinner(false);
            if (data.success) {
                showToast(data.message);
                bootstrap.Modal.getInstance(document.getElementById('editItemModal')).hide();
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showToast(data.message, 'danger');
            }
        } catch (error) {
            showSpinner(false);
            showToast('Error updating item: ' + error.message, 'danger');
        }
    });
</script>
</body>
</html>