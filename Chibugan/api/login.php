<?php
// api/login.php - FIXED VERSION with password_verify
require_once '../config/database.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$requested_role = $data['role'] ?? 'all';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

try {
    // Get user by email
    $stmt = $pdo->prepare("SELECT id, email, password, full_name, role, credit_balance, profile_picture, phone_number FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    
    // Verify password (works with both hashed and plain text for backward compatibility)
    $password_valid = false;
    
    // Check if password is hashed (starts with $2y$)
    if (preg_match('/^\$2[ay]\$/', $user['password'])) {
        $password_valid = password_verify($password, $user['password']);
    } else {
        // Plain text password (old data) - verify directly
        $password_valid = ($password === $user['password']);
    }
    
    if (!$password_valid) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    
    // Check role if specified
    if ($requested_role !== 'all' && strtolower($user['role']) !== strtolower($requested_role)) {
        echo json_encode(['success' => false, 'message' => 'Access denied. Invalid role for this login portal.']);
        exit;
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = strtolower($user['role']);
    $_SESSION['credit_balance'] = floatval($user['credit_balance'] ?? 0);
    $_SESSION['email'] = $user['email'];
    $_SESSION['profile_picture'] = $user['profile_picture'];
    $_SESSION['phone_number'] = $user['phone_number'];
    
    echo json_encode([
        'success' => true,
        'role' => strtolower($user['role']),
        'user_id' => $user['id'],
        'full_name' => $user['full_name'],
        'credit_balance' => floatval($user['credit_balance'] ?? 0),
        'message' => 'Login successful'
    ]);
    
} catch(PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Login error: ' . $e->getMessage()]);
}
?>