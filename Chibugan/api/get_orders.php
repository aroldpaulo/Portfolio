<?php
// api/get_orders.php - USES STORED PROCEDURE sp_customer_orders
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // CALL THE STORED PROCEDURE
    $stmt = $pdo->prepare("CALL sp_customer_orders(?, 20)");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'orders' => $orders]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to load orders']);
}
?>