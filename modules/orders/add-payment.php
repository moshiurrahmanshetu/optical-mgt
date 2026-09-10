<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Record Additional Payment for Existing Order
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/flash.php';

// Authorization check
requireRole(['admin', 'optician', 'sales_staff']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/orders/index.php');
}

$csrfToken     = $_POST['csrf_token'] ?? '';
$orderId       = (int) ($_POST['order_id'] ?? 0);
$amount        = (float) ($_POST['amount'] ?? 0.0);
$paymentDate   = trim($_POST['payment_date'] ?? date('Y-m-d'));
$paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
$reference     = trim($_POST['reference'] ?? '');
$notes         = trim($_POST['notes'] ?? '');

if (!verifyCsrfToken($csrfToken)) {
    setFlash('error', 'Security session token expired. Please try again.');
    redirect($orderId > 0 ? 'modules/orders/view.php?id=' . $orderId : 'modules/orders/index.php');
}

if ($orderId <= 0) {
    setFlash('error', 'Invalid order identifier.');
    redirect('modules/orders/index.php');
}

$pdo = getDbConnection();

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found.');
    redirect('modules/orders/index.php');
}

if ($order['status'] === 'cancelled') {
    setFlash('error', 'Cannot record payment for a cancelled order.');
    redirect('modules/orders/view.php?id=' . $orderId);
}

// Calculate current authoritative payments sum from DB
$sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = :order_id");
$sumStmt->execute(['order_id' => $orderId]);
$currentPaidTotal = (float) $sumStmt->fetchColumn();
$grandTotal       = (float) $order['grand_total'];
$currentDue       = max(0.0, round($grandTotal - $currentPaidTotal, 2));

if ($currentDue <= 0.005) {
    setFlash('info', 'This order has already been fully paid.');
    redirect('modules/orders/view.php?id=' . $orderId);
}

if ($amount <= 0) {
    setFlash('error', 'Payment amount must be greater than zero.');
    redirect('modules/orders/view.php?id=' . $orderId);
}

// Allow slight rounding tolerance (0.01)
if ($amount > ($currentDue + 0.01)) {
    setFlash('error', 'Payment amount (' . formatMoney($amount) . ') cannot exceed the remaining due amount (' . formatMoney($currentDue) . ').');
    redirect('modules/orders/view.php?id=' . $orderId);
}

$allowedMethods = ['Cash', 'Card', 'Mobile Banking', 'Bank Transfer', 'Other'];
if (!in_array($paymentMethod, $allowedMethods, true)) {
    setFlash('error', 'Invalid payment method selected.');
    redirect('modules/orders/view.php?id=' . $orderId);
}

try {
    $pdo->beginTransaction();

    $userId = currentUserId();

    // Insert Payment
    $payStmt = $pdo->prepare("
        INSERT INTO payments (
            order_id, payment_date, amount, payment_method, reference, notes, received_by, created_at
        ) VALUES (
            :order_id, :payment_date, :amount, :payment_method, :reference, :notes, :received_by, NOW()
        )
    ");
    $payStmt->execute([
        'order_id'       => $orderId,
        'payment_date'   => $paymentDate,
        'amount'         => $amount,
        'payment_method' => $paymentMethod,
        'reference'      => !empty($reference) ? $reference : null,
        'notes'          => !empty($notes) ? $notes : null,
        'received_by'    => $userId,
    ]);

    // Recalculate totals directly from database records
    $newSumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = :order_id");
    $newSumStmt->execute(['order_id' => $orderId]);
    $newPaidTotal = (float) $newSumStmt->fetchColumn();
    $newDueTotal  = max(0.0, round($grandTotal - $newPaidTotal, 2));

    // Update order amounts
    $upStmt = $pdo->prepare("
        UPDATE orders 
        SET paid_amount = :paid_amount, due_amount = :due_amount, updated_at = NOW() 
        WHERE id = :id
    ");
    $upStmt->execute([
        'paid_amount' => $newPaidTotal,
        'due_amount'  => $newDueTotal,
        'id'          => $orderId,
    ]);

    $pdo->commit();

    setFlash('success', 'Payment of <strong>' . formatMoney($amount) . '</strong> (' . e($paymentMethod) . ') successfully recorded for Order <strong>' . e($order['order_code']) . '</strong>.');
    redirect('modules/orders/view.php?id=' . $orderId . '#payment-section');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Failed to record payment: ' . $e->getMessage());
    setFlash('error', 'Database error while saving payment: ' . $e->getMessage());
    redirect('modules/orders/view.php?id=' . $orderId);
}
