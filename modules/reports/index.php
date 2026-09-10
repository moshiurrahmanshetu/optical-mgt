<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Reports & Analytics Dashboard
 */

$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../../includes/header.php';

// Authorization: Admin, Optician, Sales Staff
requireRole(['admin', 'optician', 'sales_staff']);

$pdo = getDbConnection();
$isAdmin = hasRole('admin');
$isOptician = hasRole('optician');
$isSales = hasRole('sales_staff');

// Parse Period Filter
$period     = trim($_GET['period'] ?? 'this_month');
$customFrom = trim($_GET['date_from'] ?? '');
$customTo   = trim($_GET['date_to'] ?? '');

$dateInfo = parseDatePeriod($period, $customFrom, $customTo);
$dateFrom = $dateInfo['from'];
$dateTo   = $dateInfo['to'];
$periodLabel = $dateInfo['label'];

// Build Order Date Filter Clause
$orderWhere = [];
$orderParams = [];
if ($dateFrom) {
    $orderWhere[] = "o.order_date >= :date_from";
    $orderParams[':date_from'] = $dateFrom;
}
if ($dateTo) {
    $orderWhere[] = "o.order_date <= :date_to";
    $orderParams[':date_to'] = $dateTo;
}
$orderWhereSql = !empty($orderWhere) ? 'WHERE ' . implode(' AND ', $orderWhere) : '';

// 1. Order & Sales Aggregate Metrics for Selected Period
$orderAggSql = "
    SELECT 
        COUNT(*) AS total_orders,
        SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN o.status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_orders,
        SUM(CASE WHEN o.status = 'processing' THEN 1 ELSE 0 END) AS processing_orders,
        SUM(CASE WHEN o.status = 'ready' THEN 1 ELSE 0 END) AS ready_orders,
        SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders,
        SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
        SUM(CASE WHEN o.status != 'cancelled' THEN o.grand_total ELSE 0 END) AS total_sales,
        SUM(CASE WHEN o.status != 'cancelled' THEN o.due_amount ELSE 0 END) AS total_due
    FROM orders o
    {$orderWhereSql}
";
$orderAggStmt = $pdo->prepare($orderAggSql);
$orderAggStmt->execute($orderParams);
$orderStats = $orderAggStmt->fetch();

$totalOrders     = (int) ($orderStats['total_orders'] ?? 0);
$pendingOrders   = (int) ($orderStats['pending_orders'] ?? 0);
$confirmedOrders = (int) ($orderStats['confirmed_orders'] ?? 0);
$processingOrders= (int) ($orderStats['processing_orders'] ?? 0);
$readyOrders     = (int) ($orderStats['ready_orders'] ?? 0);
$deliveredOrders = (int) ($orderStats['delivered_orders'] ?? 0);
$cancelledOrders = (int) ($orderStats['cancelled_orders'] ?? 0);
$totalSales      = (float) ($orderStats['total_sales'] ?? 0.0);
$totalDue        = (float) ($orderStats['total_due'] ?? 0.0);

// 2. Payments Collected in Selected Period
$payWhere = [];
$payParams = [];
if ($dateFrom) {
    $payWhere[] = "payment_date >= :p_date_from";
    $payParams[':p_date_from'] = $dateFrom;
}
if ($dateTo) {
    $payWhere[] = "payment_date <= :p_date_to";
    $payParams[':p_date_to'] = $dateTo;
}
$payWhereSql = !empty($payWhere) ? 'WHERE ' . implode(' AND ', $payWhere) : '';

$payAggSql = "SELECT COALESCE(SUM(amount), 0) AS total_collected, COUNT(*) AS count_payments FROM payments {$payWhereSql}";
$payAggStmt = $pdo->prepare($payAggSql);
$payAggStmt->execute($payParams);
$payStats = $payAggStmt->fetch();
$totalPayments = (float) ($payStats['total_collected'] ?? 0.0);
$paymentsCount = (int) ($payStats['count_payments'] ?? 0);

// 3. Overall System Statistics (Customers, Prescriptions, Products)
$totalCust = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn();
$totalRx   = (int) $pdo->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn();
$totalProd = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$lowStock  = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity <= low_stock_threshold AND stock_quantity > 0")->fetchColumn();
$outStock  = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity <= 0")->fetchColumn();
?>

<!-- Header Actions & Breadcrumbs -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold text-dark m-0">Reports &amp; Business Intelligence</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small mt-1">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Reports</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL; ?>modules/reports/print.php?type=overview&period=<?= urlencode($period); ?>&date_from=<?= urlencode($customFrom); ?>&date_to=<?= urlencode($customTo); ?>" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-sm">
      <i class="bi bi-printer-fill"></i> Print Summary
    </a>
  </div>
</div>

<!-- Date Period Filter Bar -->
<div class="card shadow-sm mb-4 border-0">
  <div class="card-body p-3">
    <form method="GET" action="<?= BASE_URL; ?>modules/reports/index.php" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label for="period" class="form-label small text-muted fw-semibold mb-1">Time Period</label>
        <select class="form-select form-select-sm" id="period" name="period" onchange="toggleCustomDates(this.value)">
          <option value="today" <?= $period === 'today' ? 'selected' : ''; ?>>Today</option>
          <option value="yesterday" <?= $period === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
          <option value="this_week" <?= $period === 'this_week' ? 'selected' : ''; ?>>This Week</option>
          <option value="this_month" <?= $period === 'this_month' ? 'selected' : ''; ?>>This Month</option>
          <option value="last_month" <?= $period === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
          <option value="this_year" <?= $period === 'this_year' ? 'selected' : ''; ?>>This Year</option>
          <option value="custom" <?= $period === 'custom' ? 'selected' : ''; ?>>Custom Date Range</option>
          <option value="all" <?= $period === 'all' ? 'selected' : ''; ?>>All Time</option>
        </select>
      </div>

      <div class="col-md-3 custom-date-col <?= $period === 'custom' ? '' : 'd-none'; ?>">
        <label for="date_from" class="form-label small text-muted fw-semibold mb-1">Date From</label>
        <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="<?= e($customFrom); ?>">
      </div>

      <div class="col-md-3 custom-date-col <?= $period === 'custom' ? '' : 'd-none'; ?>">
        <label for="date_to" class="form-label small text-muted fw-semibold mb-1">Date To</label>
        <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="<?= e($customTo); ?>">
      </div>

      <div class="col-md-2 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary w-100" title="Apply Filter">
          <i class="bi bi-funnel-fill me-1"></i> Apply
        </button>
        <?php if ($period !== 'this_month' || !empty($customFrom) || !empty($customTo)): ?>
          <a href="<?= BASE_URL; ?>modules/reports/index.php" class="btn btn-sm btn-outline-secondary" title="Reset to This Month">
            <i class="bi bi-arrow-counterclockwise"></i>
          </a>
        <?php endif; ?>
      </div>

      <div class="col-12 mt-2">
        <small class="text-secondary">
          <i class="bi bi-calendar3 me-1"></i> Active Reporting Window: <strong><?= e($periodLabel); ?></strong>
        </small>
      </div>
    </form>
  </div>
</div>

<!-- Key Performance Indicators (Selected Period) -->
<div class="row g-3 mb-4">
  <!-- Total Sales -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-primary d-flex align-items-center justify-content-between p-3">
      <div>
        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Total Net Sales</div>
        <h3 class="fw-bold text-dark m-0 mt-1 font-monospace"><?= formatMoney($totalSales); ?></h3>
        <small class="text-primary fw-semibold" style="font-size: 0.75rem;">
          <i class="bi bi-cart-check"></i> <?= $totalOrders; ?> total orders
        </small>
      </div>
      <div class="stat-icon icon-primary">
        <i class="bi bi-currency-dollar"></i>
      </div>
    </div>
  </div>

  <!-- Payments Collected -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-success d-flex align-items-center justify-content-between p-3">
      <div>
        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Payments Received</div>
        <h3 class="fw-bold text-dark m-0 mt-1 font-monospace text-success"><?= formatMoney($totalPayments); ?></h3>
        <small class="text-success fw-semibold" style="font-size: 0.75rem;">
          <i class="bi bi-check2-all"></i> <?= $paymentsCount; ?> transactions
        </small>
      </div>
      <div class="stat-icon icon-success">
        <i class="bi bi-cash-stack"></i>
      </div>
    </div>
  </div>

  <!-- Total Outstanding Due -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-warning d-flex align-items-center justify-content-between p-3">
      <div>
        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Period Due Balance</div>
        <h3 class="fw-bold text-dark m-0 mt-1 font-monospace text-danger"><?= formatMoney($totalDue); ?></h3>
        <small class="<?= $totalDue > 0 ? 'text-danger fw-bold' : 'text-success'; ?>" style="font-size: 0.75rem;">
          <i class="bi bi-exclamation-circle"></i> <?= $totalDue > 0 ? 'Unsettled receivables' : 'Zero outstanding balance'; ?>
        </small>
      </div>
      <div class="stat-icon icon-warning">
        <i class="bi bi-wallet2"></i>
      </div>
    </div>
  </div>

  <!-- Delivered Orders -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-info d-flex align-items-center justify-content-between p-3">
      <div>
        <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Delivered Orders</div>
        <h3 class="fw-bold text-dark m-0 mt-1"><?= $deliveredOrders; ?></h3>
        <small class="text-info fw-semibold" style="font-size: 0.75rem;">
          <?= $pendingOrders; ?> pending &bull; <?= $cancelledOrders; ?> cancelled
        </small>
      </div>
      <div class="stat-icon icon-info">
        <i class="bi bi-box-seam-fill"></i>
      </div>
    </div>
  </div>
</div>

<!-- Specialized Reports Directory Cards -->
<div class="row g-4 mb-4">
  
  <!-- 1. Sales Report -->
  <div class="col-md-6 col-lg-4">
    <div class="card shadow-sm h-100 border-0 hover-shadow transition">
      <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="p-2 rounded bg-primary bg-opacity-10 text-primary">
              <i class="bi bi-graph-up fs-4"></i>
            </div>
            <span class="badge bg-light text-primary border font-monospace"><?= formatMoney($totalSales); ?></span>
          </div>
          <h5 class="fw-bold text-dark mb-1">Sales Report</h5>
          <p class="text-muted small mb-3">
            Complete transaction breakdown including order subtotals, discounts applied, net grand totals, and payment status.
          </p>
        </div>
        <a href="<?= BASE_URL; ?>modules/reports/sales.php?period=<?= urlencode($period); ?>&date_from=<?= urlencode($customFrom); ?>&date_to=<?= urlencode($customTo); ?>" class="btn btn-outline-primary btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1">
          Generate Sales Report <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- 2. Payment Collections Report -->
  <div class="col-md-6 col-lg-4">
    <div class="card shadow-sm h-100 border-0 hover-shadow transition">
      <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="p-2 rounded bg-success bg-opacity-10 text-success">
              <i class="bi bi-cash-coin fs-4"></i>
            </div>
            <span class="badge bg-light text-success border font-monospace"><?= formatMoney($totalPayments); ?></span>
          </div>
          <h5 class="fw-bold text-dark mb-1">Payment Report</h5>
          <p class="text-muted small mb-3">
            Cash, Card, and Mobile Banking transaction ledger with receipt references, date tracking, and recipient staff audit.
          </p>
        </div>
        <a href="<?= BASE_URL; ?>modules/reports/payments.php?period=<?= urlencode($period); ?>&date_from=<?= urlencode($customFrom); ?>&date_to=<?= urlencode($customTo); ?>" class="btn btn-outline-success btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1">
          Generate Payment Report <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- 3. Due & Receivables Report -->
  <div class="col-md-6 col-lg-4">
    <div class="card shadow-sm h-100 border-0 hover-shadow transition">
      <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="p-2 rounded bg-warning bg-opacity-10 text-warning">
              <i class="bi bi-exclamation-diamond fs-4"></i>
            </div>
            <span class="badge bg-light text-danger border font-monospace"><?= formatMoney($totalDue); ?></span>
          </div>
          <h5 class="fw-bold text-dark mb-1">Outstanding Due Report</h5>
          <p class="text-muted small mb-3">
            List of customers and orders with unpaid balances, contact phone numbers, order timeline, and debt recovery status.
          </p>
        </div>
        <a href="<?= BASE_URL; ?>modules/reports/due.php" class="btn btn-outline-warning btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1 text-dark">
          Generate Due Report <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- 4. Orders & Workflow Report -->
  <div class="col-md-6 col-lg-4">
    <div class="card shadow-sm h-100 border-0 hover-shadow transition">
      <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="p-2 rounded bg-info bg-opacity-10 text-info">
              <i class="bi bi-receipt-cutoff fs-4"></i>
            </div>
            <span class="badge bg-light text-dark border"><?= $totalOrders; ?> Orders</span>
          </div>
          <h5 class="fw-bold text-dark mb-1">Order Status Report</h5>
          <p class="text-muted small mb-3">
            Order lifecycle analytics categorized by workflow state: Pending, Confirmed, Workshop Processing, Ready, and Delivered.
          </p>
        </div>
        <a href="<?= BASE_URL; ?>modules/reports/orders.php?period=<?= urlencode($period); ?>" class="btn btn-outline-info btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1 text-dark">
          Generate Order Report <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- 5. Product & Stock Health Report -->
  <div class="col-md-6 col-lg-4">
    <div class="card shadow-sm h-100 border-0 hover-shadow transition">
      <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="p-2 rounded bg-secondary bg-opacity-10 text-secondary">
              <i class="bi bi-box-seam fs-4"></i>
            </div>
            <span class="badge bg-light text-dark border"><?= $totalProd; ?> Catalog Items</span>
          </div>
          <h5 class="fw-bold text-dark mb-1">Product &amp; Stock Report</h5>
          <p class="text-muted small mb-3">
            Inventory stock availability, low-stock warnings (<?= $lowStock; ?>), out-of-stock items (<?= $outStock; ?>), and category breakdowns.
          </p>
        </div>
        <a href="<?= BASE_URL; ?>modules/reports/products.php" class="btn btn-outline-secondary btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1">
          Generate Inventory Report <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- 6. Customers & Patient Analytics -->
  <div class="col-md-6 col-lg-4">
    <div class="card shadow-sm h-100 border-0 hover-shadow transition">
      <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="p-2 rounded bg-dark bg-opacity-10 text-dark">
              <i class="bi bi-people fs-4"></i>
            </div>
            <span class="badge bg-light text-dark border"><?= $totalCust; ?> Active Patients</span>
          </div>
          <h5 class="fw-bold text-dark mb-1">Customer Report</h5>
          <p class="text-muted small mb-3">
            Patient demographic records, new client registration trends, top spending customers, and repeat optical visits.
          </p>
        </div>
        <a href="<?= BASE_URL; ?>modules/reports/customers.php?period=<?= urlencode($period); ?>" class="btn btn-outline-dark btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1">
          Generate Customer Report <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- 7. Prescription Refraction Report (Admin & Optician) -->
  <?php if ($isAdmin || $isOptician): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card shadow-sm h-100 border-0 hover-shadow transition">
        <div class="card-body p-4 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex align-items-center justify-content-between mb-3">
              <div class="p-2 rounded bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-file-earmark-medical fs-4"></i>
              </div>
              <span class="badge bg-light text-dark border"><?= $totalRx; ?> Total Rx</span>
            </div>
            <h5 class="fw-bold text-dark mb-1">Prescription Report</h5>
            <p class="text-muted small mb-3">
              Clinical eye refraction records, examining doctor distribution, optical power measurements, and dispensing links.
            </p>
          </div>
          <a href="<?= BASE_URL; ?>modules/reports/prescriptions.php?period=<?= urlencode($period); ?>" class="btn btn-outline-primary btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1">
            Generate Rx Report <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<script>
function toggleCustomDates(val) {
  const customCols = document.querySelectorAll('.custom-date-col');
  customCols.forEach(col => {
    if (val === 'custom') {
      col.classList.remove('d-none');
    } else {
      col.classList.add('d-none');
    }
  });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
