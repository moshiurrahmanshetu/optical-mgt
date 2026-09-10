<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Customer Growth & Demographics Report
 */

$pageTitle = 'Customers Report';
require_once __DIR__ . '/../../includes/header.php';

// Authorization: Admin, Optician, Sales Staff
requireRole(['admin', 'optician', 'sales_staff']);

$pdo = getDbConnection();

// Filters and Pagination
$period       = trim($_GET['period'] ?? 'this_month');
$customFrom   = trim($_GET['date_from'] ?? '');
$customTo     = trim($_GET['date_to'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$sortBy       = trim($_GET['sort'] ?? 'created_desc');
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
    $where[] = "c.created_at >= :date_from";
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[] = "c.created_at <= :date_to";
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

if ($statusFilter === 'active' || $statusFilter === 'inactive') {
    $where[] = "c.status = :status";
    $params[':status'] = $statusFilter;
}

if ($search !== '') {
    $where[] = "(c.customer_code LIKE :search OR c.full_name LIKE :search OR c.phone LIKE :search OR c.email LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Summary Metrics for Period
$sumSql = "
    SELECT 
        COUNT(*) AS period_count,
        SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN c.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count
    FROM customers c
    {$whereSql}
";
$sumStmt = $pdo->prepare($sumSql);
foreach ($params as $k => $v) {
    $sumStmt->bindValue($k, $v);
}
$sumStmt->execute();
$sumMetrics = $sumStmt->fetch();

// Total Overall Customers (All Time)
$allTimeCount = (int) $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();

$totalRecords = (int) ($sumMetrics['period_count'] ?? 0);
$totalPages   = max(1, (int) ceil($totalRecords / $limit));

// Sorting map
$orderBy = "c.created_at DESC";
if ($sortBy === 'spending_desc') {
    $orderBy = "total_spent DESC, c.id DESC";
} elseif ($sortBy === 'orders_desc') {
    $orderBy = "total_orders DESC, c.id DESC";
} elseif ($sortBy === 'name_asc') {
    $orderBy = "c.full_name ASC";
} elseif ($sortBy === 'due_desc') {
    $orderBy = "total_due DESC, c.id DESC";
}

// Fetch Paginated Customers with Aggregated Metrics
$custSql = "
    SELECT 
        c.id,
        c.customer_code,
        c.full_name,
        c.phone,
        c.email,
        c.gender,
        c.created_at,
        c.status,
        (SELECT COUNT(*) FROM prescriptions p WHERE p.customer_id = c.id) AS total_prescriptions,
        (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.status != 'cancelled') AS total_orders,
        COALESCE((SELECT SUM(o.grand_total) FROM orders o WHERE o.customer_id = c.id AND o.status != 'cancelled'), 0) AS total_spent,
        COALESCE((SELECT SUM(o.due_amount) FROM orders o WHERE o.customer_id = c.id AND o.status != 'cancelled'), 0) AS total_due
    FROM customers c
    {$whereSql}
    ORDER BY {$orderBy}
    LIMIT :limit OFFSET :offset
";

$custStmt = $pdo->prepare($custSql);
foreach ($params as $k => $v) {
    $custStmt->bindValue($k, $v);
}
$custStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$custStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$custStmt->execute();
$customers = $custStmt->fetchAll();

// Build query string for export/print
$filterParams = $_GET;
unset($filterParams['page']);
$printQuery = http_build_query(array_merge($filterParams, ['type' => 'customers']));
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= baseUrl('modules/reports/index.php') ?>" class="text-decoration-none">Reports</a></li>
                <li class="breadcrumb-item active" aria-current="page">Customers Report</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold text-dark mb-0">Customer &amp; Patient Report</h1>
        <p class="text-muted small mb-0">Registration growth, spending history, and patient demographics for <?= e($periodLabel) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= baseUrl('modules/reports/print.php?' . $printQuery) ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i> Print / PDF
        </a>
        <a href="<?= baseUrl('modules/customers/create.php') ?>" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i> New Customer
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Registration Period</label>
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
                <label class="form-label small fw-semibold text-muted mb-1">Account Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Sort Results By</label>
                <select name="sort" class="form-select form-select-sm">
                    <option value="created_desc" <?= $sortBy === 'created_desc' ? 'selected' : '' ?>>Newest Registered</option>
                    <option value="spending_desc" <?= $sortBy === 'spending_desc' ? 'selected' : '' ?>>Highest Lifetime Spend</option>
                    <option value="orders_desc" <?= $sortBy === 'orders_desc' ? 'selected' : '' ?>>Most Orders Placed</option>
                    <option value="due_desc" <?= $sortBy === 'due_desc' ? 'selected' : '' ?>>Highest Due Balance</option>
                    <option value="name_asc" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Customer Name (A-Z)</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Search Patient</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Name / Phone / Code / Email" value="<?= e($search) ?>">
            </div>

            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= baseUrl('modules/reports/customers.php') ?>" class="btn btn-sm btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">New in Period</div>
                        <h3 class="fw-bold text-dark my-1"><?= number_format($totalRecords) ?></h3>
                        <div class="text-muted small">Registered in <?= e($periodLabel) ?></div>
                    </div>
                    <div class="bg-primary text-white rounded p-3">
                        <i class="bi bi-person-plus fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Active Clients</div>
                        <h3 class="fw-bold text-success my-1"><?= number_format((int)($sumMetrics['active_count'] ?? 0)) ?></h3>
                        <div class="text-muted small">Current active status</div>
                    </div>
                    <div class="bg-success text-white rounded p-3">
                        <i class="bi bi-person-check fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Inactive Clients</div>
                        <h3 class="fw-bold text-muted my-1"><?= number_format((int)($sumMetrics['inactive_count'] ?? 0)) ?></h3>
                        <div class="text-muted small">Deactivated records</div>
                    </div>
                    <div class="bg-secondary text-white rounded p-3">
                        <i class="bi bi-person-dash fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Total Client Base</div>
                        <h3 class="fw-bold text-dark my-1"><?= number_format($allTimeCount) ?></h3>
                        <div class="text-muted small">All-time registered</div>
                    </div>
                    <div class="bg-dark text-white rounded p-3">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Customer Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title fw-bold text-dark mb-0">Customer Records (<?= number_format($totalRecords) ?>)</h5>
        <span class="badge bg-light text-dark border">Showing page <?= $page ?> of <?= $totalPages ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Code</th>
                        <th>Customer Name</th>
                        <th>Phone / Email</th>
                        <th class="text-center">Registered</th>
                        <th class="text-center">Prescriptions</th>
                        <th class="text-center">Orders</th>
                        <th class="text-end">Lifetime Spent</th>
                        <th class="text-end">Current Due</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                                No customer records found matching the specified filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td class="ps-3 fw-semibold text-muted">
                                    <?= e($c['customer_code']) ?>
                                </td>
                                <td>
                                    <a href="<?= baseUrl('modules/customers/view.php?id=' . $c['id']) ?>" class="fw-bold text-dark text-decoration-none">
                                        <?= e($c['full_name']) ?>
                                    </a>
                                    <?php if (!empty($c['gender'])): ?>
                                        <span class="badge bg-light text-secondary border ms-1"><?= ucfirst(e($c['gender'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($c['phone']) ?></div>
                                    <div class="text-muted small"><?= e($c['email'] ?: 'No email') ?></div>
                                </td>
                                <td class="text-center small text-muted">
                                    <?= formatDate($c['created_at']) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-primary border"><?= (int)$c['total_prescriptions'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= (int)$c['total_orders'] ?></span>
                                </td>
                                <td class="text-end fw-bold text-dark font-monospace">
                                    <?= formatMoney((float)$c['total_spent']) ?>
                                </td>
                                <td class="text-end fw-semibold font-monospace <?= (float)$c['total_due'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                    <?= formatMoney((float)$c['total_due']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($c['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="<?= baseUrl('modules/customers/view.php?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="View Patient Profile">
                                        <i class="bi bi-person"></i>
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
                Showing <?= min($totalRecords, $offset + 1) ?> to <?= min($totalRecords, $offset + count($customers)) ?> of <?= number_format($totalRecords) ?> customers
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
