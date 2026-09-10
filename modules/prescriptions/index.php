<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Prescription Management - Listing Page with Search & Pagination
 */

$pageTitle = 'Prescription Management';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDbConnection();
$canManage = hasRole(['admin', 'optician']);

// Query Parameters
$search = trim($_GET['search'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

// Build WHERE clause
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(c.full_name LIKE :search OR c.customer_code LIKE :search OR c.phone LIKE :search OR p.doctor_name LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($dateFilter)) {
    $whereClauses[] = "p.prescription_date = :date_filter";
    $params['date_filter'] = $dateFilter;
}

$whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

// Count Total Filtered Records
$countSql = "
    SELECT COUNT(*)
    FROM prescriptions p
    JOIN customers c ON p.customer_id = c.id
" . $whereSql;

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();

// Calculate Pagination
$totalPages = max(1, (int) ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Fetch Prescriptions
$sql = "
    SELECT p.*, c.customer_code, c.full_name AS customer_name, c.phone AS customer_phone,
           u.name AS created_by_name
    FROM prescriptions p
    JOIN customers c ON p.customer_id = c.id
    LEFT JOIN users u ON p.created_by = u.id
    " . $whereSql . "
    ORDER BY p.prescription_date DESC, p.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$prescriptions = $stmt->fetchAll();

// Pagination range
$fromRecord = $totalRecords > 0 ? $offset + 1 : 0;
$toRecord = min($offset + $perPage, $totalRecords);
?>

<!-- Header Actions & Breadcrumb -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold text-dark m-0">Prescriptions</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Prescriptions</li>
      </ol>
    </nav>
  </div>
  <?php if ($canManage): ?>
    <div>
      <a href="<?= BASE_URL; ?>modules/prescriptions/create.php" class="btn btn-primary d-inline-flex align-items-center gap-1">
        <i class="bi bi-file-earmark-plus-fill"></i> New Prescription (Rx)
      </a>
    </div>
  <?php endif; ?>
</div>

<!-- Search & Filter Bar -->
<div class="card shadow-sm mb-4">
  <div class="card-body p-3">
    <form method="GET" action="<?= BASE_URL; ?>modules/prescriptions/index.php" class="row g-2 align-items-center">
      <div class="col-md-6 col-lg-5">
        <div class="input-group">
          <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control border-start-0 ps-0" name="search" value="<?= e($search); ?>" placeholder="Search by customer name, code, phone, doctor...">
        </div>
      </div>
      
      <div class="col-sm-6 col-md-3 col-lg-3">
        <input type="date" class="form-control" name="date" value="<?= e($dateFilter); ?>" placeholder="Filter by date">
      </div>

      <div class="col-sm-6 col-md-3 col-lg-4 d-flex gap-2">
        <button type="submit" class="btn btn-secondary px-3">Filter</button>
        <?php if (!empty($search) || !empty($dateFilter)): ?>
          <a href="<?= BASE_URL; ?>modules/prescriptions/index.php" class="btn btn-outline-secondary px-3" title="Clear Filters">
            <i class="bi bi-x-circle me-1"></i> Reset
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Prescriptions Table Card -->
<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
    <h6 class="m-0 fw-semibold text-dark">
      <i class="bi bi-file-earmark-medical me-2 text-primary"></i> Prescription Records
    </h6>
    <span class="badge bg-light text-dark border"><?= $totalRecords; ?> Total</span>
  </div>

  <?php if (empty($prescriptions)): ?>
    <div class="card-body text-center py-5">
      <div class="text-muted mb-3">
        <i class="bi bi-file-earmark-x fs-1 text-secondary"></i>
      </div>
      <?php if (!empty($search) || !empty($dateFilter)): ?>
        <h5 class="fw-semibold text-dark">No matching prescriptions found</h5>
        <p class="text-muted small mb-3">No optical prescription records match your current filter criteria.</p>
        <a href="<?= BASE_URL; ?>modules/prescriptions/index.php" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
      <?php else: ?>
        <h5 class="fw-semibold text-dark">No prescriptions recorded yet</h5>
        <p class="text-muted small mb-3">Start by recording an eye examination prescription for a registered customer.</p>
        <?php if ($canManage): ?>
          <a href="<?= BASE_URL; ?>modules/prescriptions/create.php" class="btn btn-primary btn-sm">
            <i class="bi bi-file-earmark-plus-fill me-1"></i> Add Prescription
          </a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width: 80px;">Rx #</th>
            <th>Customer</th>
            <th>Rx Date</th>
            <th>Right Eye (OD)</th>
            <th>Left Eye (OS)</th>
            <th>Doctor / Optician</th>
            <th>Staff</th>
            <th class="text-end" style="width: 140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($prescriptions as $p): ?>
            <tr>
              <td>
                <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                  #<?= $p['id']; ?>
                </span>
              </td>
              <td>
                <div>
                  <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $p['customer_id']; ?>" class="fw-semibold text-dark text-decoration-none">
                    <?= e($p['customer_name']); ?>
                  </a>
                </div>
                <div class="small">
                  <span class="badge bg-light text-primary border font-monospace" style="font-size: 0.7rem;"><?= e($p['customer_code']); ?></span>
                  <span class="text-muted ms-1" style="font-size: 0.75rem;"><?= e($p['customer_phone']); ?></span>
                </div>
              </td>
              <td class="text-dark small fw-medium">
                <?= date('M d, Y', strtotime($p['prescription_date'])); ?>
              </td>
              <td>
                <span class="badge bg-light text-dark border small font-monospace">
                  SPH: <?= formatOpticalPower($p['right_sph']); ?>
                  <?php if ($p['right_cyl'] !== null && $p['right_cyl'] != 0): ?>
                    | CYL: <?= formatOpticalPower($p['right_cyl']); ?>
                  <?php endif; ?>
                  <?php if (!empty($p['right_axis'])): ?>
                    x <?= $p['right_axis']; ?>&deg;
                  <?php endif; ?>
                </span>
              </td>
              <td>
                <span class="badge bg-light text-dark border small font-monospace">
                  SPH: <?= formatOpticalPower($p['left_sph']); ?>
                  <?php if ($p['left_cyl'] !== null && $p['left_cyl'] != 0): ?>
                    | CYL: <?= formatOpticalPower($p['left_cyl']); ?>
                  <?php endif; ?>
                  <?php if (!empty($p['left_axis'])): ?>
                    x <?= $p['left_axis']; ?>&deg;
                  <?php endif; ?>
                </span>
              </td>
              <td class="small text-dark">
                <?= !empty($p['doctor_name']) ? e($p['doctor_name']) : '<span class="text-muted">&mdash;</span>'; ?>
              </td>
              <td class="small text-muted">
                <?= e($p['created_by_name'] ?? 'Staff'); ?>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <!-- View Rx -->
                  <a href="<?= BASE_URL; ?>modules/prescriptions/view.php?id=<?= $p['id']; ?>" class="btn btn-outline-secondary" title="View Prescription" data-bs-toggle="tooltip">
                    <i class="bi bi-eye"></i>
                  </a>

                  <!-- Edit Rx -->
                  <?php if ($canManage): ?>
                    <a href="<?= BASE_URL; ?>modules/prescriptions/edit.php?id=<?= $p['id']; ?>" class="btn btn-outline-secondary" title="Edit Prescription" data-bs-toggle="tooltip">
                      <i class="bi bi-pencil"></i>
                    </a>

                    <!-- Delete Rx -->
                    <form method="POST" action="<?= BASE_URL; ?>modules/prescriptions/delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete Prescription #<?= $p['id']; ?> for <?= e(addslashes($p['customer_name'])); ?>?');">
                      <?= csrfField(); ?>
                      <input type="hidden" name="id" value="<?= $p['id']; ?>">
                      <button type="submit" class="btn btn-outline-danger" title="Delete Prescription" data-bs-toggle="tooltip">
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
    </div>

    <!-- Pagination Footer -->
    <div class="card-footer bg-white d-flex flex-column flex-sm-row justify-content-between align-items-center py-3 gap-2">
      <div class="small text-muted">
        Showing <strong><?= $fromRecord; ?></strong> to <strong><?= $toRecord; ?></strong> of <strong><?= $totalRecords; ?></strong> prescriptions
      </div>

      <?php if ($totalPages > 1): ?>
        <nav aria-label="Prescription list pagination">
          <ul class="pagination pagination-sm m-0">
            <!-- Previous Link -->
            <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
              <?php
              $prevParams = array_merge($_GET, ['page' => $page - 1]);
              $prevUrl = '?' . http_build_query($prevParams);
              ?>
              <a class="page-link" href="<?= $prevUrl; ?>" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
              </a>
            </li>

            <!-- Page Number Links -->
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
              <?php
              $pageParams = array_merge($_GET, ['page' => $p]);
              $pageUrl = '?' . http_build_query($pageParams);
              ?>
              <li class="page-item <?= $p === $page ? 'active' : ''; ?>">
                <a class="page-link" href="<?= $pageUrl; ?>"><?= $p; ?></a>
              </li>
            <?php endfor; ?>

            <!-- Next Link -->
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
              <?php
              $nextParams = array_merge($_GET, ['page' => $page + 1]);
              $nextUrl = '?' . http_build_query($nextParams);
              ?>
              <a class="page-link" href="<?= $nextUrl; ?>" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
              </a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
