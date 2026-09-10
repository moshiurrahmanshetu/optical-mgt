<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Edit Optical Product
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/flash.php';

requireAuth();

$pdo = getDbConnection();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Invalid product ID.');
    redirect('modules/products/index.php');
}

// Fetch existing product
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

// Fetch active categories for the same product type (or current category even if inactive)
try {
    $catStmt = $pdo->prepare("
        SELECT id, name, type 
        FROM categories 
        WHERE type = :type AND (status = 'active' OR id = :current_cat_id)
        ORDER BY name ASC
    ");
    $catStmt->execute([
        'type'           => $product['category_type'],
        'current_cat_id' => $product['category_id']
    ]);
    $categories = $catStmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Populate form state
$formData = [
    'category_id'         => $product['category_id'],
    'name'                => $product['name'],
    'brand'               => $product['brand'] ?? '',
    'model'               => $product['model'] ?? '',
    'description'         => $product['description'] ?? '',
    'frame_material'      => $product['category_type'] === 'frame' ? ($product['material'] ?? '') : '',
    'frame_color'         => $product['category_type'] === 'frame' ? ($product['color'] ?? '') : '',
    'lens_type'           => $product['category_type'] === 'lens' ? ($product['lens_type'] ?? '') : '',
    'lens_material'       => $product['category_type'] === 'lens' ? ($product['material'] ?? '') : '',
    'accessory_material'  => $product['category_type'] === 'accessory' ? ($product['material'] ?? '') : '',
    'purchase_price'      => $product['purchase_price'] ?? '0.00',
    'selling_price'       => $product['selling_price'],
    'stock_quantity'      => $product['stock_quantity'],
    'low_stock_threshold' => $product['low_stock_threshold'],
    'status'              => $product['status']
];
$errors = [];

// Handle POST Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', 'Invalid security token or session expired. Please try again.');
        redirect('modules/products/edit.php?id=' . $id);
    }

    $formData['category_id']         = trim($_POST['category_id'] ?? '');
    $formData['name']                = trim($_POST['name'] ?? '');
    $formData['brand']               = trim($_POST['brand'] ?? '');
    $formData['model']               = trim($_POST['model'] ?? '');
    $formData['description']         = trim($_POST['description'] ?? '');
    $formData['frame_material']      = trim($_POST['frame_material'] ?? '');
    $formData['frame_color']         = trim($_POST['frame_color'] ?? '');
    $formData['lens_type']           = trim($_POST['lens_type'] ?? '');
    $formData['lens_material']       = trim($_POST['lens_material'] ?? '');
    $formData['accessory_material']  = trim($_POST['accessory_material'] ?? '');
    $formData['purchase_price']      = trim($_POST['purchase_price'] ?? $product['purchase_price']);
    $formData['selling_price']       = trim($_POST['selling_price'] ?? '');
    $formData['stock_quantity']      = trim($_POST['stock_quantity'] ?? '0');
    $formData['low_stock_threshold'] = trim($_POST['low_stock_threshold'] ?? '5');
    $formData['status']              = trim($_POST['status'] ?? 'active');

    // Validation
    if (empty($formData['name'])) {
        $errors['name'] = 'Product Name is required.';
    } elseif (mb_strlen($formData['name']) > 150) {
        $errors['name'] = 'Product Name cannot exceed 150 characters.';
    }

    if (empty($formData['category_id']) || (int)$formData['category_id'] <= 0) {
        $errors['category_id'] = 'Please select a category.';
    }

    // Pricing validation
    if ($formData['selling_price'] === '' || !is_numeric($formData['selling_price']) || (float)$formData['selling_price'] < 0) {
        $errors['selling_price'] = 'Selling Price is required and must be a non-negative number.';
    }

    if ($formData['purchase_price'] !== '' && (!is_numeric($formData['purchase_price']) || (float)$formData['purchase_price'] < 0)) {
        $errors['purchase_price'] = 'Purchase Price must be a non-negative number.';
    }

    // Inventory validation
    if ($formData['stock_quantity'] === '' || !is_numeric($formData['stock_quantity']) || (int)$formData['stock_quantity'] < 0) {
        $errors['stock_quantity'] = 'Stock Quantity must be a non-negative integer.';
    }

    if ($formData['low_stock_threshold'] === '' || !is_numeric($formData['low_stock_threshold']) || (int)$formData['low_stock_threshold'] < 0) {
        $errors['low_stock_threshold'] = 'Low Stock Alert Threshold must be a non-negative integer.';
    }

    // Assign type-specific fields
    $lensType = null;
    $material = null;
    $color    = null;

    if ($product['category_type'] === 'frame') {
        $material = !empty($formData['frame_material']) ? $formData['frame_material'] : null;
        $color    = !empty($formData['frame_color']) ? $formData['frame_color'] : null;
    } elseif ($product['category_type'] === 'lens') {
        $lensType = !empty($formData['lens_type']) ? $formData['lens_type'] : null;
        $material = !empty($formData['lens_material']) ? $formData['lens_material'] : null;
    } elseif ($product['category_type'] === 'accessory') {
        $material = !empty($formData['accessory_material']) ? $formData['accessory_material'] : null;
    }

    if (empty($errors)) {
        try {
            $updateSql = "UPDATE products SET
                category_id         = :category_id,
                name                = :name,
                brand               = :brand,
                model               = :model,
                description         = :description,
                lens_type           = :lens_type,
                material            = :material,
                color               = :color,
                purchase_price      = :purchase_price,
                selling_price       = :selling_price,
                stock_quantity      = :stock_quantity,
                low_stock_threshold = :low_stock_threshold,
                status              = :status,
                updated_at          = NOW()
                WHERE id = :id";

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                'category_id'         => (int)$formData['category_id'],
                'name'                => $formData['name'],
                'brand'               => !empty($formData['brand']) ? $formData['brand'] : null,
                'model'               => !empty($formData['model']) ? $formData['model'] : null,
                'description'         => !empty($formData['description']) ? $formData['description'] : null,
                'lens_type'           => $lensType,
                'material'            => $material,
                'color'               => $color,
                'purchase_price'      => (float)($formData['purchase_price'] ?: 0),
                'selling_price'       => (float)$formData['selling_price'],
                'stock_quantity'      => (int)$formData['stock_quantity'],
                'low_stock_threshold' => (int)$formData['low_stock_threshold'],
                'status'              => in_array($formData['status'], ['active', 'inactive'], true) ? $formData['status'] : 'active',
                'id'                  => $id
            ]);

            setFlash('success', 'Product "' . e($formData['name']) . '" (' . e($product['product_code']) . ') updated successfully.');
            redirect('modules/products/view.php?id=' . $id);
        } catch (Exception $e) {
            error_log('Product Update Error: ' . $e->getMessage());
            setFlash('error', 'Failed to update product: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Edit Product';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4 align-items-center">
  <div class="col-md-6 mb-2 mb-md-0">
    <h1 class="h3 fw-bold mb-1 text-dark">Edit Product</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/products/index.php">Products</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit <?= e($product['product_code']); ?></li>
      </ol>
    </nav>
  </div>
  <div class="col-md-6 text-md-end d-flex justify-content-md-end gap-2">
    <a href="<?= BASE_URL; ?>modules/products/view.php?id=<?= $id; ?>" class="btn btn-outline-secondary">
      <i class="bi bi-eye me-1"></i> View Product
    </a>
    <a href="<?= BASE_URL; ?>modules/products/index.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Back to List
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
    <div class="d-flex align-items-center">
      <i class="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
      <div>
        <strong>Please correct the following errors:</strong>
        <ul class="mb-0 mt-1 ps-3">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL; ?>modules/products/edit.php?id=<?= $id; ?>">
  <?= getCsrfField(); ?>

  <div class="row g-4">
    <!-- Left Column: Specs & Classification -->
    <div class="col-lg-8">
      <!-- 1. Classification & Code -->
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-0">
          <h6 class="mb-0 fw-bold text-dark">
            <i class="bi bi-grid-fill text-primary me-2"></i> 1. Product Classification
          </h6>
        </div>
        <div class="card-body pt-0">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold text-secondary">Product Code</label>
              <input type="text" class="form-control bg-light font-monospace fw-bold" value="<?= e($product['product_code']); ?>" readonly>
              <small class="text-muted">Unique ID (Fixed)</small>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold text-secondary">Classification Type</label>
              <input type="text" class="form-control bg-light fw-bold" value="<?= ucfirst(e($product['category_type'])); ?>" readonly>
              <small class="text-muted">Classification cannot be changed</small>
            </div>
            <div class="col-md-4">
              <label for="category_id" class="form-label fw-semibold text-secondary">Category <span class="text-danger">*</span></label>
              <select name="category_id" id="category_id" class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : ''; ?>" required>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id']; ?>" <?= (string)$formData['category_id'] === (string)$cat['id'] ? 'selected' : ''; ?>>
                    <?= e($cat['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['category_id'])): ?>
                <div class="invalid-feedback"><?= e($errors['category_id']); ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Basic Information -->
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-0">
          <h6 class="mb-0 fw-bold text-dark">
            <i class="bi bi-info-circle-fill text-primary me-2"></i> 2. Product Details
          </h6>
        </div>
        <div class="card-body pt-0">
          <div class="row g-3 mb-3">
            <div class="col-12">
              <label for="name" class="form-label fw-semibold text-secondary">Product Name / Title <span class="text-danger">*</span></label>
              <input type="text" name="name" id="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : ''; ?>" value="<?= e($formData['name']); ?>" required>
              <?php if (isset($errors['name'])): ?>
                <div class="invalid-feedback"><?= e($errors['name']); ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label for="brand" class="form-label fw-semibold text-secondary">Brand / Manufacturer</label>
              <input type="text" name="brand" id="brand" class="form-control" value="<?= e($formData['brand']); ?>">
            </div>
            <div class="col-md-6">
              <label for="model" class="form-label fw-semibold text-secondary">Model / SKU / Reference</label>
              <input type="text" name="model" id="model" class="form-control" value="<?= e($formData['model']); ?>">
            </div>
            <div class="col-12">
              <label for="description" class="form-label fw-semibold text-secondary">Description & Features</label>
              <textarea name="description" id="description" rows="3" class="form-control"><?= e($formData['description']); ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. Type-Specific Specifications -->
      <?php if ($product['category_type'] === 'frame'): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4">
          <div class="card-header bg-white py-3 border-0">
            <h6 class="mb-0 fw-bold text-primary">
              <i class="bi bi-eyeglasses me-2"></i> Frame Specifications
            </h6>
          </div>
          <div class="card-body pt-0">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="frame_material" class="form-label fw-semibold text-secondary">Frame Material</label>
                <select name="frame_material" id="frame_material" class="form-select">
                  <option value="">-- Select Material --</option>
                  <?php
                    $frameMaterials = ['Acetate', 'Metal', 'Titanium', 'TR90 / Grilamid', 'Ultem', 'Stainless Steel', 'Plastic / Polycarbonate', 'Wood', 'Carbon Fiber', 'Mixed / Combination'];
                    foreach ($frameMaterials as $mat):
                  ?>
                    <option value="<?= $mat; ?>" <?= $formData['frame_material'] === $mat ? 'selected' : ''; ?>><?= $mat; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="frame_color" class="form-label fw-semibold text-secondary">Frame Color / Finish</label>
                <input type="text" name="frame_color" id="frame_color" class="form-control" value="<?= e($formData['frame_color']); ?>">
              </div>
            </div>
          </div>
        </div>
      <?php elseif ($product['category_type'] === 'lens'): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4">
          <div class="card-header bg-white py-3 border-0">
            <h6 class="mb-0 fw-bold text-info">
              <i class="bi bi-eye me-2"></i> Lens Specifications
            </h6>
          </div>
          <div class="card-body pt-0">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="lens_type" class="form-label fw-semibold text-secondary">Lens Design / Function</label>
                <select name="lens_type" id="lens_type" class="form-select">
                  <option value="">-- Select Lens Design --</option>
                  <?php
                    $lensTypes = ['Single Vision', 'Bifocal (D-Segment)', 'Progressive / Multifocal', 'Blue Light Blocking', 'Photochromic / Transition', 'Polarized Sun Lens', 'Anti-Reflective Coated', 'High Index Clear'];
                    foreach ($lensTypes as $lt):
                  ?>
                    <option value="<?= $lt; ?>" <?= $formData['lens_type'] === $lt ? 'selected' : ''; ?>><?= $lt; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="lens_material" class="form-label fw-semibold text-secondary">Index / Material</label>
                <select name="lens_material" id="lens_material" class="form-select">
                  <option value="">-- Select Material Index --</option>
                  <?php
                    $lensMats = ['CR-39 Standard (1.50)', 'Polycarbonate (1.59)', 'High Index (1.60)', 'High Index Thin (1.67)', 'Ultra High Index (1.74)', 'Trivex (1.53)', 'Crown Glass (1.52)'];
                    foreach ($lensMats as $lm):
                  ?>
                    <option value="<?= $lm; ?>" <?= $formData['lens_material'] === $lm ? 'selected' : ''; ?>><?= $lm; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>
      <?php elseif ($product['category_type'] === 'accessory'): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4">
          <div class="card-header bg-white py-3 border-0">
            <h6 class="mb-0 fw-bold text-secondary">
              <i class="bi bi-box me-2"></i> Accessory Specifications
            </h6>
          </div>
          <div class="card-body pt-0">
            <div class="row g-3">
              <div class="col-12">
                <label for="accessory_material" class="form-label fw-semibold text-secondary">Material / Type Specification</label>
                <input type="text" name="accessory_material" id="accessory_material" class="form-control" value="<?= e($formData['accessory_material']); ?>">
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Right Column: Pricing, Inventory & Controls -->
    <div class="col-lg-4">
      <!-- Financial Pricing Card -->
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-0">
          <h6 class="mb-0 fw-bold text-dark">
            <i class="bi bi-cash-stack text-success me-2"></i> 3. Pricing
          </h6>
        </div>
        <div class="card-body pt-0">
          <div class="mb-3">
            <label for="selling_price" class="form-label fw-semibold text-secondary">Selling Price ($) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-light fw-bold">$</span>
              <input type="number" step="0.01" min="0" name="selling_price" id="selling_price" class="form-control font-monospace fw-bold <?= isset($errors['selling_price']) ? 'is-invalid' : ''; ?>" value="<?= e($formData['selling_price']); ?>" required>
              <?php if (isset($errors['selling_price'])): ?>
                <div class="invalid-feedback"><?= e($errors['selling_price']); ?></div>
              <?php endif; ?>
            </div>
          </div>

          <?php if (hasRole('admin')): ?>
            <div class="mb-3">
              <label for="purchase_price" class="form-label fw-semibold text-secondary">Purchase / Cost Price ($)</label>
              <div class="input-group">
                <span class="input-group-text bg-light">$</span>
                <input type="number" step="0.01" min="0" name="purchase_price" id="purchase_price" class="form-control font-monospace <?= isset($errors['purchase_price']) ? 'is-invalid' : ''; ?>" value="<?= e($formData['purchase_price']); ?>">
                <?php if (isset($errors['purchase_price'])): ?>
                  <div class="invalid-feedback"><?= e($errors['purchase_price']); ?></div>
                <?php endif; ?>
              </div>
              <small class="text-muted">Internal cost for margin tracking</small>
            </div>
          <?php else: ?>
            <input type="hidden" name="purchase_price" value="<?= e($formData['purchase_price']); ?>">
          <?php endif; ?>
        </div>
      </div>

      <!-- Inventory Stock Card -->
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-0">
          <h6 class="mb-0 fw-bold text-dark">
            <i class="bi bi-box-seam text-warning me-2"></i> 4. Inventory Tracking
          </h6>
        </div>
        <div class="card-body pt-0">
          <div class="mb-3">
            <label for="stock_quantity" class="form-label fw-semibold text-secondary">Current Stock Quantity <span class="text-danger">*</span></label>
            <input type="number" min="0" name="stock_quantity" id="stock_quantity" class="form-control font-monospace fw-bold <?= isset($errors['stock_quantity']) ? 'is-invalid' : ''; ?>" value="<?= e($formData['stock_quantity']); ?>" required>
            <?php if (isset($errors['stock_quantity'])): ?>
              <div class="invalid-feedback"><?= e($errors['stock_quantity']); ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label for="low_stock_threshold" class="form-label fw-semibold text-secondary">Low Stock Alert Threshold <span class="text-danger">*</span></label>
            <input type="number" min="0" name="low_stock_threshold" id="low_stock_threshold" class="form-control font-monospace <?= isset($errors['low_stock_threshold']) ? 'is-invalid' : ''; ?>" value="<?= e($formData['low_stock_threshold']); ?>" required>
            <?php if (isset($errors['low_stock_threshold'])): ?>
              <div class="invalid-feedback"><?= e($errors['low_stock_threshold']); ?></div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label for="status" class="form-label fw-semibold text-secondary">Catalog Status</label>
            <select name="status" id="status" class="form-select">
              <option value="active" <?= $formData['status'] === 'active' ? 'selected' : ''; ?>>Active (Visible)</option>
              <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive (Archived)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-3">
          <button type="submit" class="btn btn-primary w-100 py-2 mb-2">
            <i class="bi bi-check-lg me-1"></i> Update Product
          </button>
          <a href="<?= BASE_URL; ?>modules/products/view.php?id=<?= $id; ?>" class="btn btn-outline-secondary w-100">
            Cancel
          </a>
        </div>
      </div>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
