<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * View Optical Product Details
 */

$pageTitle = 'Product Details';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDbConnection();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Invalid product ID.');
    redirect('modules/products/index.php');
}

// Fetch product joined with category
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.type AS category_type 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.id = :id
");
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'Product not found.');
    redirect('modules/products/index.php');
}

// Type badge styling
$typeBadgeClass = 'bg-secondary';
if ($product['category_type'] === 'frame') {
    $typeBadgeClass = 'bg-primary';
} elseif ($product['category_type'] === 'lens') {
    $typeBadgeClass = 'bg-info text-dark';
} elseif ($product['category_type'] === 'accessory') {
    $typeBadgeClass = 'bg-dark';
}

// Margin calculations (for Admin)
$purchasePrice = (float)($product['purchase_price'] ?? 0);
$sellingPrice = (float)$product['selling_price'];
$marginAmount = $sellingPrice - $purchasePrice;
$marginPercent = ($sellingPrice > 0 && $purchasePrice > 0) ? round(($marginAmount / $sellingPrice) * 100, 1) : 0;
?>

<div class="row mb-4 align-items-center">
  <div class="col-md-6 mb-2 mb-md-0">
    <div class="d-flex align-items-center gap-2 mb-1">
      <h1 class="h3 fw-bold mb-0 text-dark"><?= e($product['name']); ?></h1>
      <span class="badge <?= $typeBadgeClass; ?>"><?= ucfirst(e($product['category_type'])); ?></span>
    </div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/products/index.php">Products</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($product['product_code']); ?></li>
      </ol>
    </nav>
  </div>
  <div class="col-md-6 text-md-end d-flex flex-wrap justify-content-md-end gap-2">
    <a href="<?= BASE_URL; ?>modules/products/index.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Products List
    </a>
    <a href="<?= BASE_URL; ?>modules/products/edit.php?id=<?= $id; ?>" class="btn btn-primary">
      <i class="bi bi-pencil me-1"></i> Edit Product
    </a>
    <?php if (hasRole('admin')): ?>
      <form method="POST" action="<?= BASE_URL; ?>modules/products/toggle-status.php" class="d-inline">
        <?= getCsrfField(); ?>
        <input type="hidden" name="id" value="<?= $id; ?>">
        <input type="hidden" name="redirect" value="view">
        <button type="submit" class="btn btn-outline-<?= $product['status'] === 'active' ? 'warning' : 'success'; ?>">
          <i class="bi bi-toggle-<?= $product['status'] === 'active' ? 'on' : 'off'; ?> me-1"></i>
          <?= $product['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
        </button>
      </form>
      <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProductModal">
        <i class="bi bi-trash me-1"></i> Delete
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-4">
  <!-- Main Specs & Description Column -->
  <div class="col-lg-8">
    <!-- Header Overview Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
      <div class="card-body p-4">
        <div class="row align-items-center">
          <div class="col-md-8">
            <span class="badge bg-light text-secondary border font-monospace fs-6 px-3 py-2 mb-2">
              <i class="bi bi-upc-scan me-1 text-primary"></i> <?= e($product['product_code']); ?>
            </span>
            <h4 class="fw-bold text-dark mb-1"><?= e($product['name']); ?></h4>
            <div class="text-secondary mb-2">
              <?php if (!empty($product['brand'])): ?>
                <strong class="text-dark"><?= e($product['brand']); ?></strong>
              <?php endif; ?>
              <?php if (!empty($product['model'])): ?>
                <span class="mx-1">&bull;</span> Model: <code><?= e($product['model']); ?></code>
              <?php endif; ?>
            </div>
            <p class="text-muted mb-0"><?= !empty($product['description']) ? nl2br(e($product['description'])) : '<em class="text-secondary">No description provided.</em>'; ?></p>
          </div>
          <div class="col-md-4 text-md-end mt-3 mt-md-0 border-start-md ps-md-4">
            <div class="small text-secondary mb-1">Retail Selling Price</div>
            <div class="h2 fw-bold text-primary mb-2 font-monospace"><?= formatMoney($product['selling_price']); ?></div>
            <div class="d-flex align-items-center justify-content-md-end gap-2">
              <?= getStockBadge((int)$product['stock_quantity'], (int)$product['low_stock_threshold'], $product['status']); ?>
              <span class="badge badge-status-<?= $product['status'] === 'active' ? 'active' : 'inactive'; ?>">
                <?= ucfirst($product['status']); ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Technical Specifications Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
      <div class="card-header bg-white py-3 border-0">
        <h6 class="mb-0 fw-bold text-dark">
          <i class="bi bi-sliders text-primary me-2"></i> Technical Specifications
        </h6>
      </div>
      <div class="card-body pt-0">
        <div class="table-responsive">
          <table class="table table-bordered mb-0">
            <tbody>
              <tr>
                <th class="bg-light text-secondary w-35">Product Classification</th>
                <td class="fw-semibold text-dark">
                  <span class="badge <?= $typeBadgeClass; ?> me-2"><?= ucfirst(e($product['category_type'])); ?></span>
                  <?= e($product['category_name']); ?>
                </td>
              </tr>
              <tr>
                <th class="bg-light text-secondary">Brand / Manufacturer</th>
                <td><?= !empty($product['brand']) ? e($product['brand']) : '<span class="text-muted">—</span>'; ?></td>
              </tr>
              <tr>
                <th class="bg-light text-secondary">Model / SKU Reference</th>
                <td class="font-monospace"><?= !empty($product['model']) ? e($product['model']) : '<span class="text-muted">—</span>'; ?></td>
              </tr>

              <?php if ($product['category_type'] === 'frame'): ?>
                <tr>
                  <th class="bg-light text-secondary">Frame Material</th>
                  <td><?= !empty($product['material']) ? e($product['material']) : '<span class="text-muted">—</span>'; ?></td>
                </tr>
                <tr>
                  <th class="bg-light text-secondary">Frame Color / Finish</th>
                  <td><?= !empty($product['color']) ? e($product['color']) : '<span class="text-muted">—</span>'; ?></td>
                </tr>
              <?php elseif ($product['category_type'] === 'lens'): ?>
                <tr>
                  <th class="bg-light text-secondary">Lens Design / Function</th>
                  <td><?= !empty($product['lens_type']) ? e($product['lens_type']) : '<span class="text-muted">—</span>'; ?></td>
                </tr>
                <tr>
                  <th class="bg-light text-secondary">Index / Material</th>
                  <td><?= !empty($product['material']) ? e($product['material']) : '<span class="text-muted">—</span>'; ?></td>
                </tr>
              <?php elseif ($product['category_type'] === 'accessory'): ?>
                <tr>
                  <th class="bg-light text-secondary">Specification / Material</th>
                  <td><?= !empty($product['material']) ? e($product['material']) : '<span class="text-muted">—</span>'; ?></td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column: Inventory, Financials & Metadata -->
  <div class="col-lg-4">
    <!-- Stock & Inventory Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
      <div class="card-header bg-white py-3 border-0">
        <h6 class="mb-0 fw-bold text-dark">
          <i class="bi bi-box-seam text-warning me-2"></i> Inventory Status
        </h6>
      </div>
      <div class="card-body pt-0">
        <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-3 mb-3">
          <div>
            <small class="text-secondary d-block fw-semibold">Current Stock</small>
            <span class="fs-3 fw-bold font-monospace text-dark"><?= (int)$product['stock_quantity']; ?></span>
            <span class="text-muted small">units</span>
          </div>
          <div>
            <?= getStockBadge((int)$product['stock_quantity'], (int)$product['low_stock_threshold'], $product['status']); ?>
          </div>
        </div>

        <ul class="list-group list-group-flush small">
          <li class="list-group-item px-0 d-flex justify-content-between">
            <span class="text-secondary">Low Stock Alert Level</span>
            <span class="fw-bold font-monospace"><?= (int)$product['low_stock_threshold']; ?> units</span>
          </li>
          <li class="list-group-item px-0 d-flex justify-content-between">
            <span class="text-secondary">Inventory Health</span>
            <?php if ((int)$product['stock_quantity'] <= 0): ?>
              <span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>Reorder Needed</span>
            <?php elseif ((int)$product['stock_quantity'] <= (int)$product['low_stock_threshold']): ?>
              <span class="text-warning fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Low Inventory</span>
            <?php else: ?>
              <span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Optimal</span>
            <?php endif; ?>
          </li>
          <li class="list-group-item px-0 d-flex justify-content-between">
            <span class="text-secondary">Catalog Status</span>
            <span class="badge badge-status-<?= $product['status'] === 'active' ? 'active' : 'inactive'; ?>"><?= ucfirst($product['status']); ?></span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Commercial Financials Card (Admin) -->
    <?php if (hasRole('admin')): ?>
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-0">
          <h6 class="mb-0 fw-bold text-dark">
            <i class="bi bi-cash-stack text-success me-2"></i> Financial & Margin Analysis
          </h6>
        </div>
        <div class="card-body pt-0">
          <div class="row g-2 text-center mb-3">
            <div class="col-6">
              <div class="p-2 border rounded-3 bg-light">
                <small class="text-secondary d-block">Purchase Cost</small>
                <span class="fw-bold font-monospace text-dark"><?= formatMoney($purchasePrice); ?></span>
              </div>
            </div>
            <div class="col-6">
              <div class="p-2 border rounded-3 bg-light">
                <small class="text-secondary d-block">Retail Price</small>
                <span class="fw-bold font-monospace text-primary"><?= formatMoney($sellingPrice); ?></span>
              </div>
            </div>
          </div>

          <div class="p-3 rounded-3 <?= $marginAmount >= 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger'; ?>">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <small class="d-block fw-semibold text-secondary">Estimated Margin / Unit</small>
                <span class="h5 fw-bold font-monospace mb-0"><?= formatMoney($marginAmount); ?></span>
              </div>
              <div class="text-end">
                <span class="badge <?= $marginAmount >= 0 ? 'bg-success' : 'bg-danger'; ?> fs-6 font-monospace">
                  <?= $marginPercent; ?>%
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Record Metadata Card -->
    <div class="card border-0 shadow-sm rounded-3">
      <div class="card-body p-3">
        <h6 class="fw-bold text-dark mb-2 small text-uppercase">Audit Trail</h6>
        <div class="small text-secondary mb-1">
          <i class="bi bi-calendar-plus me-1"></i> Created: <?= date('M d, Y H:i', strtotime($product['created_at'])); ?>
        </div>
        <div class="small text-secondary">
          <i class="bi bi-clock-history me-1"></i> Last Updated: <?= date('M d, Y H:i', strtotime($product['updated_at'])); ?>
        </div>
      </div>
    </div>
  </div>
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
          <div class="fw-bold text-dark"><?= e($product['name']); ?></div>
          <small class="text-secondary font-monospace"><?= e($product['product_code']); ?></small>
        </div>
        <p class="text-muted small mt-2 mb-0">This action cannot be undone. If this product has been ordered or referenced, deletion will be blocked.</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" action="<?= BASE_URL; ?>modules/products/delete.php" class="d-inline">
          <?= getCsrfField(); ?>
          <input type="hidden" name="id" value="<?= $id; ?>">
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash me-1"></i> Delete Product
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
