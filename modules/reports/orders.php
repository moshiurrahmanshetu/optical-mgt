<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Order Status & Workflow Distribution Report
 */

$pageTitle = 'Orders Report';
require_once __DIR__ . '/../../includes/header.php';

// Authorization: Admin, Optician, Sales Staff
requireRole(['admin', 'optician', 'sales_staff']);

$pdo = getDbConnection();

// Filters and Pagination
$period        = trim($_GET['period'] ?? 'this_month');
$customFrom    = trim($_GET['date_from'] ?? '');
$customTo      = trim($_GET['date_to'] ?? '');
$statusFilter  = trim($_GET['status'] ?? 'all');
$paymentFilter = trim($_GET['payment_status'] ?? 'all');
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int) ($_GET['page'] ?? 1));
$limit         = 15;
$offset        = ($page - 1) * $limit;

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

if ($paymentFilter !== '' && $paymentFilter !== 'all') {
    if (in_array($paymentFilter, ['unpaid', 'partial', 'paid'], true)) {
        $where[] = "o.payment_status = :payment_status";
        $params[':payment_status'] = $paymentFilter;
    }
}

if ($search !== '') {
    $where[] = "(o.order_code LIKE :search OR c.full_name LIKE :search OR c.phone LIKE :search OR c.customer_code LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Overall Summary & Status Counts in this period
$statusSql = "
    SELECT 
        COUNT(*) AS total_count,
        SUM(o.grand_total) AS total_value,
        SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) AS count_pending,
        SUM(CASE WHEN o.status = 'pending' THEN o.grand_total ELSE 0 END) AS val_pending,
        SUM(CASE WHEN o.status = 'confirmed' THEN 1 ELSE 0 END) AS count_confirmed,
        SUM(CASE WHEN o.status = 'confirmed' THEN o.grand_total ELSE 0 END) AS val_confirmed,
        SUM(CASE WHEN o.status = 'processing' THEN 1 ELSE 0 END) AS count_processing,
        SUM(CASE WHEN o.status = 'processing' THEN o.grand_total ELSE 0 END) AS val_processing,
        SUM(CASE WHEN o.status = 'ready' THEN 1 ELSE 0 END) AS count_ready,
        SUM(CASE WHEN o.status = 'ready' THEN o.grand_total ELSE 0 END) AS val_ready,
        SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) AS count_delivered,
        SUM(CASE WHEN o.status = 'delivered' THEN o.grand_total ELSE 0 END) AS val_delivered,
        SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS count_cancelled,
        SUM(CASE WHEN o.status = 'cancelled' THEN o.grand_total ELSE 0 END) AS val_cancelled
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    {$whereSql}
";
$statusStmt = $pdo->prepare($statusSql);
foreach ($params as $k => $v) {
    $statusStmt->bindValue($k, $v);
}
$statusStmt->execute();
$statusSummary = $statusStmt->fetch();

$totalRecords = (int) ($statusSummary['total_count'] ?? 0);
$totalPages   = max(1, (int) ceil($totalRecords / $limit));

// Fetch Paginated Records
$orderSql = "
    SELECT 
        o.id,
        o.order_code,
        o.order_date,
        o.delivery_date,
        o.status,
        o.payment_status,
        o.grand_total,
        o.paid_amount,
        o.due_amount,
        c.customer_code,
        c.full_name AS customer_name,
        c.phone AS customer_phone,
        u.full_name AS created_by_name,
        (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    JOIN users u ON o.created_by = u.id
    {$whereSql}
    ORDER BY o.order_date DESC, o.id DESC
    LIMIT :limit OFFSET :offset
";

$orderStmt = $pdo->prepare($orderSql);
foreach ($params as $k => $v) {
    $orderStmt->bindValue($k, $v);
}
$orderStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$orderStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$orderStmt->execute();
$orders = $orderStmt->fetchAll();

// Build query string for export/print
$filterParams = $_GET;
unset($filterParams['page']);
$printQuery = http_build_query(array_merge($filterParams, ['type' => 'orders']));
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= baseUrl('modules/reports/index.php') ?>" class="text-decoration-none">Reports</a></li>
                <li class="breadcrumb-item active" aria-current="page">Orders Report</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold text-dark mb-0">Order Workflow & Status Report</h1>
        <p class="text-muted small mb-0">Distribution, lifecycle stages, and completion rates for <?= e($periodLabel) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= baseUrl('modules/reports/print.php?' . $printQuery) ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i> Print / PDF
        </a>
        <a href="<?= baseUrl('modules/orders/index.php') ?>" class="btn btn-primary">
            <i class="bi bi-cart me-1"></i> Manage Orders
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Time Period</label>
                <select name="period" id="periodSelect" class="form-select form-select-sm" onchange="toggleCustomDates(this.value)">
                    <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= $period === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="this_week" <?= $period === 'this_week' ? 'selected' : '' ?>>This Week</option>
                    <option value="this_month" <?= $period === 'this_month' ? 'selected' : '' ?>>This Month</option>
                    <option value="last_month" <?= $period === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                    <option value="this_year" <?= $period === 'this_year' ? 'selected' : '' ?>>This Year</option>
                    <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>All Time</option>
                    <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>
            </div>

            <div class="col-md-2 custom-date-field" style="display: <?= $period === 'custom' ? 'block' : 'none' ?>;">
                <label class="form-label small fw-semibold text-muted mb-1">From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($customFrom) ?>">
            </div>

            <div class="col-md-2 custom-date-field" style="display: <?= $period === 'custom' ? 'block' : 'none' ?>;">
                <label class="form-label small fw-semibold text-muted mb-1">To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($customTo) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Order Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : '' ?>>Ready</option>
                    <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Payment Status</label>
                <select name="payment_status" class="form-select form-select-sm">
                    <option value="all" <?= $paymentFilter === 'all' ? 'selected' : '' ?>>All Payments</option>
                    <option value="paid" <?= $paymentFilter === 'paid' ? 'selected' : '' ?>>Fully Paid</option>
                    <option value="partial" <?= $paymentFilter === 'partial' ? 'selected' : '' ?>>Partially Paid</option>
                    <option value="unpaid" <?= $paymentFilter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Order / Customer / Phone" value="<?= e($search) ?>">
            </div>

            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= baseUrl('modules/reports/orders.php') ?>" class="btn btn-sm btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Workflow Status Overview Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-secondary">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold">Pending</div>
                <h4 class="fw-bold text-dark my-1"><?= number_format((int)($statusSummary['count_pending'] ?? 0)) ?></h4>
                <div class="text-muted small"><?= formatCurrency((float)($statusSummary['val_pending'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-info">
            <div class="card-body p-3">
                <div class="text-info small fw-semibold">Confirmed</div>
                <h4 class="fw-bold text-dark my-1"><?= number_format((int)($statusSummary['count_confirmed'] ?? 0)) ?></h4>
                <div class="text-muted small"><?= formatCurrency((float)($statusSummary['val_confirmed'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body p-3">
                <div class="text-primary small fw-semibold">Processing (Lab)</div>
                <h4 class="fw-bold text-dark my-1"><?= number_format((int)($statusSummary['count_processing'] ?? 0)) ?></h4>
                <div class="text-muted small"><?= formatCurrency((float)($statusSummary['val_processing'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body p-3">
                <div class="text-warning small fw-semibold">Ready for Pickup</div>
                <h4 class="fw-bold text-dark my-1"><?= number_format((int)($statusSummary['count_ready'] ?? 0)) ?></h4>
                <div class="text-muted small"><?= formatCurrency((float)($statusSummary['val_ready'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body p-3">
                <div class="text-success small fw-semibold">Delivered</div>
                <h4 class="fw-bold text-dark my-1"><?= number_format((int)($statusSummary['count_delivered'] ?? 0)) ?></h4>
                <div class="text-muted small"><?= formatCurrency((float)($statusSummary['val_delivered'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
            <div class="card-body p-3">
                <div class="text-danger small fw-semibold">Cancelled</div>
                <h4 class="fw-bold text-dark my-1"><?= number_format((int)($statusSummary['count_cancelled'] ?? 0)) ?></h4>
                <div class="text-muted small"><?= formatCurrency((float)($statusSummary['val_cancelled'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title fw-bold text-dark mb-0">Order Records (<?= number_format($totalRecords) ?> total)</h5>
        <span class="badge bg-light text-dark border">Showing page <?= $page ?> of <?= $totalPages ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Order Code</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Grand Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Due</th>
                        <th class="text-center">Workflow Status</th>
                        <th class="text-center">Payment</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                No order records found matching the specified period or filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $ord): ?>
                            <tr>
                                <td class="ps-3">
                                    <a href="<?= baseUrl('modules/orders/view.php?id=' . $ord['id']) ?>" class="fw-bold text-primary text-decoration-none">
                                        <?= e($ord['order_code']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= formatDate($ord['order_date']) ?></div>
                                    <?php if (!empty($ord['delivery_date'])): ?>
                                        <div class="text-muted" style="font-size: 0.75rem;">Due: <?= formatDate($ord['delivery_date']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($ord['customer_name']) ?></div>
                                    <div class="text-muted small"><?= e($ord['customer_phone']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= (int)$ord['item_count'] ?> item(s)</span>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    <?= formatCurrency((float)$ord['grand_total']) ?>
                                </td>
                                <td class="text-end text-success fw-semibold">
                                    <?= formatCurrency((float)$ord['paid_amount']) ?>
                                </td>
                                <td class="text-end fw-semibold <?= (float)$ord['due_amount'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                    <?= formatCurrency((float)$ord['due_amount']) ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $stBadge = 'bg-secondary';
                                    if ($ord['status'] === 'confirmed') $stBadge = 'bg-info text-dark';
                                    elseif ($ord['status'] === 'processing') $stBadge = 'bg-primary';
                                    elseif ($ord['status'] === 'ready') $stBadge = 'bg-warning text-dark';
                                    elseif ($ord['status'] === 'delivered') $stBadge = 'bg-success';
                                    elseif ($ord['status'] === 'cancelled') $stBadge = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $stBadge ?> text-capitalize px-2 py-1">
                                        <?= e($ord['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $payBadge = 'bg-secondary';
                                    if ($ord['payment_status'] === 'paid') $payBadge = 'bg-success';
                                    elseif ($ord['payment_status'] === 'partial') $payBadge = 'bg-warning text-dark';
                                    elseif ($ord['payment_status'] === 'unpaid') $payBadge = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $payBadge ?> text-capitalize px-2 py-1">
                                        <?= e($ord['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="<?= baseUrl('modules/orders/view.php?id=' . $ord['id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="View Order Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Footer -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <div class="small text-muted">
                Showing <?= min($totalRecords, $offset + 1) ?> to <?= min($totalRecords, $offset + count($orders)) ?> of <?= number_format($totalRecords) ?> orders
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleCustomDates(period) {
    const customFields = document.querySelectorAll('.custom-date-field');
    customFields.forEach(el => {
        el.style.display = (period === 'custom') ? 'block' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
