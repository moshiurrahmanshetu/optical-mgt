<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Comprehensive Order Details & Billing View
 */

$pageTitle = 'Order Details';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDbConnection();
$isAdmin = hasRole('admin');
$canManageOrders = hasRole(['admin', 'optician', 'sales_staff']);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid order identifier.');
    redirect('modules/orders/index.php');
}

// Fetch Order with Customer, Prescription and Creator details
$stmt = $pdo->prepare("
    SELECT o.*, 
           c.full_name AS customer_name, c.customer_code, c.phone AS customer_phone, c.email AS customer_email, c.address AS customer_address,
           p.prescription_date, p.doctor_name, p.notes AS rx_notes,
           p.right_sph, p.right_cyl, p.right_axis, p.right_add, p.right_pd,
           p.left_sph, p.left_cyl, p.left_axis, p.left_add, p.left_pd,
           u.name AS created_by_name
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    LEFT JOIN prescriptions p ON o.prescription_id = p.id
    LEFT JOIN users u ON o.created_by = u.id
    WHERE o.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order record not found in database.');
    redirect('modules/orders/index.php');
}

// Fetch Order Items (snapshots)
$itemsStmt = $pdo->prepare("
    SELECT oi.*, p.brand, p.category_id, c.name AS category_name, c.type AS category_type
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE oi.order_id = :order_id
    ORDER BY oi.id ASC
");
$itemsStmt->execute(['order_id' => $id]);
$orderItems = $itemsStmt->fetchAll();

// Fetch Payment History
$payStmt = $pdo->prepare("
    SELECT pm.*, u.name AS received_by_name
    FROM payments pm
    LEFT JOIN users u ON pm.received_by = u.id
    WHERE pm.order_id = :order_id
    ORDER BY pm.payment_date ASC, pm.id ASC
");
$payStmt->execute(['order_id' => $id]);
$payments = $payStmt->fetchAll();

$isPending   = ($order['status'] === 'pending');
$isCancelled = ($order['status'] === 'cancelled');
$isDelivered = ($order['status'] === 'delivered');
$hasDue      = ((float)$order['due_amount'] > 0);
?>

<!-- Header Actions & Breadcrumbs -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <h4 class="fw-bold text-dark m-0 font-monospace"><?= e($order['order_code']); ?></h4>
      <?= getOrderStatusBadge($order['status']); ?>
      <?= getPaymentStatusBadge($order['grand_total'], $order['paid_amount'], $order['due_amount']); ?>
    </div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small mt-1">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/orders/index.php" class="text-decoration-none">Orders</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($order['order_code']); ?></li>
      </ol>
    </nav>
  </div>

  <div class="d-flex flex-wrap gap-2">
    <!-- Print Invoice Button -->
    <a href="<?= BASE_URL; ?>modules/orders/print.php?id=<?= $order['id']; ?>" target="_blank" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-sm">
      <i class="bi bi-printer-fill"></i> Print Invoice
    </a>

    <!-- Add Payment Modal Trigger (If Due > 0 and not cancelled) -->
    <?php if ($hasDue && !$isCancelled && $canManageOrders): ?>
      <button type="button" class="btn btn-success d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
        <i class="bi bi-cash-stack"></i> Add Payment
      </button>
    <?php endif; ?>

    <!-- Update Status Modal Trigger -->
    <?php if (!$isDelivered && !$isCancelled && $canManageOrders): ?>
      <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
        <i class="bi bi-arrow-repeat"></i> Update Status
      </button>
    <?php endif; ?>

    <!-- Edit Order (Pending Only) -->
    <?php if ($isPending && $canManageOrders): ?>
      <a href="<?= BASE_URL; ?>modules/orders/edit.php?id=<?= $order['id']; ?>" class="btn btn-outline-primary d-inline-flex align-items-center gap-1">
        <i class="bi bi-pencil-square"></i> Edit Order
      </a>
    <?php endif; ?>

    <!-- Delete Order (Admin Only, Pending Only) -->
    <?php if ($isAdmin && $isPending): ?>
      <form method="POST" action="<?= BASE_URL; ?>modules/orders/delete.php" class="d-inline" onsubmit="return confirm('Permanently delete pending order <?= e($order['order_code']); ?>? This action cannot be undone.');">
        <?= csrfField(); ?>
        <input type="hidden" name="id" value="<?= $order['id']; ?>">
        <button type="submit" class="btn btn-outline-danger d-inline-flex align-items-center gap-1">
          <i class="bi bi-trash"></i> Delete
        </button>
      </form>
    <?php endif; ?>

    <!-- Back to Orders List -->
    <a href="<?= BASE_URL; ?>modules/orders/index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
      <i class="bi bi-arrow-left"></i> List
    </a>
  </div>
</div>

<div class="row g-4">
  <!-- Left Column: Customer Details & Order Items & Notes -->
  <div class="col-lg-8">

    <!-- Customer & Order Information Summary -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-person-circle me-2 text-primary"></i> Customer &amp; Order Information
        </h6>
        <span class="badge bg-light text-dark border font-monospace"><?= e($order['customer_code']); ?></span>
      </div>
      <div class="card-body p-4">
        <div class="row g-3">
          <div class="col-md-6">
            <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Customer Details</span>
            <h5 class="fw-bold text-dark mt-1 mb-1">
              <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $order['customer_id']; ?>" class="text-dark text-decoration-none hover-primary">
                <?= e($order['customer_name']); ?>
              </a>
            </h5>
            <div class="small text-muted mb-1"><i class="bi bi-telephone me-1"></i> <?= e($order['customer_phone']); ?></div>
            <?php if (!empty($order['customer_email'])): ?>
              <div class="small text-muted mb-1"><i class="bi bi-envelope me-1"></i> <?= e($order['customer_email']); ?></div>
            <?php endif; ?>
            <?php if (!empty($order['customer_address'])): ?>
              <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i> <?= e($order['customer_address']); ?></div>
            <?php endif; ?>
          </div>

          <div class="col-md-6 border-start-md ps-md-4">
            <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Order Timeline</span>
            <div class="small mt-2">
              <div class="d-flex justify-content-between py-1 border-bottom">
                <span class="text-muted">Order Date:</span>
                <span class="fw-bold text-dark"><?= date('M d, Y', strtotime($order['order_date'])); ?></span>
              </div>
              <div class="d-flex justify-content-between py-1 border-bottom">
                <span class="text-muted">Expected Delivery:</span>
                <span class="fw-semibold text-primary"><?= !empty($order['delivery_date']) ? date('M d, Y', strtotime($order['delivery_date'])) : '<span class="text-muted">&mdash;</span>'; ?></span>
              </div>
              <div class="d-flex justify-content-between py-1 border-bottom">
                <span class="text-muted">Created By:</span>
                <span class="fw-medium text-dark"><?= !empty($order['created_by_name']) ? e($order['created_by_name']) : 'Staff'; ?></span>
              </div>
              <div class="d-flex justify-content-between py-1">
                <span class="text-muted">Stock Status:</span>
                <span class="fw-semibold <?= (int)$order['stock_deducted'] === 1 ? 'text-success' : 'text-secondary'; ?>">
                  <i class="bi <?= (int)$order['stock_deducted'] === 1 ? 'bi-check-circle-fill' : 'bi-dash-circle'; ?>"></i>
                  <?= (int)$order['stock_deducted'] === 1 ? 'Committed & Deducted' : 'Pending Commitment'; ?>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Prescription Summary (If Linked) -->
    <?php if (!empty($order['prescription_id'])): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
          <h6 class="m-0 fw-semibold text-dark">
            <i class="bi bi-file-earmark-medical-fill me-2 text-primary"></i> Linked Prescription Refraction (Rx #<?= $order['prescription_id']; ?>)
          </h6>
          <a href="<?= BASE_URL; ?>modules/prescriptions/view.php?id=<?= $order['prescription_id']; ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-box-arrow-up-right me-1"></i> View Full Rx
          </a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered align-middle text-center mb-0 small" style="border-color: #e2e8f0;">
              <thead style="background-color: #f8fafc;">
                <tr>
                  <th class="text-start ps-3" style="width: 20%;">Eye</th>
                  <th style="width: 16%;">SPH</th>
                  <th style="width: 16%;">CYL</th>
                  <th style="width: 16%;">AXIS</th>
                  <th style="width: 16%;">ADD</th>
                  <th style="width: 16%;">PD</th>
                </tr>
              </thead>
              <tbody class="font-monospace">
                <tr>
                  <td class="text-start ps-3 font-sans-serif fw-bold text-primary">RIGHT (OD)</td>
                  <td class="fw-bold text-dark"><?= formatOpticalPower($order['right_sph']); ?></td>
                  <td class="fw-bold text-dark"><?= formatOpticalPower($order['right_cyl']); ?></td>
                  <td class="fw-bold text-dark"><?= formatAxis($order['right_axis']); ?></td>
                  <td class="fw-bold text-dark"><?= formatOpticalPower($order['right_add']); ?></td>
                  <td class="fw-bold text-dark"><?= formatPd($order['right_pd']); ?></td>
                </tr>
                <tr>
                  <td class="text-start ps-3 font-sans-serif fw-bold text-success">LEFT (OS)</td>
                  <td class="fw-bold text-dark"><?= formatOpticalPower($order['left_sph']); ?></td>
                  <td class="fw-bold text-dark"><?= formatOpticalPower($order['left_cyl']); ?></td>
                  <td class="fw-bold text-dark"><?= formatAxis($order['left_axis']); ?></td>
                  <td class="fw-bold text-dark"><?= formatOpticalPower($order['left_add']); ?></td>
                  <td class="fw-bold text-dark"><?= formatPd($order['left_pd']); ?></td>
                </tr>
              </tbody>
            </table>
          </div>
          <?php if (!empty($order['rx_notes'])): ?>
            <div class="p-3 bg-light border-top small text-muted">
              <strong>Rx Remarks:</strong> <?= nl2br(e($order['rx_notes'])); ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Order Items (Snapshots) Table -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-box-seam-fill me-2 text-primary"></i> Order Items Breakdown
        </h6>
        <span class="badge bg-light text-dark border"><?= count($orderItems); ?> <?= count($orderItems) === 1 ? 'Item' : 'Items'; ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
          <thead>
            <tr>
              <th class="ps-3">#</th>
              <th>Product Details</th>
              <th class="text-center">Type</th>
              <th class="text-end">Unit Price</th>
              <th class="text-center">Qty</th>
              <th class="text-end pe-3">Line Total</th>
            </tr>
          </thead>
          <tbody>
            <?php $i = 1; foreach ($orderItems as $item): ?>
              <tr>
                <td class="ps-3 text-muted small"><?= $i++; ?></td>
                <td>
                  <div class="fw-semibold text-dark"><?= e($item['product_name']); ?></div>
                  <small class="font-monospace text-primary"><?= e($item['product_code']); ?></small>
                  <?php if (!empty($item['brand'])): ?>
                    <small class="text-muted">&bull; <?= e($item['brand']); ?></small>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if (!empty($item['category_type'])): ?>
                    <span class="badge <?= $item['category_type'] === 'frame' ? 'bg-primary' : ($item['category_type'] === 'lens' ? 'bg-info text-dark' : 'bg-dark'); ?>">
                      <?= ucfirst(e($item['category_type'])); ?>
                    </span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Product</span>
                  <?php endif; ?>
                </td>
                <td class="text-end font-monospace">
                  <?= formatMoney($item['unit_price']); ?>
                </td>
                <td class="text-center font-monospace fw-bold">
                  <?= (int) $item['quantity']; ?>
                </td>
                <td class="text-end pe-3 font-monospace fw-bold text-dark">
                  <?= formatMoney($item['total_price']); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Order Remarks & Notes -->
    <?php if (!empty($order['notes'])): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
          <h6 class="m-0 fw-semibold text-dark">
            <i class="bi bi-card-text me-2 text-primary"></i> Order Notes &amp; Special Remarks
          </h6>
        </div>
        <div class="card-body p-3">
          <div class="p-3 bg-light rounded border small text-dark">
            <?= nl2br(e($order['notes'])); ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>

  <!-- Right Column: Financial Breakdown & Payment History -->
  <div class="col-lg-4">

    <!-- Financial Breakdown Card -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-calculator-fill me-2 text-primary"></i> Financial Summary
        </h6>
      </div>
      <div class="card-body p-4">
        <div class="d-flex justify-content-between py-2 border-bottom">
          <span class="text-muted small">Subtotal:</span>
          <span class="fw-bold font-monospace text-dark"><?= formatMoney($order['subtotal']); ?></span>
        </div>

        <div class="d-flex justify-content-between py-2 border-bottom">
          <span class="text-muted small">Discount Applied:</span>
          <span class="fw-semibold font-monospace text-danger">- <?= formatMoney($order['discount']); ?></span>
        </div>

        <div class="d-flex justify-content-between py-2 border-bottom">
          <span class="fw-bold text-dark">Grand Total:</span>
          <span class="fw-bold font-monospace fs-5 text-primary"><?= formatMoney($order['grand_total']); ?></span>
        </div>

        <div class="d-flex justify-content-between py-2 border-bottom">
          <span class="text-muted small">Total Paid:</span>
          <span class="fw-bold font-monospace text-success"><?= formatMoney($order['paid_amount']); ?></span>
        </div>

        <div class="d-flex justify-content-between py-2">
          <span class="fw-bold text-dark">Balance Due:</span>
          <span class="fw-bold font-monospace fs-5 <?= (float)$order['due_amount'] > 0 ? 'text-danger' : 'text-success'; ?>">
            <?= formatMoney($order['due_amount']); ?>
          </span>
        </div>

        <?php if ($isCancelled): ?>
          <div class="alert alert-danger mt-3 mb-0 p-2 text-center small fw-semibold">
            <i class="bi bi-x-octagon-fill me-1"></i> This order is cancelled.
          </div>
        <?php elseif ((float)$order['due_amount'] <= 0): ?>
          <div class="alert alert-success mt-3 mb-0 p-2 text-center small fw-semibold">
            <i class="bi bi-check-circle-fill me-1"></i> Order is fully settled and paid in full.
          </div>
        <?php else: ?>
          <div class="alert alert-warning mt-3 mb-0 p-2 text-center small fw-semibold text-dark">
            <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i> Outstanding due: <?= formatMoney($order['due_amount']); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Payment History Section -->
    <div class="card shadow-sm mb-4" id="payment-section">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-credit-card-2-front-fill me-2 text-primary"></i> Payment History
        </h6>
        <?php if ($hasDue && !$isCancelled && $canManageOrders): ?>
          <button type="button" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
            <i class="bi bi-plus-circle"></i> Add Payment
          </button>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if (empty($payments)): ?>
          <div class="p-4 text-center text-muted small">
            <i class="bi bi-receipt fs-3 text-secondary d-block mb-2"></i>
            No payments recorded yet for this order.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 small">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Date</th>
                  <th>Method</th>
                  <th class="text-end">Amount</th>
                  <th class="pe-3">Ref</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $pm): ?>
                  <tr>
                    <td class="ps-3 text-dark"><?= date('M d, Y', strtotime($pm['payment_date'])); ?></td>
                    <td>
                      <span class="badge bg-light text-dark border"><?= e($pm['payment_method']); ?></span>
                      <?php if (!empty($pm['received_by_name'])): ?>
                        <div class="text-muted" style="font-size: 0.7rem;">Rec: <?= e($pm['received_by_name']); ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end font-monospace fw-bold text-success">
                      <?= formatMoney($pm['amount']); ?>
                    </td>
                    <td class="pe-3 font-monospace small text-muted">
                      <?= !empty($pm['reference']) ? e($pm['reference']) : '&mdash;'; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- Modal: Add Payment -->
<?php if ($hasDue && !$isCancelled && $canManageOrders): ?>
  <div class="modal fade" id="addPaymentModal" tabindex="-1" aria-labelledby="addPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="<?= BASE_URL; ?>modules/orders/add-payment.php">
          <?= csrfField(); ?>
          <input type="hidden" name="order_id" value="<?= $order['id']; ?>">
          
          <div class="modal-header bg-light">
            <h6 class="modal-title fw-bold text-dark" id="addPaymentModalLabel">
              <i class="bi bi-cash-stack me-2 text-success"></i> Add Payment &mdash; <?= e($order['order_code']); ?>
            </h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="p-3 bg-light rounded border mb-3">
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Grand Total:</span>
                <span class="font-monospace fw-bold"><?= formatMoney($order['grand_total']); ?></span>
              </div>
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Already Paid:</span>
                <span class="font-monospace text-success fw-bold"><?= formatMoney($order['paid_amount']); ?></span>
              </div>
              <div class="d-flex justify-content-between small border-top pt-1">
                <span class="fw-bold text-dark">Current Due:</span>
                <span class="font-monospace text-danger fw-bold fs-6"><?= formatMoney($order['due_amount']); ?></span>
              </div>
            </div>

            <div class="mb-3">
              <label for="modal_amount" class="form-label small fw-semibold text-dark">Payment Amount ($) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light">$</span>
                <input type="number" step="0.01" min="0.01" max="<?= (float)$order['due_amount']; ?>" class="form-control font-monospace" id="modal_amount" name="amount" value="<?= number_format((float)$order['due_amount'], 2, '.', ''); ?>" required>
              </div>
              <small class="text-muted">Maximum allowable payment: <?= formatMoney($order['due_amount']); ?></small>
            </div>

            <div class="mb-3">
              <label for="modal_payment_date" class="form-label small fw-semibold text-dark">Payment Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="modal_payment_date" name="payment_date" value="<?= date('Y-m-d'); ?>" required>
            </div>

            <div class="mb-3">
              <label for="modal_payment_method" class="form-label small fw-semibold text-dark">Payment Method <span class="text-danger">*</span></label>
              <select class="form-select" id="modal_payment_method" name="payment_method" required>
                <option value="Cash">Cash</option>
                <option value="Card">Card / POS</option>
                <option value="Mobile Banking">Mobile Banking (bKash/Nagad/Rocket)</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="modal_reference" class="form-label small fw-semibold text-dark">Transaction / Slip Reference</label>
              <input type="text" class="form-control" id="modal_reference" name="reference" placeholder="Card txn #, money receipt #, check #...">
            </div>

            <div class="mb-0">
              <label for="modal_notes" class="form-label small fw-semibold text-dark">Payment Notes</label>
              <textarea class="form-control" id="modal_notes" name="notes" rows="2" placeholder="Optional notes..."></textarea>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">
              <i class="bi bi-check-circle me-1"></i> Record Payment
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Modal: Update Order Status -->
<?php if (!$isDelivered && !$isCancelled && $canManageOrders): ?>
  <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="<?= BASE_URL; ?>modules/orders/update-status.php">
          <?= csrfField(); ?>
          <input type="hidden" name="order_id" value="<?= $order['id']; ?>">
          
          <div class="modal-header bg-light">
            <h6 class="modal-title fw-bold text-dark" id="updateStatusModalLabel">
              <i class="bi bi-arrow-repeat me-2 text-primary"></i> Update Order Status &mdash; <?= e($order['order_code']); ?>
            </h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label small fw-semibold text-dark">Current Status:</label>
              <div><?= getOrderStatusBadge($order['status']); ?></div>
            </div>

            <div class="mb-3">
              <label for="new_status" class="form-label small fw-semibold text-dark">New Status <span class="text-danger">*</span></label>
              <select class="form-select" id="new_status" name="new_status" required>
                <?php if ($order['status'] === 'pending'): ?>
                  <option value="confirmed">Confirmed (Commit &amp; Deduct Product Stock)</option>
                  <option value="cancelled">Cancelled</option>
                <?php elseif ($order['status'] === 'confirmed'): ?>
                  <option value="processing">Processing (In Optical Workshop)</option>
                  <option value="cancelled">Cancelled (Restore Deducted Stock)</option>
                <?php elseif ($order['status'] === 'processing'): ?>
                  <option value="ready">Ready (Ready for Customer Pickup)</option>
                  <option value="cancelled">Cancelled (Restore Deducted Stock)</option>
                <?php elseif ($order['status'] === 'ready'): ?>
                  <option value="delivered">Delivered (Handed over to Customer)</option>
                  <option value="cancelled">Cancelled (Restore Deducted Stock)</option>
                <?php endif; ?>
              </select>
            </div>

            <div class="alert alert-info small mb-0">
              <i class="bi bi-info-circle-fill me-1"></i>
              <strong>Inventory Automation:</strong>
              <?php if ($order['status'] === 'pending'): ?>
                Moving to <strong>Confirmed</strong> will verify product stock availability and deduct ordered quantities.
              <?php else: ?>
                Cancelling a confirmed/processed order will safely restore product quantities back to stock inventory.
              <?php endif; ?>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check2-circle me-1"></i> Update Status
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
