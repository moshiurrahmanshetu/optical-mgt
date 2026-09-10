<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Delete Optical Product (Admin Only)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/flash.php';

requireAuth();

// Only admin can delete products
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

if ($id <= 0) {
    setFlash('error', 'Invalid product ID.');
    redirect('modules/products/index.php');
}

$pdo = getDbConnection();

try {
    $stmt = $pdo->prepare("SELECT id, name, product_code FROM products WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch();

    if (!$product) {
        setFlash('error', 'Product not found.');
        redirect('modules/products/index.php');
    }

    $deleteStmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $deleteStmt->execute(['id' => $id]);

    setFlash('success', 'Product "' . e($product['name']) . '" (' . e($product['product_code']) . ') was deleted successfully.');
} catch (PDOException $e) {
    error_log('Delete Product Error: ' . $e->getMessage());
    if ($e->getCode() === '23000') {
        setFlash('error', 'Cannot delete this product because it is currently associated with existing order or inventory records. You may deactivate it instead.');
    } else {
        setFlash('error', 'Database error occurred while deleting product.');
    }
} catch (Exception $e) {
    error_log('Delete Product General Error: ' . $e->getMessage());
    setFlash('error', 'Failed to delete product.');
}

redirect('modules/products/index.php');
