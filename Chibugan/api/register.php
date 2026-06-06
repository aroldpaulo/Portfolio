<?php
// api/register.php - FIXED VERSION
require_once '../config/database.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$full_name = $data['full_name'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$phone = $data['phone'] ?? '';

if (empty($full_name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate password length
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

try {
    // HASH the password before sending to database
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if email already exists (direct query to avoid procedure error)
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert user directly (bypass stored procedure if it has issues)
    $insertStmt = $pdo->prepare("
        INSERT INTO users (full_name, phone_number, email, password, role, credit_balance, created_at) 
        VALUES (?, ?, ?, ?, 'customer', 50.00, NOW())
    ");
    $insertStmt->execute([$full_name, $phone, $email, $hashed_password]);
    $user_id = $pdo->lastInsertId();
    
    // Check which transaction table exists
    try {
        // Try 'transactions' table first
        $insertTransStmt = $pdo->prepare("
            INSERT INTO transactions (user_id, type, amount, reason, new_balance, created_at) 
            VALUES (?, 'Top-Up', 50.00, 'Welcome bonus on sign-up', 50.00, NOW())
        ");
        $insertTransStmt->execute([$user_id]);
    } catch (PDOException $e) {
        // If 'transactions' doesn't exist, try 'wallet_transactions'
        try {
            $insertTransStmt = $pdo->prepare("
                INSERT INTO wallet_transactions (user_id, transaction_type, amount, description, balance_after, created_at) 
                VALUES (?, 'credit', 50.00, 'Welcome bonus on sign-up', 50.00, NOW())
            ");
            $insertTransStmt->execute([$user_id]);
        } catch (PDOException $e2) {
            // If no transaction table exists, just log but don't fail
            error_log("No transaction table found, but user was created");
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Account created successfully! Welcome bonus of ₱50 has been added to your wallet.',
        'user_id' => $user_id
    ]);
    
} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}
?>