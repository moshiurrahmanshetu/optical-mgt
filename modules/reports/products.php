<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Inventory & Stock Health Report
 */

$pageTitle = 'Products & Stock Report';
require_once __DIR__ . '/../../includes/header.php';

// Authorization: Admin, Optician, Sales Staff
requireRole(['admin', 'optician', 'sales_staff']);

$pdo = getDbConnection();

// Fetch Categories for Filter Dropdown
$categories = $pdo->query("SELECT id, name, type FROM categories WHERE status = 'active' ORDER BY type ASC, name ASC")->fetchAll();

// Filters and Pagination
$catFilter    = trim($_GET['category_id'] ?? 'all');
$stockStatus  = trim($_GET['stock_status'] ?? 'all');
$statusFilter = trim($_GET['status'] ?? 'all');
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$limit        = 15;
$offset       = ($page - 1) * $limit;

// Build SQL Query
$where = [];
$params = [];

if ($catFilter !== '' && $catFilter !== 'all') {
    $where[] = "p.category_id = :cat_id";
    $params[':cat_id'] = (int) $catFilter;
}

if ($statusFilter !== '' && $statusFilter !== 'all') {
    $where[] = "p.status = :status";
    $params[':status'] = $statusFilter;
}

if ($stockStatus === 'low_stock') {
    $where[] = "p.stock_quantity <= p.low_stock_threshold AND p.stock_quantity > 0";
} elseif ($stockStatus === 'out_of_stock') {
    $where[] = "p.stock_quantity <= 0";
} elseif ($stockStatus === 'in_stock') {
    $where[] = "p.stock_quantity > p.low_stock_threshold";
}

if ($search !== '') {
    $where[] = "(p.product_code LIKE :search OR p.name LIKE :search OR p.brand LIKE :search OR p.model LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Overall Valuation & Aggregates
$sumSql = "
    SELECT 
        COUNT(*) AS total_items,
        SUM(p.stock_quantity) AS total_units,
        SUM(p.stock_quantity * p.purchase_price) AS total_cost_value,
        SUM(p.stock_quantity * p.selling_price) AS total_retail_value,
        SUM(CASE WHEN p.stock_quantity <= p.low_stock_threshold AND p.stock_quantity > 0 THEN 1 ELSE 0 END) AS count_low_stock,
        SUM(CASE WHEN p.stock_quantity <= 0 THEN 1 ELSE 0 END) AS count_out_of_stock
    FROM products p
    JOIN categories c ON p.category_id = c.id
    {$whereSql}
";
$sumStmt = $pdo->prepare($sumSql);
foreach ($params as $k => $v) {
    $sumStmt->bindValue($k, $v);
}
$sumStmt->execute();
$totals = $sumStmt->fetch();

$totalRecords = (int) ($totals['total_items'] ?? 0);
$totalPages   = max(1, (int) ceil($totalRecords / $limit));

// Fetch Paginated Products
$prodSql = "
    SELECT 
        p.id,
        p.product_code,
        p.name,
        p.brand,
        p.model,
        p.purchase_price,
        p.selling_price,
        p.stock_quantity,
        p.low_stock_threshold,
        p.status,
        c.name AS category_name,
        c.type AS category_type
    FROM products p
    JOIN categories c ON p.category_id = c.id
    {$whereSql}
    ORDER BY p.stock_quantity ASC, p.name ASC
    LIMIT :limit OFFSET :offset
";

$prodStmt = $pdo->prepare($prodSql);
foreach ($params as $k => $v) {
    $prodStmt->bindValue($k, $v);
}
$prodStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$prodStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$prodStmt->execute();
$products = $prodStmt->fetchAll();

// Build query string for export/print
$filterParams = $_GET;
unset($filterParams['page']);
$printQuery = http_build_query(array_merge($filterParams, ['type' => 'products']));
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= baseUrl('modules/reports/index.php') ?>" class="text-decoration-none">Reports</a></li>
                <li class="breadcrumb-item active" aria-current="page">Inventory &amp; Stock Report</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold text-dark mb-0">Inventory &amp; Stock Health Report</h1>
        <p class="text-muted small mb-0">Stock levels, valuation at cost and retail, replenishment alerts, and category summaries</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= baseUrl('modules/reports/print.php?' . $printQuery) ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i> Print / PDF
        </a>
        <a href="<?= baseUrl('modules/products/create.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Add Product
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="all" <?= $catFilter === 'all' ? 'selected' : '' ?>>All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $catFilter == $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?> (<?= ucfirst(e($cat['type'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Stock Health Status</label>
                <select name="stock_status" class="form-select form-select-sm">
                    <option value="all" <?= $stockStatus === 'all' ? 'selected' : '' ?>>All Stock Levels</option>
                    <option value="in_stock" <?= $stockStatus === 'in_stock' ? 'selected' : '' ?>>Adequate Stock</option>
                    <option value="low_stock" <?= $stockStatus === 'low_stock' ? 'selected' : '' ?>>Low Stock (Alert)</option>
                    <option value="out_of_stock" <?= $stockStatus === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock (Zero)</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Catalog Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Active &amp; Inactive</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Search Products</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Code / Name / Brand / Model" value="<?= e($search) ?>">
            </div>

            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= baseUrl('modules/reports/products.php') ?>" class="btn btn-sm btn-outline-secondary">
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
                <div class="text-muted small fw-semibold">Filtered Products</div>
                <h3 class="fw-bold text-dark my-1"><?= number_format($totalRecords) ?> items</h3>
                <div class="text-muted small"><?= number_format((int)($totals['total_units'] ?? 0)) ?> total units in stock</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold">Inventory Valuation (Cost)</div>
                <h3 class="fw-bold text-primary my-1"><?= formatMoney((float)($totals['total_cost_value'] ?? 0)) ?></h3>
                <div class="text-muted small">Total capital tied in stock</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold">Retail Valuation</div>
                <h3 class="fw-bold text-success my-1"><?= formatMoney((float)($totals['total_retail_value'] ?? 0)) ?></h3>
                <div class="text-muted small">Potential retail revenue</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Stock Alerts</div>
                        <div class="d-flex gap-2 align-items-baseline mt-1">
                            <span class="badge bg-warning text-dark fs-6"><?= (int)($totals['count_low_stock'] ?? 0) ?> Low</span>
                            <span class="badge bg-danger fs-6"><?= (int)($totals['count_out_of_stock'] ?? 0) ?> Out</span>
                        </div>
                    </div>
                    <div class="bg-warning text-dark rounded p-3">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title fw-bold text-dark mb-0">Inventory Catalog (<?= number_format($totalRecords) ?>)</h5>
        <span class="badge bg-light text-dark border">Showing page <?= $page ?> of <?= $totalPages ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Code / SKU</th>
                        <th>Product &amp; Category</th>
                        <th>Brand &amp; Model</th>
                        <th class="text-end">Cost Price</th>
                        <th class="text-end">Selling Price</th>
                        <th class="text-end">Margin</th>
                        <th class="text-center">Stock Level</th>
                        <th class="text-end">Inventory Value</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary"></i>
                                No product inventory records found matching the specified filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <?php
                            $cost = (float) $p['purchase_price'];
                            $sell = (float) $p['selling_price'];
                            $margin = ($sell > 0) ? (($sell - $cost) / $sell) * 100 : 0;
                            $stock = (int) $p['stock_quantity'];
                            $minAlert = (int) $p['low_stock_threshold'];
                            $stockVal = $stock * $cost;
                            ?>
                            <tr>
                                <td class="ps-3 fw-semibold text-muted">
                                    <?= e($p['product_code']) ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($p['name']) ?></div>
                                    <span class="badge bg-light text-dark border small"><?= e($p['category_name']) ?></span>
                                </td>
                                <td>
                                    <div class="text-dark"><?= e($p['brand'] ?: '—') ?></div>
                                    <div class="text-muted small"><?= e($p['model'] ?: '—') ?></div>
                                </td>
                                <td class="text-end text-muted font-monospace">
                                    <?= formatMoney($cost) ?>
                                </td>
                                <td class="text-end fw-bold text-dark font-monospace">
                                    <?= formatMoney($sell) ?>
                                </td>
                                <td class="text-end text-success fw-semibold">
                                    <?= number_format($margin, 1) ?>%
                                </td>
                                <td class="text-center">
                                    <?php if ($stock <= 0): ?>
                                        <span class="badge bg-danger">0 Out of Stock</span>
                                    <?php elseif ($stock <= $minAlert): ?>
                                        <span class="badge bg-warning text-dark"><?= $stock ?> (Low: &le;<?= $minAlert ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= $stock ?> In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-semibold text-dark font-monospace">
                                    <?= formatMoney($stockVal) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="<?= baseUrl('modules/products/edit.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit Product">
                                        <i class="bi bi-pencil"></i>
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
                Showing <?= min($totalRecords, $offset + 1) ?> to <?= min($totalRecords, $offset + count($products)) ?> of <?= number_format($totalRecords) ?> products
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
