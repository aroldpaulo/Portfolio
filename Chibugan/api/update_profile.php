<?php
// api/update_profile.php - FIXED with password hashing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

require_once '../config/database.php';

try {
    // Get current user data
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $update_fields = [];
    $params = [];
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG and PNG images are allowed']);
            exit();
        }
        
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Image size must be less than 2MB']);
            exit();
        }
        
        $upload_dir = '../uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $db_path = 'uploads/profile_pictures/' . $file_name;
            $update_fields[] = "profile_picture = ?";
            $params[] = $db_path;
            
            // Delete old profile picture
            $old_pic_stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $old_pic_stmt->execute([$user_id]);
            $old_pic = $old_pic_stmt->fetchColumn();
            if ($old_pic && file_exists('../' . $old_pic)) {
                unlink('../' . $old_pic);
            }
        }
    }
    
    // Update full name
    if (isset($_POST['full_name']) && !empty($_POST['full_name'])) {
        $update_fields[] = "full_name = ?";
        $params[] = trim($_POST['full_name']);
        $_SESSION['full_name'] = trim($_POST['full_name']);
    }
    
    // Update phone number
    if (isset($_POST['phone_number'])) {
        $update_fields[] = "phone_number = ?";
        $params[] = trim($_POST['phone_number']);
        $_SESSION['phone_number'] = trim($_POST['phone_number']);
    }
    
    // Handle password change - WITH HASHING
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (!empty($current_password) && !empty($new_password)) {
        // Verify current password (works with both hashed and plain text)
        $password_valid = false;
        
        // Check if stored password is hashed
        if (preg_match('/^\$2[ay]\$/', $user['password'])) {
            $password_valid = password_verify($current_password, $user['password']);
        } else {
            // Plain text password (old data)
            $password_valid = ($current_password === $user['password']);
        }
        
        if (!$password_valid) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit();
        }
        
        if (strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
            exit();
        }
        
        // HASH the new password before storing
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_fields[] = "password = ?";
        $params[] = $hashed_password;
    }
    
    if (empty($update_fields)) {
        echo json_encode(['success' => true, 'message' => 'No changes to update']);
        exit();
    }
    
    // Build and execute update query
    $update_fields[] = "updated_at = NOW()";
    $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
    $params[] = $user_id;
    
    $update_stmt = $pdo->prepare($sql);
    $update_stmt->execute($params);
    
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    
} catch(PDOException $e) {
    error_log("Profile update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>