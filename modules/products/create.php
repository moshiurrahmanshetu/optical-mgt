<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Create New Optical Product (Frames, Lenses, Accessories)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/flash.php';

requireAuth();

$pdo = getDbConnection();

// Fetch active categories
try {
    $catStmt = $pdo->query("SELECT id, name, type FROM categories WHERE status = 'active' ORDER BY type ASC, name ASC");
    $categories = $catStmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Initial form state
$formData = [
    'type'                => 'frame',
    'category_id'         => '',
    'name'                => '',
    'brand'               => '',
    'model'               => '',
    'description'         => '',
    'frame_material'      => '',
    'frame_color'         => '',
    'lens_type'           => '',
    'lens_material'       => '',
    'accessory_material'  => '',
    'purchase_price'      => '0.00',
    'selling_price'       => '',
    'stock_quantity'      => '0',
    'low_stock_threshold' => '5',
    'status'              => 'active'
];
$errors = [];

// Handle POST Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', 'Invalid security token or session expired. Please try again.');
        redirect('modules/products/create.php');
    }

    $formData['type']                = trim($_POST['type'] ?? 'frame');
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
    $formData['purchase_price']      = trim($_POST['purchase_price'] ?? '0.00');
    $formData['selling_price']       = trim($_POST['selling_price'] ?? '');
    $formData['stock_quantity']      = trim($_POST['stock_quantity'] ?? '0');
    $formData['low_stock_threshold'] = trim($_POST['low_stock_threshold'] ?? '5');
    $formData['status']              = trim($_POST['status'] ?? 'active');

    // Validation
    if (!in_array($formData['type'], ['frame', 'lens', 'accessory'], true)) {
        $errors['type'] = 'Please select a valid product type.';
    }

    if (empty($formData['name'])) {
        $errors['name'] = 'Product Name is required.';
    } elseif (mb_strlen($formData['name']) > 150) {
        $errors['name'] = 'Product Name cannot exceed 150 characters.';
    }

    if (empty($formData['category_id']) || (int)$formData['category_id'] <= 0) {
        $errors['category_id'] = 'Please select a category.';
    } else {
        // Validate category matches selected type
        $catMatch = false;
        foreach ($categories as $c) {
            if ((int)$c['id'] === (int)$formData['category_id'] && $c['type'] === $formData['type']) {
                $catMatch = true;
                break;
            }
        }
        if (!$catMatch) {
            $errors['category_id'] = 'Selected category does not match the product type.';
        }
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

    if ($formData['type'] === 'frame') {
        $material = !empty($formData['frame_material']) ? $formData['frame_material'] : null;
        $color    = !empty($formData['frame_color']) ? $formData['frame_color'] : null;
    } elseif ($formData['type'] === 'lens') {
        $lensType = !empty($formData['lens_type']) ? $formData['lens_type'] : null;
        $material = !empty($formData['lens_material']) ? $formData['lens_material'] : null;
    } elseif ($formData['type'] === 'accessory') {
        $material = !empty($formData['accessory_material']) ? $formData['accessory_material'] : null;
    }

    if (empty($errors)) {
        try {
            // Auto generate sequential product code with prefix
            $productCode = generateProductCode($pdo, $formData['type']);

            $insertSql = "INSERT INTO products (
                category_id, product_code, name, brand, model, description,
                lens_type, material, color,
                purchase_price, selling_price, stock_quantity, low_stock_threshold,
                status, created_at
            ) VALUES (
                :category_id, :product_code, :name, :brand, :model, :description,
                :lens_type, :material, :color,
                :purchase_price, :selling_price, :stock_quantity, :low_stock_threshold,
                :status, NOW()
            )";

            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                'category_id'         => (int)$formData['category_id'],
                'product_code'        => $productCode,
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
                'status'              => in_array($formData['status'], ['active', 'inactive'], true) ? $formData['status'] : 'active'
            ]);

            $newId = (int)$pdo->lastInsertId();
            setFlash('success', 'Product "' . e($formData['name']) . '" (' . $productCode . ') created successfully.');
            redirect('modules/products/view.php?id=' . $newId);
        } catch (Exception $e) {
            error_log('Product Create Error: ' . $e->getMessage());
            setFlash('error', 'Failed to create product: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Add New Product';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4 align-items-center">
  <div class="col-md-6 mb-2 mb-md-0">
    <h1 class="h3 fw-bold mb-1 text-dark">Add New Optical Product</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/products/index.php">Products</a></li>
        <li class="breadcrumb-item active" aria-current="page">Add Product</li>
      </ol>
    </nav>
  </div>
  <div class="col-md-6 text-md-end">
    <a href="<?= BASE_URL; ?>modules/products/index.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Back to Products
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

<form method="POST" action="<?= BASE_URL; ?>modules/products/create.php" id="productForm">
  <?= getCsrfField(); ?>

  <div class="row g-4">
    <!-- Left Column: Core Specs & Type Details -->
    <div class="col-lg-8">
      <!-- 1. Product Type Selector -->
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 border-0">
          <h6 class="mb-0 fw-bold text-dark">
            <i class="bi bi-grid-fill text-primary me-2"></i> 1. Product Classification
          </h6>
        </div>
        <div class="card-body pt-0">
          <label class="form-label fw-semibold text-secondary">Product Type <span class="text-danger">*</span></label>
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <input type="radio" class="btn-check" name="type" id="type_frame" value="frame" <?= $formData['type'] === 'frame' ? 'checked' : ''; ?> autocomplete="off">
              <label class="btn btn-outline-primary w-100 p-3 text-start h-100 d-flex flex-column justify-content-between" for="type_frame">
                <div>
                  <i class="bi bi-eyeglasses fs-3 d-block mb-1"></i>
                  <span class="fw-bold d-block">Eyeglass Frame</span>
                  <small class="text-muted" style="font-size: 0.78rem;">Optical frames & sunglasses (FRM-XXXXX)</small>
                </div>
              </label>
            </div>
            <div class="col-md-4">
              <input type="radio" class="btn-check" name="type" id="type_lens" value="lens" <?= $formData['type'] === 'lens' ? 'checked' : ''; ?> autocomplete="off">
              <label class="btn btn-outline-info w-100 p-3 text-start h-100 d-flex flex-column justify-content-between" for="type_lens">
                <div>
                  <i class="bi bi-eye fs-3 d-block mb-1"></i>
                  <span class="fw-bold d-block text-dark">Ophthalmic Lens</span>
                  <small class="text-muted" style="font-size: 0.78rem;">Prescription & coated lenses (LNS-XXXXX)</small>
                </div>
              </label>
            </div>
            <div class="col-md-4">
              <input type="radio" class="btn-check" name="type" id="type_accessory" value="accessory" <?= $formData['type'] === 'accessory' ? 'checked' : ''; ?> autocomplete="off">
              <label class="btn btn-outline-secondary w-100 p-3 text-start h-100 d-flex flex-column justify-content-between" for="type_accessory">
                <div>
                  <i class="bi bi-box fs-3 d-block mb-1"></i>
                  <span class="fw-bold d-block">Optical Accessory</span>
                  <small class="text-muted" style="font-size: 0.78rem;">Cases, sprays, cords, wipes (ACC-XXXXX)</small>
                </div>
              </label>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label for="category_id" class="form-label fw-semibold text-secondary">Category <span class="text-danger">*</span></label>
              <select name="category_id" id="category_id" class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : ''; ?>" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id']; ?>" data-type="<?= $cat['type']; ?>" <?= (string)$formData['category_id'] === (string)$cat['id'] ? 'selected' : ''; ?>>
                    <?= e($cat['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['category_id'])): ?>
                <div class="invalid-feedback"><?= e($errors['category_id']); ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold text-secondary">Product Code</label>
              <input type="text" class="form-control bg-light font-monospace" id="product_code_preview" value="Auto-generated on Save" readonly>
              <small class="text-muted" id="code_preview_hint">Prefix will match classification (e.g. FRM-00001)</small>
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
              <input type="text" name="name" id="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : ''; ?>" placeholder="e.g. Ray-Ban Aviator Classic 58mm" value="<?= e($formData['name']); ?>" required>
              <?php if (isset($errors['name'])): ?>
                <div class="invalid-feedback"><?= e($errors['name']); ?></div>
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label for="brand" class="form-label fw-semibold text-secondary">Brand / Manufacturer</label>
              <input type="text" name="brand" id="brand" class="form-control" placeholder="e.g. Ray-Ban, Essilor, Zeiss" value="<?= e($formData['brand']); ?>">
            </div>
            <div class="col-md-6">
              <label for="model" class="form-label fw-semibold text-secondary">Model / SKU / Reference</label>
              <input type="text" name="model" id="model" class="form-control" placeholder="e.g. RB3025-001, Crizal Prevencia" value="<?= e($formData['model']); ?>">
            </div>
            <div class="col-12">
              <label for="description" class="form-label fw-semibold text-secondary">Description & Features</label>
              <textarea name="description" id="description" rows="3" class="form-control" placeholder="Optional notes, warranty info, specifications..."><?= e($formData['description']); ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. Dynamic Type-Specific Specifications -->
      <!-- Frame Specs -->
      <div class="card border-0 shadow-sm rounded-3 mb-4 spec-section" id="section_frame">
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
              <input type="text" name="frame_color" id="frame_color" class="form-control" placeholder="e.g. Matte Black, Gold / Tortoise, Silver" value="<?= e($formData['frame_color']); ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Lens Specs -->
      <div class="card border-0 shadow-sm rounded-3 mb-4 spec-section d-none" id="section_lens">
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

      <!-- Accessory Specs -->
      <div class="card border-0 shadow-sm rounded-3 mb-4 spec-section d-none" id="section_accessory">
        <div class="card-header bg-white py-3 border-0">
          <h6 class="mb-0 fw-bold text-secondary">
            <i class="bi bi-box me-2"></i> Accessory Specifications
          </h6>
        </div>
        <div class="card-body pt-0">
          <div class="row g-3">
            <div class="col-12">
              <label for="accessory_material" class="form-label fw-semibold text-secondary">Material / Type Specification</label>
              <input type="text" name="accessory_material" id="accessory_material" class="form-control" placeholder="e.g. Microfiber 200gsm, Hard Leatherette Case, 60ml Anti-Fog Solution" value="<?= e($formData['accessory_material']); ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: Pricing, Inventory & Status -->
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
              <input type="number" step="0.01" min="0" name="selling_price" id="selling_price" class="form-control font-monospace fw-bold <?= isset($errors['selling_price']) ? 'is-invalid' : ''; ?>" placeholder="0.00" value="<?= e($formData['selling_price']); ?>" required>
              <?php if (isset($errors['selling_price'])): ?>
                <div class="invalid-feedback"><?= e($errors['selling_price']); ?></div>
              <?php endif; ?>
            </div>
            <small class="text-muted">Retail price charged to customer</small>
          </div>

          <?php if (hasRole('admin')): ?>
            <div class="mb-3">
              <label for="purchase_price" class="form-label fw-semibold text-secondary">Purchase / Cost Price ($)</label>
              <div class="input-group">
                <span class="input-group-text bg-light">$</span>
                <input type="number" step="0.01" min="0" name="purchase_price" id="purchase_price" class="form-control font-monospace <?= isset($errors['purchase_price']) ? 'is-invalid' : ''; ?>" placeholder="0.00" value="<?= e($formData['purchase_price']); ?>">
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
            <label for="stock_quantity" class="form-label fw-semibold text-secondary">Initial Stock Quantity <span class="text-danger">*</span></label>
            <input type="number" min="0" name="stock_quantity" id="stock_quantity" class="form-control font-monospace fw-bold <?= isset($errors['stock_quantity']) ? 'is-invalid' : ''; ?>" value="<?= e($formData['stock_quantity']); ?>" required>
            <?php if (isset($errors['stock_quantity'])): ?>
              <div class="invalid-feedback"><?= e($errors['stock_quantity']); ?></div>
            <?php endif; ?>
            <small class="text-muted">Current on-hand units in store</small>
          </div>

          <div class="mb-3">
            <label for="low_stock_threshold" class="form-label fw-semibold text-secondary">Low Stock Alert Threshold <span class="text-danger">*</span></label>
            <input type="number" min="0" name="low_stock_threshold" id="low_stock_threshold" class="form-control font-monospace <?= isset($errors['low_stock_threshold']) ? 'is-invalid' : ''; ?>" value="<?= e($formData['low_stock_threshold']); ?>" required>
            <?php if (isset($errors['low_stock_threshold'])): ?>
              <div class="invalid-feedback"><?= e($errors['low_stock_threshold']); ?></div>
            <?php endif; ?>
            <small class="text-muted">Trigger low stock badge when quantity reaches this level</small>
          </div>

          <div class="mb-3">
            <label for="status" class="form-label fw-semibold text-secondary">Catalog Status</label>
            <select name="status" id="status" class="form-select">
              <option value="active" <?= $formData['status'] === 'active' ? 'selected' : ''; ?>>Active (Visible in POS/Orders)</option>
              <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive (Archived/Hidden)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-3">
          <button type="submit" class="btn btn-primary w-100 py-2 mb-2">
            <i class="bi bi-check-lg me-1"></i> Save Product to Catalog
          </button>
          <a href="<?= BASE_URL; ?>modules/products/index.php" class="btn btn-outline-secondary w-100">
            Cancel
          </a>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const typeRadios = document.querySelectorAll('input[name="type"]');
  const catSelect = document.getElementById('category_id');
  const catOptions = Array.from(catSelect.options);
  const codePreview = document.getElementById('product_code_preview');
  const codeHint = document.getElementById('code_preview_hint');

  const secFrame = document.getElementById('section_frame');
  const secLens = document.getElementById('section_lens');
  const secAcc = document.getElementById('section_accessory');

  function updateTypeUI(selectedType) {
    // 1. Toggle spec sections
    secFrame.classList.toggle('d-none', selectedType !== 'frame');
    secLens.classList.toggle('d-none', selectedType !== 'lens');
    secAcc.classList.toggle('d-none', selectedType !== 'accessory');

    // 2. Filter category options
    let currentCatVal = catSelect.value;
    catSelect.innerHTML = '<option value="">-- Select Category --</option>';
    
    let matchedCurrent = false;
    catOptions.forEach(opt => {
      if (opt.value && opt.getAttribute('data-type') === selectedType) {
        const cloned = opt.cloneNode(true);
        if (cloned.value === currentCatVal) {
          cloned.selected = true;
          matchedCurrent = true;
        }
        catSelect.appendChild(cloned);
      }
    });

    if (!matchedCurrent && catSelect.options.length > 1) {
      // Don't auto select, let user choose
    }

    // 3. Update Code Preview Hint
    if (selectedType === 'frame') {
      codePreview.value = 'FRM-XXXXX (Auto)';
      codeHint.textContent = 'Auto-assigned with FRM- prefix for frames';
    } else if (selectedType === 'lens') {
      codePreview.value = 'LNS-XXXXX (Auto)';
      codeHint.textContent = 'Auto-assigned with LNS- prefix for lenses';
    } else if (selectedType === 'accessory') {
      codePreview.value = 'ACC-XXXXX (Auto)';
      codeHint.textContent = 'Auto-assigned with ACC- prefix for accessories';
    }
  }

  typeRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      if (this.checked) {
        updateTypeUI(this.value);
      }
    });
  });

  // Initial load
  const checkedRadio = document.querySelector('input[name="type"]:checked');
  if (checkedRadio) {
    updateTypeUI(checkedRadio.value);
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
