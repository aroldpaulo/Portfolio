<?php
// api/update_order_status.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

// Allow admin and cashier
$user_role = strtolower($_SESSION['role'] ?? '');
if (!in_array($user_role, ['admin', 'cashier'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$new_status = isset($data['status']) ? trim($data['status']) : '';

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

$valid_statuses = ['New', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get current order
    $stmt = $pdo->prepare("SELECT status, payment_method, total_amount, user_id, order_code FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $old_status = $order['status'];
    
    // Prevent invalid transitions
    if ($old_status == 'Completed' || $old_status == 'Cancelled') {
        echo json_encode(['success' => false, 'message' => "Cannot change status of {$old_status} order"]);
        exit;
    }
    
    // Update status
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    // If completing, also update payment status
    if ($new_status == 'Completed') {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?");
        $stmt->execute([$order_id]);
    }
    
    // If cancelling and paid with credit, refund
    if ($new_status == 'Cancelled') {
        if ($order['payment_method'] == 'credit' || $order['payment_method'] === null) {
            $stmt = $pdo->prepare("UPDATE users SET credit_balance = credit_balance + ? WHERE id = ?");
            $stmt->execute([$order['total_amount'], $order['user_id']]);
            
            $stmt = $pdo->prepare("
                INSERT INTO credit_transactions (user_id, cashier_id, type, amount, new_balance, reason, created_at) 
                SELECT ?, ?, 'Top-Up', ?, credit_balance, CONCAT('Refund for cancelled order #', ?), NOW()
                FROM users WHERE id = ?
            ");
            $stmt->execute([$order['user_id'], $_SESSION['user_id'], $order['total_amount'], $order['order_code'], $order['user_id']]);
        }
        
        $stmt = $pdo->prepare("UPDATE orders SET cancellation_reason = ?, cancelled_at = NOW() WHERE id = ?");
        $stmt->execute(['Cancelled by ' . $_SESSION['role'], $order_id]);
    }
    
    // Log status change
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action_type, description, created_at) 
        VALUES (?, 'STATUS_CHANGE', CONCAT('Order #', ?, ' changed from ', ?, ' to ', ?), NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $order['order_code'], $old_status, $new_status]);
    
    $pdo->commit();
    
    $message = "Order #{$order['order_code']} updated from $old_status to $new_status";
    if ($new_status == 'Cancelled') {
        $message .= ". Refund of ₱" . number_format($order['total_amount'], 2) . " processed.";
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch(Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} catch(PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>