<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Change Password Page & Handler
 */

$pageTitle = 'Change Password';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash.php';

requireLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken       = $_POST['csrf_token'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', 'Security session expired. Please try again.');
        redirect('auth/change-password.php');
    }

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All password fields are required.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } else {
        try {
            $pdo = getDbConnection();
            $userId = currentUserId();

            // Fetch current password hash from DB
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $userId]);
            $hash = $stmt->fetchColumn();

            if (!$hash || !password_verify($currentPassword, $hash)) {
                $error = 'The current password you entered is incorrect.';
            } else {
                // Update password
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
                $updateStmt->execute([
                    'password' => $newHash,
                    'id'       => $userId
                ]);

                setFlash('success', 'Your password has been changed successfully.');
                redirect('auth/change-password.php');
            }
        } catch (Exception $e) {
            error_log('Change Password Error: ' . $e->getMessage());
            $error = 'An error occurred while updating your password. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-6 col-md-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-semibold text-dark"><i class="bi bi-shield-lock me-2 text-primary"></i> Change Account Password</h6>
      </div>
      <div class="card-body p-4">
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger d-flex align-items-center mb-3 py-2 px-3 small" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-6"></i>
            <div><?= e($error); ?></div>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL; ?>auth/change-password.php" novalidate>
          <?= csrfField(); ?>

          <div class="mb-3">
            <label for="current_password" class="form-label small fw-semibold text-dark">Current Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-light text-muted"><i class="bi bi-key"></i></span>
              <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Enter current password" required autofocus>
            </div>
          </div>

          <div class="mb-3">
            <label for="new_password" class="form-label small fw-semibold text-dark">New Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
              <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Minimum 6 characters" required>
            </div>
            <div class="form-text small">Use a strong combination of letters, numbers, and symbols.</div>
          </div>

          <div class="mb-4">
            <label for="confirm_password" class="form-label small fw-semibold text-dark">Confirm New Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-light text-muted"><i class="bi bi-check2-square"></i></span>
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter new password" required>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <a href="<?= BASE_URL; ?>index.php" class="btn btn-outline-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-shield-check me-1"></i> Update Password
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
