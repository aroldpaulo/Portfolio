<?php
// api/create_user.php - FIXED VERSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

// Validate required fields
$required_fields = ['full_name', 'email', 'role', 'password'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit();
    }
}

$full_name = trim(htmlspecialchars($input['full_name']));
$email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
$phone_number = isset($input['phone_number']) ? trim(htmlspecialchars($input['phone_number'])) : '';
$role = ucfirst(strtolower(trim($input['role'])));
$password = $input['password'];
$initial_balance = isset($input['initial_balance']) ? floatval($input['initial_balance']) : 0;

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Validate role
$allowed_roles = ['Admin', 'Cashier', 'Customer'];
if (!in_array($role, $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role. Allowed: Admin, Cashier, Customer']);
    exit();
}

// Validate password length
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit();
}

// For non-customer roles, initial balance should be 0
if ($role !== 'Customer') {
    $initial_balance = 0;
}

require_once '../config/database.php';

try {
    // Check if email already exists
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([$email]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit();
    }
    
    // HASH the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert new user
    $insertStmt = $pdo->prepare("
        INSERT INTO users (full_name, phone_number, email, password, role, credit_balance, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'Active', NOW())
    ");
    $insertStmt->execute([$full_name, $phone_number, $email, $hashed_password, $role, $initial_balance]);
    $user_id = $pdo->lastInsertId();
    
    // If customer and initial balance > 0, create transaction record
    if ($role === 'Customer' && $initial_balance > 0) {
        try {
            // Try 'transactions' table first
            $insertTransStmt = $pdo->prepare("
                INSERT INTO transactions (user_id, type, amount, reason, new_balance, created_at) 
                VALUES (?, 'Top-Up', ?, 'Initial credit on account creation', ?, NOW())
            ");
            $insertTransStmt->execute([$user_id, $initial_balance, $initial_balance]);
        } catch (PDOException $e) {
            // If 'transactions' doesn't exist, try 'wallet_transactions'
            try {
                $insertTransStmt = $pdo->prepare("
                    INSERT INTO wallet_transactions (user_id, transaction_type, amount, description, balance_after, created_at) 
                    VALUES (?, 'credit', ?, 'Initial credit on account creation', ?, NOW())
                ");
                $insertTransStmt->execute([$user_id, $initial_balance, $initial_balance]);
            } catch (PDOException $e2) {
                // No transaction table, just continue
                error_log("No transaction table found, but user was created");
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "User account created successfully! Role: $role",
        'user_id' => $user_id
    ]);
    
} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Create user error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>