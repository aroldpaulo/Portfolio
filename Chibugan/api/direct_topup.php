<?php
// api/direct_topup.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is cashier or admin
$user_role = strtolower($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || !in_array($user_role, ['admin', 'cashier'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$customer_id = $data['customer_id'] ?? 0;
$amount = $data['amount'] ?? 0;
$notes = $data['notes'] ?? 'Cashier direct top-up';

if ($customer_id <= 0 || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer or amount']);
    exit;
}

if ($amount < 50) {
    echo json_encode(['success' => false, 'message' => 'Minimum top-up amount is ₱50']);
    exit;
}

if ($amount > 50000) {
    echo json_encode(['success' => false, 'message' => 'Maximum top-up amount is ₱50,000']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get current balance
    $stmt = $pdo->prepare("SELECT credit_balance FROM users WHERE id = ? AND role = 'Customer'");
    $stmt->execute([$customer_id]);
    $current_balance = $stmt->fetchColumn();
    
    if ($current_balance === false) {
        throw new Exception("Customer not found");
    }
    
    $new_balance = $current_balance + $amount;
    
    // Update balance
    $stmt = $pdo->prepare("UPDATE users SET credit_balance = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_balance, $customer_id]);
    
    // Record transaction
    $stmt = $pdo->prepare("
        INSERT INTO credit_transactions (user_id, cashier_id, type, amount, new_balance, reason, created_at) 
        VALUES (?, ?, 'Top-Up', ?, ?, ?, NOW())
    ");
    $stmt->execute([$customer_id, $_SESSION['user_id'], $amount, $new_balance, $notes]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "✅ Top-up of ₱" . number_format($amount, 2) . " completed successfully!",
        'new_balance' => $new_balance
    ]);
    
} catch(Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch(PDOException $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>