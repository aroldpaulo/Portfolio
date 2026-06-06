<?php
// api/logout.php
require_once '../config/session.php';

if (isset($_SESSION['user_id'])) {
    // Optional: Call stored procedure to log logout
    // require_once '../config/database.php';
    // $pdo->prepare("CALL sp_user_logout(?, @success, @message)")->execute([$_SESSION['user_id']]);
}

session_destroy();
header('Location: ../index.php');
exit;
?>