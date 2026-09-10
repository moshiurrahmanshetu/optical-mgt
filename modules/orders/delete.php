<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Delete Pending Order (Administrator Only)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/flash.php';

// Strict Authorization: Administrator Only
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/orders/index.php');
}

$csrfToken = $_POST['csrf_token'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

if (!verifyCsrfToken($csrfToken)) {
    setFlash('error', 'Security token expired. Please try again.');
    redirect('modules/orders/index.php');
}

if ($id <= 0) {
    setFlash('error', 'Invalid order identifier.');
    redirect('modules/orders/index.php');
}

$pdo = getDbConnection();

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found in database.');
    redirect('modules/orders/index.php');
}

// Ensure only Pending orders can be deleted
if ($order['status'] !== 'pending') {
    setFlash('error', 'Order <strong>' . e($order['order_code']) . '</strong> cannot be deleted because it is already in <strong>' . ucfirst(e($order['status'])) . '</strong> status. Processed orders must be cancelled instead to preserve audit history.');
    redirect('modules/orders/view.php?id=' . $id);
}

try {
    $pdo->beginTransaction();

    // Delete associated payments
    $delPayStmt = $pdo->prepare("DELETE FROM payments WHERE order_id = :order_id");
    $delPayStmt->execute(['order_id' => $id]);

    // Delete associated order items
    $delItemsStmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = :order_id");
    $delItemsStmt->execute(['order_id' => $id]);

    // Delete the order
    $delOrderStmt = $pdo->prepare("DELETE FROM orders WHERE id = :id");
    $delOrderStmt->execute(['id' => $id]);

    $pdo->commit();

    setFlash('success', 'Pending Order <strong>' . e($order['order_code']) . '</strong> and its associated line items were permanently deleted.');
    redirect('modules/orders/index.php');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Failed to delete pending order: ' . $e->getMessage());
    setFlash('error', 'Database error while deleting order: ' . $e->getMessage());
    redirect('modules/orders/view.php?id=' . $id);
}
