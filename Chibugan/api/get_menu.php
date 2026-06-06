<?php
// api/get_menu.php - USES VIEW view_active_menu
require_once '../config/database.php';

try {
    // Using the VIEW - database handles filtering!
    $stmt = $pdo->query("SELECT * FROM view_active_menu ORDER BY category, name");
    $menu = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'menu' => $menu]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to load menu']);
}
?>