<?php
// api/get_pending_topups.php - FIXED VERSION
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

// Check if user is logged in and is staff (admin or cashier)
if (!isLoggedIn() || (!isAdmin() && !isCashier())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT wtr.*, u.full_name, u.email 
        FROM wallet_topup_requests wtr 
        JOIN users u ON wtr.user_id = u.id 
        WHERE wtr.status = 'pending' 
        ORDER BY wtr.created_at ASC
    ");
    
    $requests = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'requests' => $requests]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>