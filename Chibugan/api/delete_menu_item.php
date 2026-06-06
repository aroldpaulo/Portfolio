<?php
// api/delete_menu_item.php - FIXED VERSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['item_id']) || empty($input['item_id'])) {
    echo json_encode(['success' => false, 'message' => 'Item ID is required']);
    exit();
}

$item_id = intval($input['item_id']);

// Database connection
require_once '../config/database.php';

try {
    // First, check if the item exists
    $checkStmt = $pdo->prepare("SELECT id, name FROM menu_items WHERE id = ?");
    $checkStmt->execute([$item_id]);
    $item = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Menu item not found']);
        exit();
    }
    
    // Check if the item is used in any orders (optional - to prevent deletion of items with order history)
    $checkOrdersStmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_items WHERE menu_item_id = ?");
    $checkOrdersStmt->execute([$item_id]);
    $orderCount = $checkOrdersStmt->fetch(PDO::FETCH_ASSOC);
    
    // If the item has been ordered before, you might want to just disable it instead of deleting
    if ($orderCount['count'] > 0) {
        // Option 1: Soft delete - just disable the item
        $updateStmt = $pdo->prepare("UPDATE menu_items SET is_available = 0, status = 'Out of Stock' WHERE id = ?");
        $updateStmt->execute([$item_id]);
        echo json_encode(['success' => true, 'message' => 'Item has been disabled (has order history)']);
        exit();
    }
    
    // If no order history, delete the item
    // First, get the image path to delete the file
    $imageStmt = $pdo->prepare("SELECT image FROM menu_items WHERE id = ?");
    $imageStmt->execute([$item_id]);
    $imageData = $imageStmt->fetch(PDO::FETCH_ASSOC);
    
    // Delete the item from database
    $deleteStmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
    $deleteStmt->execute([$item_id]);
    
    // Delete the image file if it exists
    if ($imageData && !empty($imageData['image'])) {
        $imagePath = '../' . $imageData['image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Menu item deleted successfully']);
    
} catch(PDOException $e) {
    error_log("Delete menu item error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>