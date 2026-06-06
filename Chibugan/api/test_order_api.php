<?php
// api/test_order_api.php
session_start();
echo "<h1>Order API Test</h1>";

// Check session
echo "<h2>Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check database connection
require_once '../config/database.php';
echo "<h2>Database Connection:</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $count = $stmt->fetchColumn();
    echo "<p style='color:green'>✅ Database connected. Orders found: $count</p>";
} catch(Exception $e) {
    echo "<p style='color:red'>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test the update API directly
if (isset($_GET['test'])) {
    $order_id = $_GET['order_id'] ?? 0;
    $status = $_GET['status'] ?? '';
    
    if ($order_id && $status) {
        echo "<h2>Testing Update:</h2>";
        
        $data = json_encode(['order_id' => $order_id, 'status' => $status]);
        echo "<p>Sending: $data</p>";
        
        $ch = curl_init('http://' . $_SERVER['HTTP_HOST'] . '/ADVANCEDATABASE/test/PROJECT2/api/update_order_status.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "<p>HTTP Code: $httpCode</p>";
        echo "<p>Response: $response</p>";
        
        $json = json_decode($response, true);
        if ($json) {
            echo "<p style='color:green'>✅ Valid JSON response</p>";
            echo "<pre>";
            print_r($json);
            echo "</pre>";
        } else {
            echo "<p style='color:red'>❌ Invalid JSON response</p>";
        }
    }
}

// Show order IDs to test with
echo "<h2>Available Orders:</h2>";
$stmt = $pdo->query("SELECT id, order_code, status FROM orders LIMIT 10");
$orders = $stmt->fetchAll();
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Order Code</th><th>Status</th><th>Test Link</th></tr>";
foreach ($orders as $order) {
    echo "<tr>";
    echo "<td>{$order['id']}</td>";
    echo "<td>{$order['order_code']}</td>";
    echo "<td>{$order['status']}</td>";
    echo "<td><a href='?test=1&order_id={$order['id']}&status=Preparing'>Test to Preparing</a> | ";
    echo "<a href='?test=1&order_id={$order['id']}&status=Completed'>Test to Completed</a> | ";
    echo "<a href='?test=1&order_id={$order['id']}&status=Cancelled'>Test to Cancelled</a></td>";
    echo "</tr>";
}
echo "</table>";
?>