<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Category Management (Administrator Only)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/flash.php';

requireAuth();

// Only Administrator can manage product categories
requireRole('admin');

$pdo = getDbConnection();

// Handle Category Creation / Edit / Delete POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', 'Security session expired. Please try again.');
        redirect('modules/products/categories.php');
    }

    if ($action === 'create') {
        $name   = trim($_POST['name'] ?? '');
        $type   = trim($_POST['type'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if (empty($name)) {
            setFlash('error', 'Category Name is required.');
        } elseif (!in_array($type, ['frame', 'lens', 'accessory'], true)) {
            setFlash('error', 'Please select a valid Category Type.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, type, status, created_at) VALUES (:name, :type, :status, NOW())");
                $stmt->execute([
                    'name'   => $name,
                    'type'   => $type,
                    'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active'
                ]);
                setFlash('success', 'Category "' . e($name) . '" (' . ucfirst($type) . ') created successfully.');
            } catch (Exception $e) {
                error_log('Create Category Error: ' . $e->getMessage());
                setFlash('error', 'Failed to create category. Please ensure category name is valid.');
            }
        }
        redirect('modules/products/categories.php');
    }

    if ($action === 'edit') {
        $catId  = (int) ($_POST['category_id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $type   = trim($_POST['type'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if ($catId <= 0 || empty($name) || !in_array($type, ['frame', 'lens', 'accessory'], true)) {
            setFlash('error', 'Invalid category data submitted.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = :name, type = :type, status = :status, updated_at = NOW() WHERE id = :id");
                $stmt->execute([
                    'name'   => $name,
                    'type'   => $type,
                    'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
                    'id'     => $catId
                ]);
                setFlash('success', 'Category "' . e($name) . '" updated successfully.');
            } catch (Exception $e) {
                error_log('Edit Category Error: ' . $e->getMessage());
                setFlash('error', 'Failed to update category.');
            }
        }
        redirect('modules/products/categories.php');
    }

    if ($action === 'toggle_status') {
        $catId = (int) ($_POST['category_id'] ?? 0);
        if ($catId > 0) {
            $stmt = $pdo->prepare("SELECT id, name, status FROM categories WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $catId]);
            $cat = $stmt->fetch();

            if ($cat) {
                $newStatus = ($cat['status'] === 'active') ? 'inactive' : 'active';
                $upStmt = $pdo->prepare("UPDATE categories SET status = :status, updated_at = NOW() WHERE id = :id");
                $upStmt->execute(['status' => $newStatus, 'id' => $catId]);
                setFlash('success', 'Category "' . e($cat['name']) . '" status changed to ' . ucfirst($newStatus) . '.');
            }
        }
        redirect('modules/products/categories.php');
    }

    if ($action === 'delete') {
        $catId = (int) ($_POST['category_id'] ?? 0);
        if ($catId > 0) {
            // Check if products exist under this category
            $pCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
            $pCheck->execute(['id' => $catId]);
            $productCount = (int) $pCheck->fetchColumn();

            if ($productCount > 0) {
                setFlash('error', 'Cannot delete category because it contains ' . $productCount . ' product(s). Please deactivate the category or reassign its products first.');
            } else {
                $delStmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
                $delStmt->execute(['id' => $catId]);
                setFlash('success', 'Category deleted successfully.');
            }
        }
        redirect('modules/products/categories.php');
    }
}

// Fetch all categories with product counts
$categoriesStmt = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.type ASC, c.name ASC
");
$categories = $categoriesStmt->fetchAll();

$pageTitle = 'Category Management';
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Header Actions & Breadcrumbs -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold text-dark m-0">Product Categories</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/products/index.php" class="text-decoration-none">Products</a></li>
        <li class="breadcrumb-item active" aria-current="page">Categories</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL; ?>modules/products/index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
      <i class="bi bi-box-seam"></i> Products Catalog
    </a>
  </div>
</div>

<div class="row g-4">
  <!-- Left Column: Add Category Form -->
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-plus-circle me-2 text-primary"></i> Add New Category
        </h6>
      </div>
      <div class="card-body p-4">
        <form method="POST" action="<?= BASE_URL; ?>modules/products/categories.php">
          <?= csrfField(); ?>
          <input type="hidden" name="action" value="create">

          <div class="mb-3">
            <label for="type" class="form-label small fw-semibold text-dark">Category Type <span class="text-danger">*</span></label>
            <select class="form-select" id="type" name="type" required>
              <option value="frame">Frame (Eyeglasses & Sunglasses)</option>
              <option value="lens">Lens (Single Vision, Progressive, etc.)</option>
              <option value="accessory">Accessory (Cases, Cleaning, etc.)</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="name" class="form-label small fw-semibold text-dark">Category Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="name" name="name" placeholder="e.g. Titanium Optical Frames" required>
          </div>

          <div class="mb-4">
            <label for="status" class="form-label small fw-semibold text-dark">Status</label>
            <select class="form-select" id="status" name="status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check2-circle me-1"></i> Save Category
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Right Column: Categories List Table -->
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-tags-fill me-2 text-primary"></i> Category Catalog
        </h6>
        <span class="badge bg-light text-dark border"><?= count($categories); ?> Categories</span>
      </div>

      <div class="table-responsive">
        <table class="table table-custom table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Type</th>
              <th>Category Name</th>
              <th>Products</th>
              <th>Status</th>
              <th class="text-end" style="width: 140px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($categories)): ?>
              <tr>
                <td colspan="5" class="text-center py-4 text-muted">
                  No categories created yet. Add one using the form on the left.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($categories as $cat): ?>
                <tr>
                  <td>
                    <?php if ($cat['type'] === 'frame'): ?>
                      <span class="badge bg-primary px-2 py-1"><i class="bi bi-eyeglasses me-1"></i> Frame</span>
                    <?php elseif ($cat['type'] === 'lens'): ?>
                      <span class="badge bg-info text-dark px-2 py-1"><i class="bi bi-circle me-1"></i> Lens</span>
                    <?php else: ?>
                      <span class="badge bg-secondary px-2 py-1"><i class="bi bi-bag-check me-1"></i> Accessory</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="fw-semibold text-dark"><?= e($cat['name']); ?></span>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border font-monospace">
                      <?= $cat['product_count']; ?> item<?= $cat['product_count'] == 1 ? '' : 's'; ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge <?= $cat['status'] === 'active' ? 'badge-status-active' : 'badge-status-inactive'; ?>">
                      <?= ucfirst(e($cat['status'])); ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <!-- Edit Trigger Modal -->
                      <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= $cat['id']; ?>" title="Edit Category">
                        <i class="bi bi-pencil"></i>
                      </button>

                      <!-- Toggle Status -->
                      <form method="POST" action="<?= BASE_URL; ?>modules/products/categories.php" class="d-inline">
                        <?= csrfField(); ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="category_id" value="<?= $cat['id']; ?>">
                        <button type="submit" class="btn btn-outline-secondary" title="<?= $cat['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                          <i class="bi <?= $cat['status'] === 'active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-secondary'; ?>"></i>
                        </button>
                      </form>

                      <!-- Delete Category -->
                      <form method="POST" action="<?= BASE_URL; ?>modules/products/categories.php" class="d-inline" onsubmit="return confirm('Delete category <?= e(addslashes($cat['name'])); ?>?');">
                        <?= csrfField(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="category_id" value="<?= $cat['id']; ?>">
                        <button type="submit" class="btn btn-outline-danger" title="Delete Category" <?= $cat['product_count'] > 0 ? 'disabled' : ''; ?>>
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </div>

                    <!-- Edit Modal -->
                    <div class="modal fade text-start" id="editModal<?= $cat['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $cat['id']; ?>" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form method="POST" action="<?= BASE_URL; ?>modules/products/categories.php">
                            <?= csrfField(); ?>
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="category_id" value="<?= $cat['id']; ?>">

                            <div class="modal-header">
                              <h6 class="modal-title fw-bold" id="editModalLabel<?= $cat['id']; ?>">Edit Category</h6>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                              <div class="mb-3">
                                <label class="form-label small fw-semibold text-dark">Category Type</label>
                                <select class="form-select" name="type" required>
                                  <option value="frame" <?= $cat['type'] === 'frame' ? 'selected' : ''; ?>>Frame</option>
                                  <option value="lens" <?= $cat['type'] === 'lens' ? 'selected' : ''; ?>>Lens</option>
                                  <option value="accessory" <?= $cat['type'] === 'accessory' ? 'selected' : ''; ?>>Accessory</option>
                                </select>
                              </div>
                              <div class="mb-3">
                                <label class="form-label small fw-semibold text-dark">Category Name</label>
                                <input type="text" class="form-control" name="name" value="<?= e($cat['name']); ?>" required>
                              </div>
                              <div class="mb-3">
                                <label class="form-label small fw-semibold text-dark">Status</label>
                                <select class="form-select" name="status">
                                  <option value="active" <?= $cat['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                  <option value="inactive" <?= $cat['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
