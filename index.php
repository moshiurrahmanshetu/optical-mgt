<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Admin Panel Shell & Dashboard
 */

$pageTitle = 'Dashboard Overview';
require_once __DIR__ . '/includes/header.php';

// Fetch live database statistics
$pdo = getDbConnection();

// Customer Statistics
$totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$newCustomersThisMonth = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')")->fetchColumn();
$activeCustomers = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn();

// Prescription Statistics
$totalPrescriptions = (int) $pdo->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn();
$newPrescriptionsThisMonth = (int) $pdo->query("SELECT COUNT(*) FROM prescriptions WHERE prescription_date >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();

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

// Fetch Recent Customers
$recentCustStmt = $pdo->query("
    SELECT id, customer_code, full_name, phone, email, status, created_at 
    FROM customers 
    ORDER BY id DESC 
    LIMIT 5
");
$recentCustomers = $recentCustStmt->fetchAll();

// Fetch Recent Prescriptions
$recentRxStmt = $pdo->query("
    SELECT p.id, p.prescription_date, p.right_sph, p.left_sph, p.doctor_name,
           c.id AS customer_id, c.customer_code, c.full_name AS customer_name
    FROM prescriptions p
    JOIN customers c ON p.customer_id = c.id
    ORDER BY p.prescription_date DESC, p.id DESC
    LIMIT 5
");
$recentPrescriptions = $recentRxStmt->fetchAll();

$user = currentUser();
$canManageRx = hasRole(['admin', 'optician']);
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
        <?= APP_NAME; ?> &bull; Phase 3 Prescription Module Active &bull;
        Last Session: <?= !empty($user['last_login_at']) ? date('M d, Y h:i A', strtotime($user['last_login_at'])) : 'Active Now'; ?>
      </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= BASE_URL; ?>modules/customers/create.php" class="btn btn-outline-light btn-sm px-3">
        <i class="bi bi-person-plus me-1"></i> New Customer
      </a>
      <?php if ($canManageRx): ?>
        <a href="<?= BASE_URL; ?>modules/prescriptions/create.php" class="btn btn-primary btn-sm px-3">
          <i class="bi bi-file-earmark-plus me-1"></i> New Rx
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Real Database KPI Metrics Cards -->
<div class="row g-3 mb-4">
  <!-- Total Customers -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-primary d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Total Customers</div>
        <h3 class="fw-bold text-dark m-0 mt-1"><?= $totalCustomers; ?></h3>
        <small class="text-success fw-semibold" style="font-size: 0.75rem;">
          <i class="bi bi-arrow-up-short"></i> <?= $newCustomersThisMonth; ?> this month
        </small>
      </div>
      <div class="stat-icon icon-primary">
        <i class="bi bi-people-fill"></i>
      </div>
    </div>
  </div>

  <!-- Total Prescriptions -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-info d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Prescriptions (Rx)</div>
        <h3 class="fw-bold text-dark m-0 mt-1"><?= $totalPrescriptions; ?></h3>
        <small class="text-info fw-semibold" style="font-size: 0.75rem;">
          <i class="bi bi-check2"></i> <?= $newPrescriptionsThisMonth; ?> this month
        </small>
      </div>
      <div class="stat-icon icon-info">
        <i class="bi bi-file-earmark-medical-fill"></i>
      </div>
    </div>
  </div>

  <!-- Active Customers -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-success d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Active Customers</div>
        <h3 class="fw-bold text-dark m-0 mt-1"><?= $activeCustomers; ?></h3>
        <small class="text-muted" style="font-size: 0.75rem;">Dispensing ready</small>
      </div>
      <div class="stat-icon icon-success">
        <i class="bi bi-person-check-fill"></i>
      </div>
    </div>
  </div>

  <!-- System Staff Accounts -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-warning d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">System Staff</div>
        <h3 class="fw-bold text-dark m-0 mt-1"><?= $totalUsers; ?></h3>
        <small class="text-muted" style="font-size: 0.75rem;"><?= $opticianCount; ?> Optician &bull; <?= $adminCount; ?> Admin</small>
      </div>
      <div class="stat-icon icon-warning">
        <i class="bi bi-shield-person"></i>
      </div>
    </div>
  </div>
</div>

<!-- Main Row: Recent Prescriptions + Recent Customers -->
<div class="row g-4 mb-4">
  <!-- Recent Prescriptions Widget -->
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-file-earmark-medical-fill me-2 text-primary"></i> Recent Prescriptions (Rx)
        </h6>
        <a href="<?= BASE_URL; ?>modules/prescriptions/index.php" class="btn btn-sm btn-outline-primary">
          View All Rx
        </a>
      </div>
      <div class="table-responsive">
        <?php if (empty($recentPrescriptions)): ?>
          <div class="p-4 text-center text-muted small">
            No prescriptions recorded yet. <a href="<?= BASE_URL; ?>modules/prescriptions/create.php">Create first Rx</a>.
          </div>
        <?php else: ?>
          <table class="table table-custom table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Rx #</th>
                <th>Customer</th>
                <th>Date</th>
                <th>OD SPH</th>
                <th>OS SPH</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentPrescriptions as $rx): ?>
                <tr>
                  <td>
                    <span class="badge bg-light text-dark border font-monospace">#<?= $rx['id']; ?></span>
                  </td>
                  <td>
                    <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $rx['customer_id']; ?>" class="fw-semibold text-dark text-decoration-none">
                      <?= e($rx['customer_name']); ?>
                    </a>
                  </td>
                  <td class="text-muted small"><?= date('M d', strtotime($rx['prescription_date'])); ?></td>
                  <td class="small font-monospace"><?= formatOpticalPower($rx['right_sph']); ?></td>
                  <td class="small font-monospace"><?= formatOpticalPower($rx['left_sph']); ?></td>
                  <td class="text-end">
                    <a href="<?= BASE_URL; ?>modules/prescriptions/view.php?id=<?= $rx['id']; ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Rx">
                      <i class="bi bi-eye"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Customers Widget -->
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-people-fill me-2 text-primary"></i> Recent Optical Customers
        </h6>
        <a href="<?= BASE_URL; ?>modules/customers/index.php" class="btn btn-sm btn-outline-primary">
          View All Customers
        </a>
      </div>
      <div class="table-responsive">
        <?php if (empty($recentCustomers)): ?>
          <div class="p-4 text-center text-muted small">
            No customers registered yet. <a href="<?= BASE_URL; ?>modules/customers/create.php">Add a customer</a>.
          </div>
        <?php else: ?>
          <table class="table table-custom table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentCustomers as $rc): ?>
                <tr>
                  <td>
                    <span class="badge bg-light text-dark border font-monospace">
                      <?= e($rc['customer_code']); ?>
                    </span>
                  </td>
                  <td>
                    <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $rc['id']; ?>" class="fw-semibold text-dark text-decoration-none">
                      <?= e($rc['full_name']); ?>
                    </a>
                  </td>
                  <td class="text-dark small"><?= e($rc['phone']); ?></td>
                  <td>
                    <span class="badge <?= $rc['status'] === 'active' ? 'badge-status-active' : 'badge-status-inactive'; ?>">
                      <?= ucfirst(e($rc['status'])); ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $rc['id']; ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Customer">
                      <i class="bi bi-eye"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Phase 4 Roadmap Summary -->
<div class="card shadow-sm border-0 bg-light">
  <div class="card-body p-4">
    <div class="d-flex align-items-center gap-2 mb-2">
      <i class="bi bi-compass text-primary fs-5"></i>
      <h6 class="m-0 fw-bold text-dark">Optical Management System &mdash; Phase 3 Completed</h6>
    </div>
    <p class="text-muted small mb-3">
      The Customer Management and Prescription Management modules are fully operational with complete OD/OS refraction history, multiple customer prescriptions, search, and role-based permissions.
    </p>
    <div class="row g-2">
      <div class="col-md-6">
        <div class="p-3 bg-white rounded border d-flex align-items-center gap-2">
          <i class="bi bi-cart-check text-muted fs-5"></i>
          <div>
            <div class="fw-semibold small text-dark">Orders & Dispensing Module</div>
            <small class="text-muted" style="font-size: 0.72rem;">Scheduled for Phase 4 (Frames, Lenses, Invoicing)</small>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="p-3 bg-white rounded border d-flex align-items-center gap-2">
          <i class="bi bi-bar-chart-line text-muted fs-5"></i>
          <div>
            <div class="fw-semibold small text-dark">Reports & Analytics Module</div>
            <small class="text-muted" style="font-size: 0.72rem;">Scheduled for Phase 5</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
