<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Collapsible Sidebar Navigation Include
 */

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$currentDir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Determine active items
$isDashboard     = ($currentScript === 'index.php' && $currentDir !== 'auth' && $currentDir !== 'customers' && $currentDir !== 'prescriptions');
$isCustomers     = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/modules/customers/') !== false);
$isPrescriptions = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/modules/prescriptions/') !== false);
$isProfile       = ($currentScript === 'profile.php' || $currentScript === 'update-profile.php');
$isPassword      = ($currentScript === 'change-password.php');
?>
<aside class="app-sidebar" id="appSidebar">
  <!-- Brand Header -->
  <div class="sidebar-header">
    <a href="<?= BASE_URL; ?>" class="sidebar-brand">
      <div class="sidebar-brand-icon">
        <i class="bi bi-eyeglasses"></i>
      </div>
      <div class="sidebar-brand-text">
        <span class="d-block fw-bold text-white lh-1">VisionCare</span>
        <small class="text-secondary" style="font-size: 0.72rem; font-weight: 500;">Optical CMS</small>
      </div>
    </a>
  </div>

  <!-- Navigation Content -->
  <div class="sidebar-content">
    <!-- Main Group -->
    <div class="sidebar-heading">Main</div>
    <ul class="sidebar-nav">
      <li class="nav-item">
        <a href="<?= BASE_URL; ?>index.php" class="nav-link <?= $isDashboard ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
          <i class="bi bi-grid-1x2-fill"></i>
          <span class="nav-link-text">Dashboard</span>
        </a>
      </li>
    </ul>

    <!-- Modules Group -->
    <div class="sidebar-heading mt-2">Optical Operations</div>
    <ul class="sidebar-nav">
      <li class="nav-item">
        <a href="<?= BASE_URL; ?>modules/customers/index.php" class="nav-link <?= $isCustomers ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Customers">
          <i class="bi bi-people"></i>
          <span class="nav-link-text">Customers</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= BASE_URL; ?>modules/prescriptions/index.php" class="nav-link <?= $isPrescriptions ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Prescriptions">
          <i class="bi bi-file-earmark-medical"></i>
          <span class="nav-link-text">Prescriptions</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="javascript:void(0);" class="nav-link disabled-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Products & Inventory (Phase 2)">
          <i class="bi bi-box-seam"></i>
          <span class="nav-link-text">Products</span>
          <span class="badge-soon">Soon</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="javascript:void(0);" class="nav-link disabled-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Orders & Billing (Phase 2)">
          <i class="bi bi-cart-check"></i>
          <span class="nav-link-text">Orders</span>
          <span class="badge-soon">Soon</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="javascript:void(0);" class="nav-link disabled-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payments & Reports (Phase 2)">
          <i class="bi bi-bar-chart-line"></i>
          <span class="nav-link-text">Reports</span>
          <span class="badge-soon">Soon</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="javascript:void(0);" class="nav-link disabled-link" data-bs-toggle="tooltip" data-bs-placement="right" title="User Management (Phase 2)">
          <i class="bi bi-shield-person"></i>
          <span class="nav-link-text">Users</span>
          <span class="badge-soon">Soon</span>
        </a>
      </li>
    </ul>

    <!-- Account Group -->
    <div class="sidebar-heading mt-2">Account</div>
    <ul class="sidebar-nav">
      <li class="nav-item">
        <a href="<?= BASE_URL; ?>auth/profile.php" class="nav-link <?= $isProfile ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="My Profile">
          <i class="bi bi-person-circle"></i>
          <span class="nav-link-text">My Profile</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= BASE_URL; ?>auth/change-password.php" class="nav-link <?= $isPassword ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Change Password">
          <i class="bi bi-key"></i>
          <span class="nav-link-text">Change Password</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= BASE_URL; ?>auth/logout.php" class="nav-link text-danger" data-bs-toggle="tooltip" data-bs-placement="right" title="Log Out">
          <i class="bi bi-box-arrow-right"></i>
          <span class="nav-link-text">Log Out</span>
        </a>
      </li>
    </ul>
  </div>

  <!-- Sidebar Footer -->
  <div class="sidebar-footer text-center">
    <small class="sidebar-footer-text text-secondary" style="font-size: 0.75rem;">
      &copy; <?= date('Y'); ?> <?= e(APP_SHORT_NAME); ?> v<?= e(APP_VERSION); ?>
    </small>
  </div>
</aside>
