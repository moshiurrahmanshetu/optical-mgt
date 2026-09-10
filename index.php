<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Admin Panel Shell & Comprehensive Analytics Dashboard
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

// Product & Inventory Statistics
try {
    $prodStats = $pdo->query("
        SELECT 
            COUNT(*) AS total_products,
            SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
            SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END) AS low_stock,
            SUM(stock_quantity * purchase_price) AS cost_valuation,
            SUM(stock_quantity * selling_price) AS retail_valuation
        FROM products 
        WHERE status = 'active'
    ")->fetch();
    $totalProducts   = (int)($prodStats['total_products'] ?? 0);
    $outOfStockCount = (int)($prodStats['out_of_stock'] ?? 0);
    $lowStockCount   = (int)($prodStats['low_stock'] ?? 0);
    $costValuation   = (float)($prodStats['cost_valuation'] ?? 0.0);
    $retailValuation = (float)($prodStats['retail_valuation'] ?? 0.0);
} catch (Exception $e) {
    $totalProducts = 0;
    $outOfStockCount = 0;
    $lowStockCount = 0;
    $costValuation = 0.0;
    $retailValuation = 0.0;
}

// Order & Financial Statistics
try {
    $orderStats = $pdo->query("
        SELECT 
            COUNT(*) AS total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN status IN ('confirmed', 'processing', 'ready') THEN 1 ELSE 0 END) AS active_orders,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders,
            SUM(CASE WHEN status != 'cancelled' THEN grand_total ELSE 0 END) AS total_sales,
            SUM(CASE WHEN status != 'cancelled' THEN paid_amount ELSE 0 END) AS total_collected,
            SUM(CASE WHEN status != 'cancelled' THEN due_amount ELSE 0 END) AS total_due
        FROM orders
    ")->fetch();
    $totalOrders     = (int)($orderStats['total_orders'] ?? 0);
    $pendingOrders   = (int)($orderStats['pending_orders'] ?? 0);
    $activeOrders    = (int)($orderStats['active_orders'] ?? 0);
    $deliveredOrders = (int)($orderStats['delivered_orders'] ?? 0);
    $totalSales      = (float)($orderStats['total_sales'] ?? 0.0);
    $totalCollected  = (float)($orderStats['total_collected'] ?? 0.0);
    $totalDue        = (float)($orderStats['total_due'] ?? 0.0);
} catch (Exception $e) {
    $totalOrders = $pendingOrders = $activeOrders = $deliveredOrders = 0;
    $totalSales = $totalCollected = $totalDue = 0.0;
}

// Total Users Count
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// --- Live Chart.js Dataset Queries ---

// 1. Last 6 Months Revenue & Collections Trend
$monthLabels = [];
$monthlySales = [];
$monthlyPayments = [];

for ($i = 5; $i >= 0; $i--) {
    $mKey = date('Y-m', strtotime("-$i months"));
    $mLabel = date('M Y', strtotime("-$i months"));
    $monthLabels[] = $mLabel;

    // Monthly Net Sales (excluding cancelled)
    $salesStmt = $pdo->prepare("
        SELECT COALESCE(SUM(grand_total), 0) 
        FROM orders 
        WHERE status != 'cancelled' 
          AND DATE_FORMAT(order_date, '%Y-%m') = :m
    ");
    $salesStmt->execute([':m' => $mKey]);
    $monthlySales[] = (float) $salesStmt->fetchColumn();

    // Monthly Collections
    $payStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) 
        FROM payments 
        WHERE DATE_FORMAT(payment_date, '%Y-%m') = :m
    ");
    $payStmt->execute([':m' => $mKey]);
    $monthlyPayments[] = (float) $payStmt->fetchColumn();
}

// 2. Order Workflow Status Breakdown
$statusCounts = [
    'Pending'    => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'Confirmed'  => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'confirmed'")->fetchColumn(),
    'Processing' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn(),
    'Ready'      => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'ready'")->fetchColumn(),
    'Delivered'  => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn(),
    'Cancelled'  => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn(),
];

// 3. Category Stock Inventory Levels
$catStockStmt = $pdo->query("
    SELECT c.name, COALESCE(SUM(p.stock_quantity), 0) AS total_stock
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id, c.name
    ORDER BY total_stock DESC
    LIMIT 6
");
$catStockRows = $catStockStmt->fetchAll();
$catLabels = [];
$catQuantities = [];
foreach ($catStockRows as $cs) {
    $catLabels[] = $cs['name'];
    $catQuantities[] = (int) $cs['total_stock'];
}

// Fetch Recent Orders
$recentOrdersStmt = $pdo->query("
    SELECT o.*, c.full_name AS customer_name, c.customer_code, c.phone AS customer_phone
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    ORDER BY o.id DESC
    LIMIT 5
");
$recentOrders = $recentOrdersStmt->fetchAll();

// Fetch Recent Payments
$recentPaymentsStmt = $pdo->query("
    SELECT p.*, o.order_code, c.full_name AS customer_name, u.name AS received_by_name
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON p.received_by = u.id
    ORDER BY p.id DESC
    LIMIT 5
");
$recentPayments = $recentPaymentsStmt->fetchAll();

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

// Fetch Recent Customers
$recentCustStmt = $pdo->query("
    SELECT id, customer_code, full_name, phone, email, status, created_at 
    FROM customers 
    ORDER BY id DESC 
    LIMIT 5
");
$recentCustomers = $recentCustStmt->fetchAll();

$user = currentUser();
$canManageRx = hasRole(['admin', 'optician']);
?>

<!-- Include Chart.js (v4.4.1) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Welcome Banner (Solid Slate) -->
<div class="card mb-4 border-0 shadow-sm" style="background-color: #0f172a; color: #ffffff;">
  <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
      <div class="badge badge-role <?= ($user['role_name'] ?? '') === 'admin' ? 'badge-role-admin' : (($user['role_name'] ?? '') === 'optician' ? 'badge-role-optician' : 'badge-role-sales'); ?> mb-2" style="border: 1px solid #334155;">
        <?= e($user['role_display_name'] ?? 'Staff'); ?>
      </div>
      <h4 class="fw-bold m-0 text-white">Welcome, <?= e($user['name'] ?? 'User'); ?></h4>
      <p class="text-secondary small m-0 mt-1">
        <?= APP_NAME; ?> &bull; Complete Optical CMS &amp; Business Intelligence Suite &bull;
        Last Session: <?= !empty($user['last_login_at']) ? date('M d, Y h:i A', strtotime($user['last_login_at'])) : 'Active Now'; ?>
      </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= BASE_URL; ?>modules/reports/index.php" class="btn btn-outline-light btn-sm px-3">
        <i class="bi bi-graph-up me-1"></i> Analytics Hub
      </a>
      <a href="<?= BASE_URL; ?>modules/orders/create.php" class="btn btn-primary btn-sm px-3 shadow-sm">
        <i class="bi bi-cart-plus me-1"></i> New Order
      </a>
      <a href="<?= BASE_URL; ?>modules/customers/create.php" class="btn btn-outline-light btn-sm px-3">
        <i class="bi bi-person-plus me-1"></i> New Customer
      </a>
      <?php if ($canManageRx): ?>
        <a href="<?= BASE_URL; ?>modules/prescriptions/create.php" class="btn btn-outline-info btn-sm px-3 text-white">
          <i class="bi bi-file-earmark-plus me-1"></i> New Rx
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Primary Financial & Order KPI Row -->
<div class="row g-3 mb-4">
  <!-- Total Net Sales -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-primary d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Total Invoiced Sales</div>
        <h3 class="fw-bold text-dark m-0 mt-1 font-monospace"><?= formatMoney($totalSales); ?></h3>
        <small class="text-primary fw-semibold" style="font-size: 0.75rem;">
          <i class="bi bi-graph-up-arrow"></i> Active orders (non-cancelled)
        </small>
      </div>
      <div class="stat-icon icon-primary">
        <i class="bi bi-currency-dollar"></i>
      </div>
    </div>
  </div>

  <!-- Total Collections -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-success d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Total Collected</div>
        <h3 class="fw-bold text-dark m-0 mt-1 font-monospace text-success"><?= formatMoney($totalCollected); ?></h3>
        <small class="text-success fw-semibold" style="font-size: 0.75rem;">
          <i class="bi bi-cash-stack"></i> Realized cash flow
        </small>
      </div>
      <div class="stat-icon icon-success">
        <i class="bi bi-cash-coin"></i>
      </div>
    </div>
  </div>

  <!-- Outstanding Due Amount -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-warning d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Outstanding Due</div>
        <h3 class="fw-bold text-dark m-0 mt-1 font-monospace text-danger"><?= formatMoney($totalDue); ?></h3>
        <small class="<?= $totalDue > 0 ? 'text-danger fw-bold' : 'text-success'; ?>" style="font-size: 0.75rem;">
          <i class="bi bi-exclamation-circle"></i> <?= $totalDue > 0 ? 'Collectable patient balance' : 'All accounts settled'; ?>
        </small>
      </div>
      <div class="stat-icon icon-warning">
        <i class="bi bi-wallet2"></i>
      </div>
    </div>
  </div>

  <!-- Total Orders -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-info d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Total Orders</div>
        <h3 class="fw-bold text-dark m-0 mt-1"><?= $totalOrders; ?></h3>
        <small class="text-info fw-semibold" style="font-size: 0.75rem;">
          <?= $activeOrders; ?> active &bull; <?= $deliveredOrders; ?> delivered
        </small>
      </div>
      <div class="stat-icon icon-info">
        <i class="bi bi-receipt-cutoff"></i>
      </div>
    </div>
  </div>
</div>

<!-- Interactive Analytics Charts Row -->
<div class="row g-4 mb-4">
  <!-- Monthly Sales vs Payments Trend -->
  <div class="col-xl-8">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <div>
          <h6 class="m-0 fw-bold text-dark">
            <i class="bi bi-graph-up me-2 text-primary"></i> Revenue &amp; Collections Trend (Last 6 Months)
          </h6>
          <small class="text-muted">Comparing invoiced sales with actual cash received</small>
        </div>
        <a href="<?= BASE_URL; ?>modules/reports/sales.php" class="btn btn-sm btn-outline-primary">
          Detailed Report
        </a>
      </div>
      <div class="card-body p-3">
        <div style="height: 280px; position: relative;">
          <canvas id="salesTrendChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Order Status Donut Chart -->
  <div class="col-xl-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <div>
          <h6 class="m-0 fw-bold text-dark">
            <i class="bi bi-pie-chart-fill me-2 text-primary"></i> Order Lifecycle Distribution
          </h6>
          <small class="text-muted">Workflow stages</small>
        </div>
        <a href="<?= BASE_URL; ?>modules/reports/orders.php" class="btn btn-sm btn-outline-secondary">
          View All
        </a>
      </div>
      <div class="card-body p-3 d-flex flex-column align-items-center justify-content-center">
        <div style="height: 230px; width: 100%; position: relative;">
          <canvas id="orderStatusChart"></canvas>
        </div>
        <div class="d-flex flex-wrap justify-content-center gap-2 mt-2" style="font-size: 0.75rem;">
          <span class="badge bg-secondary">Pending: <?= $statusCounts['Pending'] ?></span>
          <span class="badge bg-info text-dark">Confirmed: <?= $statusCounts['Confirmed'] ?></span>
          <span class="badge bg-primary">Processing: <?= $statusCounts['Processing'] ?></span>
          <span class="badge bg-warning text-dark">Ready: <?= $statusCounts['Ready'] ?></span>
          <span class="badge bg-success">Delivered: <?= $statusCounts['Delivered'] ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Secondary Operational Metrics Row -->
<div class="row g-3 mb-4">
  <!-- Total Customers -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-primary d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Total Customers</div>
        <h4 class="fw-bold text-dark m-0 mt-1"><?= $totalCustomers; ?></h4>
        <small class="text-success fw-semibold" style="font-size: 0.75rem;">
          <i class="bi bi-arrow-up-short"></i> <?= $newCustomersThisMonth; ?> this month
        </small>
      </div>
      <div class="stat-icon icon-primary" style="width: 40px; height: 40px; font-size: 1.25rem;">
        <i class="bi bi-people-fill"></i>
      </div>
    </div>
  </div>

  <!-- Prescriptions (Rx) -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-info d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Prescriptions (Rx)</div>
        <h4 class="fw-bold text-dark m-0 mt-1"><?= $totalPrescriptions; ?></h4>
        <small class="text-info fw-semibold" style="font-size: 0.75rem;">
          <i class="bi bi-check2"></i> <?= $newPrescriptionsThisMonth; ?> this month
        </small>
      </div>
      <div class="stat-icon icon-info" style="width: 40px; height: 40px; font-size: 1.25rem;">
        <i class="bi bi-file-earmark-medical-fill"></i>
      </div>
    </div>
  </div>

  <!-- Inventory Valuation -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-success d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Stock Valuation (Cost)</div>
        <h4 class="fw-bold text-dark m-0 mt-1 font-monospace"><?= formatMoney($costValuation); ?></h4>
        <small class="text-muted" style="font-size: 0.75rem;">
          Retail: <?= formatMoney($retailValuation); ?>
        </small>
      </div>
      <div class="stat-icon icon-success" style="width: 40px; height: 40px; font-size: 1.25rem;">
        <i class="bi bi-box-seam-fill"></i>
      </div>
    </div>
  </div>

  <!-- Stock Alerts -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-warning d-flex align-items-center justify-content-between">
      <div>
        <div class="text-muted small fw-semibold text-uppercase">Inventory Alerts</div>
        <h4 class="fw-bold text-dark m-0 mt-1"><?= $lowStockCount + $outOfStockCount; ?> items</h4>
        <small class="<?= ($outOfStockCount > 0 || $lowStockCount > 0) ? 'text-warning fw-bold' : 'text-success'; ?>" style="font-size: 0.75rem;">
          <?= $lowStockCount; ?> low &bull; <?= $outOfStockCount; ?> out of stock
        </small>
      </div>
      <div class="stat-icon icon-warning" style="width: 40px; height: 40px; font-size: 1.25rem;">
        <i class="bi bi-exclamation-triangle-fill"></i>
      </div>
    </div>
  </div>
</div>

<!-- Row: Recent Orders & Recent Payments Side by Side -->
<div class="row g-4 mb-4">
  <!-- Recent Orders -->
  <div class="col-lg-6">
    <div class="card shadow-sm h-100 border-0">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="m-0 fw-bold text-dark">
          <i class="bi bi-cart-check-fill me-2 text-primary"></i> Recent Optical Orders
        </h6>
        <a href="<?= BASE_URL; ?>modules/orders/index.php" class="btn btn-sm btn-outline-primary">
          View All
        </a>
      </div>
      <div class="table-responsive">
        <?php if (empty($recentOrders)): ?>
          <div class="p-4 text-center text-muted small">
            No orders placed yet. <a href="<?= BASE_URL; ?>modules/orders/create.php">Create first order</a>.
          </div>
        <?php else: ?>
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Order</th>
                <th>Customer</th>
                <th class="text-end">Total</th>
                <th class="text-end">Due</th>
                <th class="text-center">Status</th>
                <th class="text-end pe-3">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentOrders as $ro): ?>
                <tr>
                  <td class="ps-3 font-monospace fw-bold">
                    <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $ro['id']; ?>" class="text-primary text-decoration-none">
                      <?= e($ro['order_code']); ?>
                    </a>
                  </td>
                  <td>
                    <div class="fw-semibold text-dark"><?= e($ro['customer_name']); ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= e($ro['customer_phone']); ?></div>
                  </td>
                  <td class="text-end font-monospace fw-bold text-dark">
                    <?= formatMoney($ro['grand_total']); ?>
                  </td>
                  <td class="text-end font-monospace <?= (float)$ro['due_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                    <?= formatMoney($ro['due_amount']); ?>
                  </td>
                  <td class="text-center">
                    <?= getOrderStatusBadge($ro['status']); ?>
                  </td>
                  <td class="text-end pe-3">
                    <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $ro['id']; ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Order">
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

  <!-- Recent Payments -->
  <div class="col-lg-6">
    <div class="card shadow-sm h-100 border-0">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="m-0 fw-bold text-dark">
          <i class="bi bi-cash-stack me-2 text-success"></i> Recent Payment Receipts
        </h6>
        <a href="<?= BASE_URL; ?>modules/reports/payments.php" class="btn btn-sm btn-outline-success">
          View Collections
        </a>
      </div>
      <div class="table-responsive">
        <?php if (empty($recentPayments)): ?>
          <div class="p-4 text-center text-muted small">
            No payment transactions recorded yet.
          </div>
        <?php else: ?>
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Receipt #</th>
                <th>Order</th>
                <th>Customer</th>
                <th>Method</th>
                <th class="text-end">Amount</th>
                <th class="text-end pe-3">Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentPayments as $rp): ?>
                <tr>
                  <td class="ps-3 font-monospace fw-bold text-dark">
                    <?= e($rp['receipt_number']); ?>
                  </td>
                  <td class="font-monospace">
                    <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $rp['order_id']; ?>" class="text-decoration-none">
                      <?= e($rp['order_code']); ?>
                    </a>
                  </td>
                  <td class="text-dark small">
                    <?= e($rp['customer_name']); ?>
                  </td>
                  <td class="text-capitalize small">
                    <span class="badge bg-light text-dark border"><?= str_replace('_', ' ', e($rp['payment_method'])); ?></span>
                  </td>
                  <td class="text-end font-monospace fw-bold text-success">
                    <?= formatMoney((float)$rp['amount']); ?>
                  </td>
                  <td class="text-end pe-3 small text-muted">
                    <?= formatDate($rp['payment_date']); ?>
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

<!-- Main Row: Recent Prescriptions + Recent Customers -->
<div class="row g-4 mb-4">
  <!-- Recent Prescriptions Widget -->
  <div class="col-lg-6">
    <div class="card shadow-sm h-100 border-0">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="m-0 fw-bold text-dark">
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
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Rx #</th>
                <th>Customer</th>
                <th>Date</th>
                <th>OD SPH</th>
                <th>OS SPH</th>
                <th class="text-end pe-3">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentPrescriptions as $rx): ?>
                <tr>
                  <td class="ps-3">
                    <span class="badge bg-light text-dark border font-monospace">#<?= str_pad($rx['id'], 5, '0', STR_PAD_LEFT); ?></span>
                  </td>
                  <td>
                    <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $rx['customer_id']; ?>" class="fw-semibold text-dark text-decoration-none">
                      <?= e($rx['customer_name']); ?>
                    </a>
                  </td>
                  <td class="text-muted small"><?= formatDate($rx['prescription_date']); ?></td>
                  <td class="small font-monospace"><?= formatOpticalPower($rx['right_sph']); ?></td>
                  <td class="small font-monospace"><?= formatOpticalPower($rx['left_sph']); ?></td>
                  <td class="text-end pe-3">
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
    <div class="card shadow-sm h-100 border-0">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="m-0 fw-bold text-dark">
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
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Code</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Status</th>
                <th class="text-end pe-3">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentCustomers as $rc): ?>
                <tr>
                  <td class="ps-3 font-monospace fw-semibold text-muted">
                    <?= e($rc['customer_code']); ?>
                  </td>
                  <td>
                    <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $rc['id']; ?>" class="fw-semibold text-dark text-decoration-none">
                      <?= e($rc['full_name']); ?>
                    </a>
                  </td>
                  <td class="text-dark small"><?= e($rc['phone']); ?></td>
                  <td>
                    <span class="badge <?= $rc['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                      <?= ucfirst(e($rc['status'])); ?>
                    </span>
                  </td>
                  <td class="text-end pe-3">
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

<!-- Chart Initialization Scripts -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1. Sales vs Collections Line/Bar Chart
    const trendCtx = document.getElementById('salesTrendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthLabels) ?>,
                datasets: [
                    {
                        label: 'Invoiced Sales ($)',
                        data: <?= json_encode($monthlySales) ?>,
                        backgroundColor: '#0284c7',
                        borderColor: '#0284c7',
                        borderRadius: 4,
                        maxBarThickness: 32
                    },
                    {
                        label: 'Cash Collections ($)',
                        data: <?= json_encode($monthlyPayments) ?>,
                        backgroundColor: '#10b981',
                        borderColor: '#10b981',
                        borderRadius: 4,
                        maxBarThickness: 32
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            font: { family: '-apple-system, BlinkMacSystemFont, Segoe UI, Roboto', size: 12 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + context.raw.toLocaleString(undefined, {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            callback: function(value) { return '$' + value.toLocaleString(); }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // 2. Order Status Donut Chart
    const statusCtx = document.getElementById('orderStatusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Confirmed', 'Processing', 'Ready', 'Delivered', 'Cancelled'],
                datasets: [{
                    data: [
                        <?= (int)$statusCounts['Pending'] ?>,
                        <?= (int)$statusCounts['Confirmed'] ?>,
                        <?= (int)$statusCounts['Processing'] ?>,
                        <?= (int)$statusCounts['Ready'] ?>,
                        <?= (int)$statusCounts['Delivered'] ?>,
                        <?= (int)$statusCounts['Cancelled'] ?>
                    ],
                    backgroundColor: [
                        '#64748b', // Pending (slate)
                        '#0284c7', // Confirmed (sky)
                        '#3b82f6', // Processing (blue)
                        '#f59e0b', // Ready (amber)
                        '#10b981', // Delivered (emerald)
                        '#ef4444'  // Cancelled (rose)
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const val = context.raw;
                                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return `${context.label}: ${val} orders (${pct}%)`;
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
