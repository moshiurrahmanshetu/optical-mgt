<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Orders & Billing Management
 */

$pageTitle = 'Orders & Billing';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDbConnection();
$isAdmin = hasRole('admin');
$canManageOrders = hasRole(['admin', 'optician', 'sales_staff']);

// --- Filters and Pagination Parameters ---
$search        = trim($_GET['q'] ?? '');
$statusFilter  = trim($_GET['status'] ?? 'all');
$paymentFilter = trim($_GET['payment_status'] ?? 'all');
$dateFrom      = trim($_GET['date_from'] ?? '');
$dateTo        = trim($_GET['date_to'] ?? '');
$page          = max(1, (int) ($_GET['page'] ?? 1));
$limit         = 10;
$offset        = ($page - 1) * $limit;

// --- Build SQL Query with Conditions ---
$whereConditions = [];
$params = [];

if ($search !== '') {
    $whereConditions[] = "(o.order_code LIKE :search OR c.full_name LIKE :search OR c.phone LIKE :search OR c.customer_code LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($statusFilter !== '' && $statusFilter !== 'all') {
    if (in_array($statusFilter, ['pending', 'confirmed', 'processing', 'ready', 'delivered', 'cancelled'], true)) {
        $whereConditions[] = "o.status = :status";
        $params[':status'] = $statusFilter;
    }
}

if ($paymentFilter === 'paid') {
    $whereConditions[] = "o.due_amount <= 0";
} elseif ($paymentFilter === 'due') {
    $whereConditions[] = "o.due_amount > 0";
} elseif ($paymentFilter === 'partial') {
    $whereConditions[] = "o.paid_amount > 0 AND o.due_amount > 0";
} elseif ($paymentFilter === 'unpaid') {
    $whereConditions[] = "o.paid_amount <= 0 AND o.due_amount > 0";
}

if ($dateFrom !== '') {
    $whereConditions[] = "o.order_date >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $whereConditions[] = "o.order_date <= :date_to";
    $params[':date_to'] = $dateTo;
}

$whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// --- Count Total Matching Records ---
$countSql = "
    SELECT COUNT(*) 
    FROM orders o 
    JOIN customers c ON o.customer_id = c.id 
    {$whereSql}
";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$totalRecords = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRecords / $limit));

// --- Fetch Matching Orders with Customer & User details ---
$sql = "
    SELECT o.*, c.full_name AS customer_name, c.customer_code, c.phone AS customer_phone,
           u.name AS created_by_name
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON o.created_by = u.id
    {$whereSql}
    ORDER BY o.order_date DESC, o.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

// --- Live KPI Statistics ---
try {
    $kpiStats = $pdo->query("
        SELECT 
            COUNT(*) AS total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN status IN ('confirmed', 'processing', 'ready') THEN 1 ELSE 0 END) AS active_orders,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders,
            SUM(CASE WHEN status != 'cancelled' THEN grand_total ELSE 0 END) AS total_sales,
            SUM(CASE WHEN status != 'cancelled' THEN due_amount ELSE 0 END) AS total_due
        FROM orders
    ")->fetch();

    $statTotalOrders = (int) ($kpiStats['total_orders'] ?? 0);
    $statPending     = (int) ($kpiStats['pending_orders'] ?? 0);
    $statActive      = (int) ($kpiStats['active_orders'] ?? 0);
    $statDelivered   = (int) ($kpiStats['delivered_orders'] ?? 0);
    $statSales       = (float) ($kpiStats['total_sales'] ?? 0.0);
    $statDue         = (float) ($kpiStats['total_due'] ?? 0.0);
} catch (Exception $e) {
    $statTotalOrders = $statPending = $statActive = $statDelivered = 0;
    $statSales = $statDue = 0.0;
}
?>

<!-- Header Actions & Breadcrumbs -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold text-dark m-0">Orders &amp; Billing</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small mt-1">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Orders</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL; ?>modules/orders/create.php" class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm">
      <i class="bi bi-cart-plus-fill"></i> Create New Order
    </a>
  </div>
</div>

<!-- KPI Metrics Cards (Solid Colors) -->
<div class="row g-3 mb-4">
  <!-- Total Orders -->
  <div class="col-sm-6 col-xl-2">
    <div class="stat-card stat-primary h-100 p-3">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Total Orders</div>
      <h3 class="fw-bold text-dark m-0 mt-1"><?= $statTotalOrders; ?></h3>
      <small class="text-muted" style="font-size: 0.75rem;">Lifetime count</small>
    </div>
  </div>

  <!-- Pending Orders -->
  <div class="col-sm-6 col-xl-2">
    <div class="stat-card h-100 p-3" style="border-left: 4px solid var(--secondary);">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Pending</div>
      <h3 class="fw-bold text-dark m-0 mt-1"><?= $statPending; ?></h3>
      <small class="text-secondary" style="font-size: 0.75rem;">Awaiting confirm</small>
    </div>
  </div>

  <!-- In-Progress (Confirmed / Processing / Ready) -->
  <div class="col-sm-6 col-xl-2">
    <div class="stat-card stat-info h-100 p-3">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">In Processing</div>
      <h3 class="fw-bold text-dark m-0 mt-1"><?= $statActive; ?></h3>
      <small class="text-info fw-semibold" style="font-size: 0.75rem;">Workshop &amp; Ready</small>
    </div>
  </div>

  <!-- Delivered -->
  <div class="col-sm-6 col-xl-2">
    <div class="stat-card stat-success h-100 p-3">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Delivered</div>
      <h3 class="fw-bold text-dark m-0 mt-1"><?= $statDelivered; ?></h3>
      <small class="text-success fw-semibold" style="font-size: 0.75rem;">Completed</small>
    </div>
  </div>

  <!-- Total Sales (Non-Cancelled) -->
  <div class="col-sm-6 col-xl-2">
    <div class="stat-card stat-primary h-100 p-3">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Total Sales</div>
      <h4 class="fw-bold text-dark m-0 mt-1 font-monospace" style="font-size: 1.15rem;"><?= formatMoney($statSales); ?></h4>
      <small class="text-primary fw-semibold" style="font-size: 0.75rem;">Active orders</small>
    </div>
  </div>

  <!-- Total Due Amount -->
  <div class="col-sm-6 col-xl-2">
    <div class="stat-card stat-warning h-100 p-3">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Outstanding Due</div>
      <h4 class="fw-bold text-dark m-0 mt-1 font-monospace text-danger" style="font-size: 1.15rem;"><?= formatMoney($statDue); ?></h4>
      <small class="<?= $statDue > 0 ? 'text-danger fw-bold' : 'text-success'; ?>" style="font-size: 0.75rem;">
        <?= $statDue > 0 ? 'Collectable due' : 'All clear'; ?>
      </small>
    </div>
  </div>
</div>

<!-- Search & Filters Card -->
<div class="card shadow-sm mb-4">
  <div class="card-body p-3">
    <form method="GET" action="<?= BASE_URL; ?>modules/orders/index.php" class="row g-2 align-items-end">
      <!-- Search Input -->
      <div class="col-md-3">
        <label for="search" class="form-label small text-muted fw-semibold mb-1">Search Orders</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control" id="search" name="q" value="<?= e($search); ?>" placeholder="Order #, Customer, Phone...">
        </div>
      </div>

      <!-- Status Filter -->
      <div class="col-md-2">
        <label for="status" class="form-label small text-muted fw-semibold mb-1">Order Status</label>
        <select class="form-select form-select-sm" id="status" name="status">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
          <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
          <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
          <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : ''; ?>>Ready</option>
          <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
          <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>
      </div>

      <!-- Payment Status Filter -->
      <div class="col-md-2">
        <label for="payment_status" class="form-label small text-muted fw-semibold mb-1">Payment Status</label>
        <select class="form-select form-select-sm" id="payment_status" name="payment_status">
          <option value="all" <?= $paymentFilter === 'all' ? 'selected' : ''; ?>>All Settlements</option>
          <option value="paid" <?= $paymentFilter === 'paid' ? 'selected' : ''; ?>>Fully Paid</option>
          <option value="due" <?= $paymentFilter === 'due' ? 'selected' : ''; ?>>Has Due Balance</option>
          <option value="partial" <?= $paymentFilter === 'partial' ? 'selected' : ''; ?>>Partial Payment</option>
          <option value="unpaid" <?= $paymentFilter === 'unpaid' ? 'selected' : ''; ?>>Zero Payment (Unpaid)</option>
        </select>
      </div>

      <!-- Date Range (From) -->
      <div class="col-md-2">
        <label for="date_from" class="form-label small text-muted fw-semibold mb-1">Date From</label>
        <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="<?= e($dateFrom); ?>">
      </div>

      <!-- Date Range (To) -->
      <div class="col-md-2">
        <label for="date_to" class="form-label small text-muted fw-semibold mb-1">Date To</label>
        <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="<?= e($dateTo); ?>">
      </div>

      <!-- Buttons -->
      <div class="col-md-1 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary w-100" title="Apply Filters">
          <i class="bi bi-funnel-fill"></i>
        </button>
        <?php if ($search !== '' || $statusFilter !== 'all' || $paymentFilter !== 'all' || $dateFrom !== '' || $dateTo !== ''): ?>
          <a href="<?= BASE_URL; ?>modules/orders/index.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
            <i class="bi bi-x-circle"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Orders Table Card -->
<div class="card shadow-sm border-0">
  <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <h6 class="m-0 fw-semibold text-dark">
        <i class="bi bi-receipt me-1 text-primary"></i> Orders List
      </h6>
      <span class="badge bg-light text-dark border"><?= number_format($totalRecords); ?> <?= $totalRecords === 1 ? 'Order' : 'Orders'; ?></span>
    </div>
  </div>

  <div class="table-responsive">
    <?php if (empty($orders)): ?>
      <div class="p-5 text-center">
        <div class="text-secondary mb-3"><i class="bi bi-cart-x fs-1"></i></div>
        <h5 class="fw-bold text-dark">No orders found</h5>
        <p class="text-muted small mb-4">
          <?= ($search !== '' || $statusFilter !== 'all' || $paymentFilter !== 'all' || $dateFrom !== '' || $dateTo !== '') ? 'No orders match your filter criteria. Try adjusting your search query.' : 'There are no optical orders recorded in the system yet.'; ?>
        </p>
        <a href="<?= BASE_URL; ?>modules/orders/create.php" class="btn btn-primary btn-sm px-3">
          <i class="bi bi-cart-plus me-1"></i> Create First Order
        </a>
      </div>
    <?php else: ?>
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-3">Order Code</th>
            <th>Customer</th>
            <th>Order Date</th>
            <th class="text-end">Grand Total</th>
            <th class="text-end">Paid</th>
            <th class="text-end">Due</th>
            <th class="text-center">Status</th>
            <th>Created By</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $ord): ?>
            <tr>
              <td class="ps-3">
                <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $ord['id']; ?>" class="font-monospace fw-bold text-primary text-decoration-none">
                  <?= e($ord['order_code']); ?>
                </a>
              </td>
              <td>
                <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $ord['customer_id']; ?>" class="fw-semibold text-dark text-decoration-none hover-primary">
                  <?= e($ord['customer_name']); ?>
                </a>
                <div class="small text-muted font-monospace"><?= e($ord['customer_phone']); ?></div>
              </td>
              <td class="small text-dark">
                <?= date('M d, Y', strtotime($ord['order_date'])); ?>
              </td>
              <td class="text-end font-monospace fw-bold text-dark">
                <?= formatMoney($ord['grand_total']); ?>
              </td>
              <td class="text-end font-monospace text-success fw-semibold">
                <?= formatMoney($ord['paid_amount']); ?>
              </td>
              <td class="text-end font-monospace <?= (float)$ord['due_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                <?= formatMoney($ord['due_amount']); ?>
              </td>
              <td class="text-center">
                <?= getOrderStatusBadge($ord['status']); ?>
              </td>
              <td class="small text-muted">
                <?= !empty($ord['created_by_name']) ? e($ord['created_by_name']) : '&mdash;'; ?>
              </td>
              <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                  <!-- View Order -->
                  <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $ord['id']; ?>" class="btn btn-outline-secondary" title="View Order Details">
                    <i class="bi bi-eye"></i>
                  </a>

                  <!-- Print Invoice -->
                  <a href="<?= BASE_URL; ?>modules/orders/print.php?id=<?= $ord['id']; ?>" target="_blank" class="btn btn-outline-secondary" title="Print Invoice">
                    <i class="bi bi-printer"></i>
                  </a>

                  <!-- Edit (Pending Only) -->
                  <?php if ($ord['status'] === 'pending'): ?>
                    <a href="<?= BASE_URL; ?>modules/orders/edit.php?id=<?= $ord['id']; ?>" class="btn btn-outline-primary" title="Edit Pending Order">
                      <i class="bi bi-pencil"></i>
                    </a>
                  <?php endif; ?>

                  <!-- Add Payment Quick Action (If Due > 0 and not cancelled) -->
                  <?php if ((float)$ord['due_amount'] > 0 && $ord['status'] !== 'cancelled'): ?>
                    <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $ord['id']; ?>#payment-section" class="btn btn-outline-success" title="Add Payment">
                      <i class="bi bi-cash-stack"></i>
                    </a>
                  <?php endif; ?>

                  <!-- Delete (Admin Only, Pending Only) -->
                  <?php if ($isAdmin && $ord['status'] === 'pending'): ?>
                    <form method="POST" action="<?= BASE_URL; ?>modules/orders/delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete pending order <?= e($ord['order_code']); ?>? This action cannot be undone.');">
                      <?= csrfField(); ?>
                      <input type="hidden" name="id" value="<?= $ord['id']; ?>">
                      <button type="submit" class="btn btn-outline-danger" title="Delete Pending Order" style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Pagination Footer -->
  <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white d-flex flex-column flex-md-row justify-content-between align-items-center py-3">
      <small class="text-muted mb-2 mb-md-0">
        Showing <?= min(($offset + 1), $totalRecords); ?> to <?= min(($offset + $limit), $totalRecords); ?> of <?= $totalRecords; ?> orders
      </small>
      <nav aria-label="Page navigation">
        <ul class="pagination pagination-sm m-0">
          <?php
          $queryParams = $_GET;
          $prevPage = $page - 1;
          $nextPage = $page + 1;
          ?>

          <!-- Previous Page Link -->
          <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
            <?php $queryParams['page'] = $prevPage; ?>
            <a class="page-link" href="<?= BASE_URL; ?>modules/orders/index.php?<?= http_build_query($queryParams); ?>" aria-label="Previous">
              <span aria-hidden="true">&laquo; Prev</span>
            </a>
          </li>

          <!-- Page Number Links -->
          <?php
          $startPage = max(1, $page - 2);
          $endPage   = min($totalPages, $page + 2);
          for ($i = $startPage; $i <= $endPage; $i++):
              $queryParams['page'] = $i;
          ?>
            <li class="page-item <?= $i === $page ? 'active' : ''; ?>">
              <a class="page-link" href="<?= BASE_URL; ?>modules/orders/index.php?<?= http_build_query($queryParams); ?>"><?= $i; ?></a>
            </li>
          <?php endfor; ?>

          <!-- Next Page Link -->
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
            <?php $queryParams['page'] = $nextPage; ?>
            <a class="page-link" href="<?= BASE_URL; ?>modules/orders/index.php?<?= http_build_query($queryParams); ?>" aria-label="Next">
              <span aria-hidden="true">Next &raquo;</span>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
