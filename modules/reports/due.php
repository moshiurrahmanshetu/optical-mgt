<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Outstanding Due & Receivables Report
 */

$pageTitle = 'Outstanding Due Report';
require_once __DIR__ . '/../../includes/header.php';

// Authorization: Admin, Optician, Sales Staff
requireRole(['admin', 'optician', 'sales_staff']);

$pdo = getDbConnection();

// Filters and Pagination
$period       = trim($_GET['period'] ?? 'all');
$customFrom   = trim($_GET['date_from'] ?? '');
$customTo     = trim($_GET['date_to'] ?? '');
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$limit        = 15;
$offset       = ($page - 1) * $limit;

$dateInfo = parseDatePeriod($period, $customFrom, $customTo);
$dateFrom = $dateInfo['from'];
$dateTo   = $dateInfo['to'];
$periodLabel = $dateInfo['label'];

// Build SQL Query (Strictly due_amount > 0 and not cancelled)
$where = ["o.due_amount > 0", "o.status != 'cancelled'"];
$params = [];

if ($dateFrom) {
    $where[] = "o.order_date >= :date_from";
    $params[':date_from'] = $dateFrom;
}
if ($dateTo) {
    $where[] = "o.order_date <= :date_to";
    $params[':date_to'] = $dateTo;
}

if ($search !== '') {
    $where[] = "(o.order_code LIKE :search OR c.full_name LIKE :search OR c.phone LIKE :search OR c.customer_code LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Summary Totals
$sumSql = "
    SELECT 
        COUNT(*) AS total_count,
        SUM(o.grand_total) AS total_invoiced,
        SUM(o.paid_amount) AS total_paid,
        SUM(o.due_amount) AS total_due
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

// Fetch Paginated Due Records
$sql = "
    SELECT o.*, c.full_name AS customer_name, c.customer_code, c.phone AS customer_phone, c.address AS customer_address,
           u.name AS created_by_name
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON o.created_by = u.id
    {$whereSql}
    ORDER BY o.due_amount DESC, o.order_date ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$dueOrders = $stmt->fetchAll();
?>

<!-- Header Actions & Breadcrumbs -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold text-dark m-0">Outstanding Due &amp; Receivables</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small mt-1">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/reports/index.php" class="text-decoration-none">Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Outstanding Due</li>
      </ol>
    </nav>
  </div>

  <div class="d-flex gap-2">
    <!-- Print Report Button -->
    <a href="<?= BASE_URL; ?>modules/reports/print.php?type=due&period=<?= urlencode($period); ?>&date_from=<?= urlencode($customFrom); ?>&date_to=<?= urlencode($customTo); ?>&q=<?= urlencode($search); ?>" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-sm">
      <i class="bi bi-printer-fill"></i> Print Due List
    </a>
    <a href="<?= BASE_URL; ?>modules/reports/index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
      <i class="bi bi-arrow-left"></i> Reports Hub
    </a>
  </div>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm mb-4 border-0">
  <div class="card-body p-3">
    <form method="GET" action="<?= BASE_URL; ?>modules/reports/due.php" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label for="period" class="form-label small text-muted fw-semibold mb-1">Time Period</label>
        <select class="form-select form-select-sm" id="period" name="period" onchange="toggleCustomDates(this.value)">
          <option value="all" <?= $period === 'all' ? 'selected' : ''; ?>>All Time (All Open Dues)</option>
          <option value="today" <?= $period === 'today' ? 'selected' : ''; ?>>Today</option>
          <option value="this_week" <?= $period === 'this_week' ? 'selected' : ''; ?>>This Week</option>
          <option value="this_month" <?= $period === 'this_month' ? 'selected' : ''; ?>>This Month</option>
          <option value="last_month" <?= $period === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
          <option value="this_year" <?= $period === 'this_year' ? 'selected' : ''; ?>>This Year</option>
          <option value="custom" <?= $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
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

      <div class="col-md-2">
        <label for="search" class="form-label small text-muted fw-semibold mb-1">Search Customer / Order</label>
        <input type="text" class="form-control form-control-sm" id="search" name="q" value="<?= e($search); ?>" placeholder="Name, Phone, ORD-...">
      </div>

      <div class="col-md-1 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary w-100" title="Filter Receivables">
          <i class="bi bi-funnel-fill"></i>
        </button>
        <?php if ($period !== 'all' || $search !== '' || !empty($customFrom) || !empty($customTo)): ?>
          <a href="<?= BASE_URL; ?>modules/reports/due.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
            <i class="bi bi-x-circle"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Summary Due Breakdown -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4">
    <div class="p-3 bg-white rounded border shadow-sm" style="border-left: 4px solid var(--danger) !important;">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Total Outstanding Due</div>
      <h3 class="fw-bold text-danger m-0 mt-1 font-monospace"><?= formatMoney($totals['total_due'] ?? 0); ?></h3>
      <small class="text-muted" style="font-size: 0.75rem;">Across <?= number_format($totalRecords); ?> pending invoices</small>
    </div>
  </div>

  <div class="col-6 col-md-4">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Total Invoiced Value</div>
      <h3 class="fw-bold text-dark m-0 mt-1 font-monospace"><?= formatMoney($totals['total_invoiced'] ?? 0); ?></h3>
      <small class="text-muted" style="font-size: 0.75rem;">Invoice value of due orders</small>
    </div>
  </div>

  <div class="col-6 col-md-4">
    <div class="p-3 bg-white rounded border shadow-sm" style="border-left: 4px solid var(--success) !important;">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Advance Collected</div>
      <h3 class="fw-bold text-success m-0 mt-1 font-monospace"><?= formatMoney($totals['total_paid'] ?? 0); ?></h3>
      <small class="text-success fw-semibold" style="font-size: 0.75rem;">
        <?= ($totals['total_invoiced'] ?? 0) > 0 ? round((($totals['total_paid'] ?? 0) / ($totals['total_invoiced'] ?? 1)) * 100, 1) : 0; ?>% collected so far
      </small>
    </div>
  </div>
</div>

<!-- Due Orders Data Table -->
<div class="card shadow-sm border-0 mb-4">
  <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <h6 class="m-0 fw-semibold text-dark">
        <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> Unsettled Customer Accounts
      </h6>
      <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><?= number_format($totalRecords); ?> Open Dues</span>
    </div>
    <small class="text-muted">Window: <strong><?= e($periodLabel); ?></strong></small>
  </div>

  <div class="table-responsive">
    <?php if (empty($dueOrders)): ?>
      <div class="p-5 text-center text-muted">
        <i class="bi bi-check-circle-fill fs-1 text-success mb-2 d-block"></i>
        <h6 class="text-success fw-bold">All Accounts Settled</h6>
        <p class="small mb-0">There are no outstanding customer due balances for the selected period.</p>
      </div>
    <?php else: ?>
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-3">Order Code</th>
            <th>Customer &amp; Contact</th>
            <th>Order Date</th>
            <th class="text-end">Grand Total</th>
            <th class="text-end">Advance Paid</th>
            <th class="text-end">Outstanding Due</th>
            <th class="text-center">Status</th>
            <th class="text-end pe-3">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dueOrders as $ord): ?>
            <tr>
              <td class="ps-3">
                <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $ord['id']; ?>" class="font-monospace fw-bold text-primary text-decoration-none">
                  <?= e($ord['order_code']); ?>
                </a>
              </td>
              <td>
                <div class="fw-semibold text-dark">
                  <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $ord['customer_id']; ?>" class="text-dark text-decoration-none hover-primary">
                    <?= e($ord['customer_name']); ?>
                  </a>
                </div>
                <div class="small text-muted font-monospace"><i class="bi bi-telephone me-1"></i><?= e($ord['customer_phone']); ?></div>
              </td>
              <td class="small text-dark"><?= date('M d, Y', strtotime($ord['order_date'])); ?></td>
              <td class="text-end font-monospace"><?= formatMoney($ord['grand_total']); ?></td>
              <td class="text-end font-monospace text-success"><?= formatMoney($ord['paid_amount']); ?></td>
              <td class="text-end font-monospace fw-bold text-danger fs-6">
                <?= formatMoney($ord['due_amount']); ?>
              </td>
              <td class="text-center">
                <?= getOrderStatusBadge($ord['status']); ?>
              </td>
              <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                  <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $ord['id']; ?>#payment-section" class="btn btn-outline-success" title="Collect Payment">
                    <i class="bi bi-cash-stack"></i>
                  </a>
                  <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $ord['id']; ?>" class="btn btn-outline-secondary" title="View Order">
                    <i class="bi bi-eye"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light font-monospace fw-bold">
          <tr>
            <td colspan="5" class="ps-3 text-uppercase font-sans-serif">Page Total Due Balance:</td>
            <td class="text-end text-danger fs-6">
              <?= formatMoney(array_reduce($dueOrders, fn($carry, $item) => $carry + (float)$item['due_amount'], 0)); ?>
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
            <a class="page-link" href="<?= BASE_URL; ?>modules/reports/due.php?<?= http_build_query($qParams); ?>">&laquo; Prev</a>
          </li>
          <?php for ($p = 1; $p <= $totalPages; $p++): $qParams['page'] = $p; ?>
            <li class="page-item <?= $p === $page ? 'active' : ''; ?>">
              <a class="page-link" href="<?= BASE_URL; ?>modules/reports/due.php?<?= http_build_query($qParams); ?>"><?= $p; ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
            <?php $qParams['page'] = $nextPage; ?>
            <a class="page-link" href="<?= BASE_URL; ?>modules/reports/due.php?<?= http_build_query($qParams); ?>">Next &raquo;</a>
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
