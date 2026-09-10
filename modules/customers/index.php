<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Customer Management - Listing Page with Search & Pagination
 */

$pageTitle = 'Customer Management';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDbConnection();
$currentUser = currentUser();
$isAdmin = hasRole('admin');

// Query Parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

// Build WHERE clause
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(customer_code LIKE :search OR full_name LIKE :search OR phone LIKE :search OR email LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if ($statusFilter === 'active' || $statusFilter === 'inactive') {
    $whereClauses[] = "status = :status";
    $params['status'] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

// Count Total Filtered Records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers" . $whereSql);
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();

// Calculate Pagination
$totalPages = max(1, (int) ceil($totalRecords / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Fetch Customers Page Records
$sql = "SELECT * FROM customers" . $whereSql . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);

foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll();

// Pagination Display Helper Range
$fromRecord = $totalRecords > 0 ? $offset + 1 : 0;
$toRecord = min($offset + $perPage, $totalRecords);
?>

<!-- Header Actions & Breadcrumb -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold text-dark m-0">Customers</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Customers</li>
      </ol>
    </nav>
  </div>
  <div>
    <a href="<?= BASE_URL; ?>modules/customers/create.php" class="btn btn-primary d-inline-flex align-items-center gap-1">
      <i class="bi bi-person-plus-fill"></i> Add New Customer
    </a>
  </div>
</div>

<!-- Search & Filter Bar -->
<div class="card shadow-sm mb-4">
  <div class="card-body p-3">
    <form method="GET" action="<?= BASE_URL; ?>modules/customers/index.php" class="row g-2 align-items-center">
      <div class="col-md-6 col-lg-5">
        <div class="input-group">
          <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
          <input type="text" class="form-control border-start-0 ps-0" name="search" value="<?= e($search); ?>" placeholder="Search by name, code, phone, or email...">
        </div>
      </div>
      
      <div class="col-sm-6 col-md-3 col-lg-3">
        <select class="form-select" name="status" onchange="this.form.submit()">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
          <option value="active" <?= $statusFilter === 'active' ? 'selected' : ''; ?>>Active Customers</option>
          <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive Customers</option>
        </select>
      </div>

      <div class="col-sm-6 col-md-3 col-lg-4 d-flex gap-2">
        <button type="submit" class="btn btn-secondary px-3">Filter</button>
        <?php if (!empty($search) || $statusFilter !== 'all'): ?>
          <a href="<?= BASE_URL; ?>modules/customers/index.php" class="btn btn-outline-secondary px-3" title="Clear Filters">
            <i class="bi bi-x-circle me-1"></i> Reset
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Customers Table Card -->
<div class="card shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
    <h6 class="m-0 fw-semibold text-dark">
      <i class="bi bi-people-fill me-2 text-primary"></i> Customer List
    </h6>
    <span class="badge bg-light text-dark border"><?= $totalRecords; ?> Total</span>
  </div>

  <?php if (empty($customers)): ?>
    <div class="card-body text-center py-5">
      <div class="text-muted mb-3">
        <i class="bi bi-person-x fs-1 text-secondary"></i>
      </div>
      <?php if (!empty($search) || $statusFilter !== 'all'): ?>
        <h5 class="fw-semibold text-dark">No matching customers found</h5>
        <p class="text-muted small mb-3">No customer records match your current search or filter criteria.</p>
        <a href="<?= BASE_URL; ?>modules/customers/index.php" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
      <?php else: ?>
        <h5 class="fw-semibold text-dark">No customers registered yet</h5>
        <p class="text-muted small mb-3">Start by adding your first optical customer to the system.</p>
        <a href="<?= BASE_URL; ?>modules/customers/create.php" class="btn btn-primary btn-sm">
          <i class="bi bi-person-plus-fill me-1"></i> Add Customer
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-custom table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width: 130px;">Code</th>
            <th>Customer Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Gender</th>
            <th>Status</th>
            <th>Joined</th>
            <th class="text-end" style="width: 170px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td>
                <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                  <?= e($c['customer_code']); ?>
                </span>
              </td>
              <td>
                <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $c['id']; ?>" class="fw-semibold text-dark text-decoration-none hover-primary">
                  <?= e($c['full_name']); ?>
                </a>
              </td>
              <td>
                <span class="text-dark"><?= e($c['phone']); ?></span>
              </td>
              <td>
                <?= !empty($c['email']) ? e($c['email']) : '<span class="text-muted small">&mdash;</span>'; ?>
              </td>
              <td>
                <?php if ($c['gender'] === 'male'): ?>
                  <span class="small text-secondary"><i class="bi bi-gender-male text-primary me-1"></i> Male</span>
                <?php elseif ($c['gender'] === 'female'): ?>
                  <span class="small text-secondary"><i class="bi bi-gender-female text-danger me-1"></i> Female</span>
                <?php elseif ($c['gender'] === 'other'): ?>
                  <span class="small text-secondary">Other</span>
                <?php else: ?>
                  <span class="text-muted small">&mdash;</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $c['status'] === 'active' ? 'badge-status-active' : 'badge-status-inactive'; ?>">
                  <?= ucfirst(e($c['status'])); ?>
                </span>
              </td>
              <td class="text-muted small">
                <?= date('M d, Y', strtotime($c['created_at'])); ?>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <!-- View Profile -->
                  <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $c['id']; ?>" class="btn btn-outline-secondary" title="View Customer Profile" data-bs-toggle="tooltip">
                    <i class="bi bi-eye"></i>
                  </a>
                  
                  <!-- Edit Customer -->
                  <a href="<?= BASE_URL; ?>modules/customers/edit.php?id=<?= $c['id']; ?>" class="btn btn-outline-secondary" title="Edit Customer" data-bs-toggle="tooltip">
                    <i class="bi bi-pencil"></i>
                  </a>

                  <!-- Status Toggle Button Form -->
                  <form method="POST" action="<?= BASE_URL; ?>modules/customers/toggle-status.php" class="d-inline" onsubmit="return confirm('Change status of <?= e(addslashes($c['full_name'])); ?> to <?= $c['status'] === 'active' ? 'Inactive' : 'Active'; ?>?');">
                    <?= csrfField(); ?>
                    <input type="hidden" name="id" value="<?= $c['id']; ?>">
                    <button type="submit" class="btn btn-outline-secondary" title="<?= $c['status'] === 'active' ? 'Deactivate Customer' : 'Activate Customer'; ?>" data-bs-toggle="tooltip">
                      <i class="bi <?= $c['status'] === 'active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-secondary'; ?>"></i>
                    </button>
                  </form>

                  <!-- Delete Customer (Admin Only) -->
                  <?php if ($isAdmin): ?>
                    <form method="POST" action="<?= BASE_URL; ?>modules/customers/delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete customer <?= e(addslashes($c['full_name'])); ?> (<?= e($c['customer_code']); ?>)? This action cannot be undone.');">
                      <?= csrfField(); ?>
                      <input type="hidden" name="id" value="<?= $c['id']; ?>">
                      <button type="submit" class="btn btn-outline-danger" title="Delete Customer" data-bs-toggle="tooltip">
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
        Showing <strong><?= $fromRecord; ?></strong> to <strong><?= $toRecord; ?></strong> of <strong><?= $totalRecords; ?></strong> customers
      </div>

      <?php if ($totalPages > 1): ?>
        <nav aria-label="Customer list pagination">
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
