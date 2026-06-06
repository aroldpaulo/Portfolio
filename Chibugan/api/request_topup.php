<?php
// api/request_topup.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$amount = $data['amount'] ?? 0;

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount']);
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
    // Call the stored procedure
    $stmt = $pdo->prepare("CALL sp_request_topup(?, ?, @request_id, @message)");
    $stmt->execute([$_SESSION['user_id'], $amount]);
    
    // Get the output parameters
    $stmt = $pdo->query("SELECT @request_id as request_id, @message as message");
    $result = $stmt->fetch();
    
    if ($result && $result['request_id'] > 0) {
        echo json_encode([
            'success' => true, 
            'message' => $result['message'],
            'request_id' => $result['request_id']
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => $result['message'] ?? 'Failed to submit request'
        ]);
    }
    
} catch(PDOException $e) {
    error_log("Top-up request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>