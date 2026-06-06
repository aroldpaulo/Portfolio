<?php
// api/get_balance.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT credit_balance FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $balance = $stmt->fetchColumn();
    
    echo json_encode(['success' => true, 'balance' => $balance]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to get balance']);
}
?>