<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Toggle Product Status (Active / Inactive)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/flash.php';

requireAuth();

// Only admin can toggle product status
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Invalid request method.');
    redirect('modules/products/index.php');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    setFlash('error', 'Security session expired. Please try again.');
    redirect('modules/products/index.php');
}

$id = (int) ($_POST['id'] ?? 0);
$redirectTarget = $_POST['redirect'] ?? 'index';

if ($id <= 0) {
    setFlash('error', 'Invalid product ID.');
    redirect('modules/products/index.php');
}

$pdo = getDbConnection();

try {
    $stmt = $pdo->prepare("SELECT id, name, product_code, status FROM products WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch();

    if (!$product) {
        setFlash('error', 'Product not found.');
        redirect('modules/products/index.php');
    }

    $newStatus = ($product['status'] === 'active') ? 'inactive' : 'active';

    $updateStmt = $pdo->prepare("UPDATE products SET status = :status, updated_at = NOW() WHERE id = :id");
    $updateStmt->execute([
        'status' => $newStatus,
        'id'     => $id
    ]);

    setFlash('success', 'Product "' . e($product['name']) . '" (' . e($product['product_code']) . ') is now ' . $newStatus . '.');
} catch (Exception $e) {
    error_log('Toggle Product Status Error: ' . $e->getMessage());
    setFlash('error', 'Failed to update product status.');
}

if ($redirectTarget === 'view') {
    redirect('modules/products/view.php?id=' . $id);
} else {
    redirect('modules/products/index.php');
}
