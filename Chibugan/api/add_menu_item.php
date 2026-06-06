<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? 0;
$quantity = $_POST['quantity'] ?? 0;
$category = $_POST['category'] ?? '';
$low_stock_threshold = $_POST['low_stock_threshold'] ?? 10;

if (empty($name) || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    // Generate item code
    $item_code = 'MI' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Determine status
    if ($quantity == 0) {
        $status = 'Out of Stock';
    } elseif ($quantity <= $low_stock_threshold) {
        $status = 'Low Stock';
    } else {
        $status = 'In Stock';
    }
    
    // Handle image upload
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../uploads/menu/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'item_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $image_path = 'uploads/menu/' . $filename;
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO menu_items (item_code, name, description, price, quantity, low_stock_threshold, 
                               status, category, is_available, image, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
    ");
    $stmt->execute([$item_code, $name, $description, $price, $quantity, $low_stock_threshold, 
                    $status, $category, $image_path]);
    
    echo json_encode(['success' => true, 'message' => 'Menu item added successfully']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>