<?php
// api/upload_menu_image.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is admin or cashier
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'cashier')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_FILES['menu_image']) || $_FILES['menu_image']['error'] != 0) {
    echo json_encode(['success' => false, 'message' => 'No image uploaded']);
    exit;
}

$menu_item_id = $_POST['menu_item_id'] ?? 0;

if ($menu_item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid menu item ID']);
    exit;
}

$upload_dir = '../uploads/menu/';

// Create directory if not exists
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_extension = pathinfo($_FILES['menu_image']['name'], PATHINFO_EXTENSION);
$new_filename = 'item_' . $menu_item_id . '_' . time() . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// Allowed file types
$allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];

if (!in_array($_FILES['menu_image']['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed']);
    exit;
}

// Max file size 5MB
if ($_FILES['menu_image']['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB']);
    exit;
}

if (move_uploaded_file($_FILES['menu_image']['tmp_name'], $upload_path)) {
    $image_path = 'uploads/menu/' . $new_filename;
    
    // Update database
    $stmt = $pdo->prepare("UPDATE menu_items SET image = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$image_path, $menu_item_id]);
    
    echo json_encode(['success' => true, 'message' => 'Image uploaded successfully', 'image_path' => $image_path]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
}
?>