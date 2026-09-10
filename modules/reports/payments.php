<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Payment Collections & Receipts Report
 */

$pageTitle = 'Payment Collections Report';
require_once __DIR__ . '/../../includes/header.php';

// Authorization: Admin, Optician, Sales Staff
requireRole(['admin', 'optician', 'sales_staff']);

$pdo = getDbConnection();

// Filters and Pagination
$period       = trim($_GET['period'] ?? 'this_month');
$customFrom   = trim($_GET['date_from'] ?? '');
$customTo     = trim($_GET['date_to'] ?? '');
$methodFilter = trim($_GET['payment_method'] ?? 'all');
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
    $where[] = "pm.payment_date >= :date_from";
    $params[':date_from'] = $dateFrom;
}
if ($dateTo) {
    $where[] = "pm.payment_date <= :date_to";
    $params[':date_to'] = $dateTo;
}

if ($methodFilter !== '' && $methodFilter !== 'all') {
    $where[] = "pm.payment_method = :method";
    $params[':method'] = $methodFilter;
}

if ($search !== '') {
    $where[] = "(o.order_code LIKE :search OR c.full_name LIKE :search OR pm.receipt_number LIKE :search OR pm.transaction_reference LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Summary Totals
$sumSql = "
    SELECT 
        COUNT(*) AS total_count,
        SUM(pm.amount) AS total_amount,
        SUM(CASE WHEN LOWER(pm.payment_method) = 'cash' THEN pm.amount ELSE 0 END) AS cash_amount,
        SUM(CASE WHEN LOWER(pm.payment_method) = 'card' THEN pm.amount ELSE 0 END) AS card_amount,
        SUM(CASE WHEN LOWER(pm.payment_method) LIKE '%mobile%' THEN pm.amount ELSE 0 END) AS mobile_amount,
        SUM(CASE WHEN LOWER(pm.payment_method) LIKE '%bank%' OR LOWER(pm.payment_method) LIKE '%transfer%' THEN pm.amount ELSE 0 END) AS bank_amount
    FROM payments pm
    JOIN orders o ON pm.order_id = o.id
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

// Fetch Paginated Payment Records
$sql = "
    SELECT pm.*, o.order_code, c.id AS customer_id, c.full_name AS customer_name, c.phone AS customer_phone,
           u.name AS received_by_name
    FROM payments pm
    JOIN orders o ON pm.order_id = o.id
    JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON pm.received_by = u.id
    {$whereSql}
    ORDER BY pm.payment_date DESC, pm.id DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll();
?>

<!-- Header Actions & Breadcrumbs -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold text-dark m-0">Payment Collections Report</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small mt-1">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/reports/index.php" class="text-decoration-none">Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">Payments</li>
      </ol>
    </nav>
  </div>

  <div class="d-flex gap-2">
    <!-- Print Report Button -->
    <a href="<?= BASE_URL; ?>modules/reports/print.php?type=payments&period=<?= urlencode($period); ?>&date_from=<?= urlencode($customFrom); ?>&date_to=<?= urlencode($customTo); ?>&payment_method=<?= urlencode($methodFilter); ?>&q=<?= urlencode($search); ?>" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-sm">
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
    <form method="GET" action="<?= BASE_URL; ?>modules/reports/payments.php" class="row g-2 align-items-end">
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
        <label for="payment_method" class="form-label small text-muted fw-semibold mb-1">Payment Method</label>
        <select class="form-select form-select-sm" id="payment_method" name="payment_method">
          <option value="all" <?= $methodFilter === 'all' ? 'selected' : ''; ?>>All Methods</option>
          <option value="Cash" <?= $methodFilter === 'Cash' ? 'selected' : ''; ?>>Cash</option>
          <option value="Card" <?= $methodFilter === 'Card' ? 'selected' : ''; ?>>Card / POS</option>
          <option value="Mobile Banking" <?= $methodFilter === 'Mobile Banking' ? 'selected' : ''; ?>>Mobile Banking</option>
          <option value="Bank Transfer" <?= $methodFilter === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
          <option value="Other" <?= $methodFilter === 'Other' ? 'selected' : ''; ?>>Other</option>
        </select>
      </div>

      <div class="col-md-2">
        <label for="search" class="form-label small text-muted fw-semibold mb-1">Search</label>
        <input type="text" class="form-control form-control-sm" id="search" name="q" value="<?= e($search); ?>" placeholder="Order #, Customer, Ref #...">
      </div>

      <div class="col-md-1 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary w-100" title="Filter Collections">
          <i class="bi bi-funnel-fill"></i>
        </button>
        <?php if ($period !== 'this_month' || $methodFilter !== 'all' || $search !== '' || !empty($customFrom) || !empty($customTo)): ?>
          <a href="<?= BASE_URL; ?>modules/reports/payments.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
            <i class="bi bi-x-circle"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Summary Payment Breakdown -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="p-3 bg-white rounded border shadow-sm" style="border-left: 4px solid var(--success) !important;">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Total Collections</div>
      <h4 class="fw-bold text-success m-0 mt-1 font-monospace"><?= formatMoney($totals['total_amount'] ?? 0); ?></h4>
      <small class="text-muted" style="font-size: 0.75rem;"><?= number_format($totalRecords); ?> transactions</small>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Cash Receipts</div>
      <h4 class="fw-bold text-dark m-0 mt-1 font-monospace"><?= formatMoney($totals['cash_amount'] ?? 0); ?></h4>
      <small class="text-muted" style="font-size: 0.75rem;">Counter cash transactions</small>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Card / POS</div>
      <h4 class="fw-bold text-primary m-0 mt-1 font-monospace"><?= formatMoney($totals['card_amount'] ?? 0); ?></h4>
      <small class="text-muted" style="font-size: 0.75rem;">Debit/Credit card terminals</small>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="p-3 bg-white rounded border shadow-sm">
      <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Mobile Banking / Transfer</div>
      <h4 class="fw-bold text-dark m-0 mt-1 font-monospace"><?= formatMoney(($totals['mobile_amount'] ?? 0) + ($totals['bank_amount'] ?? 0)); ?></h4>
      <small class="text-muted" style="font-size: 0.75rem;">bKash, Nagad, Bank</small>
    </div>
  </div>
</div>

<!-- Payments Data Table -->
<div class="card shadow-sm border-0 mb-4">
  <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <h6 class="m-0 fw-semibold text-dark">
        <i class="bi bi-cash-stack me-2 text-success"></i> Payment Transaction Ledger
      </h6>
      <span class="badge bg-light text-dark border"><?= number_format($totalRecords); ?> Records</span>
    </div>
    <small class="text-muted">Window: <strong><?= e($periodLabel); ?></strong></small>
  </div>

  <div class="table-responsive">
    <?php if (empty($payments)): ?>
      <div class="p-5 text-center text-muted">
        <i class="bi bi-wallet2 fs-1 text-secondary mb-2 d-block"></i>
        <h6>No payment collections found</h6>
        <p class="small mb-0">No receipts match your selected filter window.</p>
      </div>
    <?php else: ?>
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-3">Payment Date</th>
            <th>Order Code</th>
            <th>Customer</th>
            <th>Payment Method</th>
            <th>Reference #</th>
            <th>Received By</th>
            <th class="text-end pe-3">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $pm): ?>
            <tr>
              <td class="ps-3 text-dark small fw-medium">
                <?= date('M d, Y', strtotime($pm['payment_date'])); ?>
              </td>
              <td>
                <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $pm['order_id']; ?>" class="font-monospace fw-bold text-primary text-decoration-none">
                  <?= e($pm['order_code']); ?>
                </a>
              </td>
              <td>
                <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $pm['customer_id']; ?>" class="fw-semibold text-dark text-decoration-none hover-primary">
                  <?= e($pm['customer_name']); ?>
                </a>
                <div class="small text-muted font-monospace"><?= e($pm['customer_phone']); ?></div>
              </td>
              <td>
                <span class="badge bg-light text-dark border"><?= e($pm['payment_method']); ?></span>
              </td>
              <td class="font-monospace small text-muted">
                <?= !empty($pm['transaction_reference']) ? e($pm['transaction_reference']) : (!empty($pm['receipt_number']) ? e($pm['receipt_number']) : '&mdash;'); ?>
              </td>
              <td class="small text-muted">
                <?= !empty($pm['received_by_name']) ? e($pm['received_by_name']) : 'Staff'; ?>
              </td>
              <td class="text-end pe-3 font-monospace fw-bold text-success fs-6">
                <?= formatMoney($pm['amount']); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light font-monospace fw-bold">
          <tr>
            <td colspan="6" class="ps-3 text-uppercase font-sans-serif">Page Total Collected:</td>
            <td class="text-end pe-3 text-success fs-6">
              <?= formatMoney(array_reduce($payments, fn($carry, $item) => $carry + (float)$item['amount'], 0)); ?>
            </td>
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
            <a class="page-link" href="<?= BASE_URL; ?>modules/reports/payments.php?<?= http_build_query($qParams); ?>">&laquo; Prev</a>
          </li>
          <?php for ($p = 1; $p <= $totalPages; $p++): $qParams['page'] = $p; ?>
            <li class="page-item <?= $p === $page ? 'active' : ''; ?>">
              <a class="page-link" href="<?= BASE_URL; ?>modules/reports/payments.php?<?= http_build_query($qParams); ?>"><?= $p; ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
            <?php $qParams['page'] = $nextPage; ?>
            <a class="page-link" href="<?= BASE_URL; ?>modules/reports/payments.php?<?= http_build_query($qParams); ?>">Next &raquo;</a>
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
