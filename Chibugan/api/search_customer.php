<?php
// api/search_customer.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is cashier or admin
$user_role = strtolower($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || !in_array($user_role, ['admin', 'cashier'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if (empty($search)) {
    echo json_encode(['success' => false, 'message' => 'Search term required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, full_name, email, phone_number, credit_balance 
        FROM users 
        WHERE role = 'Customer' 
        AND (full_name LIKE ? OR email LIKE ? OR phone_number LIKE ? OR id LIKE ?)
        ORDER BY full_name ASC
        LIMIT 10
    ");
    $search_param = "%$search%";
    $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
    $customers = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'customers' => $customers]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>