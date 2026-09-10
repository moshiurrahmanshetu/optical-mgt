<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Optical Products & Inventory Catalog
 */

$pageTitle = 'Product Catalog';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDbConnection();
$currentRole = $_SESSION['user_role'] ?? 'sales';

// --- Filters and Pagination Parameters ---
$search      = trim($_GET['q'] ?? '');
$typeFilter  = trim($_GET['type'] ?? '');
$catFilter   = (int) ($_GET['category_id'] ?? 0);
$stockFilter = trim($_GET['stock_status'] ?? '');
$status      = trim($_GET['status'] ?? 'all');
$page        = max(1, (int) ($_GET['page'] ?? 1));
$limit       = 10;
$offset      = ($page - 1) * $limit;

// --- Fetch Categories for Filter Dropdown ---
try {
    $catStmt = $pdo->query("SELECT id, name, type FROM categories WHERE status = 'active' ORDER BY type ASC, name ASC");
    $allCategories = $catStmt->fetchAll();
} catch (Exception $e) {
    $allCategories = [];
}

// --- Build SQL Query with Conditions ---
$whereConditions = [];
$params = [];

if ($search !== '') {
    $whereConditions[] = "(p.product_code LIKE :search OR p.name LIKE :search OR p.brand LIKE :search OR p.model LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($typeFilter !== '' && in_array($typeFilter, ['frame', 'lens', 'accessory'], true)) {
    $whereConditions[] = "c.type = :type";
    $params[':type'] = $typeFilter;
}

if ($catFilter > 0) {
    $whereConditions[] = "p.category_id = :cat_id";
    $params[':cat_id'] = $catFilter;
}

if ($stockFilter === 'out_of_stock') {
    $whereConditions[] = "p.stock_quantity <= 0";
} elseif ($stockFilter === 'low_stock') {
    $whereConditions[] = "p.stock_quantity > 0 AND p.stock_quantity <= p.low_stock_threshold";
} elseif ($stockFilter === 'in_stock') {
    $whereConditions[] = "p.stock_quantity > p.low_stock_threshold";
}

if ($status === 'active' || $status === 'inactive') {
    $whereConditions[] = "p.status = :status";
    $params[':status'] = $status;
}

$whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// --- Count Total Matching Records ---
$countSql = "SELECT COUNT(*) FROM products p JOIN categories c ON p.category_id = c.id {$whereSql}";
$stmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$totalRecords = (int) $stmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $limit));

// --- Fetch Products with Category Details ---
$sql = "SELECT p.*, c.name AS category_name, c.type AS category_type 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        {$whereSql} 
        ORDER BY p.id DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// --- Summary KPI Counts ---
try {
    $stats = $pdo->query("
        SELECT 
            COUNT(*) AS total_products,
            SUM(CASE WHEN c.type = 'frame' THEN 1 ELSE 0 END) AS total_frames,
            SUM(CASE WHEN c.type = 'lens' THEN 1 ELSE 0 END) AS total_lenses,
            SUM(CASE WHEN c.type = 'accessory' THEN 1 ELSE 0 END) AS total_accessories,
            SUM(CASE WHEN p.stock_quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
            SUM(CASE WHEN p.stock_quantity > 0 AND p.stock_quantity <= p.low_stock_threshold THEN 1 ELSE 0 END) AS low_stock
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active'
    ")->fetch();
} catch (Exception $e) {
    $stats = ['total_products' => 0, 'total_frames' => 0, 'total_lenses' => 0, 'total_accessories' => 0, 'out_of_stock' => 0, 'low_stock' => 0];
}
?>

<div class="row mb-4 align-items-center">
  <div class="col-md-6 mb-2 mb-md-0">
    <h1 class="h3 fw-bold mb-1 text-dark">Optical Products Catalog</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Products</li>
      </ol>
    </nav>
  </div>
  <div class="col-md-6 text-md-end d-flex flex-wrap justify-content-md-end gap-2">
    <?php if (hasRole(['admin'])): ?>
      <a href="<?= BASE_URL; ?>modules/products/categories.php" class="btn btn-outline-secondary">
        <i class="bi bi-tags me-1"></i> Categories
      </a>
    <?php endif; ?>
    <a href="<?= BASE_URL; ?>modules/products/create.php" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Add Product
    </a>
  </div>
</div>

<!-- KPI Mini Dashboard -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white text-center">
      <small class="text-secondary fw-semibold d-block mb-1">Total Active</small>
      <span class="fs-4 fw-bold text-dark"><?= number_format($stats['total_products'] ?? 0); ?></span>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white text-center">
      <small class="text-secondary fw-semibold d-block mb-1">Frames</small>
      <span class="fs-4 fw-bold text-primary"><?= number_format($stats['total_frames'] ?? 0); ?></span>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white text-center">
      <small class="text-secondary fw-semibold d-block mb-1">Lenses</small>
      <span class="fs-4 fw-bold text-info"><?= number_format($stats['total_lenses'] ?? 0); ?></span>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white text-center">
      <small class="text-secondary fw-semibold d-block mb-1">Accessories</small>
      <span class="fs-4 fw-bold text-secondary"><?= number_format($stats['total_accessories'] ?? 0); ?></span>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white text-center">
      <small class="text-secondary fw-semibold d-block mb-1">Low Stock</small>
      <span class="fs-4 fw-bold text-warning"><?= number_format($stats['low_stock'] ?? 0); ?></span>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white text-center">
      <small class="text-secondary fw-semibold d-block mb-1">Out of Stock</small>
      <span class="fs-4 fw-bold text-danger"><?= number_format($stats['out_of_stock'] ?? 0); ?></span>
    </div>
  </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
  <div class="card-body p-3">
    <form method="GET" action="<?= BASE_URL; ?>modules/products/index.php" class="row g-2 align-items-center">
      <div class="col-12 col-md-3">
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Search Code, Name, Brand, Model..." value="<?= e($search); ?>">
        </div>
      </div>
      <div class="col-6 col-md-2">
        <select name="type" class="form-select" id="filterTypeSelect">
          <option value="">All Types</option>
          <option value="frame" <?= $typeFilter === 'frame' ? 'selected' : ''; ?>>Frames</option>
          <option value="lens" <?= $typeFilter === 'lens' ? 'selected' : ''; ?>>Lenses</option>
          <option value="accessory" <?= $typeFilter === 'accessory' ? 'selected' : ''; ?>>Accessories</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="category_id" class="form-select" id="filterCategorySelect">
          <option value="0">All Categories</option>
          <?php foreach ($allCategories as $cat): ?>
            <option value="<?= $cat['id']; ?>" data-type="<?= $cat['type']; ?>" <?= $catFilter === (int)$cat['id'] ? 'selected' : ''; ?>>
              <?= e($cat['name']); ?> (<?= ucfirst($cat['type']); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="stock_status" class="form-select">
          <option value="">All Stock Levels</option>
          <option value="in_stock" <?= $stockFilter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
          <option value="low_stock" <?= $stockFilter === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
          <option value="out_of_stock" <?= $stockFilter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
        </select>
      </div>
      <div class="col-6 col-md-1">
        <select name="status" class="form-select">
          <option value="all" <?= $status === 'all' ? 'selected' : ''; ?>>All Status</option>
          <option value="active" <?= $status === 'active' ? 'selected' : ''; ?>>Active</option>
          <option value="inactive" <?= $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
      </div>
      <div class="col-12 col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill">
          <i class="bi bi-funnel me-1"></i> Filter
        </button>
        <?php if ($search !== '' || $typeFilter !== '' || $catFilter > 0 || $stockFilter !== '' || $status !== 'all'): ?>
          <a href="<?= BASE_URL; ?>modules/products/index.php" class="btn btn-outline-secondary" title="Reset Filters">
            <i class="bi bi-arrow-counterclockwise"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Products Table Card -->
<div class="card border-0 shadow-sm rounded-3">
  <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
    <h6 class="mb-0 fw-bold text-dark">
      <i class="bi bi-box-seam me-2 text-primary"></i> Product Inventory List
      <span class="badge bg-light text-secondary ms-2 border"><?= number_format($totalRecords); ?> <?= $totalRecords === 1 ? 'item' : 'items'; ?></span>
    </h6>
    <span class="text-secondary small">Showing page <?= $page; ?> of <?= $totalPages; ?></span>
  </div>
  
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light text-secondary text-uppercase fs-7">
          <tr>
            <th class="ps-3" style="width: 120px;">Code</th>
            <th>Product Name & Specs</th>
            <th>Type / Category</th>
            <th>Brand & Model</th>
            <th class="text-end">Price</th>
            <th class="text-center">Stock Level</th>
            <th class="text-center">Status</th>
            <th class="text-end pe-3" style="width: 130px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($products)): ?>
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <div class="py-4">
                  <i class="bi bi-inbox fs-1 d-block mb-3 text-secondary"></i>
                  <p class="h6 text-secondary mb-1">No products found</p>
                  <p class="small text-muted mb-3">Try adjusting your search criteria or add a new optical product to the catalog.</p>
                  <a href="<?= BASE_URL; ?>modules/products/create.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Add Product
                  </a>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($products as $p): ?>
              <?php
                // Type badge styling
                $typeBadgeClass = 'bg-secondary';
                if ($p['category_type'] === 'frame') {
                    $typeBadgeClass = 'bg-primary';
                } elseif ($p['category_type'] === 'lens') {
                    $typeBadgeClass = 'bg-info text-dark';
                } elseif ($p['category_type'] === 'accessory') {
                    $typeBadgeClass = 'bg-dark';
                }
              ?>
              <tr>
                <td class="ps-3">
                  <a href="<?= BASE_URL; ?>modules/products/view.php?id=<?= $p['id']; ?>" class="fw-bold text-decoration-none text-primary font-monospace">
                    <?= e($p['product_code']); ?>
                  </a>
                </td>
                <td>
                  <div class="fw-bold text-dark"><?= e($p['name']); ?></div>
                  <div class="small text-muted">
                    <?php if ($p['category_type'] === 'frame'): ?>
                      <?php if (!empty($p['material'])): ?><span class="me-2"><i class="bi bi-layers me-1"></i><?= e($p['material']); ?></span><?php endif; ?>
                      <?php if (!empty($p['color'])): ?><span><i class="bi bi-palette me-1"></i><?= e($p['color']); ?></span><?php endif; ?>
                    <?php elseif ($p['category_type'] === 'lens'): ?>
                      <?php if (!empty($p['lens_type'])): ?><span class="me-2"><i class="bi bi-eye me-1"></i><?= e($p['lens_type']); ?></span><?php endif; ?>
                      <?php if (!empty($p['material'])): ?><span><i class="bi bi-layers me-1"></i><?= e($p['material']); ?></span><?php endif; ?>
                    <?php else: ?>
                      <?php if (!empty($p['material'])): ?><span><?= e($p['material']); ?></span><?php endif; ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <span class="badge <?= $typeBadgeClass; ?> mb-1"><?= ucfirst(e($p['category_type'])); ?></span>
                  <div class="small text-secondary fw-semibold"><?= e($p['category_name']); ?></div>
                </td>
                <td>
                  <div class="fw-semibold text-dark"><?= !empty($p['brand']) ? e($p['brand']) : '<span class="text-muted">—</span>'; ?></div>
                  <div class="small text-muted"><?= !empty($p['model']) ? e($p['model']) : ''; ?></div>
                </td>
                <td class="text-end font-monospace">
                  <div class="fw-bold text-dark"><?= formatMoney($p['selling_price']); ?></div>
                  <?php if (hasRole('admin') && (float)$p['purchase_price'] > 0): ?>
                    <div class="small text-muted" title="Cost / Purchase Price">Cost: <?= formatMoney($p['purchase_price']); ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <div class="fw-bold font-monospace fs-6"><?= (int)$p['stock_quantity']; ?></div>
                  <?= getStockBadge((int)$p['stock_quantity'], (int)$p['low_stock_threshold'], $p['status']); ?>
                </td>
                <td class="text-center">
                  <span class="badge badge-status-<?= $p['status'] === 'active' ? 'active' : 'inactive'; ?>">
                    <?= ucfirst($p['status']); ?>
                  </span>
                </td>
                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <a href="<?= BASE_URL; ?>modules/products/view.php?id=<?= $p['id']; ?>" class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View Details">
                      <i class="bi bi-eye"></i>
                    </a>
                    <a href="<?= BASE_URL; ?>modules/products/edit.php?id=<?= $p['id']; ?>" class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Edit Product">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <?php if (hasRole('admin')): ?>
                      <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProductModal" 
                              data-id="<?= $p['id']; ?>" 
                              data-name="<?= e($p['name']); ?>" 
                              data-code="<?= e($p['product_code']); ?>" 
                              title="Delete Product">
                        <i class="bi bi-trash"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Card Footer Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white border-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
      <span class="small text-muted">
        Showing <?= min($totalRecords, $offset + 1); ?> to <?= min($totalRecords, $offset + $limit); ?> of <?= number_format($totalRecords); ?> items
      </span>
      <nav aria-label="Products pagination">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?= buildPaginationUrl($page - 1); ?>" aria-label="Previous">
              <i class="bi bi-chevron-left"></i>
            </a>
          </li>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i === 1 || $i === $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
              <li class="page-item <?= $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="<?= buildPaginationUrl($i); ?>"><?= $i; ?></a>
              </li>
            <?php elseif ($i === $page - 3 || $i === $page + 3): ?>
              <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
            <?php endif; ?>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?= buildPaginationUrl($page + 1); ?>" aria-label="Next">
              <i class="bi bi-chevron-right"></i>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  <?php endif; ?>
</div>

<!-- Admin Delete Modal -->
<?php if (hasRole('admin')): ?>
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-danger" id="deleteProductModalLabel">
          <i class="bi bi-exclamation-triangle-fill me-2"></i> Confirm Delete Product
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-3">
        <p class="mb-2">Are you sure you want to permanently delete this product?</p>
        <div class="alert alert-light border rounded-3 p-2 mb-0">
          <div class="fw-bold text-dark" id="modalProductName"></div>
          <small class="text-secondary font-monospace" id="modalProductCode"></small>
        </div>
        <p class="text-muted small mt-2 mb-0">This action cannot be undone. If this product has been ordered or referenced, deletion will be blocked.</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" action="<?= BASE_URL; ?>modules/products/delete.php" class="d-inline">
          <?= getCsrfField(); ?>
          <input type="hidden" name="id" id="modalProductId" value="">
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash me-1"></i> Delete Product
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const deleteModal = document.getElementById('deleteProductModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      const id = button.getAttribute('data-id');
      const name = button.getAttribute('data-name');
      const code = button.getAttribute('data-code');
      
      document.getElementById('modalProductId').value = id;
      document.getElementById('modalProductName').textContent = name;
      document.getElementById('modalProductCode').textContent = code;
    });
  }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
