<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Printable Order Invoice & Optical Dispensing Slip
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Authentication check
requireLogin();

$pdo = getDbConnection();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Invalid order identifier.');
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
    die('Order record not found.');
}

// Fetch Order Items
$itemsStmt = $pdo->prepare("
    SELECT oi.*, p.brand, c.name AS category_name, c.type AS category_type
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE oi.order_id = :order_id
    ORDER BY oi.id ASC
");
$itemsStmt->execute(['order_id' => $id]);
$orderItems = $itemsStmt->fetchAll();

// Fetch Payments
$payStmt = $pdo->prepare("
    SELECT pm.*, u.name AS received_by_name
    FROM payments pm
    LEFT JOIN users u ON pm.received_by = u.id
    WHERE pm.order_id = :order_id
    ORDER BY pm.payment_date ASC, pm.id ASC
");
$payStmt->execute(['order_id' => $id]);
$payments = $payStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice &mdash; <?= e($order['order_code']); ?> &mdash; <?= e(APP_NAME); ?></title>
  
  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: #f1f5f9;
      color: #0f172a;
      margin: 0;
      padding: 2rem 0;
    }

    .invoice-wrapper {
      max-width: 850px;
      margin: 0 auto;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 2.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .invoice-header {
      border-bottom: 2px solid #0f172a;
      padding-bottom: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .shop-logo-box {
      width: 44px;
      height: 44px;
      background-color: #0284c7;
      color: #ffffff;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }

    .table-invoice th {
      background-color: #f8fafc;
      color: #475569;
      font-weight: 600;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      border-bottom: 2px solid #cbd5e1;
      padding: 0.6rem 0.75rem;
    }

    .table-invoice td {
      padding: 0.65rem 0.75rem;
      vertical-align: middle;
      border-bottom: 1px solid #e2e8f0;
    }

    .table-rx-print th, .table-rx-print td {
      padding: 0.4rem 0.5rem;
      font-size: 0.85rem;
      text-align: center;
    }

    .total-highlight-row {
      background-color: #f8fafc;
      font-weight: 700;
    }

    .signature-box {
      border-top: 1px dashed #94a3b8;
      padding-top: 0.5rem;
      margin-top: 3.5rem;
      text-align: center;
      font-size: 0.8rem;
      color: #475569;
    }

    @media print {
      body {
        background-color: #ffffff !important;
        padding: 0 !important;
      }
      .no-print {
        display: none !important;
      }
      .invoice-wrapper {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        max-width: 100% !important;
      }
      .invoice-header {
        border-bottom: 2px solid #000000 !important;
      }
      @page {
        margin: 1.5cm;
        size: auto;
      }
    }
  </style>
</head>
<body>

<!-- Top Floating Action Toolbar (Hidden in Print) -->
<div class="container mb-3 no-print" style="max-width: 850px;">
  <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded border shadow-sm">
    <div class="d-flex align-items-center gap-2">
      <a href="<?= BASE_URL; ?>modules/orders/view.php?id=<?= $order['id']; ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Order
      </a>
      <span class="text-muted small">Viewing Invoice <strong><?= e($order['order_code']); ?></strong></span>
    </div>
    <div class="d-flex gap-2">
      <button type="button" onclick="window.print();" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm">
        <i class="bi bi-printer-fill"></i> Print / Save PDF
      </button>
    </div>
  </div>
</div>

<div class="invoice-wrapper">
  
  <!-- Invoice Header -->
  <div class="invoice-header d-flex justify-content-between align-items-start">
    <div class="d-flex align-items-center gap-3">
      <div class="shop-logo-box">
        <i class="bi bi-eyeglasses"></i>
      </div>
      <div>
        <h3 class="fw-bold m-0 text-dark" style="letter-spacing: -0.02em;">VisionCare Optical</h3>
        <p class="text-muted small m-0">Modern Eye Care &bull; Precision Optical Dispensing</p>
        <small class="text-secondary" style="font-size: 0.75rem;">House 24, Road 5, Dhanmondi, Dhaka &bull; Tel: +880 1711-234567</small>
      </div>
    </div>
    <div class="text-end">
      <h4 class="fw-bold font-monospace text-primary m-0"><?= e($order['order_code']); ?></h4>
      <div class="badge <?= $order['status'] === 'delivered' ? 'bg-success' : ($order['status'] === 'cancelled' ? 'bg-danger' : 'bg-primary'); ?> text-uppercase mt-1" style="letter-spacing: 0.05em; font-size: 0.75rem;">
        <?= ucfirst(e($order['status'])); ?>
      </div>
      <div class="text-muted small mt-1">Date: <strong><?= date('M d, Y', strtotime($order['order_date'])); ?></strong></div>
      <?php if (!empty($order['delivery_date'])): ?>
        <div class="text-muted small">Ready Date: <strong><?= date('M d, Y', strtotime($order['delivery_date'])); ?></strong></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Customer & Order Meta Information -->
  <div class="row g-3 mb-4">
    <div class="col-6">
      <span class="text-uppercase text-secondary fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Billed To (Customer):</span>
      <h6 class="fw-bold text-dark mt-1 mb-1"><?= e($order['customer_name']); ?></h6>
      <div class="small text-muted"><i class="bi bi-person-badge me-1"></i> Code: <?= e($order['customer_code']); ?></div>
      <div class="small text-muted"><i class="bi bi-telephone me-1"></i> Phone: <?= e($order['customer_phone']); ?></div>
      <?php if (!empty($order['customer_email'])): ?>
        <div class="small text-muted"><i class="bi bi-envelope me-1"></i> Email: <?= e($order['customer_email']); ?></div>
      <?php endif; ?>
      <?php if (!empty($order['customer_address'])): ?>
        <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i> Address: <?= e($order['customer_address']); ?></div>
      <?php endif; ?>
    </div>
    
    <div class="col-6 text-end">
      <span class="text-uppercase text-secondary fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Dispensing Information:</span>
      <div class="small text-muted mt-1">Order Status: <strong class="text-dark"><?= ucfirst(e($order['status'])); ?></strong></div>
      <div class="small text-muted">Created By: <strong class="text-dark"><?= !empty($order['created_by_name']) ? e($order['created_by_name']) : 'Staff'; ?></strong></div>
      <?php if (!empty($order['prescription_id'])): ?>
        <div class="small text-muted">Refraction Rx: <strong class="text-dark">Rx #<?= $order['prescription_id']; ?> (<?= e($order['doctor_name'] ?: 'Dr. Sarah Connor'); ?>)</strong></div>
      <?php else: ?>
        <div class="small text-muted">Refraction Rx: <em>Non-Prescription / Accessory</em></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Linked Prescription Summary (If applicable) -->
  <?php if (!empty($order['prescription_id'])): ?>
    <div class="mb-4">
      <div class="fw-bold small text-dark mb-2 text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">
        <i class="bi bi-file-earmark-medical me-1 text-primary"></i> Prescription Refraction Record (Rx #<?= $order['prescription_id']; ?>)
      </div>
      <table class="table table-sm table-bordered table-rx-print mb-0 bg-white" style="border-color: #cbd5e1;">
        <thead style="background-color: #f8fafc;">
          <tr>
            <th class="text-start ps-3" style="width: 25%;">Eye (Oculus)</th>
            <th style="width: 15%;">SPH</th>
            <th style="width: 15%;">CYL</th>
            <th style="width: 15%;">AXIS</th>
            <th style="width: 15%;">ADD</th>
            <th style="width: 15%;">PD (mm)</th>
          </tr>
        </thead>
        <tbody class="font-monospace">
          <tr>
            <td class="text-start ps-3 font-sans-serif fw-bold text-primary">Right Eye (OD)</td>
            <td class="fw-bold"><?= formatOpticalPower($order['right_sph']); ?></td>
            <td><?= formatOpticalPower($order['right_cyl']); ?></td>
            <td><?= formatAxis($order['right_axis']); ?></td>
            <td><?= formatOpticalPower($order['right_add']); ?></td>
            <td><?= formatPd($order['right_pd']); ?></td>
          </tr>
          <tr>
            <td class="text-start ps-3 font-sans-serif fw-bold text-success">Left Eye (OS)</td>
            <td class="fw-bold"><?= formatOpticalPower($order['left_sph']); ?></td>
            <td><?= formatOpticalPower($order['left_cyl']); ?></td>
            <td><?= formatAxis($order['left_axis']); ?></td>
            <td><?= formatOpticalPower($order['left_add']); ?></td>
            <td><?= formatPd($order['left_pd']); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- Items Table -->
  <div class="mb-4">
    <table class="table table-invoice mb-0">
      <thead>
        <tr>
          <th style="width: 8%;">#</th>
          <th style="width: 48%;">Item Description</th>
          <th class="text-center" style="width: 14%;">Type</th>
          <th class="text-end" style="width: 15%;">Unit Price</th>
          <th class="text-center" style="width: 15%;">Qty</th>
          <th class="text-end" style="width: 20%;">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php $idx = 1; foreach ($orderItems as $item): ?>
          <tr>
            <td class="text-muted small"><?= $idx++; ?></td>
            <td>
              <div class="fw-bold text-dark"><?= e($item['product_name']); ?></div>
              <small class="font-monospace text-secondary"><?= e($item['product_code']); ?></small>
              <?php if (!empty($item['brand'])): ?>
                <small class="text-muted">&bull; <?= e($item['brand']); ?></small>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <span class="badge bg-light text-dark border small"><?= ucfirst(e($item['category_type'] ?? 'Item')); ?></span>
            </td>
            <td class="text-end font-monospace"><?= formatMoney($item['unit_price']); ?></td>
            <td class="text-center font-monospace fw-bold"><?= (int)$item['quantity']; ?></td>
            <td class="text-end font-monospace fw-bold text-dark"><?= formatMoney($item['total_price']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Financial Totals & Payment Breakdown -->
  <div class="row g-4 mb-4">
    <div class="col-6">
      <!-- Payment Records Table -->
      <span class="text-uppercase text-secondary fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Payment Transactions</span>
      <?php if (empty($payments)): ?>
        <div class="p-3 bg-light rounded border text-muted small mt-1">No payment transactions recorded yet.</div>
      <?php else: ?>
        <table class="table table-sm table-borderless small mt-1 mb-0">
          <thead>
            <tr class="border-bottom">
              <th>Date</th>
              <th>Method</th>
              <th>Ref #</th>
              <th class="text-end">Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $pm): ?>
              <tr>
                <td><?= date('M d, Y', strtotime($pm['payment_date'])); ?></td>
                <td><?= e($pm['payment_method']); ?></td>
                <td class="font-monospace text-muted"><?= !empty($pm['reference']) ? e($pm['reference']) : '&mdash;'; ?></td>
                <td class="text-end font-monospace fw-bold text-success"><?= formatMoney($pm['amount']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if (!empty($order['notes'])): ?>
        <div class="mt-3">
          <span class="text-uppercase text-secondary fw-bold small" style="font-size: 0.7rem; letter-spacing: 0.05em;">Order Notes:</span>
          <div class="p-2 bg-light rounded border small text-dark mt-1">
            <?= nl2br(e($order['notes'])); ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-6">
      <div class="p-3 bg-light rounded border">
        <div class="d-flex justify-content-between py-1 small">
          <span class="text-muted">Subtotal:</span>
          <span class="fw-bold font-monospace text-dark"><?= formatMoney($order['subtotal']); ?></span>
        </div>
        <div class="d-flex justify-content-between py-1 small border-bottom pb-2">
          <span class="text-muted">Discount:</span>
          <span class="fw-semibold font-monospace text-danger">- <?= formatMoney($order['discount']); ?></span>
        </div>
        <div class="d-flex justify-content-between py-2 border-bottom">
          <span class="fw-bold text-dark fs-6">Grand Total:</span>
          <span class="fw-bold font-monospace fs-5 text-primary"><?= formatMoney($order['grand_total']); ?></span>
        </div>
        <div class="d-flex justify-content-between py-1 small">
          <span class="text-muted">Total Paid Amount:</span>
          <span class="fw-bold font-monospace text-success"><?= formatMoney($order['paid_amount']); ?></span>
        </div>
        <div class="d-flex justify-content-between py-1 border-top pt-2">
          <span class="fw-bold text-dark fs-6">Balance Due:</span>
          <span class="fw-bold font-monospace fs-5 <?= (float)$order['due_amount'] > 0 ? 'text-danger' : 'text-success'; ?>">
            <?= formatMoney($order['due_amount']); ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Terms and Optical Care Notice -->
  <div class="small text-muted border-top pt-3 mb-4" style="font-size: 0.75rem; line-height: 1.4;">
    <strong>Notice:</strong> Prescription optical lenses are precision manufactured and customized specifically for the patient. Please inspect your frames and lenses upon collection. 30-day adaptation guarantee applies on optical prescriptions.
  </div>

  <!-- Signature Lines -->
  <div class="row g-4 mt-2">
    <div class="col-6">
      <div class="signature-box">
        Customer Signature &amp; Acceptance
      </div>
    </div>
    <div class="col-6">
      <div class="signature-box">
        Authorized Dispenser / VisionCare Optical
      </div>
    </div>
  </div>

</div>

</body>
</html>
