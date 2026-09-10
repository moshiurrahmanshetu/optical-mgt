<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Top Navbar Include
 */

$navUser = currentUser();
$userAvatarUrl = getAvatarUrl($navUser['avatar'] ?? null, $navUser['name'] ?? 'User');
?>
<header class="app-navbar">
  <div class="d-flex align-items-center gap-3">
    <!-- Sidebar Toggle Button -->
    <button type="button" class="navbar-toggle-btn" id="sidebarToggle" title="Toggle Sidebar">
      <i class="bi bi-list"></i>
    </button>
    
    <!-- Page Title / Breadcrumb -->
    <div>
      <h5 class="m-0 fw-semibold text-dark fs-6"><?= e($pageTitle ?? 'Dashboard'); ?></h5>
    </div>
  </div>

  <!-- Right Actions & User Profile Dropdown -->
  <div class="d-flex align-items-center gap-3">
    <!-- Role Badge -->
    <span class="badge badge-role <?= ($navUser['role_name'] ?? '') === 'admin' ? 'badge-role-admin' : (($navUser['role_name'] ?? '') === 'optician' ? 'badge-role-optician' : 'badge-role-sales'); ?> d-none d-sm-inline-block">
      <?= e($navUser['role_display_name'] ?? 'Staff'); ?>
    </span>

    <!-- User Dropdown Menu -->
    <div class="dropdown">
      <a href="#" class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle text-dark" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <img src="<?= e($userAvatarUrl); ?>" alt="<?= e($navUser['name'] ?? 'User'); ?>" class="user-avatar-img">
        <span class="d-none d-md-inline fw-medium fs-7"><?= e($navUser['name'] ?? 'Account'); ?></span>
      </a>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2" aria-labelledby="userMenuDropdown">
        <li class="px-3 py-2 border-bottom">
          <div class="fw-semibold text-dark"><?= e($navUser['name'] ?? ''); ?></div>
          <small class="text-muted"><?= e($navUser['email'] ?? ''); ?></small>
        </li>
        <li>
          <a class="dropdown-item py-2" href="<?= BASE_URL; ?>auth/profile.php">
            <i class="bi bi-person me-2 text-primary"></i> My Profile
          </a>
        </li>
        <li>
          <a class="dropdown-item py-2" href="<?= BASE_URL; ?>auth/change-password.php">
            <i class="bi bi-key me-2 text-secondary"></i> Change Password
          </a>
        </li>
        <li><hr class="dropdown-divider my-1"></li>
        <li>
          <a class="dropdown-item py-2 text-danger" href="<?= BASE_URL; ?>auth/logout.php">
            <i class="bi bi-box-arrow-right me-2"></i> Log Out
          </a>
        </li>
      </ul>
    </div>
  </div>
</header>
