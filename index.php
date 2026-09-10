<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Admin Panel Shell & Dashboard
 */

$pageTitle = 'Dashboard Overview';
require_once __DIR__ . '/includes/header.php';

// Fetch live database statistics
$pdo = getDbConnection();

// Total Users Count
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Role Counts
$adminCount = (int) $pdo->query("
    SELECT COUNT(*) 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name = 'admin'
")->fetchColumn();

$opticianCount = (int) $pdo->query("
    SELECT COUNT(*) 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name = 'optician'
")->fetchColumn();

$salesCount = (int) $pdo->query("
    SELECT COUNT(*) 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name = 'sales_staff'
")->fetchColumn();

// Fetch System Users List
$usersStmt = $pdo->query("
    SELECT u.id, u.name, u.username, u.email, u.phone, u.avatar, u.status, u.last_login_at, u.created_at,
           r.name AS role_name, r.display_name AS role_display_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    ORDER BY u.id ASC
");
$allUsers = $usersStmt->fetchAll();

$user = currentUser();
?>

<!-- Welcome Banner (Solid Slate) -->
<div class="card mb-4 border-0 shadow-sm" style="background-color: #0f172a; color: #ffffff;">
  <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
      <div class="badge badge-role <?= ($user['role_name'] ?? '') === 'admin' ? 'badge-role-admin' : (($user['role_name'] ?? '') === 'optician' ? 'badge-role-optician' : 'badge-role-sales'); ?> mb-2" style="border: 1px solid #334155;">
        <?= e($user['role_display_name'] ?? 'Staff'); ?>
      </div>
      <h4 class="fw-bold m-0 text-white">Welcome, <?= e($user['name'] ?? 'User'); ?></h4>
      <p class="text-secondary small m-0 mt-1">
        <?= APP_NAME; ?> &bull; Phase 1 Foundation Active &bull;
        Last Session: <?= !empty($user['last_login_at']) ? date('M d, Y h:i A', strtotime($user['last_login_at'])) : 'Active Now'; ?>
      </p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL; ?>auth/profile.php" class="btn btn-outline-light btn-sm px-3">
        <i class="bi bi-person me-1"></i> Edit Profile
      </a>
      <a href="<?= BASE_URL; ?>auth/change-password.php" class="btn btn-primary btn-sm px-3">
        <i class="bi bi-key me-1"></i> Security
      </a>
    </div>
  </div>
</div>

<!-- Real DB KPI Metrics Cards -->
<div class="row g-3 mb-4">
  <!-- Total Users -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-primary d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Total Users</div>
        <h3 class="fw-bold text-dark m-0 mt-1"><?= $totalUsers; ?></h3>
        <small class="text-muted" style="font-size: 0.75rem;">Registered accounts</small>
      </div>
      <div class="stat-icon icon-primary">
        <i class="bi bi-people-fill"></i>
      </div>
    </div>
  </div>

  <!-- Administrators -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-success d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Administrators</div>
        <h3 class="fw-bold text-dark m-0 mt-1"><?= $adminCount; ?></h3>
        <small class="text-muted" style="font-size: 0.75rem;">System managers</small>
      </div>
      <div class="stat-icon icon-success">
        <i class="bi bi-shield-lock-fill"></i>
      </div>
    </div>
  </div>

  <!-- Opticians -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-info d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Opticians</div>
        <h3 class="fw-bold text-dark m-0 mt-1"><?= $opticianCount; ?></h3>
        <small class="text-muted" style="font-size: 0.75rem;">Clinical practitioners</small>
      </div>
      <div class="stat-icon icon-info">
        <i class="bi bi-eyeglasses"></i>
      </div>
    </div>
  </div>

  <!-- Sales Staff -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-warning d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Sales Staff</div>
        <h3 class="fw-bold text-dark m-0 mt-1"><?= $salesCount; ?></h3>
        <small class="text-muted" style="font-size: 0.75rem;">Front desk & billing</small>
      </div>
      <div class="stat-icon icon-warning">
        <i class="bi bi-cart-check-fill"></i>
      </div>
    </div>
  </div>
</div>

<!-- System User Accounts Table -->
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-person-lines-fill me-2 text-primary"></i> System Users & Roles
        </h6>
        <span class="badge bg-light text-dark border"><?= count($allUsers); ?> Records</span>
      </div>
      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width: 60px;">#</th>
              <th>User</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Last Login</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allUsers as $u): ?>
              <?php $uAvatar = getAvatarUrl($u['avatar'], $u['name']); ?>
              <tr>
                <td class="text-muted fw-medium"><?= e($u['id']); ?></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <img src="<?= e($uAvatar); ?>" alt="<?= e($u['name']); ?>" class="user-avatar-img">
                    <div>
                      <div class="fw-semibold text-dark"><?= e($u['name']); ?></div>
                      <small class="text-muted">@<?= e($u['username']); ?></small>
                    </div>
                  </div>
                </td>
                <td><?= e($u['email']); ?></td>
                <td>
                  <span class="badge badge-role <?= $u['role_name'] === 'admin' ? 'badge-role-admin' : ($u['role_name'] === 'optician' ? 'badge-role-optician' : 'badge-role-sales'); ?>">
                    <?= e($u['role_display_name']); ?>
                  </span>
                </td>
                <td>
                  <span class="badge <?= $u['status'] === 'active' ? 'badge-status-active' : 'badge-status-inactive'; ?>">
                    <?= ucfirst(e($u['status'])); ?>
                  </span>
                </td>
                <td class="text-muted small">
                  <?= !empty($u['last_login_at']) ? date('M d, Y h:i A', strtotime($u['last_login_at'])) : '<span class="text-secondary">&mdash;</span>'; ?>
                </td>
                <td class="text-muted small">
                  <?= date('M d, Y', strtotime($u['created_at'])); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Phase 2 Module Roadmap Container -->
<div class="card shadow-sm border-0 bg-light">
  <div class="card-body p-4">
    <div class="d-flex align-items-center gap-2 mb-2">
      <i class="bi bi-compass text-primary fs-5"></i>
      <h6 class="m-0 fw-bold text-dark">Optical Management Architecture &mdash; Phase 1 Active</h6>
    </div>
    <p class="text-muted small mb-3">
      The core project foundation, secure PDO authentication, RBAC authorization, responsive collapsible layout, profile management, and avatar processing are ready. Business modules will integrate seamlessly into <code>modules/</code> in subsequent phases.
    </p>
    <div class="row g-2">
      <div class="col-md-4 col-sm-6">
        <div class="p-3 bg-white rounded border d-flex align-items-center gap-2">
          <i class="bi bi-people text-muted fs-5"></i>
          <div>
            <div class="fw-semibold small text-dark">Customers Module</div>
            <small class="text-muted" style="font-size: 0.72rem;">Scheduled for Phase 2</small>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-sm-6">
        <div class="p-3 bg-white rounded border d-flex align-items-center gap-2">
          <i class="bi bi-file-earmark-medical text-muted fs-5"></i>
          <div>
            <div class="fw-semibold small text-dark">Prescriptions (Rx)</div>
            <small class="text-muted" style="font-size: 0.72rem;">Scheduled for Phase 2</small>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-sm-6">
        <div class="p-3 bg-white rounded border d-flex align-items-center gap-2">
          <i class="bi bi-box-seam text-muted fs-5"></i>
          <div>
            <div class="fw-semibold small text-dark">Frames & Lens Inventory</div>
            <small class="text-muted" style="font-size: 0.72rem;">Scheduled for Phase 2</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
