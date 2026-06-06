<?php
// api/reject_topup.php
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
$request_id = $data['request_id'] ?? 0;

if ($request_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Update request status to rejected
    $stmt = $pdo->prepare("
        UPDATE wallet_topup_requests 
        SET status = 'rejected', processed_at = NOW() 
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$request_id]);
    
    if ($stmt->rowCount() == 0) {
        throw new Exception("Request not found or already processed");
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Top-up request rejected']);
    
} catch(Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch(PDOException $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>