<?php
// api/cancel_order.php - FIXED FOR CUSTOMERS
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$order_id = $data['order_id'] ?? 0;
$reason = $data['reason'] ?? '';

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // Call stored procedure for cancellation
    $stmt = $pdo->prepare("CALL sp_cancel_customer_order(?, ?, ?, @success, @message)");
    $stmt->execute([$order_id, $_SESSION['user_id'], $reason]);
    
    $result = $pdo->query("SELECT @success AS success, @message AS message")->fetch();
    
    // Update session balance if successful
    if ($result['success'] == 1) {
        // Refresh user balance in session
        $stmt = $pdo->prepare("SELECT credit_balance FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $new_balance = $stmt->fetchColumn();
        $_SESSION['credit_balance'] = $new_balance;
    }
    
    echo json_encode([
        'success' => $result['success'] == 1,
        'message' => $result['message']
    ]);
    
} catch(PDOException $e) {
    error_log("Cancel order error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>