<?php
session_start();
header('Content-Type: application/json');

echo json_encode([
    'user_id' => $_SESSION['user_id'] ?? null,
    'role' => $_SESSION['role'] ?? null,
    'role_type' => gettype($_SESSION['role'] ?? null),
    'full_name' => $_SESSION['full_name'] ?? null,
    'email' => $_SESSION['email'] ?? null,
    'is_admin' => in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'cashier'])
]);
?>