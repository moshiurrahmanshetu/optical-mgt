<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Order Status Workflow & Inventory Stock Deduction / Restoration
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

$csrfToken = $_POST['csrf_token'] ?? '';
$orderId   = (int) ($_POST['order_id'] ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');

if (!verifyCsrfToken($csrfToken)) {
    setFlash('error', 'Security token expired. Please try again.');
    redirect($orderId > 0 ? 'modules/orders/view.php?id=' . $orderId : 'modules/orders/index.php');
}

if ($orderId <= 0) {
    setFlash('error', 'Invalid order identifier.');
    redirect('modules/orders/index.php');
}

$pdo = getDbConnection();

// Fetch current order state
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found in database.');
    redirect('modules/orders/index.php');
}

$currentStatus  = $order['status'];
$stockDeducted  = (int) $order['stock_deducted'];
$validStatuses  = ['pending', 'confirmed', 'processing', 'ready', 'delivered', 'cancelled'];

if (!in_array($newStatus, $validStatuses, true)) {
    setFlash('error', 'Invalid status selected.');
    redirect('modules/orders/view.php?id=' . $orderId);
}

// Define strictly allowed state transitions
$allowedTransitions = [
    'pending'    => ['confirmed', 'cancelled'],
    'confirmed'  => ['processing', 'cancelled'],
    'processing' => ['ready', 'cancelled'],
    'ready'      => ['delivered', 'cancelled'],
    'delivered'  => [], // Terminal state
    'cancelled'  => []  // Terminal state
];

if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
    setFlash('error', "Invalid status transition from \"" . ucfirst($currentStatus) . "\" to \"" . ucfirst($newStatus) . "\".");
    redirect('modules/orders/view.php?id=' . $orderId);
}

// Execute state transition inside atomic transaction
try {
    $pdo->beginTransaction();

    // Fetch all items belonging to this order
    $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
    $itemsStmt->execute(['order_id' => $orderId]);
    $items = $itemsStmt->fetchAll();

    $newStockDeducted = $stockDeducted;

    // CASE 1: Transitioning to 'confirmed' -> Deduct Stock
    if ($newStatus === 'confirmed' && $stockDeducted === 0) {
        foreach ($items as $item) {
            $prodId = (int) $item['product_id'];
            $qty    = (int) $item['quantity'];

            // Lock product row to prevent race conditions / overselling
            $prodStmt = $pdo->prepare("SELECT id, name, stock_quantity FROM products WHERE id = :id FOR UPDATE");
            $prodStmt->execute(['id' => $prodId]);
            $prod = $prodStmt->fetch();

            if (!$prod) {
                throw new Exception("Product ID {$prodId} (\"" . e($item['product_name']) . "\") no longer exists in catalog.");
            }

            if ((int)$prod['stock_quantity'] < $qty) {
                throw new Exception("Cannot confirm order: Insufficient stock for \"{$prod['name']}\". Available: {$prod['stock_quantity']}, Ordered: {$qty}.");
            }

            // Deduct stock
            $deductStmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :id");
            $deductStmt->execute([
                'qty' => $qty,
                'id'  => $prodId
            ]);
        }
        $newStockDeducted = 1;
    }

    // CASE 2: Transitioning to 'cancelled' -> Restore Stock if previously deducted
    if ($newStatus === 'cancelled' && $stockDeducted === 1) {
        foreach ($items as $item) {
            $prodId = (int) $item['product_id'];
            $qty    = (int) $item['quantity'];

            // Restore product stock
            $restoreStmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :id");
            $restoreStmt->execute([
                'qty' => $qty,
                'id'  => $prodId
            ]);
        }
        $newStockDeducted = 0;
    }

    // Update order status and stock flag
    $updateStmt = $pdo->prepare("
        UPDATE orders 
        SET status = :status, stock_deducted = :stock_deducted, updated_at = NOW() 
        WHERE id = :id
    ");
    $updateStmt->execute([
        'status'         => $newStatus,
        'stock_deducted' => $newStockDeducted,
        'id'             => $orderId
    ]);

    $pdo->commit();

    $statusLabels = [
        'confirmed'  => 'Confirmed (Stock committed & deducted)',
        'processing' => 'Processing (Workshop manufacturing)',
        'ready'      => 'Ready (Awaiting customer pickup)',
        'delivered'  => 'Delivered (Completed)',
        'cancelled'  => 'Cancelled' . ($stockDeducted === 1 ? ' (Stock restored to inventory)' : '')
    ];

    setFlash('success', "Order <strong>" . e($order['order_code']) . "</strong> status updated to <strong>" . ($statusLabels[$newStatus] ?? ucfirst($newStatus)) . "</strong>.");
    redirect('modules/orders/view.php?id=' . $orderId);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('error', $e->getMessage());
    redirect('modules/orders/view.php?id=' . $orderId);
}
