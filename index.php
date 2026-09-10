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

// Product & Inventory Statistics (Phase 4)
try {
    $prodStats = $pdo->query("
        SELECT 
            COUNT(*) AS total_products,
            SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
            SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END) AS low_stock
        FROM products 
        WHERE status = 'active'
    ")->fetch();
    $totalProducts = (int)($prodStats['total_products'] ?? 0);
    $outOfStockCount = (int)($prodStats['out_of_stock'] ?? 0);
    $lowStockCount = (int)($prodStats['low_stock'] ?? 0);
} catch (Exception $e) {
    $totalProducts = 0;
    $outOfStockCount = 0;
    $lowStockCount = 0;
}

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

// Fetch Recent or Low-Stock Products
$recentProdStmt = $pdo->query("
    SELECT p.id, p.product_code, p.name, p.brand, p.selling_price, p.stock_quantity, p.low_stock_threshold, p.status,
           c.name AS category_name, c.type AS category_type
    FROM products p
    JOIN categories c ON p.category_id = c.id
    ORDER BY p.id DESC
    LIMIT 5
");
$recentProducts = $recentProdStmt->fetchAll();

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
        <?= APP_NAME; ?> &bull; Phase 4 Product Catalog &amp; Inventory Active &bull;
        Last Session: <?= !empty($user['last_login_at']) ? date('M d, Y h:i A', strtotime($user['last_login_at'])) : 'Active Now'; ?>
      </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= BASE_URL; ?>modules/customers/create.php" class="btn btn-outline-light btn-sm px-3">
        <i class="bi bi-person-plus me-1"></i> New Customer
      </a>
      <?php if ($canManageRx): ?>
        <a href="<?= BASE_URL; ?>modules/prescriptions/create.php" class="btn btn-outline-info btn-sm px-3 text-white">
          <i class="bi bi-file-earmark-plus me-1"></i> New Rx
        </a>
      <?php endif; ?>
      <a href="<?= BASE_URL; ?>modules/products/create.php" class="btn btn-primary btn-sm px-3">
        <i class="bi bi-box-seam me-1"></i> Add Product
      </a>
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

  <!-- Total Catalog Products -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-success d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Catalog Products</div>
        <h3 class="fw-bold text-dark m-0 mt-1"><?= $totalProducts; ?></h3>
        <small class="<?= ($outOfStockCount > 0 || $lowStockCount > 0) ? 'text-warning fw-bold' : 'text-success'; ?>" style="font-size: 0.75rem;">
          <?= $lowStockCount; ?> low &bull; <?= $outOfStockCount; ?> out of stock
        </small>
      </div>
      <div class="stat-icon icon-success">
        <i class="bi bi-box-seam-fill"></i>
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

<!-- Product Catalog & Inventory Overview Widget -->
<div class="card shadow-sm mb-4 border-0">
  <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
    <h6 class="m-0 fw-semibold text-dark">
      <i class="bi bi-box-seam-fill me-2 text-primary"></i> Optical Product Catalog &amp; Inventory
    </h6>
    <div class="d-flex gap-2">
      <?php if (hasRole('admin')): ?>
        <a href="<?= BASE_URL; ?>modules/products/categories.php" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-tags me-1"></i> Categories
        </a>
      <?php endif; ?>
      <a href="<?= BASE_URL; ?>modules/products/index.php" class="btn btn-sm btn-outline-primary">
        Manage Catalog
      </a>
    </div>
  </div>
  <div class="table-responsive">
    <?php if (empty($recentProducts)): ?>
      <div class="p-4 text-center text-muted small">
        No optical products added yet. <a href="<?= BASE_URL; ?>modules/products/create.php">Add first product</a>.
      </div>
    <?php else: ?>
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-3">Code</th>
            <th>Product Name</th>
            <th>Type / Category</th>
            <th class="text-end">Retail Price</th>
            <th class="text-center">Stock Level</th>
            <th class="text-center">Status</th>
            <th class="text-end pe-3">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentProducts as $prod): ?>
            <tr>
              <td class="ps-3">
                <a href="<?= BASE_URL; ?>modules/products/view.php?id=<?= $prod['id']; ?>" class="font-monospace fw-bold text-primary text-decoration-none">
                  <?= e($prod['product_code']); ?>
                </a>
              </td>
              <td>
                <div class="fw-semibold text-dark"><?= e($prod['name']); ?></div>
                <small class="text-muted"><?= !empty($prod['brand']) ? e($prod['brand']) : ''; ?></small>
              </td>
              <td>
                <span class="badge <?= $prod['category_type'] === 'frame' ? 'bg-primary' : ($prod['category_type'] === 'lens' ? 'bg-info text-dark' : 'bg-dark'); ?> mb-1">
                  <?= ucfirst(e($prod['category_type'])); ?>
                </span>
                <div class="small text-secondary"><?= e($prod['category_name']); ?></div>
              </td>
              <td class="text-end font-monospace fw-bold text-dark">
                <?= formatMoney($prod['selling_price']); ?>
              </td>
              <td class="text-center">
                <span class="font-monospace fw-bold me-1"><?= (int)$prod['stock_quantity']; ?></span>
                <?= getStockBadge((int)$prod['stock_quantity'], (int)$prod['low_stock_threshold'], $prod['status']); ?>
              </td>
              <td class="text-center">
                <span class="badge badge-status-<?= $prod['status'] === 'active' ? 'active' : 'inactive'; ?>">
                  <?= ucfirst($prod['status']); ?>
                </span>
              </td>
              <td class="text-end pe-3">
                <a href="<?= BASE_URL; ?>modules/products/view.php?id=<?= $prod['id']; ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Product">
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
