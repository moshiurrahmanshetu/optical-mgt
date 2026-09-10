<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Create New Optical Order & Initial Payment
 */

$pageTitle = 'Create New Order';
require_once __DIR__ . '/../../includes/header.php';

// Authorization: Admin, Optician, Sales Staff
requireRole(['admin', 'optician', 'sales_staff']);

$pdo = getDbConnection();

// Fetch Active Customers
$custStmt = $pdo->query("SELECT id, customer_code, full_name, phone, status FROM customers ORDER BY full_name ASC");
$allCustomers = $custStmt->fetchAll();

// Fetch All Prescriptions with Customer Association
$rxStmt = $pdo->query("
    SELECT p.id, p.customer_id, p.prescription_date, p.doctor_name,
           p.right_sph, p.right_cyl, p.right_axis, p.right_add, p.right_pd,
           p.left_sph, p.left_cyl, p.left_axis, p.left_add, p.left_pd
    FROM prescriptions p
    ORDER BY p.prescription_date DESC, p.id DESC
");
$allPrescriptions = $rxStmt->fetchAll();

// Index prescriptions by customer_id for fast client-side lookup
$prescriptionsByCustomer = [];
foreach ($allPrescriptions as $rx) {
    $cId = (int) $rx['customer_id'];
    if (!isset($prescriptionsByCustomer[$cId])) {
        $prescriptionsByCustomer[$cId] = [];
    }
    $prescriptionsByCustomer[$cId][] = $rx;
}

// Fetch Active Catalog Products
$prodStmt = $pdo->query("
    SELECT p.id, p.category_id, p.product_code, p.name, p.brand, p.model,
           p.selling_price, p.stock_quantity, p.low_stock_threshold, p.status,
           c.name AS category_name, c.type AS category_type
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
    ORDER BY c.type ASC, p.name ASC
");
$allProducts = $prodStmt->fetchAll();

// Index products by id
$productsMap = [];
foreach ($allProducts as $p) {
    $productsMap[(int) $p['id']] = $p;
}

// Pre-selections from URL query parameters
$preSelectedCustomerId = (int) ($_GET['customer_id'] ?? 0);
$preSelectedRxId       = (int) ($_GET['prescription_id'] ?? 0);

$error = '';
$formCustomerId      = $preSelectedCustomerId ?: '';
$formRxId            = $preSelectedRxId ?: '';
$formOrderDate       = date('Y-m-d');
$formDeliveryDate    = date('Y-m-d', strtotime('+3 days'));
$formNotes           = '';
$formDiscount        = 0.00;
$formPaymentAmount   = 0.00;
$formPaymentMethod   = 'Cash';
$formPaymentRef      = '';
$formPaymentNotes    = '';
$formItems           = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken         = $_POST['csrf_token'] ?? '';
    $formCustomerId    = (int) ($_POST['customer_id'] ?? 0);
    $formRxId          = !empty($_POST['prescription_id']) ? (int) $_POST['prescription_id'] : null;
    $formOrderDate     = trim($_POST['order_date'] ?? date('Y-m-d'));
    $formDeliveryDate  = !empty($_POST['delivery_date']) ? trim($_POST['delivery_date']) : null;
    $formNotes         = trim($_POST['notes'] ?? '');
    $formDiscount      = max(0.0, (float) ($_POST['discount'] ?? 0.0));
    $formPaymentAmount = max(0.0, (float) ($_POST['payment_amount'] ?? 0.0));
    $formPaymentMethod = trim($_POST['payment_method'] ?? 'Cash');
    $formPaymentRef    = trim($_POST['payment_reference'] ?? '');
    $formPaymentNotes  = trim($_POST['payment_notes'] ?? '');

    $itemProductIds = $_POST['product_id'] ?? [];
    $itemQuantities = $_POST['quantity'] ?? [];

    // Basic Validations
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Security session expired. Please submit the form again.';
    } elseif ($formCustomerId <= 0) {
        $error = 'Please select a valid customer.';
    } elseif (empty($formOrderDate)) {
        $error = 'Order date is required.';
    } elseif (empty($itemProductIds) || !is_array($itemProductIds) || count($itemProductIds) === 0) {
        $error = 'Please add at least one product item to the order.';
    } else {
        // Verify customer exists
        $custCheck = $pdo->prepare("SELECT id, full_name, status FROM customers WHERE id = :id LIMIT 1");
        $custCheck->execute(['id' => $formCustomerId]);
        $targetCustomer = $custCheck->fetch();

        if (!$targetCustomer) {
            $error = 'The selected customer does not exist in the database.';
        } elseif ($formRxId !== null && $formRxId > 0) {
            // Verify prescription exists and belongs to selected customer
            $rxCheck = $pdo->prepare("SELECT id, customer_id FROM prescriptions WHERE id = :id LIMIT 1");
            $rxCheck->execute(['id' => $formRxId]);
            $targetRx = $rxCheck->fetch();

            if (!$targetRx) {
                $error = 'The selected prescription does not exist.';
            } elseif ((int)$targetRx['customer_id'] !== $formCustomerId) {
                $error = 'Security violation: The selected prescription does not belong to the selected customer.';
            }
        }

        if (empty($error)) {
            // Validate product items and calculate authoritative server-side pricing
            $validatedItems = [];
            $computedSubtotal = 0.00;
            $hasLens = false;

            // Product ID validation and stock verification
            for ($i = 0; $i < count($itemProductIds); $i++) {
                $pId = (int) ($itemProductIds[$i] ?? 0);
                $qty = (int) ($itemQuantities[$i] ?? 0);

                if ($pId <= 0) {
                    continue; // Skip empty row
                }

                if ($qty <= 0) {
                    $error = 'Product quantity must be at least 1.';
                    break;
                }

                // Query database directly for authoritative price and current stock
                $pStmt = $pdo->prepare("
                    SELECT p.*, c.type AS category_type 
                    FROM products p 
                    JOIN categories c ON p.category_id = c.id 
                    WHERE p.id = :id AND p.status = 'active' 
                    LIMIT 1
                ");
                $pStmt->execute(['id' => $pId]);
                $dbProduct = $pStmt->fetch();

                if (!$dbProduct) {
                    $error = "One or more selected products are invalid or no longer active.";
                    break;
                }

                // Verify stock availability
                if ((int)$dbProduct['stock_quantity'] < $qty) {
                    $error = "Insufficient stock for \"" . e($dbProduct['name']) . "\". Available: " . (int)$dbProduct['stock_quantity'] . ", Requested: " . $qty . ".";
                    break;
                }

                if ($dbProduct['category_type'] === 'lens') {
                    $hasLens = true;
                }

                $unitPrice = (float) $dbProduct['selling_price'];
                $lineTotal = round($unitPrice * $qty, 2);
                $computedSubtotal += $lineTotal;

                $validatedItems[] = [
                    'product_id'   => (int) $dbProduct['id'],
                    'product_name' => $dbProduct['name'],
                    'product_code' => $dbProduct['product_code'],
                    'quantity'     => $qty,
                    'unit_price'   => $unitPrice,
                    'total_price'  => $lineTotal,
                ];
            }

            if (empty($error) && empty($validatedItems)) {
                $error = 'Please select at least one valid product item.';
            }

            // Check discount bounds
            if (empty($error)) {
                if ($formDiscount < 0) {
                    $error = 'Discount amount cannot be negative.';
                } elseif ($formDiscount > $computedSubtotal) {
                    $error = 'Discount amount cannot exceed the order subtotal (' . formatMoney($computedSubtotal) . ').';
                }
            }

            // Calculate Grand Total
            $computedGrandTotal = max(0.0, round($computedSubtotal - $formDiscount, 2));

            // Validate Initial Payment
            if (empty($error)) {
                if ($formPaymentAmount < 0) {
                    $error = 'Payment amount cannot be negative.';
                } elseif ($formPaymentAmount > $computedGrandTotal) {
                    $error = 'Initial payment amount cannot exceed the grand total (' . formatMoney($computedGrandTotal) . ').';
                }

                $allowedPaymentMethods = ['Cash', 'Card', 'Mobile Banking', 'Bank Transfer', 'Other'];
                if ($formPaymentAmount > 0 && !in_array($formPaymentMethod, $allowedPaymentMethods, true)) {
                    $error = 'Please select a valid payment method.';
                }
            }

            // If all checks pass, execute atomic database transaction
            if (empty($error)) {
                try {
                    $pdo->beginTransaction();

                    $orderCode = generateOrderCode($pdo);
                    $userId = currentUserId();
                    $initialPaid = $formPaymentAmount > 0 ? $formPaymentAmount : 0.00;
                    $initialDue  = round($computedGrandTotal - $initialPaid, 2);

                    // Insert Order Record
                    $orderInsertStmt = $pdo->prepare("
                        INSERT INTO orders (
                            order_code, customer_id, prescription_id, order_date,
                            subtotal, discount, grand_total, paid_amount, due_amount,
                            status, stock_deducted, delivery_date, notes, created_by, created_at
                        ) VALUES (
                            :order_code, :customer_id, :prescription_id, :order_date,
                            :subtotal, :discount, :grand_total, :paid_amount, :due_amount,
                            'pending', 0, :delivery_date, :notes, :created_by, NOW()
                        )
                    ");

                    $orderInsertStmt->execute([
                        'order_code'      => $orderCode,
                        'customer_id'     => $formCustomerId,
                        'prescription_id' => $formRxId ?: null,
                        'order_date'      => $formOrderDate,
                        'subtotal'        => $computedSubtotal,
                        'discount'        => $formDiscount,
                        'grand_total'     => $computedGrandTotal,
                        'paid_amount'     => $initialPaid,
                        'due_amount'      => $initialDue,
                        'delivery_date'   => $formDeliveryDate ?: null,
                        'notes'           => $formNotes ?: null,
                        'created_by'      => $userId,
                    ]);

                    $newOrderId = (int) $pdo->lastInsertId();

                    // Insert Order Items (with snapshot names and codes)
                    $itemInsertStmt = $pdo->prepare("
                        INSERT INTO order_items (
                            order_id, product_id, product_name, product_code,
                            quantity, unit_price, total_price, created_at
                        ) VALUES (
                            :order_id, :product_id, :product_name, :product_code,
                            :quantity, :unit_price, :total_price, NOW()
                        )
                    ");

                    foreach ($validatedItems as $item) {
                        $itemInsertStmt->execute([
                            'order_id'     => $newOrderId,
                            'product_id'   => $item['product_id'],
                            'product_name' => $item['product_name'],
                            'product_code' => $item['product_code'],
                            'quantity'     => $item['quantity'],
                            'unit_price'   => $item['unit_price'],
                            'total_price'  => $item['total_price'],
                        ]);
                    }

                    // Record Initial Payment if amount > 0
                    if ($initialPaid > 0) {
                        $payInsertStmt = $pdo->prepare("
                            INSERT INTO payments (
                                order_id, payment_date, amount, payment_method,
                                reference, notes, received_by, created_at
                            ) VALUES (
                                :order_id, :payment_date, :amount, :payment_method,
                                :reference, :notes, :received_by, NOW()
                            )
                        ");

                        $payInsertStmt->execute([
                            'order_id'       => $newOrderId,
                            'payment_date'   => $formOrderDate,
                            'amount'         => $initialPaid,
                            'payment_method' => $formPaymentMethod,
                            'reference'      => !empty($formPaymentRef) ? $formPaymentRef : null,
                            'notes'          => !empty($formPaymentNotes) ? $formPaymentNotes : 'Initial advance payment on order creation.',
                            'received_by'    => $userId,
                        ]);
                    }

                    // Commit Transaction
                    $pdo->commit();

                    setFlash('success', "Order <strong>{$orderCode}</strong> created successfully with status <strong>Pending</strong>.");
                    redirect('modules/orders/view.php?id=' . $newOrderId);

                } catch (Exception $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Order creation failed: ' . $ex->getMessage());
                    $error = 'An unexpected database error occurred while creating the order. Please try again.';
                }
            }
        }
    }
}
?>

<!-- Header Actions & Breadcrumbs -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold text-dark m-0">Create New Optical Order</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small mt-1">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/orders/index.php" class="text-decoration-none">Orders</a></li>
        <li class="breadcrumb-item active" aria-current="page">New Order</li>
      </ol>
    </nav>
  </div>
  <a href="<?= BASE_URL; ?>modules/orders/index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
    <i class="bi bi-arrow-left"></i> Back to Orders
  </a>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert">
    <i class="bi bi-exclamation-octagon-fill flex-shrink-0 me-2 fs-5"></i>
    <div class="flex-grow-1"><?= $error; ?></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL; ?>modules/orders/create.php" id="orderForm">
  <?= csrfField(); ?>

  <div class="row g-4">
    <!-- Left Column: Customer, Prescription & Order Items -->
    <div class="col-lg-8">
      
      <!-- Section 1 & 2: Customer & Prescription Selection -->
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
          <h6 class="m-0 fw-semibold text-dark">
            <i class="bi bi-person-bounding-box me-2 text-primary"></i> 1. Customer &amp; Optical Prescription
          </h6>
        </div>
        <div class="card-body p-4">
          <div class="row g-3">
            <!-- Customer Selector -->
            <div class="col-md-6">
              <label for="customer_id" class="form-label fw-semibold small text-dark">
                Customer <span class="text-danger">*</span>
              </label>
              <select class="form-select" id="customer_id" name="customer_id" required>
                <option value="">-- Select Customer --</option>
                <?php foreach ($allCustomers as $cust): ?>
                  <option value="<?= $cust['id']; ?>" <?= (int)$formCustomerId === (int)$cust['id'] ? 'selected' : ''; ?> data-code="<?= e($cust['customer_code']); ?>" data-phone="<?= e($cust['phone']); ?>">
                    <?= e($cust['full_name']); ?> (<?= e($cust['customer_code']); ?>) &bull; <?= e($cust['phone']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="d-flex justify-content-between align-items-center mt-1">
                <small class="text-muted">Select an existing optical customer.</small>
                <a href="<?= BASE_URL; ?>modules/customers/create.php" target="_blank" class="small text-primary text-decoration-none">
                  <i class="bi bi-person-plus"></i> New Customer
                </a>
              </div>
            </div>

            <!-- Prescription Selector (Dynamically populated based on Customer) -->
            <div class="col-md-6">
              <label for="prescription_id" class="form-label fw-semibold small text-dark">
                Prescription (Rx) <span class="text-muted fw-normal">(Optional for non-lens orders)</span>
              </label>
              <select class="form-select" id="prescription_id" name="prescription_id">
                <option value="">-- No Prescription Linked --</option>
              </select>
              <div id="rxHelpText" class="mt-1 small text-muted">
                Select customer first to view prescriptions.
              </div>
            </div>
          </div>

          <!-- Prescription Details Live Preview Box -->
          <div id="rxPreviewCard" class="mt-3 p-3 bg-light rounded border d-none">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="fw-bold small text-dark"><i class="bi bi-file-earmark-medical me-1 text-primary"></i> Prescription Refraction Summary</span>
              <span id="rxPreviewDoctor" class="small text-muted"></span>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-bordered bg-white text-center mb-0 small">
                <thead>
                  <tr class="table-light">
                    <th>Eye</th>
                    <th>SPH</th>
                    <th>CYL</th>
                    <th>AXIS</th>
                    <th>ADD</th>
                    <th>PD</th>
                  </tr>
                </thead>
                <tbody class="font-monospace">
                  <tr>
                    <td class="fw-bold text-primary">OD (Right)</td>
                    <td id="rxOdSph">-</td>
                    <td id="rxOdCyl">-</td>
                    <td id="rxOdAxis">-</td>
                    <td id="rxOdAdd">-</td>
                    <td id="rxOdPd">-</td>
                  </tr>
                  <tr>
                    <td class="fw-bold text-success">OS (Left)</td>
                    <td id="rxOsSph">-</td>
                    <td id="rxOsCyl">-</td>
                    <td id="rxOsAxis">-</td>
                    <td id="rxOsAdd">-</td>
                    <td id="rxOsPd">-</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 3: Products / Order Items -->
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
          <h6 class="m-0 fw-semibold text-dark">
            <i class="bi bi-box-seam-fill me-2 text-primary"></i> 2. Order Items &amp; Products
          </h6>
          <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" id="addItemBtn">
            <i class="bi bi-plus-circle"></i> Add Item
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-custom align-middle mb-0" id="itemsTable">
              <thead>
                <tr>
                  <th class="ps-3" style="width: 45%;">Product</th>
                  <th style="width: 15%;">Unit Price</th>
                  <th style="width: 15%;">Quantity</th>
                  <th class="text-end" style="width: 15%;">Total</th>
                  <th class="text-center" style="width: 10%;"></th>
                </tr>
              </thead>
              <tbody id="itemsContainer">
                <!-- Rows will be dynamically rendered via JavaScript -->
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Section 6: Delivery Date & Notes -->
      <div class="card shadow-sm mb-4 mb-lg-0">
        <div class="card-header bg-white py-3">
          <h6 class="m-0 fw-semibold text-dark">
            <i class="bi bi-calendar-event-fill me-2 text-primary"></i> 3. Schedule &amp; Order Remarks
          </h6>
        </div>
        <div class="card-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="order_date" class="form-label fw-semibold small text-dark">
                Order Date <span class="text-danger">*</span>
              </label>
              <input type="date" class="form-control" id="order_date" name="order_date" value="<?= e($formOrderDate); ?>" required>
            </div>
            <div class="col-md-6">
              <label for="delivery_date" class="form-label fw-semibold small text-dark">
                Expected Ready / Delivery Date
              </label>
              <input type="date" class="form-control" id="delivery_date" name="delivery_date" value="<?= e($formDeliveryDate); ?>">
            </div>
            <div class="col-12">
              <label for="notes" class="form-label fw-semibold small text-dark">
                Order Notes / Dispensing Instructions
              </label>
              <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Frame fitting adjustments, lens coatings, special customer instructions..."><?= e($formNotes); ?></textarea>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Right Column: Financial Summary & Initial Payment -->
    <div class="col-lg-4">
      
      <!-- Pricing & Totals Card -->
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
          <h6 class="m-0 fw-semibold text-dark">
            <i class="bi bi-calculator-fill me-2 text-primary"></i> 4. Pricing &amp; Totals
          </h6>
        </div>
        <div class="card-body p-4">
          <!-- Subtotal -->
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small">Subtotal:</span>
            <span class="fw-bold font-monospace fs-6 text-dark" id="displaySubtotal">$0.00</span>
          </div>

          <!-- Discount Input -->
          <div class="mb-3">
            <label for="discount" class="form-label small fw-semibold text-dark">Discount ($)</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light">$</span>
              <input type="number" step="0.01" min="0" class="form-control font-monospace text-end" id="discount" name="discount" value="<?= number_format((float)$formDiscount, 2, '.', ''); ?>">
            </div>
            <small class="text-muted" style="font-size: 0.72rem;">Fixed discount applied to subtotal.</small>
          </div>

          <hr class="my-3">

          <!-- Grand Total -->
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="fw-bold text-dark">Grand Total:</span>
            <span class="fw-bold font-monospace fs-4 text-primary" id="displayGrandTotal">$0.00</span>
          </div>
        </div>
      </div>

      <!-- Initial Payment Card -->
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
          <h6 class="m-0 fw-semibold text-dark">
            <i class="bi bi-cash-coin me-2 text-primary"></i> 5. Initial Payment
          </h6>
        </div>
        <div class="card-body p-4">
          <!-- Payment Amount Input -->
          <div class="mb-3">
            <label for="payment_amount" class="form-label small fw-semibold text-dark">
              Initial Advance Payment ($)
            </label>
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light">$</span>
              <input type="number" step="0.01" min="0" class="form-control font-monospace text-end" id="payment_amount" name="payment_amount" value="<?= number_format((float)$formPaymentAmount, 2, '.', ''); ?>">
            </div>
            <small class="text-muted" style="font-size: 0.72rem;">Enter $0.00 if creating order with no initial deposit.</small>
          </div>

          <!-- Payment Method -->
          <div class="mb-3" id="paymentMethodGroup">
            <label for="payment_method" class="form-label small fw-semibold text-dark">Payment Method</label>
            <select class="form-select form-select-sm" id="payment_method" name="payment_method">
              <option value="Cash" <?= $formPaymentMethod === 'Cash' ? 'selected' : ''; ?>>Cash</option>
              <option value="Card" <?= $formPaymentMethod === 'Card' ? 'selected' : ''; ?>>Card / POS</option>
              <option value="Mobile Banking" <?= $formPaymentMethod === 'Mobile Banking' ? 'selected' : ''; ?>>Mobile Banking (bKash/Nagad/Rocket)</option>
              <option value="Bank Transfer" <?= $formPaymentMethod === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
              <option value="Other" <?= $formPaymentMethod === 'Other' ? 'selected' : ''; ?>>Other</option>
            </select>
          </div>

          <!-- Payment Reference -->
          <div class="mb-3" id="paymentRefGroup">
            <label for="payment_reference" class="form-label small fw-semibold text-dark">Transaction Ref #</label>
            <input type="text" class="form-control form-control-sm" id="payment_reference" name="payment_reference" value="<?= e($formPaymentRef); ?>" placeholder="Txn ID, POS slip #, check #...">
          </div>

          <!-- Balance Due Indicator -->
          <div class="p-3 bg-light rounded border">
            <div class="d-flex justify-content-between align-items-center">
              <span class="small fw-semibold text-muted">Remaining Due:</span>
              <span class="fw-bold font-monospace fs-5 text-danger" id="displayDueAmount">$0.00</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Submit Buttons -->
      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary btn-lg shadow-sm">
          <i class="bi bi-check-circle-fill me-1"></i> Confirm &amp; Place Order
        </button>
        <a href="<?= BASE_URL; ?>modules/orders/index.php" class="btn btn-outline-secondary">
          Cancel
        </a>
      </div>

    </div>
  </div>
</form>

<!-- Pass JSON Data to JavaScript for Dynamic Selector Functionality -->
<script>
const productsCatalog = <?= json_encode($allProducts); ?>;
const prescriptionsData = <?= json_encode($prescriptionsByCustomer); ?>;
const preselectedCustomerId = <?= (int)$formCustomerId; ?>;
const preselectedRxId = <?= (int)$formRxId; ?>;

document.addEventListener('DOMContentLoaded', function () {
  const customerSelect = document.getElementById('customer_id');
  const rxSelect = document.getElementById('prescription_id');
  const rxHelpText = document.getElementById('rxHelpText');
  const rxPreviewCard = document.getElementById('rxPreviewCard');
  const rxPreviewDoctor = document.getElementById('rxPreviewDoctor');
  
  const itemsContainer = document.getElementById('itemsContainer');
  const addItemBtn = document.getElementById('addItemBtn');
  const discountInput = document.getElementById('discount');
  const paymentInput = document.getElementById('payment_amount');
  
  const displaySubtotal = document.getElementById('displaySubtotal');
  const displayGrandTotal = document.getElementById('displayGrandTotal');
  const displayDueAmount = document.getElementById('displayDueAmount');

  // Populate Prescription Dropdown based on Customer Selection
  function updatePrescriptions(customerId, selectedRxId = null) {
    rxSelect.innerHTML = '<option value="">-- No Prescription Linked --</option>';
    rxPreviewCard.classList.add('d-none');
    
    if (!customerId || !prescriptionsData[customerId] || prescriptionsData[customerId].length === 0) {
      if (customerId) {
        rxHelpText.innerHTML = '<span class="text-warning"><i class="bi bi-info-circle"></i> No prescription available for this customer.</span> <a href="<?= BASE_URL; ?>modules/prescriptions/create.php?customer_id=' + customerId + '" target="_blank" class="text-primary">Add Prescription</a>';
      } else {
        rxHelpText.innerText = 'Select customer first to view prescriptions.';
      }
      return;
    }

    const rxList = prescriptionsData[customerId];
    rxHelpText.innerHTML = '<span class="text-success"><i class="bi bi-check2"></i> ' + rxList.length + ' prescription(s) found.</span>';

    rxList.forEach(function (rx) {
      const opt = document.createElement('option');
      opt.value = rx.id;
      opt.text = 'Rx #' + rx.id + ' (' + rx.prescription_date + ') - Dr. ' + (rx.doctor_name || 'Staff');
      if (selectedRxId && parseInt(selectedRxId) === parseInt(rx.id)) {
        opt.selected = true;
      }
      rxSelect.appendChild(opt);
    });

    if (rxSelect.value) {
      showRxPreview(customerId, rxSelect.value);
    }
  }

  function showRxPreview(customerId, rxId) {
    if (!rxId || !prescriptionsData[customerId]) {
      rxPreviewCard.classList.add('d-none');
      return;
    }
    const rx = prescriptionsData[customerId].find(r => parseInt(r.id) === parseInt(rxId));
    if (!rx) {
      rxPreviewCard.classList.add('d-none');
      return;
    }

    document.getElementById('rxOdSph').innerText = rx.right_sph !== null ? (parseFloat(rx.right_sph) > 0 ? '+' : '') + parseFloat(rx.right_sph).toFixed(2) : '-';
    document.getElementById('rxOdCyl').innerText = rx.right_cyl !== null ? (parseFloat(rx.right_cyl) > 0 ? '+' : '') + parseFloat(rx.right_cyl).toFixed(2) : '-';
    document.getElementById('rxOdAxis').innerText = rx.right_axis !== null ? rx.right_axis + '°' : '-';
    document.getElementById('rxOdAdd').innerText = rx.right_add !== null ? (parseFloat(rx.right_add) > 0 ? '+' : '') + parseFloat(rx.right_add).toFixed(2) : '-';
    document.getElementById('rxOdPd').innerText = rx.right_pd !== null ? rx.right_pd + ' mm' : '-';

    document.getElementById('rxOsSph').innerText = rx.left_sph !== null ? (parseFloat(rx.left_sph) > 0 ? '+' : '') + parseFloat(rx.left_sph).toFixed(2) : '-';
    document.getElementById('rxOsCyl').innerText = rx.left_cyl !== null ? (parseFloat(rx.left_cyl) > 0 ? '+' : '') + parseFloat(rx.left_cyl).toFixed(2) : '-';
    document.getElementById('rxOsAxis').innerText = rx.left_axis !== null ? rx.left_axis + '°' : '-';
    document.getElementById('rxOsAdd').innerText = rx.left_add !== null ? (parseFloat(rx.left_add) > 0 ? '+' : '') + parseFloat(rx.left_add).toFixed(2) : '-';
    document.getElementById('rxOsPd').innerText = rx.left_pd !== null ? rx.left_pd + ' mm' : '-';

    rxPreviewDoctor.innerText = 'Doctor / Optician: ' + (rx.doctor_name || 'Dr. Sarah Connor');
    rxPreviewCard.classList.remove('d-none');
  }

  customerSelect.addEventListener('change', function () {
    updatePrescriptions(this.value);
  });

  rxSelect.addEventListener('change', function () {
    showRxPreview(customerSelect.value, this.value);
  });

  // Initial Customer & Rx preselection
  if (preselectedCustomerId > 0) {
    customerSelect.value = preselectedCustomerId;
    updatePrescriptions(preselectedCustomerId, preselectedRxId);
  }

  // --- Dynamic Order Items Management ---
  function createProductRow(selectedProdId = '', quantity = 1) {
    const tr = document.createElement('tr');
    tr.className = 'item-row';

    // Group products by Type
    let optionsHtml = '<option value="">-- Select Product --</option>';
    const frames = productsCatalog.filter(p => p.category_type === 'frame');
    const lenses = productsCatalog.filter(p => p.category_type === 'lens');
    const accessories = productsCatalog.filter(p => p.category_type === 'accessory');

    if (frames.length > 0) {
      optionsHtml += '<optgroup label="Frames &amp; Eyewear">';
      frames.forEach(p => {
        const isSel = parseInt(selectedProdId) === parseInt(p.id) ? 'selected' : '';
        optionsHtml += `<option value="${p.id}" data-price="${p.selling_price}" data-stock="${p.stock_quantity}" data-code="${p.product_code}" ${isSel}>
          ${p.name} (${p.product_code}) - $${parseFloat(p.selling_price).toFixed(2)} [Stock: ${p.stock_quantity}]
        </option>`;
      });
      optionsHtml += '</optgroup>';
    }

    if (lenses.length > 0) {
      optionsHtml += '<optgroup label="Prescription Lenses">';
      lenses.forEach(p => {
        const isSel = parseInt(selectedProdId) === parseInt(p.id) ? 'selected' : '';
        optionsHtml += `<option value="${p.id}" data-price="${p.selling_price}" data-stock="${p.stock_quantity}" data-code="${p.product_code}" ${isSel}>
          ${p.name} (${p.product_code}) - $${parseFloat(p.selling_price).toFixed(2)} [Stock: ${p.stock_quantity}]
        </option>`;
      });
      optionsHtml += '</optgroup>';
    }

    if (accessories.length > 0) {
      optionsHtml += '<optgroup label="Accessories &amp; Care">';
      accessories.forEach(p => {
        const isSel = parseInt(selectedProdId) === parseInt(p.id) ? 'selected' : '';
        optionsHtml += `<option value="${p.id}" data-price="${p.selling_price}" data-stock="${p.stock_quantity}" data-code="${p.product_code}" ${isSel}>
          ${p.name} (${p.product_code}) - $${parseFloat(p.selling_price).toFixed(2)} [Stock: ${p.stock_quantity}]
        </option>`;
      });
      optionsHtml += '</optgroup>';
    }

    tr.innerHTML = `
      <td class="ps-3">
        <select class="form-select form-select-sm product-select" name="product_id[]" required>
          ${optionsHtml}
        </select>
        <div class="small text-muted mt-1 product-meta"></div>
      </td>
      <td>
        <span class="font-monospace text-dark row-unit-price">$0.00</span>
      </td>
      <td>
        <input type="number" min="1" class="form-control form-control-sm font-monospace row-quantity" name="quantity[]" value="${quantity}" required>
      </td>
      <td class="text-end">
        <span class="font-monospace fw-bold text-dark row-total-price">$0.00</span>
      </td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn" title="Remove Item">
          <i class="bi bi-trash"></i>
        </button>
      </td>
    `;

    itemsContainer.appendChild(tr);

    // Row Event Listeners
    const prodSelect = tr.querySelector('.product-select');
    const qtyInput = tr.querySelector('.row-quantity');
    const removeBtn = tr.querySelector('.remove-row-btn');

    prodSelect.addEventListener('change', function () {
      updateRow(tr);
      calculateTotals();
    });

    qtyInput.addEventListener('input', function () {
      updateRow(tr);
      calculateTotals();
    });

    removeBtn.addEventListener('click', function () {
      if (itemsContainer.querySelectorAll('.item-row').length > 1) {
        tr.remove();
        calculateTotals();
      } else {
        alert('An order must contain at least one item.');
      }
    });

    updateRow(tr);
  }

  function updateRow(tr) {
    const prodSelect = tr.querySelector('.product-select');
    const qtyInput = tr.querySelector('.row-quantity');
    const unitPriceSpan = tr.querySelector('.row-unit-price');
    const totalPriceSpan = tr.querySelector('.row-total-price');
    const metaDiv = tr.querySelector('.product-meta');

    const selectedOption = prodSelect.options[prodSelect.selectedIndex];
    if (selectedOption && selectedOption.value) {
      const price = parseFloat(selectedOption.getAttribute('data-price') || 0);
      const stock = parseInt(selectedOption.getAttribute('data-stock') || 0);
      const code = selectedOption.getAttribute('data-code') || '';
      const qty = parseInt(qtyInput.value) || 1;

      qtyInput.max = stock > 0 ? stock : 1;
      unitPriceSpan.innerText = '$' + price.toFixed(2);
      totalPriceSpan.innerText = '$' + (price * qty).toFixed(2);

      let stockBadgeClass = stock > 5 ? 'text-success' : (stock > 0 ? 'text-warning' : 'text-danger');
      metaDiv.innerHTML = `<span class="${stockBadgeClass}"><i class="bi bi-box"></i> Available Stock: <strong>${stock}</strong></span>`;
    } else {
      unitPriceSpan.innerText = '$0.00';
      totalPriceSpan.innerText = '$0.00';
      metaDiv.innerHTML = '';
    }
  }

  function calculateTotals() {
    let subtotal = 0;
    const rows = itemsContainer.querySelectorAll('.item-row');

    rows.forEach(tr => {
      const prodSelect = tr.querySelector('.product-select');
      const qtyInput = tr.querySelector('.row-quantity');
      const selectedOption = prodSelect.options[prodSelect.selectedIndex];
      if (selectedOption && selectedOption.value) {
        const price = parseFloat(selectedOption.getAttribute('data-price') || 0);
        const qty = parseInt(qtyInput.value) || 0;
        subtotal += (price * qty);
      }
    });

    let discount = parseFloat(discountInput.value) || 0;
    if (discount < 0) {
      discount = 0;
      discountInput.value = '0.00';
    }
    if (discount > subtotal) {
      discount = subtotal;
      discountInput.value = subtotal.toFixed(2);
    }

    const grandTotal = Math.max(0, subtotal - discount);
    
    let payment = parseFloat(paymentInput.value) || 0;
    if (payment < 0) {
      payment = 0;
      paymentInput.value = '0.00';
    }
    if (payment > grandTotal) {
      payment = grandTotal;
      paymentInput.value = grandTotal.toFixed(2);
    }

    const dueAmount = Math.max(0, grandTotal - payment);

    displaySubtotal.innerText = '$' + subtotal.toFixed(2);
    displayGrandTotal.innerText = '$' + grandTotal.toFixed(2);
    displayDueAmount.innerText = '$' + dueAmount.toFixed(2);
  }

  addItemBtn.addEventListener('click', function () {
    createProductRow();
  });

  discountInput.addEventListener('input', calculateTotals);
  paymentInput.addEventListener('input', calculateTotals);

  // Initialize with one empty product row
  createProductRow();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
