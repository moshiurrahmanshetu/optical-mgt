<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Prescriptions & Clinical Examination Report
 */

$pageTitle = 'Prescriptions Report';
require_once __DIR__ . '/../../includes/header.php';

// Authorization: Strictly Admin & Optician (Clinical Data)
requireRole(['admin', 'optician']);

$pdo = getDbConnection();

// Fetch Opticians / Doctors for Filter Dropdown
$opticians = $pdo->query("SELECT id, name, username FROM users WHERE role_id IN (1, 2) AND status = 'active' ORDER BY name ASC")->fetchAll();

// Filters and Pagination
$period     = trim($_GET['period'] ?? 'this_month');
$customFrom = trim($_GET['date_from'] ?? '');
$customTo   = trim($_GET['date_to'] ?? '');
$docFilter  = trim($_GET['doctor_id'] ?? 'all');
$search     = trim($_GET['q'] ?? '');
$page       = max(1, (int) ($_GET['page'] ?? 1));
$limit      = 15;
$offset     = ($page - 1) * $limit;

$dateInfo = parseDatePeriod($period, $customFrom, $customTo);
$dateFrom = $dateInfo['from'];
$dateTo   = $dateInfo['to'];
$periodLabel = $dateInfo['label'];

// Build SQL Query
$where = [];
$params = [];

if ($dateFrom) {
    $where[] = "p.prescription_date >= :date_from";
    $params[':date_from'] = $dateFrom;
}
if ($dateTo) {
    $where[] = "p.prescription_date <= :date_to";
    $params[':date_to'] = $dateTo;
}

if ($docFilter !== '' && $docFilter !== 'all') {
    $where[] = "p.created_by = :doc_id";
    $params[':doc_id'] = (int) $docFilter;
}

if ($search !== '') {
    $where[] = "(c.full_name LIKE :search OR c.phone LIKE :search OR c.customer_code LIKE :search OR p.doctor_name LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Overall Aggregates
$sumSql = "
    SELECT 
        COUNT(*) AS total_prescriptions,
        COUNT(DISTINCT p.customer_id) AS unique_patients,
        SUM(CASE WHEN p.prescription_date = CURRENT_DATE() THEN 1 ELSE 0 END) AS exams_today
    FROM prescriptions p
    JOIN customers c ON p.customer_id = c.id
    {$whereSql}
";
$sumStmt = $pdo->prepare($sumSql);
foreach ($params as $k => $v) {
    $sumStmt->bindValue($k, $v);
}
$sumStmt->execute();
$totals = $sumStmt->fetch();

$totalRecords = (int) ($totals['total_prescriptions'] ?? 0);
$totalPages   = max(1, (int) ceil($totalRecords / $limit));

// Fetch Paginated Prescriptions
$rxSql = "
    SELECT 
        p.id,
        p.prescription_date,
        p.right_sph, p.right_cyl, p.right_axis, p.right_add, p.right_pd,
        p.left_sph, p.left_cyl, p.left_axis, p.left_add, p.left_pd,
        p.doctor_name,
        c.customer_code,
        c.full_name AS customer_name,
        c.phone AS customer_phone,
        u.name AS created_by_name
    FROM prescriptions p
    JOIN customers c ON p.customer_id = c.id
    LEFT JOIN users u ON p.created_by = u.id
    {$whereSql}
    ORDER BY p.prescription_date DESC, p.id DESC
    LIMIT :limit OFFSET :offset
";

$rxStmt = $pdo->prepare($rxSql);
foreach ($params as $k => $v) {
    $rxStmt->bindValue($k, $v);
}
$rxStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$rxStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$rxStmt->execute();
$prescriptions = $rxStmt->fetchAll();

// Build query string for export/print
$filterParams = $_GET;
unset($filterParams['page']);
$printQuery = http_build_query(array_merge($filterParams, ['type' => 'prescriptions']));
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= baseUrl('modules/reports/index.php') ?>" class="text-decoration-none">Reports</a></li>
                <li class="breadcrumb-item active" aria-current="page">Prescriptions Report</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold text-dark mb-0">Prescriptions &amp; Clinical Exam Log</h1>
        <p class="text-muted small mb-0">Refraction test logs, optometrist examinations, and patient optical records for <?= e($periodLabel) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= baseUrl('modules/reports/print.php?' . $printQuery) ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i> Print / PDF
        </a>
        <a href="<?= baseUrl('modules/prescriptions/create.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> New Prescription
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Exam Period</label>
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

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Recorded By Staff</label>
                <select name="doctor_id" class="form-select form-select-sm">
                    <option value="all" <?= $docFilter === 'all' ? 'selected' : '' ?>>All Practitioners</option>
                    <?php foreach ($opticians as $opt): ?>
                        <option value="<?= $opt['id'] ?>" <?= $docFilter == $opt['id'] ? 'selected' : '' ?>>
                            <?= e($opt['name']) ?> (<?= e($opt['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Search Records</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Patient / Phone / Doctor" value="<?= e($search) ?>">
            </div>

            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= baseUrl('modules/reports/prescriptions.php') ?>" class="btn btn-sm btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Clinical Examinations</div>
                        <h3 class="fw-bold text-dark my-1"><?= number_format($totalRecords) ?></h3>
                        <div class="text-muted small">Recorded in <?= e($periodLabel) ?></div>
                    </div>
                    <div class="bg-primary text-white rounded p-3">
                        <i class="bi bi-eye fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Unique Patients</div>
                        <h3 class="fw-bold text-success my-1"><?= number_format((int)($totals['unique_patients'] ?? 0)) ?></h3>
                        <div class="text-muted small">Distinct individuals examined</div>
                    </div>
                    <div class="bg-success text-white rounded p-3">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold">Exams Conducted Today</div>
                        <h3 class="fw-bold text-dark my-1"><?= number_format((int)($totals['exams_today'] ?? 0)) ?></h3>
                        <div class="text-muted small">Today's clinical workflow</div>
                    </div>
                    <div class="bg-info text-white rounded p-3">
                        <i class="bi bi-calendar-check fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title fw-bold text-dark mb-0">Prescription Records (<?= number_format($totalRecords) ?>)</h5>
        <span class="badge bg-light text-dark border">Showing page <?= $page ?> of <?= $totalPages ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Rx #</th>
                        <th>Exam Date</th>
                        <th>Patient</th>
                        <th>Examined By</th>
                        <th>Right Eye (OD)</th>
                        <th>Left Eye (OS)</th>
                        <th class="text-center">PD</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prescriptions)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-file-earmark-medical fs-1 d-block mb-2 text-secondary"></i>
                                No prescription examination records found matching the specified filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prescriptions as $rx): ?>
                            <tr>
                                <td class="ps-3">
                                    <a href="<?= baseUrl('modules/prescriptions/view.php?id=' . $rx['id']) ?>" class="fw-bold text-primary text-decoration-none">
                                        #<?= str_pad($rx['id'], 5, '0', STR_PAD_LEFT) ?>
                                    </a>
                                </td>
                                <td class="small fw-semibold text-dark">
                                    <?= formatDate($rx['prescription_date']) ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($rx['customer_name']) ?></div>
                                    <div class="text-muted small"><?= e($rx['customer_phone']) ?></div>
                                </td>
                                <td>
                                    <div class="text-dark"><?= e($rx['doctor_name'] ?: ($rx['created_by_name'] ?: 'Optometrist')) ?></div>
                                </td>
                                <td class="small font-monospace">
                                    SPH: <?= formatOpticalPower($rx['right_sph']) ?><br>
                                    CYL: <?= formatOpticalPower($rx['right_cyl']) ?> AX: <?= e($rx['right_axis'] ?? '0') ?>&deg;
                                </td>
                                <td class="small font-monospace">
                                    SPH: <?= formatOpticalPower($rx['left_sph']) ?><br>
                                    CYL: <?= formatOpticalPower($rx['left_cyl']) ?> AX: <?= e($rx['left_axis'] ?? '0') ?>&deg;
                                </td>
                                <td class="text-center fw-semibold">
                                    <?= e($rx['right_pd'] ? ($rx['right_pd'] . '/' . ($rx['left_pd'] ?? '')) : '—') ?>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="<?= baseUrl('modules/prescriptions/view.php?id=' . $rx['id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="View Prescription Details">
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
                Showing <?= min($totalRecords, $offset + 1) ?> to <?= min($totalRecords, $offset + count($prescriptions)) ?> of <?= number_format($totalRecords) ?> prescriptions
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
