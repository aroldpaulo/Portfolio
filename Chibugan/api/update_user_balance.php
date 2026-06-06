<?php
// api/update_user_balance.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['user_id'] ?? 0;
$transaction_type = $data['transaction_type'] ?? '';
$amount = $data['amount'] ?? 0;
$reason = $data['reason'] ?? 'Admin adjustment';
$new_password = $data['password'] ?? '';
$status = $data['status'] ?? '';

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $response = ['success' => true, 'message' => 'User updated successfully'];
    $new_balance = null;
    
    // Update balance if transaction type is selected
    if (!empty($transaction_type) && $amount > 0) {
        $stmt = $pdo->prepare("SELECT credit_balance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_balance = $stmt->fetchColumn();
        
        if ($transaction_type == 'topup') {
            $new_balance = $current_balance + $amount;
            $transaction_type_name = 'Top-Up';
            $reason_text = $reason ?: "Admin added ₱" . number_format($amount, 2);
        } elseif ($transaction_type == 'deduct') {
            if ($amount > $current_balance) {
                throw new Exception("Cannot deduct more than current balance");
            }
            $new_balance = $current_balance - $amount;
            $transaction_type_name = 'Deduction';
            $reason_text = $reason ?: "Admin deducted ₱" . number_format($amount, 2);
        } elseif ($transaction_type == 'reset') {
            $new_balance = 0;
            $transaction_type_name = 'Deduction';
            $reason_text = $reason ?: "Admin reset balance to zero";
        } else {
            throw new Exception("Invalid transaction type");
        }
        
        $stmt = $pdo->prepare("UPDATE users SET credit_balance = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_balance, $user_id]);
        
        $stmt = $pdo->prepare("
            INSERT INTO credit_transactions (user_id, cashier_id, type, amount, new_balance, reason, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $_SESSION['user_id'], $transaction_type_name, $amount, $new_balance, $reason_text]);
        
        $response['new_balance'] = $new_balance;
        $response['message'] = "Balance updated successfully! New balance: ₱" . number_format($new_balance, 2);
    }
    
    // Update password if provided
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            throw new Exception("Password must be at least 6 characters");
        }
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_password, $user_id]);
        $response['message'] .= " Password has been reset.";
    }
    
    // Update status if provided
    if (!empty($status)) {
        $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $user_id]);
        $response['message'] .= " Status updated to " . $status . ".";
    }
    
    // Update phone number if provided
    if (isset($data['phone_number'])) {
        $stmt = $pdo->prepare("UPDATE users SET phone_number = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$data['phone_number'], $user_id]);
    }
    
    $pdo->commit();
    echo json_encode($response);
    
} catch(Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch(PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>