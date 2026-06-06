<?php
// api/place_order.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

$items = $data['items'] ?? [];
$order_type = $data['order_type'] ?? 'Take-Out';
$total_amount = $data['total_amount'] ?? 0;
$user_id = $_SESSION['user_id'];
$customer_name = $_SESSION['full_name'];

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Check user balance
    $stmt = $pdo->prepare("SELECT credit_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || $user['credit_balance'] < $total_amount) {
        echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
        $pdo->rollBack();
        exit;
    }
    
    // Generate order code
    $order_code = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Insert order
    $stmt = $pdo->prepare("
        INSERT INTO orders (order_code, user_id, customer_name, total_amount, order_type, status, payment_method, payment_status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'New', 'credit', 'pending', NOW())
    ");
    $stmt->execute([$order_code, $user_id, $customer_name, $total_amount, $order_type]);
    $order_id = $pdo->lastInsertId();
    
    // Insert order items
    foreach ($items as $item) {
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, unit_price, subtotal, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $order_id,
            $item['id'],
            $item['name'],
            $item['quantity'],
            $item['price'],
            $item['quantity'] * $item['price']
        ]);
        
        // Update inventory
        $stmt = $pdo->prepare("UPDATE menu_items SET quantity = quantity - ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['id']]);
    }
    
    // Deduct from user balance
    $stmt = $pdo->prepare("UPDATE users SET credit_balance = credit_balance - ? WHERE id = ?");
    $stmt->execute([$total_amount, $user_id]);
    
    // Record transaction
    $stmt = $pdo->prepare("
        INSERT INTO credit_transactions (user_id, type, amount, new_balance, reason, created_at) 
        SELECT ?, 'Deduction', ?, credit_balance, CONCAT('Order #', ?, ' payment'), NOW()
        FROM users WHERE id = ?
    ");
    $stmt->execute([$user_id, $total_amount, $order_code, $user_id]);
    
    // Update session balance
    $_SESSION['credit_balance'] = $user['credit_balance'] - $total_amount;
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Order placed successfully!',
        'order_code' => $order_code,
        'new_balance' => $_SESSION['credit_balance']
    ]);
    
} catch(PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>