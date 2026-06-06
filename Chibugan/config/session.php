<?php
// config/session.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isCustomer() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'customer';
}

function isCashier() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'cashier';
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function isStaff() {
    return isCashier() || isAdmin();
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
}

function requireCustomer() {
    requireLogin();
    if (!isCustomer()) {
        header('Location: ../index.php');
        exit();
    }
}

function requireStaff() {
    requireLogin();
    if (!isStaff()) {
        header('Location: ../index.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ../index.php');
        exit();
    }
}
?>