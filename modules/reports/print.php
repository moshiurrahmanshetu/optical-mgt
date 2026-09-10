<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Universal Printable Report View (Print / PDF)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

$pdo = getDbConnection();
$currentUser = currentUser();

$type = trim($_GET['type'] ?? 'sales');
$validTypes = ['sales', 'payments', 'due', 'orders', 'customers', 'products', 'prescriptions', 'overview'];

if (!in_array($type, $validTypes, true)) {
    $type = 'sales';
}

// Permissions
if ($type === 'prescriptions') {
    requireRole(['admin', 'optician']);
} else {
    requireRole(['admin', 'optician', 'sales_staff']);
}

// Common Filter Handling
$period     = trim($_GET['period'] ?? 'this_month');
$customFrom = trim($_GET['date_from'] ?? '');
$customTo   = trim($_GET['date_to'] ?? '');
$dateInfo   = parseDatePeriod($period, $customFrom, $customTo);
$dateFrom   = $dateInfo['from'];
$dateTo     = $dateInfo['to'];
$periodLabel = $dateInfo['label'];

$reportTitle = 'Report';
$summaryData = [];
$records     = [];

// -------------------------------------------------------------
// 1. SALES REPORT
// -------------------------------------------------------------
if ($type === 'sales') {
    $reportTitle = 'Sales & Revenue Summary Report';
    $statusFilter = trim($_GET['status'] ?? 'all');
    $search       = trim($_GET['q'] ?? '');

    $where = [];
    $params = [];
    if ($dateFrom) {
        $where[] = "o.order_date >= :date_from";
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = "o.order_date <= :date_to";
        $params[':date_to'] = $dateTo;
    }
    if ($statusFilter !== '' && $statusFilter !== 'all') {
        $where[] = "o.status = :status";
        $params[':status'] = $statusFilter;
    }
    if ($search !== '') {
        $where[] = "(o.order_code LIKE :search OR c.full_name LIKE :search OR c.phone LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Summary
    $sumStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_count,
            SUM(CASE WHEN o.status != 'cancelled' THEN o.subtotal ELSE 0 END) AS total_subtotal,
            SUM(CASE WHEN o.status != 'cancelled' THEN o.discount ELSE 0 END) AS total_discount,
            SUM(CASE WHEN o.status != 'cancelled' THEN o.grand_total ELSE 0 END) AS net_sales,
            SUM(CASE WHEN o.status != 'cancelled' THEN o.paid_amount ELSE 0 END) AS total_paid,
            SUM(CASE WHEN o.status != 'cancelled' THEN o.due_amount ELSE 0 END) AS total_due,
            SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        {$whereSql}
    ");
    foreach ($params as $k => $v) $sumStmt->bindValue($k, $v);
    $sumStmt->execute();
    $summaryData = $sumStmt->fetch();

    // Rows
    $stmt = $pdo->prepare("
        SELECT o.order_code, o.order_date, c.full_name AS customer_name, c.phone AS customer_phone,
               o.subtotal, o.discount, o.grand_total, o.paid_amount, o.due_amount, o.status, o.payment_status
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        {$whereSql}
        ORDER BY o.order_date DESC, o.id DESC
        LIMIT 500
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $records = $stmt->fetchAll();
}

// -------------------------------------------------------------
// 2. PAYMENTS REPORT
// -------------------------------------------------------------
elseif ($type === 'payments') {
    $reportTitle = 'Payment Collections & Cash Flow Report';
    $methodFilter = trim($_GET['method'] ?? 'all');
    $search       = trim($_GET['q'] ?? '');

    $where = [];
    $params = [];
    if ($dateFrom) {
        $where[] = "p.payment_date >= :date_from";
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = "p.payment_date <= :date_to";
        $params[':date_to'] = $dateTo;
    }
    if ($methodFilter !== '' && $methodFilter !== 'all') {
        $where[] = "p.payment_method = :pmethod";
        $params[':pmethod'] = $methodFilter;
    }
    if ($search !== '') {
        $where[] = "(p.receipt_number LIKE :search OR o.order_code LIKE :search OR c.full_name LIKE :search OR p.transaction_reference LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sumStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_transactions,
            SUM(p.amount) AS total_collected,
            SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount ELSE 0 END) AS cash_total,
            SUM(CASE WHEN p.payment_method = 'card' THEN p.amount ELSE 0 END) AS card_total,
            SUM(CASE WHEN p.payment_method = 'mobile_banking' THEN p.amount ELSE 0 END) AS mobile_total,
            SUM(CASE WHEN p.payment_method = 'bank_transfer' THEN p.amount ELSE 0 END) AS transfer_total
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        JOIN customers c ON o.customer_id = c.id
        {$whereSql}
    ");
    foreach ($params as $k => $v) $sumStmt->bindValue($k, $v);
    $sumStmt->execute();
    $summaryData = $sumStmt->fetch();

    $stmt = $pdo->prepare("
        SELECT p.receipt_number, p.payment_date, p.amount, p.payment_method, p.transaction_reference,
               o.order_code, c.full_name AS customer_name, c.phone AS customer_phone, u.name AS staff_name
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        JOIN customers c ON o.customer_id = c.id
        LEFT JOIN users u ON p.received_by = u.id
        {$whereSql}
        ORDER BY p.payment_date DESC, p.id DESC
        LIMIT 500
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $records = $stmt->fetchAll();
}

// -------------------------------------------------------------
// 3. DUE BALANCE REPORT
// -------------------------------------------------------------
elseif ($type === 'due') {
    $reportTitle = 'Outstanding Customer Due Balances Report';
    $search = trim($_GET['q'] ?? '');

    $where = ["o.due_amount > 0", "o.status != 'cancelled'"];
    $params = [];
    if ($dateFrom) {
        $where[] = "o.order_date >= :date_from";
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = "o.order_date <= :date_to";
        $params[':date_to'] = $dateTo;
    }
    if ($search !== '') {
        $where[] = "(o.order_code LIKE :search OR c.full_name LIKE :search OR c.phone LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $sumStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_due_orders,
            COUNT(DISTINCT o.customer_id) AS total_due_customers,
            SUM(o.grand_total) AS total_order_value,
            SUM(o.paid_amount) AS total_paid_value,
            SUM(o.due_amount) AS total_outstanding_due
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        {$whereSql}
    ");
    foreach ($params as $k => $v) $sumStmt->bindValue($k, $v);
    $sumStmt->execute();
    $summaryData = $sumStmt->fetch();

    $stmt = $pdo->prepare("
        SELECT o.order_code, o.order_date, o.delivery_date, o.grand_total, o.paid_amount, o.due_amount, o.status,
               c.customer_code, c.full_name AS customer_name, c.phone AS customer_phone
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        {$whereSql}
        ORDER BY o.due_amount DESC, o.order_date DESC
        LIMIT 500
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $records = $stmt->fetchAll();
}

// -------------------------------------------------------------
// 4. ORDERS WORKFLOW REPORT
// -------------------------------------------------------------
elseif ($type === 'orders') {
    $reportTitle = 'Order Workflow & Status Distribution Report';
    $statusFilter = trim($_GET['status'] ?? 'all');
    $search       = trim($_GET['q'] ?? '');

    $where = [];
    $params = [];
    if ($dateFrom) {
        $where[] = "o.order_date >= :date_from";
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = "o.order_date <= :date_to";
        $params[':date_to'] = $dateTo;
    }
    if ($statusFilter !== '' && $statusFilter !== 'all') {
        $where[] = "o.status = :status";
        $params[':status'] = $statusFilter;
    }
    if ($search !== '') {
        $where[] = "(o.order_code LIKE :search OR c.full_name LIKE :search OR c.phone LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sumStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_count,
            SUM(o.grand_total) AS total_value,
            SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) AS count_pending,
            SUM(CASE WHEN o.status = 'confirmed' THEN 1 ELSE 0 END) AS count_confirmed,
            SUM(CASE WHEN o.status = 'processing' THEN 1 ELSE 0 END) AS count_processing,
            SUM(CASE WHEN o.status = 'ready' THEN 1 ELSE 0 END) AS count_ready,
            SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) AS count_delivered,
            SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS count_cancelled
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        {$whereSql}
    ");
    foreach ($params as $k => $v) $sumStmt->bindValue($k, $v);
    $sumStmt->execute();
    $summaryData = $sumStmt->fetch();

    $stmt = $pdo->prepare("
        SELECT o.order_code, o.order_date, o.delivery_date, o.grand_total, o.paid_amount, o.due_amount,
               o.status, o.payment_status, c.full_name AS customer_name, c.phone AS customer_phone
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        {$whereSql}
        ORDER BY o.order_date DESC, o.id DESC
        LIMIT 500
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $records = $stmt->fetchAll();
}

// -------------------------------------------------------------
// 5. CUSTOMERS REPORT
// -------------------------------------------------------------
elseif ($type === 'customers') {
    $reportTitle = 'Customer Growth & Patient Demographics Report';
    $search = trim($_GET['q'] ?? '');

    $where = [];
    $params = [];
    if ($dateFrom) {
        $where[] = "c.created_at >= :date_from";
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo) {
        $where[] = "c.created_at <= :date_to";
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }
    if ($search !== '') {
        $where[] = "(c.customer_code LIKE :search OR c.full_name LIKE :search OR c.phone LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sumStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS period_count,
            SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN c.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count
        FROM customers c
        {$whereSql}
    ");
    foreach ($params as $k => $v) $sumStmt->bindValue($k, $v);
    $sumStmt->execute();
    $summaryData = $sumStmt->fetch();

    $stmt = $pdo->prepare("
        SELECT c.customer_code, c.full_name, c.phone, c.gender, c.created_at, c.status,
               (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.status != 'cancelled') AS total_orders,
               COALESCE((SELECT SUM(o.grand_total) FROM orders o WHERE o.customer_id = c.id AND o.status != 'cancelled'), 0) AS total_spent,
               COALESCE((SELECT SUM(o.due_amount) FROM orders o WHERE o.customer_id = c.id AND o.status != 'cancelled'), 0) AS total_due
        FROM customers c
        {$whereSql}
        ORDER BY c.created_at DESC
        LIMIT 500
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $records = $stmt->fetchAll();
}

// -------------------------------------------------------------
// 6. PRODUCTS REPORT
// -------------------------------------------------------------
elseif ($type === 'products') {
    $reportTitle = 'Inventory & Stock Valuation Report';
    $catFilter   = trim($_GET['category_id'] ?? 'all');
    $stockStatus = trim($_GET['stock_status'] ?? 'all');
    $search      = trim($_GET['q'] ?? '');

    $where = [];
    $params = [];
    if ($catFilter !== '' && $catFilter !== 'all') {
        $where[] = "p.category_id = :cat_id";
        $params[':cat_id'] = (int) $catFilter;
    }
    if ($stockStatus === 'low_stock') {
        $where[] = "p.stock_quantity <= p.low_stock_threshold AND p.stock_quantity > 0";
    } elseif ($stockStatus === 'out_of_stock') {
        $where[] = "p.stock_quantity <= 0";
    }
    if ($search !== '') {
        $where[] = "(p.product_code LIKE :search OR p.name LIKE :search OR p.brand LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sumStmt = $pdo->prepare("
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
    ");
    foreach ($params as $k => $v) $sumStmt->bindValue($k, $v);
    $sumStmt->execute();
    $summaryData = $sumStmt->fetch();

    $stmt = $pdo->prepare("
        SELECT p.product_code, p.name, p.brand, p.purchase_price, p.selling_price, p.stock_quantity, p.low_stock_threshold,
               c.name AS category_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        {$whereSql}
        ORDER BY p.stock_quantity ASC, p.name ASC
        LIMIT 500
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $records = $stmt->fetchAll();
}

// -------------------------------------------------------------
// 7. PRESCRIPTIONS REPORT
// -------------------------------------------------------------
elseif ($type === 'prescriptions') {
    $reportTitle = 'Clinical Examinations & Refraction Log Report';
    $search = trim($_GET['q'] ?? '');

    $where = [];
    $params = [];
    if ($dateFrom) {
        $where[] = "p.prescription_date >= :date_from";
        $params[':date_from'] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = "p.prescription_date <= :date_to";
        $params[':date_to'] = $dateTo;
    }
    if ($search !== '') {
        $where[] = "(c.full_name LIKE :search OR c.phone LIKE :search OR p.doctor_name LIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sumStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_prescriptions,
            COUNT(DISTINCT p.customer_id) AS unique_patients
        FROM prescriptions p
        JOIN customers c ON p.customer_id = c.id
        {$whereSql}
    ");
    foreach ($params as $k => $v) $sumStmt->bindValue($k, $v);
    $sumStmt->execute();
    $summaryData = $sumStmt->fetch();

    $stmt = $pdo->prepare("
        SELECT p.id, p.prescription_date, p.right_sph, p.right_cyl, p.right_axis, p.left_sph, p.left_cyl, p.left_axis, p.right_pd, p.left_pd,
               p.doctor_name, c.customer_code, c.full_name AS customer_name, c.phone AS customer_phone
        FROM prescriptions p
        JOIN customers c ON p.customer_id = c.id
        {$whereSql}
        ORDER BY p.prescription_date DESC
        LIMIT 500
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $records = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($reportTitle) ?> — VisionCare Optical</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            font-size: 13px;
        }

        .report-wrapper {
            max-width: 1040px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px 40px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
        }

        .report-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 10px 15px;
            border-radius: 4px;
        }

        .table-report th {
            background-color: #f1f5f9 !important;
            color: #0f172a;
            font-weight: 600;
            border-bottom: 1px solid #cbd5e1;
            padding: 6px 8px;
        }

        .table-report td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        .signature-line {
            border-top: 1px solid #0f172a;
            margin-top: 50px;
            padding-top: 5px;
            text-align: center;
            font-size: 11px;
            font-weight: 600;
        }

        @media print {
            body {
                background: #ffffff !important;
                font-size: 11px;
            }
            .report-wrapper {
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
            .table-report th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<!-- Control Bar -->
<div class="no-print bg-dark py-2 px-4 text-white d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-3">
        <span class="fw-bold"><i class="bi bi-file-earmark-bar-graph me-1"></i> <?= e($reportTitle) ?></span>
        <span class="badge bg-secondary"><?= e($periodLabel) ?></span>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-sm btn-primary">
            <i class="bi bi-printer me-1"></i> Print / Save PDF
        </button>
        <button onclick="window.close()" class="btn btn-sm btn-outline-light">
            Close
        </button>
    </div>
</div>

<div class="report-wrapper">
    <!-- Header -->
    <div class="report-header d-flex justify-content-between align-items-start">
        <div>
            <h2 class="fw-bold text-dark mb-0">VisionCare Optical CMS</h2>
            <div class="text-muted small">Eye Care Clinic &amp; Optical Dispensary</div>
            <div class="text-muted small">Generated on: <?= date('F d, Y \a\t h:i A') ?></div>
        </div>
        <div class="text-end">
            <h4 class="fw-bold text-primary mb-1"><?= e($reportTitle) ?></h4>
            <div class="badge bg-light text-dark border px-2 py-1">Period: <?= e($periodLabel) ?></div>
            <div class="text-muted small mt-1">Generated by: <?= e($currentUser['name'] ?? 'Staff') ?></div>
        </div>
    </div>

    <!-- Summary Box -->
    <?php if (!empty($summaryData)): ?>
        <div class="summary-box mb-4">
            <div class="row g-2 text-center">
                <?php if ($type === 'sales'): ?>
                    <div class="col">
                        <div class="text-muted small">Total Orders</div>
                        <div class="fw-bold fs-6"><?= number_format((int)$summaryData['total_count']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Subtotal</div>
                        <div class="fw-bold fs-6 font-monospace"><?= formatMoney((float)$summaryData['total_subtotal']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Total Discounts</div>
                        <div class="fw-bold fs-6 text-danger font-monospace"><?= formatMoney((float)$summaryData['total_discount']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Net Sales</div>
                        <div class="fw-bold fs-6 text-primary font-monospace"><?= formatMoney((float)$summaryData['net_sales']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Collected</div>
                        <div class="fw-bold fs-6 text-success font-monospace"><?= formatMoney((float)$summaryData['total_paid']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Outstanding Due</div>
                        <div class="fw-bold fs-6 text-danger font-monospace"><?= formatMoney((float)$summaryData['total_due']) ?></div>
                    </div>

                <?php elseif ($type === 'payments'): ?>
                    <div class="col">
                        <div class="text-muted small">Total Transactions</div>
                        <div class="fw-bold fs-6"><?= number_format((int)$summaryData['total_transactions']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Cash Collections</div>
                        <div class="fw-bold fs-6 font-monospace"><?= formatMoney((float)$summaryData['cash_total']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Card Collections</div>
                        <div class="fw-bold fs-6 font-monospace"><?= formatMoney((float)$summaryData['card_total']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Mobile Banking</div>
                        <div class="fw-bold fs-6 font-monospace"><?= formatMoney((float)$summaryData['mobile_total']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Total Collected</div>
                        <div class="fw-bold fs-6 text-success font-monospace"><?= formatMoney((float)$summaryData['total_collected']) ?></div>
                    </div>

                <?php elseif ($type === 'due'): ?>
                    <div class="col">
                        <div class="text-muted small">Pending Invoices</div>
                        <div class="fw-bold fs-6"><?= number_format((int)$summaryData['total_due_orders']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Customers with Due</div>
                        <div class="fw-bold fs-6"><?= number_format((int)$summaryData['total_due_customers']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Invoiced Amount</div>
                        <div class="fw-bold fs-6 font-monospace"><?= formatMoney((float)$summaryData['total_order_value']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Already Paid</div>
                        <div class="fw-bold fs-6 text-success font-monospace"><?= formatMoney((float)$summaryData['total_paid_value']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Total Outstanding</div>
                        <div class="fw-bold fs-6 text-danger font-monospace"><?= formatMoney((float)$summaryData['total_outstanding_due']) ?></div>
                    </div>

                <?php elseif ($type === 'products'): ?>
                    <div class="col">
                        <div class="text-muted small">Catalog Items</div>
                        <div class="fw-bold fs-6"><?= number_format((int)$summaryData['total_items']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Units in Stock</div>
                        <div class="fw-bold fs-6"><?= number_format((int)$summaryData['total_units']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Cost Valuation</div>
                        <div class="fw-bold fs-6 text-primary font-monospace"><?= formatMoney((float)$summaryData['total_cost_value']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Retail Valuation</div>
                        <div class="fw-bold fs-6 text-success font-monospace"><?= formatMoney((float)$summaryData['total_retail_value']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Low / Out Stock</div>
                        <div class="fw-bold fs-6 text-danger"><?= (int)$summaryData['count_low_stock'] ?> / <?= (int)$summaryData['count_out_of_stock'] ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Table of Records -->
    <div class="table-responsive">
        <table class="table table-report table-sm table-bordered align-middle">
            <thead>
                <?php if ($type === 'sales'): ?>
                    <tr>
                        <th>#</th>
                        <th>Order Code</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Grand Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Due</th>
                        <th class="text-center">Status</th>
                    </tr>
                <?php elseif ($type === 'payments'): ?>
                    <tr>
                        <th>#</th>
                        <th>Receipt #</th>
                        <th>Date</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th class="text-end">Amount</th>
                        <th>Received By</th>
                    </tr>
                <?php elseif ($type === 'due'): ?>
                    <tr>
                        <th>#</th>
                        <th>Order Code</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th class="text-end">Grand Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Outstanding Due</th>
                        <th class="text-center">Status</th>
                    </tr>
                <?php elseif ($type === 'orders'): ?>
                    <tr>
                        <th>#</th>
                        <th>Order Code</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th class="text-end">Grand Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Due</th>
                        <th class="text-center">Workflow</th>
                        <th class="text-center">Payment</th>
                    </tr>
                <?php elseif ($type === 'customers'): ?>
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Customer Name</th>
                        <th>Phone</th>
                        <th>Registered</th>
                        <th class="text-center">Orders</th>
                        <th class="text-end">Lifetime Spend</th>
                        <th class="text-end">Outstanding Due</th>
                        <th class="text-center">Status</th>
                    </tr>
                <?php elseif ($type === 'products'): ?>
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Brand</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Selling</th>
                        <th class="text-center">Stock</th>
                    </tr>
                <?php elseif ($type === 'prescriptions'): ?>
                    <tr>
                        <th>#</th>
                        <th>Rx #</th>
                        <th>Exam Date</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>OD (Right Eye)</th>
                        <th>OS (Left Eye)</th>
                        <th class="text-center">PD</th>
                    </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">No records found for the selected criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($records as $r): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <?php if ($type === 'sales'): ?>
                                <td class="fw-bold"><?= e($r['order_code']) ?></td>
                                <td><?= formatDate($r['order_date']) ?></td>
                                <td><?= e($r['customer_name']) ?> (<?= e($r['customer_phone']) ?>)</td>
                                <td class="text-end font-monospace"><?= formatMoney((float)$r['subtotal']) ?></td>
                                <td class="text-end text-danger font-monospace"><?= formatMoney((float)$r['discount']) ?></td>
                                <td class="text-end fw-bold font-monospace"><?= formatMoney((float)$r['grand_total']) ?></td>
                                <td class="text-end text-success font-monospace"><?= formatMoney((float)$r['paid_amount']) ?></td>
                                <td class="text-end text-danger font-monospace"><?= formatMoney((float)$r['due_amount']) ?></td>
                                <td class="text-center text-capitalize"><?= e($r['status']) ?></td>

                            <?php elseif ($type === 'payments'): ?>
                                <td class="fw-bold font-monospace"><?= e($r['receipt_number']) ?></td>
                                <td><?= formatDate($r['payment_date']) ?></td>
                                <td class="font-monospace"><?= e($r['order_code']) ?></td>
                                <td><?= e($r['customer_name']) ?></td>
                                <td class="text-capitalize"><?= str_replace('_', ' ', e($r['payment_method'])) ?></td>
                                <td><?= e($r['transaction_reference'] ?: '—') ?></td>
                                <td class="text-end fw-bold text-success font-monospace"><?= formatMoney((float)$r['amount']) ?></td>
                                <td><?= e($r['staff_name'] ?: 'Staff') ?></td>

                            <?php elseif ($type === 'due'): ?>
                                <td class="fw-bold font-monospace"><?= e($r['order_code']) ?></td>
                                <td><?= formatDate($r['order_date']) ?></td>
                                <td><?= e($r['customer_name']) ?></td>
                                <td><?= e($r['customer_phone']) ?></td>
                                <td class="text-end font-monospace"><?= formatMoney((float)$r['grand_total']) ?></td>
                                <td class="text-end text-success font-monospace"><?= formatMoney((float)$r['paid_amount']) ?></td>
                                <td class="text-end fw-bold text-danger font-monospace"><?= formatMoney((float)$r['due_amount']) ?></td>
                                <td class="text-center text-capitalize"><?= e($r['status']) ?></td>

                            <?php elseif ($type === 'orders'): ?>
                                <td class="fw-bold font-monospace"><?= e($r['order_code']) ?></td>
                                <td><?= formatDate($r['order_date']) ?></td>
                                <td><?= e($r['customer_name']) ?></td>
                                <td><?= e($r['customer_phone']) ?></td>
                                <td class="text-end fw-bold font-monospace"><?= formatMoney((float)$r['grand_total']) ?></td>
                                <td class="text-end text-success font-monospace"><?= formatMoney((float)$r['paid_amount']) ?></td>
                                <td class="text-end text-danger font-monospace"><?= formatMoney((float)$r['due_amount']) ?></td>
                                <td class="text-center text-capitalize"><?= e($r['status']) ?></td>
                                <td class="text-center text-capitalize"><?= e($r['payment_status']) ?></td>

                            <?php elseif ($type === 'customers'): ?>
                                <td class="font-monospace"><?= e($r['customer_code']) ?></td>
                                <td class="fw-bold"><?= e($r['full_name']) ?></td>
                                <td><?= e($r['phone']) ?></td>
                                <td><?= formatDate($r['created_at']) ?></td>
                                <td class="text-center"><?= (int)$r['total_orders'] ?></td>
                                <td class="text-end fw-bold font-monospace"><?= formatMoney((float)$r['total_spent']) ?></td>
                                <td class="text-end text-danger font-monospace"><?= formatMoney((float)$r['total_due']) ?></td>
                                <td class="text-center"><?= ucfirst(e($r['status'])) ?></td>

                            <?php elseif ($type === 'products'): ?>
                                <td class="font-monospace"><?= e($r['product_code']) ?></td>
                                <td class="fw-bold"><?= e($r['name']) ?></td>
                                <td><?= e($r['category_name']) ?></td>
                                <td><?= e($r['brand'] ?: '—') ?></td>
                                <td class="text-end font-monospace"><?= formatMoney((float)$r['purchase_price']) ?></td>
                                <td class="text-end fw-bold font-monospace"><?= formatMoney((float)$r['selling_price']) ?></td>
                                <td class="text-center fw-bold"><?= (int)$r['stock_quantity'] ?></td>

                            <?php elseif ($type === 'prescriptions'): ?>
                                <td class="fw-bold font-monospace">#<?= str_pad($r['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                <td><?= formatDate($r['prescription_date']) ?></td>
                                <td><?= e($r['customer_name']) ?></td>
                                <td><?= e($r['doctor_name'] ?: 'Optometrist') ?></td>
                                <td class="small">SPH: <?= formatOpticalPower($r['right_sph']) ?> CYL: <?= formatOpticalPower($r['right_cyl']) ?> AX: <?= e($r['right_axis']) ?></td>
                                <td class="small">SPH: <?= formatOpticalPower($r['left_sph']) ?> CYL: <?= formatOpticalPower($r['left_cyl']) ?> AX: <?= e($r['left_axis']) ?></td>
                                <td class="text-center"><?= e($r['right_pd'] ? ($r['right_pd'] . '/' . ($r['left_pd'] ?? '')) : '—') ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Signatures -->
    <div class="row mt-5 pt-4">
        <div class="col-4">
            <div class="signature-line">Prepared By (Signature)</div>
        </div>
        <div class="col-4">
            <div class="signature-line">Verified By</div>
        </div>
        <div class="col-4">
            <div class="signature-line">Authorized Signatory</div>
        </div>
    </div>
</div>

</body>
</html>
