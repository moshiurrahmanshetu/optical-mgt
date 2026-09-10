<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Update Profile Processor
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('auth/profile.php');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    setFlash('error', 'Invalid security token. Please try again.');
    redirect('auth/profile.php');
}

$userId = currentUserId();
$name   = trim($_POST['name'] ?? '');
$email  = trim($_POST['email'] ?? '');
$phone  = trim($_POST['phone'] ?? '');

// Validation
if (empty($name)) {
    setFlash('error', 'Full Name is required.');
    redirect('auth/profile.php');
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setFlash('error', 'A valid email address is required.');
    redirect('auth/profile.php');
}

try {
    $pdo = getDbConnection();

    // Check if email is already taken by another user
    $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
    $emailCheck->execute(['email' => $email, 'id' => $userId]);
    if ($emailCheck->fetch()) {
        setFlash('error', 'This email address is already in use by another account.');
        redirect('auth/profile.php');
    }

    // Get current user avatar
    $currStmt = $pdo->prepare("SELECT avatar FROM users WHERE id = :id LIMIT 1");
    $currStmt->execute(['id' => $userId]);
    $currentAvatar = $currStmt->fetchColumn() ?: null;

    // Handle Avatar Upload
    $newAvatarFilename = $currentAvatar;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = handleAvatarUpload($_FILES['avatar'], $currentAvatar);
        if (!$uploadResult['success']) {
            setFlash('error', $uploadResult['error'] ?? 'Avatar upload failed.');
            redirect('auth/profile.php');
        }
        $newAvatarFilename = $uploadResult['filename'];
    }

    // Update user record
    $updateStmt = $pdo->prepare("
        UPDATE users 
        SET name = :name, 
            email = :email, 
            phone = :phone, 
            avatar = :avatar,
            updated_at = NOW()
        WHERE id = :id
    ");

    $updateStmt->execute([
        'name'   => $name,
        'email'  => $email,
        'phone'  => $phone ?: null,
        'avatar' => $newAvatarFilename,
        'id'     => $userId
    ]);

    // Refresh session data
    refreshUserSession();

    setFlash('success', 'Your profile has been updated successfully.');
} catch (Exception $e) {
    error_log('Profile Update Error: ' . $e->getMessage());
    setFlash('error', 'An error occurred while updating your profile.');
}

redirect('auth/profile.php');
