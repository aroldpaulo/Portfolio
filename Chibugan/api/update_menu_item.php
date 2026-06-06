<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$item_id = $_POST['item_id'] ?? 0;
$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? 0;
$quantity = $_POST['quantity'] ?? 0;
$category = $_POST['category'] ?? '';
$low_stock_threshold = $_POST['low_stock_threshold'] ?? 10;

if ($item_id <= 0 || empty($name) || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    // Determine status
    if ($quantity == 0) {
        $status = 'Out of Stock';
        $is_available = 0;
    } elseif ($quantity <= $low_stock_threshold) {
        $status = 'Low Stock';
        $is_available = 1;
    } else {
        $status = 'In Stock';
        $is_available = 1;
    }
    
    // Handle image upload
    $new_image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../uploads/menu/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'item_' . $item_id . '_' . time() . '.' . $ext;
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $new_image = 'uploads/menu/' . $filename;
            
            // Delete old image
            $stmt = $pdo->prepare("SELECT image FROM menu_items WHERE id = ?");
            $stmt->execute([$item_id]);
            $old_image = $stmt->fetchColumn();
            if ($old_image && file_exists('../' . $old_image)) {
                unlink('../' . $old_image);
            }
        }
    }
    
    // Build update query
    $sql = "UPDATE menu_items SET name = ?, description = ?, price = ?, quantity = ?, 
            low_stock_threshold = ?, status = ?, is_available = ?, category = ?, updated_at = NOW()";
    $params = [$name, $description, $price, $quantity, $low_stock_threshold, $status, $is_available, $category];
    
    if ($new_image) {
        $sql .= ", image = ?";
        $params[] = $new_image;
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $item_id;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'message' => 'Menu item updated successfully', 'item_id' => $item_id, 'new_image' => $new_image]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>