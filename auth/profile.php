<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * User Profile View & Edit Page
 */

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';

// Fetch fresh user data from DB
$pdo = getDbConnection();
$stmt = $pdo->prepare("
    SELECT u.*, r.display_name AS role_display_name, r.name AS role_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE u.id = :id
    LIMIT 1
");
$stmt->execute(['id' => currentUserId()]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'User record not found.');
    redirect('index.php');
}

$avatarUrl = getAvatarUrl($user['avatar'] ?? null, $user['name']);
?>

<div class="row g-4">
  <!-- Profile Summary Card -->
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center p-4">
        <div class="position-relative d-inline-block mb-3">
          <img src="<?= e($avatarUrl); ?>" alt="<?= e($user['name']); ?>" id="avatar-preview" class="user-avatar-lg shadow-sm">
        </div>
        
        <h5 class="fw-bold text-dark mb-1"><?= e($user['name']); ?></h5>
        <p class="text-muted small mb-2">@<?= e($user['username']); ?></p>
        
        <div class="mb-3">
          <span class="badge badge-role <?= $user['role_name'] === 'admin' ? 'badge-role-admin' : ($user['role_name'] === 'optician' ? 'badge-role-optician' : 'badge-role-sales'); ?>">
            <?= e($user['role_display_name']); ?>
          </span>
          <span class="badge badge-status-active ms-1">
            <?= ucfirst(e($user['status'])); ?>
          </span>
        </div>

        <hr class="my-3 text-muted">

        <div class="text-start small">
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted"><i class="bi bi-envelope me-1"></i> Email:</span>
            <span class="fw-medium text-dark"><?= e($user['email']); ?></span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted"><i class="bi bi-telephone me-1"></i> Phone:</span>
            <span class="fw-medium text-dark"><?= e($user['phone'] ?: 'Not specified'); ?></span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted"><i class="bi bi-clock-history me-1"></i> Last Login:</span>
            <span class="fw-medium text-dark"><?= $user['last_login_at'] ? date('M d, Y h:i A', strtotime($user['last_login_at'])) : 'First session'; ?></span>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span class="text-muted"><i class="bi bi-calendar-check me-1"></i> Joined:</span>
            <span class="fw-medium text-dark"><?= date('M d, Y', strtotime($user['created_at'])); ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Profile Edit Form -->
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-semibold text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i> Edit Profile Information</h6>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="<?= BASE_URL; ?>auth/update-profile.php" enctype="multipart/form-data">
          <?= csrfField(); ?>

          <div class="row g-3">
            <div class="col-md-6">
              <label for="name" class="form-label small fw-semibold text-dark">Full Name <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control" id="name" name="name" value="<?= e($user['name']); ?>" required>
              </div>
            </div>

            <div class="col-md-6">
              <label for="username" class="form-label small fw-semibold text-dark">Username</label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-at"></i></span>
                <input type="text" class="form-control bg-light" id="username" value="<?= e($user['username']); ?>" disabled readonly>
              </div>
              <small class="text-muted" style="font-size: 0.75rem;">Username cannot be changed.</small>
            </div>

            <div class="col-md-6">
              <label for="email" class="form-label small fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email" value="<?= e($user['email']); ?>" required>
              </div>
            </div>

            <div class="col-md-6">
              <label for="phone" class="form-label small fw-semibold text-dark">Phone Number</label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone"></i></span>
                <input type="text" class="form-control" id="phone" name="phone" value="<?= e($user['phone'] ?? ''); ?>" placeholder="+1 (555) 000-0000">
              </div>
            </div>

            <div class="col-12">
              <label for="avatar-input" class="form-label small fw-semibold text-dark">Profile Avatar</label>
              <input type="file" class="form-control" id="avatar-input" name="avatar" accept="image/jpeg,image/png,image/webp">
              <div class="form-text small">Accepted formats: JPG, PNG, WebP (Max file size: 2MB). Choosing a new file will update the preview instantly.</div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <a href="<?= BASE_URL; ?>index.php" class="btn btn-outline-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-check2-circle me-1"></i> Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
