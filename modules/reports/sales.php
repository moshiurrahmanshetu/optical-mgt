<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Comprehensive Sales & Revenue Report
 */

$pageTitle = 'Sales Report';
require_once __DIR__ . '/../../includes/header.php';

// Authorization: Admin, Optician, Sales Staff
requireRole(['admin', 'optician', 'sales_staff']);

$pdo = getDbConnection();

// Filters and Pagination
$period       = trim($_GET['period'] ?? 'this_month');
$customFrom   = trim($_GET['date_from'] ?? '');
$customTo     = trim($_GET['date_to'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$limit        = 15;
$offset       = ($page - 1) * $limit;

$dateInfo = parseDatePeriod($period, $customFrom, $customTo);
$dateFrom = $dateInfo['from'];
$dateTo   = $dateInfo['to'];
$periodLabel = $dateInfo['label'];

// Build SQL Query
$where = [];
$params = [];

if ($dateFrom) {
    $where[] = "o.order_date >= :date_from";
    $params[':date_from'] = $dateFrom;
}
if ($dateTo) {
    $where[] = "o.order_date <= :date_to";
    $params[':date_to'] = $dateTo;
}

if ($statusFilter !== '' && $statusFilter !== 'all') {
    if (in_array($statusFilter, ['pending', 'confirmed', 'processing', 'ready', 'delivered', 'cancelled'], true)) {
        $where[] = "o.status = :status";
        $params[':status'] = $statusFilter;
    }
}

if ($search !== '') {
    $where[] = "(o.order_code LIKE :search OR c.full_name LIKE :search OR c.phone LIKE :search OR c.customer_code LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Overall Summary Totals for this selection (Unpaginated)
$sumSql = "
    SELECT 
        COUNT(*) AS total_count,
        SUM(CASE WHEN o.status != 'cancelled' THEN o.subtotal ELSE 0 END) AS total_subtotal,
        SUM(CASE WHEN o.status != 'cancelled' THEN o.discount ELSE 0 END) AS total_discount,
        SUM(CASE WHEN o.status != 'cancelled' THEN o.grand_total ELSE 0 END) AS net_sales,
        SUM(CASE WHEN o.status != 'cancelled' THEN o.paid_amount ELSE 0 END) AS total_paid,
        SUM(CASE WHEN o.status != 'cancelled' THEN o.due_amount ELSE 0 END) AS total_due,
        SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    {$whereSql}
";
$sumStmt = $pdo->prepare($sumSql);
foreach ($params as $k => $v) {
    $sumStmt->bindValue($k, $v);
}
$sumStmt->execute();
$totals = $sumStmt->fetch();

$totalRecords = (int) ($totals['total_count'] ?? 0);
$totalPages   = max(1, (int) ceil($totalRecords / $limit));

// Fetch Paginated Sales Records
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
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();
?>

<!-- Header Actions & Breadcrumbs -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold text-dark m-0">Sales &amp; Revenue Report</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small mt-1">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/reports/index.php" class="text-decoration-none">Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Sales Report</li>
      </ol>
    </nav>
  </div>

  <div class="d-flex gap-2">
    <!-- Print Report Button -->
    <a href="<?= BASE_URL; ?>modules/reports/print.php?type=sales&period=<?= urlencode($period); ?>&date_from=<?= urlencode($customFrom); ?>&date_to=<?= urlencode($customTo); ?>&status=<?= urlencode($statusFilter); ?>&q=<?= urlencode($search); ?>" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-sm">
      <i class="bi bi-printer-fill"></i> Print Report
    </a>
    <a href="<?= BASE_URL; ?>modules/reports/index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
      <i class="bi bi-arrow-left"></i> Reports Hub
    </a>
  </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm mb-4 border-0">
  <div class="card-body p-3">
    <form method="GET" action="<?= BASE_URL; ?>modules/reports/sales.php" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label for="period" class="form-label small text-muted fw-semibold mb-1">Time Period</label>
        <select class="form-select form-select-sm" id="period" name="period" onchange="toggleCustomDates(this.value)">
          <option value="today" <?= $period === 'today' ? 'selected' : ''; ?>>Today</option>
          <option value="yesterday" <?= $period === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
          <option value="this_week" <?= $period === 'this_week' ? 'selected' : ''; ?>>This Week</option>
          <option value="this_month" <?= $period === 'this_month' ? 'selected' : ''; ?>>This Month</option>
          <option value="last_month" <?= $period === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
          <option value="this_year" <?= $period === 'this_year' ? 'selected' : ''; ?>>This Year</option>
          <option value="custom" <?= $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
          <option value="all" <?= $period === 'all' ? 'selected' : ''; ?>>All Time</option>
        </select>
      </div>

      <div class="col-md-2 custom-date-col <?= $period === 'custom' ? '' : 'd-none'; ?>">
        <label for="date_from" class="form-label small text-muted fw-semibold mb-1">Date From</label>
        <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="<?= e($customFrom); ?>">
      </div>

      <div class="col-md-2 custom-date-col <?= $period === 'custom' ? '' : 'd-none'; ?>">
        <label for="date_to" class="form-label small text-muted fw-semibold mb-1">Date To</label>
        <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="<?= e($customTo); ?>">
      </div>

      <div class="col-md-2">
        <label for="status" class="form-label small text-muted fw-semibold mb-1">Status</label>
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

      <div class="col-md-2">
        <label for="search" class="form-label small text-muted fw-semibold mb-1">Search</label>
        <input type="text" class="form-control form-control-sm" id="search" name="q" value="<?= e($search); ?>" placeholder="Order #, Customer...">
      </div>

      <div class="col-md-1 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary w-100" title="Generate Report">
          <i class="bi bi-funnel-fill"></i>
        </button>
        <?php if ($period !== 'this_month' || $statusFilter !== 'all' || $search !== '' || !empty($customFrom) || !empty($customTo)): ?>
          <a href="<?= BASE_URL; ?>modules/reports/sales.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
            <i class="bi bi-x-circle"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Summary Financial Highlights -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Total Invoiced (Subtotal)</div>
      <h4 class="fw-bold text-dark m-0 mt-1 font-monospace"><?= formatMoney($totals['total_subtotal'] ?? 0); ?></h4>
      <small class="text-muted" style="font-size: 0.75rem;"><?= number_format($totalRecords); ?> orders analyzed</small>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Total Discounts</div>
      <h4 class="fw-bold text-danger m-0 mt-1 font-monospace">- <?= formatMoney($totals['total_discount'] ?? 0); ?></h4>
      <small class="text-muted" style="font-size: 0.75rem;">Discounts applied</small>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="p-3 bg-white rounded border shadow-sm" style="border-left: 4px solid var(--primary) !important;">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Net Sales (Grand Total)</div>
      <h4 class="fw-bold text-primary m-0 mt-1 font-monospace"><?= formatMoney($totals['net_sales'] ?? 0); ?></h4>
      <small class="text-primary fw-semibold" style="font-size: 0.75rem;">Excludes cancelled orders</small>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="p-3 bg-white rounded border shadow-sm" style="border-left: 4px solid var(--warning) !important;">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Outstanding Due</div>
      <h4 class="fw-bold text-danger m-0 mt-1 font-monospace"><?= formatMoney($totals['total_due'] ?? 0); ?></h4>
      <small class="text-success fw-semibold" style="font-size: 0.75rem;">Paid: <?= formatMoney($totals['total_paid'] ?? 0); ?></small>
    </div>
  </div>
</div>

<!-- Sales Data Table -->
<div class="card shadow-sm border-0 mb-4">
  <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <h6 class="m-0 fw-semibold text-dark">
        <i class="bi bi-table me-2 text-primary"></i> Detailed Sales Transactions
      </h6>
      <span class="badge bg-light text-dark border"><?= number_format($totalRecords); ?> Records</span>
    </div>
    <small class="text-muted">Window: <strong><?= e($periodLabel); ?></strong></small>
  </div>

  <div class="table-responsive">
    <?php if (empty($orders)): ?>
      <div class="p-5 text-center text-muted">
        <i class="bi bi-inbox fs-1 text-secondary mb-2 d-block"></i>
        <h6>No sales records found</h6>
        <p class="small mb-0">No optical orders matched your filter criteria.</p>
      </div>
    <?php else: ?>
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-3">Order Code</th>
            <th>Date</th>
            <th>Customer</th>
            <th class="text-end">Subtotal</th>
            <th class="text-end">Discount</th>
            <th class="text-end">Grand Total</th>
            <th class="text-end">Paid</th>
            <th class="text-end">Due</th>
            <th class="text-center">Status</th>
            <th class="text-end pe-3">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $ord): ?>
            <tr class="<?= $ord['status'] === 'cancelled' ? 'table-light opacity-75' : ''; ?>">
              <td class="ps-3">
                <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $ord['id']; ?>" class="font-monospace fw-bold text-primary text-decoration-none">
                  <?= e($ord['order_code']); ?>
                </a>
              </td>
              <td class="small text-dark"><?= date('M d, Y', strtotime($ord['order_date'])); ?></td>
              <td>
                <div class="fw-semibold text-dark"><?= e($ord['customer_name']); ?></div>
                <small class="text-muted font-monospace"><?= e($ord['customer_phone']); ?></small>
              </td>
              <td class="text-end font-monospace"><?= formatMoney($ord['subtotal']); ?></td>
              <td class="text-end font-monospace text-danger"><?= (float)$ord['discount'] > 0 ? '- ' . formatMoney($ord['discount']) : '&mdash;'; ?></td>
              <td class="text-end font-monospace fw-bold text-dark"><?= formatMoney($ord['grand_total']); ?></td>
              <td class="text-end font-monospace text-success"><?= formatMoney($ord['paid_amount']); ?></td>
              <td class="text-end font-monospace <?= (float)$ord['due_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                <?= formatMoney($ord['due_amount']); ?>
              </td>
              <td class="text-center">
                <?= getOrderStatusBadge($ord['status']); ?>
              </td>
              <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                  <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $ord['id']; ?>" class="btn btn-outline-secondary" title="View Order">
                    <i class="bi bi-eye"></i>
                  </a>
                  <a href="<?= BASE_URL; ?>modules/orders/print.php?id=<?= $ord['id']; ?>" target="_blank" class="btn btn-outline-secondary" title="Print Invoice">
                    <i class="bi bi-printer"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light font-monospace fw-bold">
          <tr>
            <td colspan="3" class="ps-3 text-uppercase font-sans-serif">Page Totals:</td>
            <td class="text-end">
              <?= formatMoney(array_reduce($orders, fn($carry, $item) => $carry + ($item['status'] !== 'cancelled' ? (float)$item['subtotal'] : 0), 0)); ?>
            </td>
            <td class="text-end text-danger">
              - <?= formatMoney(array_reduce($orders, fn($carry, $item) => $carry + ($item['status'] !== 'cancelled' ? (float)$item['discount'] : 0), 0)); ?>
            </td>
            <td class="text-end text-primary">
              <?= formatMoney(array_reduce($orders, fn($carry, $item) => $carry + ($item['status'] !== 'cancelled' ? (float)$item['grand_total'] : 0), 0)); ?>
            </td>
            <td class="text-end text-success">
              <?= formatMoney(array_reduce($orders, fn($carry, $item) => $carry + ($item['status'] !== 'cancelled' ? (float)$item['paid_amount'] : 0), 0)); ?>
            </td>
            <td class="text-end text-danger">
              <?= formatMoney(array_reduce($orders, fn($carry, $item) => $carry + ($item['status'] !== 'cancelled' ? (float)$item['due_amount'] : 0), 0)); ?>
            </td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
      <small class="text-muted">
        Showing <?= min(($offset + 1), $totalRecords); ?> to <?= min(($offset + $limit), $totalRecords); ?> of <?= $totalRecords; ?> records
      </small>
      <nav aria-label="Page navigation">
        <ul class="pagination pagination-sm m-0">
          <?php
          $qParams = $_GET;
          $prevPage = $page - 1;
          $nextPage = $page + 1;
          ?>
          <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
            <?php $qParams['page'] = $prevPage; ?>
            <a class="page-link" href="<?= BASE_URL; ?>modules/reports/sales.php?<?= http_build_query($qParams); ?>">&laquo; Prev</a>
          </li>
          <?php for ($p = 1; $p <= $totalPages; $p++): $qParams['page'] = $p; ?>
            <li class="page-item <?= $p === $page ? 'active' : ''; ?>">
              <a class="page-link" href="<?= BASE_URL; ?>modules/reports/sales.php?<?= http_build_query($qParams); ?>"><?= $p; ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
            <?php $qParams['page'] = $nextPage; ?>
            <a class="page-link" href="<?= BASE_URL; ?>modules/reports/sales.php?<?= http_build_query($qParams); ?>">Next &raquo;</a>
          </li>
        </ul>
      </nav>
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
