<?php
// api/approve_topup.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin or cashier (FIX: case-insensitive)
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
    // Call the stored procedure
    $stmt = $pdo->prepare("CALL sp_approve_topup(?, ?, @success, @message)");
    $stmt->execute([$request_id, $_SESSION['user_id']]);
    
    // Get the output parameters
    $stmt = $pdo->query("SELECT @success as success, @message as message");
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => $result['success'] == 1,
        'message' => $result['message']
    ]);
    
} catch(PDOException $e) {
    error_log("Approve top-up error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>