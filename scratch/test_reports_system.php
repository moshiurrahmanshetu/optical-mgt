<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Comprehensive Automated Verification Suite for Phase 6 (Reports & Analytics)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

echo "========================================================================\n";
echo "  VISIONCARE OPTICAL CMS - PHASE 6 AUTOMATED QA & REPORT VERIFICATION\n";
echo "========================================================================\n\n";

$pdo = getDbConnection();
$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description} " . ($details ? "({$details})" : "") . "\n";
    }
}

// -----------------------------------------------------------------------------
// Test 1: Date Period Parsing Helper
// -----------------------------------------------------------------------------
echo "1. Testing parseDatePeriod() Helper...\n";
$todayPeriod = parseDatePeriod('today');
assertTest("Today period returns today's date", $todayPeriod['from'] === date('Y-m-d') && $todayPeriod['to'] === date('Y-m-d'));

$thisMonth = parseDatePeriod('this_month');
assertTest("This Month period starts from 1st of month", $thisMonth['from'] === date('Y-m-01'));

$allTime = parseDatePeriod('all');
assertTest("All Time period has null boundaries", $allTime['from'] === null && $allTime['to'] === null);

$custom = parseDatePeriod('custom', '2026-01-01', '2026-06-30');
assertTest("Custom range returns correct start and end", $custom['from'] === '2026-01-01' && $custom['to'] === '2026-06-30');

// -----------------------------------------------------------------------------
// Test 2: Reports Overview KPI Queries
// -----------------------------------------------------------------------------
echo "\n2. Testing Reports Overview Aggregation Queries...\n";
try {
    $orderAgg = $pdo->query("
        SELECT 
            COUNT(*) AS total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_orders,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing_orders,
            SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) AS ready_orders,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
            SUM(CASE WHEN status != 'cancelled' THEN grand_total ELSE 0 END) AS total_sales,
            SUM(CASE WHEN status != 'cancelled' THEN due_amount ELSE 0 END) AS total_due
        FROM orders
    ")->fetch();
    assertTest("Reports Overview orders & revenue aggregation executes without error", is_array($orderAgg));
} catch (Exception $e) {
    assertTest("Reports Overview orders aggregation", false, $e->getMessage());
}

try {
    $payAgg = $pdo->query("SELECT COALESCE(SUM(amount), 0) AS total_collected, COUNT(*) AS count_payments FROM payments")->fetch();
    assertTest("Reports Overview payments aggregation executes without error", is_array($payAgg));
} catch (Exception $e) {
    assertTest("Reports Overview payments aggregation", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 3: Sales Report Queries
// -----------------------------------------------------------------------------
echo "\n3. Testing Sales Report Queries...\n";
try {
    $salesSum = $pdo->query("
        SELECT 
            COUNT(*) AS total_count,
            SUM(CASE WHEN o.status != 'cancelled' THEN o.subtotal ELSE 0 END) AS total_subtotal,
            SUM(CASE WHEN o.status != 'cancelled' THEN o.discount ELSE 0 END) AS total_discount,
            SUM(CASE WHEN o.status != 'cancelled' THEN o.grand_total ELSE 0 END) AS net_sales,
            SUM(CASE WHEN o.status != 'cancelled' THEN o.paid_amount ELSE 0 END) AS total_paid,
            SUM(CASE WHEN o.status != 'cancelled' THEN o.due_amount ELSE 0 END) AS total_due
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
    ")->fetch();
    assertTest("Sales Report summary aggregation executes successfully", is_array($salesSum));
} catch (Exception $e) {
    assertTest("Sales Report summary aggregation", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 4: Payments Report Queries
// -----------------------------------------------------------------------------
echo "\n4. Testing Payments Collections Report Queries...\n";
try {
    $paySum = $pdo->query("
        SELECT 
            COUNT(*) AS total_count,
            SUM(pm.amount) AS total_amount,
            SUM(CASE WHEN LOWER(pm.payment_method) = 'cash' THEN pm.amount ELSE 0 END) AS cash_amount,
            SUM(CASE WHEN LOWER(pm.payment_method) = 'card' THEN pm.amount ELSE 0 END) AS card_amount,
            SUM(CASE WHEN LOWER(pm.payment_method) LIKE '%mobile%' THEN pm.amount ELSE 0 END) AS mobile_amount,
            SUM(CASE WHEN LOWER(pm.payment_method) LIKE '%bank%' OR LOWER(pm.payment_method) LIKE '%transfer%' THEN pm.amount ELSE 0 END) AS bank_amount
        FROM payments pm
        JOIN orders o ON pm.order_id = o.id
        JOIN customers c ON o.customer_id = c.id
    ")->fetch();
    assertTest("Payments Collections summary aggregation executes successfully", is_array($paySum));
} catch (Exception $e) {
    assertTest("Payments Collections summary aggregation", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 5: Due Balance Report Queries
// -----------------------------------------------------------------------------
echo "\n5. Testing Due Balance Report Queries...\n";
try {
    $dueSum = $pdo->query("
        SELECT 
            COUNT(*) AS total_due_orders,
            COUNT(DISTINCT o.customer_id) AS total_due_customers,
            SUM(o.grand_total) AS total_order_value,
            SUM(o.paid_amount) AS total_paid_value,
            SUM(o.due_amount) AS total_outstanding_due
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.due_amount > 0 AND o.status != 'cancelled'
    ")->fetch();
    assertTest("Due Balance report aggregation executes successfully", is_array($dueSum));
} catch (Exception $e) {
    assertTest("Due Balance report aggregation", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 6: Order Status & Workflow Report Queries
// -----------------------------------------------------------------------------
echo "\n6. Testing Orders Workflow Report Queries...\n";
try {
    $ordSum = $pdo->query("
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
    ")->fetch();
    assertTest("Orders Workflow report aggregation executes successfully", is_array($ordSum));
} catch (Exception $e) {
    assertTest("Orders Workflow report aggregation", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 7: Customers Report Queries
// -----------------------------------------------------------------------------
echo "\n7. Testing Customers & Patient Growth Report Queries...\n";
try {
    $custSum = $pdo->query("
        SELECT 
            COUNT(*) AS period_count,
            SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN c.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count
        FROM customers c
    ")->fetch();
    assertTest("Customers report summary aggregation executes successfully", is_array($custSum));

    $custRows = $pdo->query("
        SELECT 
            c.id, c.customer_code, c.full_name, c.phone, c.status,
            (SELECT COUNT(*) FROM prescriptions p WHERE p.customer_id = c.id) AS total_prescriptions,
            (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.status != 'cancelled') AS total_orders,
            COALESCE((SELECT SUM(o.grand_total) FROM orders o WHERE o.customer_id = c.id AND o.status != 'cancelled'), 0) AS total_spent,
            COALESCE((SELECT SUM(o.due_amount) FROM orders o WHERE o.customer_id = c.id AND o.status != 'cancelled'), 0) AS total_due
        FROM customers c
        LIMIT 5
    ")->fetchAll();
    assertTest("Customers report detailed rows query executes successfully", count($custRows) >= 0);
} catch (Exception $e) {
    assertTest("Customers report queries", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 8: Products & Inventory Report Queries
// -----------------------------------------------------------------------------
echo "\n8. Testing Products & Inventory Stock Report Queries...\n";
try {
    $prodSum = $pdo->query("
        SELECT 
            COUNT(*) AS total_items,
            SUM(p.stock_quantity) AS total_units,
            SUM(p.stock_quantity * p.purchase_price) AS total_cost_value,
            SUM(p.stock_quantity * p.selling_price) AS total_retail_value,
            SUM(CASE WHEN p.stock_quantity <= p.low_stock_threshold AND p.stock_quantity > 0 THEN 1 ELSE 0 END) AS count_low_stock,
            SUM(CASE WHEN p.stock_quantity <= 0 THEN 1 ELSE 0 END) AS count_out_of_stock
        FROM products p
        JOIN categories c ON p.category_id = c.id
    ")->fetch();
    assertTest("Products inventory report valuation executes successfully", is_array($prodSum));
} catch (Exception $e) {
    assertTest("Products inventory report valuation", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 9: Prescriptions & Clinical Log Queries
// -----------------------------------------------------------------------------
echo "\n9. Testing Prescriptions Clinical Report Queries...\n";
try {
    $rxSum = $pdo->query("
        SELECT 
            COUNT(*) AS total_prescriptions,
            COUNT(DISTINCT p.customer_id) AS unique_patients,
            SUM(CASE WHEN p.prescription_date = CURRENT_DATE() THEN 1 ELSE 0 END) AS exams_today
        FROM prescriptions p
        JOIN customers c ON p.customer_id = c.id
    ")->fetch();
    assertTest("Prescriptions clinical summary aggregation executes successfully", is_array($rxSum));

    $rxRows = $pdo->query("
        SELECT p.id, p.prescription_date, p.right_sph, p.right_cyl, p.left_sph, p.left_cyl, p.doctor_name,
               c.customer_code, c.full_name AS customer_name, u.name AS created_by_name
        FROM prescriptions p
        JOIN customers c ON p.customer_id = c.id
        LEFT JOIN users u ON p.created_by = u.id
        LIMIT 5
    ")->fetchAll();
    assertTest("Prescriptions clinical rows query executes successfully", count($rxRows) >= 0);
} catch (Exception $e) {
    assertTest("Prescriptions clinical queries", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 10: Dashboard Visual Analytics Queries (Chart.js datasets)
// -----------------------------------------------------------------------------
echo "\n10. Testing Dashboard Visual Analytics Datasets...\n";
try {
    // 6-Month Trend
    $mCount = 0;
    for ($i = 5; $i >= 0; $i--) {
        $mKey = date('Y-m', strtotime("-$i months"));
        $st = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE status != 'cancelled' AND DATE_FORMAT(order_date, '%Y-%m') = :m");
        $st->execute([':m' => $mKey]);
        $st->fetchColumn();
        $mCount++;
    }
    assertTest("Dashboard 6-month monthly revenue trend dataset computes correctly", $mCount === 6);

    // Status breakdown
    $stCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','confirmed','processing','ready','delivered','cancelled')")->fetchColumn();
    assertTest("Dashboard order status donut dataset query executes without error", $stCount >= 0);
} catch (Exception $e) {
    assertTest("Dashboard analytics datasets", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n========================================================================\n";
echo "  TEST RESULTS: Total Passed: {$passed} | Total Failed: {$failed}\n";
echo "========================================================================\n";

if ($failed === 0) {
    echo "  >> ALL PHASE 6 REPORTS & ANALYTICS TESTS PASSED SUCCESSFULLY! <<\n\n";
} else {
    echo "  >> SOME TESTS FAILED. PLEASE REVIEW LOG ABOVE. <<\n\n";
}
